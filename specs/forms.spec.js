const {
	Admin,
	Editor,
	PageUtils,
	RequestUtils,
	test,
	expect,
} = require( '@wordpress/e2e-test-utils-playwright' );

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

const authorFormBlock = {
	name: 'formblox/form',
	attributes: {
		anchor: 'author-contract-form',
		className: 'author-contract-form',
		style: {
			spacing: {
				padding: {
					top: '12px',
					right: '14px',
					bottom: '16px',
					left: '18px',
				},
			},
		},
	},
	innerBlocks: [
		{
			name: 'formblox/form-submission-notification',
			innerBlocks: [
				{
					name: 'core/paragraph',
					attributes: { content: 'Author submission succeeded' },
				},
			],
		},
		{
			name: 'formblox/form-submission-notification',
			attributes: { type: 'error' },
			innerBlocks: [
				{
					name: 'core/paragraph',
					attributes: { content: 'Author submission failed' },
				},
			],
		},
		{
			name: 'formblox/form-input',
			attributes: {
				type: 'text',
				name: 'full-name',
				label: 'Full name',
				placeholder: 'Your full name',
				required: true,
				style: { border: { radius: '7px' } },
			},
		},
		{
			name: 'formblox/form-input',
			attributes: {
				type: 'email',
				name: 'email',
				label: 'Email address',
				placeholder: 'you@example.com',
				required: true,
			},
		},
		{
			name: 'formblox/form-input',
			attributes: {
				type: 'textarea',
				name: 'message',
				label: 'Message',
				placeholder: 'Tell us something',
				required: true,
				style: { border: { radius: '9px' } },
			},
		},
		{
			name: 'formblox/form-input',
			attributes: {
				type: 'checkbox',
				name: 'consent',
				label: 'I consent',
				inlineLabel: true,
				required: true,
			},
		},
		{
			name: 'formblox/form-input',
			attributes: {
				type: 'hidden',
				name: 'source',
				value: 'author-e2e',
			},
		},
		{
			name: 'formblox/form-submit-button',
			innerBlocks: [
				{
					name: 'core/buttons',
					innerBlocks: [
						{
							name: 'core/button',
							attributes: {
								tagName: 'button',
								type: 'submit',
								text: 'Send author form',
							},
						},
					],
				},
			],
		},
	],
};

const getPersistedFormContract = ( content ) => {
	const document = new window.DOMParser().parseFromString(
		content,
		'text/html'
	);
	const form = document.querySelector( 'form' );
	const attributes = ( element ) =>
		element
			? Object.fromEntries(
					element
						.getAttributeNames()
						.map( ( name ) => [
							name,
							element.getAttribute( name ),
						] )
			  )
			: null;

	return {
		form: attributes( form ),
		labels: [ ...document.querySelectorAll( 'label' ) ].map(
			( label ) => ( {
				attributes: attributes( label ),
				text: label.textContent.trim(),
			} )
		),
		fields: [ ...document.querySelectorAll( 'input, textarea' ) ].map(
			( field ) => ( {
				tagName: field.tagName.toLowerCase(),
				attributes: attributes( field ),
			} )
		),
		notifications: [
			...document.querySelectorAll(
				'.wp-block-formblox-form-submission-notification'
			),
		].map( ( notification ) => ( {
			attributes: attributes( notification ),
			text: notification.textContent.trim(),
		} ) ),
		submitButton: attributes( document.querySelector( 'button' ) ),
		submitButtonText: document
			.querySelector( 'button' )
			?.textContent.trim(),
	};
};

