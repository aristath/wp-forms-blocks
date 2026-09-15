<?php
/**
 * AJAX nonce rejection check. Run with `wp eval-file`.
 *
 * @package WPFormsBlocks
 */

$_POST = array(
	'action'           => 'formblox_form_email_submit',
	'_ajax_nonce'      => 'invalid',
	'_wp_http_referer' => '/contact/',
	'formAction'       => 'mailto:recipient@example.com',
);
$_REQUEST = $_POST;

\WPFormsBlocks\block_formblox_form_send_email();
