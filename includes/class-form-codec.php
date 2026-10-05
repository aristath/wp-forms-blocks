<?php
/**
 * Gutenberg-compatible form definitions and surgical saved-content edits.
 *
 * @package WPFormsBlocks
 */

namespace WPFormsBlocks;

use InvalidArgumentException;

defined( 'ABSPATH' ) || exit;

/**
 * Operates on saved markup, never on frontend render output.
 */
final class Form_Codec {
	const FIELD_TYPES = array( 'text', 'textarea', 'checkbox', 'email', 'url', 'tel', 'number', 'hidden' );
	const BLOCKS      = array( 'formblox/form', 'formblox/form-input', 'formblox/form-submit-button', 'formblox/form-submission-notification', 'core/paragraph', 'core/heading', 'core/group', 'core/columns', 'core/column', 'core/buttons', 'core/button' );

	/**
	 * Map named block paths to exact source ranges. Freeform HTML is not indexed.
	 *
	 * @param string $content Saved content.
	 * @return array<int|string, array<string, mixed>> Source map.
	 */
	public static function locations( $content ) {
		if ( strlen( $content ) > 2000000 ) {
			throw new InvalidArgumentException( 'Content exceeds the 2 MB editing limit.' );
		}
		$parser           = new \WP_Block_Parser();
		$parser->document = $content;
		$parser->offset   = 0;
		$stack            = array();
		$map              = array();
		$root_index       = 0;
		while ( true ) {
			list( $kind, $name, $attrs, $offset, $length ) = $parser->next_token();
			if ( 'no-more-tokens' === $kind ) {
				break;
			}
			$parser->offset = $offset + $length;
			if ( 'block-closer' === $kind ) {
				$key = array_pop( $stack );
				if ( null === $key || $map[ $key ]['name'] !== $name ) {
					throw new InvalidArgumentException( 'Malformed block delimiters.' );
				}
				$map[ $key ]['close'] = $offset;
				$map[ $key ]['end']   = $offset + $length;
				continue;
			}
			$parent = end( $stack );
			if ( false === $parent ) {
				$path = array( $root_index++ );
			} else {
				$path = array_merge( $map[ $parent ]['path'], array( count( $map[ $parent ]['children'] ) ) );
			}
			$key         = implode( '.', $path );
			$map[ $key ] = array(
				'name'       => $name,
				'attributes' => $attrs ? $attrs : array(),
				'path'       => $path,
				'start'      => $offset,
				'open'       => $offset + $length,
				'close'      => $offset + $length,
				'end'        => $offset + $length,
				'children'   => array(),
			);
			if ( false !== $parent ) {
				$map[ $parent ]['children'][] = $key;
			}
			if ( 'block-opener' === $kind ) {
				$stack[] = $key;
			}
			if ( count( $map ) > 10000 || count( $stack ) > 40 ) {
				throw new InvalidArgumentException( 'Block structure exceeds editing limits.' );
			}
		}
		if ( $stack ) {
			throw new InvalidArgumentException( 'Unclosed block delimiters.' );
		}
		return $map;
	}

	/**
	 * Read a block tree including HTML-sourced attributes and relative paths.
	 *
	 * @param string $markup A single saved block.
	 * @return array<string, mixed> Structured definition.
	 */
	public static function read( $markup ) {
		$map = self::locations( $markup );
		if ( ! isset( $map['0'] ) ) {
			throw new InvalidArgumentException( 'No block found.' );
		}
		return self::read_node( $markup, $map, '0', 1 );
	}

	/**
	 * Read one mapped node.
	 *
	 * @param string               $content Source.
	 * @param array<int|string,array<string,mixed>> $map Source map.
	 * @param string              $key Node key.
	 * @param int                 $prefix Relative path prefix length.
	 * @return array<string,mixed> Definition.
	 */
	private static function read_node( $content, $map, $key, $prefix ) {
		$node  = $map[ $key ];
		$html  = substr( $content, $node['open'], $node['close'] - $node['open'] );
		$attrs = $node['attributes'];
		if ( 'formblox/form-input' === $node['name'] ) {
			$p = new \WP_HTML_Tag_Processor( $html );
			while ( $p->next_tag() ) {
				if ( in_array( $p->get_tag(), array( 'INPUT', 'TEXTAREA' ), true ) ) {
					$attrs['name']        = (string) $p->get_attribute( 'name' );
					$attrs['required']    = null !== $p->get_attribute( 'required' );
					$attrs['placeholder'] = (string) $p->get_attribute( 'placeholder' );
					$attrs['value']       = (string) $p->get_attribute( 'value' );
					break;
				}
			}
			$label = self::label_range( $html );
			if ( null !== $label ) {
				$attrs['label'] = substr( $html, $label['start'], $label['length'] );
			}
		} elseif ( in_array( $node['name'], array( 'core/paragraph', 'core/heading', 'core/button' ), true ) ) {
			$tag = 'core/button' === $node['name'] ? 'button|a' : 'p|h[1-6]';
			if ( preg_match( '/<(' . $tag . ')\b[^>]*>(.*?)<\/\1>/s', $html, $match ) ) {
				$attrs[ 'core/button' === $node['name'] ? 'text' : 'content' ] = $match[2];
			}
		}
		$children = array();
		foreach ( $node['children'] as $child ) {
			$children[] = self::read_node( $content, $map, $child, $prefix );
		}
		return array(
			'name'        => $node['name'],
			'attributes'  => $attrs,
			'innerBlocks' => $children,
			'path'        => array_slice( $node['path'], $prefix ),
			'editable'    => in_array( $node['name'], self::BLOCKS, true ),
		);
	}

