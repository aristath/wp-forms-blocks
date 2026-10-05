import path from 'path';
import { spawnSync } from 'child_process';
import { registerCoreBlocks } from '@wordpress/block-library';
import { parse, serialize } from '@wordpress/blocks';
import { init as initForm } from '../../src/form';
import { init as initInput } from '../../src/form-input';
import { init as initButton } from '../../src/form-submit-button';
import { init as initNotification } from '../../src/form-submission-notification';

const codec = ( operation, ...args ) => {
	const process = spawnSync(
		'php',
		[ path.join( __dirname, '../php/ability-codec.php' ) ],
		{ input: JSON.stringify( { operation, args } ), encoding: 'utf8' }
	);
	if ( process.status !== 0 ) {
		throw new Error( process.stderr );
	}
	return JSON.parse( process.stdout );
};

const assertValid = ( markup ) => {
	const visit = ( blocks ) => {
		blocks.forEach( ( block ) => {
			expect( block.isValid ).toBe( true );
			visit( block.innerBlocks );
		} );
	};
	const blocks = parse( markup );
	visit( blocks );
	expect( serialize( parse( serialize( blocks ) ) ) ).toBe(
		serialize( blocks )
	);
	return blocks;
};

beforeAll( () => {
	registerCoreBlocks();
	initForm();
	initInput();
	initButton();
	initNotification();
} );

test.each( [ 'contact', 'comment', 'privacy', 'custom' ] )(
	'PHP-generated %s forms remain valid in Gutenberg',
	( template ) => {
		const { result } = codec( 'create', {
			template,
			...( template === 'custom'
				? { attributes: { action: 'https://example.com/submit' } }
				: {} ),
		} );
		expect( result ).toEqual( expect.any( String ) );
		assertValid( result );
	}
);

test( 'all field types and form styling survive PHP creation and editing', () => {
	const fields = [
		'text',
		'textarea',
		'checkbox',
		'email',
		'url',
		'tel',
		'number',
		'hidden',
	].map( ( type ) => ( {
		name: 'formblox/form-input',
		attributes: {
			type,
			name: type,
			label: 'A <strong>label</strong>',
			required: type !== 'hidden',
			placeholder: 'Enter a value',
			...( type === 'hidden' ? { value: 'source' } : {} ),
			style: {
				spacing: { margin: { top: '12px' } },
				border: { radius: '4px' },
			},
		},
	} ) );
	const { result: markup } = codec( 'create', {
		attributes: {
			anchor: 'test-form',
			backgroundColor: 'base',
			style: {
				spacing: { padding: { top: '20px' } },
				typography: { fontSize: '18px' },
			},
		},
		blocks: [ ...fields, { name: 'formblox/form-submit-button' } ],
	} );
	assertValid( markup );
	const { result: edited } = codec( 'edit', markup, [
		{
			operation: 'update',
			path: [ 0 ],
			attributes: {
				label: 'Changed',
				required: false,
				type: 'checkbox',
				inlineLabel: true,
			},
		},
		{ operation: 'move', path: [ 1 ], parent_path: [], index: 5 },
	] );
	const blocks = assertValid( edited );
	expect(
		JSON.parse( JSON.stringify( blocks[ 0 ].innerBlocks[ 0 ].attributes ) )
	).toMatchObject( {
		label: 'Changed',
		required: false,
		name: 'text',
		type: 'checkbox',
	} );
} );

test( 'targeted edits preserve custom content and styled nested layouts', () => {
	const { result: markup } = codec( 'create', {
		blocks: [
			{
				name: 'core/group',
				attributes: {
					className: 'custom-layout',
					layout: { type: 'constrained' },
				},
				innerBlocks: [
					{
						name: 'formblox/form-input',
						attributes: { label: 'Name', name: 'name' },
					},
				],
			},
			{ name: 'formblox/form-submit-button' },
		],
	} );
	const custom =
		'<!-- wp:html --><aside data-custom="untouched">Custom content</aside><!-- /wp:html -->';
	const original = markup.replace( '</form>', custom + '</form>' );
	const { result: edited } = codec( 'edit', original, [
		{
			operation: 'update',
			path: [ 0, 0 ],
			attributes: { label: 'Full name', placeholder: 'Your name' },
		},
	] );
	expect( edited ).toContain( custom );
	expect( edited ).toContain( 'custom-layout' );
	assertValid( edited );
} );

