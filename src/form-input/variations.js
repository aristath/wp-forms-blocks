import { __ } from '@wordpress/i18n';

const variations = [
	{
		name: 'text',
		title: __( 'Text Input', 'wp-forms-blocks' ),
		description: __( 'A generic text input.', 'wp-forms-blocks' ),
		attributes: { type: 'text' },
		isDefault: true,
		scope: [ 'inserter', 'transform' ],
		isActive: ( blockAttributes ) =>
			! blockAttributes?.type || blockAttributes?.type === 'text',
	},
	{
		name: 'textarea',
		title: __( 'Textarea Input', 'wp-forms-blocks' ),
		description: __(
			'A textarea input to allow entering multiple lines of text.',
			'wp-forms-blocks'
		),
		attributes: { type: 'textarea' },
		isDefault: false,
		scope: [ 'inserter', 'transform' ],
		isActive: ( blockAttributes ) => blockAttributes?.type === 'textarea',
	},
	{
		name: 'checkbox',
		title: __( 'Checkbox Input', 'wp-forms-blocks' ),
		description: __( 'A simple checkbox input.', 'wp-forms-blocks' ),
		attributes: { type: 'checkbox', inlineLabel: true },
		isDefault: false,
		scope: [ 'inserter', 'transform' ],
		isActive: ( blockAttributes ) => blockAttributes?.type === 'checkbox',
	},
	{
		name: 'email',
		title: __( 'Email Input', 'wp-forms-blocks' ),
		description: __( 'Used for email addresses.', 'wp-forms-blocks' ),
		attributes: { type: 'email' },
		isDefault: false,
		scope: [ 'inserter', 'transform' ],
		isActive: ( blockAttributes ) => blockAttributes?.type === 'email',
	},
	{
		name: 'url',
		title: __( 'URL Input', 'wp-forms-blocks' ),
		description: __( 'Used for URLs.', 'wp-forms-blocks' ),
		attributes: { type: 'url' },
		isDefault: false,
		scope: [ 'inserter', 'transform' ],
		isActive: ( blockAttributes ) => blockAttributes?.type === 'url',
	},
	{
		name: 'tel',
		title: __( 'Telephone Input', 'wp-forms-blocks' ),
		description: __( 'Used for phone numbers.', 'wp-forms-blocks' ),
		attributes: { type: 'tel' },
		isDefault: false,
		scope: [ 'inserter', 'transform' ],
		isActive: ( blockAttributes ) => blockAttributes?.type === 'tel',
	},
	{
		name: 'number',
		title: __( 'Number Input', 'wp-forms-blocks' ),
		description: __( 'A numeric input.', 'wp-forms-blocks' ),
		attributes: { type: 'number' },
		isDefault: false,
		scope: [ 'inserter', 'transform' ],
		isActive: ( blockAttributes ) => blockAttributes?.type === 'number',
	},
	{
		name: 'hidden',
		title: __( 'Hidden Input', 'wp-forms-blocks' ),
		icon: 'visibility',
		description: __( 'A hidden input field.', 'wp-forms-blocks' ),
		attributes: { type: 'hidden' },
		isDefault: false,
		scope: [ 'inserter', 'transform' ],
		isActive: ( blockAttributes ) => blockAttributes?.type === 'hidden',
	},
];

export default variations;
