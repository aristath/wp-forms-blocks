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

wp_forms_blocks_integration_assert(
	'' === wp_forms_blocks_render_input( array( 'visibilityPermissions' => 'logged-in' ), '<input>' ),
	'Logged-in-only field was shown to a logged-out visitor.'
);
wp_forms_blocks_integration_assert(
	'<input>' === wp_forms_blocks_render_input( array( 'visibilityPermissions' => 'logged-out' ), '<input>' ),
	'Logged-out field was unexpectedly hidden.'
);

$_GET['wp-form-result'] = 'success';
wp_forms_blocks_integration_assert(
	'<p>Success</p>' === wp_forms_blocks_render_notification( array( 'type' => 'success' ), '<p>Success</p>' ),
	'Success notification did not render for a successful result.'
);
wp_forms_blocks_integration_assert(
	'' === wp_forms_blocks_render_notification( array( 'type' => 'error' ), '<p>Error</p>' ),
	'Error notification rendered for a successful result.'
);
unset( $_GET['wp-form-result'] );

$allowed_html = wp_kses_allowed_html( 'post' );
wp_forms_blocks_integration_assert( isset( $allowed_html['form'], $allowed_html['input'] ), 'KSES form elements were not enabled.' );
wp_forms_blocks_integration_assert( isset( $allowed_html['input']['required'] ), 'KSES input attributes were not enabled.' );

// Registration should remain safe if another provider has already registered the historical names.
wp_forms_blocks_register_blocks();

echo "WordPress integration smoke tests passed for all four blocks.\n";
