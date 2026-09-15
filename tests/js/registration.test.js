import { getBlockType, unregisterBlockType } from '@wordpress/blocks';
import { applyFilters, removeFilter } from '@wordpress/hooks';

import initBlock from '../../src/utils/init-block';
import {
	init as initForm,
	metadata as formMetadata,
	settings as formSettings,
} from '../../src/form';
import {
	metadata as inputMetadata,
	settings as inputSettings,
} from '../../src/form-input';
import {
	metadata as submitMetadata,
	settings as submitSettings,
} from '../../src/form-submit-button';
import {
	metadata as notificationMetadata,
	settings as notificationSettings,
} from '../../src/form-submission-notification';

describe( 'standalone block registration contracts', () => {
	test( 'initBlock handles absent, new, and already-registered blocks', () => {
		expect( initBlock() ).toBeUndefined();

		const name = 'wp-forms-blocks/unit-test';
		const block = {
			name,
			metadata: {
				apiVersion: 3,
				title: 'Unit test block',
				category: 'widgets',
			},
			settings: {
				edit: () => null,
				save: () => null,
			},
		};
		const registered = initBlock( block );

		expect( registered ).toBe( getBlockType( name ) );
		expect( initBlock( block ) ).toBe( registered );
		unregisterBlockType( name );
	} );

	test( 'retains all four Gutenberg metadata contracts', () => {
		expect( formMetadata ).toMatchObject( {
			name: 'core/form',
			apiVersion: 3,
			allowedBlocks: [
				'core/paragraph',
				'core/heading',
				'core/form-input',
				'core/form-submit-button',
				'core/form-submission-notification',
				'core/group',
				'core/columns',
			],
		} );
		expect( inputMetadata ).toMatchObject( {
			name: 'core/form-input',
			ancestor: [ 'core/form' ],
			style: [ 'wp-block-form-input' ],
		} );
		expect( submitMetadata ).toMatchObject( {
			name: 'core/form-submit-button',
			ancestor: [ 'core/form' ],
			allowedBlocks: [ 'core/buttons', 'core/button' ],
			style: [ 'wp-block-form-submit-button' ],
		} );
		expect( notificationMetadata ).toMatchObject( {
			name: 'core/form-submission-notification',
			ancestor: [ 'core/form' ],
		} );
	} );

	test( 'retains every default inner-block template', () => {
		expect( formSettings.template.map( ( item ) => item[ 0 ] ) ).toEqual( [
			'core/form-submission-notification',
			'core/form-submission-notification',
			'core/form-input',
			'core/form-input',
			'core/form-input',
			'core/form-submit-button',
		] );
		expect( formSettings.template[ 0 ][ 1 ] ).toEqual( {
			type: 'success',
		} );
		expect( formSettings.template[ 1 ][ 1 ] ).toEqual( { type: 'error' } );
		expect(
			formSettings.template.slice( 2, 5 ).map( ( item ) => item[ 1 ] )
		).toEqual( [
			{ type: 'text', label: 'Name', required: true },
			{ type: 'email', label: 'Email', required: true },
			{ type: 'textarea', label: 'Comment', required: true },
		] );
		expect( submitSettings.template ).toEqual( [
			[
				'core/buttons',
				{},
				[
					[
						'core/button',
						{ text: 'Submit', tagName: 'button', type: 'submit' },
					],
				],
			],
		] );
		expect( notificationSettings.template[ 0 ][ 0 ] ).toBe(
			'core/paragraph'
		);
		expect( inputSettings.example ).toEqual( {} );
	} );

	test( 'registers only the canonical block schemas', () => {
		expect( formSettings ).not.toHaveProperty( 'deprecated' );
		expect( inputSettings ).not.toHaveProperty( 'deprecated' );
	} );

	test( 'retains every variation attribute, template, scope, and activation rule', () => {
		const inputVariations = inputSettings.variations;
		expect(
			inputVariations.map( ( variation ) => variation.attributes )
		).toEqual( [
			{ type: 'text' },
			{ type: 'textarea' },
			{ type: 'checkbox', inlineLabel: true },
			{ type: 'email' },
			{ type: 'url' },
			{ type: 'tel' },
			{ type: 'number' },
			{ type: 'hidden' },
		] );
		inputVariations.forEach( ( variation ) => {
			expect( variation.scope ).toEqual( [ 'inserter', 'transform' ] );
			expect( variation.isActive( variation.attributes ) ).toBe( true );
			expect(
				variation.isActive( { type: 'not-' + variation.name } )
			).toBe( false );
		} );
		expect( inputVariations[ 0 ].isActive( {} ) ).toBe( true );

		const [ comment, privacy ] = formSettings.variations;
		expect( comment.attributes ).toEqual( {
			submissionMethod: 'custom',
			action: '{SITE_URL}/wp-comments-post.php',
			method: 'post',
			anchor: 'comment-form',
		} );
		expect( comment.innerBlocks.map( ( item ) => item[ 0 ] ) ).toEqual( [
			'core/form-input',
			'core/form-input',
			'core/form-input',
			'core/form-submit-button',
		] );
		expect( privacy.attributes ).toEqual( {
			submissionMethod: 'custom',
			action: '',
			method: 'post',
			anchor: 'gdpr-form',
		} );
		expect( privacy.innerBlocks.map( ( item ) => item[ 0 ] ) ).toEqual( [
			'core/form-submission-notification',
			'core/form-submission-notification',
			'core/paragraph',
			'core/form-input',
			'core/form-input',
			'core/form-input',
			'core/form-submit-button',
			'core/form-input',
			'core/form-input',
		] );
		for ( const variation of [ comment, privacy ] ) {
			expect( variation.scope ).toEqual( [ 'inserter', 'transform' ] );
			expect( variation.isActive() ).toBe( true );
			expect( variation.isActive( { type: 'text' } ) ).toBe( true );
			expect( variation.isActive( { type: 'other' } ) ).toBe( false );
		}

		const [ success, error ] = notificationSettings.variations;
		expect( success.attributes ).toEqual( { type: 'success' } );
		expect( error.attributes ).toEqual( { type: 'error' } );
		expect( success.innerBlocks[ 0 ][ 1 ] ).toMatchObject( {
			content: 'Your form has been submitted successfully.',
			backgroundColor: '#00D084',
			textColor: '#000000',
		} );
		expect( error.innerBlocks[ 0 ][ 1 ] ).toMatchObject( {
			content: 'There was an error submitting your form.',
			backgroundColor: '#CF2E2E',
			textColor: '#FFFFFF',
		} );
		expect( success.isActive() ).toBe( true );
		expect( success.isActive( { type: 'success' } ) ).toBe( true );
		expect( success.isActive( { type: 'error' } ) ).toBe( false );
		expect( error.isActive() ).toBe( true );
		expect( error.isActive( { type: 'error' } ) ).toBe( true );
		expect( error.isActive( { type: 'success' } ) ).toBe( false );
	} );

	test( 'prevents nested forms without affecting other insertion decisions', () => {
		initForm();
		const selectors = {
			getBlock: jest.fn(),
			getBlockParentsByBlockName: jest.fn( () => [] ),
		};
		const filterName = 'blockEditor.__unstableCanInsertBlockType';

		expect(
			applyFilters(
				filterName,
				false,
				{ name: 'core/paragraph' },
				'root',
				selectors
			)
		).toBe( false );
		expect(
			applyFilters(
				filterName,
				true,
				{ name: 'core/form' },
				'root',
				selectors
			)
		).toBe( true );

		selectors.getBlock.mockReturnValue( { name: 'core/form' } );
		expect(
			applyFilters(
				filterName,
				true,
				{ name: 'core/form' },
				'root',
				selectors
			)
		).toBe( false );

		selectors.getBlock.mockReturnValue( { name: 'core/group' } );
		selectors.getBlockParentsByBlockName.mockReturnValue( [ 'parent' ] );
		expect(
			applyFilters(
				filterName,
				true,
				{ name: 'core/form' },
				'root',
				selectors
			)
		).toBe( false );

		removeFilter(
			filterName,
			'core/block-library/preventInsertingFormIntoAnotherForm'
		);
	} );
} );