test.describe( 'WP Forms Blocks', () => {
	test.beforeEach( async ( { requestUtils } ) => {
		await requestUtils.rest( {
			path: '/wp-forms-blocks-test/v1/mail-mode',
			method: 'POST',
			data: { mode: 'success' },
		} );
	} );

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

	test( 'preserves and submits the form created by an Author through the complete WordPress lifecycle', async ( {
		baseURL,
		browser,
		browserName,
		requestUtils,
	} ) => {
		const unique = `${ Date.now() }-${ Math.random()
			.toString( 36 )
			.slice( 2 ) }`;
		const username = `form_author_${ unique }`;
		const password = 'author-password';
		const author = await requestUtils.rest( {
			path: '/wp/v2/users',
			method: 'POST',
			data: {
				username,
				name: 'Form Author',
				email: `${ username }@example.com`,
				password,
				roles: [ 'author' ],
			},
		} );
		const authorRequestUtils = await RequestUtils.setup( {
			baseURL,
			user: { username, password },
		} );
		let authorContext;
		let visitorContext;

		try {
			const authorState = await authorRequestUtils.setupRest();
			const currentUser = await authorRequestUtils.rest( {
				path: '/wp/v2/users/me',
				params: { context: 'edit' },
			} );
			expect( currentUser.roles ).toEqual( [ 'author' ] );
			expect( currentUser.capabilities?.unfiltered_html ).not.toBe(
				true
			);

			authorContext = await browser.newContext( {
				baseURL,
				storageState: {
					cookies: authorState.cookies,
					origins: [],
				},
			} );
			const authorPage = await authorContext.newPage();
			const authorEditor = new Editor( { page: authorPage } );
			const authorAdmin = new Admin( {
				page: authorPage,
				pageUtils: new PageUtils( {
					page: authorPage,
					browserName,
				} ),
				editor: authorEditor,
			} );

			await authorAdmin.createNewPost( {
				title: 'Author-created KSES contract form',
			} );
			await authorPage
				.getByRole( 'button', { name: 'Block Inserter' } )
				.click();
			await authorPage.getByPlaceholder( 'Search' ).fill( 'Form' );
			await authorPage
				.getByRole( 'option', { name: 'Contact Form', exact: true } )
				.click();
			await expect(
				authorEditor.canvas.locator( 'form.wp-block-formblox-form' )
			).toBeVisible();
			expect( ( await authorEditor.getBlocks() )[ 0 ].name ).toBe(
				'formblox/form'
			);

			await authorPage.evaluate( ( formBlock ) => {
				const createBlock = ( representation ) =>
					window.wp.blocks.createBlock(
						representation.name,
						representation.attributes || {},
						( representation.innerBlocks || [] ).map( createBlock )
					);
				const form = window.wp.data
					.select( 'core/block-editor' )
					.getBlocks()[ 0 ];
				const dispatcher =
					window.wp.data.dispatch( 'core/block-editor' );

				dispatcher.updateBlockAttributes(
					form.clientId,
					formBlock.attributes
				);
				dispatcher.replaceInnerBlocks(
					form.clientId,
					formBlock.innerBlocks.map( createBlock )
				);
			}, authorFormBlock );

			const createdForm = ( await authorEditor.getBlocks() )[ 0 ];
			expect( createdForm ).toMatchObject( {
				name: 'formblox/form',
				attributes: {
					anchor: 'author-contract-form',
					className: 'author-contract-form',
				},
			} );
			expect(
				createdForm.innerBlocks.map( ( block ) => block.name )
			).toEqual( [
				'formblox/form-submission-notification',
				'formblox/form-submission-notification',
				'formblox/form-input',
				'formblox/form-input',
				'formblox/form-input',
				'formblox/form-input',
				'formblox/form-input',
				'formblox/form-submit-button',
			] );

			const postId = await authorEditor.publishPost();
			expect( postId ).toEqual( expect.any( Number ) );
			const savedPost = await requestUtils.rest( {
				path: `/wp/v2/posts/${ postId }`,
				params: { context: 'edit' },
			} );
			const persisted = await authorPage.evaluate(
				getPersistedFormContract,
				savedPost.content.raw
			);

			expect( persisted.form ).toMatchObject( {
				id: 'author-contract-form',
				enctype: 'text/plain',
			} );
			expect( persisted.form.class ).toEqual( expect.any( String ) );
			expect( persisted.form.class.split( /\s+/ ) ).toEqual(
				expect.arrayContaining( [
					'wp-block-formblox-form',
					'author-contract-form',
				] )
			);
			expect( persisted.form.style ).toContain( 'padding-top:12px' );
			expect( persisted.form.style ).toContain( 'padding-right:14px' );
			expect( persisted.form.style ).toContain( 'padding-bottom:16px' );
			expect( persisted.form.style ).toContain( 'padding-left:18px' );
			expect( persisted.labels ).toHaveLength( 4 );
			expect( persisted.labels.map( ( label ) => label.text ) ).toEqual( [
				'Full name',
				'Email address',
				'Message',
				'I consent',
			] );
			expect(
				persisted.labels[ 3 ].attributes.class.split( /\s+/ )
			).toEqual(
				expect.arrayContaining( [
					'wp-block-formblox-form-input__label',
					'is-label-inline',
				] )
			);

			const fieldsByName = Object.fromEntries(
				persisted.fields.map( ( field ) => [
					field.attributes.name,
					field,
				] )
			);
			expect( Object.keys( fieldsByName ) ).toEqual( [
				'full-name',
				'email',
				'message',
				'consent',
				'source',
			] );
			expect( fieldsByName[ 'full-name' ] ).toMatchObject( {
				tagName: 'input',
				attributes: {
					type: 'text',
					name: 'full-name',
					placeholder: 'Your full name',
					required: '',
					'aria-required': 'true',
				},
			} );
			expect( fieldsByName[ 'full-name' ].attributes.class ).toContain(
				'wp-block-formblox-form-input__input'
			);
			expect( fieldsByName[ 'full-name' ].attributes.style ).toContain(
				'border-radius:7px'
			);
			expect( fieldsByName.email ).toMatchObject( {
				tagName: 'input',
				attributes: {
					type: 'email',
					name: 'email',
					placeholder: 'you@example.com',
					required: '',
					'aria-required': 'true',
				},
			} );
			expect( fieldsByName.message ).toMatchObject( {
				tagName: 'textarea',
				attributes: {
					name: 'message',
					placeholder: 'Tell us something',
					required: '',
					'aria-required': 'true',
				},
			} );
			expect( fieldsByName.message.attributes.style ).toContain(
				'border-radius:9px'
			);
			expect( fieldsByName.consent ).toMatchObject( {
				tagName: 'input',
				attributes: {
					type: 'checkbox',
					name: 'consent',
					required: '',
					'aria-required': 'true',
				},
			} );
			expect( fieldsByName.source ).toEqual( {
				tagName: 'input',
				attributes: {
					type: 'hidden',
					name: 'source',
					value: 'author-e2e',
				},
			} );
			expect( persisted.notifications ).toEqual( [
				expect.objectContaining( {
					attributes: expect.objectContaining( {
						class: expect.stringContaining(
							'formblox-form-notification-type-success'
						),
					} ),
					text: 'Author submission succeeded',
				} ),
				expect.objectContaining( {
					attributes: expect.objectContaining( {
						class: expect.stringContaining(
							'formblox-form-notification-type-error'
						),
					} ),
					text: 'Author submission failed',
				} ),
			] );
			expect( persisted.submitButton ).toMatchObject( {
				type: 'submit',
				class: expect.stringContaining( 'wp-block-button__link' ),
			} );
			expect( persisted.submitButtonText ).toBe( 'Send author form' );

			await authorPage.reload();
			await expect(
				authorPage.getByText(
					'This block contains unexpected or invalid content.'
				)
			).toHaveCount( 0 );
			const reloadedForm = ( await authorEditor.getBlocks() )[ 0 ];
			expect( reloadedForm ).toMatchObject( {
				name: 'formblox/form',
				attributes: {
					anchor: 'author-contract-form',
					className: 'author-contract-form',
				},
			} );
			expect( reloadedForm.innerBlocks ).toHaveLength( 8 );

			visitorContext = await browser.newContext( {
				baseURL,
				storageState: { cookies: [], origins: [] },
			} );
			const visitorPage = await visitorContext.newPage();
			await visitorPage.goto( savedPost.link );
			const form = visitorPage.locator( '#author-contract-form' );
			await expect( form ).toBeVisible();
			await expect( form ).toHaveClass( /\bwp-block-formblox-form\b/ );
			await expect( form ).toHaveClass( /\bauthor-contract-form\b/ );
			await expect( form ).toHaveAttribute( 'enctype', 'text/plain' );
			await expect( form ).toHaveAttribute( 'method', 'post' );
			await expect( form ).toHaveAttribute( 'action', '' );
			await expect( form ).toHaveAttribute(
				'data-formblox-submission-method',
				'email'
			);
			await expect( form ).toHaveCSS( 'padding-top', '12px' );
			await expect( form ).toHaveCSS( 'padding-right', '14px' );
			await expect( form ).toHaveCSS( 'padding-bottom', '16px' );
			await expect( form ).toHaveCSS( 'padding-left', '18px' );
			await expect( form.locator( 'label' ) ).toHaveCount( 4 );
			await expect(
				form.locator( '[name="full-name"]' )
			).toHaveAttribute( 'placeholder', 'Your full name' );
			await expect( form.locator( '[name="full-name"]' ) ).toHaveCSS(
				'border-radius',
				'7px'
			);
			await expect(
				form.locator( 'textarea[name="message"]' )
			).toHaveCSS( 'border-radius', '9px' );
			await expect( form.locator( '[name="source"]' ) ).toHaveValue(
				'author-e2e'
			);

			await form.locator( '[name="full-name"]' ).fill( 'Author User' );
			await form.locator( '[name="email"]' ).fill( 'author@example.com' );
			await form
				.locator( '[name="message"]' )
				.fill( 'Saved, rendered, and submitted' );
			await form.locator( '[name="consent"]' ).check();
			const sourceUrl = visitorPage.url();
			await form
				.getByRole( 'button', { name: 'Send author form' } )
				.click();

			await expect( visitorPage ).toHaveURL(
				/[?&]formblox-form-result=success/
			);
			const successNotice = visitorPage
				.getByText( 'Author submission succeeded' )
				.locator( '..' );
			await expect( successNotice ).toBeVisible();
			await expect( successNotice ).toHaveAttribute( 'role', 'status' );
			await expect( successNotice ).toBeFocused();
			await expect(
				visitorPage.getByText( 'Author submission failed' )
			).toHaveCount( 0 );

			const mail = await requestUtils.rest( {
				path: '/wp-forms-blocks-test/v1/mail',
			} );
			expect( mail.to ).toBe( 'admin@example.com' );
			expect( mail.subject ).toBe( 'Form submission' );
			expect( mail.message ).toBe(
				`Form submission from WP Forms Blocks E2E
Source: ${ sourceUrl }

full-name: Author User
email: author@example.com
message: Saved, rendered, and submitted
consent: on
source: author-e2e
`
			);
		} finally {
			await visitorContext?.close();
			await authorContext?.close();
			await authorRequestUtils.request.dispose();
			await requestUtils.rest( {
				path: `/wp/v2/users/${ author.id }`,
				method: 'DELETE',
				params: { force: true, reassign: 1 },
			} );
		}
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
		await expect(
			page.locator(
				'link[rel="stylesheet"][href*="/wp-forms-blocks/build/style.css"]'
			)
		).toHaveCount( 1 );
		const submitWrapper = form.locator(
			'.wp-block-formblox-form-submit-button'
		);
		expect(
			await submitWrapper.evaluate( ( element ) =>
				Number.parseFloat(
					window.getComputedStyle( element ).marginBottom
				)
			)
		).toBeGreaterThan( 0 );
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
		const requiredLabel = form.locator(
			'.wp-block-formblox-form-input:has([name="full-name"]) .wp-block-formblox-form-input__label-content'
		);
		await expect( requiredLabel ).toHaveAttribute(
			'data-formblox-required-label',
			'required'
		);
		expect(
			await requiredLabel.evaluate(
				( element ) =>
					window.getComputedStyle( element, '::after' ).content
			)
		).toContain( 'required' );

		await form.locator( '[name="full-name"]' ).fill( 'Ada Lovelace' );
		await form.locator( '[name="email"]' ).fill( 'ada@example.com' );
		await form.locator( '[name="website"]' ).fill( 'https://example.com' );
		await form.locator( '[name="phone"]' ).fill( '+30 210 000 0000' );
		await form.locator( '[name="guests"]' ).fill( '2' );
		await form
			.locator( '[name="message"]' )
			.fill( 'Hello <b>from</b>\nPlaywright' );
		await form.locator( '[name="consent"]' ).check();
		const sourceUrl = page.url();
		await form.evaluate( ( element ) => {
			for ( const [ name, value ] of [
				[ 'formAction', 'mailto:attacker-controlled@example.net' ],
				[ 'topic', 'music' ],
				[ 'topic', 'art' ],
				[ 'language[]', 'Greek' ],
				[ 'language[]', 'English' ],
				[ 'Όνομα', 'Αριστάθης' ],
			] ) {
				const input = document.createElement( 'input' );
				input.type = 'hidden';
				input.name = name;
				input.value = value;
				element.appendChild( input );
			}
		} );

		let releaseSubmission;
		const submissionRelease = new Promise( ( resolve ) => {
			releaseSubmission = resolve;
		} );
		let markSubmissionStarted;
		const submissionStarted = new Promise( ( resolve ) => {
			markSubmissionStarted = resolve;
		} );
		let submissionRequests = 0;
		await page.route( '**/wp-admin/admin-ajax.php', async ( route ) => {
			const request = route.request();
			if (
				'POST' === request.method() &&
				request
					.postData()
					?.includes( 'action=formblox_form_email_submit' )
			) {
				submissionRequests++;
				markSubmissionStarted();
				await submissionRelease;
			}
			await route.continue();
		} );

		const submitButton = form.getByRole( 'button', { name: 'Submit' } );
		await submitButton.click();
		await submissionStarted;
		await expect( form ).toHaveAttribute( 'aria-busy', 'true' );
		await expect( submitButton ).toBeDisabled();
		const submittingStatus = page.getByText( 'Submitting…' );
		await expect( submittingStatus ).toBeVisible();
		await expect( submittingStatus ).toHaveAttribute( 'role', 'status' );
		await expect( submittingStatus ).toHaveAttribute(
			'aria-live',
			'polite'
		);
		await expect( submittingStatus ).toHaveAttribute(
			'aria-atomic',
			'true'
		);
		await form.evaluate( ( element ) => {
			element.dispatchEvent(
				new Event( 'submit', { bubbles: true, cancelable: true } )
			);
		} );
		await page.waitForTimeout( 50 );
		expect( submissionRequests ).toBe( 1 );
		releaseSubmission();

		await expect( page ).toHaveURL( /[?&]formblox-form-result=success/ );
		const successNotice = page
			.getByText( 'Submission succeeded' )
			.locator( '..' );
		await expect( successNotice ).toBeVisible();
		await expect( successNotice ).toHaveAttribute( 'role', 'status' );
		await expect( successNotice ).toHaveAttribute( 'aria-live', 'polite' );
		await expect( successNotice ).toHaveAttribute( 'aria-atomic', 'true' );
		await expect( successNotice ).toHaveAttribute( 'tabindex', '-1' );
		await expect( successNotice ).toBeFocused();
		await expect( page.getByText( 'Submission failed' ) ).toHaveCount( 0 );

		const mail = await requestUtils.rest( {
			path: '/wp-forms-blocks-test/v1/mail',
		} );
		expect( mail.to ).toBe( 'admin@example.com' );
		expect( mail.subject ).toBe( 'Form submission' );
		expect( mail.headers ).toEqual( [
			'Content-Type: text/plain; charset=UTF-8',
		] );
		expect( mail.message ).toBe(
			`Form submission from WP Forms Blocks E2E
Source: ${ sourceUrl }

full-name: Ada Lovelace
email: ada@example.com
website: https://example.com
phone: +30 210 000 0000
guests: 2
message: Hello from
Playwright
consent: on
source: e2e
topic: music, art
language: Greek, English
Όνομα: Αριστάθης
`
		);
	} );

	test( 'shows the error notification when the AJAX endpoint fails', async ( {
		page,
		requestUtils,
	} ) => {
		await requestUtils.rest( {
			path: '/wp-forms-blocks-test/v1/mail-mode',
			method: 'POST',
			data: { mode: 'failure' },
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
		const responsePromise = page.waitForResponse(
			( response ) =>
				response.url().includes( '/wp-admin/admin-ajax.php' ) &&
				'POST' === response.request().method()
		);
		await form.getByRole( 'button', { name: 'Submit' } ).click();
		const response = await responsePromise;
		expect( response.status() ).toBe( 500 );

		await expect( page ).toHaveURL( /[?&]formblox-form-result=error/ );
		const errorNotice = page
			.getByText( 'Submission failed' )
			.locator( '..' );
		await expect( errorNotice ).toBeVisible();
		await expect( errorNotice ).toHaveAttribute( 'role', 'alert' );
		await expect( errorNotice ).toHaveAttribute( 'aria-live', 'assertive' );
		await expect( errorNotice ).toHaveAttribute( 'aria-atomic', 'true' );
		await expect( errorNotice ).toHaveAttribute( 'tabindex', '-1' );
		await expect( errorNotice ).toBeFocused();
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

	test( 'reports a privacy email failure and allows a successful retry', async ( {
		page,
		requestUtils,
	} ) => {
		await requestUtils.rest( {
			path: '/wp-forms-blocks-test/v1/mail-mode',
			method: 'POST',
			data: { mode: 'failure' },
		} );
		const post = await requestUtils.createPost( {
			title: 'Privacy form mail failure',
			content: privacyContent,
			status: 'publish',
		} );
		const email = 'privacy-failure-e2e@example.com';

		await page.goto( post.link );
		let form = page.locator( '#gdpr-form' );
		await form.locator( '[name="email"]' ).fill( email );
		await form.locator( '[name="export_personal_data"]' ).check();
		await form
			.getByRole( 'button', { name: 'Submit privacy request' } )
			.click();

		await expect( page.getByText( 'Submission failed' ) ).toBeVisible();
		await expect( page.getByText( 'Submission succeeded' ) ).toHaveCount(
			0
		);
		let requests = await requestUtils.rest( {
			path: '/wp-forms-blocks-test/v1/privacy-requests',
		} );
		expect(
			requests.filter( ( request ) => request.email === email )
		).toEqual( [
			expect.objectContaining( {
				action: 'export_personal_data',
				status: 'request-failed',
			} ),
		] );

		await requestUtils.rest( {
			path: '/wp-forms-blocks-test/v1/mail-mode',
			method: 'POST',
			data: { mode: 'success' },
		} );
		form = page.locator( '#gdpr-form' );
		await form.locator( '[name="email"]' ).fill( email );
		await form.locator( '[name="export_personal_data"]' ).check();
		await form
			.getByRole( 'button', { name: 'Submit privacy request' } )
			.click();

		await expect( page.getByText( 'Submission succeeded' ) ).toBeVisible();
		await expect( page.getByText( 'Submission failed' ) ).toHaveCount( 0 );
		requests = await requestUtils.rest( {
			path: '/wp-forms-blocks-test/v1/privacy-requests',
		} );
		expect(
			requests
				.filter( ( request ) => request.email === email )
				.map( ( request ) => request.status )
				.sort()
		).toEqual( [ 'request-failed', 'request-pending' ] );
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
