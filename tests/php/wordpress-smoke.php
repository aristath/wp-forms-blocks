<?php
/**
 * WordPress integration checks. Run with `wp eval-file` after activation.
 *
 * @package WPFormsBlocks
 */

/**
 * Throw when a WordPress integration assertion fails.
 *
 * @param bool   $condition Assertion result.
 * @param string $message   Failure description.
 */
function wp_forms_blocks_integration_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

$registry    = WP_Block_Type_Registry::get_instance();
$block_names = array(
	'core/form',
	'core/form-input',
	'core/form-submit-button',
	'core/form-submission-notification',
);
foreach ( $block_names as $block_name ) {
	wp_forms_blocks_integration_assert(
		$registry->is_registered( $block_name ),
		"Block was not registered: {$block_name}"
	);
}

wp_forms_blocks_integration_assert(
	array(
		'dependencies' => array(),
		'version'      => WP_FORMS_BLOCKS_VERSION,
	) === wp_forms_blocks_get_asset( 'does-not-exist' ),
	'Asset manifest fallback changed.'
);

$form_block = $registry->get_registered( 'core/form' );
wp_forms_blocks_integration_assert(
	'WPFormsBlocks\\render_block_core_form' === $form_block->render_callback,
	'Form block is not using the ported Gutenberg render callback.'
);
wp_forms_blocks_integration_assert(
	in_array( 'wp-block-form-input', $registry->get_registered( 'core/form-input' )->style_handles, true ),
	'Input block did not retain its Gutenberg style handle.'
);
wp_forms_blocks_integration_assert(
	in_array( 'wp-block-form-submit-button', $registry->get_registered( 'core/form-submit-button' )->style_handles, true ),
	'Submit block did not retain its Gutenberg style handle.'
);

wp_forms_blocks_integration_assert(
	(bool) wp_scripts()->query( 'wp-forms-blocks-editor', 'registered' ),
	'Editor script was not registered.'
);
do_action( 'enqueue_block_editor_assets' );
wp_forms_blocks_integration_assert(
	wp_script_is( 'wp-forms-blocks-editor', 'enqueued' ),
	'Editor script was not enqueued through the standalone adapter.'
);
wp_forms_blocks_integration_assert(
	wp_style_is( 'wp-forms-blocks-editor', 'enqueued' ),
	'Editor stylesheet was not enqueued through the standalone adapter.'
);
wp_forms_blocks_integration_assert(
	(bool) wp_styles()->query( 'wp-block-form-input', 'registered' ),
	'Input stylesheet was not registered.'
);
wp_forms_blocks_integration_assert(
	(bool) wp_styles()->query( 'wp-block-form-submit-button', 'registered' ),
	'Submit stylesheet was not registered.'
);
wp_forms_blocks_integration_assert(
	null !== wp_script_modules()->get_registered( '@wordpress/block-library/form/view' ),
	'Gutenberg form view-module ID was not registered.'
);

$module_data = apply_filters( 'script_module_data_@wordpress/block-library/form/view', array() );
wp_forms_blocks_integration_assert(
	'wp_block_form_email_submit' === $module_data['action'],
	'View-module AJAX action differs from Gutenberg.'
);
wp_forms_blocks_integration_assert(
	admin_url( 'admin-ajax.php' ) === $module_data['ajaxUrl'],
	'View-module AJAX URL differs from Gutenberg.'
);
wp_forms_blocks_integration_assert(
	(bool) wp_verify_nonce( $module_data['nonce'], 'wp-block-form' ),
	'View-module nonce differs from Gutenberg.'
);

$email_form = \WPFormsBlocks\render_block_core_form(
	array(
		'action' => 'mailto:recipient@example.com',
		'method' => 'post',
	),
	'<form class="wp-block-form" enctype="text/plain"><input name="message"></form>'
);
wp_forms_blocks_integration_assert(
	false !== strpos( $email_form, 'action="mailto:recipient@example.com"' ),
	'Email form action was changed from the Gutenberg behavior.'
);
wp_forms_blocks_integration_assert(
	false !== strpos( $email_form, 'method="post"' ),
	'Email form method was not rendered.'
);
wp_forms_blocks_integration_assert(
	false !== strpos( $email_form, 'enctype="text/plain"' ),
	'Email form encoding was changed from the Gutenberg behavior.'
);
wp_forms_blocks_integration_assert(
	false === strpos( $email_form, 'wp_forms_blocks_' ),
	'Plugin-specific hidden fields were injected into the Gutenberg markup.'
);
wp_forms_blocks_integration_assert(
	in_array( '@wordpress/block-library/form/view', wp_script_modules()->get_queue(), true ),
	'Form rendering did not enqueue the original Gutenberg view-module ID.'
);
ob_start();
wp_script_modules()->print_script_module_data();
$module_data_html = ob_get_clean();
wp_forms_blocks_integration_assert(
	false !== strpos( $module_data_html, 'wp-script-module-data-@wordpress/block-library/form/view' ),
	'WordPress did not print data under the DOM ID expected by Gutenberg view.js.'
);
wp_forms_blocks_integration_assert(
	false !== strpos( $module_data_html, 'wp_block_form_email_submit' ),
	'Printed view-module data omitted the Gutenberg AJAX action.'
);

$comment_form = \WPFormsBlocks\render_block_core_form(
	array(
		'action' => '{SITE_URL}/wp-comments-post.php',
		'method' => 'post',
	),
	'<form class="wp-block-form"></form>'
);
wp_forms_blocks_integration_assert(
	false !== strpos( $comment_form, site_url() . '/wp-comments-post.php' ),
	'Comment form site URL placeholder was not expanded.'
);
wp_forms_blocks_integration_assert(
	false !== strpos( $comment_form, 'name="comment_post_ID"' ),
	'Comment form post ID field was not added.'
);

