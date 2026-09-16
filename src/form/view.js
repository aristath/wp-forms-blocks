let formSettings;
try {
	formSettings = JSON.parse(
		document.getElementById( 'wp-script-module-data-@formblox/form/view' )
			?.textContent
	);
} catch {}

document
	.querySelectorAll( 'form.wp-block-formblox-form' )
	.forEach( function ( form ) {
		// Bail if the form settings are unavailable or this is not an email form.
		if (
			! formSettings ||
			form.dataset.formbloxSubmissionMethod !== 'email'
		) {
			return;
		}

		const redirectNotification = ( status ) => {
			const urlParams = new URLSearchParams( window.location.search );
			urlParams.append( 'formblox-form-result', status );
			window.location.search = urlParams.toString();
		};

		// Add an event listener for the form submission.
		form.addEventListener( 'submit', async function ( event ) {
			event.preventDefault();
			// Get the form data and merge it with the form action and nonce.
			const formData = Object.fromEntries(
				new FormData( form ).entries()
			);
			formData._ajax_nonce = formSettings.nonce;
			formData.action = formSettings.action;
			formData._wp_http_referer = window.location.href;

			try {
				const response = await fetch( formSettings.ajaxUrl, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
					},
					body: new URLSearchParams( formData ).toString(),
				} );
				const responseData = await response.json();
				if ( response.ok && true === responseData.success ) {
					redirectNotification( 'success' );
				} else {
					redirectNotification( 'error' );
				}
			} catch {
				redirectNotification( 'error' );
			}
		} );
	} );
