const config = require( '@wordpress/scripts/config/eslint.config.cjs' );

module.exports = [
	...config,
	{
		rules: {
			// These blocks still depend on APIs that WordPress exports under
			// experimental aliases. Keep the dependency explicit until stable
			// aliases are available in every supported WordPress release.
			'@wordpress/no-unsafe-wp-apis': 'off',
		},
	},
	{
		files: [ 'tests/js/view-response.test.js' ],
		rules: {
			'jsdoc/check-tag-names': 'off',
		},
	},
];
