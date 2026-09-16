<?php
/**
 * Loads Plugin Check's Composer dependencies for the isolated CLI runner.
 *
 * @package WPFormsBlocks
 */

$plugin_check_dir = getenv( 'WP_FORMS_BLOCKS_PLUGIN_CHECK_DIR' );

if ( ! is_string( $plugin_check_dir ) || ! file_exists( $plugin_check_dir . '/vendor/autoload.php' ) ) {
	throw new RuntimeException( 'Plugin Check was not downloaded correctly.' );
}

define( 'WP_PLUGIN_CHECK_VERSION', '2.1.0' );
define( 'WP_PLUGIN_CHECK_MINIMUM_PHP', '7.4' );
define( 'WP_PLUGIN_CHECK_MAIN_FILE', $plugin_check_dir . '/plugin.php' );
define( 'WP_PLUGIN_CHECK_PLUGIN_DIR_PATH', $plugin_check_dir . '/' );
define( 'WP_PLUGIN_CHECK_PLUGIN_DIR_URL', '' );

require_once $plugin_check_dir . '/vendor/autoload.php';
