<?php
/**
 * Rejected AJAX submission checks. Run with `wp eval-file` after activation.
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
);

$rejection_case = getenv( 'WP_FORMS_BLOCKS_REJECTION_CASE' );
if ( 'honeypot' === $rejection_case ) {
	$_POST['wp_forms_blocks_website'] = 'https://spam.example';
} elseif ( 'signature' === $rejection_case ) {
	$_POST['wp_forms_blocks_signature'] = str_repeat( '0', 64 );
} else {
	throw new RuntimeException( 'Unknown AJAX rejection test case.' );
}

add_filter(
	'pre_wp_mail',
	static function () {
		throw new RuntimeException( 'A rejected AJAX submission attempted to send mail.' );
	}
);

wp_forms_blocks_send_email();
