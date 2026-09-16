import clsx from 'clsx';
import { __ } from '@wordpress/i18n';
import {
	InspectorControls,
	RichText,
	useBlockProps,
	__experimentalUseBorderProps as useBorderProps,
	__experimentalUseColorProps as useColorProps,
} from '@wordpress/block-editor';
import {
	TextControl,
	CheckboxControl,
	__experimentalToolsPanel as ToolsPanel,
	__experimentalToolsPanelItem as ToolsPanelItem,
} from '@wordpress/components';
import { useRef } from '@wordpress/element';
import { useToolsPanelDropdownMenuProps } from '../utils/hooks';

function InputFieldBlock( { attributes, setAttributes, className } ) {
	const { type, name, label, inlineLabel, required, placeholder, value } =
		attributes;
	const blockProps = useBlockProps();
	const dropdownMenuProps = useToolsPanelDropdownMenuProps();
	const ref = useRef();
	const TagName = type === 'textarea' ? 'textarea' : 'input';

	const borderProps = useBorderProps( attributes );
	const colorProps = useColorProps( attributes );
	if ( ref.current ) {
		ref.current.focus();
	}

	// Note: radio inputs aren't implemented yet.
	const isCheckboxOrRadio = type === 'checkbox' || type === 'radio';

	const controls = (
		<>
			{ 'hidden' !== type && (
				<InspectorControls>
					<ToolsPanel
						label={ __( 'Settings', 'wp-forms-blocks' ) }
						resetAll={ () => {
							setAttributes( {
								inlineLabel: false,
								required: false,
							} );
						} }
						dropdownMenuProps={ dropdownMenuProps }
					>
						{ 'checkbox' !== type && (
							<ToolsPanelItem
								label={ __(
									'Inline label',
									'wp-forms-blocks'
								) }
								hasValue={ () => !! inlineLabel }
								onDeselect={ () =>
									setAttributes( { inlineLabel: false } )
								}
								isShownByDefault
							>
								<CheckboxControl
									label={ __(
										'Inline label',
										'wp-forms-blocks'
									) }
									checked={ inlineLabel }
									onChange={ ( newVal ) => {
										setAttributes( {
											inlineLabel: newVal,
										} );
									} }
								/>
							</ToolsPanelItem>
						) }

						<ToolsPanelItem
							label={ __( 'Required', 'wp-forms-blocks' ) }
							hasValue={ () => !! required }
							onDeselect={ () =>
								setAttributes( { required: false } )
							}
							isShownByDefault
						>
							<CheckboxControl
								label={ __( 'Required', 'wp-forms-blocks' ) }
								checked={ required }
								onChange={ ( newVal ) => {
									setAttributes( {
										required: newVal,
									} );
								} }
							/>
						</ToolsPanelItem>
					</ToolsPanel>
				</InspectorControls>
			) }
			<InspectorControls group="advanced">
				<TextControl
					autoComplete="off"
					label={ __( 'Name', 'wp-forms-blocks' ) }
					value={ name }
					onChange={ ( newVal ) => {
						setAttributes( {
							name: newVal,
						} );
					} }
					help={ __(
						'Affects the "name" attribute of the input element, and is used as a name for the form submission results.',
						'wp-forms-blocks'
					) }
				/>
				{ 'hidden' === type && (
					<TextControl
						autoComplete="off"
						label={ __( 'Value', 'wp-forms-blocks' ) }
						value={ value }
						onChange={ ( newVal ) =>
							setAttributes( { value: newVal } )
						}
						help={ __(
							'Sets the stored value for this hidden field.',
							'wp-forms-blocks'
						) }
					/>
				) }
			</InspectorControls>
		</>
	);

	const content = (
		<RichText
			tagName="span"
			className="wp-block-formblox-form-input__label-content"
			value={ label }
			onChange={ ( newLabel ) => setAttributes( { label: newLabel } ) }
			aria-label={
				label
					? __( 'Label', 'wp-forms-blocks' )
					: __( 'Empty label', 'wp-forms-blocks' )
			}
			data-empty={ ! label }
			placeholder={ __(
				'Type the label for this input',
				'wp-forms-blocks'
			) }
		/>
	);

	if ( 'hidden' === type ) {
		return (
			<div { ...blockProps }>
				{ controls }
				<span
					className="wp-block-formblox-form-input__label is-input-hidden"
					data-message={ __( 'Hidden field', 'wp-forms-blocks' ) }
				/>
			</div>
		);
	}
	const editorInputProps = isCheckboxOrRadio
		? {
				'aria-hidden': true,
				tabIndex: -1,
				onClick: ( event ) => event.preventDefault(),
		  }
		: {
				'aria-label': __(
					'Optional placeholder text',
					'wp-forms-blocks'
				),
				// We hide the placeholder field's placeholder when there is a value. This
				// stops screen readers from reading the placeholder field's placeholder
				// which is confusing.
				placeholder: placeholder
					? undefined
					: __( 'Optional placeholder…', 'wp-forms-blocks' ),
				value: placeholder,
				onChange: ( event ) =>
					setAttributes( { placeholder: event.target.value } ),
		  };

	return (
		<div { ...blockProps }>
			{ controls }
			<span
				className={ clsx( 'wp-block-formblox-form-input__label', {
					'is-label-inline': inlineLabel || 'checkbox' === type,
				} ) }
			>
				{ ! isCheckboxOrRadio && content }
				<TagName
					type={ 'textarea' === type ? undefined : type }
					className={ clsx(
						className,
						'wp-block-formblox-form-input__input',
						colorProps.className,
						borderProps.className
					) }
					{ ...editorInputProps }
					aria-required={ required }
					style={ {
						...borderProps.style,
						...colorProps.style,
					} }
				/>
				{ isCheckboxOrRadio && content }
			</span>
		</div>
	);
}

export default InputFieldBlock;
