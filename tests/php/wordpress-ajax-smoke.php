<?php
/**
 * Gutenberg-compatible AJAX submission check. Run with `wp eval-file`.
 *
 * @package WPFormsBlocks
 */

$_POST    = array(
	'action'           => 'formblox_form_email_submit',
	'_ajax_nonce'      => wp_create_nonce( 'formblox-form' ),
	'_wp_http_referer' => '/contact/',
	'formAction'       => 'mailto:attacker-controlled@example.net',
	'name'             => 'Ada Lovelace',
	'message'          => '<b>Hello</b>',
);
$_REQUEST = $_POST;

add_filter(
	'pre_wp_mail',
	static function ( $preempt, $attributes ) {
		if ( get_option( 'admin_email' ) !== $attributes['to'] ) {
			throw new RuntimeException( 'Email submissions must go to the WordPress administration email.' );
		}
		if ( 'attacker-controlled@example.net' === $attributes['to'] ) {
			throw new RuntimeException( 'The request overrode the server-controlled recipient.' );
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

\WPFormsBlocks\block_formblox_form_send_email();