	/**
	 * Locate a field label's original contents, respecting quoted tag attributes.
	 *
	 * @param string $html Saved field HTML.
	 * @return array{start:int,length:int}|null Label range, or null for hidden fields.
	 */
	private static function label_range( $html ) {
		preg_match_all( '/<\/?span\b(?:[^>"\']|"[^"]*"|\'[^\']*\')*>/i', $html, $tags, PREG_OFFSET_CAPTURE );
		$start = null;
		$level = 0;
		foreach ( $tags[0] as $tag ) {
			$closer = str_starts_with( $tag[0], '</' );
			if ( null === $start ) {
				$processor = new \WP_HTML_Tag_Processor( $tag[0] );
				if ( ! $closer && $processor->next_tag() && $processor->has_class( 'wp-block-formblox-form-input__label-content' ) ) {
					$start = $tag[1] + strlen( $tag[0] );
					$level = 1;
				}
				continue;
			}
			$level += $closer ? -1 : 1;
			if ( 0 === $level ) {
				return array(
					'start'  => $start,
					'length' => $tag[1] - $start,
				);
			}
		}
		return null;
	}

	/**
	 * Create a contact, comment, privacy, or custom form.
	 *
	 * @param array<string,mixed> $definition Definition.
	 * @return string Saved Gutenberg markup.
	 */
	public static function create( $definition ) {
		self::keys( $definition, array( 'template', 'attributes', 'blocks' ) );
		$template = $definition['template'] ?? 'contact';
		if ( ! in_array( $template, array( 'contact', 'comment', 'privacy', 'custom' ), true ) ) {
			throw new InvalidArgumentException( 'Unknown form template.' );
		}
		$attributes = 'contact' === $template ? array() : array(
			'submissionMethod' => 'custom',
			'method'           => 'post',
		);
		if ( 'comment' === $template ) {
			$attributes['action'] = '{SITE_URL}/wp-comments-post.php';
		}
		$attributes = array_merge( $attributes, $definition['attributes'] ?? array() );
		$blocks     = $definition['blocks'] ?? self::template( $template );
		$markup     = self::build(
			array(
				'name'        => 'formblox/form',
				'attributes'  => $attributes,
				'innerBlocks' => $blocks,
			)
		);
		self::validate( $markup );
		return $markup;
	}

	/**
	 * Generate the native template hierarchy.
	 *
	 * @param string $template Template name.
	 * @return array<int,array<string,mixed>> Blocks.
	 */
	private static function template( $template ) {
		$blocks = array();
		if ( 'comment' !== $template ) {
			foreach ( array(
				'success' => __( 'Your form has been submitted successfully', 'wp-forms-blocks' ),
				'error'   => __( 'There was an error submitting your form.', 'wp-forms-blocks' ),
			) as $type => $message ) {
				$blocks[] = array(
					'name'        => 'formblox/form-submission-notification',
					'attributes'  => array( 'type' => $type ),
					'innerBlocks' => array(
						array(
							'name'       => 'core/paragraph',
							'attributes' => array( 'content' => $message ),
						),
					),
				);
			}
		}
		$fields = 'privacy' === $template ? array( array( 'email', 'email', __( 'Email', 'wp-forms-blocks' ), true ), array( 'checkbox', 'export_personal_data', __( 'Request data export', 'wp-forms-blocks' ), false ), array( 'checkbox', 'remove_personal_data', __( 'Request data deletion', 'wp-forms-blocks' ), false ), array( 'hidden', 'wp-action', '', false, 'wp_privacy_send_request' ), array( 'hidden', 'wp-privacy-request', '', false, '1' ) ) : array( array( 'text', 'author', __( 'Name', 'wp-forms-blocks' ), true ), array( 'email', 'email', __( 'Email', 'wp-forms-blocks' ), true ), array( 'textarea', 'comment', __( 'Comment', 'wp-forms-blocks' ), true ) );
		foreach ( $fields as $field ) {
			$attrs = array(
				'type'     => $field[0],
				'name'     => $field[1],
				'label'    => $field[2],
				'required' => $field[3],
			);
			if ( isset( $field[4] ) ) {
				$attrs['value'] = $field[4];
			}
			if ( 'comment' === $template && 'textarea' !== $field[0] ) {
				$attrs['visibilityPermissions'] = 'logged-out';
			}
			if ( 'checkbox' === $field[0] ) {
				$attrs['inlineLabel'] = true;
			}
			$blocks[] = array(
				'name'       => 'formblox/form-input',
				'attributes' => $attrs,
			);
		}
		$blocks[] = array( 'name' => 'formblox/form-submit-button' );
		return $blocks;
	}

	/**
	 * Enforce known keys, including for direct PHP calls.
	 *
	 * @param array<mixed> $input Input.
	 * @param array<string> $allowed Allowed keys.
	 * @return void
	 */
	private static function keys( $input, $allowed ) {
		if ( array_diff( array_keys( $input ), $allowed ) ) {
			throw new InvalidArgumentException( 'Unsupported definition or attribute property.' );
		}
	}

	/**
	 * Sanitize inline content and reject injected block delimiters.
	 *
	 * @param string $text Rich text.
	 * @return string Sanitized rich text.
	 */
	private static function rich_text( $text ) {
		if ( false !== strpos( $text, '<!--' ) || strlen( $text ) > 20000 ) {
			throw new InvalidArgumentException( 'Rich text contains a block delimiter or exceeds limits.' );
		}
		return wp_kses(
			$text,
			array(
				'strong' => array(),
				'em'     => array(),
				'b'      => array(),
				'i'      => array(),
				'br'     => array(),
				's'      => array(),
				'u'      => array(),
				'sup'    => array(),
				'sub'    => array(),
				'code'   => array(),
				'a'      => array(
					'href'   => true,
					'rel'    => true,
					'target' => true,
				),
				'mark'   => array(
					'style' => true,
					'class' => true,
				),
				'span'   => array(
					'style' => true,
					'class' => true,
				),
			)
		);
	}

