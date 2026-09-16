<?php
/**
 * Server-side rendering of the `formblox/form` block.
 *
 * @package WPFormsBlocks
 */

namespace WPFormsBlocks;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the `formblox/form` block on server.
 *
 * @param array<string, mixed> $attributes The block attributes.
 * @param string $content The saved content.
 *
 * @return string The content of the block being rendered.
 */
function render_block_formblox_form( $attributes, $content ) {
	wp_enqueue_script_module( '@formblox/form/view' );

	$processed_content = new \WP_HTML_Tag_Processor( $content );
	$processed_content->next_tag( array( 'tag_name' => 'form' ) );
	$submission_method = $attributes['submissionMethod'] ?? 'email';
	$processed_content->set_attribute( 'data-formblox-submission-method', $submission_method );

	// Get the action for this form.
	$action      = '';
	$form_action = $attributes['action'] ?? null;
	if ( 'email' !== $submission_method && is_string( $form_action ) ) {
		$action = str_replace(
			array( '{SITE_URL}', '{ADMIN_URL}' ),
			array( site_url(), admin_url() ),
			$form_action
		);
	}
	$processed_content->set_attribute( 'action', esc_attr( $action ) );

	// Add the method attribute. If it is not set, default to `post`.
	$method = empty( $attributes['method'] ) ? 'post' : $attributes['method'];
	$processed_content->set_attribute( 'method', $method );

	$extra_fields = apply_filters( 'render_block_formblox_form_extra_fields', '', $attributes );
	if ( block_formblox_form_is_privacy_form( $content ) ) {
		$form_id       = 'formblox-privacy-' . wp_generate_uuid4();
		$extra_fields .= sprintf(
			'<input type="hidden" name="formblox-privacy-form-id" value="%1$s"><input type="hidden" name="formblox-privacy-nonce" value="%2$s">',
			esc_attr( $form_id ),
			esc_attr( wp_create_nonce( 'formblox-privacy-request:' . $form_id ) )
		);
	}

	return str_replace(
		'</form>',
		$extra_fields . '</form>',
		$processed_content->get_updated_html()
	);
}

/**
 * Determines whether rendered form markup is a privacy-request form.
 *
 * @param string $content The saved form markup.
 *
 * @return bool Whether both privacy-request marker fields are present.
 */
function block_formblox_form_is_privacy_form( $content ) {
	$processor       = new \WP_HTML_Tag_Processor( $content );
	$has_action      = false;
	$has_request     = false;
	$expected_fields = array(
		'wp-action'          => 'wp_privacy_send_request',
		'wp-privacy-request' => '1',
	);

	while ( $processor->next_tag( array( 'tag_name' => 'input' ) ) ) {
		$name = $processor->get_attribute( 'name' );
		if ( ! is_string( $name ) || ! isset( $expected_fields[ $name ] ) ) {
			continue;
		}

		if ( $expected_fields[ $name ] !== $processor->get_attribute( 'value' ) ) {
			continue;
		}

		if ( 'wp-action' === $name ) {
			$has_action = true;
		} else {
			$has_request = true;
		}
	}

	return $has_action && $has_request;
}

/**
 * Adds extra fields to the form.
 *
 * If the form is a comment form, adds the post ID as a hidden field,
 * to allow the comment to be associated with the post.
 *
 * @param string $extra_fields The extra fields.
 * @param array<string, mixed> $attributes The block attributes.
 *
 * @return string The extra fields.
 */
function block_formblox_form_extra_fields_comment_form( $extra_fields, $attributes ) {
	$form_action = $attributes['action'] ?? null;
	if ( ! empty( $form_action ) && is_string( $form_action ) && str_ends_with( $form_action, '/wp-comments-post.php' ) ) {
		$extra_fields .= '<input type="hidden" name="comment_post_ID" value="' . get_the_ID() . '" id="comment_post_ID">';
	}
	return $extra_fields;
}
add_filter( 'render_block_formblox_form_extra_fields', __NAMESPACE__ . '\\block_formblox_form_extra_fields_comment_form', 10, 2 );

/**
 * Sends an email if the form is a contact form.
 *
 * @return void
 */
