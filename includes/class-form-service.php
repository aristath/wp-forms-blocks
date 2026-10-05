<?php
/**
 * Permission-aware management of forms in native WordPress content entities.
 *
 * @package WPFormsBlocks
 */

namespace WPFormsBlocks;

use InvalidArgumentException;
use WP_Error;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Uses native saves inside row-locked transactions, without a separate form store.
 */
final class Form_Service {
	/**
	 * Content-bearing post types the current site exposes to editing.
	 *
	 * @return array<string> Post type names.
	 */
	public static function post_types() {
		$types = array();
		foreach ( get_post_types( array(), 'objects' ) as $type ) {
			if ( $type->show_in_rest && post_type_supports( $type->name, 'editor' ) && 'attachment' !== $type->name ) {
				$types[] = $type->name;
			}
		}
		return $types;
	}

	/**
	 * Restrict definition discovery to users allowed to author supported content.
	 *
	 * @return bool Whether the current user is a content editor.
	 */
	public static function can_discover() {
		foreach ( self::post_types() as $name ) {
			$type = get_post_type_object( $name );
			if ( $type && current_user_can( $type->cap->edit_posts ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Require edit permission for the actual owning entity, including reads.
	 *
	 * @param int $id Owner ID.
	 * @return WP_Post|WP_Error Owner or permission error.
	 */
	public static function owner( $id ) {
		$post = get_post( $id );
		if ( ! $post || ! in_array( $post->post_type, self::post_types(), true ) || in_array( $post->post_status, array( 'trash', 'auto-draft' ), true ) ) {
			return new WP_Error( 'formblox_invalid_owner', __( 'The requested content cannot contain editable forms.', 'wp-forms-blocks' ), array( 'status' => 404 ) );
		}
		if ( ! current_user_can( 'edit_post', $id ) ) {
			return new WP_Error( 'formblox_forbidden', __( 'You cannot access forms in this content.', 'wp-forms-blocks' ), array( 'status' => 403 ) );
		}
		return $post;
	}

	/**
	 * Extract one form using a named-block path from discovery.
	 *
	 * @param string $content Owner content.
	 * @param array<int> $path Named-block path.
	 * @return array<string,mixed> Source location.
	 */
	public static function form_location( $content, $path ) {
		$map = Form_Codec::locations( $content );
		$key = implode( '.', $path );
		if ( ! isset( $map[ $key ] ) || 'formblox/form' !== $map[ $key ]['name'] ) {
			throw new InvalidArgumentException( 'The path does not identify a form in the owning content.' );
		}
		return $map[ $key ];
	}

	/**
	 * Read a complete form and its owning content version.
	 *
	 * @param int $id Owner ID.
	 * @param array<int> $path Form path.
	 * @return array<string,mixed>|WP_Error Form record.
	 */
	public static function get( $id, $path ) {
		$post = self::owner( $id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		try {
			$node = self::form_location( $post->post_content, $path );
			return self::record( $post, $node );
		} catch ( InvalidArgumentException $error ) {
			return self::invalid( $error );
		}
	}

	/**
	 * Build a record with a complete structured definition.
	 *
	 * @param WP_Post $post Owner.
	 * @param array<string,mixed> $node Mapped form.
	 * @return array<string,mixed> Record.
	 */
	private static function record( $post, $node ) {
		$markup = substr( $post->post_content, $node['start'], $node['end'] - $node['start'] );
		return array(
			'post_id'         => $post->ID,
			'post_type'       => $post->post_type,
			'post_status'     => $post->post_status,
			'path'            => $node['path'],
			'content_version' => hash( 'sha256', $post->post_content ),
			'shared'          => in_array( $post->post_type, array( 'wp_block', 'wp_template', 'wp_template_part' ), true ),
			'definition'      => Form_Codec::read( $markup ),
		);
	}

	/**
	 * Paginated owner discovery. References are reported, never silently followed.
	 *
	 * @param array<string,mixed> $input Pagination or owner selection.
	 * @return array<string,mixed>|WP_Error Discovery results.
	 */
	public static function listing( $input ) {
		$page       = $input['page'] ?? 1;
		$limit      = $input['per_page'] ?? 20;
		$forms      = array();
		$references = array();
		$warnings   = array();
		if ( ! empty( $input['post_id'] ) ) {
			$post = self::owner( $input['post_id'] );
			if ( is_wp_error( $post ) ) {
				return $post;
			}
			$ids  = array( $post->ID );
			$more = false;
		} else {
			$types = self::post_types();
			if ( isset( $input['post_type'] ) ) {
				if ( ! in_array( $input['post_type'], $types, true ) ) {
					return new WP_Error( 'formblox_invalid_owner', __( 'Unsupported content type.', 'wp-forms-blocks' ), array( 'status' => 400 ) );
				}
				$types = array( $input['post_type'] );
			}
			$query = new \WP_Query(
				array(
					'post_type'      => $types,
					'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
					'posts_per_page' => $limit + 1,
					'offset'         => ( $page - 1 ) * $limit,
					'orderby'        => 'ID',
					'order'          => 'ASC',
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			);
			$ids   = $query->posts;
			$more  = count( $ids ) > $limit;
			$ids   = array_slice( $ids, 0, $limit );
		}
		foreach ( $ids as $id ) {
			$post = self::owner( $id );
			if ( is_wp_error( $post ) ) {
				continue;
			}
			try {
				foreach ( Form_Codec::locations( $post->post_content ) as $node ) {
					if ( 'formblox/form' === $node['name'] ) {
						$forms[] = self::record( $post, $node );
					} elseif ( 'core/template-part' === $node['name'] && isset( $node['attributes']['slug'] ) ) {
						$theme    = $node['attributes']['theme'] ?? get_stylesheet();
						$template = get_block_template( $theme . '//' . $node['attributes']['slug'], 'wp_template_part' );
						if ( $template && ! empty( $template->wp_id ) && ! is_wp_error( self::owner( $template->wp_id ) ) ) {
							$references[] = array(
								'post_id'       => $post->ID,
								'path'          => $node['path'],
								'owner_post_id' => $template->wp_id,
								'shared'        => true,
							);
						}
					} elseif ( 'core/block' === $node['name'] && isset( $node['attributes']['ref'] ) ) {
						$ref = (int) $node['attributes']['ref'];
						if ( ! is_wp_error( self::owner( $ref ) ) ) {
							$references[] = array(
								'post_id'       => $post->ID,
								'path'          => $node['path'],
								'owner_post_id' => $ref,
								'shared'        => true,
							);
						}
					}
				}
			} catch ( InvalidArgumentException $error ) {
				$warnings[] = array(
					'post_id' => $post->ID,
					'message' => $error->getMessage(),
				);
			}
		}
		return array(
			'forms'      => $forms,
			'references' => $references,
			'warnings'   => $warnings,
			'next_page'  => $more ? $page + 1 : null,
		);
	}

	/**
	 * Convert codec rejection to a structured API error.
	 *
	 * @param InvalidArgumentException $error Definition error.
	 * @return WP_Error API error.
	 */
	private static function invalid( $error ) {
		return new WP_Error( 'formblox_invalid_form', $error->getMessage(), array( 'status' => 422 ) );
	}

	/**
	 * Build a definition preview without rendering/submitting or saving a form.
	 *
	 * @param array<string,mixed> $definition Form definition.
	 * @return array<string,mixed>|WP_Error Preview.
	 */
	public static function preview( $definition ) {
		try {
			$markup = Form_Codec::create( $definition );
			return array(
				'valid'        => true,
				'saved_markup' => $markup,
				'definition'   => Form_Codec::read( $markup ),
			);
		} catch ( InvalidArgumentException $error ) {
			return self::invalid( $error );
		}
	}

	/**
	 * Execute a native content mutation under a transaction and row locks.
	 *
	 * @param string $operation Ability suffix.
	 * @param array<string,mixed> $input Input validated by the ability schema.
	 * @return array<string,mixed>|WP_Error Result.
	 */
	public static function mutate( $operation, $input ) {
		global $wpdb;
		$target_id = 'duplicate-form' === $operation ? $input['target_post_id'] : $input['post_id'];
		$ids       = array_unique( array( (int) $input['post_id'], (int) $target_id ) );
		sort( $ids, SORT_NUMERIC );
		foreach ( $ids as $id ) {
			$owner = self::owner( $id );
			if ( is_wp_error( $owner ) ) {
				return $owner;
			}
		}
		// MyISAM does not honor row locks or rollback. Fail closed on such sites.
		if ( ! defined( 'DB_ENGINE' ) || 'sqlite' !== DB_ENGINE ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction control and locked-row verification require uncached database access.
			$engines = $wpdb->get_col( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (%s, %s)', $wpdb->posts, $wpdb->postmeta ) );
			if ( 2 !== count( $engines ) || array( 'InnoDB' ) !== array_values( array_unique( $engines ) ) ) {
				return new WP_Error( 'formblox_transactions_required', __( 'Form editing requires transactional WordPress database tables.', 'wp-forms-blocks' ), array( 'status' => 503 ) );
			}
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Transaction control and locked-row verification require uncached database access.
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return new WP_Error( 'formblox_lock_failed', __( 'Could not lock the content for editing.', 'wp-forms-blocks' ), array( 'status' => 503 ) );
		}
		$committed = false;
		try {
			$owners = array();
			foreach ( $ids as $id ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction control and locked-row verification require uncached database access.
				$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE ID = %d FOR UPDATE', $wpdb->posts, $id ) );
				if ( ! $row || $wpdb->last_error ) {
					return new WP_Error( 'formblox_lock_failed', __( 'Could not lock the content for editing.', 'wp-forms-blocks' ), array( 'status' => 503 ) );
				}
				clean_post_cache( $id );
				// Use the locked row rather than an older InnoDB consistent-read snapshot.
				wp_cache_set( $id, $row, 'posts' );
				$owners[ $id ] = self::owner( $id );
				if ( is_wp_error( $owners[ $id ] ) ) {
					return $owners[ $id ];
				}
			}
			$target      = $owners[ $target_id ];
			$source      = $owners[ $input['post_id'] ];
			$receipt_key = get_current_user_id() . ':' . ( $input['request_id'] ?? '' );
			$receipts    = get_post_meta( $target_id, '_formblox_ability_receipts', true );
			$receipts    = is_array( $receipts ) ? $receipts : array();
			$fingerprint = hash( 'sha256', $operation . wp_json_encode( $input ) );
			if ( isset( $input['request_id'], $receipts[ $receipt_key ] ) ) {
				if ( $receipts[ $receipt_key ]['fingerprint'] !== $fingerprint ) {
					return new WP_Error( 'formblox_request_conflict', __( 'This request ID was already used with different input.', 'wp-forms-blocks' ), array( 'status' => 409 ) );
				}
				$replay             = $receipts[ $receipt_key ]['result'];
				$replay['replayed'] = true;
				return $replay;
			}
			$target_version = 'duplicate-form' === $operation ? $input['target_content_version'] : $input['content_version'];
			if ( ! hash_equals( hash( 'sha256', $source->post_content ), $input['content_version'] ) || ! hash_equals( hash( 'sha256', $target->post_content ), $target_version ) ) {
				return new WP_Error( 'formblox_stale_content', __( 'The content changed. Read it again before editing.', 'wp-forms-blocks' ), array( 'status' => 409 ) );
			}
			if ( 'create-form' === $operation ) {
				$markup                 = Form_Codec::create( $input['definition'] );
				$markup                 = self::unique_anchors( $markup, false );
				list( $content, $path ) = self::place( $target->post_content, $markup, $input );
			} else {
				$location = self::form_location( $source->post_content, $input['path'] );
				$markup   = substr( $source->post_content, $location['start'], $location['end'] - $location['start'] );
				$path     = $input['path'];
				if ( 'duplicate-form' === $operation ) {
					$markup                 = self::unique_anchors( $markup );
					list( $content, $path ) = self::place( $target->post_content, $markup, $input );
				} else {
					$replacement = 'delete-form' === $operation ? '' : Form_Codec::edit( $markup, $input['operations'] );
					$content     = substr_replace( $target->post_content, $replacement, $location['start'], $location['end'] - $location['start'] );
				}
			}
			self::check_ids( $content );
			// Reject filtered status changes before WordPress writes or fires publication hooks.
			$status_guard = static function ( $id, $data ) use ( $target_id, $target ) {
				if ( (int) $id === (int) $target_id && $data['post_status'] !== $target->post_status ) {
						throw new InvalidArgumentException( esc_html__( 'Form edits cannot change publication status. The edit was rolled back.', 'wp-forms-blocks' ) );
				}
			};
			add_action( 'pre_post_update', $status_guard, PHP_INT_MAX, 2 );
			try {
				$result = wp_update_post(
					wp_slash(
						array(
							'ID'           => $target_id,
							'post_content' => $content,
						)
					),
					true
				);
			} finally {
				remove_action( 'pre_post_update', $status_guard, PHP_INT_MAX );
			}
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction control and locked-row verification require uncached database access.
			$saved = $wpdb->get_row( $wpdb->prepare( 'SELECT post_content, post_status FROM %i WHERE ID = %d', $wpdb->posts, $target_id ) );
			// KSES canonicalizes block comments and void-tag whitespace for authors.
			// Accept those cosmetic changes, but reject material changes by save filters.
			if ( ! $saved || ! is_string( $saved->post_content ) || self::canonical_markup( $saved->post_content ) !== self::canonical_markup( $content ) || $saved->post_status !== $target->post_status ) {
				return new WP_Error( 'formblox_save_changed', __( 'WordPress filters changed the proposed content or publication status. The edit was rolled back.', 'wp-forms-blocks' ), array( 'status' => 422 ) );
			}
			$content = $saved->post_content;
			$result  = array(
				'post_id'         => (int) $target_id,
				'path'            => $path,
				'content_version' => hash( 'sha256', $content ),
				'post_status'     => $saved->post_status,
				'live'            => 'publish' === $saved->post_status,
				'deleted'         => 'delete-form' === $operation,
				'replayed'        => false,
			);
			if ( isset( $input['request_id'] ) ) {
				$receipts[ $receipt_key ] = array(
					'fingerprint' => $fingerprint,
					'result'      => $result,
				);
				$receipts                 = array_slice( $receipts, -50, null, true );
				if ( false === update_post_meta( $target_id, '_formblox_ability_receipts', $receipts ) ) {
					return new WP_Error( 'formblox_receipt_failed', __( 'Could not record the operation. The edit was rolled back.', 'wp-forms-blocks' ), array( 'status' => 500 ) );
				}
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Transaction control and locked-row verification require uncached database access.
			$committed = false !== $wpdb->query( 'COMMIT' );
			if ( ! $committed ) {
				return new WP_Error( 'formblox_save_failed', __( 'Could not save the form edit.', 'wp-forms-blocks' ), array( 'status' => 500 ) );
			}
			return $result;
		} catch ( InvalidArgumentException $error ) {
			return self::invalid( $error );
		} finally {
			if ( ! $committed ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Transaction control and locked-row verification require uncached database access.
				$wpdb->query( 'ROLLBACK' );
			}
			foreach ( $ids as $id ) {
				clean_post_cache( $id );
			}
		}
	}

	/**
	 * Normalize only native comment serialization and void-tag slash whitespace.
	 *
	 * @param string $markup Saved markup.
	 * @return string Canonical comparison markup.
	 */
	private static function canonical_markup( $markup ) {
		return preg_replace( '/(<(?:input|br|hr|img)\b[^>]*?)\s+\/>/i', '$1/>', serialize_blocks( parse_blocks( $markup ) ) );
	}

	/**
	 * Assign fresh anchors to a copied form and its anchored descendants.
	 *
	 * @param string $markup Form markup.
	 * @param bool $copy Whether to replace existing anchors for a copy.
	 * @return string Copy markup.
	 */
	private static function unique_anchors( $markup, $copy = true ) {
		$remap = array();
		if ( $copy ) {
			$processor = new \WP_HTML_Tag_Processor( $markup );
			while ( $processor->next_tag() ) {
				$id = $processor->get_attribute( 'id' );
				if ( is_string( $id ) && '' !== $id ) {
					$remap[ $id ] = 'formblox-' . wp_generate_uuid4();
				}
			}
		}
		$keys = array_reverse( array_keys( Form_Codec::locations( $markup ) ) );
		foreach ( $keys as $key ) {
			$map  = Form_Codec::locations( $markup );
			$node = $map[ $key ];
			if ( '0' !== (string) $key && empty( $node['attributes']['anchor'] ) ) {
				continue;
			}
			$attrs = $node['attributes'];
			if ( ! $copy && ! empty( $attrs['anchor'] ) ) {
				continue;
			}
			$previous        = $attrs['anchor'] ?? '';
			$attrs['anchor'] = $remap[ $previous ] ?? 'formblox-' . wp_generate_uuid4();
			if ( '' !== $previous ) {
				$remap[ $previous ] = $attrs['anchor'];
			}
			$html = substr( $markup, $node['open'], $node['close'] - $node['open'] );
			$p    = new \WP_HTML_Tag_Processor( $html );
			if ( ! $p->next_tag() ) {
				throw new InvalidArgumentException( 'An anchored block has no saved wrapper.' );
			}
			if ( 'formblox/form-input' !== $node['name'] || 'hidden' !== ( $attrs['type'] ?? 'text' ) ) {
				$p->set_attribute( 'id', $attrs['anchor'] );
			}
			$new_markup = get_comment_delimited_block_content( $node['name'], $attrs, $p->get_updated_html() );
			$markup     = substr_replace( $markup, $new_markup, $node['start'], $node['end'] - $node['start'] );
		}
		if ( $copy && $remap ) {
			$processor = new \WP_HTML_Tag_Processor( $markup );
			while ( $processor->next_tag() ) {
				$id = $processor->get_attribute( 'id' );
				if ( is_string( $id ) && isset( $remap[ $id ] ) ) {
					$processor->set_attribute( 'id', $remap[ $id ] );
				}
				foreach ( array( 'for', 'aria-labelledby', 'aria-describedby', 'aria-controls' ) as $attribute ) {
					$value = $processor->get_attribute( $attribute );
					if ( is_string( $value ) ) {
						$ids = preg_split( '/\s+/', $value );
						$ids = array_map(
							static function ( $ref ) use ( $remap ) {
								return $remap[ $ref ] ?? $ref;
							},
							$ids
						);
						$processor->set_attribute( $attribute, implode( ' ', $ids ) );
					}
				}
				$href = $processor->get_attribute( 'href' );
				if ( is_string( $href ) && str_starts_with( $href, '#' ) && isset( $remap[ substr( $href, 1 ) ] ) ) {
					$processor->set_attribute( 'href', '#' . $remap[ substr( $href, 1 ) ] );
				}
			}
			$markup = $processor->get_updated_html();
		}
		return $markup;
	}

	/**
	 * Reject newly introduced duplicate HTML IDs before saving.
	 *
	 * @param string $content Proposed content.
	 * @return void
	 */
	private static function check_ids( $content ) {
		$processor = new \WP_HTML_Tag_Processor( $content );
		$seen      = array();
		while ( $processor->next_tag() ) {
			$id = $processor->get_attribute( 'id' );
			if ( is_string( $id ) && '' !== $id ) {
				if ( isset( $seen[ $id ] ) ) {
					throw new InvalidArgumentException( 'The resulting content contains duplicate HTML IDs.' );
				}
				$seen[ $id ] = true;
			}
		}
	}

	/**
	 * Place a form at an explicit sibling index or append it to the owner.
	 *
	 * @param string $content Owner content.
	 * @param string $markup Form.
	 * @param array<string,mixed> $input Location input.
	 * @return array<mixed> Content and actual path.
	 */
	private static function place( $content, $markup, $input ) {
		$map         = Form_Codec::locations( $content );
		$parent_path = $input['parent_path'] ?? array();
		$index       = $input['index'] ?? PHP_INT_MAX;
		if ( $parent_path ) {
			$key = implode( '.', $parent_path );
			if ( ! isset( $map[ $key ] ) || ! in_array( $map[ $key ]['name'], array( 'core/group', 'core/column' ), true ) ) {
				throw new InvalidArgumentException( 'Forms can be placed at the content root or inside a Group/Column.' );
			}
			foreach ( $map as $node ) {
				if ( 'formblox/form' === $node['name'] && array_slice( $parent_path, 0, count( $node['path'] ) ) === $node['path'] ) {
					throw new InvalidArgumentException( 'Forms cannot be nested.' );
				}
			}
			$parent = $map[ $key ];
			$index  = min( $index, count( $parent['children'] ) );
			if ( isset( $parent['children'][ $index ] ) ) {
				$offset = $map[ $parent['children'][ $index ] ]['start'];
			} else {
				$tail = substr( $content, $parent['open'], $parent['close'] - $parent['open'] );
				if ( ! preg_match( '/<\/(?:div|section|article|aside|header|footer|main)>\s*$/', $tail, $match, PREG_OFFSET_CAPTURE ) ) {
					throw new InvalidArgumentException( 'Unsupported container wrapper.' );
				}
				$offset = $parent['open'] + $match[0][1];
			}
		} else {
			$roots  = array_values(
				array_filter(
					$map,
					static function ( $node ) {
						return 1 === count( $node['path'] );
					}
				)
			);
			$index  = min( $index, count( $roots ) );
			$offset = isset( $roots[ $index ] ) ? $roots[ $index ]['start'] : strlen( $content );
		}
		return array( substr_replace( $content, $markup, $offset, 0 ), array_merge( $parent_path, array( $index ) ) );
	}
}
