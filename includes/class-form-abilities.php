<?php
/**
 * Native WordPress abilities for agent-driven form management.
 *
 * @package WPFormsBlocks
 */

namespace WPFormsBlocks;

defined( 'ABSPATH' ) || exit;

/**
 * Registers schemas, descriptions, permissions, REST and MCP discovery metadata.
 */
final class Form_Abilities {
	/**
	 * Register the abilities category before the ability registry initializes.
	 *
	 * @return void
	 */
	public static function category() {
		wp_register_ability_category(
			'formblox',
			array(
				'label'       => __( 'Forms', 'wp-forms-blocks' ),
				'description' => __( 'Build and manage native Gutenberg forms in WordPress content.', 'wp-forms-blocks' ),
			)
		);
	}

	/**
	 * Register each operation as a native ability, independently of MCP installation.
	 *
	 * @return void
	 */
	public static function register() {
		$definitions = array(
			'describe-capabilities' => array( __( 'Describe form capabilities', 'wp-forms-blocks' ), __( 'Discover supported field types, templates, block definitions and editing conventions. These abilities manage form configuration; they do not submit forms, send email, or retrieve submission entries.', 'wp-forms-blocks' ), true, false ),
			'list-forms'            => array( __( 'List forms', 'wp-forms-blocks' ), __( 'Discover forms in content you can edit. Pagination scans owning posts, including posts without forms; continue until next_page is null. A post_id restricts the scan to one owner. Synced-pattern references identify their separate owner and are not automatically followed. Returned paths count named blocks only, excluding freeform HTML.', 'wp-forms-blocks' ), true, false ),
			'get-form'              => array( __( 'Get form', 'wp-forms-blocks' ), __( 'Read a complete form definition with HTML-sourced field properties, relative child paths, and the SHA-256 content_version of its owning post. Use the owner post_id and absolute form path returned by discovery. Editing a shared pattern or template affects all its uses.', 'wp-forms-blocks' ), true, false ),
			'create-form'           => array( __( 'Create form', 'wp-forms-blocks' ), __( 'Insert a structured form into an existing content owner. Create a draft post/page with WordPress content tools first when needed. Supply its current SHA-256 content_version and a unique request_id. parent_path selects a Group/Column, or [] selects the content root; index is a named-block sibling index, omitted to append. Publication status is preserved: edits to published content are immediately live. Contact forms email the site administrator; custom forms submit to the configured URL.', 'wp-forms-blocks' ), false, false ),
			'update-form'           => array( __( 'Update form', 'wp-forms-blocks' ), __( 'Apply ordered update/insert/remove/move operations to one form. Operation paths and parent_path are relative to the form root ([]); paths refer to the result of preceding operations. Move index refers to the destination list after removing the source. Style changes replace the style object; include existing properties to retain them. Unknown existing blocks are retained but cannot be rebuilt. Supply the owner content_version; stale edits fail. Published content changes immediately.', 'wp-forms-blocks' ), false, true ),
			'duplicate-form'        => array( __( 'Duplicate form', 'wp-forms-blocks' ), __( 'Copy a form into an explicit target owner. Supply source and target content versions, target_post_id, and a unique request_id. Both owners require edit permission. Anchored blocks receive fresh anchors. Target parent_path/index use the same placement conventions as create-form. Publication status is preserved.', 'wp-forms-blocks' ), false, false ),
			'delete-form'           => array( __( 'Delete form', 'wp-forms-blocks' ), __( 'Remove exactly one form subtree from its owning content while preserving surrounding content. Does not delete the post, comments, privacy requests, or other forms. Requires the owner content_version. Removing a form from published content takes effect immediately.', 'wp-forms-blocks' ), false, true ),
			'validate-form'         => array( __( 'Validate form', 'wp-forms-blocks' ), __( 'Validate a proposed structured form definition and return Gutenberg saved markup and its parsed definition without saving, frontend rendering, submission, email, or privacy-request processing. Unsupported fields, duplicate names, invalid placement, and broken native submission contracts are rejected.', 'wp-forms-blocks' ), true, false ),
		);
		$schemas     = self::schemas();
		foreach ( $definitions as $name => $definition ) {
			wp_register_ability(
				'formblox/' . $name,
				array(
					'label'               => $definition[0],
					'description'         => $definition[1],
					'category'            => 'formblox',
					'input_schema'        => $schemas[ $name ]['input'],
					'output_schema'       => $schemas[ $name ]['output'],
					'permission_callback' => static function ( $input ) use ( $name ) {
						return self::permission( $name, $input ); },
					'execute_callback'    => static function ( $input ) use ( $name ) {
						return self::execute( $name, $input ); },
					'meta'                => array(
						'public'       => true,
						'show_in_rest' => true,
						'mcp'          => array(
							'public' => true,
							'type'   => 'tool',
						),
						'annotations'  => array(
							'readonly'    => $definition[2],
							'destructive' => $definition[3],
							'idempotent'  => $definition[2],
						),
					),
				)
			);
		}
	}

