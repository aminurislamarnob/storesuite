/**
 * PostCSS config.
 *
 * `@wordpress/scripts` only injects its built-in PostCSS options when no user
 * config exists, so once this file is present it owns the pipeline for every
 * entry (admin, analytics, dashboard, and the module bundles). We therefore
 * reproduce the wp-scripts default chain exactly — the preset, plus cssnano
 * with wp-scripts' fallback preset in production — and prepend Tailwind.
 *
 * Reproducing cssnano here (not just the preset) is required for the existing
 * SCSS bundles to build byte-identically; verified by diffing analytics /
 * dashboard built CSS before and after adding this file.
 *
 * `@tailwindcss/postcss` is a no-op on stylesheets that don't `@import`
 * Tailwind, so only the inventory module bundle is affected.
 *
 * Mirrors node_modules/@wordpress/scripts/config/webpack.config.js.
 */
const isProduction = process.env.NODE_ENV === 'production';

const plugins = [
	require( '@tailwindcss/postcss' ),
	...require( '@wordpress/postcss-plugins-preset' ),
];

if ( isProduction ) {
	plugins.push(
		require( 'cssnano' )( {
			preset: [
				'default',
				{
					discardComments: { removeAll: true },
				},
			],
		} )
	);
}

module.exports = { plugins };