	/**
	 * Serialize a constrained block definition using the current save contracts.
	 *
	 * @param array<string,mixed> $node Definition.
	 * @param int $depth Nesting depth.
	 * @return string Saved markup.
	 */
	public static function build( $node, $depth = 0 ) {
		self::keys( $node, array( 'name', 'attributes', 'innerBlocks' ) );
		$name = $node['name'] ?? '';
		if ( ! in_array( $name, self::BLOCKS, true ) || $depth > 12 ) {
			throw new InvalidArgumentException( 'Unsupported block or excessive nesting.' );
		}
		$a = $node['attributes'] ?? array();
		self::attributes( $name, $a );
		$children = $node['innerBlocks'] ?? array();
		if ( 'formblox/form-submit-button' === $name && ! $children ) {
			$children = array(
				array(
					'name'        => 'core/buttons',
					'innerBlocks' => array(
						array(
							'name'       => 'core/button',
							'attributes' => array(
								'tagName' => 'button',
								'type'    => 'submit',
								'text'    => __( 'Submit', 'wp-forms-blocks' ),
							),
						),
					),
				),
			);
		}
		$inner = '';
		if ( count( $children ) > 200 ) {
			throw new InvalidArgumentException( 'Too many child blocks.' );
		}
		foreach ( $children as $child ) {
			self::child_allowed( $name, $child['name'] ?? '' );
			$inner .= self::build( $child, $depth + 1 );
			if ( strlen( $inner ) > 2000000 ) {
				throw new InvalidArgumentException( 'Form exceeds the 2 MB editing limit.' );
			}
		}
		$wrapper = self::wrapper( $name, $a );
		$tag     = 'div';
		switch ( $name ) {
			case 'core/group':
				$tag = $a['tagName'] ?? 'div';
				break;
			case 'formblox/form':
				$tag      = 'form';
				$wrapper .= 'email' === ( $a['submissionMethod'] ?? 'email' ) ? ' enctype="text/plain"' : '';
				break;
			case 'formblox/form-input':
				$type      = $a['type'] ?? 'text';
				$a['name'] = $a['name'] ?? trim( preg_replace( '/[^\p{L}\p{N}]+/u', '-', strtolower( remove_accents( wp_strip_all_tags( $a['label'] ?? __( 'Label', 'wp-forms-blocks' ) ) ) ) ), '-' );
				if ( 'hidden' === $type ) {
					$inner = '<input type="hidden" name="' . esc_attr( $a['name'] ) . '" value="' . esc_attr( $a['value'] ?? '' ) . '"/>';
				} else {
					$input_tag = 'textarea' === $type ? 'textarea' : 'input';
					$control   = '<' . $input_tag . self::wrapper( $name, $a, true );
					$control  .= 'textarea' !== $type ? ' type="' . esc_attr( $type ) . '"' : '';
					$control  .= ' name="' . esc_attr( $a['name'] ) . '"' . ( ! empty( $a['required'] ) ? ' required' : '' ) . ' aria-required="' . ( ! empty( $a['required'] ) ? 'true' : 'false' ) . '"';
					$control  .= ! empty( $a['placeholder'] ) ? ' placeholder="' . esc_attr( $a['placeholder'] ) . '"' : '';
					$control  .= 'textarea' === $type ? '></textarea>' : '/>';
					$label     = '<span class="wp-block-formblox-form-input__label-content">' . self::rich_text( $a['label'] ?? __( 'Label', 'wp-forms-blocks' ) ) . '</span>';
					$inner     = '<div' . $wrapper . '><label class="wp-block-formblox-form-input__label' . ( ! empty( $a['inlineLabel'] ) ? ' is-label-inline' : '' ) . '">' . ( 'checkbox' === $type ? $control . $label : $label . $control ) . '</label></div>';
				}
				foreach ( array( 'label', 'required', 'placeholder', 'value' ) as $sourced ) {
					unset( $a[ $sourced ] );
				}
				return get_comment_delimited_block_content( $name, $a, $inner );
			case 'formblox/form-submission-notification':
				$wrapper = str_replace( 'wp-block-formblox-form-submission-notification', 'wp-block-formblox-form-submission-notification formblox-form-notification-type-' . ( $a['type'] ?? 'success' ), $wrapper );
				break;
			case 'core/paragraph':
			case 'core/heading':
				$tag   = 'core/paragraph' === $name ? 'p' : 'h' . ( $a['level'] ?? 2 );
				$inner = self::rich_text( $a['content'] ?? '' );
				unset( $a['content'] );
				break;
			case 'core/button':
				$inner        = '<button type="submit"' . self::wrapper( $name, $a, true ) . '>' . self::rich_text( $a['text'] ?? __( 'Submit', 'wp-forms-blocks' ) ) . '</button>';
				$a['tagName'] = 'button';
				$a['type']    = 'submit';
				unset( $a['text'] );
				break;
		}
		return get_comment_delimited_block_content( $name, $a, '<' . $tag . $wrapper . '>' . $inner . '</' . $tag . '>' );
	}

	/**
	 * List attributes supported for new definitions of each block.
	 *
	 * @param string $name Block name.
	 * @return array<string> Attribute names.
	 */
	private static function attribute_names( $name ) {
		$specific = array(
			'formblox/form'                         => array( 'submissionMethod', 'method', 'action' ),
			'formblox/form-input'                   => array( 'type', 'name', 'label', 'inlineLabel', 'required', 'placeholder', 'value', 'visibilityPermissions' ),
			'formblox/form-submission-notification' => array( 'type' ),
			'core/paragraph'                        => array( 'content' ),
			'core/heading'                          => array( 'content', 'level' ),
			'core/group'                            => array( 'layout', 'tagName', 'align' ),
			'core/columns'                          => array( 'isStackedOnMobile', 'verticalAlignment', 'align' ),
			'core/column'                           => array( 'width', 'verticalAlignment' ),
			'core/button'                           => array( 'text', 'tagName', 'type' ),
		);
		$common   = array( 'anchor', 'className', 'style', 'backgroundColor', 'textColor', 'gradient', 'fontSize', 'fontFamily' );
		if ( 'formblox/form-input' === $name ) {
			$common = array( 'anchor', 'className', 'style' );
		} elseif ( in_array( $name, array( 'formblox/form-submit-button', 'formblox/form-submission-notification' ), true ) ) {
			$common = array( 'className' );
		} elseif ( 'core/buttons' === $name ) {
			$common = array( 'anchor', 'className', 'style', 'layout', 'fontSize', 'fontFamily' );
		}
		return array_merge( $specific[ $name ] ?? array(), $common );
	}

