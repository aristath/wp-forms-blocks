<?php
/**
 * Isolated native abilities, REST, permissions, revisions and form lifecycle.
 *
 * @package WPFormsBlocks
 */

use WPFormsBlocks\Form_Codec;

/**
 * Assert an integration contract.
 *
 * @param bool $condition Contract result.
 * @param string $message Failure description.
 */
function formblox_ability_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

/**
 * Invoke an ability as a real authenticated WordPress user.
 *
 * @param string $name Ability suffix.
 * @param array<string,mixed> $input Input.
 * @return mixed Ability result.
 */
function formblox_run_ability( $name, $input = array() ) {
	$ability = wp_get_ability( 'formblox/' . $name );
	formblox_ability_assert( null !== $ability, 'Missing ability: ' . $name );
	return $ability->execute( $input );
}

$admin = get_user_by( 'login', 'admin' );
wp_set_current_user( $admin->ID );
$author_id       = wp_insert_user(
	array(
		'user_login' => 'form-agent-author',
		'user_pass'  => 'test-password',
		'role'       => 'author',
	)
);
$other_id        = wp_insert_user(
	array(
		'user_login' => 'form-agent-other',
		'user_pass'  => 'test-password',
		'role'       => 'author',
	)
);
$subscriber_id   = wp_insert_user(
	array(
		'user_login' => 'form-agent-reader',
		'user_pass'  => 'test-password',
		'role'       => 'subscriber',
	)
);
$prefix          = '<!-- wp:paragraph {"className":"keep-original"} --><p class="keep-original">Before</p><!-- /wp:paragraph -->';
$ability_post_id = wp_insert_post(
	array(
		'post_type'    => 'post',
		'post_title'   => 'Agent forms',
		'post_author'  => $author_id,
		'post_status'  => 'draft',
		'post_content' => $prefix,
	)
);
wp_set_current_user( $author_id );

$capabilities = formblox_run_ability( 'describe-capabilities' );
formblox_ability_assert( 8 === count( $capabilities['field_types'] ), 'Missing field types.' );
$input   = array(
	'post_id'         => $ability_post_id,
	'content_version' => hash( 'sha256', $prefix ),
	'request_id'      => 'create-contact-1',
	'definition'      => array( 'template' => 'contact' ),
);
$created = formblox_run_ability( 'create-form', $input );

formblox_ability_assert( ! is_wp_error( $created ), 'Author create failed: ' . wp_json_encode( $created ) );
formblox_ability_assert( 'draft' === get_post_status( $ability_post_id ), 'Create changed publication status.' );
formblox_ability_assert( str_starts_with( get_post( $ability_post_id )->post_content, $prefix ), 'Create changed unrelated source bytes.' );
$replay = formblox_run_ability( 'create-form', $input );
formblox_ability_assert( ! is_wp_error( $replay ) && $replay['replayed'], 'Create retries were not deduplicated.' );
$input['definition'] = array( 'template' => 'comment' );
formblox_ability_assert( is_wp_error( formblox_run_ability( 'create-form', $input ) ), 'Request ID reuse accepted different input.' );

$read = formblox_run_ability(
	'get-form',
	array(
		'post_id' => $ability_post_id,
		'path'    => $created['path'],
	)
);
formblox_ability_assert( ! is_wp_error( $read ) && 'formblox/form' === $read['definition']['name'], 'Read failed.' );
$updated = formblox_run_ability(
	'update-form',
	array(
		'post_id'         => $ability_post_id,
		'path'            => $created['path'],
		'content_version' => $created['content_version'],
		'operations'      => array(
			array(
				'operation'  => 'update',
				'path'       => array( 2 ),
				'attributes' => array(
					'label'    => 'Full name',
					'required' => false,
				),
			),
		),
	)
);
formblox_ability_assert( ! is_wp_error( $updated ), 'Author update failed: ' . wp_json_encode( $updated ) );
$stale = formblox_run_ability(
	'delete-form',
	array(
		'post_id'         => $ability_post_id,
		'path'            => $created['path'],
		'content_version' => $created['content_version'],
	)
);
formblox_ability_assert( is_wp_error( $stale ) && 'formblox_stale_content' === $stale->get_error_code(), 'Stale write was accepted.' );
formblox_ability_assert( str_starts_with( get_post( $ability_post_id )->post_content, $prefix ), 'Update changed unrelated content.' );
formblox_ability_assert( count( wp_get_post_revisions( $ability_post_id ) ) > 0, 'Native revisions were not created.' );

