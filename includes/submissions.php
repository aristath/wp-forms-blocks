<?php
/**
 * Form submission handlers.
 *
 * @package WPFormsBlocks
 */

defined( 'ABSPATH' ) || exit;

/**
 * Convert binary data to URL-safe base64.
 *
 * @param string $value Raw value.
 * @return string
 */
function wp_forms_blocks_base64url_encode( $value ) {
	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Encoding a signed JSON payload, not executable code.
	return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' );
}

/**
 * Decode URL-safe base64 data.
 *
 * @param string $value Encoded value.
 * @return string|false
 */
function wp_forms_blocks_base64url_decode( $value ) {
	$padding = strlen( $value ) % 4;
	if ( $padding ) {
		$value .= str_repeat( '=', 4 - $padding );
	}

	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decoding a signed JSON payload, not executable code.
	return base64_decode( strtr( $value, '-_', '+/' ), true );
}

/**
 * Normalize the configured recipient list.
 *
 * @param array $attributes Form block attributes.
 * @return string[]
 */
function wp_forms_blocks_get_recipients( $attributes ) {
	$configured = isset( $attributes['email'] ) && is_string( $attributes['email'] )
		? $attributes['email']
		: '';
	$recipients = array_filter(
		array_map( 'sanitize_email', preg_split( '/\s*,\s*/', $configured ) )
	);
	$recipients = array_values( array_unique( array_filter( $recipients, 'is_email' ) ) );

	if ( empty( $recipients ) ) {
		$admin_email = sanitize_email( get_option( 'admin_email' ) );
		if ( is_email( $admin_email ) ) {
			$recipients[] = $admin_email;
		}
	}

	return $recipients;
}

/**
 * Generate signed hidden fields for an email form.
 *
 * The recipient remains visible in the document, but cannot be changed without
 * invalidating the HMAC. This prevents the public AJAX endpoint being used as
 * an arbitrary mail relay.
 *
 * @param array $attributes Form block attributes.
 * @return string
 */
function wp_forms_blocks_get_email_fields( $attributes ) {
	$payload   = wp_json_encode( wp_forms_blocks_get_recipients( $attributes ) );
	$token     = wp_forms_blocks_base64url_encode( $payload );
	$signature = hash_hmac( 'sha256', $token, wp_salt( 'auth' ) );
	$nonce     = wp_create_nonce( 'wp_forms_blocks_submit_' . $signature );

	return sprintf(
		'<input type="hidden" name="action" value="wp_forms_blocks_email_submit">' .
		'<input type="hidden" name="wp_forms_blocks_token" value="%1$s">' .
		'<input type="hidden" name="wp_forms_blocks_signature" value="%2$s">' .
		'<input type="hidden" name="_ajax_nonce" value="%3$s">' .
		'%4$s' .
		'<label class="wp-forms-blocks-honeypot" aria-hidden="true">%5$s<input type="text" name="wp_forms_blocks_website" value="" tabindex="-1" autocomplete="off"></label>',
		esc_attr( $token ),
		esc_attr( $signature ),
		esc_attr( $nonce ),
		wp_referer_field( false ),
		esc_html__( 'Leave this field empty', 'wp-forms-blocks' )
	);
}

/**
 * Recover and verify recipients from a submitted token.
 *
 * @param string $token     Encoded recipient payload.
 * @param string $signature Submitted signature.
 * @return string[]|false
 */
function wp_forms_blocks_verify_recipients( $token, $signature ) {
	$expected = hash_hmac( 'sha256', $token, wp_salt( 'auth' ) );
	if ( ! hash_equals( $expected, $signature ) ) {
		return false;
	}

	$decoded = wp_forms_blocks_base64url_decode( $token );
	if ( false === $decoded ) {
		return false;
	}

	$recipients = json_decode( $decoded, true );
	if ( ! is_array( $recipients ) || empty( $recipients ) ) {
		return false;
	}

	foreach ( $recipients as $recipient ) {
		if ( ! is_string( $recipient ) || ! is_email( $recipient ) ) {
			return false;
		}
	}

	return array_values( array_unique( $recipients ) );
}

/**
 * Convert a submitted value into safe, readable plain text.
 *
 * @param mixed $value Submitted value.
 * @return string
 */
function wp_forms_blocks_format_submitted_value( $value ) {
	if ( is_array( $value ) ) {
		return implode( ', ', array_map( 'wp_forms_blocks_format_submitted_value', $value ) );
	}

	return sanitize_textarea_field( (string) $value );
}

/**
 * Handle an email-form AJAX submission.
 */
