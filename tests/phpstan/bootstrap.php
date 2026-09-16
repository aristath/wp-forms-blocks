<?php
/**
 * Constants available to PHPStan when it analyzes plugin files in isolation.
 *
 * @package WPFormsBlocks
 */

if ( ! defined( 'FORMBLOX_DIR' ) ) {
	define( 'FORMBLOX_DIR', __DIR__ . '/../../' );
}

if ( ! defined( 'FORMBLOX_URL' ) ) {
	define( 'FORMBLOX_URL', 'https://example.com/wp-content/plugins/wp-forms-blocks/' );
}
