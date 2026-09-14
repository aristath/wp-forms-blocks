import { getBlockType, registerBlockType } from '@wordpress/blocks';

/**
 * Register a block unless another provider already registered its historical name.
 *
 * @param {Object} block Block metadata and settings.
 * @return {WPBlockType|undefined} Registered block type.
 */
export default function initBlock( block ) {
	if ( ! block ) {
		return undefined;
	}

	const { metadata, settings, name } = block;
	return (
		getBlockType( name ) ||
		registerBlockType( { name, ...metadata }, settings )
	);
}
