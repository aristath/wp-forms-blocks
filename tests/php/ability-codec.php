<?php
/**
 * Run the PHP codec against real WordPress parsing/escaping without a database.
 *
 * @package WPFormsBlocks
 */

$root = getenv( 'WP_ROOT_DIR' );
$root = $root ? $root : dirname( __DIR__, 5 );
define( 'ABSPATH', $root . '/' );
define( 'WPINC', 'wp-includes' );

/**
 * Keep untranslated fixture text without bootstrapping a database or locale.
 *
 * @param string $text Text.
 * @param string $domain Text domain.
 * @return string Text.
 */
function __( $text, $domain = 'default' ) {
	if ( 'wp-forms-blocks' !== $domain ) {
		throw new InvalidArgumentException( 'Unexpected translation domain.' );
	}
	return $text;
}

foreach ( array( 'compat.php', 'plugin.php', 'functions.php', 'formatting.php', 'kses.php', 'class-wp-error.php', 'class-wp-block-parser.php', 'blocks.php' ) as $file ) {
	require_once ABSPATH . WPINC . '/' . $file;
}
add_filter(
	'pre_option_blog_charset',
	static function () {
		return 'UTF-8';
	}
);
if ( file_exists( ABSPATH . WPINC . '/utf8.php' ) ) {
	require_once ABSPATH . WPINC . '/utf8.php';
}
require_once ABSPATH . WPINC . '/html-api/class-wp-html-tag-processor.php';
require_once ABSPATH . WPINC . '/class-wp-token-map.php';
foreach ( glob( ABSPATH . WPINC . '/html-api/*.php' ) as $file ) {
	require_once $file;
}
require_once dirname( __DIR__, 2 ) . '/includes/class-form-codec.php';
$request = json_decode( file_get_contents( 'php://stdin' ), true );
try {
	$result = WPFormsBlocks\Form_Codec::{$request['operation']}( ...$request['args'] );
	echo wp_json_encode( array( 'result' => $result ) );
} catch ( InvalidArgumentException $error ) {
	echo wp_json_encode( array( 'error' => $error->getMessage() ) );
}
