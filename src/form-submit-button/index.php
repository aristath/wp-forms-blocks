<?php
/**
 * Registration of the `formblox/form-submit-button` block.
 *
 * @package WPFormsBlocks
 */

namespace WPFormsBlocks;

/**
 * Registers the `formblox/form-submit-button` block on server.
 */
function register_block_formblox_form_submit_button() {
	register_block_type_from_metadata( __DIR__ );
}
add_action( 'init', __NAMESPACE__ . '\\register_block_formblox_form_submit_button' );