	/**
	 * Validate new attributes against the selected block's save contract.
	 *
	 * @param string $name Block name.
	 * @param array<string,mixed> $a Proposed attributes.
	 * @return void
	 */
	public static function attributes( $name, $a ) {
		self::keys( $a, self::attribute_names( $name ) );
		foreach ( $a as $key => $value ) {
			if ( in_array( $key, array( 'inlineLabel', 'required', 'isStackedOnMobile' ), true ) ) {
				if ( ! is_bool( $value ) ) {
					throw new InvalidArgumentException( 'Expected a boolean attribute.' );
				}
			} elseif ( 'level' === $key ) {
				if ( ! is_int( $value ) || $value < 1 || $value > 6 ) {
					throw new InvalidArgumentException( 'Heading level must be between 1 and 6.' );
				}
			} elseif ( in_array( $key, array( 'style', 'layout' ), true ) ) {
				if ( ! is_array( $value ) ) {
					throw new InvalidArgumentException( 'Expected an object attribute.' );
				}
			} elseif ( ! is_string( $value ) || strlen( $value ) > 20000 ) {
				throw new InvalidArgumentException( 'Expected a bounded string attribute.' );
			}
		}
		if ( 'formblox/form-input' === $name && ! in_array( $a['type'] ?? 'text', self::FIELD_TYPES, true ) ) {
			throw new InvalidArgumentException( 'Unsupported input type.' );
		}
		if ( isset( $a['visibilityPermissions'] ) && ! in_array( $a['visibilityPermissions'], array( 'all', 'logged-in', 'logged-out' ), true ) ) {
			throw new InvalidArgumentException( 'Unsupported field visibility.' );
		}
		if ( 'formblox/form-submission-notification' === $name && ! in_array( $a['type'] ?? 'success', array( 'success', 'error' ), true ) ) {
			throw new InvalidArgumentException( 'Unknown notification type.' );
		}
		if ( 'formblox/form' === $name ) {
			if ( ! in_array( $a['submissionMethod'] ?? 'email', array( 'email', 'custom' ), true ) || ! in_array( $a['method'] ?? 'post', array( 'get', 'post' ), true ) ) {
				throw new InvalidArgumentException( 'Unsupported submission method.' );
			}
			if ( ! empty( $a['action'] ) ) {
				$url = str_replace( array( '{SITE_URL}', '{ADMIN_URL}' ), 'https://example.com', $a['action'] );
				if ( ! preg_match( '#^https?://#i', $url ) || esc_url_raw( $url, array( 'http', 'https' ) ) !== $url ) {
					throw new InvalidArgumentException( 'Form action must be an HTTP(S) URL or use a supported URL placeholder.' );
				}
			}
		}
		if ( 'core/group' === $name && ! in_array( $a['tagName'] ?? 'div', array( 'div', 'section', 'article', 'aside', 'header', 'footer', 'main' ), true ) ) {
			throw new InvalidArgumentException( 'Unsupported Group wrapper tag.' );
		}
		if ( isset( $a['align'] ) && ! in_array( $a['align'], array( '', 'wide', 'full' ), true ) ) {
			throw new InvalidArgumentException( 'Unsupported block alignment.' );
		}
		if ( isset( $a['verticalAlignment'] ) && ! in_array( $a['verticalAlignment'], array( 'top', 'center', 'bottom' ), true ) ) {
			throw new InvalidArgumentException( 'Unsupported vertical alignment.' );
		}
		if ( isset( $a['width'] ) ) {
			self::css_value( $a['width'] );
		}
		if ( isset( $a['layout'] ) ) {
			self::keys( $a['layout'], array( 'type', 'orientation', 'justifyContent', 'flexWrap', 'contentSize', 'wideSize' ) );
			if ( ! in_array( $a['layout']['type'] ?? 'default', array( 'default', 'constrained', 'flex' ), true ) ) {
				throw new InvalidArgumentException( 'Unsupported layout type.' );
			}
			foreach ( $a['layout'] as $value ) {
				self::css_value( $value );
			}
		}
		if ( isset( $a['style'] ) ) {
			$allowed = 'formblox/form-input' === $name ? array( 'spacing', 'border' ) : array( 'color', 'spacing', 'typography' );
			self::keys( $a['style'], $allowed );
			$properties = array(
				'color'      => array( 'text', 'background', 'gradient' ),
				'spacing'    => array( 'margin', 'padding', 'blockGap' ),
				'typography' => array( 'fontSize', 'fontFamily', 'fontStyle', 'fontWeight', 'lineHeight', 'textDecoration', 'letterSpacing', 'textTransform' ),
				'border'     => array( 'radius' ),
			);
			foreach ( $a['style'] as $group => $values ) {
				if ( 'formblox/form-input' === $name && 'spacing' === $group ) {
					self::keys( $values, array( 'margin' ) );
				}
				self::keys( $values, $properties[ $group ] );
				foreach ( $values as $property => $value ) {
					if ( is_array( $value ) && in_array( $property, array( 'margin', 'padding', 'radius' ), true ) ) {
						self::keys( $value, 'radius' === $property ? array( 'topLeft', 'topRight', 'bottomLeft', 'bottomRight' ) : array( 'top', 'right', 'bottom', 'left' ) );
						foreach ( $value as $amount ) {
							self::css_value( $amount );
						}
					} else {
						self::css_value( $value );
					}
				}
			}
		}
		if ( 'core/button' === $name && ( 'button' !== ( $a['tagName'] ?? 'button' ) || 'submit' !== ( $a['type'] ?? 'submit' ) ) ) {
			throw new InvalidArgumentException( 'Submit buttons must use button/type=submit.' );
		}
	}

	/**
	 * Validate child placement.
	 *
	 * @param string $parent_name Parent block.
	 * @param string $child Child block.
	 * @return void
	 */
	public static function child_allowed( $parent_name, $child ) {
		$allowed = array(
			'formblox/form'                         => array( 'core/paragraph', 'core/heading', 'core/group', 'core/columns', 'formblox/form-input', 'formblox/form-submit-button', 'formblox/form-submission-notification' ),
			'core/group'                            => array( 'core/paragraph', 'core/heading', 'core/group', 'core/columns', 'formblox/form-input', 'formblox/form-submit-button', 'formblox/form-submission-notification' ),
			'core/columns'                          => array( 'core/column' ),
			'core/column'                           => array( 'core/paragraph', 'core/heading', 'core/group', 'core/columns', 'formblox/form-input', 'formblox/form-submit-button', 'formblox/form-submission-notification' ),
			'formblox/form-submit-button'           => array( 'core/buttons', 'core/button' ),
			'core/buttons'                          => array( 'core/button' ),
			'formblox/form-submission-notification' => array( 'core/paragraph', 'core/heading', 'core/group' ),
		);
		if ( ! in_array( $child, $allowed[ $parent_name ] ?? array(), true ) ) {
			throw new InvalidArgumentException( 'Invalid child block placement.' );
		}
	}

