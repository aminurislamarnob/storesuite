/**
 * Global setup: idempotently prepare the WordPress site for the E2E run.
 *
 * Uses wp-cli against the Herd install (two directories up from the plugin)
 * to guarantee the plugin is active, rewrites are flushed, the three E2E
 * users exist, and the seed catalog/order data is in place. Everything is
 * create-if-missing so repeated runs are cheap and never duplicate data.
 */
const { execFileSync } = require( 'child_process' );
const path = require( 'path' );

const WP_ROOT = path.resolve( __dirname, '..', '..', '..', '..', '..' );

/**
 * Run a wp-cli command in the WordPress root and return trimmed stdout.
 *
 * @param {string[]} args wp-cli arguments.
 * @return {string} stdout.
 */
function wp( args ) {
	return execFileSync( 'wp', args, { cwd: WP_ROOT, encoding: 'utf8' } ).trim();
}

module.exports = async () => {
	wp( [ 'plugin', 'activate', 'storesuite', '--quiet' ] );

	// The inventory, dashboard-nav and modules-admin specs assume the
	// Inventory Manager module is active. Activate through the Manager so
	// its activate() hook (schema install) runs like it would from the UI.
	wp( [
		'eval',
		"pluginizelab_storesuite()->modules->activate( 'inventory-manager' );",
	] );

	wp( [ 'rewrite', 'flush', '--quiet' ] );

	const users = [
		[ 'e2e-admin', 'e2e-admin@example.test', 'administrator', 'E2E!admin#2026' ],
		[ 'e2e-manager', 'e2e-manager@example.test', 'shop_manager', 'E2E!manager#2026' ],
		[ 'e2e-customer', 'e2e-customer@example.test', 'customer', 'E2E!customer#2026' ],
	];

	for ( const [ login, email, role, pass ] of users ) {
		try {
			wp( [ 'user', 'get', login, '--field=ID' ] );
		} catch ( e ) {
			wp( [ 'user', 'create', login, email, `--role=${ role }`, `--user_pass=${ pass }` ] );
		}
	}

	// Seed catalog + order data through WooCommerce's own APIs.
	wp( [ 'eval-file', path.join( __dirname, 'seed.php' ) ] );
};
