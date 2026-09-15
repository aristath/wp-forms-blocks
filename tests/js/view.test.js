const settings = {
	nonce: 'test-nonce',
	ajaxUrl: 'https://example.com/wp-admin/admin-ajax.php',
	action: 'wp_block_form_email_submit',
};

const renderDocument = ( action, includeSettings = true ) => {
	document.body.innerHTML = `
		${
			includeSettings
				? `<script id="wp-script-module-data-@wordpress/block-library/form/view" type="application/json">${ JSON.stringify(
						settings
				  ) }</script>`
				: ''
		}
		<form class="wp-block-form" action="${ action }">
			<input name="message" value="Hello">
		</form>
	`;
};

const loadViewModule = () => {
	jest.isolateModules( () => {
		require( '../../src/form/view' );
	} );
};

describe( 'Gutenberg form view module', () => {
	beforeEach( () => {
		global.fetch = jest.fn( () => new Promise( () => {} ) );
	} );

	test( 'submits mailto forms with the original module-data contract', async () => {
		renderDocument( 'mailto:recipient@example.com' );
		loadViewModule();

		const form = document.querySelector( 'form' );
		const event = new Event( 'submit', {
			bubbles: true,
			cancelable: true,
		} );
		form.dispatchEvent( event );
		await Promise.resolve();

		expect( event.defaultPrevented ).toBe( true );
		expect( global.fetch ).toHaveBeenCalledTimes( 1 );
		const [ url, request ] = global.fetch.mock.calls[ 0 ];
		expect( url ).toBe( settings.ajaxUrl );
		expect( request.method ).toBe( 'POST' );
		expect( request.headers ).toEqual( {
			'Content-Type': 'application/x-www-form-urlencoded',
		} );
		const body = new URLSearchParams( request.body );
		expect( body.get( 'action' ) ).toBe( settings.action );
		expect( body.get( '_ajax_nonce' ) ).toBe( settings.nonce );
		expect( body.get( 'formAction' ) ).toBe(
			'mailto:recipient@example.com'
		);
		expect( body.get( 'message' ) ).toBe( 'Hello' );
	} );

	test.each( [
		[ 'custom actions', 'https://example.com/custom', true ],
		[ 'missing module data', 'mailto:recipient@example.com', false ],
	] )( 'leaves %s to normal browser submission', ( label, action, data ) => {
		renderDocument( action, data );
		loadViewModule();

		const event = new Event( 'submit', {
			bubbles: true,
			cancelable: true,
		} );
		document.querySelector( 'form' ).dispatchEvent( event );

		expect( event.defaultPrevented ).toBe( false );
		expect( global.fetch ).not.toHaveBeenCalled();
	} );
} );