	/**
	 * Generate the save wrapper and the input's skipped border styles.
	 *
	 * @param string $name Block name.
	 * @param array<string,mixed> $a Attributes.
	 * @param bool $input Whether this is the inner field control.
	 * @return string HTML attributes.
	 */
	private static function wrapper( $name, $a, $input = false ) {
		$class = 'core/paragraph' === $name ? '' : 'wp-block-' . str_replace( '/', '-', str_replace( 'core/', '', $name ) );
		if ( $input ) {
			$class = 'core/button' === $name ? 'wp-block-button__link' : 'wp-block-formblox-form-input__input';
		}
		if ( ! $input && ! empty( $a['className'] ) ) {
			$class .= ' ' . $a['className'];
		}
		$style = $a['style'] ?? array();
		if ( 'core/button' === $name && ! $input ) {
			$style = array();
			$a     = array_intersect_key( $a, array_flip( array( 'className', 'anchor' ) ) );
		}
		$css           = array();
		$color_enabled = $input || in_array( $name, array( 'formblox/form', 'core/group', 'core/columns', 'core/column', 'core/paragraph', 'core/heading' ), true );
		if ( $color_enabled ) {
			foreach ( array(
				'textColor'       => 'color',
				'gradient'        => 'gradient-background',
				'backgroundColor' => 'background-color',
			) as $attribute => $suffix ) {
				if ( ! empty( $a[ $attribute ] ) && ( 'backgroundColor' !== $attribute || ( empty( $style['color']['gradient'] ) && ( ! $input || empty( $a['gradient'] ) ) ) ) ) {
					$class .= ' has-' . sanitize_html_class( $a[ $attribute ] ) . '-' . $suffix;
				}
			}
			if ( ! empty( $a['textColor'] ) || ! empty( $style['color']['text'] ) ) {
				$class .= ' has-text-color';
			}
			if ( ! empty( $a['backgroundColor'] ) || ! empty( $a['gradient'] ) || ! empty( $style['color']['background'] ) || ! empty( $style['color']['gradient'] ) ) {
				$class .= ' has-background';
			}
			foreach ( array(
				'text'       => 'color',
				'background' => 'background-color',
				'gradient'   => 'background',
			) as $property => $css_name ) {
				if ( isset( $style['color'][ $property ] ) ) {
					$css[ $css_name ] = self::css_value( $style['color'][ $property ] );
				}
			}
		}
		if ( $input && isset( $style['border']['radius'] ) ) {
			if ( is_array( $style['border']['radius'] ) ) {
				foreach ( $style['border']['radius'] as $corner => $radius ) {
					$css[ 'border-' . strtolower( preg_replace( '/[A-Z]/', '-$0', $corner ) ) . '-radius' ] = self::css_value( $radius );
				}
			} else {
				$css['border-radius'] = self::css_value( $style['border']['radius'] );
			}
		}
		if ( ! $input || 'core/button' === $name ) {
			foreach ( array( 'margin', 'padding' ) as $property ) {
				if ( ! isset( $style['spacing'][ $property ] ) ) {
					continue;
				}
				$value = $style['spacing'][ $property ];
				if ( is_array( $value ) ) {
					foreach ( $value as $side => $amount ) {
						if ( ! in_array( $side, array( 'top', 'right', 'bottom', 'left' ), true ) ) {
							throw new InvalidArgumentException( 'Unknown spacing side.' );
						}
						$css[ $property . '-' . $side ] = self::css_value( $amount );
					}
				} else {
					$css[ $property ] = self::css_value( $value );
				}
			}
			foreach ( $style['typography'] ?? array() as $property => $value ) {
				$css_name         = strtolower( preg_replace( '/[A-Z]/', '-$0', $property ) );
				$css[ $css_name ] = self::css_value( $value );
			}
			if ( ! empty( $a['fontSize'] ) ) {
				$class .= ' has-' . sanitize_html_class( $a['fontSize'] ) . '-font-size';
			}
		}
		if ( ( 'core/button' === $name && $input ) || 'core/buttons' === $name ) {
			if ( ! empty( $a['fontSize'] ) || ! empty( $style['typography']['fontSize'] ) ) {
				$class .= ' has-custom-font-size';
			}
			if ( 'core/button' === $name ) {
				$class .= ' wp-element-button';
			}
		}
		if ( ! $input && 'core/columns' === $name && isset( $a['isStackedOnMobile'] ) && ! $a['isStackedOnMobile'] ) {
			$class .= ' is-not-stacked-on-mobile';
		}
		if ( ! $input && isset( $a['verticalAlignment'] ) ) {
			$class .= ( 'core/columns' === $name ? ' are-vertically-aligned-' : ' is-vertically-aligned-' ) . $a['verticalAlignment'];
		}
		if ( ! $input && ! empty( $a['align'] ) ) {
			$class .= ' align' . $a['align'];
		}
		if ( ! $input && 'core/column' === $name && ! empty( $a['width'] ) ) {
			$css['flex-basis'] = self::css_value( $a['width'] );
		}
		if ( ( ! $input || 'core/button' === $name ) && ! empty( $a['fontFamily'] ) ) {
			$class .= ' has-' . sanitize_html_class( $a['fontFamily'] ) . '-font-family';
		}
		$result = '';
		if ( ! $input && ! empty( $a['anchor'] ) ) {
			$result .= ' id="' . esc_attr( $a['anchor'] ) . '"';
		}
		if ( trim( $class ) ) {
			$result .= ' class="' . esc_attr( trim( $class ) ) . '"';
		}
		if ( $css ) {
			ksort( $css );
			$declarations = array();
			foreach ( $css as $property => $value ) {
				$declarations[] = $property . ':' . $value;
			}
			$result .= ' style="' . esc_attr( implode( ';', $declarations ) ) . '"';
		}
		return $result;
	}