function block_formblox_form_send_email() {
	check_ajax_referer( 'formblox-form' );

	// Get the POST data.
	$params = wp_unslash( $_POST );
	// Start building the email content.
	$content = sprintf(
		/* translators: %s: The request URI. */
		__( 'Form submission from %1$s', 'wp-forms-blocks' ) . '</br>',
		'<a href="' . esc_url( get_site_url( null, $params['_wp_http_referer'] ) ) . '">' . get_bloginfo( 'name' ) . '</a>'
	);

	$skip_fields = array( 'formAction', '_ajax_nonce', 'action', '_wp_http_referer' );
	foreach ( $params as $key => $value ) {
		if ( in_array( $key, $skip_fields, true ) ) {
			continue;
		}
		$content .= sanitize_key( $key ) . ': ' . wp_kses_post( $value ) . '</br>';
	}

	// Filter the email content.
	$content = apply_filters( 'render_block_formblox_form_email_content', $content, $params );

	// Email forms always send to the site administrator. The request cannot
	// select or override the recipient.
	$recipient = get_option( 'admin_email' );
	if ( ! is_email( $recipient ) ) {
		wp_send_json_error( false, 500 );
	}

	// Send the email.
	$result = wp_mail(
		$recipient,
		__( 'Form submission', 'wp-forms-blocks' ),
		$content
	);

	if ( ! $result ) {
		wp_send_json_error( $result, 500 );
	}
	wp_send_json_success( $result );
}
add_action( 'wp_ajax_formblox_form_email_submit', __NAMESPACE__ . '\\block_formblox_form_send_email' );
add_action( 'wp_ajax_nopriv_formblox_form_email_submit', __NAMESPACE__ . '\\block_formblox_form_send_email' );

/**
 * Marks a privacy request as failed without leaving it able to block retries.
 *
 * @param int $request_id The privacy request post ID.
 *
 * @return void
 */
function block_formblox_form_mark_privacy_request_failed( $request_id ) {
	$failed = wp_update_post(
		array(
			'ID'            => $request_id,
			'post_status'   => 'request-failed',
			'post_password' => '',
		),
		true
	);
	if ( is_wp_error( $failed ) || ! $failed ) {
		wp_delete_post( $request_id, true );
	}
}

/**
 * Send the data export/remove request if the form is a privacy-request form.
 *
 * @return void
 */
function block_formblox_form_privacy_form() {
	// Get the POST data.
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The submitted nonce is extracted and verified below before any request is processed.
	$params  = wp_unslash( $_POST );
	$form_id = $params['formblox-privacy-form-id'] ?? null;
	$nonce   = $params['formblox-privacy-nonce'] ?? null;

	// Bail early if the submission is not tied to a rendered privacy form.
	if ( empty( $params['wp-action'] )
		|| 'wp_privacy_send_request' !== $params['wp-action']
		|| empty( $params['wp-privacy-request'] )
		|| '1' !== $params['wp-privacy-request']
		|| empty( $params['email'] )
		|| ! is_string( $form_id )
		|| 1 !== preg_match( '/^formblox-privacy-[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $form_id )
		|| ! is_string( $nonce )
		|| ! wp_verify_nonce( $nonce, 'formblox-privacy-request:' . $form_id )
	) {
		return;
	}

	// Get the request types.
	$request_types  = _wp_privacy_action_request_types();
	$requests_found = array();
	foreach ( $request_types as $request_type ) {
		if ( ! empty( $params[ $request_type ] ) ) {
			$requests_found[] = $request_type;
		}
	}

	// Bail early if no requests were found.
	if ( empty( $requests_found ) ) {
		return;
	}

	// Process the requests.
	$actions_errored   = array();
	$actions_performed = array();
	foreach ( $requests_found as $action_name ) {
		// Get the request ID.
		$request_id = wp_create_user_request( $params['email'], $action_name );

		// Bail early if the request ID is invalid.
		if ( is_wp_error( $request_id ) || ! $request_id ) {
			$actions_errored[] = $action_name;
			continue;
		}

		// Send the request email.
		$email_sent = wp_send_user_request( $request_id );
		if ( true !== $email_sent ) {
			// A pending request blocks retries. Keep an auditable failed request,
			// but clear its unusable confirmation key so a retry can create a
			// fresh request. Fall back to deletion if the state cannot be saved.
			block_formblox_form_mark_privacy_request_failed( $request_id );

			$actions_errored[] = $action_name;
			continue;
		}

		$actions_performed[] = $action_name;
	}

	/**
	 * Determine whether the formblox/form-submission-notification block should be shown.
	 *
	 * @param bool   $show       Whether to show the formblox/form-submission-notification block.
	 * @param array  $attributes The block attributes.
	 *
	 * @return bool Whether to show the formblox/form-submission-notification block.
	 */
	$show_notification = static function ( $show, $attributes ) use ( $actions_performed, $actions_errored ) {
		switch ( $attributes['type'] ) {
			case 'success':
				return ! empty( $actions_performed ) && empty( $actions_errored );

			case 'error':
				return ! empty( $actions_errored );

			default:
				return $show;
		}
	};

	// Add filter to show the formblox/form-submission-notification block.
	add_filter( 'formblox_show_form_submission_notification_block', $show_notification, 10, 2 );
}
add_action( 'wp', __NAMESPACE__ . '\\block_formblox_form_privacy_form' );

/**
 * Registers the `formblox/form` block on server.
 *
 * @return void
 */
function register_block_formblox_form() {
	register_block_type_from_metadata(
		__DIR__,
		array(
			'render_callback' => __NAMESPACE__ . '\\render_block_formblox_form',
		)
	);
}
add_action( 'init', __NAMESPACE__ . '\\register_block_formblox_form' );