	/**
	 * Check the exact content owners before calling the service.
	 *
	 * @param string $name Ability suffix.
	 * @param array<string,mixed> $input Input.
	 * @return bool Permission.
	 */
	public static function permission( $name, $input ) {
		if ( ! Form_Service::can_discover() ) {
			return false;
		}
		if ( isset( $input['post_id'] ) && is_wp_error( Form_Service::owner( $input['post_id'] ) ) ) {
			return false;
		}
		if ( 'duplicate-form' === $name && is_wp_error( Form_Service::owner( $input['target_post_id'] ) ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Dispatch a schema-validated operation.
	 *
	 * @param string $name Ability suffix.
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|\WP_Error Result.
	 */
	public static function execute( $name, $input ) {
		if ( ! self::permission( $name, $input ) ) {
			return new \WP_Error( 'formblox_forbidden', __( 'You cannot manage forms in this content.', 'wp-forms-blocks' ), array( 'status' => 403 ) );
		}
		switch ( $name ) {
			case 'describe-capabilities':
				return array(
					'field_types'       => Form_Codec::FIELD_TYPES,
					'block_types'       => Form_Codec::BLOCKS,
					'templates'         => array( 'contact', 'comment', 'privacy', 'custom' ),
					'post_types'        => Form_Service::post_types(),
					'definition_schema' => self::definition_schema(),
					'conventions'       => array(
						'paths'       => 'Named-block sibling indices excluding freeform HTML. Form paths are owner-relative; operation paths are form-relative.',
						'version'     => 'SHA-256 of the exact owning post_content; fetch it through WordPress REST content.raw for an owner without forms.',
						'receipts'    => 'Create/copy require request_id. The latest 50 requests per owner are retained. An identical retry returns the original result with replayed=true; refetch before further editing.',
						'publication' => 'Status is preserved. Changes to published owners are live immediately. Shared owners affect every use.',
						'submission'  => 'Contact email recipient is the site administrator. These tools manage definitions, not submissions.',
						'styles'      => 'Native color/gradient presets, spacing margin/padding, typography, and field border radius. Existing decoration is retained during semantic edits.',
					),
				);
			case 'list-forms':
				return Form_Service::listing( $input );
			case 'get-form':
				return Form_Service::get( $input['post_id'], $input['path'] );
			case 'validate-form':
				return Form_Service::preview( $input['definition'] );
			default:
				return Form_Service::mutate( $name, $input );
		}
	}

	/**
	 * Construct an object schema with explicit properties and required keys.
	 *
	 * @param array<string,mixed> $properties Properties.
	 * @param array<string> $required Required names.
	 * @return array<string,mixed> Object schema.
	 */
	private static function object_schema( $properties, $required = array() ) {
		return array(
			'type'                 => 'object',
			'properties'           => $properties,
			'required'             => $required,
			'additionalProperties' => false,
		);
	}

	/**
	 * Supported saved-block attributes. Semantic validation is block-specific.
	 *
	 * @return array<string,mixed> Attributes schema.
	 */
	private static function attributes_schema() {
		$string                          = array(
			'type'      => 'string',
			'maxLength' => 20000,
		);
		$properties                      = array_fill_keys( array( 'submissionMethod', 'method', 'action', 'type', 'name', 'label', 'placeholder', 'value', 'visibilityPermissions', 'anchor', 'className', 'backgroundColor', 'textColor', 'gradient', 'fontSize', 'fontFamily', 'align', 'verticalAlignment', 'width', 'content', 'text', 'tagName' ), $string );
		$properties['isStackedOnMobile'] = array( 'type' => 'boolean' );
		$properties['required']          = array( 'type' => 'boolean' );
		$properties['inlineLabel']       = array( 'type' => 'boolean' );
		$properties['level']             = array(
			'type'    => 'integer',
			'minimum' => 1,
			'maximum' => 6,
		);
		$properties['layout']            = self::object_schema(
			array(
				'type'           => array(
					'type' => 'string',
					'enum' => array( 'default', 'constrained', 'flex' ),
				),
				'orientation'    => array(
					'type' => 'string',
					'enum' => array( 'horizontal', 'vertical' ),
				),
				'justifyContent' => $string,
				'flexWrap'       => $string,
				'contentSize'    => $string,
				'wideSize'       => $string,
			)
		);
		$box                             = array(
			'type'                 => array( 'string', 'object' ),
			'properties'           => array_fill_keys( array( 'top', 'right', 'bottom', 'left' ), $string ),
			'additionalProperties' => false,
		);
		$properties['style']             = self::object_schema(
			array(
				'color'      => self::object_schema( array_fill_keys( array( 'text', 'background', 'gradient' ), $string ) ),
				'spacing'    => self::object_schema(
					array(
						'margin'   => $box,
						'padding'  => $box,
						'blockGap' => $box,
					)
				),
				'typography' => self::object_schema( array_fill_keys( array( 'fontSize', 'fontFamily', 'fontStyle', 'fontWeight', 'lineHeight', 'textDecoration', 'letterSpacing', 'textTransform' ), $string ) ),
				'border'     => self::object_schema(
					array(
						'radius' => array(
							'type'                 => array( 'string', 'object' ),
							'properties'           => array_fill_keys( array( 'topLeft', 'topRight', 'bottomLeft', 'bottomRight' ), $string ),
							'additionalProperties' => false,
						),
					)
				),
			)
		);
		return self::object_schema( $properties );
	}

	/**
	 * A bounded recursive block schema supported by WordPress REST validation.
	 *
	 * @param int $depth Remaining depth.
	 * @return array<string,mixed> Block schema.
	 */
	private static function block_schema( $depth = 12 ) {
		$properties = array(
			'name'       => array(
				'type' => 'string',
				'enum' => Form_Codec::BLOCKS,
			),
			'attributes' => self::attributes_schema(),
		);
		if ( $depth > 0 ) {
			$properties['innerBlocks'] = array(
				'type'     => 'array',
				'maxItems' => 200,
				'items'    => self::block_schema( $depth - 1 ),
			);
		}
		return self::object_schema( $properties, array( 'name' ) );
	}

	/**
	 * Agent-supplied form definition schema.
	 *
	 * @return array<string,mixed> Definition schema.
	 */
	private static function definition_schema() {
		return self::object_schema(
			array(
				'template'   => array(
					'type' => 'string',
					'enum' => array( 'contact', 'comment', 'privacy', 'custom' ),
				),
				'attributes' => self::attributes_schema(),
				'blocks'     => array(
					'type'     => 'array',
					'maxItems' => 200,
					'items'    => self::block_schema(),
				),
			)
		);
	}

	/**
	 * Build operation-specific input and output schemas.
	 *
	 * @return array<string,mixed> Schemas keyed by ability suffix.
	 */
	private static function schemas() {
		$id         = array(
			'type'    => 'integer',
			'minimum' => 1,
		);
		$path       = array(
			'type'        => 'array',
			'maxItems'    => 40,
			'items'       => array(
				'type'    => 'integer',
				'minimum' => 0,
			),
			'description' => 'Named-block sibling indices, excluding freeform HTML.',
		);
		$version    = array(
			'type'        => 'string',
			'pattern'     => '^[a-f0-9]{64}$',
			'description' => 'SHA-256 of the exact owning post_content.',
		);
		$request_id = array(
			'type'      => 'string',
			'minLength' => 1,
			'maxLength' => 100,
			'pattern'   => '^[a-zA-Z0-9._:-]+$',
		);
		$location   = array(
			'parent_path' => $path,
			'index'       => array(
				'type'    => 'integer',
				'minimum' => 0,
			),
		);
		$owner      = array(
			'post_id' => $id,
			'path'    => $path,
		);
		$write      = $owner + array( 'content_version' => $version );
		$tree       = array(
			'type'       => 'object',
			'required'   => array( 'name', 'attributes', 'innerBlocks', 'path', 'editable' ),
			'properties' => array(
				'name'        => array( 'type' => 'string' ),
				'attributes'  => array( 'type' => 'object' ),
				'innerBlocks' => array(
					'type'  => 'array',
					'items' => array( 'type' => 'object' ),
				),
				'path'        => $path,
				'editable'    => array( 'type' => 'boolean' ),
			),
		);
		$record     = self::object_schema(
			array(
				'post_id'         => $id,
				'post_type'       => array( 'type' => 'string' ),
				'post_status'     => array( 'type' => 'string' ),
				'path'            => $path,
				'content_version' => $version,
				'shared'          => array( 'type' => 'boolean' ),
				'definition'      => $tree,
			),
			array( 'post_id', 'path', 'content_version', 'definition' )
		);
		$result     = self::object_schema(
			array(
				'post_id'         => $id,
				'path'            => $path,
				'content_version' => $version,
				'post_status'     => array( 'type' => 'string' ),
				'live'            => array( 'type' => 'boolean' ),
				'deleted'         => array( 'type' => 'boolean' ),
				'replayed'        => array( 'type' => 'boolean' ),
			),
			array( 'post_id', 'path', 'content_version', 'live', 'deleted', 'replayed' )
		);
		$operations = array(
			'type'     => 'array',
			'minItems' => 1,
			'maxItems' => 100,
			'items'    => self::object_schema(
				array(
					'operation'   => array(
						'type' => 'string',
						'enum' => array( 'update', 'insert', 'remove', 'move' ),
					),
					'path'        => $path,
					'parent_path' => $path,
					'index'       => array(
						'type'    => 'integer',
						'minimum' => 0,
					),
					'attributes'  => self::attributes_schema(),
					'block'       => self::block_schema(),
				),
				array( 'operation' )
			),
		);
		$strings    = array(
			'type'  => 'array',
			'items' => array( 'type' => 'string' ),
		);
		return array(
			'describe-capabilities' => array(
				'input'  => self::object_schema( array() ),
				'output' => self::object_schema(
					array(
						'field_types'       => $strings,
						'block_types'       => $strings,
						'templates'         => $strings,
						'post_types'        => $strings,
						'definition_schema' => array( 'type' => 'object' ),
						'conventions'       => array(
							'type'                 => 'object',
							'additionalProperties' => array( 'type' => 'string' ),
						),
					),
					array( 'field_types', 'templates', 'definition_schema' )
				),
			),
			'list-forms'            => array(
				'input'  => self::object_schema(
					array(
						'post_id'   => $id,
						'post_type' => array( 'type' => 'string' ),
						'page'      => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'per_page'  => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 100,
						),
					)
				),
				'output' => self::object_schema(
					array(
						'forms'      => array(
							'type'  => 'array',
							'items' => $record,
						),
						'references' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'object' ),
						),
						'warnings'   => array(
							'type'  => 'array',
							'items' => array( 'type' => 'object' ),
						),
						'next_page'  => array( 'type' => array( 'integer', 'null' ) ),
					),
					array( 'forms', 'references', 'warnings', 'next_page' )
				),
			),
			'get-form'              => array(
				'input'  => self::object_schema( $owner, array( 'post_id', 'path' ) ),
				'output' => $record,
			),
			'create-form'           => array(
				'input'  => self::object_schema(
					array(
						'post_id'         => $id,
						'content_version' => $version,
						'request_id'      => $request_id,
						'definition'      => self::definition_schema(),
					) + $location,
					array( 'post_id', 'content_version', 'request_id', 'definition' )
				),
				'output' => $result,
			),
			'update-form'           => array(
				'input'  => self::object_schema( $write + array( 'operations' => $operations ), array( 'post_id', 'path', 'content_version', 'operations' ) ),
				'output' => $result,
			),
			'duplicate-form'        => array(
				'input'  => self::object_schema(
					$write + array(
						'target_post_id'         => $id,
						'target_content_version' => $version,
						'request_id'             => $request_id,
					) + $location,
					array( 'post_id', 'path', 'content_version', 'target_post_id', 'target_content_version', 'request_id' )
				),
				'output' => $result,
			),
			'delete-form'           => array(
				'input'  => self::object_schema( $write, array( 'post_id', 'path', 'content_version' ) ),
				'output' => $result,
			),
			'validate-form'         => array(
				'input'  => self::object_schema( array( 'definition' => self::definition_schema() ), array( 'definition' ) ),
				'output' => self::object_schema(
					array(
						'valid'        => array( 'type' => 'boolean' ),
						'saved_markup' => array( 'type' => 'string' ),
						'definition'   => $tree,
					),
					array( 'valid', 'saved_markup', 'definition' )
				),
			),
		);
	}
}

add_action( 'wp_abilities_api_categories_init', array( Form_Abilities::class, 'category' ) );
add_action( 'wp_abilities_api_init', array( Form_Abilities::class, 'register' ) );