test( 'field updates preserve existing Gutenberg metadata and unsupported decoration', () => {
	const { result: markup } = codec( 'create', {
		blocks: [
			{
				name: 'formblox/form-input',
				attributes: { name: 'name', label: 'Name' },
			},
			{ name: 'formblox/form-submit-button' },
		],
	} );
	const decorated = markup.replace(
		'"name":"name"',
		'"name":"name","metadata":{"name":"Primary field"}'
	);
	const { result, error } = codec( 'edit', decorated, [
		{
			operation: 'update',
			path: [ 0 ],
			attributes: { label: 'Full name' },
		},
	] );
	expect( error ).toBeUndefined();
	expect( result ).toContain( '"metadata":{"name":"Primary field"}' );
	assertValid( result );
} );

test( 'native colors, typography, columns, and styled submit buttons remain valid', () => {
	const style = {
		color: { text: 'rgb(20, 40, 60)', background: '#eeeeee' },
		spacing: {
			padding: {
				top: 'var:preset|spacing|40',
				right: '12px',
				bottom: '10px',
				left: '12px',
			},
		},
		typography: {
			fontSize: '18px',
			lineHeight: '1.5',
			fontWeight: '600',
			fontFamily: 'sans-serif',
		},
	};
	const { result, error } = codec( 'create', {
		attributes: { style },
		blocks: [
			{
				name: 'core/heading',
				attributes: { content: 'Contact us', level: 3, style },
			},
			{
				name: 'core/columns',
				innerBlocks: [
					{
						name: 'core/column',
						innerBlocks: [
							{
								name: 'formblox/form-input',
								attributes: { name: 'email', type: 'email' },
							},
						],
					},
				],
			},
			{
				name: 'formblox/form-submit-button',
				innerBlocks: [
					{
						name: 'core/buttons',
						innerBlocks: [
							{
								name: 'core/button',
								attributes: { text: 'Send', style },
							},
						],
					},
				],
			},
		],
	} );
	expect( error ).toBeUndefined();
	assertValid( result );
} );

test( 'rejects properties Gutenberg cannot save on the selected block', () => {
	expect(
		codec( 'create', {
			blocks: [
				{
					name: 'formblox/form-input',
					attributes: { name: 'name', backgroundColor: 'base' },
				},
				{ name: 'formblox/form-submit-button' },
			],
		} ).error
	).toBeTruthy();
} );

test.each( [
	{
		blocks: [
			{ name: 'formblox/form-input', attributes: { type: 'file' } },
		],
	},
	{
		blocks: [
			{ name: 'formblox/form-input', attributes: { name: 'same' } },
			{ name: 'formblox/form-input', attributes: { name: 'same' } },
			{ name: 'formblox/form-submit-button' },
		],
	},
	{
		attributes: {
			submissionMethod: 'custom',
			action: 'javascript:alert(1)',
		},
	},
	{
		blocks: [
			{
				name: 'core/html',
				attributes: { content: '<script>alert(1)</script>' },
			},
		],
	},
	{
		blocks: [
			{
				name: 'formblox/form-input',
				attributes: { label: '<!-- wp:formblox/form /-->' },
			},
		],
	},
] )( 'rejects unsupported or unsafe definitions %#', ( definition ) => {
	expect( codec( 'create', definition ).error ).toBeTruthy();
} );

test( 'rich labels retain nested spans when read and edited', () => {
	const label =
		'<span style="color:#123456">Your <strong>name</strong></span> please';
	const { result: original } = codec( 'create', {
		blocks: [
			{
				name: 'formblox/form-input',
				attributes: { name: 'name', label },
			},
			{ name: 'formblox/form-submit-button' },
		],
	} );
	const { result: tree } = codec( 'read', original );
	expect( tree.innerBlocks[ 0 ].attributes.label ).toBe( label );
	const { result } = codec( 'edit', original, [
		{ operation: 'update', path: [ 0 ], attributes: { required: true } },
	] );
	assertValid( result );
	expect( result ).toContain( label );
} );

