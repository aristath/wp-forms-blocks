import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';

export default function save() {
	const blockProps = useBlockProps.save();
	return (
		<div
			className="wp-block-formblox-form-submit-wrapper"
			{ ...blockProps }
		>
			<InnerBlocks.Content />
		</div>
	);
}
