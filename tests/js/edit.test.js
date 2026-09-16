/* eslint-disable jest/no-conditional-expect */
import { act } from 'react';
import { createRoot } from 'react-dom/client';

import FormEdit from '../../src/form/edit';
import InputEdit from '../../src/form-input/edit';
import NotificationEdit from '../../src/form-submission-notification/edit';
import SubmitButtonEdit from '../../src/form-submit-button/edit';
import SubmitButtonSave from '../../src/form-submit-button/save';

const mockCaptured = {
	checkboxControls: [],
	innerBlockOptions: [],
	richTexts: [],
	selectControls: [],
	textControls: [],
	toolsPanels: [],
	toolsPanelItems: [],
};
const mockState = {
	block: null,
	blockProps: {},
	ref: { current: null },
};
const mockTemplates = {
	'formblox/form': [ [ 'formblox/form-input' ] ],
	'formblox/form-submit-button': [ [ 'core/buttons' ] ],
	'formblox/form-submission-notification': [ [ 'core/paragraph' ] ],
};

jest.mock( '../../src/utils/hooks', () => ( {
	useToolsPanelDropdownMenuProps: () => ( {
		popoverProps: { placement: 'left-start', offset: 259 },
	} ),
} ) );

jest.mock( '@wordpress/element', () => ( {
	...jest.requireActual( '@wordpress/element' ),
	useRef: () => mockState.ref,
} ) );

jest.mock( '@wordpress/data', () => ( {
	useSelect: ( callback ) =>
		callback( () => ( {
			getBlock: () => mockState.block,
		} ) ),
} ) );

jest.mock( '@wordpress/blocks', () => ( {
	getBlockType: ( name ) => ( { template: mockTemplates[ name ] } ),
} ) );

jest.mock( '@wordpress/block-editor', () => {
	const React = jest.requireActual( 'react' );
	const useBlockProps = ( properties = {} ) => ( {
		...mockState.blockProps,
		...properties,
		'data-block-props': 'true',
	} );
	useBlockProps.save = useBlockProps;
	const useInnerBlocksProps = ( properties = {}, options = {} ) => {
		mockCaptured.innerBlockOptions.push( options );
		return { ...properties, 'data-inner-block-props': 'true' };
	};
	useInnerBlocksProps.save = useInnerBlocksProps;

	return {
		InnerBlocks: {
			ButtonBlockAppender: () =>
				React.createElement( 'span', null, 'Appender' ),
			Content: () => React.createElement( 'span', null, 'Content' ),
		},
		InspectorControls: ( { children, group } ) =>
			React.createElement(
				'div',
				{ 'data-inspector-group': group || 'default' },
				children
			),
		RichText: ( properties ) => {
			mockCaptured.richTexts.push( properties );
			return React.createElement( 'span', null, properties.value );
		},
		store: {},
		useBlockProps,
		useInnerBlocksProps,
		__experimentalUseBorderProps: () => ( {
			className: 'has-border',
			style: { borderRadius: '2px' },
		} ),
		__experimentalUseColorProps: () => ( {
			className: 'has-color',
			style: { color: 'red' },
		} ),
	};
} );

jest.mock( '@wordpress/components', () => {
	const React = jest.requireActual( 'react' );
	const capture = ( collection, tagName ) => ( properties ) => {
		mockCaptured[ collection ].push( properties );
		return React.createElement(
			tagName,
			{ 'data-control-label': properties.label },
			properties.children
		);
	};

	return {
		CheckboxControl: capture( 'checkboxControls', 'span' ),
		SelectControl: capture( 'selectControls', 'span' ),
		TextControl: capture( 'textControls', 'span' ),
		__experimentalToolsPanel: capture( 'toolsPanels', 'section' ),
		__experimentalToolsPanelItem: capture( 'toolsPanelItems', 'div' ),
	};
} );

const getControl = ( collection, label, occurrence = 0 ) =>
	mockCaptured[ collection ].filter(
		( properties ) => properties.label === label
	)[ occurrence ];