test( 'styled buttons, Group tags, and anchors can be updated and cleared', () => {
	const { result: original } = codec( 'create', {
		attributes: { anchor: 'contact' },
		blocks: [
			{
				name: 'core/group',
				innerBlocks: [
					{
						name: 'formblox/form-input',
						attributes: { name: 'name' },
					},
				],
			},
			{ name: 'formblox/form-submit-button' },
		],
	} );
	const { result, error } = codec( 'edit', original, [
		{ operation: 'update', path: [], attributes: { anchor: '' } },
		{
			operation: 'update',
			path: [ 0 ],
			attributes: { tagName: 'section' },
		},
		{
			operation: 'update',
			path: [ 1, 0, 0 ],
			attributes: {
				text: 'Send',
				style: {
					color: { background: '#123456' },
					typography: { fontSize: '22px' },
				},
			},
		},
	] );
	expect( error ).toBeUndefined();
	assertValid( result );
	expect( result ).toContain( '<section' );
	expect( result ).not.toContain( 'id=""' );
	const { result: cleared } = codec( 'edit', result, [
		{ operation: 'update', path: [ 1, 0, 0 ], attributes: { style: {} } },
	] );
	assertValid( cleared );
} );

test( 'insert, move and remove preserve their sequential path semantics', () => {
	const { result: original } = codec( 'create', {} );
	const { result, error } = codec( 'edit', original, [
		{
			operation: 'insert',
			parent_path: [],
			index: 0,
			block: {
				name: 'core/group',
				innerBlocks: [
					{
						name: 'core/paragraph',
						attributes: { content: 'Introduction' },
					},
				],
			},
		},
		{ operation: 'move', path: [ 3 ], parent_path: [ 0 ], index: 1 },
		{ operation: 'remove', path: [ 0, 0 ] },
	] );
	expect( error ).toBeUndefined();
	assertValid( result );
	const { result: tree } = codec( 'read', result );
	expect( tree.innerBlocks[ 0 ].innerBlocks[ 0 ].attributes.name ).toBe(
		'author'
	);
} );

test.each( [
	{ name: 'core/group', attributes: { tagName: 'div onclick="alert(1)"' } },
	{
		name: 'core/group',
		attributes: { style: { typography: { arbitrary: 'red' } } },
	},
	{
		name: 'core/group',
		attributes: { style: { spacing: { padding: '1px; color:red' } } },
	},
	{
		name: 'formblox/form-input',
		attributes: { name: 'test', style: { color: { text: 'red' } } },
	},
] )( 'rejects unsafe tags and unsupported nested styles %#', ( block ) => {
	expect(
		codec( 'create', {
			blocks: [ block, { name: 'formblox/form-submit-button' } ],
		} ).error
	).toBeTruthy();
} );

test( 'rejects changes that break a privacy request contract', () => {
	const { result } = codec( 'create', { template: 'privacy' } );
	expect(
		codec( 'edit', result, [
			{ operation: 'update', path: [ 3 ], attributes: { type: 'text' } },
		] ).error
	).toBeTruthy();
} );

test( 'native corner radii, gradients, and button-group font sizes stay valid', () => {
	const { result, error } = codec( 'create', {
		attributes: {
			backgroundColor: 'base',
			style: {
				color: {
					gradient:
						'linear-gradient(90deg, #eeeeee 0%, #ffffff 100%)',
				},
			},
		},
		blocks: [
			{
				name: 'formblox/form-input',
				attributes: {
					name: 'name',
					style: {
						border: {
							radius: {
								topLeft: '4px',
								topRight: '8px',
								bottomRight: '12px',
								bottomLeft: '2px',
							},
						},
					},
				},
			},
			{
				name: 'formblox/form-submit-button',
				innerBlocks: [
					{
						name: 'core/buttons',
						attributes: { fontSize: 'large' },
						innerBlocks: [
							{
								name: 'core/button',
								attributes: {
									backgroundColor: 'base',
									gradient: 'vivid-cyan-blue-to-vivid-purple',
									fontSize: 'small',
								},
							},
						],
					},
				],
			},
		],
	} );
	expect( error ).toBeUndefined();
	assertValid( result );
} );

test.each( [
	'section',
	'article',
	'aside',
	'header',
	'footer',
	'main',
	'div',
] )(
	'Group wrapper changes to %s survive Gutenberg serialization',
	( tagName ) => {
		const { result: made } = codec( 'create', {
			blocks: [
				{
					name: 'core/group',
					attributes: { tagName: 'section' },
					innerBlocks: [
						{
							name: 'formblox/form-input',
							attributes: { name: 'name' },
						},
					],
				},
				{ name: 'formblox/form-submit-button' },
			],
		} );
		const native = serialize( assertValid( made ) );
		const { result, error } = codec( 'edit', native, [
			{ operation: 'update', path: [ 0 ], attributes: { tagName } },
		] );
		expect( error ).toBeUndefined();
		const group = assertValid( result )[ 0 ].innerBlocks[ 0 ];
		expect( group.attributes.tagName ).toBe( tagName );
		expect( group.innerBlocks[ 0 ].attributes.name ).toBe( 'name' );
	}
);

