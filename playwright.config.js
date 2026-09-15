const baseConfig = require( '@wordpress/scripts/config/playwright.config' );

module.exports = {
	...baseConfig,
	testDir: './specs',
	webServer: {
		...baseConfig.webServer,
		command: 'tests/run-e2e-server.sh',
	},
};
