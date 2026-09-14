<?php
/**
 * Lightweight tests for submission primitives that do not need a database.
 *
 * @package WPFormsBlocks
 */

define( 'ABSPATH', dirname( __DIR__, 3 ) . '/' );

function wp_json_encode( $value ) {
	return json_encode( $value );
}

function sanitize_email( $value ) {
	return filter_var( $value, FILTER_SANITIZE_EMAIL );
}

function is_email( $value ) {
	return false !== filter_var( $value, FILTER_VALIDATE_EMAIL );
}

function get_option( $name ) {
	return 'admin@example.com';
}

function wp_salt( $scheme = 'auth' ) {
	return 'test-salt-' . $scheme;
}

function wp_create_nonce( $action ) {
	return substr( hash( 'sha256', $action ), 0, 10 );
}

function wp_referer_field() {
	return '<input type="hidden" name="_wp_http_referer" value="/contact/">';
}

function esc_attr( $value ) {
	return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
}

function esc_html__( $value ) {
	return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
}

function sanitize_textarea_field( $value ) {
	return trim( strip_tags( $value ) );
}

require_once dirname( __DIR__, 2 ) . '/includes/submissions.php';

/**
 * Fail the script when an assertion is false.
 *
 * @param bool   $condition Assertion result.
 * @param string $message   Failure description.
 */
function wp_forms_blocks_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

$recipients = wp_forms_blocks_get_recipients(
	array(
		'email' => 'first@example.com, invalid, first@example.com, second@example.org',
	)
);
wp_forms_blocks_assert(
	array( 'first@example.com', 'second@example.org' ) === $recipients,
	'Recipient normalization must reject invalid addresses and remove duplicates.'
);

$fallback = wp_forms_blocks_get_recipients( array() );
wp_forms_blocks_assert(
	array( 'admin@example.com' ) === $fallback,
	'An empty configuration must fall back to the site administrator.'
);

$fields = wp_forms_blocks_get_email_fields( array( 'email' => 'recipient@example.com' ) );
preg_match( '/name="wp_forms_blocks_token" value="([^"]+)"/', $fields, $token_match );
preg_match( '/name="wp_forms_blocks_signature" value="([^"]+)"/', $fields, $signature_match );
wp_forms_blocks_assert( isset( $token_match[1], $signature_match[1] ), 'Signed fields must be rendered.' );
wp_forms_blocks_assert(
	false !== strpos( $fields, 'name="_wp_http_referer"' ),
	'The submission source field must be rendered.'
);

$verified = wp_forms_blocks_verify_recipients( $token_match[1], $signature_match[1] );
wp_forms_blocks_assert(
	array( 'recipient@example.com' ) === $verified,
	'A valid signed recipient must round-trip.'
);
wp_forms_blocks_assert(
	false === wp_forms_blocks_verify_recipients( $token_match[1] . 'x', $signature_match[1] ),
	'A modified recipient token must be rejected.'
);
wp_forms_blocks_assert(
	false === wp_forms_blocks_verify_recipients( $token_match[1], str_repeat( '0', 64 ) ),
	'A modified recipient signature must be rejected.'
);
wp_forms_blocks_assert(
	false === wp_forms_blocks_verify_recipients( '!', hash_hmac( 'sha256', '!', wp_salt( 'auth' ) ) ),
	'Invalid base64 recipient data must be rejected.'
);

wp_forms_blocks_assert(
	'one, two' === wp_forms_blocks_format_submitted_value( array( '<b>one</b>', 'two' ) ),
	'Nested submitted values must be flattened and sanitized.'
);

echo "Submission security smoke tests passed.\n";