	/**
	 * Validate a style value and expand native preset references.
	 *
	 * @param mixed $value Style value.
	 * @return string CSS value.
	 */
	private static function css_value( $value ) {
		if ( ! is_string( $value ) || preg_match( '/[;{}<>"\x00-\x1f]|url\s*\(|expression\s*\(/i', $value ) ) {
			throw new InvalidArgumentException( 'Unsafe or unsupported style value.' );
		}
		return preg_replace( '/var:preset\|([a-z-]+)\|([a-z0-9-]+)/', 'var(--wp--preset--$1--$2)', $value );
	}

	/**
	 * Apply operations to one form while retaining all untouched source bytes.
	 *
	 * @param string $markup Single form markup.
	 * @param array<int,array<string,mixed>> $operations Operations.
	 * @return string Edited form.
	 */
	public static function edit( $markup, $operations ) {
		if ( ! $operations || count( $operations ) > 100 ) {
			throw new InvalidArgumentException( 'Provide between 1 and 100 operations.' );
		}
		foreach ( $operations as $op ) {
			$map  = self::locations( $markup );
			$kind = $op['operation'] ?? '';
			$keys = array(
				'update' => array( 'operation', 'path', 'attributes' ),
				'insert' => array( 'operation', 'parent_path', 'index', 'block' ),
				'remove' => array( 'operation', 'path' ),
				'move'   => array( 'operation', 'path', 'parent_path', 'index' ),
			);
			if ( ! isset( $keys[ $kind ] ) || ( in_array( $kind, array( 'update', 'remove', 'move' ), true ) && ! isset( $op['path'] ) ) ) {
				throw new InvalidArgumentException( 'Unknown operation or missing path.' );
			}
			self::keys( $op, $keys[ $kind ] );
			if ( 'insert' === $kind ) {
				$markup = self::insert( $markup, $op['parent_path'] ?? array(), $op['index'] ?? PHP_INT_MAX, self::build( $op['block'] ?? array() ) );
				continue;
			}
			$path = $op['path'] ?? array();
			$key  = implode( '.', array_merge( array( 0 ), $path ) );
			if ( ! isset( $map[ $key ] ) || ( ! $path && 'update' !== $kind ) ) {
				throw new InvalidArgumentException( 'Invalid operation path.' );
			}
			$node = $map[ $key ];
			$old  = substr( $markup, $node['start'], $node['end'] - $node['start'] );
			if ( 'update' === $kind ) {
				$replacement = self::patch( $old, $op['attributes'] ?? array() );
				$markup      = substr_replace( $markup, $replacement, $node['start'], $node['end'] - $node['start'] );
			} elseif ( in_array( $kind, array( 'remove', 'move' ), true ) ) {
				$markup = substr_replace( $markup, '', $node['start'], $node['end'] - $node['start'] );
				if ( 'move' === $kind ) {
					$parent = $op['parent_path'] ?? array();
					if ( array_slice( $parent, 0, count( $path ) ) === $path ) {
						throw new InvalidArgumentException( 'Cannot move a block inside itself.' );
					}
					$ancestor = array_slice( $path, 0, -1 );
					$level    = count( $ancestor );
					if ( array_slice( $parent, 0, $level ) === $ancestor && isset( $parent[ $level ] ) && $parent[ $level ] > end( $path ) ) {
						--$parent[ $level ];
					}
					$markup = self::insert( $markup, $parent, $op['index'] ?? PHP_INT_MAX, $old );
				}
			} else {
				throw new InvalidArgumentException( 'Unknown edit operation.' );
			}
		}
		self::validate( $markup );
		return $markup;
	}

	/**
	 * Insert saved block markup into a supported container.
	 *
	 * @param string $markup Content.
	 * @param array<int> $parent_path Named-block parent path, relative to the form.
	 * @param int $index Insertion index in the resulting sibling list.
	 * @param string $block Saved block.
	 * @return string Content.
	 */
	public static function insert( $markup, $parent_path, $index, $block ) {
		$map = self::locations( $markup );
		$key = implode( '.', array_merge( array( 0 ), $parent_path ) );
		if ( ! isset( $map[ $key ] ) || $index < 0 ) {
			throw new InvalidArgumentException( 'Invalid insertion location.' );
		}
		$parent = $map[ $key ];
		$child  = self::read( $block );
		self::child_allowed( $parent['name'], $child['name'] );
		$index = min( $index, count( $parent['children'] ) );
		if ( isset( $parent['children'][ $index ] ) ) {
			$offset = $map[ $parent['children'][ $index ] ]['start'];
		} else {
			$tail = substr( $markup, $parent['open'], $parent['close'] - $parent['open'] );
			if ( ! preg_match( '/<\/(?:div|form|section|article|aside|header|footer|main)>\s*$/', $tail, $match, PREG_OFFSET_CAPTURE ) ) {
				throw new InvalidArgumentException( 'Unsupported container markup.' );
			}
			$offset = $parent['open'] + $match[0][1];
		}
		return substr_replace( $markup, $block, $offset, 0 );
	}

