<?php
/**
 * Verify the official MCP Adapter transport using an isolated installation.
 *
 * @package WPFormsBlocks
 */

/**
 * Send a real MCP JSON-RPC message through the WordPress REST transport.
 *
 * @param string $method MCP method.
 * @param array<string,mixed> $params Parameters.
 * @param string $session Session identifier.
 * @return WP_REST_Response Transport response.
 */
function formblox_mcp_request( $method, $params, $session = '' ) {
	$request = new WP_REST_Request( 'POST', '/mcp/mcp-adapter-default-server' );
	$request->set_header( 'Content-Type', 'application/json' );
	$request->set_header( 'Accept', 'application/json, text/event-stream' );
	$request->set_header( 'MCP-Protocol-Version', '2025-06-18' );
	if ( $session ) {
		$request->set_header( 'Mcp-Session-Id', $session );
	}
	$request->set_body(
		wp_json_encode(
			array(
				'jsonrpc' => '2.0',
				'id'      => 1,
				'method'  => $method,
				'params'  => $params,
			)
		)
	);
	$response = rest_do_request( $request );
	if ( 200 !== $response->get_status() || isset( json_decode( wp_json_encode( $response->get_data() ), true )['error'] ) ) {
		throw new RuntimeException( 'MCP request failed: ' . wp_json_encode( $response->get_data() ) );
	}
	return $response;
}

$mcp_admin = get_user_by( 'login', 'admin' );
wp_set_current_user( $mcp_admin->ID );
$initialized = formblox_mcp_request(
	'initialize',
	array(
		'protocolVersion' => '2025-06-18',
		'capabilities'    => new stdClass(),
		'clientInfo'      => array(
			'name'    => 'Formblox integration tests',
			'version' => '1.0',
		),
	)
);
$headers     = array_change_key_case( $initialized->get_headers(), CASE_LOWER );
$session     = $headers['mcp-session-id'] ?? '';
$discovered  = formblox_mcp_request(
	'tools/call',
	array(
		'name'      => 'mcp-adapter-discover-abilities',
		'arguments' => new stdClass(),
	),
	$session
);
$data        = json_decode( wp_json_encode( $discovered->get_data() ), true );

$result        = $data['result']['structuredContent'] ?? json_decode( $data['result']['content'][0]['text'] ?? '{}', true );
$ability_names = array_column( $result['abilities'] ?? array(), 'name' );
if ( 8 !== count(
	array_filter(
		$ability_names,
		static function ( $name ) {
			return str_starts_with( $name, 'formblox/' ); }
	)
) ) {
	throw new RuntimeException( 'The MCP adapter did not discover all form abilities.' );
}
$mcp_post_id = wp_insert_post(
	array(
		'post_title'   => 'MCP-created form',
		'post_type'    => 'post',
		'post_status'  => 'draft',
		'post_content' => '',
	)
);
$executed    = formblox_mcp_request(
	'tools/call',
	array(
		'name'      => 'mcp-adapter-execute-ability',
		'arguments' => array(
			'ability_name' => 'formblox/create-form',
			'parameters'   => array(
				'post_id'         => $mcp_post_id,
				'content_version' => hash( 'sha256', '' ),
				'request_id'      => 'mcp-create-contact',
				'definition'      => array( 'template' => 'contact' ),
			),
		),
	),
	$session
);
$data        = json_decode( wp_json_encode( $executed->get_data() ), true );
$result      = $data['result']['structuredContent'] ?? json_decode( $data['result']['content'][0]['text'] ?? '{}', true );
if ( empty( $result['success'] ) || ! has_block( 'formblox/form', get_post( $mcp_post_id )->post_content ) ) {
	throw new RuntimeException( 'MCP form creation failed: ' . wp_json_encode( $data ) );
}
echo "Official MCP Adapter 0.7.0 discovery and execution passed.\n";
