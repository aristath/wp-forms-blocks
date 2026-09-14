<?php
/**
 * Successful AJAX submission smoke check. Run with `wp eval-file` after activation.
 *
 * @package WPFormsBlocks
 */

$fields = wp_forms_blocks_get_email_fields( array( 'email' => 'recipient@example.com' ) );
preg_match( '/name="wp_forms_blocks_token" value="([^"]+)"/', $fields, $token_match );
preg_match( '/name="wp_forms_blocks_signature" value="([^"]+)"/', $fields, $signature_match );

$signature = $signature_match[1];
$_POST     = array(
	'action'                    => 'wp_forms_blocks_email_submit',
	'wp_forms_blocks_token'     => $token_match[1],
	'wp_forms_blocks_signature' => $signature,
	'_ajax_nonce'               => wp_create_nonce( 'wp_forms_blocks_submit_' . $signature ),
	'name'                      => 'Ada Lovelace',
	'message'                   => '<b>Hello</b>',
);

add_filter(
	'pre_wp_mail',
	static function ( $return, $attributes ) {
		if ( array( 'recipient@example.com' ) !== $attributes['to'] ) {
			throw new RuntimeException( 'The AJAX handler changed the verified recipient.' );
		}
		if ( false === strpos( $attributes['message'], 'name: Ada Lovelace' ) ) {
			throw new RuntimeException( 'The AJAX handler omitted submitted fields.' );
		}
		if ( false !== strpos( $attributes['message'], '<b>' ) ) {
			throw new RuntimeException( 'The AJAX handler did not sanitize submitted HTML.' );
		}

		return true;
	},
	10,
	2
);

wp_forms_blocks_send_email();
