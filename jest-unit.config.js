const defaultConfig = require( '@wordpress/scripts/config/jest-unit.config' );
const defaultPreset = require( '@wordpress/jest-preset-default' );

module.exports = {
	...defaultConfig,
	setupFiles: [ ...defaultPreset.setupFiles, '<rootDir>/tests/js/setup.js' ],
	collectCoverageFrom: [
		'src/**/*.js',
		'!src/**/init.js',
		'!src/index.js',
		'!src/style.js',
	],
	coverageThreshold: {
		global: {
			branches: 90,
			functions: 90,
			lines: 95,
			statements: 95,
		},
	},
	transform: {
		'^.+\\.m?[jt]sx?$': defaultConfig.transform[ '\\.[jt]sx?$' ],
	},
	// uuid is ESM-only. The block package imports it, so Jest must transform
	// it and the ESM-only WordPress theme package instead of treating them as
	// precompiled CommonJS.
	transformIgnorePatterns: [
		'<rootDir>/node_modules/.pnpm/(?!(marked|parsel-js|uuid|@wordpress\\+theme)@)',
	],
};
