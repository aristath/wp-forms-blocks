/**
 * @jest-environment node
 */

const settings = {
	nonce: 'test-nonce',
	ajaxUrl: 'https://example.com/wp-admin/admin-ajax.php',
	action: 'formblox_form_email_submit',
	submittingText: 'Submitting…',
};

describe( 'Gutenberg form view response handling', () => {
	let submitHandler;
	let form;

	beforeEach( () => {
		form = {
			dataset: { formbloxSubmissionMethod: 'email' },
			setAttribute: jest.fn(),
			querySelectorAll: jest.fn( () => [] ),
			after: jest.fn(),
			addEventListener: jest.fn( ( eventName, callback ) => {
				if ( 'submit' === eventName ) {
					submitHandler = callback;
				}
			} ),
		};
		global.document = {
			getElementById: jest.fn( () => ( {
				textContent: JSON.stringify( settings ),
			} ) ),
			querySelector: jest.fn( () => null ),
			querySelectorAll: jest.fn( () => [ form ] ),
			createElement: jest.fn( () => ( {
				setAttribute: jest.fn(),
			} ) ),
		};
		global.window = {
			location: {
				href: 'https://example.com/contact/?existing=1',
				search: '?existing=1',
			},
		};
		global.FormData = class FormData {
			entries() {
				return [ [ 'message', 'Hello' ] ];
			}
		};
	} );

	afterEach( () => {
		delete global.document;
		delete global.window;
		delete global.FormData;
		delete global.fetch;
	} );

	test.each( [
		[ 'success', true, true, null ],
		[ 'error', true, false, null ],
		[ 'error', false, true, null ],
		[ 'error', false, false, null ],
		[ 'error', true, null, new Error( 'Invalid JSON' ) ],
		[ 'error', null, null, new Error( 'Network unavailable' ) ],
	] )(
		'redirects to %s after the corresponding request result',
		async ( status, ok, success, thrownError ) => {
			if ( null === ok ) {
				global.fetch = jest.fn().mockRejectedValue( thrownError );
			} else {
				global.fetch = jest.fn().mockResolvedValue( {
					ok,
					json:
						null === success
							? jest.fn().mockRejectedValue( thrownError )
							: jest.fn().mockResolvedValue( { success } ),
				} );
			}
			jest.isolateModules( () => {
				require( '../../src/form/view' );
			} );
			const event = { preventDefault: jest.fn() };

			await submitHandler( event );

			expect( event.preventDefault ).toHaveBeenCalledTimes( 1 );
			expect( global.window.location.search ).toBe(
				`existing=1&formblox-form-result=${ status }`
			);
		}
	);
} );
