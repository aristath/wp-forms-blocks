document.querySelectorAll( 'form.wp-block-form' ).forEach( function ( form ) {
	// Custom forms submit normally to their configured action.
	if ( ! form.querySelector( '[name="wp_forms_blocks_token"]' ) ) {
		return;
	}

	const redirectNotification = ( status ) => {
		const url = new URL( window.location.href );
		url.searchParams.set( 'wp-form-result', status );
		window.location.assign( url.href );
	};

	// Add an event listener for the form submission.
	form.addEventListener( 'submit', async function ( event ) {
		event.preventDefault();
		const formData = new FormData( form );
		const submitButtons = form.querySelectorAll( '[type="submit"]' );
		submitButtons.forEach( ( button ) => {
			button.disabled = true;
		} );

		try {
			const response = await fetch( form.action, {
				method: 'POST',
				body: formData,
			} );
			const result = await response.json();
			if ( response.ok && result.success ) {
				redirectNotification( 'success' );
			} else {
				redirectNotification( 'error' );
			}
		} catch {
			redirectNotification( 'error' );
		} finally {
			submitButtons.forEach( ( button ) => {
				button.disabled = false;
			} );
		}
	} );
} );