wp_set_current_user( $other_id );
formblox_ability_assert(
	is_wp_error(
		formblox_run_ability(
			'get-form',
			array(
				'post_id' => $ability_post_id,
				'path'    => $created['path'],
			)
		)
	),
	'Another author read a protected definition.'
);
formblox_ability_assert(
	is_wp_error(
		formblox_run_ability(
			'delete-form',
			array(
				'post_id'         => $ability_post_id,
				'path'            => $created['path'],
				'content_version' => $updated['content_version'],
			)
		)
	),
	'Another author wrote protected content.'
);
wp_set_current_user( $subscriber_id );
formblox_ability_assert( is_wp_error( formblox_run_ability( 'list-forms' ) ), 'Subscriber could discover forms.' );
wp_set_current_user( 0 );
formblox_ability_assert( is_wp_error( formblox_run_ability( 'validate-form', array( 'definition' => array() ) ) ), 'Anonymous execution was allowed.' );
wp_set_current_user( $author_id );

$list = formblox_run_ability( 'list-forms', array( 'post_id' => $ability_post_id ) );
formblox_ability_assert( ! is_wp_error( $list ) && 1 === count( $list['forms'] ), 'Discovery failed.' );
$duplicate = formblox_run_ability(
	'duplicate-form',
	array(
		'post_id'                => $ability_post_id,
		'path'                   => $created['path'],
		'content_version'        => $updated['content_version'],
		'target_post_id'         => $ability_post_id,
		'target_content_version' => $updated['content_version'],
		'request_id'             => 'copy-contact-1',
	)
);
formblox_ability_assert( ! is_wp_error( $duplicate ), 'Duplicate failed: ' . wp_json_encode( $duplicate ) );
$list = formblox_run_ability( 'list-forms', array( 'post_id' => $ability_post_id ) );
formblox_ability_assert( 2 === count( $list['forms'] ), 'Copy did not create a second form.' );
formblox_ability_assert( $list['forms'][0]['definition']['attributes']['anchor'] !== $list['forms'][1]['definition']['attributes']['anchor'], 'Copy reused an anchor.' );

$request = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/formblox/get-form/run' );
$request->set_param(
	'input',
	array(
		'post_id' => $ability_post_id,
		'path'    => $created['path'],
	)
);
$response = rest_do_request( $request );
formblox_ability_assert( 200 === $response->get_status(), 'Authenticated abilities REST execution failed: ' . wp_json_encode( $response->get_data() ) );
$request = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities' );
$request->set_param( 'category', 'formblox' );
$response = rest_do_request( $request );
formblox_ability_assert( 200 === $response->get_status() && 8 === count( $response->get_data() ), 'REST discovery did not expose eight abilities.' );

$deleted = formblox_run_ability(
	'delete-form',
	array(
		'post_id'         => $ability_post_id,
		'path'            => $duplicate['path'],
		'content_version' => $duplicate['content_version'],
	)
);
formblox_ability_assert( ! is_wp_error( $deleted ), 'Delete failed.' );
$current = get_post( $ability_post_id )->post_content;
$bad     = formblox_run_ability(
	'update-form',
	array(
		'post_id'         => $ability_post_id,
		'path'            => $created['path'],
		'content_version' => hash( 'sha256', $current ),
		'operations'      => array(
			array(
				'operation'  => 'update',
				'path'       => array( 2 ),
				'attributes' => array( 'type' => 'file' ),
			),
		),
	)
);
formblox_ability_assert( is_wp_error( $bad ) && get_post( $ability_post_id )->post_content === $current, 'Invalid update partially changed content.' );

foreach ( array( 'contact', 'comment', 'privacy', 'custom' ) as $template ) {
	$definition = array( 'template' => $template );
	if ( 'custom' === $template ) {
		$definition['attributes'] = array( 'action' => 'https://example.com/submit' );
	}
	$validated = formblox_run_ability( 'validate-form', array( 'definition' => $definition ) );
	formblox_ability_assert( ! is_wp_error( $validated ) && $validated['valid'], 'Template validation failed: ' . $template );
	Form_Codec::validate( $validated['saved_markup'] );
}
// Save filters must never commit a materially changed form or receipt.
$before_revisions = array_keys( wp_get_post_revisions( $ability_post_id ) );
$alter_save       = static function ( $data ) {
	$data['post_content'] .= '<p>Injected by a save filter</p>';
	return $data;
};
add_filter( 'wp_insert_post_data', $alter_save );
$rollback = formblox_run_ability(
	'create-form',
	array(
		'post_id'         => $ability_post_id,
		'content_version' => hash( 'sha256', $current ),
		'request_id'      => 'rollback-create',
		'definition'      => array(),
	)
);
remove_filter( 'wp_insert_post_data', $alter_save );
formblox_ability_assert( is_wp_error( $rollback ) && 'formblox_save_changed' === $rollback->get_error_code(), 'Save-filter changes were accepted.' );
formblox_ability_assert( get_post( $ability_post_id )->post_content === $current, 'Rollback left modified content.' );
formblox_ability_assert( array_keys( wp_get_post_revisions( $ability_post_id ) ) === $before_revisions, 'Rollback retained an invalid revision.' );
$receipts = get_post_meta( $ability_post_id, '_formblox_ability_receipts', true );
formblox_ability_assert( ! isset( $receipts[ $author_id . ':rollback-create' ] ), 'Rollback retained a receipt.' );