	/**
	 * Patch known attributes while retaining children and unknown saved decoration.
	 *
	 * @param string $markup Block markup.
	 * @param array<string,mixed> $changes Changed attributes.
	 * @return string Updated block markup.
	 */
	private static function patch( $markup, $changes ) {
		$map = self::locations( $markup );
		if ( ! isset( $map[0] ) ) {
			throw new InvalidArgumentException( 'No block found.' );
		}
		$old  = $map['0'];
		$name = $old['name'];
		if ( ! in_array( $name, self::BLOCKS, true ) || ! $changes ) {
			throw new InvalidArgumentException( 'This block cannot be edited or no changes were provided.' );
		}
		self::attributes( $name, $changes );
		$definition = self::read( $markup );
		$attrs      = array_merge( $definition['attributes'], $changes );
		if ( 'formblox/form-input' === $name ) {
			$build_attrs = array_intersect_key( $attrs, array_flip( self::attribute_names( $name ) ) );
			$keep_label  = ! array_key_exists( 'label', $changes ) && isset( $definition['attributes']['label'] );
			if ( $keep_label ) {
				// Unchanged saved RichText can contain custom formats outside our creation vocabulary.
				$build_attrs['label'] = '';
			}
			$generated = self::build(
				array(
					'name'       => $name,
					'attributes' => $build_attrs,
				)
			);
			$label     = self::label_range( $generated );
			if ( $keep_label && null !== $label ) {
				$generated = substr_replace( $generated, $definition['attributes']['label'], $label['start'], $label['length'] );
			}
			// Preserve wrapper/control decoration on semantic edits to existing fields.
			if ( ! array_intersect( array_keys( $changes ), array( 'style', 'className', 'anchor', 'backgroundColor', 'textColor', 'gradient', 'fontSize', 'fontFamily', 'align', 'verticalAlignment', 'isStackedOnMobile', 'width' ) ) ) {
				$generated = self::retain_decoration( $markup, $generated );
			}
			$generated_map  = self::locations( $generated );
			$generated_node = $generated_map[0];
			$passthrough    = array_diff_key( $old['attributes'], array_flip( self::attribute_names( $name ) ) );
			$generated_html = substr( $generated, $generated_node['open'], $generated_node['close'] - $generated_node['open'] );
			return get_comment_delimited_block_content( $name, $generated_node['attributes'] + $passthrough, $generated_html );
		}
		$html = substr( $markup, $old['open'], $old['close'] - $old['open'] );
		if ( isset( $changes['content'] ) || isset( $changes['text'] ) ) {
			$property = 'core/button' === $name ? 'text' : 'content';
			$tag      = 'core/button' === $name ? 'button|a' : 'p|h[1-6]';
			$html     = preg_replace_callback(
				'/(<(' . $tag . ')\b[^>]*>).*?(<\/\2>)/s',
				static function ( $matches ) use ( $changes, $property ) {
					return $matches[1] . self::rich_text( $changes[ $property ] ) . $matches[3];
				},
				$html,
				1
			);
		}
		if ( isset( $changes['level'] ) ) {
			$html = preg_replace( '/(<\/?)(h[1-6])\b/', '${1}h' . $changes['level'], $html );
		}
		$p = new \WP_HTML_Tag_Processor( $html );
		$p->next_tag();
		if ( isset( $changes['anchor'] ) ) {
			if ( '' === $changes['anchor'] ) {
				$p->remove_attribute( 'id' );
			} else {
				$p->set_attribute( 'id', $changes['anchor'] );
			}
		}
		if ( 'formblox/form' === $name ) {
			if ( 'email' === ( $attrs['submissionMethod'] ?? 'email' ) ) {
				$p->set_attribute( 'enctype', 'text/plain' );
			} else {
				$p->remove_attribute( 'enctype' );
			}
		}
		if ( 'formblox/form-submission-notification' === $name && isset( $changes['type'] ) ) {
			$p->remove_class( 'formblox-form-notification-type-' . ( $old['attributes']['type'] ?? 'success' ) );
			$p->add_class( 'formblox-form-notification-type-' . $changes['type'] );
		}
		if ( array_intersect( array_keys( $changes ), array( 'style', 'className', 'backgroundColor', 'textColor', 'gradient', 'fontSize', 'fontFamily', 'align', 'verticalAlignment', 'isStackedOnMobile', 'width' ) ) ) {
			$before = new \WP_HTML_Tag_Processor( '<div' . self::wrapper( $name, $definition['attributes'] ) . '></div>' );
			$after  = new \WP_HTML_Tag_Processor( '<div' . self::wrapper( $name, $attrs ) . '></div>' );
			$before->next_tag();
			$after->next_tag();
			foreach ( explode( ' ', (string) $before->get_attribute( 'class' ) ) as $class ) {
				$p->remove_class( $class );
			}
			foreach ( explode( ' ', (string) $after->get_attribute( 'class' ) ) as $class ) {
				$p->add_class( $class );
			}
			if ( null === $after->get_attribute( 'style' ) ) {
				$p->remove_attribute( 'style' );
			} else {
				$p->set_attribute( 'style', $after->get_attribute( 'style' ) );
			}
		}
		$html = $p->get_updated_html();
		if ( 'core/button' === $name && array_intersect( array_keys( $changes ), array( 'style', 'backgroundColor', 'textColor', 'gradient', 'fontSize', 'fontFamily' ) ) ) {
			$button = new \WP_HTML_Tag_Processor( $html );
			$button->next_tag( array( 'tag_name' => 'BUTTON' ) );
			$generated = new \WP_HTML_Tag_Processor( '<button' . self::wrapper( $name, $attrs, true ) . '></button>' );
			$generated->next_tag();
			$button->set_attribute( 'class', $generated->get_attribute( 'class' ) );
			if ( null === $generated->get_attribute( 'style' ) ) {
				$button->remove_attribute( 'style' );
			} else {
				$button->set_attribute( 'style', $generated->get_attribute( 'style' ) );
			}
			$html = $button->get_updated_html();
		}
		if ( 'core/group' === $name && isset( $changes['tagName'] ) ) {
			$html = preg_replace( '/^(\s*)<(div|section|article|aside|header|footer|main)\b/', '${1}<' . $changes['tagName'], $html );
			$html = preg_replace( '/<\/(div|section|article|aside|header|footer|main)>(\s*)$/', '</' . $changes['tagName'] . '>${2}', $html );
		}
		foreach ( array( 'content', 'text' ) as $sourced ) {
			unset( $attrs[ $sourced ] );
		}
		self::attributes( $name, array_intersect_key( $attrs, $changes ) );
		return get_comment_delimited_block_content( $name, $attrs, $html );
	}

