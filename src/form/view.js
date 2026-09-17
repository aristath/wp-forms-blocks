let formSettings;
try {
	formSettings = JSON.parse(
		document.getElementById( 'wp-script-module-data-@formblox/form/view' )
			?.textContent
	);
} catch {}

const result = new URLSearchParams( window.location.search ).get(
	'formblox-form-result'
);
if ( 'success' === result || 'error' === result ) {
	document
		.querySelector(
			`.wp-block-formblox-form-submission-notification.formblox-form-notification-type-${ result }`
		)
		?.focus();
}

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
			urlParams.set( 'formblox-form-result', status );
			window.location.search = urlParams.toString();
		};
		let isSubmitting = false;

		// Add an event listener for the form submission.
		form.addEventListener( 'submit', async function ( event ) {
			event.preventDefault();
			if ( isSubmitting ) {
				return;
			}
			isSubmitting = true;
			form.setAttribute( 'aria-busy', 'true' );
			form.querySelectorAll(
				'button[type="submit"], button:not([type]), input[type="submit"]'
			).forEach( ( control ) => {
				control.disabled = true;
			} );

			const submittingStatus = document.createElement( 'p' );
			submittingStatus.className = 'formblox-form-submitting-status';
			submittingStatus.setAttribute( 'role', 'status' );
			submittingStatus.setAttribute( 'aria-live', 'polite' );
			submittingStatus.setAttribute( 'aria-atomic', 'true' );
			submittingStatus.textContent = formSettings.submittingText;
			form.after( submittingStatus );

			// Preserve repeated controls and use array-style names when needed so
			// PHP does not discard all but the final value.
			const submittedData = new FormData( form );
			const formData = new URLSearchParams();
			const valuesByName = new Map();
			[ ...submittedData.entries() ].forEach( ( [ name, value ] ) => {
				const values = valuesByName.get( name ) || [];
				values.push( value );
				valuesByName.set( name, values );
			} );
			valuesByName.forEach( ( values, name ) => {
				const submittedName =
					values.length > 1 && ! name.endsWith( '[]' )
						? `${ name }[]`
						: name;
				values.forEach( ( value ) => {
					formData.append( submittedName, value );
				} );
			} );
			formData.set( '_ajax_nonce', formSettings.nonce );
			formData.set( 'action', formSettings.action );
			formData.set( '_wp_http_referer', window.location.href );

			try {
				const response = await fetch( formSettings.ajaxUrl, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
					},
					body: formData.toString(),
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