// Publication-changing filters must be rejected before transition hooks run.
wp_set_current_user( $admin->ID );
foreach ( array(
	'draft'   => 'publish',
	'publish' => 'draft',
) as $original_status => $changed_status ) {
	$status_post_id  = wp_insert_post(
		array(
			'post_type'   => 'page',
			'post_status' => $original_status,
			'post_title'  => 'Status guard regression',
		)
	);
	$transitions     = array();
	$status_filter   = static function ( $data, $postarr ) use ( $status_post_id, $changed_status ) {
		if ( (int) ( $postarr['ID'] ?? 0 ) === $status_post_id ) {
			$data['post_status'] = $changed_status;
		}
		return $data;
	};
	$transition_hook = static function ( $new_status, $old_status, $changed_post ) use ( $status_post_id, &$transitions ) {
		if ( $status_post_id === $changed_post->ID && $new_status !== $old_status ) {
			$transitions[] = $new_status;
		}
	};
	add_filter( 'wp_insert_post_data', $status_filter, 10, 2 );
	add_action( 'transition_post_status', $transition_hook, 10, 3 );
	$status_result = formblox_run_ability(
		'create-form',
		array(
			'post_id'         => $status_post_id,
			'content_version' => hash( 'sha256', '' ),
			'request_id'      => 'status-create',
			'definition'      => array(),
		)
	);
	remove_filter( 'wp_insert_post_data', $status_filter, 10 );
	remove_action( 'transition_post_status', $transition_hook, 10 );
	formblox_ability_assert( is_wp_error( $status_result ), 'An unexpected publication transition was accepted.' );
	formblox_ability_assert( get_post_status( $status_post_id ) === $original_status && '' === get_post( $status_post_id )->post_content, 'Status rejection failed to restore the owner.' );
	formblox_ability_assert( empty( $transitions ), 'Publication transition hooks ran before rejection.' );
	formblox_ability_assert( ! get_post_meta( $status_post_id, '_formblox_ability_receipts', true ), 'Status rejection retained a receipt.' );
	formblox_ability_assert( ! wp_get_post_revisions( $status_post_id ), 'Status rejection retained a revision.' );
	// The temporary guard must be removed so a normal editor can change status.
	wp_update_post(
		array(
			'ID'          => $status_post_id,
			'post_status' => $changed_status,
		)
	);
	formblox_ability_assert( get_post_status( $status_post_id ) === $changed_status, 'Status guard leaked beyond an ability save.' );
}

// Status changes made by later save hooks must also roll back.
$late_status_id   = wp_insert_post(
	array(
		'post_type'   => 'page',
		'post_status' => 'draft',
	)
);
$late_status_hook = static function ( $saved_id ) use ( $late_status_id ) {
	global $wpdb;
	if ( $late_status_id === $saved_id ) {
		$wpdb->update( $wpdb->posts, array( 'post_status' => 'publish' ), array( 'ID' => $saved_id ) );
	}
};
add_action( 'save_post_page', $late_status_hook );
$late_status_result = formblox_run_ability(
	'create-form',
	array(
		'post_id'         => $late_status_id,
		'content_version' => hash( 'sha256', '' ),
		'request_id'      => 'late-status-create',
		'definition'      => array(),
	)
);
remove_action( 'save_post_page', $late_status_hook );
formblox_ability_assert( is_wp_error( $late_status_result ) && 'formblox_save_changed' === $late_status_result->get_error_code(), 'A late status change was accepted.' );
formblox_ability_assert( 'draft' === get_post_status( $late_status_id ) && '' === get_post( $late_status_id )->post_content, 'Late status change was not rolled back.' );

// Controls cannot be hidden in notification subtrees.
$hidden_control = formblox_run_ability(
	'validate-form',
	array(
		'definition' => array(
			'blocks' => array(
				array(
					'name'        => 'formblox/form-submission-notification',
					'innerBlocks' => array(
						array(
							'name'        => 'core/group',
							'innerBlocks' => array( array( 'name' => 'formblox/form-submit-button' ) ),
						),
					),
				),
			),
		),
	)
);
formblox_ability_assert( is_wp_error( $hidden_control ), 'A form with only a notification-hidden submit button was valid.' );

