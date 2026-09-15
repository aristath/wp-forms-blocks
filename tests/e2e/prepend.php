<?php
/**
 * Select the disposable SQLite database before WordPress loads wp-config.php.
 *
 * @package WPFormsBlocksTests
 */

$wp_forms_blocks_e2e_db_dir = getenv( 'WP_FORMS_BLOCKS_E2E_DB_DIR' );
if ( $wp_forms_blocks_e2e_db_dir ) {
	define( 'DB_DIR', $wp_forms_blocks_e2e_db_dir );
	define( 'DB_FILE', 'e2e.sqlite' );
}
