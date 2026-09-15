const port = Number( process.env.WP_FORMS_BLOCKS_E2E_PORT || 8889 );

if ( ! Number.isInteger( port ) || port < 1024 || port > 65535 ) {
	throw new Error( 'WP_FORMS_BLOCKS_E2E_PORT must be a valid port.' );
}

process.env.WP_BASE_URL = `http://localhost:${ port }`;

const baseConfig = require( '@wordpress/scripts/config/playwright.config' );

module.exports = {
	...baseConfig,
	testDir: './specs',
	use: {
		...baseConfig.use,
		baseURL: `http://localhost:${ port }/`,
	},
	webServer: {
		...baseConfig.webServer,
		command: 'tests/run-e2e-server.sh',
		env: {
			...process.env,
			WP_FORMS_BLOCKS_E2E_PORT: String( port ),
		},
		port,
		reuseExistingServer: false,
	},
};