// Explicit anchors survive creation; shared owners remain explicit.
wp_set_current_user( $admin->ID );
$shared_id = wp_insert_post(
	array(
		'post_type'   => 'wp_block',
		'post_status' => 'publish',
		'post_title'  => 'Shared agent form',
	)
);
$shared    = formblox_run_ability(
	'create-form',
	array(
		'post_id'         => $shared_id,
		'content_version' => hash( 'sha256', '' ),
		'request_id'      => 'shared-create',
		'definition'      => array(
			'attributes' => array( 'anchor' => 'shared-contact' ),
			'blocks'     => array(
				array(
					'name'       => 'core/heading',
					'attributes' => array(
						'anchor'  => 'shared-title',
						'content' => 'Contact',
					),
				),
				array(
					'name'       => 'core/paragraph',
					'attributes' => array( 'content' => '<a href="#shared-title">Back to heading</a>' ),
				),
				array(
					'name'       => 'formblox/form-input',
					'attributes' => array(
						'name' => 'email',
						'type' => 'email',
					),
				),
				array( 'name' => 'formblox/form-submit-button' ),
			),
		),
	)
);
formblox_ability_assert( ! is_wp_error( $shared ) && $shared['live'], 'Published shared owner create failed.' );
$shared_record = formblox_run_ability(
	'get-form',
	array(
		'post_id' => $shared_id,
		'path'    => $shared['path'],
	)
);
formblox_ability_assert( $shared_record['shared'] && 'shared-contact' === $shared_record['definition']['attributes']['anchor'], 'Shared flag or explicit anchor lost.' );
$reference_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'draft',
		'post_content' => '<!-- wp:block {"ref":' . $shared_id . '} /-->',
	)
);
$references   = formblox_run_ability( 'list-forms', array( 'post_id' => $reference_id ) );
formblox_ability_assert( empty( $references['forms'] ) && $shared_id === $references['references'][0]['owner_post_id'], 'Synced pattern was silently inlined or lost.' );
$shared_record_markup = get_post( $shared_id )->post_content;
$copy                 = formblox_run_ability(
	'duplicate-form',
	array(
		'post_id'                => $shared_id,
		'path'                   => $shared['path'],
		'content_version'        => $shared['content_version'],
		'target_post_id'         => $reference_id,
		'target_content_version' => hash( 'sha256', get_post( $reference_id )->post_content ),
		'request_id'             => 'cross-owner-copy',
	)
);
formblox_ability_assert( ! is_wp_error( $copy ) && ! $copy['live'], 'Cross-owner copy failed or changed publication status.' );
formblox_ability_assert( get_post( $shared_id )->post_content === $shared_record_markup, 'Source changed during copy.' );

$copied_record  = formblox_run_ability(
	'get-form',
	array(
		'post_id' => $reference_id,
		'path'    => $copy['path'],
	)
);
$new_heading_id = $copied_record['definition']['innerBlocks'][0]['attributes']['anchor'];
formblox_ability_assert( 'shared-title' !== $new_heading_id && str_contains( get_post( $reference_id )->post_content, 'href="#' . $new_heading_id . '"' ), 'Copy did not remap fragment references.' );

$template_part_id = wp_insert_post(
	array(
		'post_type'    => 'wp_template_part',
		'post_status'  => 'publish',
		'post_name'    => 'agent-form-part',
		'post_content' => $shared_record_markup,
	)
);
wp_set_object_terms( $template_part_id, get_stylesheet(), 'wp_theme' );
$template_reference_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'draft',
		'post_content' => '<!-- wp:template-part {"slug":"agent-form-part","theme":"' . get_stylesheet() . '"} /-->',
	)
);
$template_references   = formblox_run_ability( 'list-forms', array( 'post_id' => $template_reference_id ) );
formblox_ability_assert( empty( $template_references['forms'] ) && ( $template_references['references'][0]['owner_post_id'] ?? 0 ) === $template_part_id, 'Saved template-part reference was lost.' );

$request = new WP_REST_Request( 'POST', '/wp-abilities/v1/abilities/formblox/update-form/run' );
$request->set_param(
	'input',
	array(
		'post_id'         => $reference_id,
		'path'            => $copy['path'],
		'content_version' => $copy['content_version'],
		'operations'      => array(
			array(
				'operation'  => 'update',
				'path'       => array(),
				'attributes' => array( 'style' => array( 'unknown' => 'bad' ) ),
			),
		),
	)
);
formblox_ability_assert( 400 === rest_do_request( $request )->get_status(), 'REST accepted unknown nested style properties.' );
echo "Native abilities integration passed.\n";
