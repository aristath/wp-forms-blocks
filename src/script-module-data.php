<?php
/**
 * Additional data for the Form block view module.
 *
 * @package WPFormsBlocks
 */

namespace WPFormsBlocks;

/**
 * Additional data to expose to the view script module in the Form block.
 *
 * @param array $data Existing script module data.
 * @return array The script module data.
 */
function gutenberg_block_core_form_view_script_module( $data ) {
	$data['nonce']   = wp_create_nonce( 'wp-block-form' );
	$data['ajaxUrl'] = admin_url( 'admin-ajax.php' );
	$data['action']  = 'wp_block_form_email_submit';

	return $data;
}
add_filter(
	'script_module_data_@wordpress/block-library/form/view',
	__NAMESPACE__ . '\\gutenberg_block_core_form_view_script_module'
);
