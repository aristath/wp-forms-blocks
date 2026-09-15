<?php
/**
 * Gutenberg-compatible AJAX submission check. Run with `wp eval-file`.
 *
 * @package WPFormsBlocks
 */

$_POST = array(
	'action'           => 'wp_block_form_email_submit',
	'_ajax_nonce'      => wp_create_nonce( 'wp-block-form' ),
	'_wp_http_referer' => '/contact/',
	'formAction'       => 'mailto:recipient@example.com',
	'name'             => 'Ada Lovelace',
	'message'          => '<b>Hello</b>',
);
$_REQUEST = $_POST;

add_filter(
	'pre_wp_mail',
	static function ( $preempt, $attributes ) {
		if ( 'recipient@example.com' !== $attributes['to'] ) {
			throw new RuntimeException( 'The port changed the Gutenberg formAction recipient.' );
		}
		if ( false === strpos( $attributes['message'], 'name: Ada Lovelace</br>' ) ) {
			throw new RuntimeException( 'The port changed Gutenberg email field formatting.' );
		}
		if ( false === strpos( $attributes['message'], 'message: <b>Hello</b></br>' ) ) {
			throw new RuntimeException( 'The port changed Gutenberg email HTML handling.' );
		}
		if ( 'Form submission' !== $attributes['subject'] ) {
			throw new RuntimeException( 'The port changed the Gutenberg email subject.' );
		}

		return true;
	},
	10,
	2
);

\WPFormsBlocks\block_core_form_send_email();
