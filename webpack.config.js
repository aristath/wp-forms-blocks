const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		index: path.resolve( process.cwd(), 'src/index.js' ),
		style: path.resolve( process.cwd(), 'src/style.js' ),
		view: path.resolve( process.cwd(), 'src/form/view.js' ),
	},
};
