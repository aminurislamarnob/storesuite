/**
 * Test files that opt into the node environment (via a docblock) have no
 * `window`, but @wordpress/jest-preset-default's setup-globals writes to it.
 * Provide a bare object there; jsdom-environment files are unaffected.
 */
if ( typeof global.window === 'undefined' ) {
	global.window = {};
}
