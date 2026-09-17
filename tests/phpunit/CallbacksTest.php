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
use function WPFormsBlocks\block_formblox_form_mark_privacy_request_failed;
use function WPFormsBlocks\block_formblox_form_privacy_form;
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
		Functions\when( '__' )->returnArg();

		$this->assertSame(
			array(
				'existing'       => true,
				'nonce'          => 'nonce',
				'ajaxUrl'        => 'https://example.com/wp-admin/admin-ajax.php',
				'action'         => 'formblox_form_email_submit',
				'submittingText' => 'Submitting…',
			),
			formblox_form_view_script_module( array( 'existing' => true ) )
		);
	}

	/**
	 * Mail transport failures return an HTTP 500 JSON error.
	 */
	public function test_mail_failure_returns_http_500_json_error(): void {
		$_POST            = array(
			'_wp_http_referer' => '/contact/',
			'message'          => 'Expected failure',
			'Όνομα'            => 'Αριστάθης',
			'topics'           => array( 'music', 'art' ),
		);
		$expected_content = "Form submission from Example\nSource: /contact/\n\nmessage: Expected failure\nΌνομα: Αριστάθης\ntopics: music, art\n";

		Functions\expect( 'check_ajax_referer' )->once()->with( 'formblox-form' );
		Functions\when( 'wp_unslash' )->returnArg();
		Functions\when( '__' )->returnArg();
		Functions\when( 'esc_url_raw' )->returnArg();
		Functions\when( 'get_bloginfo' )->alias(
			static function ( $show ) {
				return 'charset' === $show ? 'UTF-8' : 'Example';
			}
		);
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'sanitize_textarea_field' )->returnArg();
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'render_block_formblox_form_email_content', $expected_content, $_POST )
			->andReturn( $expected_content );
		Functions\when( 'get_option' )->justReturn( 'admin@example.com' );
		Functions\when( 'is_email' )->justReturn( true );
		Functions\expect( 'wp_mail' )
			->once()
			->with(
				'admin@example.com',
				'Form submission',
				$expected_content,
				array( 'Content-Type: text/plain; charset=UTF-8' )
			)
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
	 * Privacy submissions without their rendered form token are ignored.
	 */
	public function test_privacy_submission_requires_rendered_form_token(): void {
		$_POST        = array(
			'wp-action'            => 'wp_privacy_send_request',
			'wp-privacy-request'   => '1',
			'email'                => 'privacy@example.com',
			'export_personal_data' => '1',
		);
		$filter_count = count( $GLOBALS['wp_forms_blocks_test_filters'] );

		Functions\when( 'wp_unslash' )->returnArg();
		Functions\expect( 'wp_create_user_request' )->never();

		block_formblox_form_privacy_form();

		$this->assertCount( $filter_count, $GLOBALS['wp_forms_blocks_test_filters'] );
	}

	/**
	 * Privacy mail failures become failed requests and error notifications.
	 */
	public function test_privacy_mail_failure_is_recorded_as_an_error(): void {
		$_POST        = array(
			'wp-action'                => 'wp_privacy_send_request',
			'wp-privacy-request'       => '1',
			'email'                    => 'privacy@example.com',
			'export_personal_data'     => '1',
			'formblox-privacy-form-id' => 'formblox-privacy-00000000-0000-4000-8000-000000000001',
			'formblox-privacy-nonce'   => 'valid-nonce',
		);
		$filter_count = count( $GLOBALS['wp_forms_blocks_test_filters'] );

		Functions\when( 'wp_unslash' )->returnArg();
		Functions\expect( 'wp_verify_nonce' )
			->once()
			->with( 'valid-nonce', 'formblox-privacy-request:formblox-privacy-00000000-0000-4000-8000-000000000001' )
			->andReturn( 1 );
		Functions\when( '_wp_privacy_action_request_types' )->justReturn( array( 'export_personal_data' ) );
		Functions\expect( 'wp_create_user_request' )
			->once()
			->with( 'privacy@example.com', 'export_personal_data' )
			->andReturn( 55 );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\expect( 'wp_send_user_request' )->once()->with( 55 )->andReturn( false );
		Functions\expect( 'wp_update_post' )
			->once()
			->with(
				array(
					'ID'            => 55,
					'post_status'   => 'request-failed',
					'post_password' => '',
				),
				true
			)
			->andReturn( 55 );
		Functions\expect( 'wp_delete_post' )->never();

		block_formblox_form_privacy_form();

		$this->assertCount( $filter_count + 1, $GLOBALS['wp_forms_blocks_test_filters'] );
		$notification_filter = $GLOBALS['wp_forms_blocks_test_filters'][ $filter_count ]['callback'];
		$this->assertFalse( $notification_filter( false, array( 'type' => 'success' ) ) );
		$this->assertTrue( $notification_filter( false, array( 'type' => 'error' ) ) );
	}

	/**
	 * A request is deleted when its failed state cannot be persisted.
	 */
	public function test_unpersistable_privacy_failure_is_deleted(): void {
		Functions\expect( 'wp_update_post' )
			->once()
			->with(
				array(
					'ID'            => 55,
					'post_status'   => 'request-failed',
					'post_password' => '',
				),
				true
			)
			->andReturn( 0 );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\expect( 'wp_delete_post' )->once()->with( 55, true );

		block_formblox_form_mark_privacy_request_failed( 55 );
		$this->addToAssertionCount( 1 );
	}

	/**
	 * KSES receives the complete set of form elements and attributes.
	 */
	public function test_kses_form_elements(): void {
		$allowed = gutenberg_kses_allowed_html(
			array(
				'p'     => array(),
				'input' => array( 'existing' => true ),
			),
			'post'
		);

		$this->assertArrayHasKey( 'p', $allowed );
		$this->assertTrue( $allowed['input']['existing'] );
		$this->assertArrayHasKey( 'class', $allowed['form'] );
		$this->assertArrayHasKey( 'id', $allowed['form'] );
		$this->assertArrayHasKey( 'style', $allowed['form'] );
		$this->assertArrayHasKey( 'data-*', $allowed['form'] );
		$this->assertArrayHasKey( 'placeholder', $allowed['input'] );
		$this->assertArrayHasKey( 'style', $allowed['input'] );
		$this->assertArrayHasKey( 'style', $allowed['label'] );
		$this->assertArrayHasKey( 'placeholder', $allowed['textarea'] );
		$this->assertArrayHasKey( 'style', $allowed['textarea'] );
	}

	/**
	 * KSES rules do not leak into unrelated contexts.
	 */
	public function test_kses_form_elements_only_apply_to_post_context(): void {
		$allowed = array( 'p' => array( 'class' => true ) );

		$this->assertSame( $allowed, gutenberg_kses_allowed_html( $allowed, 'data' ) );
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
