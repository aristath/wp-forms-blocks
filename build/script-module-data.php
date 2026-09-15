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
function formblox_form_view_script_module( $data ) {
	$data['nonce']   = wp_create_nonce( 'formblox-form' );
	$data['ajaxUrl'] = admin_url( 'admin-ajax.php' );
	$data['action']  = 'formblox_form_email_submit';

	return $data;
}
add_filter(
	'script_module_data_@formblox/form/view',
	__NAMESPACE__ . '\\formblox_form_view_script_module'
);
