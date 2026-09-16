<?php
/**
 * Bootstrap for isolated PHP unit tests.
 *
 * @package WPFormsBlocks
 */

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
}

$GLOBALS['wp_forms_blocks_test_actions'] = array();
$GLOBALS['wp_forms_blocks_test_filters'] = array();

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * Record an action registration while loading callback files.
	 *
	 * @param string   $hook          Hook name.
	 * @param callable $callback      Hook callback.
	 * @param int      $priority      Hook priority.
	 * @param int      $accepted_args Accepted argument count.
	 */
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['wp_forms_blocks_test_actions'][] = array(
			'hook'          => $hook,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * Record a filter registration while loading callback files.
	 *
	 * @param string   $hook          Hook name.
	 * @param callable $callback      Hook callback.
	 * @param int      $priority      Hook priority.
	 * @param int      $accepted_args Accepted argument count.
	 */
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['wp_forms_blocks_test_filters'][] = array(
			'hook'          => $hook,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);
	}
}

require_once dirname( __DIR__, 2 ) . '/src/form/index.php';
require_once dirname( __DIR__, 2 ) . '/src/form-input/index.php';
require_once dirname( __DIR__, 2 ) . '/src/form-submit-button/index.php';
require_once dirname( __DIR__, 2 ) . '/src/form-submission-notification/index.php';
require_once dirname( __DIR__, 2 ) . '/src/kses-allowed-html.php';
require_once dirname( __DIR__, 2 ) . '/src/script-module-data.php';
