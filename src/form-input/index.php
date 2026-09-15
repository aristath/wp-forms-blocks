<?php
/**
 * Server-side rendering of the `formblox/form-input` block.
 *
 * @package WordPress
 */

namespace WPFormsBlocks;

/**
 * Renders the `formblox/form-input` block on server.
 *
 * @param array  $attributes The block attributes.
 * @param string $content The saved content.
 *
 * @return string The content of the block being rendered.
 */
function render_block_formblox_form_input( $attributes, $content ) {
	$visibility_permissions = 'all';
	if ( isset( $attributes['visibilityPermissions'] ) ) {
		$visibility_permissions = $attributes['visibilityPermissions'];
	}

	$user_logged_in = is_user_logged_in();

	if ( 'logged-in' === $visibility_permissions && ! $user_logged_in ) {
		return '';
	}
	if ( 'logged-out' === $visibility_permissions && $user_logged_in ) {
		return '';
	}
	return $content;
}

/**
 * Registers the `formblox/form-input` block on server.
 */
function register_block_formblox_form_input() {
	register_block_type_from_metadata(
		__DIR__,
		array(
			'render_callback' => __NAMESPACE__ . '\\render_block_formblox_form_input',
		)
	);
}
add_action( 'init', __NAMESPACE__ . '\\register_block_formblox_form_input' );
