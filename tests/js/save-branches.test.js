import { renderToStaticMarkup } from 'react-dom/server';

import formSave from '../../src/form/save';
import inputSave from '../../src/form-input/save';

jest.mock( '@wordpress/block-editor', () => {
	const React = jest.requireActual( 'react' );
	const useBlockProps = ( properties = {} ) => properties;
	useBlockProps.save = useBlockProps;
	return {
		InnerBlocks: {
			Content: () => React.createElement( 'span', null, 'Inner blocks' ),
		},
		RichText: {
			Content: ( { value } ) =>
				React.createElement( React.Fragment, null, value ),
		},
		useBlockProps,
		getTypographyClassesAndStyles: () => ( { style: {} } ),
		__experimentalGetBorderClassesAndStyles: () => ( { style: {} } ),
		__experimentalGetColorClassesAndStyles: () => ( { style: {} } ),
		__experimentalGetSpacingClassesAndStyles: () => ( { style: {} } ),
	};
} );

describe( 'save-function edge branches', () => {
	test( 'saves hidden fields exactly', () => {
		const attributes = {
			type: 'hidden',
			name: 'token',
			value: 'secret',
		};
		expect( renderToStaticMarkup( inputSave( { attributes } ) ) ).toBe(
			'<input type="hidden" name="token" value="secret"/>'
		);
	} );

	test( 'omits email encoding from custom forms', () => {
		const attributes = { submissionMethod: 'custom' };
		expect( renderToStaticMarkup( formSave( { attributes } ) ) ).toBe(
			'<form><span>Inner blocks</span></form>'
		);
	} );
} );
