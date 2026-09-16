<?php
/**
 * Plugin Name: WP Forms Blocks E2E Support
 * Description: Captures mail and exposes deterministic controls to the isolated E2E environment.
 * Version: 1.0.0
 *
 * @package WPFormsBlocksTests
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'pre_wp_mail',
	static function ( $pre_wp_mail, $attributes ) {
		update_option( 'wp_forms_blocks_e2e_last_mail', $attributes, false );
		return 'failure' === get_option( 'wp_forms_blocks_e2e_mail_mode', 'success' ) ? false : true;
	},
	10,
	2
);

add_action(
	'rest_api_init',
	static function () {
		$permission_callback = static function () {
			return current_user_can( 'manage_options' );
		};

		register_rest_route(
			'wp-forms-blocks-test/v1',
			'/mail',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => $permission_callback,
				'callback'            => static function () {
					return rest_ensure_response( get_option( 'wp_forms_blocks_e2e_last_mail', array() ) );
				},
			)
		);

		register_rest_route(
			'wp-forms-blocks-test/v1',
			'/mail-mode',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => $permission_callback,
				'callback'            => static function ( WP_REST_Request $request ) {
					$mode = 'failure' === $request->get_param( 'mode' ) ? 'failure' : 'success';
					update_option( 'wp_forms_blocks_e2e_mail_mode', $mode, false );
					delete_option( 'wp_forms_blocks_e2e_last_mail' );
					return rest_ensure_response( array( 'mode' => $mode ) );
				},
			)
		);

		register_rest_route(
			'wp-forms-blocks-test/v1',
			'/privacy-requests',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => $permission_callback,
				'callback'            => static function () {
					$requests = get_posts(
						array(
							'post_type'      => 'user_request',
							'post_status'    => 'any',
							'posts_per_page' => -1,
						)
					);
					return rest_ensure_response(
						array_map(
							static function ( $request ) {
								return array(
									'action' => $request->post_name,
									'email'  => $request->post_title,
									'status' => $request->post_status,
								);
							},
							$requests
						)
					);
				},
			)
		);
	}
);
