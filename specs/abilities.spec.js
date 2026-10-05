const { createHash } = require( 'crypto' );
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

const run = ( requestUtils, name, input, method = 'POST' ) =>
	requestUtils.rest( {
		path: `/wp-abilities/v1/abilities/formblox/${ name }/run`,
		method,
		...( method === 'GET' || method === 'DELETE'
			? {
					params: Object.fromEntries(
						Object.entries( input ).flatMap( ( [ key, value ] ) =>
							Array.isArray( value )
								? value.map( ( item, index ) => [
										`input[${ key }][${ index }]`,
										item,
								  ] )
								: [ [ `input[${ key }]`, value ] ]
						)
					),
			  }
			: { data: { input } } ),
	} );

test( 'agent-created and edited forms reload in Gutenberg and submit normally', async ( {
	admin,
	editor,
	page,
	requestUtils,
} ) => {
	await requestUtils.rest( {
		path: '/wp-forms-blocks-test/v1/mail-mode',
		method: 'POST',
		data: { mode: 'success' },
	} );
	const original =
		'<!-- wp:paragraph --><p>Keep this page content.</p><!-- /wp:paragraph -->';
	const post = await requestUtils.createPost( {
		title: 'Forms created by an agent',
		status: 'draft',
		content: original,
	} );
	const created = await run( requestUtils, 'create-form', {
		post_id: post.id,
		content_version: createHash( 'sha256' )
			.update( original )
			.digest( 'hex' ),
		request_id: 'browser-contact-create',
		definition: { template: 'contact' },
	} );
	const updated = await run( requestUtils, 'update-form', {
		post_id: post.id,
		path: created.path,
		content_version: created.content_version,
		operations: [
			{
				operation: 'update',
				path: [ 2 ],
				attributes: { label: 'Full name', placeholder: 'Your name' },
			},
			{
				operation: 'insert',
				parent_path: [],
				index: 5,
				block: {
					name: 'formblox/form-input',
					attributes: {
						type: 'tel',
						name: 'phone',
						label: 'Phone',
						required: false,
					},
				},
			},
			{
				operation: 'update',
				path: [ 6, 0, 0 ],
				attributes: { text: 'Send request' },
			},
		],
	} );
	expect( updated.live ).toBe( false );
	await admin.visitAdminPage( 'post.php', `post=${ post.id }&action=edit` );
	await expect(
		editor.canvas.locator( 'form.wp-block-formblox-form' )
	).toBeVisible();
	await expect(
		page.getByText( 'This block contains unexpected or invalid content.' )
	).toHaveCount( 0 );
	await expect(
		editor.canvas.getByText( 'Full name', { exact: true } )
	).toBeVisible();
	const welcome = page.getByRole( 'dialog', {
		name: 'Welcome to the editor',
	} );
	if ( await welcome.isVisible() ) {
		await welcome
			.getByRole( 'button', { name: 'Close', exact: true } )
			.click();
	}
	await editor.publishPost();
	await page.reload();
	await expect(
		page.getByText( 'This block contains unexpected or invalid content.' )
	).toHaveCount( 0 );
	const saved = await requestUtils.rest( {
		path: `/wp/v2/posts/${ post.id }`,
		params: { context: 'edit' },
	} );
	expect( saved.content.raw ).toContain( 'Keep this page content.' );
	await page.goto( saved.link );
	const form = page.locator( 'form.wp-block-formblox-form' );
	await form.locator( '[name="author"]' ).fill( 'Agent visitor' );
	await form.locator( '[name="email"]' ).fill( 'visitor@example.com' );
	await form
		.locator( '[name="comment"]' )
		.fill( 'Created through native abilities.' );
	await form.locator( '[name="phone"]' ).fill( '123456789' );
	await form.getByRole( 'button', { name: 'Send request' } ).click();
	await expect(
		page.getByText( 'Your form has been submitted successfully', {
			exact: true,
		} )
	).toBeVisible();
	await requestUtils.rest( {
		path: `/wp/v2/posts/${ post.id }`,
		method: 'DELETE',
		params: { force: true },
	} );
} );

