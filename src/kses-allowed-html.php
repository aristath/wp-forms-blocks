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
 *
 * @return array<string, mixed> The allowed tags.
 */
function gutenberg_kses_allowed_html( $allowedtags ) {
	$allowedtags['input'] = array(
		'type'          => array(),
		'name'          => array(),
		'value'         => array(),
		'checked'       => array(),
		'required'      => array(),
		'aria-required' => array(),
		'class'         => array(),
	);

	$allowedtags['label'] = array(
		'for'   => array(),
		'class' => array(),
	);

	$allowedtags['textarea'] = array(
		'name'          => array(),
		'required'      => array(),
		'aria-required' => array(),
		'class'         => array(),
	);
	return $allowedtags;
}
add_filter( 'wp_kses_allowed_html', __NAMESPACE__ . '\\gutenberg_kses_allowed_html' );
