<?php
/**
 * WordPress integration smoke checks. Run with `wp eval-file` after activation.
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

$registry = WP_Block_Type_Registry::get_instance();
$names    = array(
	'core/form',
	'core/form-input',
	'core/form-submit-button',
	'core/form-submission-notification',
);
foreach ( $names as $name ) {
	wp_forms_blocks_integration_assert(
		$registry->is_registered( $name ),
		"Block was not registered: {$name}"
	);
}

$form_block = $registry->get_registered( 'core/form' );
wp_forms_blocks_integration_assert(
	in_array( 'wp-forms-blocks-editor', $form_block->editor_script_handles, true ),
	'Form block is not connected to the editor bundle.'
);
wp_forms_blocks_integration_assert(
	in_array( 'wp-forms-blocks-view', $form_block->view_script_module_ids, true ),
	'Form block is not connected to the front-end script module.'
);
wp_forms_blocks_integration_assert(
	in_array( 'wp-forms-blocks', $form_block->style_handles, true ),
	'Form block is not connected to the front-end stylesheet.'
);

wp_forms_blocks_integration_assert(
	(bool) wp_scripts()->query( 'wp-forms-blocks-editor', 'registered' ),
	'Editor script was not registered.'
);
wp_forms_blocks_integration_assert(
	(bool) wp_styles()->query( 'wp-forms-blocks', 'registered' ),
	'Front-end stylesheet was not registered.'
);
wp_forms_blocks_integration_assert(
	null !== wp_script_modules()->get_registered( 'wp-forms-blocks-view' ),
	'Front-end script module was not registered.'
);

$email_form = wp_forms_blocks_render_form(
	array(
		'submissionMethod' => 'email',
		'email'            => 'recipient@example.com',
	),
	'<form class="wp-block-form" enctype="text/plain"><input name="message"></form>'
);
wp_forms_blocks_integration_assert(
	false !== strpos( $email_form, admin_url( 'admin-ajax.php' ) ),
	'Email form does not target WordPress AJAX.'
);
wp_forms_blocks_integration_assert(
	false !== strpos( $email_form, 'name="wp_forms_blocks_signature"' ),
	'Email form does not contain a signed recipient.'
);
wp_forms_blocks_integration_assert(
	false !== strpos( $email_form, 'name="_wp_http_referer"' ),
	'Email form does not preserve the submission source URL.'
);
wp_forms_blocks_integration_assert(
	false === strpos( $email_form, 'enctype=' ),
	'Email form still has the legacy text/plain encoding.'
);

preg_match( '/name="wp_forms_blocks_token" value="([^"]+)"/', $email_form, $token_match );
preg_match( '/name="wp_forms_blocks_signature" value="([^"]+)"/', $email_form, $signature_match );
wp_forms_blocks_integration_assert(
	array( 'recipient@example.com' ) === wp_forms_blocks_verify_recipients( $token_match[1], $signature_match[1] ),
	'Rendered email recipient signature could not be verified.'
);

$comment_form = wp_forms_blocks_render_form(
	array(
		'submissionMethod' => 'custom',
		'action'           => '{SITE_URL}/wp-comments-post.php',
		'method'           => 'post',
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

$custom_form = wp_forms_blocks_render_form(
	array(
		'submissionMethod' => 'custom',
		'action'           => '{ADMIN_URL}admin-post.php',
		'method'           => 'GET',
	),
	'<form class="wp-block-form"></form>'
);
wp_forms_blocks_integration_assert(
	false !== strpos( $custom_form, admin_url( 'admin-post.php' ) ),
	'Custom form admin URL placeholder was not expanded.'
);
wp_forms_blocks_integration_assert(
	false !== strpos( $custom_form, 'method="get"' ),
	'Custom form GET method was not preserved.'
);
wp_forms_blocks_integration_assert(
	false === strpos( $custom_form, 'wp_forms_blocks_signature' ),
	'Custom form unexpectedly received email-submission fields.'
);
wp_forms_blocks_integration_assert(
	'<p>No form here.</p>' === wp_forms_blocks_render_form( array(), '<p>No form here.</p>' ),
	'Form renderer changed content without a form element.'
);

add_filter(
	'render_block_core_form_extra_fields',
	static function ( $fields ) {
		return $fields . '<input type="hidden" name="extension-field" value="yes">';
	},
	30
);
$filtered_form = wp_forms_blocks_render_form(
	array(
		'submissionMethod' => 'custom',
	),
	'<form class="wp-block-form"></form>'
);
wp_forms_blocks_integration_assert(
	false !== strpos( $filtered_form, 'name="extension-field"' ),
	'Custom form did not receive fields from the shared extra-fields filter.'
);
remove_all_filters( 'render_block_core_form_extra_fields', 30 );

$privacy_fields = wp_forms_blocks_add_privacy_fields( '', array( 'anchor' => 'gdpr-form' ) );
wp_forms_blocks_integration_assert(
	false !== strpos( $privacy_fields, 'name="wp_forms_blocks_privacy_nonce"' ),
	'Privacy form nonce field was not added.'
);
wp_forms_blocks_integration_assert(
	false !== strpos( $privacy_fields, 'name="wp_forms_blocks_website"' ),
	'Privacy form honeypot was not added.'
);
wp_forms_blocks_integration_assert(
	'' === wp_forms_blocks_add_privacy_fields( '', array( 'anchor' => 'another-form' ) ),
	'Privacy fields were added to an unrelated form.'
);

wp_forms_blocks_integration_assert(
	'' === wp_forms_blocks_render_input( array( 'visibilityPermissions' => 'logged-in' ), '<input>' ),
	'Logged-in-only field was shown to a logged-out visitor.'
);
wp_forms_blocks_integration_assert(
	'<input>' === wp_forms_blocks_render_input( array( 'visibilityPermissions' => 'logged-out' ), '<input>' ),
	'Logged-out field was unexpectedly hidden.'
);

$administrator = get_user_by( 'login', 'admin' );
wp_set_current_user( $administrator ? $administrator->ID : 0 );
wp_forms_blocks_integration_assert(
	'<input>' === wp_forms_blocks_render_input( array( 'visibilityPermissions' => 'logged-in' ), '<input>' ),
	'Logged-in field was unexpectedly hidden from an authenticated user.'
);
wp_forms_blocks_integration_assert(
	'' === wp_forms_blocks_render_input( array( 'visibilityPermissions' => 'logged-out' ), '<input>' ),
	'Logged-out-only field was shown to an authenticated user.'
);
wp_set_current_user( 0 );

$_GET['wp-form-result'] = 'success';
wp_forms_blocks_integration_assert(
	'<p>Success</p>' === wp_forms_blocks_render_notification( array( 'type' => 'success' ), '<p>Success</p>' ),
	'Success notification did not render for a successful result.'
);
wp_forms_blocks_integration_assert(
	'' === wp_forms_blocks_render_notification( array( 'type' => 'error' ), '<p>Error</p>' ),
	'Error notification rendered for a successful result.'
);
add_filter( 'show_form_submission_notification_block', '__return_true' );
wp_forms_blocks_integration_assert(
	'<p>Forced</p>' === wp_forms_blocks_render_notification( array( 'type' => 'error' ), '<p>Forced</p>' ),
	'Notification visibility filter could not override the query result.'
);
remove_filter( 'show_form_submission_notification_block', '__return_true' );
unset( $_GET['wp-form-result'] );

$allowed_html = wp_kses_allowed_html( 'post' );
wp_forms_blocks_integration_assert( isset( $allowed_html['form'], $allowed_html['input'] ), 'KSES form elements were not enabled.' );
wp_forms_blocks_integration_assert( isset( $allowed_html['input']['required'] ), 'KSES input attributes were not enabled.' );
wp_forms_blocks_integration_assert( isset( $allowed_html['textarea']['placeholder'] ), 'KSES textarea attributes were not enabled.' );
wp_forms_blocks_integration_assert(
	array( 'a' => array( 'href' => true ) ) === wp_forms_blocks_kses_allowed_html( array( 'a' => array( 'href' => true ) ), 'data' ),
	'KSES rules changed a non-post context.'
);

// Registration should remain safe if another provider has already registered the historical names.
wp_forms_blocks_register_blocks();

echo "WordPress integration smoke tests passed for all four blocks.\n";
