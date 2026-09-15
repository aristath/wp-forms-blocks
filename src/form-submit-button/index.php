<?php
/**
 * Registration of the `core/form-submit-button` block.
 *
 * @package WPFormsBlocks
 */

namespace WPFormsBlocks;

/**
 * Registers the `core/form-submit-button` block on server.
 */
function register_block_core_form_submit_button() {
	register_block_type_from_metadata( __DIR__ );
}
add_action( 'init', __NAMESPACE__ . '\\register_block_core_form_submit_button' );
