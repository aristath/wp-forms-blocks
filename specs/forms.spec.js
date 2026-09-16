const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

const successNotification = `<!-- wp:formblox/form-submission-notification -->
<div class="wp-block-formblox-form-submission-notification formblox-form-notification-type-success"><!-- wp:paragraph -->
<p>Submission succeeded</p>
<!-- /wp:paragraph --></div>
<!-- /wp:formblox/form-submission-notification -->`;

const errorNotification = `<!-- wp:formblox/form-submission-notification {"type":"error"} -->
<div class="wp-block-formblox-form-submission-notification formblox-form-notification-type-error"><!-- wp:paragraph -->
<p>Submission failed</p>
<!-- /wp:paragraph --></div>
<!-- /wp:formblox/form-submission-notification -->`;

const inputBlocks = [
	[ 'text', 'Full name', 'full-name' ],
	[ 'email', 'Email', 'email' ],
	[ 'url', 'Website', 'website' ],
	[ 'tel', 'Phone', 'phone' ],
	[ 'number', 'Guests', 'guests' ],
]
	.map(
		( [
			type,
			label,
			name,
		] ) => `<!-- wp:formblox/form-input {"type":"${ type }","name":"${ name }","label":"${ label }","required":true} -->
<div class="wp-block-formblox-form-input"><label class="wp-block-formblox-form-input__label"><span class="wp-block-formblox-form-input__label-content">${ label }</span><input class="wp-block-formblox-form-input__input" type="${ type }" name="${ name }" required aria-required="true"/></label></div>
<!-- /wp:formblox/form-input -->`
	)
	.join( '\n\n' );

const formContent = `<!-- wp:formblox/form -->
<form class="wp-block-formblox-form" enctype="text/plain">
${ successNotification }

${ errorNotification }

${ inputBlocks }

<!-- wp:formblox/form-input {"type":"textarea","name":"message","label":"Message","required":true} -->
<div class="wp-block-formblox-form-input"><label class="wp-block-formblox-form-input__label"><span class="wp-block-formblox-form-input__label-content">Message</span><textarea class="wp-block-formblox-form-input__input" name="message" required aria-required="true"></textarea></label></div>
<!-- /wp:formblox/form-input -->

<!-- wp:formblox/form-input {"type":"checkbox","name":"consent","label":"Consent","inlineLabel":true} -->
<div class="wp-block-formblox-form-input"><label class="wp-block-formblox-form-input__label is-label-inline"><input class="wp-block-formblox-form-input__input" type="checkbox" name="consent" aria-required="false"/><span class="wp-block-formblox-form-input__label-content">Consent</span></label></div>
<!-- /wp:formblox/form-input -->

<!-- wp:formblox/form-input {"type":"hidden","name":"source","value":"e2e"} -->
<input type="hidden" name="source" value="e2e"/>
<!-- /wp:formblox/form-input -->

<!-- wp:formblox/form-submit-button -->
<div class="wp-block-formblox-form-submit-button"><!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"tagName":"button","type":"submit"} -->
<div class="wp-block-button"><button type="submit" class="wp-block-button__link wp-element-button">Submit</button></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:formblox/form-submit-button -->
</form>
<!-- /wp:formblox/form -->`;

const visibilityContent = `<!-- wp:formblox/form {"submissionMethod":"custom","action":""} -->
<form class="wp-block-formblox-form">
<!-- wp:formblox/form-input {"name":"always","label":"Always"} -->
<div class="wp-block-formblox-form-input"><label class="wp-block-formblox-form-input__label"><span class="wp-block-formblox-form-input__label-content">Always</span><input class="wp-block-formblox-form-input__input" type="text" name="always" aria-required="false"/></label></div>
<!-- /wp:formblox/form-input -->
<!-- wp:formblox/form-input {"name":"members","label":"Members","visibilityPermissions":"logged-in"} -->
<div class="wp-block-formblox-form-input"><label class="wp-block-formblox-form-input__label"><span class="wp-block-formblox-form-input__label-content">Members</span><input class="wp-block-formblox-form-input__input" type="text" name="members" aria-required="false"/></label></div>
<!-- /wp:formblox/form-input -->
<!-- wp:formblox/form-input {"name":"visitors","label":"Visitors","visibilityPermissions":"logged-out"} -->
<div class="wp-block-formblox-form-input"><label class="wp-block-formblox-form-input__label"><span class="wp-block-formblox-form-input__label-content">Visitors</span><input class="wp-block-formblox-form-input__input" type="text" name="visitors" aria-required="false"/></label></div>
<!-- /wp:formblox/form-input -->
</form>
<!-- /wp:formblox/form -->`;

