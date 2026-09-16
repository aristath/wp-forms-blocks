import { __ } from '@wordpress/i18n';
import {
	formSubmissionNotificationSuccess,
	formSubmissionNotificationError,
} from './utils.js';

const variations = [
	{
		name: 'contact-form',
		title: __( 'Contact Form', 'wp-forms-blocks' ),
		description: __(
			'A contact form for site visitors.',
			'wp-forms-blocks'
		),
		attributes: {
			submissionMethod: 'email',
		},
		isDefault: true,
		innerBlocks: [
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
		],
		scope: [ 'inserter', 'transform' ],
		isActive: ( blockAttributes ) =>
			! blockAttributes?.submissionMethod ||
			blockAttributes?.submissionMethod === 'email',
	},
	{
		name: 'comment-form',
		title: __( 'Comment Form', 'wp-forms-blocks' ),
		description: __(
			'A comment form for posts and pages.',
			'wp-forms-blocks'
		),
		attributes: {
			submissionMethod: 'custom',
			action: '{SITE_URL}/wp-comments-post.php',
			method: 'post',
			anchor: 'comment-form',
		},
		isDefault: false,
		innerBlocks: [
			[
				'formblox/form-input',
				{
					type: 'text',
					name: 'author',
					label: __( 'Name', 'wp-forms-blocks' ),
					required: true,
					visibilityPermissions: 'logged-out',
				},
			],
			[
				'formblox/form-input',
				{
					type: 'email',
					name: 'email',
					label: __( 'Email', 'wp-forms-blocks' ),
					required: true,
					visibilityPermissions: 'logged-out',
				},
			],
			[
				'formblox/form-input',
				{
					type: 'textarea',
					name: 'comment',
					label: __( 'Comment', 'wp-forms-blocks' ),
					required: true,
					visibilityPermissions: 'all',
				},
			],
			[ 'formblox/form-submit-button', {} ],
		],
		scope: [ 'inserter', 'transform' ],
		isActive: ( blockAttributes ) =>
			blockAttributes?.action === '{SITE_URL}/wp-comments-post.php',
	},
	{
		name: 'wp-privacy-form',
		title: __( 'Privacy Request Form', 'wp-forms-blocks' ),
		keywords: [ 'GDPR' ],
		description: __(
			'A form to request data exports and/or deletion.',
			'wp-forms-blocks'
		),
		attributes: {
			submissionMethod: 'custom',
			action: '',
			method: 'post',
			anchor: 'gdpr-form',
		},
		isDefault: false,
		innerBlocks: [
			formSubmissionNotificationSuccess,
			formSubmissionNotificationError,
			[
				'core/paragraph',
				{
					content: __(
						'To request an export or deletion of your personal data on this site, please fill-in the form below. You can define the type of request you wish to perform, and your email address. Once the form is submitted, you will receive a confirmation email with instructions on the next steps.',
						'wp-forms-blocks'
					),
				},
			],
			[
				'formblox/form-input',
				{
					type: 'email',
					name: 'email',
					label: __( 'Enter your email address.', 'wp-forms-blocks' ),
					required: true,
					visibilityPermissions: 'all',
				},
			],
			[
				'formblox/form-input',
				{
					type: 'checkbox',
					name: 'export_personal_data',
					label: __( 'Request data export', 'wp-forms-blocks' ),
					required: false,
					visibilityPermissions: 'all',
				},
			],
			[
				'formblox/form-input',
				{
					type: 'checkbox',
					name: 'remove_personal_data',
					label: __( 'Request data deletion', 'wp-forms-blocks' ),
					required: false,
					visibilityPermissions: 'all',
				},
			],
			[ 'formblox/form-submit-button', {} ],
			[
				'formblox/form-input',
				{
					type: 'hidden',
					name: 'wp-action',
					value: 'wp_privacy_send_request',
				},
			],
			[
				'formblox/form-input',
				{
					type: 'hidden',
					name: 'wp-privacy-request',
					value: '1',
				},
			],
		],
		scope: [ 'inserter', 'transform' ],
		isActive: ( blockAttributes ) =>
			blockAttributes?.anchor === 'gdpr-form',
	},
];

export default variations;
