import { __ } from '@wordpress/i18n';
import {
	InnerBlocks,
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import {
	SelectControl,
	TextControl,
	__experimentalToolsPanel as ToolsPanel,
	__experimentalToolsPanelItem as ToolsPanelItem,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { getBlockType } from '@wordpress/blocks';
import { useToolsPanelDropdownMenuProps } from '../utils/hooks';

const Edit = ( { attributes, setAttributes, clientId } ) => {
	const dropdownMenuProps = useToolsPanelDropdownMenuProps();

	const resetAllSettings = () => {
		setAttributes( {
			submissionMethod: 'email',
			action: undefined,
			method: 'post',
		} );
	};

	const { action, method, submissionMethod } = attributes;
	const blockProps = useBlockProps();

	const { hasInnerBlocks } = useSelect(
		( select ) => {
			const { getBlock } = select( blockEditorStore );
			const block = getBlock( clientId );
			return {
				hasInnerBlocks: !! ( block && block.innerBlocks.length ),
			};
		},
		[ clientId ]
	);

	const innerBlocksProps = useInnerBlocksProps( blockProps, {
		template: getBlockType( 'formblox/form' )?.template,
		renderAppender: hasInnerBlocks
			? undefined
			: InnerBlocks.ButtonBlockAppender,
	} );

	return (
		<>
			<InspectorControls>
				<ToolsPanel
					dropdownMenuProps={ dropdownMenuProps }
					label={ __( 'Settings', 'wp-forms-blocks' ) }
					resetAll={ resetAllSettings }
				>
					<ToolsPanelItem
						hasValue={ () => submissionMethod !== 'email' }
						label={ __( 'Submissions method', 'wp-forms-blocks' ) }
						onDeselect={ () =>
							setAttributes( {
								submissionMethod: 'email',
							} )
						}
						isShownByDefault
					>
						<SelectControl
							label={ __(
								'Submissions method',
								'wp-forms-blocks'
							) }
							options={ [
								// TODO: Allow plugins to add their own submission methods.
								{
									label: __(
										'Send email',
										'wp-forms-blocks'
									),
									value: 'email',
								},
								{
									label: __(
										'- Custom -',
										'wp-forms-blocks'
									),
									value: 'custom',
								},
							] }
							value={ submissionMethod }
							onChange={ ( value ) =>
								setAttributes( { submissionMethod: value } )
							}
							help={
								submissionMethod === 'custom'
									? __(
											'Select the method to use for form submissions. Additional options for the "custom" mode can be found in the "Advanced" section.',
											'wp-forms-blocks'
									  )
									: __(
											'Select the method to use for form submissions.',
											'wp-forms-blocks'
									  )
							}
						/>
					</ToolsPanelItem>
				</ToolsPanel>
			</InspectorControls>
			{ submissionMethod !== 'email' && (
				<InspectorControls group="advanced">
					<SelectControl
						label={ __( 'Method', 'wp-forms-blocks' ) }
						options={ [
							{ label: 'Get', value: 'get' },
							{ label: 'Post', value: 'post' },
						] }
						value={ method }
						onChange={ ( value ) =>
							setAttributes( { method: value } )
						}
						help={ __(
							'Select the method to use for form submissions.',
							'wp-forms-blocks'
						) }
					/>
					<TextControl
						autoComplete="off"
						label={ __( 'Form action', 'wp-forms-blocks' ) }
						value={ action }
						onChange={ ( newVal ) => {
							setAttributes( {
								action: newVal,
							} );
						} }
						help={ __(
							'The URL where the form should be submitted.',
							'wp-forms-blocks'
						) }
						type="url"
					/>
				</InspectorControls>
			) }
			<form
				{ ...innerBlocksProps }
				encType={ submissionMethod === 'email' ? 'text/plain' : null }
			/>
		</>
	);
};
export default Edit;