const privacyContent = `<!-- wp:formblox/form {"submissionMethod":"custom","action":"","anchor":"gdpr-form"} -->
<form class="wp-block-formblox-form" id="gdpr-form">
${ successNotification }
${ errorNotification }
<!-- wp:formblox/form-input {"type":"email","name":"email","label":"Privacy email","required":true} -->
<div class="wp-block-formblox-form-input"><label class="wp-block-formblox-form-input__label"><span class="wp-block-formblox-form-input__label-content">Privacy email</span><input class="wp-block-formblox-form-input__input" type="email" name="email" required aria-required="true"/></label></div>
<!-- /wp:formblox/form-input -->
<!-- wp:formblox/form-input {"type":"checkbox","name":"export_personal_data","label":"Export","inlineLabel":true} -->
<div class="wp-block-formblox-form-input"><label class="wp-block-formblox-form-input__label is-label-inline"><input class="wp-block-formblox-form-input__input" type="checkbox" name="export_personal_data" aria-required="false"/><span class="wp-block-formblox-form-input__label-content">Export</span></label></div>
<!-- /wp:formblox/form-input -->
<!-- wp:formblox/form-input {"type":"checkbox","name":"remove_personal_data","label":"Erase","inlineLabel":true} -->
<div class="wp-block-formblox-form-input"><label class="wp-block-formblox-form-input__label is-label-inline"><input class="wp-block-formblox-form-input__input" type="checkbox" name="remove_personal_data" aria-required="false"/><span class="wp-block-formblox-form-input__label-content">Erase</span></label></div>
<!-- /wp:formblox/form-input -->
<!-- wp:formblox/form-input {"type":"hidden","name":"wp-action","value":"wp_privacy_send_request"} -->
<input type="hidden" name="wp-action" value="wp_privacy_send_request"/>
<!-- /wp:formblox/form-input -->
<!-- wp:formblox/form-input {"type":"hidden","name":"wp-privacy-request","value":"1"} -->
<input type="hidden" name="wp-privacy-request" value="1"/>
<!-- /wp:formblox/form-input -->
<!-- wp:formblox/form-submit-button -->
<div class="wp-block-formblox-form-submit-button"><!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button {"tagName":"button","type":"submit"} --><div class="wp-block-button"><button type="submit" class="wp-block-button__link wp-element-button">Submit privacy request</button></div><!-- /wp:button --></div><!-- /wp:buttons --></div>
<!-- /wp:formblox/form-submit-button -->
</form>
<!-- /wp:formblox/form -->`;

