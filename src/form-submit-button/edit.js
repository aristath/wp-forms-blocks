import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { getBlockType } from '@wordpress/blocks';

const Edit = () => {
	const blockProps = useBlockProps();
	const innerBlocksProps = useInnerBlocksProps( blockProps, {
		template: getBlockType( 'formblox/form-submit-button' )?.template,
		templateLock: 'all',
	} );
	return (
		<div
			className="wp-block-formblox-form-submit-wrapper"
			{ ...innerBlocksProps }
		/>
	);
};
export default Edit;
