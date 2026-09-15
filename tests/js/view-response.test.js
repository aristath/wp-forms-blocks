/**
 * @jest-environment node
 */

const settings = {
	nonce: 'test-nonce',
	ajaxUrl: 'https://example.com/wp-admin/admin-ajax.php',
	action: 'formblox_form_email_submit',
};

describe( 'Gutenberg form view response handling', () => {
	let submitHandler;
	let form;

	beforeEach( () => {
		form = {
			action: 'mailto:recipient@example.com',
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
			querySelectorAll: jest.fn( () => [ form ] ),
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
		[ 'success', true, null ],
		[ 'error', false, null ],
		[ 'error', null, new Error( 'Network unavailable' ) ],
	] )(
		'redirects to %s after the corresponding request result',
		async ( status, ok, thrownError ) => {
			global.fetch = thrownError
				? jest.fn().mockRejectedValue( thrownError )
				: jest.fn().mockResolvedValue( { ok } );
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