describe( 'block editor components', () => {
	let container;
	let root;

	beforeAll( () => {
		global.IS_REACT_ACT_ENVIRONMENT = true;
	} );

	beforeEach( () => {
		Object.keys( mockCaptured ).forEach( ( key ) => {
			mockCaptured[ key ].length = 0;
		} );
		mockState.block = null;
		mockState.blockProps = {};
		mockState.ref = { current: null };
		container = document.createElement( 'div' );
		document.body.appendChild( container );
		root = createRoot( container );
	} );

	afterEach( () => {
		act( () => root.unmount() );
		container.remove();
	} );

	test( 'form editor sends email to the site administrator without a recipient control', () => {
		const setAttributes = jest.fn();
		mockState.block = { innerBlocks: [] };
		act( () => {
			root.render(
				<FormEdit
					attributes={ {
						submissionMethod: 'email',
						action: undefined,
						method: 'post',
					} }
					setAttributes={ setAttributes }
					clientId="form"
				/>
			);
		} );

		expect(
			container.querySelector( 'form' ).getAttribute( 'enctype' )
		).toBe( 'text/plain' );
		expect(
			mockCaptured.innerBlockOptions[ 0 ].renderAppender
		).toBeDefined();
		expect( mockCaptured.innerBlockOptions[ 0 ].template ).toEqual(
			mockTemplates[ 'formblox/form' ]
		);
		expect( mockCaptured.toolsPanels[ 0 ].dropdownMenuProps ).toEqual( {
			popoverProps: { placement: 'left-start', offset: 259 },
		} );
		expect(
			getControl( 'toolsPanelItems', 'Submissions method' ).hasValue()
		).toBe( false );
		getControl( 'selectControls', 'Submissions method' ).onChange(
			'custom'
		);
		getControl( 'toolsPanelItems', 'Submissions method' ).onDeselect();
		mockCaptured.toolsPanels[ 0 ].resetAll();

		expect( setAttributes.mock.calls ).toEqual( [
			[ { submissionMethod: 'custom' } ],
			[ { submissionMethod: 'email' } ],
			[
				{
					submissionMethod: 'email',
					action: undefined,
					method: 'post',
				},
			],
		] );
	} );

	test( 'form editor exposes and applies custom action settings', () => {
		const setAttributes = jest.fn();
		mockState.block = { innerBlocks: [ { name: 'formblox/form-input' } ] };
		act( () => {
			root.render(
				<FormEdit
					attributes={ {
						submissionMethod: 'custom',
						action: 'https://example.com/submit',
						method: 'get',
					} }
					setAttributes={ setAttributes }
					clientId="form"
				/>
			);
		} );

		expect(
			container.querySelector( 'form' ).hasAttribute( 'enctype' )
		).toBe( false );
		expect(
			mockCaptured.innerBlockOptions[ 0 ].renderAppender
		).toBeUndefined();
		expect(
			getControl( 'toolsPanelItems', 'Submissions method' ).hasValue()
		).toBe( true );
		getControl( 'selectControls', 'Method' ).onChange( 'post' );
		getControl( 'textControls', 'Form action' ).onChange(
			'https://example.com/new'
		);
		expect( setAttributes.mock.calls ).toEqual( [
			[ { method: 'post' } ],
			[ { action: 'https://example.com/new' } ],
		] );
	} );

	test.each( [ 'text', 'textarea', 'checkbox', 'radio' ] )(
		'input editor renders and updates the %s field branch',
		( type ) => {
			const setAttributes = jest.fn();
			const focus = jest.fn();
			mockState.ref = { current: { focus } };
			act( () => {
				root.render(
					<InputEdit
						attributes={ {
							type,
							name: 'field',
							label: 'Field label',
							inlineLabel: false,
							required: false,
							placeholder: '',
							value: '',
						} }
						setAttributes={ setAttributes }
						className="custom-class"
					/>
				);
			} );

			expect( focus ).toHaveBeenCalledTimes( 1 );
			const field = container.querySelector(
				type === 'textarea' ? 'textarea' : 'input'
			);
			expect( [ ...field.classList ] ).toEqual(
				expect.arrayContaining( [
					'custom-class',
					'wp-block-formblox-form-input__input',
					'has-color',
					'has-border',
				] )
			);
			expect( field.style.color ).toBe( 'red' );
			expect( field.style.borderRadius ).toBe( '2px' );
			if ( 'checkbox' === type || 'radio' === type ) {
				expect( field.hasAttribute( 'placeholder' ) ).toBe( false );
				expect( field.value ).toBe( 'on' );
				expect( field.tabIndex ).toBe( -1 );
				expect( field.getAttribute( 'aria-hidden' ) ).toBe( 'true' );
				act( () => field.click() );
				expect( field.checked ).toBe( false );
			}
			expect( getControl( 'textControls', 'Name' ).value ).toBe(
				'field'
			);
			expect(
				getControl( 'toolsPanelItems', 'Required' ).hasValue()
			).toBe( false );
			getControl( 'textControls', 'Name' ).onChange( 'renamed' );
			getControl( 'checkboxControls', 'Required' ).onChange( true );
			getControl( 'toolsPanelItems', 'Required' ).onDeselect();
			mockCaptured.toolsPanels[ 0 ].resetAll();
			mockCaptured.richTexts[ 0 ].onChange( 'Changed label' );

			if ( 'checkbox' !== type ) {
				expect(
					getControl( 'toolsPanelItems', 'Inline label' ).hasValue()
				).toBe( false );
				getControl( 'checkboxControls', 'Inline label' ).onChange(
					true
				);
				getControl( 'toolsPanelItems', 'Inline label' ).onDeselect();
			} else {
				expect(
					getControl( 'checkboxControls', 'Inline label' )
				).toBeUndefined();
				expect(
					container
						.querySelector( '.wp-block-formblox-form-input__label' )
						.classList.contains( 'is-label-inline' )
				).toBe( true );
			}
			if ( 'text' === type || 'textarea' === type ) {
				act( () => {
					const prototype =
						type === 'textarea'
							? window.HTMLTextAreaElement.prototype
							: window.HTMLInputElement.prototype;
					Object.getOwnPropertyDescriptor(
						prototype,
						'value'
					).set.call( field, 'Changed placeholder' );
					field.dispatchEvent(
						new Event( 'input', { bubbles: true } )
					);
				} );
			}

			expect( setAttributes ).toHaveBeenCalledWith( { name: 'renamed' } );
			expect( setAttributes ).toHaveBeenCalledWith( { required: true } );
			expect( setAttributes ).toHaveBeenCalledWith( { required: false } );
			expect( setAttributes ).toHaveBeenCalledWith( {
				inlineLabel: false,
				required: false,
			} );
			expect( setAttributes ).toHaveBeenCalledWith( {
				label: 'Changed label',
			} );
			if ( 'text' === type || 'textarea' === type ) {
				expect( setAttributes ).toHaveBeenCalledWith( {
					placeholder: 'Changed placeholder',
				} );
			} else {
				expect(
					setAttributes.mock.calls.some( ( [ attributes ] ) =>
						Object.hasOwn( attributes, 'placeholder' )
					)
				).toBe( false );
			}
		}
	);

	test( 'input editor renders and updates a hidden field', () => {
		const setAttributes = jest.fn();
		act( () => {
			root.render(
				<InputEdit
					attributes={ {
						type: 'hidden',
						name: 'token',
						label: '',
						inlineLabel: false,
						required: false,
						placeholder: 'ignored',
						value: 'old',
					} }
					setAttributes={ setAttributes }
				/>
			);
		} );

		expect(
			container
				.querySelector( '.is-input-hidden' )
				.getAttribute( 'data-message' )
		).toBe( 'Hidden field' );
		expect( mockCaptured.toolsPanels ).toHaveLength( 0 );
		expect( getControl( 'textControls', 'Value' ).value ).toBe( 'old' );
		getControl( 'textControls', 'Value' ).onChange( 'new' );
		expect( setAttributes ).toHaveBeenCalledWith( { value: 'new' } );
	} );

	test.each( [
		[ 'success', 'formblox-form-notification-type-success' ],
		[ 'error', 'formblox-form-notification-type-error' ],
	] )( 'notification editor renders the %s state', ( type, className ) => {
		mockState.block = { innerBlocks: [] };
		act( () => {
			root.render(
				<NotificationEdit attributes={ { type } } clientId="notice" />
			);
		} );

		const notification = container.querySelector(
			'.wp-block-formblox-form-submission-notification'
		);
		expect( notification.classList.contains( className ) ).toBe( true );
		expect( notification.getAttribute( 'data-message-success' ) ).toBe(
			'Submission success notification'
		);
		expect( notification.getAttribute( 'data-message-error' ) ).toBe(
			'Submission error notification'
		);
		expect(
			mockCaptured.innerBlockOptions[ 0 ].renderAppender
		).toBeDefined();
		expect( mockCaptured.innerBlockOptions[ 0 ].template ).toEqual(
			mockTemplates[ 'formblox/form-submission-notification' ]
		);
	} );

	test( 'notification and submit editors suppress or lock appenders correctly', () => {
		mockState.block = { innerBlocks: [ { name: 'core/paragraph' } ] };
		act( () => {
			root.render(
				<NotificationEdit
					attributes={ { type: undefined } }
					clientId="notice"
				/>
			);
		} );
		expect(
			mockCaptured.innerBlockOptions[ 0 ].renderAppender
		).toBeUndefined();
		expect(
			container.querySelector(
				'.formblox-form-notification-type-success'
			)
		).toBeNull();

		act( () => {
			mockState.blockProps = {
				className: 'wp-block-formblox-form-submit-button',
			};
			root.render( <SubmitButtonEdit /> );
		} );
		expect( mockCaptured.innerBlockOptions.at( -1 ) ).toEqual( {
			template: mockTemplates[ 'formblox/form-submit-button' ],
			templateLock: 'all',
		} );
		expect(
			container.querySelector( '.wp-block-formblox-form-submit-button' )
		).not.toBeNull();
		expect(
			container.querySelector( '.wp-block-formblox-form-submit-wrapper' )
		).toBeNull();

		act( () => root.render( <SubmitButtonSave /> ) );
		expect(
			container.querySelector( '.wp-block-formblox-form-submit-button' )
		).not.toBeNull();
		expect(
			container.querySelector( '.wp-block-formblox-form-submit-wrapper' )
		).toBeNull();
	} );
} );
