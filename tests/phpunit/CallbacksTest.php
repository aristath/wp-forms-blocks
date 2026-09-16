<?php
/**
 * Unit tests for standalone PHP callbacks.
 *
 * @package WPFormsBlocks
 */

namespace WPFormsBlocks\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use function WPFormsBlocks\block_formblox_form_extra_fields_comment_form;
use function WPFormsBlocks\block_formblox_form_send_email;
use function WPFormsBlocks\formblox_form_view_script_module;
use function WPFormsBlocks\gutenberg_kses_allowed_html;
use function WPFormsBlocks\render_block_formblox_form_input;
use function WPFormsBlocks\render_block_formblox_form_submission_notification;

/**
 * Tests callbacks that can be exercised without booting WordPress.
 */
final class CallbacksTest extends TestCase {
	/**
	 * Set up Brain Monkey.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$_GET  = array();
		$_POST = array();
	}

	/**
	 * Tear down Brain Monkey.
	 */
	protected function tearDown(): void {
		$_GET  = array();
		$_POST = array();
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Input visibility honors all three supported permission values.
	 */
	public function test_input_visibility_permissions(): void {
		Functions\when( 'is_user_logged_in' )->justReturn( false );

		$this->assertSame( '<input>', render_block_formblox_form_input( array(), '<input>' ) );
		$this->assertSame( '', render_block_formblox_form_input( array( 'visibilityPermissions' => 'logged-in' ), '<input>' ) );
		$this->assertSame( '<input>', render_block_formblox_form_input( array( 'visibilityPermissions' => 'logged-out' ), '<input>' ) );
	}

	/**
	 * Logged-in users receive the inverse visibility result.
	 */
	public function test_input_visibility_permissions_for_logged_in_user(): void {
		Functions\when( 'is_user_logged_in' )->justReturn( true );

		$this->assertSame( '<input>', render_block_formblox_form_input( array( 'visibilityPermissions' => 'logged-in' ), '<input>' ) );
		$this->assertSame( '', render_block_formblox_form_input( array( 'visibilityPermissions' => 'logged-out' ), '<input>' ) );
	}

	/**
	 * Comment forms receive the current post identifier.
	 */
	public function test_comment_form_extra_field(): void {
		Functions\when( 'get_the_ID' )->justReturn( 42 );

		$this->assertSame(
			'<input type="hidden" name="comment_post_ID" value="42" id="comment_post_ID">',
			block_formblox_form_extra_fields_comment_form( '', array( 'action' => '{SITE_URL}/wp-comments-post.php' ) )
		);
		$this->assertSame(
			'unchanged',
			block_formblox_form_extra_fields_comment_form( 'unchanged', array( 'action' => '/custom-endpoint' ) )
		);
	}

	/**
	 * The view module receives only the server-generated submission settings.
	 */
	public function test_view_script_module_data(): void {
		Functions\expect( 'wp_create_nonce' )
			->once()
			->with( 'formblox-form' )
			->andReturn( 'nonce' );
		Functions\expect( 'admin_url' )
			->once()
			->with( 'admin-ajax.php' )
			->andReturn( 'https://example.com/wp-admin/admin-ajax.php' );

		$this->assertSame(
			array(
				'existing' => true,
				'nonce'    => 'nonce',
				'ajaxUrl'  => 'https://example.com/wp-admin/admin-ajax.php',
				'action'   => 'formblox_form_email_submit',
			),
			formblox_form_view_script_module( array( 'existing' => true ) )
		);
	}

	/**
	 * Mail transport failures return an HTTP 500 JSON error.
	 */
	public function test_mail_failure_returns_http_500_json_error(): void {
		$_POST = array(
			'_wp_http_referer' => '/contact/',
			'message'          => 'Expected failure',
		);

		Functions\expect( 'check_ajax_referer' )->once()->with( 'formblox-form' );
		Functions\when( 'wp_unslash' )->returnArg();
		Functions\when( '__' )->returnArg();
		Functions\when( 'get_site_url' )->justReturn( 'https://example.com/contact/' );
		Functions\when( 'esc_url' )->returnArg();
		Functions\when( 'get_bloginfo' )->justReturn( 'Example' );
		Functions\when( 'sanitize_key' )->returnArg();
		Functions\when( 'wp_kses_post' )->returnArg();
		Functions\when( 'apply_filters' )->alias(
			static function ( $hook, $value ) {
				return $value;
			}
		);
		Functions\when( 'get_option' )->justReturn( 'admin@example.com' );
		Functions\when( 'is_email' )->justReturn( true );
		Functions\expect( 'wp_mail' )
			->once()
			->with( 'admin@example.com', 'Form submission', \Mockery::type( 'string' ) )
			->andReturn( false );
		Functions\expect( 'wp_send_json_error' )
			->once()
			->with( false, 500 )
			->andThrow( new \RuntimeException( 'JSON error response sent.' ) );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'JSON error response sent.' );
		block_formblox_form_send_email();
	}

	/**
	 * KSES receives the complete set of form elements and attributes.
	 */
	public function test_kses_form_elements(): void {
		$allowed = gutenberg_kses_allowed_html( array( 'p' => array() ) );

		$this->assertArrayHasKey( 'p', $allowed );
		$this->assertSame(
			array( 'type', 'name', 'value', 'checked', 'required', 'aria-required', 'class' ),
			array_keys( $allowed['input'] )
		);
		$this->assertSame( array( 'for', 'class' ), array_keys( $allowed['label'] ) );
		$this->assertSame( array( 'name', 'required', 'aria-required', 'class' ), array_keys( $allowed['textarea'] ) );
	}

	/**
	 * Notifications remain hidden without a matching result and honor filters.
	 */
	public function test_submission_notification_visibility(): void {
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'formblox_show_form_submission_notification_block', false, array( 'type' => 'success' ), '<p>Success</p>' )
			->andReturn( false );

		$this->assertSame(
			'',
			render_block_formblox_form_submission_notification( array( 'type' => 'success' ), '<p>Success</p>' )
		);
	}

	/**
	 * Matching result parameters display their corresponding notification.
	 */
	public function test_matching_submission_notification_is_visible(): void {
		$_GET['formblox-form-result'] = 'success';
		Functions\when( 'wp_unslash' )->returnArg();
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'formblox_show_form_submission_notification_block', true, array( 'type' => 'success' ), '<p>Success</p>' )
			->andReturn( true );

		$this->assertSame(
			'<p>Success</p>',
			render_block_formblox_form_submission_notification( array( 'type' => 'success' ), '<p>Success</p>' )
		);
	}
}