$custom_form = \WPFormsBlocks\render_block_core_form(
	array(
		'action' => '{ADMIN_URL}admin-post.php',
		'method' => 'get',
	),
	'<form class="wp-block-form"></form>'
);
wp_forms_blocks_integration_assert(
	false !== strpos( $custom_form, admin_url( 'admin-post.php' ) ),
	'Custom form admin URL placeholder was not expanded.'
);
wp_forms_blocks_integration_assert(
	false !== strpos( $custom_form, 'method="get"' ),
	'Custom form method was not preserved.'
);

$default_form = \WPFormsBlocks\render_block_core_form(
	array(),
	'<form class="wp-block-form"></form>'
);
wp_forms_blocks_integration_assert(
	false !== strpos( $default_form, 'action=""' ) && false !== strpos( $default_form, 'method="post"' ),
	'Form defaults changed.'
);

$non_string_action_form = \WPFormsBlocks\render_block_core_form(
	array( 'action' => array( 'invalid' ) ),
	'<form class="wp-block-form"></form>'
);
wp_forms_blocks_integration_assert(
	false !== strpos( $non_string_action_form, 'action=""' ),
	'Non-string form actions are no longer ignored.'
);

$extra_field_callback = static function ( $fields, $attributes ) {
	return $fields . '<input type="hidden" name="filtered" value="' . esc_attr( $attributes['marker'] ) . '">';
};
add_filter( 'render_block_core_form_extra_fields', $extra_field_callback, 20, 2 );
$filtered_form = \WPFormsBlocks\render_block_core_form(
	array( 'marker' => 'yes' ),
	'<form class="wp-block-form"></form>'
);
remove_filter( 'render_block_core_form_extra_fields', $extra_field_callback, 20 );
wp_forms_blocks_integration_assert(
	false !== strpos( $filtered_form, 'name="filtered" value="yes"' ),
	'Form extra-field extension point changed.'
);

wp_forms_blocks_integration_assert(
	'' === \WPFormsBlocks\render_block_core_form_input( array( 'visibilityPermissions' => 'logged-in' ), '<input>' ),
	'Logged-in-only field was shown to a logged-out visitor.'
);
wp_forms_blocks_integration_assert(
	'<input>' === \WPFormsBlocks\render_block_core_form_input( array( 'visibilityPermissions' => 'logged-out' ), '<input>' ),
	'Logged-out field was unexpectedly hidden.'
);

$administrator = get_user_by( 'login', 'admin' );
wp_set_current_user( $administrator ? $administrator->ID : 0 );
wp_forms_blocks_integration_assert(
	'<input>' === \WPFormsBlocks\render_block_core_form_input( array( 'visibilityPermissions' => 'logged-in' ), '<input>' ),
	'Logged-in field was unexpectedly hidden.'
);
wp_forms_blocks_integration_assert(
	'' === \WPFormsBlocks\render_block_core_form_input( array( 'visibilityPermissions' => 'logged-out' ), '<input>' ),
	'Logged-out-only field was shown to an authenticated user.'
);
wp_set_current_user( 0 );

wp_forms_blocks_integration_assert(
	'<input>' === \WPFormsBlocks\render_block_core_form_input( array(), '<input>' ),
	'The default input visibility is no longer all visitors.'
);

$_GET['wp-form-result'] = 'success';
wp_forms_blocks_integration_assert(
	'<p>Success</p>' === \WPFormsBlocks\render_block_core_form_submission_notification( array( 'type' => 'success' ), '<p>Success</p>' ),
	'Success notification did not render for a successful result.'
);
wp_forms_blocks_integration_assert(
	'' === \WPFormsBlocks\render_block_core_form_submission_notification( array( 'type' => 'error' ), '<p>Error</p>' ),
	'Error notification rendered for a successful result.'
);
unset( $_GET['wp-form-result'] );

$notification_override = static function ( $show, $attributes, $content ) {
	return 'forced' === $attributes['type'] && '<p>Forced</p>' === $content;
};
add_filter( 'show_form_submission_notification_block', $notification_override, 99, 3 );
wp_forms_blocks_integration_assert(
	'<p>Forced</p>' === \WPFormsBlocks\render_block_core_form_submission_notification( array( 'type' => 'forced' ), '<p>Forced</p>' ),
	'Notification visibility filter cannot force a notification to display.'
);
remove_filter( 'show_form_submission_notification_block', $notification_override, 99 );

$allowed_html = \WPFormsBlocks\gutenberg_kses_allowed_html(
	array( 'existing' => array( 'attribute' => array() ) )
);
wp_forms_blocks_integration_assert(
	isset( $allowed_html['existing']['attribute'] ),
	'KSES extension discarded existing allowed HTML.'
);
wp_forms_blocks_integration_assert(
	array( 'type', 'name', 'value', 'checked', 'required', 'aria-required', 'class' ) === array_keys( $allowed_html['input'] ),
	'Input KSES rules differ from Gutenberg.'
);
wp_forms_blocks_integration_assert(
	array( 'for', 'class' ) === array_keys( $allowed_html['label'] ),
	'Label KSES rules differ from Gutenberg.'
);
wp_forms_blocks_integration_assert(
	array( 'name', 'required', 'aria-required', 'class' ) === array_keys( $allowed_html['textarea'] ),
	'Textarea KSES rules differ from Gutenberg.'
);

echo "WordPress integration tests passed for the faithful port.\n";
