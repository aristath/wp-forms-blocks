<?php
/**
 * Server-side rendering of the `formblox/form-submission-notification` block.
 *
 * @package WordPress
 */

namespace WPFormsBlocks;

/**
 * Renders the `formblox/form-submission-notification` block on server.
 *
 * @param array  $attributes The block attributes.
 * @param string $content The saved content.
 *
 * @return string The content of the block being rendered.
 */
function render_block_formblox_form_submission_notification( $attributes, $content ) {
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
	return $content;
}

/**
 * Registers the `formblox/form-submission-notification` block on server.
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