	/**
	 * Keep existing saved classes/styles when only changing a field's semantics.
	 *
	 * @param string $old Previous markup.
	 * @param string $replacement_markup New markup.
	 * @return string Decorated markup.
	 */
	private static function retain_decoration( $old, $replacement_markup ) {
		foreach ( array( 'DIV', 'INPUT', 'TEXTAREA' ) as $tag ) {
			$source = new \WP_HTML_Tag_Processor( $old );
			$target = new \WP_HTML_Tag_Processor( $replacement_markup );
			if ( ! $source->next_tag( array( 'tag_name' => $tag ) ) ) {
				continue;
			}
			$query = 'DIV' === $tag ? array( 'tag_name' => 'DIV' ) : array( 'class_name' => 'wp-block-formblox-form-input__input' );
			if ( $target->next_tag( $query ) ) {
				foreach ( array( 'class', 'style', 'id' ) as $attribute ) {
					$value = $source->get_attribute( $attribute );
					if ( null !== $value ) {
						$target->set_attribute( $attribute, $value );
					}
				}
				$replacement_markup = $target->get_updated_html();
			}
		}
		return $replacement_markup;
	}

	/**
	 * Check the complete saved form, including submission contracts.
	 *
	 * @param string $markup Saved form.
	 * @return array<string,mixed> Definition.
	 */
	public static function validate( $markup ) {
		$map = self::locations( $markup );
		if ( ! isset( $map['0'] ) || 'formblox/form' !== $map['0']['name'] ) {
			throw new InvalidArgumentException( 'Expected one form block.' );
		}
		$definition = self::read( $markup );
		self::attributes( 'formblox/form', array_intersect_key( $definition['attributes'], array_flip( array( 'submissionMethod', 'method', 'action' ) ) ) );
		$a            = $definition['attributes'];
		$method       = $a['submissionMethod'] ?? 'email';
		$comment      = str_ends_with( $a['action'] ?? '', '/wp-comments-post.php' );
		$php_handler  = 'email' === $method || $comment || empty( $a['action'] );
		$names        = array();
		$parsed_names = array();
		$submit       = false;
		foreach ( $map as $key => $node ) {
			if ( 'formblox/form' === $node['name'] && '0' !== (string) $key ) {
				throw new InvalidArgumentException( 'Forms cannot be nested.' );
			}
			$is_submit = 'core/button' === $node['name'] && 'submit' === ( $node['attributes']['type'] ?? '' ) && 'button' === ( $node['attributes']['tagName'] ?? '' );
			if ( $is_submit || in_array( $node['name'], array( 'formblox/form-input', 'formblox/form-submit-button' ), true ) ) {
				$depth = count( $node['path'] );
				for ( $length = 1; $length < $depth; ++$length ) {
					$ancestor = $map[ implode( '.', array_slice( $node['path'], 0, $length ) ) ];
					if ( 'formblox/form-submission-notification' === $ancestor['name'] ) {
						throw new InvalidArgumentException( 'Form controls cannot be placed inside submission notifications.' );
					}
				}
			}
			if ( 'formblox/form-input' === $node['name'] ) {
				$field = self::read_node( $markup, $map, $key, 1 )['attributes'];
				$name  = $field['name'] ?? '';
				if ( '' === $name || isset( $names[ $name ] ) || preg_match( '/[\s\[\]<>"\x00-\x1f]/', $name ) ) {
					throw new InvalidArgumentException( 'Field names must be nonempty, unique scalar names.' );
				}
				$names[ $name ] = $field;
				$parsed_name    = $php_handler ? str_replace( '.', '_', $name ) : $name;
				if ( isset( $parsed_names[ $parsed_name ] ) ) {
					throw new InvalidArgumentException( 'Field names collide after PHP request parsing.' );
				}
				$parsed_names[ $parsed_name ] = true;
			}
			$submit = $submit || $is_submit;
		}
		if ( ! $submit ) {
			throw new InvalidArgumentException( 'A form needs a submit button.' );
		}
		if ( 'email' === $method && array_intersect( array_keys( $parsed_names ), array( 'action', 'formAction', '_ajax_nonce', '_wp_http_referer' ) ) ) {
			throw new InvalidArgumentException( 'Email form field names conflict with submission controls.' );
		}
		$privacy = isset( $names['wp-action'] ) || isset( $names['wp-privacy-request'] );
		if ( $privacy && ( 'custom' !== $method || '' !== ( $a['action'] ?? '' ) || 'post' !== ( $a['method'] ?? 'post' ) || 'wp_privacy_send_request' !== ( $names['wp-action']['value'] ?? '' ) || '1' !== ( $names['wp-privacy-request']['value'] ?? '' ) || ! isset( $names['email'] ) || ( ! isset( $names['export_personal_data'] ) && ! isset( $names['remove_personal_data'] ) ) ) ) {
			throw new InvalidArgumentException( 'Privacy forms need their native markers, email, and at least one request checkbox.' );
		}
		if ( $privacy ) {
			foreach ( array(
				'wp-action'            => 'hidden',
				'wp-privacy-request'   => 'hidden',
				'email'                => 'email',
				'export_personal_data' => 'checkbox',
				'remove_personal_data' => 'checkbox',
			) as $field_name => $type ) {
				if ( isset( $names[ $field_name ] ) && ( ( $names[ $field_name ]['type'] ?? 'text' ) !== $type || 'all' !== ( $names[ $field_name ]['visibilityPermissions'] ?? 'all' ) ) ) {
					throw new InvalidArgumentException( 'Privacy fields must retain their native types and public visibility.' );
				}
			}
			if ( empty( $names['email']['required'] ) ) {
				throw new InvalidArgumentException( 'Privacy request email must be required.' );
			}
		}
		if ( $comment && ( 'custom' !== $method || 'post' !== ( $a['method'] ?? 'post' ) || ! isset( $names['comment'], $names['author'], $names['email'] ) ) ) {
			throw new InvalidArgumentException( 'Comment forms need author, email, and comment fields and POST.' );
		}
		if ( $comment && ( 'textarea' !== ( $names['comment']['type'] ?? 'text' ) || 'email' !== ( $names['email']['type'] ?? 'text' ) || 'text' !== ( $names['author']['type'] ?? 'text' ) || 'all' !== ( $names['comment']['visibilityPermissions'] ?? 'all' ) ) ) {
			throw new InvalidArgumentException( 'Comment forms must retain native field types and a publicly visible comment field.' );
		}
		if ( 'custom' === $method && ! $privacy && empty( $a['action'] ) ) {
			throw new InvalidArgumentException( 'Custom forms need a destination URL.' );
		}
		return $definition;
	}
}
