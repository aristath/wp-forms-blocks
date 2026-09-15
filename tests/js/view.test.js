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

const submit = () => {
	const event = new Event( 'submit', {
		bubbles: true,
		cancelable: true,
	} );
	document.querySelector( 'form' ).dispatchEvent( event );
	return event;
};

describe( 'Gutenberg form view module', () => {
	beforeEach( () => {
		global.fetch = jest.fn( () => new Promise( () => {} ) );
	} );

	test( 'submits mailto forms with the original module-data contract', async () => {
		renderDocument( 'mailto:recipient@example.com' );
		loadViewModule();

		const event = submit();
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

	test( 'ignores malformed module data', () => {
		document.body.innerHTML = `
			<script id="wp-script-module-data-@wordpress/block-library/form/view" type="application/json">not-json</script>
			<form class="wp-block-form" action="mailto:recipient@example.com"></form>
		`;
		loadViewModule();

		expect( submit().defaultPrevented ).toBe( false );
		expect( global.fetch ).not.toHaveBeenCalled();
	} );

	test( 'enhances every mailto form on a page and no unrelated form', async () => {
		document.body.innerHTML = `
			<script id="wp-script-module-data-@wordpress/block-library/form/view" type="application/json">${ JSON.stringify(
				settings
			) }</script>
			<form id="first" class="wp-block-form" action="mailto:first@example.com"><input name="value" value="first"></form>
			<form id="second" class="wp-block-form" action="mailto:second@example.com"><input name="value" value="second"></form>
			<form id="other" action="mailto:other@example.com"></form>
		`;
		loadViewModule();

		for ( const id of [ 'first', 'second' ] ) {
			const event = new Event( 'submit', {
				bubbles: true,
				cancelable: true,
			} );
			document.getElementById( id ).dispatchEvent( event );
			expect( event.defaultPrevented ).toBe( true );
		}
		const unrelatedEvent = new Event( 'submit', {
			bubbles: true,
			cancelable: true,
		} );
		document.getElementById( 'other' ).dispatchEvent( unrelatedEvent );
		await Promise.resolve();

		expect( unrelatedEvent.defaultPrevented ).toBe( false );
		expect( global.fetch ).toHaveBeenCalledTimes( 2 );
		expect(
			new URLSearchParams( global.fetch.mock.calls[ 0 ][ 1 ].body ).get(
				'formAction'
			)
		).toBe( 'mailto:first@example.com' );
		expect(
			new URLSearchParams( global.fetch.mock.calls[ 1 ][ 1 ].body ).get(
				'formAction'
			)
		).toBe( 'mailto:second@example.com' );
	} );
} );
