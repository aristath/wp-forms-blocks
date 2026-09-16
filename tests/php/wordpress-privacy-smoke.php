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

$get_requests = static function ( $email ) {
	return array_values(
		array_filter(
			get_posts(
				array(
					'post_type'      => 'user_request',
					'post_status'    => 'any',
					'posts_per_page' => -1,
				)
			),
			static function ( $request ) use ( $email ) {
				return $email === $request->post_title;
			}
		)
	);
};

$submission_number = 0;
$make_submission   = static function ( $email, $actions ) use ( &$submission_number ) {
	++$submission_number;
	$form_id    = sprintf( 'formblox-privacy-00000000-0000-4000-8000-%012d', $submission_number );
	$submission = array(
		'wp-action'                => 'wp_privacy_send_request',
		'wp-privacy-request'       => '1',
		'email'                    => $email,
		'formblox-privacy-form-id' => $form_id,
		'formblox-privacy-nonce'   => wp_create_nonce( 'formblox-privacy-request:' . $form_id ),
	);
	foreach ( $actions as $action ) {
		$submission[ $action ] = '1';
	}
	return $submission;
};

$notification_is_visible = static function ( $type ) {
	$content = '<p>' . ucfirst( $type ) . '</p>';
	return \WPFormsBlocks\render_block_formblox_form_submission_notification( array( 'type' => $type ), $content ) === $content;
};

$mail_results = array();
add_filter(
	'pre_wp_mail',
	static function () use ( &$mail_results ) {
		return empty( $mail_results ) ? true : array_shift( $mail_results );
	}
);

$before = count( $get_requests( 'privacy-ignored@example.com' ) );
foreach (
	array(
		array(),
		array(
			'wp-action'            => 'wp_privacy_send_request',
			'wp-privacy-request'   => '1',
			'email'                => 'privacy-ignored@example.com',
			'export_personal_data' => '1',
		),
		array_merge(
			$make_submission( 'privacy-ignored@example.com', array( 'export_personal_data' ) ),
			array( 'formblox-privacy-nonce' => wp_create_nonce( 'wrong-purpose' ) )
		),
		$make_submission( 'privacy-ignored@example.com', array() ),
	) as $ignored_submission
) {
	$_POST = $ignored_submission;
	\WPFormsBlocks\block_formblox_form_privacy_form();
}
wp_forms_blocks_privacy_assert(
	count( $get_requests( 'privacy-ignored@example.com' ) ) === $before,
	'Untokened, incorrectly tokened, or actionless privacy submissions created requests.'
);

$success_email = 'privacy-success@example.com';
$success_post  = $make_submission( $success_email, array( 'export_personal_data', 'remove_personal_data' ) );
$mail_results  = array( true, true );
$_POST         = $success_post;
\WPFormsBlocks\block_formblox_form_privacy_form();
$success_requests = $get_requests( $success_email );
wp_forms_blocks_privacy_assert(
	2 === count( $success_requests ),
	'The privacy form did not create both requested actions.'
);
wp_forms_blocks_privacy_assert(
	array( 'request-pending' ) === array_values( array_unique( wp_list_pluck( $success_requests, 'post_status' ) ) ),
	'Successful privacy requests were not left pending confirmation.'
);
wp_forms_blocks_privacy_assert(
	$notification_is_visible( 'success' ) && ! $notification_is_visible( 'error' ),
	'Successful privacy requests did not show only the success notification.'
);

$mail_results = array();
$_POST        = $success_post;
\WPFormsBlocks\block_formblox_form_privacy_form();
wp_forms_blocks_privacy_assert(
	2 === count( $get_requests( $success_email ) ),
	'A duplicate privacy submission created additional requests.'
);
wp_forms_blocks_privacy_assert(
	! $notification_is_visible( 'success' ) && $notification_is_visible( 'error' ),
	'A duplicate privacy submission was not reported as an error.'
);

$_POST = $make_submission( 'not-an-email', array( 'export_personal_data' ) );
\WPFormsBlocks\block_formblox_form_privacy_form();
wp_forms_blocks_privacy_assert(
	! $notification_is_visible( 'success' ) && $notification_is_visible( 'error' ),
	'A malformed privacy email address was not reported as an error.'
);

$retry_email  = 'privacy-retry@example.com';
$mail_results = array( false );
$_POST        = $make_submission( $retry_email, array( 'export_personal_data' ) );
\WPFormsBlocks\block_formblox_form_privacy_form();
$failed_requests = $get_requests( $retry_email );
wp_forms_blocks_privacy_assert(
	1 === count( $failed_requests ) && 'request-failed' === $failed_requests[0]->post_status && '' === $failed_requests[0]->post_password,
	'A failed confirmation email did not leave an auditable request-failed row with no confirmation key.'
);
wp_forms_blocks_privacy_assert(
	! $notification_is_visible( 'success' ) && $notification_is_visible( 'error' ),
	'A failed confirmation email was not reported as an error.'
);

$mail_results = array( true );
$_POST        = $make_submission( $retry_email, array( 'export_personal_data' ) );
\WPFormsBlocks\block_formblox_form_privacy_form();
$retry_requests = $get_requests( $retry_email );
wp_forms_blocks_privacy_assert(
	2 === count( $retry_requests )
		&& in_array( 'request-failed', wp_list_pluck( $retry_requests, 'post_status' ), true )
		&& in_array( 'request-pending', wp_list_pluck( $retry_requests, 'post_status' ), true ),
	'A visitor could not retry after a failed confirmation email.'
);
wp_forms_blocks_privacy_assert(
	$notification_is_visible( 'success' ) && ! $notification_is_visible( 'error' ),
	'A successful retry did not show only the success notification.'
);

$partial_email = 'privacy-partial@example.com';
$mail_results  = array( true, false );
$_POST         = $make_submission( $partial_email, array( 'export_personal_data', 'remove_personal_data' ) );
\WPFormsBlocks\block_formblox_form_privacy_form();
$partial_requests = $get_requests( $partial_email );
$partial_statuses = wp_list_pluck( $partial_requests, 'post_status', 'post_name' );
wp_forms_blocks_privacy_assert(
	'request-pending' === $partial_statuses['export_personal_data']
		&& 'request-failed' === $partial_statuses['remove_personal_data'],
	'A partial privacy mail failure did not preserve the successful and failed action states.'
);
wp_forms_blocks_privacy_assert(
	! $notification_is_visible( 'success' ) && $notification_is_visible( 'error' ),
	'A partial privacy mail failure was reported as a complete success.'
);

echo "WordPress privacy-request tests passed for the faithful port.\n";
