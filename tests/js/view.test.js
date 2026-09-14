import {
	attachEmailForm,
	getNotificationUrl,
	submitEmailForm,
} from '../../src/form/view';

const createForm = () => {
	document.body.innerHTML = `
		<form class="wp-block-form" action="https://example.com/wp-admin/admin-ajax.php">
			<input type="hidden" name="wp_forms_blocks_token" value="signed-token">
			<input name="choice" value="one">
			<input name="choice" value="two">
			<button type="submit">Send</button>
		</form>
	`;
	return document.querySelector( 'form' );
};

describe( 'form front-end submission adapter', () => {
	test( 'sets rather than duplicates the notification query parameter', () => {
		expect(
			getNotificationUrl(
				'success',
				'https://example.com/contact/?source=footer&wp-form-result=error#form'
			)
		).toBe(
			'https://example.com/contact/?source=footer&wp-form-result=success#form'
		);
	} );

	test( 'leaves custom forms to the browser', () => {
		document.body.innerHTML =
			'<form class="wp-block-form" action="https://example.com/custom"></form>';
		expect( attachEmailForm( document.querySelector( 'form' ) ) ).toBe(
			false
		);
	} );

	test( 'submits all values and restores buttons after success', async () => {
		const form = createForm();
		const navigate = jest.fn();
		const fetchRequest = jest.fn( async ( url, request ) => {
			expect( url ).toBe( 'https://example.com/wp-admin/admin-ajax.php' );
			expect( request.method ).toBe( 'POST' );
			expect( request.body.getAll( 'choice' ) ).toEqual( [
				'one',
				'two',
			] );
			expect( form.querySelector( 'button' ).disabled ).toBe( true );
			return { ok: true, json: async () => ( { success: true } ) };
		} );

		await expect(
			submitEmailForm( form, { fetch: fetchRequest, navigate } )
		).resolves.toBe( 'success' );
		expect( form.querySelector( 'button' ).disabled ).toBe( false );
		expect( navigate ).toHaveBeenCalledWith(
			'http://localhost/?wp-form-result=success'
		);
	} );

	test.each( [
		[
			'an application error',
			async () => ( {
				ok: true,
				json: async () => ( { success: false } ),
			} ),
		],
		[
			'an HTTP error',
			async () => ( {
				ok: false,
				json: async () => ( { success: true } ),
			} ),
		],
		[
			'a network error',
			async () => Promise.reject( new Error( 'offline' ) ),
		],
	] )(
		'shows the error notification for %s',
		async ( label, fetchRequest ) => {
			const navigate = jest.fn();
			await expect(
				submitEmailForm( createForm(), {
					fetch: fetchRequest,
					navigate,
				} )
			).resolves.toBe( 'error' );
			expect( navigate ).toHaveBeenCalledWith(
				'http://localhost/?wp-form-result=error'
			);
		}
	);
} );
