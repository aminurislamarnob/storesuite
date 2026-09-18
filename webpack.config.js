const path = require( 'path' );
const glob = require( 'glob' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

// Discover every module's React entry at `modules/<slug>/src/index.{js,jsx}`.
//
// The entry key intentionally uses `../../` so the build output escapes the
// shared `assets/build/` directory and lands inside the module's own
// `assets/build/`. With `output.path = assets/build`, an entry keyed
// `../../modules/<slug>/assets/build/script` resolves to
// `<plugin-root>/modules/<slug>/assets/build/script.js`, keeping each module
// fully self-contained on disk. This mirrors Dokan Pro's webpack-entries
// convention.
const moduleEntries = glob
	.sync( './modules/*/src/index.{js,jsx}' )
	.reduce( ( acc, entry ) => {
		const match = entry.match( /^\.\/modules\/([^/]+)\// );
		if ( ! match ) {
			return acc;
		}
		const slug = match[ 1 ];
		acc[ `../../modules/${ slug }/assets/build/script` ] = path.resolve(
			__dirname,
			entry
		);
		return acc;
	}, {} );

module.exports = {
	...defaultConfig,
	entry: {
		'admin/script':    './src/admin.js',
		'analytics/index': './src/analytics/index.js',
		'dashboard/index': './src/dashboard/index.js',
		...moduleEntries,
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'assets/build' ),
		filename: '[name].js',
		chunkFilename: ( pathData ) => {
			const name = pathData.chunk?.name || '';
			const dir  = name.startsWith( 'ss-dashboard' ) ? 'dashboard' : 'analytics';
			return `${ dir }/chunks/[name].js`;
		},
	},
	externals: {
		...defaultConfig.externals,
		'@woocommerce/components':            [ 'window', 'wc', 'components' ],
		'@woocommerce/data':                  [ 'window', 'wc', 'data' ],
		'@woocommerce/date':                  [ 'window', 'wc', 'date' ],
		'@woocommerce/currency':              [ 'window', 'wc', 'currency' ],
		'@woocommerce/navigation':            [ 'window', 'wc', 'navigation' ],
		'@woocommerce/number':                [ 'window', 'wc', 'number' ],
		'@woocommerce/tracks':                [ 'window', 'wc', 'tracks' ],
		'@woocommerce/customer-effort-score': [ 'window', 'wc', 'customerEffortScore' ],
		'@woocommerce/csv-export':            [ 'window', 'wc', 'csvExport' ],
		'@woocommerce/settings':              [ 'window', 'wc', 'wcSettings' ],
	},
	resolve: {
		...defaultConfig.resolve,
		alias: {
			...( defaultConfig.resolve?.alias || {} ),
			'analytics':              path.resolve( __dirname, 'src/analytics' ),
			'dashboard':              path.resolve( __dirname, 'src/dashboard' ),
			// Shared StoreSuite primitives — module bundles can `import` from
			// `@storesuite/components` instead of relative paths into core.
			// Today these resolve to core source and get bundled in each
			// module that uses them; future work can flip them to externals
			// without touching module code.
			'@storesuite/components': path.resolve( __dirname, 'src/Components' ),
		},
	},
};
