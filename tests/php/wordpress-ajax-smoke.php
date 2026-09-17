<?php
/**
 * AJAX submission and plain-text email check. Run with `wp eval-file`.
 *
 * @package WPFormsBlocks
 */

$_POST    = array(
	'action'           => 'formblox_form_email_submit',
	'_ajax_nonce'      => wp_create_nonce( 'formblox-form' ),
	'_wp_http_referer' => home_url( '/contact/' ),
	'formAction'       => 'mailto:attacker-controlled@example.net',
	'name'             => 'Ada Lovelace',
	'Όνομα'            => 'Αριστάθης',
	'topics'           => array( 'music', 'art' ),
	'message'          => "<b>Hello</b>\nSecond line",
);
$_REQUEST = $_POST;

add_filter(
	'pre_wp_mail',
	static function ( $preempt, $attributes ) {
		$expected_message = sprintf(
			"Form submission from %s\nSource: %s\n\nname: Ada Lovelace\nΌνομα: Αριστάθης\ntopics: music, art\nmessage: Hello\nSecond line\n",
			sanitize_text_field( get_bloginfo( 'name' ) ),
			esc_url_raw( home_url( '/contact/' ) )
		);

		if ( get_option( 'admin_email' ) !== $attributes['to'] ) {
			throw new RuntimeException( 'Email submissions must go to the WordPress administration email.' );
		}
		if ( 'attacker-controlled@example.net' === $attributes['to'] ) {
			throw new RuntimeException( 'The request overrode the server-controlled recipient.' );
		}
		if ( $expected_message !== $attributes['message'] ) {
			throw new RuntimeException( 'Email submissions must use the exact plain-text format.' );
		}
		if ( array( 'Content-Type: text/plain; charset=UTF-8' ) !== $attributes['headers'] ) {
			throw new RuntimeException( 'Email submissions must declare their plain-text content type.' );
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
