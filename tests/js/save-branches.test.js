import { renderToStaticMarkup } from 'react-dom/server';

import formDeprecated from '../../src/form/deprecated';
import formSave from '../../src/form/save';
import inputDeprecated from '../../src/form-input/deprecated';
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
	test( 'saves current and deprecated hidden fields exactly', () => {
		const attributes = {
			type: 'hidden',
			name: 'token',
			value: 'secret',
		};
		expect( renderToStaticMarkup( inputSave( { attributes } ) ) ).toBe(
			'<input type="hidden" name="token" value="secret"/>'
		);
		for ( const deprecated of inputDeprecated ) {
			expect(
				renderToStaticMarkup( deprecated.save( { attributes } ) )
			).toBe( '<input type="hidden" name="token" value="secret"/>' );
		}
	} );

	test( 'omits email encoding from current and deprecated custom forms', () => {
		const attributes = { submissionMethod: 'custom' };
		expect( renderToStaticMarkup( formSave( { attributes } ) ) ).toBe(
			'<form><span>Inner blocks</span></form>'
		);
		expect(
			renderToStaticMarkup( formDeprecated[ 0 ].save( { attributes } ) )
		).toBe(
			'<form class="wp-block-form"><span>Inner blocks</span></form>'
		);
	} );
} );