function wp_forms_blocks_send_email() {
	$params    = wp_unslash( $_POST );
	$token     = isset( $params['wp_forms_blocks_token'] ) ? sanitize_text_field( $params['wp_forms_blocks_token'] ) : '';
	$signature = isset( $params['wp_forms_blocks_signature'] ) ? sanitize_text_field( $params['wp_forms_blocks_signature'] ) : '';
	$honeypot  = isset( $params['wp_forms_blocks_website'] ) ? trim( (string) $params['wp_forms_blocks_website'] ) : '';

	if ( '' !== $honeypot ) {
		wp_send_json_error( array( 'message' => __( 'The submission could not be processed.', 'wp-forms-blocks' ) ), 400 );
	}

	$recipients = wp_forms_blocks_verify_recipients( $token, $signature );
	$nonce      = isset( $params['_ajax_nonce'] ) ? sanitize_text_field( $params['_ajax_nonce'] ) : '';
	if ( false === $recipients || ! wp_verify_nonce( $nonce, 'wp_forms_blocks_submit_' . $signature ) ) {
		wp_send_json_error( array( 'message' => __( 'The form security check failed.', 'wp-forms-blocks' ) ), 403 );
	}

	$skip   = array(
		'action',
		'_ajax_nonce',
		'_wp_http_referer',
		'wp_forms_blocks_signature',
		'wp_forms_blocks_token',
		'wp_forms_blocks_website',
	);
	$source = '';
	if ( isset( $params['_wp_http_referer'] ) && is_string( $params['_wp_http_referer'] ) ) {
		$referer = sanitize_text_field( $params['_wp_http_referer'] );
		if ( 0 === strpos( $referer, '/' ) && 0 !== strpos( $referer, '//' ) ) {
			$source = esc_url_raw( site_url( $referer ) );
		}
	}

	$heading = sprintf(
		/* translators: %s: Website name. */
		__( 'Form submission from %s', 'wp-forms-blocks' ),
		wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
	);
	if ( '' !== $source ) {
		$heading .= ' (' . $source . ')';
	}

	$lines = array(
		$heading,
		'',
	);

	foreach ( $params as $key => $value ) {
		if ( in_array( $key, $skip, true ) ) {
			continue;
		}
		$lines[] = sanitize_text_field( (string) $key ) . ': ' . wp_forms_blocks_format_submitted_value( $value );
	}

	$content = implode( "\n", $lines );
	$content = apply_filters( 'render_block_core_form_email_content', $content, $params );
	$subject = apply_filters( 'wp_forms_blocks_email_subject', __( 'Form submission', 'wp-forms-blocks' ), $params );
	$sent    = wp_mail( $recipients, $subject, $content );

	if ( ! $sent ) {
		wp_send_json_error( array( 'message' => __( 'The email could not be sent.', 'wp-forms-blocks' ) ), 500 );
	}

	wp_send_json_success();
}

/**
 * Add the current post ID to the comment form variation.
 *
 * @param string $fields     Existing hidden fields.
 * @param array  $attributes Form block attributes.
 * @return string
 */
function wp_forms_blocks_add_comment_fields( $fields, $attributes ) {
	$action = isset( $attributes['action'] ) && is_string( $attributes['action'] )
		? $attributes['action']
		: '';

	if ( preg_match( '#/wp-comments-post\.php$#', $action ) ) {
		$fields .= sprintf(
			'<input type="hidden" name="comment_post_ID" value="%d" id="comment_post_ID">',
			get_the_ID()
		);
	}

	return $fields;
}

/**
 * Add anti-bot and nonce fields to the privacy request variation.
 *
 * @param string $fields     Existing hidden fields.
 * @param array  $attributes Form block attributes.
 * @return string
 */
function wp_forms_blocks_add_privacy_fields( $fields, $attributes ) {
	if ( empty( $attributes['anchor'] ) || 'gdpr-form' !== $attributes['anchor'] ) {
		return $fields;
	}

	$fields .= wp_nonce_field( 'wp_forms_blocks_privacy_request', 'wp_forms_blocks_privacy_nonce', false, false );
	$fields .= '<label class="wp-forms-blocks-honeypot" aria-hidden="true">' .
		esc_html__( 'Leave this field empty', 'wp-forms-blocks' ) .
		'<input type="text" name="wp_forms_blocks_website" value="" tabindex="-1" autocomplete="off"></label>';

	return $fields;
}

/**
 * Process the privacy request form variation.
 */
function wp_forms_blocks_process_privacy_request() {
	if ( 'POST' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' ) ) {
		return;
	}

	$params = wp_unslash( $_POST );
	if (
		empty( $params['wp-action'] ) ||
		'wp_privacy_send_request' !== $params['wp-action'] ||
		empty( $params['wp-privacy-request'] ) ||
		'1' !== $params['wp-privacy-request']
	) {
		return;
	}

	$nonce = isset( $params['wp_forms_blocks_privacy_nonce'] )
		? sanitize_text_field( $params['wp_forms_blocks_privacy_nonce'] )
		: '';
	if (
		! wp_verify_nonce( $nonce, 'wp_forms_blocks_privacy_request' ) ||
		! empty( $params['wp_forms_blocks_website'] ) ||
		empty( $params['email'] ) ||
		! is_email( $params['email'] )
	) {
		return;
	}

	$request_types = _wp_privacy_action_request_types();
	$requested     = array();
	foreach ( $request_types as $request_type ) {
		if ( ! empty( $params[ $request_type ] ) ) {
			$requested[] = $request_type;
		}
	}

	if ( empty( $requested ) ) {
		return;
	}

	$performed = array();
	$errored   = array();
	foreach ( $requested as $request_type ) {
		$request_id = wp_create_user_request( sanitize_email( $params['email'] ), $request_type );
		if ( is_wp_error( $request_id ) ) {
			$errored[] = $request_type;
			continue;
		}

		$result = wp_send_user_request( $request_id );
		if ( is_wp_error( $result ) ) {
			$errored[] = $request_type;
			continue;
		}
		$performed[] = $request_type;
	}

	add_filter(
		'show_form_submission_notification_block',
		static function ( $show, $attributes ) use ( $performed, $errored ) {
			$type = isset( $attributes['type'] ) ? $attributes['type'] : '';
			if ( 'success' === $type ) {
				return ! empty( $performed ) && empty( $errored );
			}
			if ( 'error' === $type ) {
				return ! empty( $errored );
			}
			return $show;
		},
		10,
		2
	);
}
