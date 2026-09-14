<?php
/**
 * Privacy-request processing checks. Run with `wp eval-file` after activation.
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

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST                     = array(
	'wp-action'                         => 'wp_privacy_send_request',
	'wp-privacy-request'                => '1',
	'wp_forms_blocks_privacy_nonce'     => 'invalid',
	'email'                             => 'privacy@example.com',
	'export_personal_data'              => '1',
);

$before = $count_requests();
wp_forms_blocks_process_privacy_request();
wp_forms_blocks_privacy_assert(
	$before === $count_requests(),
	'An invalid privacy nonce created a user request.'
);

add_filter( 'pre_wp_mail', '__return_true' );
$_POST['wp_forms_blocks_privacy_nonce'] = wp_create_nonce( 'wp_forms_blocks_privacy_request' );
$_POST['remove_personal_data']          = '1';
wp_forms_blocks_process_privacy_request();

wp_forms_blocks_privacy_assert(
	$before + 2 === $count_requests(),
	'The privacy form did not create both requested actions.'
);
wp_forms_blocks_privacy_assert(
	'<p>Success</p>' === wp_forms_blocks_render_notification( array( 'type' => 'success' ), '<p>Success</p>' ),
	'The privacy success notification was not enabled.'
);
wp_forms_blocks_privacy_assert(
	'' === wp_forms_blocks_render_notification( array( 'type' => 'error' ), '<p>Error</p>' ),
	'The privacy error notification was enabled after successful requests.'
);

echo "WordPress privacy-request smoke tests passed.\n";
