/**
 * Build the page URL that selects the matching notification block.
 *
 * @param {string} status Submission status.
 * @param {string} href   Current page URL.
 * @return {string} Notification URL.
 */
export function getNotificationUrl( status, href = window.location.href ) {
	const url = new URL( href );
	url.searchParams.set( 'wp-form-result', status );
	return url.href;
}

/**
 * Submit an email form and navigate to its success or error notification.
 *
 * @param {HTMLFormElement} form    Form element.
 * @param {Object}          options Testable browser dependencies.
 * @return {Promise<string>} Submission status.
 */
export async function submitEmailForm( form, options = {} ) {
	const fetchRequest = options.fetch || window.fetch.bind( window );
	const navigate =
		options.navigate || ( ( url ) => window.location.assign( url ) );
	const formData = new FormData( form );
	const submitButtons = form.querySelectorAll( '[type="submit"]' );
	let status = 'error';

	submitButtons.forEach( ( button ) => {
		button.disabled = true;
	} );

	try {
		const response = await fetchRequest( form.action, {
			method: 'POST',
			body: formData,
		} );
		const result = await response.json();
		if ( response.ok && result.success ) {
			status = 'success';
		}
	} catch {
		status = 'error';
	} finally {
		submitButtons.forEach( ( button ) => {
			button.disabled = false;
		} );
	}

	navigate( getNotificationUrl( status ) );
	return status;
}

/**
 * Attach the AJAX handler to a server-configured email form.
 *
 * @param {HTMLFormElement} form    Form element.
 * @param {Object}          options Testable browser dependencies.
 * @return {boolean} Whether the handler was attached.
 */
export function attachEmailForm( form, options = {} ) {
	// Custom forms submit normally to their configured action.
	if ( ! form.querySelector( '[name="wp_forms_blocks_token"]' ) ) {
		return false;
	}

	form.addEventListener( 'submit', function ( event ) {
		event.preventDefault();
		submitEmailForm( form, options );
	} );

	return true;
}

document
	.querySelectorAll( 'form.wp-block-form' )
	.forEach( ( form ) => attachEmailForm( form ) );
