import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { getBlockType } from '@wordpress/blocks';

const Edit = () => {
	const blockProps = useBlockProps();
	const innerBlocksProps = useInnerBlocksProps( blockProps, {
		template: getBlockType( 'core/form-submit-button' )?.template,
		templateLock: 'all',
	} );
	return (
		<div className="wp-block-form-submit-wrapper" { ...innerBlocksProps } />
	);
};
export default Edit;