test.describe( 'WP Forms Blocks', () => {
	test.afterEach( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
	} );

	test( 'registers, edits, saves, and reloads the complete form hierarchy', async ( {
		admin,
		editor,
		page,
	} ) => {
		await admin.createNewPost();
		const registration = await page.evaluate( () => {
			const templates = Object.fromEntries(
				[
					'formblox/form',
					'formblox/form-submit-button',
					'formblox/form-submission-notification',
				].map( ( name ) => [
					name,
					window.wp.blocks
						.getBlockType( name )
						.template?.map( ( block ) => block[ 0 ] ),
				] )
			);
			const variations = window.wp.blocks
				.getBlockVariations( 'formblox/form' )
				.map( ( { name, title, isDefault } ) => ( {
					name,
					title,
					isDefault,
				} ) );
			const defaultVariation = window.wp.data
				.select( 'core/blocks' )
				.getDefaultBlockVariation( 'formblox/form', 'inserter' );

			return {
				templates,
				variations,
				defaultVariation: {
					name: defaultVariation.name,
					innerBlocks: defaultVariation.innerBlocks.map(
						( block ) => block[ 0 ]
					),
				},
			};
		} );
		expect( registration.templates ).toEqual( {
			'formblox/form': [
				'formblox/form-submission-notification',
				'formblox/form-submission-notification',
				'formblox/form-input',
				'formblox/form-input',
				'formblox/form-input',
				'formblox/form-submit-button',
			],
			'formblox/form-submit-button': [ 'core/buttons' ],
			'formblox/form-submission-notification': [ 'core/paragraph' ],
		} );
		expect( registration.variations ).toEqual( [
			{
				name: 'contact-form',
				title: 'Contact Form',
				isDefault: true,
			},
			{
				name: 'comment-form',
				title: 'Comment Form',
				isDefault: false,
			},
			{
				name: 'wp-privacy-form',
				title: 'Privacy Request Form',
				isDefault: false,
			},
		] );
		expect( registration.defaultVariation ).toEqual( {
			name: 'contact-form',
			innerBlocks: registration.templates[ 'formblox/form' ],
		} );
		await page.evaluate( () => {
			const variation = window.wp.data
				.select( 'core/blocks' )
				.getDefaultBlockVariation( 'formblox/form', 'inserter' );
			const innerBlocks =
				window.wp.blocks.createBlocksFromInnerBlocksTemplate(
					variation.innerBlocks
				);
			window.wp.data
				.dispatch( 'core/block-editor' )
				.insertBlock(
					window.wp.blocks.createBlock(
						'formblox/form',
						variation.attributes,
						innerBlocks
					)
				);
		} );

		await expect(
			editor.canvas.locator( 'form.wp-block-formblox-form' )
		).toBeVisible();
		await expect(
			editor.canvas.locator( '.wp-block-formblox-form-input' )
		).toHaveCount( 3 );
		await expect(
			editor.canvas.locator( '.wp-block-formblox-form-submit-button' )
		).toHaveCount( 1 );
		const insertedForm = ( await editor.getBlocks() )[ 0 ];
		expect( insertedForm.name ).toBe( 'formblox/form' );
		expect(
			insertedForm.innerBlocks.map( ( block ) => block.name )
		).toEqual( [
			'formblox/form-submission-notification',
			'formblox/form-submission-notification',
			'formblox/form-input',
			'formblox/form-input',
			'formblox/form-input',
			'formblox/form-submit-button',
		] );
		expect(
			insertedForm.innerBlocks
				.slice( 0, 2 )
				.map( ( notification ) =>
					notification.innerBlocks.map( ( block ) => block.name )
				)
		).toEqual( [ [ 'core/paragraph' ], [ 'core/paragraph' ] ] );
		expect(
			insertedForm.innerBlocks.slice( 2, 5 ).map( ( input ) => ( {
				type: input.attributes.type,
				label: input.attributes.label,
				required: input.attributes.required,
			} ) )
		).toEqual( [
			{ type: 'text', label: 'Name', required: true },
			{ type: 'email', label: 'Email', required: true },
			{ type: 'textarea', label: 'Comment', required: true },
		] );
		const submitBlock = insertedForm.innerBlocks[ 5 ];
		expect( submitBlock.innerBlocks[ 0 ].name ).toBe( 'core/buttons' );
		expect( submitBlock.innerBlocks[ 0 ].innerBlocks[ 0 ] ).toMatchObject( {
			name: 'core/button',
			attributes: {
				text: 'Submit',
				tagName: 'button',
				type: 'submit',
			},
		} );
		await editor.setContent( formContent );
		await expect(
			editor.canvas.locator( '.wp-block-formblox-form-input' )
		).toHaveCount( 8 );
		await expect(
			editor.canvas.locator( '.wp-block-formblox-form-submit-button' )
		).toHaveCount( 1 );
		await editor.publishPost();

		await page.reload();
		await expect(
			editor.canvas.locator( 'form.wp-block-formblox-form' )
		).toBeVisible();
		await expect(
			page.getByText(
				'This block contains unexpected or invalid content.'
			)
		).toHaveCount( 0 );
		expect( await editor.getEditedPostContent() ).toContain(
			'<!-- wp:formblox/form-input {"type":"hidden"'
		);
	} );

	test( 'renders every field type and submits email successfully', async ( {
		page,
		requestUtils,
	} ) => {
		await requestUtils.rest( {
			path: '/wp-forms-blocks-test/v1/mail-mode',
			method: 'POST',
			data: { mode: 'success' },
		} );
		const post = await requestUtils.createPost( {
			title: 'Forms E2E',
			content: formContent,
			status: 'publish',
		} );

		await page.goto( post.link );
		const form = page.locator( 'form.wp-block-formblox-form' );
		await expect( form ).toHaveAttribute( 'action', '' );
		await expect( form ).toHaveAttribute(
			'data-formblox-submission-method',
			'email'
		);
		for ( const type of [
			'text',
			'email',
			'url',
			'tel',
			'number',
			'checkbox',
			'hidden',
		] ) {
			await expect(
				form.locator( `input[type="${ type }"]` )
			).toHaveCount( 1 );
		}
		await expect( form.locator( 'textarea[name="message"]' ) ).toHaveCount(
			1
		);

		await form.locator( '[name="full-name"]' ).fill( 'Ada Lovelace' );
		await form.locator( '[name="email"]' ).fill( 'ada@example.com' );
		await form.locator( '[name="website"]' ).fill( 'https://example.com' );
		await form.locator( '[name="phone"]' ).fill( '+30 210 000 0000' );
		await form.locator( '[name="guests"]' ).fill( '2' );
		await form
			.locator( '[name="message"]' )
			.fill( 'Hello from Playwright' );
		await form.locator( '[name="consent"]' ).check();
		await form.evaluate( ( element ) => {
			const forgedRecipient = document.createElement( 'input' );
			forgedRecipient.type = 'hidden';
			forgedRecipient.name = 'formAction';
			forgedRecipient.value = 'mailto:attacker-controlled@example.net';
			element.appendChild( forgedRecipient );
		} );
		await form.getByRole( 'button', { name: 'Submit' } ).click();

		await expect( page ).toHaveURL( /[?&]formblox-form-result=success/ );
		await expect( page.getByText( 'Submission succeeded' ) ).toBeVisible();
		await expect( page.getByText( 'Submission failed' ) ).toHaveCount( 0 );

		const mail = await requestUtils.rest( {
			path: '/wp-forms-blocks-test/v1/mail',
		} );
		expect( mail.to ).toBe( 'admin@example.com' );
		expect( mail.subject ).toBe( 'Form submission' );
		expect( mail.message ).toContain( 'full-name: Ada Lovelace</br>' );
		expect( mail.message ).toContain(
			'message: Hello from Playwright</br>'
		);
		expect( mail.message ).toContain( 'source: e2e</br>' );
	} );

	test( 'shows the error notification when the AJAX endpoint fails', async ( {
		page,
		requestUtils,
	} ) => {
		await page.route( '**/wp-admin/admin-ajax.php', async ( route ) => {
			await route.fulfill( {
				status: 500,
				contentType: 'application/json',
				body: JSON.stringify( { success: false } ),
			} );
		} );
		const post = await requestUtils.createPost( {
			title: 'Forms E2E failure',
			content: formContent,
			status: 'publish',
		} );

		await page.goto( post.link );
		const form = page.locator( 'form.wp-block-formblox-form' );
		await form.locator( '[name="full-name"]' ).fill( 'Grace Hopper' );
		await form.locator( '[name="email"]' ).fill( 'grace@example.com' );
		await form.locator( '[name="website"]' ).fill( 'https://example.com' );
		await form.locator( '[name="phone"]' ).fill( '+1 555 0100' );
		await form.locator( '[name="guests"]' ).fill( '1' );
		await form.locator( '[name="message"]' ).fill( 'Expected failure' );
		await form.getByRole( 'button', { name: 'Submit' } ).click();

		await expect( page ).toHaveURL( /[?&]formblox-form-result=error/ );
		await expect( page.getByText( 'Submission failed' ) ).toBeVisible();
		await expect( page.getByText( 'Submission succeeded' ) ).toHaveCount(
			0
		);
	} );

	test( 'leaves custom submissions to the browser with the configured method and action', async ( {
		page,
		requestUtils,
	} ) => {
		const customContent = formContent
			.replace(
				'<!-- wp:formblox/form -->',
				'<!-- wp:formblox/form {"submissionMethod":"custom","method":"post","action":"/custom-endpoint"} -->'
			)
			.replace( 'enctype="text/plain"', '' );
		const post = await requestUtils.createPost( {
			title: 'Custom form',
			content: customContent,
			status: 'publish',
		} );
		let submittedBody;
		await page.route( '**/custom-endpoint', async ( route ) => {
			submittedBody = route.request().postData();
			await route.fulfill( {
				status: 200,
				contentType: 'text/html',
				body: '<h1>Custom endpoint received the form</h1>',
			} );
		} );

		await page.goto( post.link );
		const form = page.locator( 'form.wp-block-formblox-form' );
		await expect( form ).toHaveAttribute( 'method', 'post' );
		await expect( form ).toHaveAttribute( 'action', '/custom-endpoint' );
		await form.locator( '[name="full-name"]' ).fill( 'Custom User' );
		await form.locator( '[name="email"]' ).fill( 'custom@example.com' );
		await form.locator( '[name="website"]' ).fill( 'https://example.com' );
		await form.locator( '[name="phone"]' ).fill( '+30 123' );
		await form.locator( '[name="guests"]' ).fill( '3' );
		await form.locator( '[name="message"]' ).fill( 'Custom payload' );
		await form.getByRole( 'button', { name: 'Submit' } ).click();

		await expect(
			page.getByRole( 'heading', {
				name: 'Custom endpoint received the form',
			} )
		).toBeVisible();
		const submitted = new URLSearchParams( submittedBody );
		expect( submitted.get( 'full-name' ) ).toBe( 'Custom User' );
		expect( submitted.get( 'message' ) ).toBe( 'Custom payload' );
		expect( submitted.get( 'source' ) ).toBe( 'e2e' );
	} );

	test( 'enforces logged-in and logged-out field visibility on the front end', async ( {
		browser,
		page,
		requestUtils,
	} ) => {
		const post = await requestUtils.createPost( {
			title: 'Visibility form',
			content: visibilityContent,
			status: 'publish',
		} );

		await page.goto( post.link );
		await expect( page.locator( '[name="always"]' ) ).toHaveCount( 1 );
		await expect( page.locator( '[name="members"]' ) ).toHaveCount( 1 );
		await expect( page.locator( '[name="visitors"]' ) ).toHaveCount( 0 );

		const anonymousContext = await browser.newContext( {
			storageState: { cookies: [], origins: [] },
		} );
		const anonymousPage = await anonymousContext.newPage();
		await anonymousPage.goto( post.link );
		await expect( anonymousPage.locator( '[name="always"]' ) ).toHaveCount(
			1
		);
		await expect( anonymousPage.locator( '[name="members"]' ) ).toHaveCount(
			0
		);
		await expect(
			anonymousPage.locator( '[name="visitors"]' )
		).toHaveCount( 1 );
		await anonymousContext.close();
	} );

	test( 'submits both WordPress privacy request types and displays success', async ( {
		page,
		requestUtils,
	} ) => {
		const post = await requestUtils.createPost( {
			title: 'Privacy form',
			content: privacyContent,
			status: 'publish',
		} );

		await page.goto( post.link );
		const form = page.locator( '#gdpr-form' );
		await form
			.locator( '[name="email"]' )
			.fill( 'privacy-e2e@example.com' );
		await form.locator( '[name="export_personal_data"]' ).check();
		await form.locator( '[name="remove_personal_data"]' ).check();
		await form
			.getByRole( 'button', { name: 'Submit privacy request' } )
			.click();

		await expect( page.getByText( 'Submission succeeded' ) ).toBeVisible();
		await expect( page.getByText( 'Submission failed' ) ).toHaveCount( 0 );
		const requests = await requestUtils.rest( {
			path: '/wp-forms-blocks-test/v1/privacy-requests',
		} );
		expect( requests ).toEqual(
			expect.arrayContaining( [
				expect.objectContaining( {
					action: 'export_personal_data',
					email: 'privacy-e2e@example.com',
				} ),
				expect.objectContaining( {
					action: 'remove_personal_data',
					email: 'privacy-e2e@example.com',
				} ),
			] )
		);
	} );

	test( 'submits the comment-form workflow with its injected post ID', async ( {
		page,
		requestUtils,
	} ) => {
		const commentContent = `<!-- wp:formblox/form {"submissionMethod":"custom","action":"{SITE_URL}/wp-comments-post.php","method":"post","anchor":"comment-form"} -->
<form class="wp-block-formblox-form" id="comment-form">
<!-- wp:formblox/form-input {"type":"textarea","name":"comment","label":"Comment","required":true} -->
<div class="wp-block-formblox-form-input"><label class="wp-block-formblox-form-input__label"><span class="wp-block-formblox-form-input__label-content">Comment</span><textarea class="wp-block-formblox-form-input__input" name="comment" required aria-required="true"></textarea></label></div>
<!-- /wp:formblox/form-input -->
<!-- wp:formblox/form-submit-button --><div class="wp-block-formblox-form-submit-button"><!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button {"tagName":"button","type":"submit"} --><div class="wp-block-button"><button type="submit" class="wp-block-button__link wp-element-button">Post comment</button></div><!-- /wp:button --></div><!-- /wp:buttons --></div><!-- /wp:formblox/form-submit-button -->
</form>
<!-- /wp:formblox/form -->`;
		const post = await requestUtils.rest( {
			path: '/wp/v2/posts',
			method: 'POST',
			data: {
				title: 'Comment form',
				content: commentContent,
				status: 'publish',
				comment_status: 'open',
			},
		} );

		await page.goto( post.link );
		const form = page.locator( '#comment-form' );
		await expect( form.locator( '[name="comment_post_ID"]' ) ).toHaveValue(
			String( post.id )
		);
		await form
			.locator( '[name="comment"]' )
			.fill( 'Comment from Playwright' );
		await form.getByRole( 'button', { name: 'Post comment' } ).click();
		await page.waitForURL( /#comment-/ );

		const comments = await requestUtils.rest( {
			path: '/wp/v2/comments',
			params: { post: post.id, context: 'edit' },
		} );
		expect( comments ).toHaveLength( 1 );
		expect( comments[ 0 ].content.raw ).toBe( 'Comment from Playwright' );
	} );
} );
