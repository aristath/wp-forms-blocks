<?php
/**
 * AJAX mail failure check. Run with `wp eval-file`.
 *
 * @package WPFormsBlocks
 */

$_POST = array(
	'action'           => 'wp_block_form_email_submit',
	'_ajax_nonce'      => wp_create_nonce( 'wp-block-form' ),
	'_wp_http_referer' => '/contact/',
	'formAction'       => 'mailto:recipient@example.com',
	'message'          => 'Expected failure',
);
$_REQUEST = $_POST;

add_filter( 'pre_wp_mail', '__return_false' );

\WPFormsBlocks\block_core_form_send_email();