test.each( [ 'double', 'single', 'unquoted' ] )(
	'unrelated field edits preserve existing rich labels with %s class quoting',
	( quoting ) => {
		const { result: made } = codec( 'create', {} );
		const tree = assertValid( made );
		const label =
			'<span title="a > b" class="custom-format">Area (m<sup>2</sup>) <u>required</u></span>';
		tree[ 0 ].innerBlocks[ 2 ].attributes.label = label;
		let native = serialize( tree );
		if ( quoting === 'single' ) {
			native = native.replaceAll(
				'class="wp-block-formblox-form-input__label-content"',
				"class='wp-block-formblox-form-input__label-content'"
			);
		} else if ( quoting === 'unquoted' ) {
			native = native.replaceAll(
				'class="wp-block-formblox-form-input__label-content"',
				'class=wp-block-formblox-form-input__label-content'
			);
		}
		assertValid( native );
		expect(
			codec( 'read', native ).result.innerBlocks[ 2 ].attributes.label
		).toBe( label );
		const { result, error } = codec( 'edit', native, [
			{
				operation: 'update',
				path: [ 2 ],
				attributes: { required: false, placeholder: 'Square meters' },
			},
		] );
		expect( error ).toBeUndefined();
		assertValid( result );
		expect(
			codec( 'read', result ).result.innerBlocks[ 2 ].attributes.label
		).toBe( label );
	}
);

test( 'an explicit label edit replaces preserved label markup safely', () => {
	const { result: made } = codec( 'create', {} );
	const tree = assertValid( made );
	tree[ 0 ].innerBlocks[ 2 ].attributes.label = 'Area (m<sup>2</sup>)';
	const native = serialize( tree );
	const { result, error } = codec( 'edit', native, [
		{
			operation: 'update',
			path: [ 2 ],
			attributes: {
				label: 'Depth (cm<sub>ref</sub>)<script>alert(1)</script>',
			},
		},
	] );
	expect( error ).toBeUndefined();
	assertValid( result );
	expect(
		codec( 'read', result ).result.innerBlocks[ 2 ].attributes.label
	).toContain( '<sub>ref</sub>' );
	expect( result ).not.toContain( '<sup>' );
	expect( result ).not.toContain( '<script>' );
} );

test.each( [ 'formblox/form-input', 'formblox/form-submit-button' ] )(
	'rejects %s inside a nested submission notification',
	( name ) => {
		const { error } = codec( 'create', {
			blocks: [
				{
					name: 'formblox/form-submission-notification',
					innerBlocks: [
						{ name: 'core/group', innerBlocks: [ { name } ] },
					],
				},
				{ name: 'formblox/form-submit-button' },
			],
		} );
		expect( error ).toMatch( /notification/i );
	}
);

test( 'moving a submit button into a notification is rejected', () => {
	const { result: made } = codec( 'create', {} );
	const { error } = codec( 'edit', made, [
		{
			operation: 'insert',
			parent_path: [ 0 ],
			block: { name: 'core/group' },
		},
		{ operation: 'move', path: [ 5 ], parent_path: [ 0, 1 ] },
	] );
	expect( error ).toMatch( /notification/i );
} );

test( 'PHP field-name collisions and normalized reserved controls are rejected', () => {
	for ( const fieldNames of [
		[ 'phone.mobile', 'phone_mobile' ],
		[ '_ajax.nonce' ],
		[ '_wp.http.referer' ],
	] ) {
		const { error } = codec( 'create', {
			blocks: [
				...fieldNames.map( ( name ) => ( {
					name: 'formblox/form-input',
					attributes: { name },
				} ) ),
				{ name: 'formblox/form-submit-button' },
			],
		} );
		expect( error ).toBeTruthy();
	}
} );

test( 'raw dotted names remain available for external custom endpoints', () => {
	const { result, error } = codec( 'create', {
		template: 'custom',
		attributes: { action: 'https://example.com/custom' },
		blocks: [
			{
				name: 'formblox/form-input',
				attributes: { name: 'phone.mobile' },
			},
			{
				name: 'formblox/form-input',
				attributes: { name: 'phone_mobile' },
			},
			{ name: 'formblox/form-submit-button' },
		],
	} );
	expect( error ).toBeUndefined();
	assertValid( result );
} );
