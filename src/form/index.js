import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import initBlock from '../utils/init-block';
import edit from './edit';
import metadata from './block.json';
import save from './save';
import variations from './variations';
import { icon } from './icons';
import {
	formSubmissionNotificationSuccess,
	formSubmissionNotificationError,
} from './utils';

const { name } = metadata;

export { metadata, name };

const TEMPLATE = [
	formSubmissionNotificationSuccess,
	formSubmissionNotificationError,
	[
		'formblox/form-input',
		{
			type: 'text',
			label: __( 'Name', 'wp-forms-blocks' ),
			required: true,
		},
	],
	[
		'formblox/form-input',
		{
			type: 'email',
			label: __( 'Email', 'wp-forms-blocks' ),
			required: true,
		},
	],
	[
		'formblox/form-input',
		{
			type: 'textarea',
			label: __( 'Comment', 'wp-forms-blocks' ),
			required: true,
		},
	],
	[ 'formblox/form-submit-button', {} ],
];

export const settings = {
	icon,
	template: TEMPLATE,
	edit,
	save,
	variations,
	example: {},
};

export const init = () => {
	// Prevent adding forms inside forms.
	const DISALLOWED_PARENTS = [ 'formblox/form' ];
	addFilter(
		'blockEditor.__unstableCanInsertBlockType',
		'formblox/block-library/preventInsertingFormIntoAnotherForm',
		(
			canInsert,
			blockType,
			rootClientId,
			{ getBlock, getBlockParentsByBlockName }
		) => {
			if ( blockType.name !== 'formblox/form' ) {
				return canInsert;
			}

			for ( const disallowedParentType of DISALLOWED_PARENTS ) {
				const hasDisallowedParent =
					getBlock( rootClientId )?.name === disallowedParentType ||
					getBlockParentsByBlockName(
						rootClientId,
						disallowedParentType
					).length;
				if ( hasDisallowedParent ) {
					return false;
				}
			}
			return true;
		}
	);

	return initBlock( { name, metadata, settings } );
};
