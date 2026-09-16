<?php
/**
 * Unit tests for PHP hook registration.
 *
 * @package WPFormsBlocks
 */

namespace WPFormsBlocks\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Verifies that every ported server callback is connected to WordPress.
 */
final class HookRegistrationTest extends TestCase {
	/**
	 * Every required action is registered.
	 */
	public function test_required_actions_are_registered(): void {
		$hooks = array_column( $GLOBALS['wp_forms_blocks_test_actions'], 'hook' );

		$this->assertSame( 4, count( array_keys( $hooks, 'init', true ) ) );
		$this->assertContains( 'wp_ajax_formblox_form_email_submit', $hooks );
		$this->assertContains( 'wp_ajax_nopriv_formblox_form_email_submit', $hooks );
		$this->assertContains( 'wp', $hooks );
	}

	/**
	 * Every required filter is registered with its full argument count.
	 */
	public function test_required_filters_are_registered(): void {
		$filters = array_column( $GLOBALS['wp_forms_blocks_test_filters'], null, 'hook' );

		$this->assertArrayHasKey( 'render_block_formblox_form_extra_fields', $filters );
		$this->assertSame( 2, $filters['render_block_formblox_form_extra_fields']['accepted_args'] );
		$this->assertArrayHasKey( 'wp_kses_allowed_html', $filters );
		$this->assertArrayHasKey( 'script_module_data_@formblox/form/view', $filters );
	}
}
