<?php
/**
 * Plugin Name:       WP Forms Blocks
 * Plugin URI:        https://github.com/aristath/wp-forms-blocks
 * Description:       Block-editor forms with email, custom actions, comments, and privacy requests.
 * Version:           0.1.0
 * Requires at least: 6.7
 * Requires PHP:      7.4
 * Author:            Aristeidis Stathopoulos
 * Author URI:        https://aristath.github.io/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-forms-blocks
 *
 * @package WPFormsBlocks
 */

defined( 'ABSPATH' ) || exit;

define( 'WP_FORMS_BLOCKS_VERSION', '0.1.0' );
define( 'WP_FORMS_BLOCKS_FILE', __FILE__ );
define( 'WP_FORMS_BLOCKS_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_FORMS_BLOCKS_URL', plugin_dir_url( __FILE__ ) );

require_once WP_FORMS_BLOCKS_DIR . 'includes/assets.php';
require_once WP_FORMS_BLOCKS_DIR . 'includes/blocks.php';
require_once WP_FORMS_BLOCKS_DIR . 'includes/submissions.php';
require_once WP_FORMS_BLOCKS_DIR . 'includes/kses.php';

add_action( 'init', 'wp_forms_blocks_register_assets', 5 );
add_action( 'init', 'wp_forms_blocks_register_blocks', 10 );
add_action( 'wp', 'wp_forms_blocks_process_privacy_request' );
add_action( 'wp_ajax_wp_forms_blocks_email_submit', 'wp_forms_blocks_send_email' );
add_action( 'wp_ajax_nopriv_wp_forms_blocks_email_submit', 'wp_forms_blocks_send_email' );
add_filter( 'render_block_core_form_extra_fields', 'wp_forms_blocks_add_comment_fields', 10, 2 );
add_filter( 'render_block_core_form_extra_fields', 'wp_forms_blocks_add_privacy_fields', 20, 2 );
add_filter( 'wp_kses_allowed_html', 'wp_forms_blocks_kses_allowed_html', 10, 2 );
