import fs from 'fs';
import path from 'path';

import { registerCoreBlocks } from '@wordpress/block-library';
import { parse as grammarParse } from '@wordpress/block-serialization-default-parser';
import {
	getBlockType,
	getBlockVariations,
	parse,
	serialize,
	unregisterBlockType,
} from '@wordpress/blocks';

import { init as initForm } from '../../src/form';
import { init as initFormInput } from '../../src/form-input';
import { init as initFormSubmissionNotification } from '../../src/form-submission-notification';
import { init as initFormSubmitButton } from '../../src/form-submit-button';

const fixturesDirectory = path.join( __dirname, '../fixtures/blocks' );
const blockNames = [
	'core/form',
	'core/form-input',
	'core/form-submit-button',
	'core/form-submission-notification',
];
const fixtureBasenames = fs
	.readdirSync( fixturesDirectory )
	.filter(
		( filename ) =>
			filename.endsWith( '.html' ) &&
			! filename.endsWith( '.serialized.html' )
	)
	.map( ( filename ) => filename.replace( /\.html$/, '' ) )
	.sort();

const readFixture = ( basename, suffix ) =>
	fs.readFileSync(
		path.join( fixturesDirectory, `${ basename }.${ suffix }` ),
		'utf8'
	);

/**
 * Keep only the public block representation captured by Gutenberg's fixtures.
 *
 * @param {WPBlock[]} blocks Parsed blocks.
 * @return {Object[]} Normalized blocks.
 */
const normalizeParsedBlocks = ( blocks ) =>
	blocks.map( ( block ) => ( {
		name: block.name,
		isValid: block.isValid,
		attributes: JSON.parse( JSON.stringify( block.attributes ) ),
		innerBlocks: normalizeParsedBlocks( block.innerBlocks ),
		...( block.innerContent ? { innerContent: block.innerContent } : {} ),
	} ) );

beforeAll( () => {
	registerCoreBlocks();

	// Ensure this suite exercises the standalone implementation even if the
	// installed @wordpress/block-library package happens to contain an older
	// copy of the experimental blocks.
	blockNames.forEach( ( name ) => {
		if ( getBlockType( name ) ) {
			unregisterBlockType( name );
		}
	} );

	initForm();
	initFormInput();
	initFormSubmitButton();
	initFormSubmissionNotification();
} );

describe( 'current form block serialization fixtures', () => {
	test( 'contains every canonical current-format fixture', () => {
		expect( fixtureBasenames ).toHaveLength( 7 );
		expect( fs.readdirSync( fixturesDirectory ) ).toHaveLength( 28 );
		expect(
			fixtureBasenames.some( ( basename ) =>
				basename.includes( 'deprecated' )
			)
		).toBe( false );
	} );

	blockNames.forEach( ( name ) => {
		test( `registers ${ name } from this plugin`, () => {
			expect( getBlockType( name ) ).toBeDefined();
		} );
	} );

	test( 'registers the complete variation set', () => {
		expect(
			getBlockVariations( 'core/form' ).map(
				( variation ) => variation.name
			)
		).toEqual( [ 'contact-form', 'comment-form', 'wp-privacy-form' ] );
		expect(
			getBlockVariations( 'core/form-input' ).map(
				( variation ) => variation.name
			)
		).toEqual( [
			'text',
			'textarea',
			'checkbox',
			'email',
			'url',
			'tel',
			'number',
			'hidden',
		] );
		expect(
			getBlockVariations( 'core/form-submission-notification' ).map(
				( variation ) => variation.name
			)
		).toEqual( [ 'form-submission-success', 'form-submission-error' ] );
	} );

	test.each( fixtureBasenames )( '%s', ( basename ) => {
		const html = readFixture( basename, 'html' ).trim();
		const expectedParserOutput = JSON.parse(
			readFixture( basename, 'parsed.json' )
		);
		const expectedBlocks = JSON.parse( readFixture( basename, 'json' ) );
		const expectedSerialization = readFixture(
			basename,
			'serialized.html'
		);

		expect( grammarParse( html ) ).toEqual( expectedParserOutput );

		const blocks = parse( html );

		expect( normalizeParsedBlocks( blocks ) ).toEqual( expectedBlocks );
		expect( serialize( blocks ) + '\n' ).toBe( expectedSerialization );
	} );
} );
