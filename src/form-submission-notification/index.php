<?php
/**
 * Server-side rendering of the `formblox/form-submission-notification` block.
 *
 * @package WPFormsBlocks
 */

namespace WPFormsBlocks;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the `formblox/form-submission-notification` block on server.
 *
 * @param array<string, mixed> $attributes The block attributes.
 * @param string $content The saved content.
 *
 * @return string The content of the block being rendered.
 */
function render_block_formblox_form_submission_notification( $attributes, $content ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This read-only query parameter only controls whether saved notification content is displayed.
	$show = isset( $_GET['formblox-form-result'] ) && sanitize_text_field( wp_unslash( $_GET['formblox-form-result'] ) ) === $attributes['type'];
	/**
	 * Filters whether to show the form submission notification block.
	 *
	 * @param bool   $show       Whether to show the form submission notification block.
	 * @param array  $attributes The block attributes.
	 * @param string $content    The saved content.
	 *
	 * @return bool Whether to show the form submission notification block.
	 */
	$show = apply_filters( 'formblox_show_form_submission_notification_block', $show, $attributes, $content );
	if ( ! $show ) {
		return '';
	}

	if ( false === strpos( $content, 'wp-block-formblox-form-submission-notification' ) ) {
		return $content;
	}

	$type      = $attributes['type'] ?? 'success';
	$processor = new \WP_HTML_Tag_Processor( $content );
	if ( $processor->next_tag( array( 'class_name' => 'wp-block-formblox-form-submission-notification' ) ) ) {
		$processor->set_attribute( 'role', 'error' === $type ? 'alert' : 'status' );
		$processor->set_attribute( 'aria-live', 'error' === $type ? 'assertive' : 'polite' );
		$processor->set_attribute( 'aria-atomic', 'true' );
		$processor->set_attribute( 'tabindex', '-1' );
		return $processor->get_updated_html();
	}

	return $content;
}

/**
 * Registers the `formblox/form-submission-notification` block on server.
 *
 * @return void
 */
function register_block_formblox_form_submission_notification() {
	register_block_type_from_metadata(
		__DIR__,
		array(
			'render_callback' => __NAMESPACE__ . '\\render_block_formblox_form_submission_notification',
		)
	);
}
add_action( 'init', __NAMESPACE__ . '\\register_block_formblox_form_submission_notification' );
