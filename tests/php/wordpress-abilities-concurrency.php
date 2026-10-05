<?php
/**
 * Execute concurrent, identical creation requests in separate WP-CLI processes.
 *
 * @package WPFormsBlocks
 */

$admin = get_user_by( 'login', 'admin' );
wp_set_current_user( $admin->ID );
if ( 'setup' === $args[0] ) {
	$ability_test_id = wp_insert_post(
		array(
			'post_type'   => 'page',
			'post_status' => 'draft',
		)
	);
	echo (int) $ability_test_id;
	return;
}
if ( in_array( $args[0], array( 'verify', 'verify-stale' ), true ) ) {
	$first  = json_decode( file_get_contents( $args[2] ), true );
	$second = json_decode( file_get_contents( $args[3] ), true );
	if ( 'verify-stale' === $args[0] ) {
		$stale_errors = array_filter(
			array( $first, $second ),
			static function ( $result ) {
				return isset( $result['errors']['formblox_stale_content'] );
			}
		);
		if ( 1 !== count( $stale_errors ) ) {
			throw new RuntimeException( 'Concurrent stale write was accepted: ' . wp_json_encode( array( $first, $second ) ) );
		}
	} elseif ( isset( $first['errors'] ) || isset( $second['errors'] ) || $first['replayed'] === $second['replayed'] ) {
		throw new RuntimeException( 'Concurrent retry failed: ' . wp_json_encode( array( $first, $second ) ) );
	}
	$list = wp_get_ability( 'formblox/list-forms' )->execute( array( 'post_id' => (int) $args[1] ) );
	if ( 1 !== count( $list['forms'] ) ) {
		throw new RuntimeException( 'Concurrent retries created duplicate forms.' );
	}
	echo 'Concurrent creation ' . ( 'verify-stale' === $args[0] ? 'version checks' : 'retries' ) . " passed.\n";
	return;
}
add_filter(
	'wp_insert_post_data',
	static function ( $data ) {
		usleep( 300000 );
		return $data;
	}
);
$result = wp_get_ability( 'formblox/create-form' )->execute(
	array(
		'post_id'         => (int) $args[1],
		'content_version' => hash( 'sha256', '' ),
		'request_id'      => $args[2] ?? 'concurrent-retry',
		'definition'      => array(),
	)
);
echo wp_json_encode( $result );
