const settings = {
	nonce: 'test-nonce',
	ajaxUrl: 'https://example.com/wp-admin/admin-ajax.php',
	action: 'formblox_form_email_submit',
	submittingText: 'Submitting…',
};

const renderDocument = (
	submissionMethod,
	includeSettings = true,
	action = ''
) => {
	document.body.innerHTML = `
		${
			includeSettings
				? `<script id="wp-script-module-data-@formblox/form/view" type="application/json">${ JSON.stringify(
						settings
				  ) }</script>`
				: ''
		}
		<form class="wp-block-formblox-form" action="${ action }" data-formblox-submission-method="${ submissionMethod }">
			<input name="message" value="Hello">
			<button type="submit">Submit</button>
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
		window.history.replaceState( {}, '', '/' );
	} );

	test( 'submits email forms without a client-controlled recipient', async () => {
		renderDocument( 'email' );
		loadViewModule();

		const event = submit();
		const duplicateEvent = submit();
		await Promise.resolve();

		expect( event.defaultPrevented ).toBe( true );
		expect( duplicateEvent.defaultPrevented ).toBe( true );
		expect( global.fetch ).toHaveBeenCalledTimes( 1 );
		const form = document.querySelector( 'form' );
		expect( form.getAttribute( 'aria-busy' ) ).toBe( 'true' );
		expect( form.querySelector( 'button' ).disabled ).toBe( true );
		const submittingStatus = document.querySelector(
			'.formblox-form-submitting-status'
		);
		expect( submittingStatus.textContent ).toBe( 'Submitting…' );
		expect( submittingStatus.getAttribute( 'role' ) ).toBe( 'status' );
		expect( submittingStatus.getAttribute( 'aria-live' ) ).toBe( 'polite' );
		expect( submittingStatus.getAttribute( 'aria-atomic' ) ).toBe( 'true' );
		expect( submittingStatus.previousElementSibling ).toBe( form );
		const [ url, request ] = global.fetch.mock.calls[ 0 ];
		expect( url ).toBe( settings.ajaxUrl );
		expect( request.method ).toBe( 'POST' );
		expect( request.headers ).toEqual( {
			'Content-Type': 'application/x-www-form-urlencoded',
		} );
		const body = new URLSearchParams( request.body );
		expect( body.get( 'action' ) ).toBe( settings.action );
		expect( body.get( '_ajax_nonce' ) ).toBe( settings.nonce );
		expect( body.has( 'formAction' ) ).toBe( false );
		expect( body.get( 'message' ) ).toBe( 'Hello' );
	} );

	test.each( [
		[ 'custom actions', 'custom', true, 'https://example.com/custom' ],
		[ 'missing module data', 'email', false, '' ],
	] )(
		'leaves %s to normal browser submission',
		( label, method, data, action ) => {
			renderDocument( method, data, action );
			loadViewModule();

			const event = new Event( 'submit', {
				bubbles: true,
				cancelable: true,
			} );
			document.querySelector( 'form' ).dispatchEvent( event );

			expect( event.defaultPrevented ).toBe( false );
			expect( global.fetch ).not.toHaveBeenCalled();
			expect(
				document.querySelector( 'form' ).hasAttribute( 'aria-busy' )
			).toBe( false );
		}
	);

	test.each( [ 'success', 'error' ] )(
		'focuses the visible %s notification after a redirect',
		( status ) => {
			window.history.replaceState(
				{},
				'',
				`/?formblox-form-result=${ status }`
			);
			document.body.innerHTML = `<div class="wp-block-formblox-form-submission-notification formblox-form-notification-type-${ status }" tabindex="-1">Result</div>`;

			loadViewModule();

			expect( document.activeElement ).toBe(
				document.querySelector(
					'.wp-block-formblox-form-submission-notification'
				)
			);
		}
	);

	test( 'ignores malformed module data', () => {
		document.body.innerHTML = `
			<script id="wp-script-module-data-@formblox/form/view" type="application/json">not-json</script>
			<form class="wp-block-formblox-form" data-formblox-submission-method="email"></form>
		`;
		loadViewModule();

		expect( submit().defaultPrevented ).toBe( false );
		expect( global.fetch ).not.toHaveBeenCalled();
	} );

	test( 'enhances every email form on a page and no unrelated form', async () => {
		document.body.innerHTML = `
			<script id="wp-script-module-data-@formblox/form/view" type="application/json">${ JSON.stringify(
				settings
			) }</script>
			<form id="first" class="wp-block-formblox-form" data-formblox-submission-method="email"><input name="value" value="first"><input name="formAction" value="mailto:attacker-controlled@example.net"></form>
			<form id="second" class="wp-block-formblox-form" data-formblox-submission-method="email"><input name="value" value="second"></form>
			<form id="other" class="wp-block-formblox-form" data-formblox-submission-method="custom" action="https://example.com/custom"></form>
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
		).toBe( 'mailto:attacker-controlled@example.net' );
		expect(
			new URLSearchParams( global.fetch.mock.calls[ 1 ][ 1 ].body ).has(
				'formAction'
			)
		).toBe( false );
	} );
} );