for ( const template of [ 'comment', 'privacy', 'custom' ] ) {
	test( `agent-generated ${ template } template remains editable`, async ( {
		admin,
		editor,
		page,
		requestUtils,
	} ) => {
		const definition = {
			template,
			...( template === 'custom'
				? { attributes: { action: 'https://example.com/submit' } }
				: {} ),
		};
		const post = await requestUtils.createPost( {
			title: `Agent ${ template }`,
			status: 'draft',
			content: '',
		} );
		await run( requestUtils, 'create-form', {
			post_id: post.id,
			content_version: createHash( 'sha256' )
				.update( '' )
				.digest( 'hex' ),
			request_id: `browser-${ template }-create`,
			definition,
		} );
		await admin.visitAdminPage(
			'post.php',
			`post=${ post.id }&action=edit`
		);
		await expect(
			editor.canvas.locator( 'form.wp-block-formblox-form' )
		).toBeVisible();
		await expect(
			page.getByText(
				'This block contains unexpected or invalid content.'
			)
		).toHaveCount( 0 );
		await requestUtils.rest( {
			path: `/wp/v2/posts/${ post.id }`,
			method: 'DELETE',
			params: { force: true },
		} );
	} );
}

test( 'agents preserve rich labels and change Group tags on editor-saved forms', async ( {
	admin,
	editor,
	page,
	requestUtils,
} ) => {
	const post = await requestUtils.createPost( {
		title: 'Native saved form edits',
		status: 'draft',
		content: '',
	} );
	const created = await run( requestUtils, 'create-form', {
		post_id: post.id,
		content_version: createHash( 'sha256' ).update( '' ).digest( 'hex' ),
		request_id: 'native-save-regression',
		definition: {
			blocks: [
				{
					name: 'core/group',
					innerBlocks: [
						{
							name: 'formblox/form-input',
							attributes: {
								name: 'area',
								label: '<span class="custom-format">Area (m<sup>2</sup>)</span>',
								required: true,
							},
						},
					],
				},
				{ name: 'formblox/form-submit-button' },
			],
		},
	} );
	await admin.visitAdminPage( 'post.php', `post=${ post.id }&action=edit` );
	await expect(
		editor.canvas.locator( 'form.wp-block-formblox-form' )
	).toBeVisible();
	const nativeContent = await page.evaluate( () =>
		window.wp.blocks.serialize(
			window.wp.data.select( 'core/block-editor' ).getBlocks()
		)
	);
	expect( nativeContent ).toContain( '\n<div class="wp-block-group">' );
	await requestUtils.rest( {
		path: `/wp/v2/posts/${ post.id }`,
		method: 'POST',
		data: { content: nativeContent },
	} );
	const record = await run(
		requestUtils,
		'get-form',
		{ post_id: post.id, path: created.path },
		'GET'
	);
	const edited = await run( requestUtils, 'update-form', {
		post_id: post.id,
		path: record.path,
		content_version: record.content_version,
		operations: [
			{
				operation: 'update',
				path: [ 0 ],
				attributes: { tagName: 'section' },
			},
			{
				operation: 'update',
				path: [ 0, 0 ],
				attributes: { required: false },
			},
		],
	} );
	expect( edited.post_status ).toBe( 'draft' );
	expect( edited.live ).toBe( false );
	await page.reload();
	await expect(
		editor.canvas.locator( 'section.wp-block-group' )
	).toBeVisible();
	await expect( editor.canvas.locator( '.custom-format sup' ) ).toHaveText(
		'2'
	);
	await expect(
		page.getByText( 'This block contains unexpected or invalid content.' )
	).toHaveCount( 0 );
	const reread = await run(
		requestUtils,
		'get-form',
		{ post_id: post.id, path: record.path },
		'GET'
	);
	expect( reread.definition.innerBlocks[ 0 ].attributes.tagName ).toBe(
		'section'
	);
	expect(
		reread.definition.innerBlocks[ 0 ].innerBlocks[ 0 ].attributes
	).toMatchObject( {
		label: '<span class="custom-format">Area (m<sup>2</sup>)</span>',
		required: false,
	} );
	await requestUtils.rest( {
		path: `/wp/v2/posts/${ post.id }`,
		method: 'DELETE',
		params: { force: true },
	} );
} );
