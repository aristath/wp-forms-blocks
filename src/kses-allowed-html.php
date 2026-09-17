<?php
/**
 * Modifies the wp_kses_allowed_html array.
 *
 * @package WPFormsBlocks
 */

namespace WPFormsBlocks;

defined( 'ABSPATH' ) || exit;

/**
 * Add the form elements to the allowed tags array.
 *
 * @param array<string, mixed> $allowedtags The allowed tags.
 * @param string               $context     The KSES context.
 *
 * @return array<string, mixed> The allowed tags.
 */
function gutenberg_kses_allowed_html( $allowedtags, $context ) {
	if ( 'post' !== $context ) {
		return $allowedtags;
	}

	$allowedtags['form'] = array_merge(
		isset( $allowedtags['form'] ) && is_array( $allowedtags['form'] ) ? $allowedtags['form'] : array(),
		array(
			'action'           => true,
			'accept'           => true,
			'accept-charset'   => true,
			'enctype'          => true,
			'method'           => true,
			'name'             => true,
			'target'           => true,
			'aria-controls'    => true,
			'aria-current'     => true,
			'aria-describedby' => true,
			'aria-details'     => true,
			'aria-expanded'    => true,
			'aria-hidden'      => true,
			'aria-label'       => true,
			'aria-labelledby'  => true,
			'aria-live'        => true,
			'class'            => true,
			'data-*'           => true,
			'dir'              => true,
			'hidden'           => true,
			'id'               => true,
			'lang'             => true,
			'role'             => true,
			'style'            => true,
			'tabindex'         => true,
			'title'            => true,
			'xml:lang'         => true,
		)
	);

	$allowedtags['input'] = array_merge(
		isset( $allowedtags['input'] ) && is_array( $allowedtags['input'] ) ? $allowedtags['input'] : array(),
		array(
			'aria-required' => true,
			'autocomplete'  => true,
			'checked'       => true,
			'class'         => true,
			'disabled'      => true,
			'id'            => true,
			'name'          => true,
			'placeholder'   => true,
			'required'      => true,
			'style'         => true,
			'tabindex'      => true,
			'type'          => true,
			'value'         => true,
		)
	);

	$allowedtags['label'] = array_merge(
		isset( $allowedtags['label'] ) && is_array( $allowedtags['label'] ) ? $allowedtags['label'] : array(),
		array(
			'class' => true,
			'for'   => true,
			'style' => true,
		)
	);

	$allowedtags['textarea'] = array_merge(
		isset( $allowedtags['textarea'] ) && is_array( $allowedtags['textarea'] ) ? $allowedtags['textarea'] : array(),
		array(
			'aria-required' => true,
			'class'         => true,
			'id'            => true,
			'name'          => true,
			'placeholder'   => true,
			'required'      => true,
			'style'         => true,
		)
	);
	return $allowedtags;
}
add_filter( 'wp_kses_allowed_html', __NAMESPACE__ . '\\gutenberg_kses_allowed_html', 10, 2 );
