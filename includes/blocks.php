<?php
/**
 * Server-side block registration and rendering.
 *
 * @package WPFormsBlocks
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the form blocks unless another provider already registered them.
 */
function wp_forms_blocks_register_blocks() {
	$blocks = array(
		'core/form'                         => array(
			'path'     => 'form',
			'callback' => 'wp_forms_blocks_render_form',
		),
		'core/form-input'                   => array(
			'path'     => 'form-input',
			'callback' => 'wp_forms_blocks_render_input',
		),
		'core/form-submit-button'           => array(
			'path'     => 'form-submit-button',
			'callback' => null,
		),
		'core/form-submission-notification' => array(
			'path'     => 'form-submission-notification',
			'callback' => 'wp_forms_blocks_render_notification',
		),
	);

	$registry = WP_Block_Type_Registry::get_instance();
	foreach ( $blocks as $name => $block ) {
		if ( $registry->is_registered( $name ) ) {
			continue;
		}

		$args = array();
		if ( $block['callback'] ) {
			$args['render_callback'] = $block['callback'];
		}

		register_block_type_from_metadata(
			WP_FORMS_BLOCKS_DIR . 'build/' . $block['path'],
			$args
		);
	}
}

/**
 * Render a form with its configured submission target.
 *
 * @param array  $attributes Block attributes.
 * @param string $content    Saved block HTML.
 * @return string
 */
function wp_forms_blocks_render_form( $attributes, $content ) {
	$processor = new WP_HTML_Tag_Processor( $content );
	if ( ! $processor->next_tag( 'form' ) ) {
		return $content;
	}

	$is_email = empty( $attributes['submissionMethod'] ) || 'email' === $attributes['submissionMethod'];
	if ( $is_email ) {
		$processor->set_attribute( 'action', admin_url( 'admin-ajax.php' ) );
		$processor->set_attribute( 'method', 'post' );
		$processor->remove_attribute( 'enctype' );
	} else {
		$action = isset( $attributes['action'] ) && is_string( $attributes['action'] )
			? $attributes['action']
			: '';
		$action = str_replace(
			array( '{SITE_URL}', '{ADMIN_URL}' ),
			array( site_url(), admin_url() ),
			$action
		);
		$processor->set_attribute( 'action', $action );
		$processor->set_attribute(
			'method',
			isset( $attributes['method'] ) && 'get' === strtolower( $attributes['method'] ) ? 'get' : 'post'
		);
	}

	$extra_fields = '';
	if ( $is_email ) {
		$extra_fields .= wp_forms_blocks_get_email_fields( $attributes );
	}
	$extra_fields = apply_filters( 'render_block_core_form_extra_fields', $extra_fields, $attributes );

	return str_replace(
		'</form>',
		$extra_fields . '</form>',
		$processor->get_updated_html()
	);
}

/**
 * Hide conditionally visible form fields.
 *
 * @param array  $attributes Block attributes.
 * @param string $content    Saved block HTML.
 * @return string
 */
function wp_forms_blocks_render_input( $attributes, $content ) {
	$permission = isset( $attributes['visibilityPermissions'] )
		? $attributes['visibilityPermissions']
		: 'all';

	if ( 'logged-in' === $permission && ! is_user_logged_in() ) {
		return '';
	}

	if ( 'logged-out' === $permission && is_user_logged_in() ) {
		return '';
	}

	return $content;
}

/**
 * Render only the notification matching the current submission result.
 *
 * @param array  $attributes Block attributes.
 * @param string $content    Saved block HTML.
 * @return string
 */
function wp_forms_blocks_render_notification( $attributes, $content ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This read-only query value only selects a notification.
	$result = isset( $_GET['wp-form-result'] )
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- See above.
		? sanitize_key( wp_unslash( $_GET['wp-form-result'] ) )
		: '';
	$type = isset( $attributes['type'] ) ? sanitize_key( $attributes['type'] ) : 'success';
	$show = $result === $type;

	$show = apply_filters(
		'show_form_submission_notification_block',
		$show,
		$attributes,
		$content
	);

	return $show ? $content : '';
}
