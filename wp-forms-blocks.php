<?php
/**
 * Plugin Name:       WP Forms Blocks
 * Plugin URI:        https://github.com/aristath/wp-forms-blocks
 * Description:       Block-editor forms with email, custom actions, comments, and privacy requests.
 * Version:           0.1.0
 * Requires at least: 7.0
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
require_once WP_FORMS_BLOCKS_DIR . 'build/script-module-data.php';
require_once WP_FORMS_BLOCKS_DIR . 'build/kses-allowed-html.php';
require_once WP_FORMS_BLOCKS_DIR . 'build/form/index.php';
require_once WP_FORMS_BLOCKS_DIR . 'build/form-input/index.php';
require_once WP_FORMS_BLOCKS_DIR . 'build/form-submit-button/index.php';
require_once WP_FORMS_BLOCKS_DIR . 'build/form-submission-notification/index.php';

add_action( 'init', 'wp_forms_blocks_register_assets', 5 );
add_action( 'enqueue_block_editor_assets', 'wp_forms_blocks_enqueue_editor_assets' );
