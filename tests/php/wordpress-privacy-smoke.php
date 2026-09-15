<?php
/**
 * Gutenberg-compatible privacy-request checks. Run with `wp eval-file`.
 *
 * @package WPFormsBlocks
 */

/**
 * Throw when a privacy integration assertion fails.
 *
 * @param bool   $condition Assertion result.
 * @param string $message   Failure description.
 */
function wp_forms_blocks_privacy_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

$count_requests = static function () {
	return count(
		get_posts(
			array(
				'post_type'      => 'user_request',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		)
	);
};

$_POST = array(
	'wp-action'            => 'wp_privacy_send_request',
	'wp-privacy-request'   => '1',
	'email'                => 'privacy@example.com',
	'export_personal_data' => '1',
	'remove_personal_data' => '1',
);

add_filter( 'pre_wp_mail', '__return_true' );
$before = $count_requests();

foreach (
	array(
		array(),
		array(
			'wp-action'          => 'wrong_action',
			'wp-privacy-request' => '1',
			'email'              => 'privacy@example.com',
		),
		array(
			'wp-action'          => 'wp_privacy_send_request',
			'wp-privacy-request' => '1',
			'email'              => 'privacy@example.com',
		),
	) as $ignored_submission
) {
	$_POST = $ignored_submission;
	\WPFormsBlocks\block_formblox_form_privacy_form();
}
wp_forms_blocks_privacy_assert(
	$before === $count_requests(),
	'Invalid or actionless privacy submissions created requests.'
);

$_POST = array(
	'wp-action'            => 'wp_privacy_send_request',
	'wp-privacy-request'   => '1',
	'email'                => 'privacy@example.com',
	'export_personal_data' => '1',
	'remove_personal_data' => '1',
);
\WPFormsBlocks\block_formblox_form_privacy_form();

wp_forms_blocks_privacy_assert(
	$before + 2 === $count_requests(),
	'The privacy form did not create both requested actions.'
);
wp_forms_blocks_privacy_assert(
	'<p>Success</p>' === \WPFormsBlocks\render_block_formblox_form_submission_notification( array( 'type' => 'success' ), '<p>Success</p>' ),
	'The Gutenberg privacy success notification was not enabled.'
);
wp_forms_blocks_privacy_assert(
	'' === \WPFormsBlocks\render_block_formblox_form_submission_notification( array( 'type' => 'error' ), '<p>Error</p>' ),
	'The Gutenberg privacy error notification was enabled after successful requests.'
);

$_POST = array(
	'wp-action'            => 'wp_privacy_send_request',
	'wp-privacy-request'   => '1',
	'email'                => 'not-an-email',
	'export_personal_data' => '1',
);
\WPFormsBlocks\block_formblox_form_privacy_form();
wp_forms_blocks_privacy_assert(
	'<p>Error</p>' === \WPFormsBlocks\render_block_formblox_form_submission_notification( array( 'type' => 'error' ), '<p>Error</p>' ),
	'The privacy error notification was not enabled after request creation failed.'
);
wp_forms_blocks_privacy_assert(
	'' === \WPFormsBlocks\render_block_formblox_form_submission_notification( array( 'type' => 'success' ), '<p>Success</p>' ),
	'The privacy success notification was enabled after request creation failed.'
);

echo "WordPress privacy-request tests passed for the faithful port.\n";
