<?php
/**
 * Base test case for StoreSuite integration tests.
 *
 * @package StoreSuite\Tests
 */

namespace PluginizeLab\StoreSuite\Test;

/**
 * Base class for StoreSuite tests that do not dispatch admin-ajax requests.
 *
 * Every test gets pretty permalinks and fresh admin / shop manager / customer
 * user fixtures ($this->admin_id, $this->shop_manager_id, $this->customer_id).
 */
abstract class StoreSuiteTestCase extends \WP_UnitTestCase {

	use StoreSuiteFixtures;

	/**
	 * Set up the test fixture.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->set_permalink_structure( '/%postname%/' );
		$this->create_storesuite_users();
	}

	/**
	 * Tear down the test fixture.
	 *
	 * @return void
	 */
	public function tear_down() {
		parent::tear_down();

		$this->reset_role_singleton();
		$this->reset_rest_server();
	}
}
