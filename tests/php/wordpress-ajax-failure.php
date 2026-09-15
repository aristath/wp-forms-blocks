<?php
/**
 * AJAX mail failure check. Run with `wp eval-file`.
 *
 * @package WPFormsBlocks
 */

$_POST = array(
	'action'           => 'formblox_form_email_submit',
	'_ajax_nonce'      => wp_create_nonce( 'formblox-form' ),
	'_wp_http_referer' => '/contact/',
	'formAction'       => 'mailto:recipient@example.com',
	'message'          => 'Expected failure',
);
$_REQUEST = $_POST;

add_filter( 'pre_wp_mail', '__return_false' );

\WPFormsBlocks\block_formblox_form_send_email();
