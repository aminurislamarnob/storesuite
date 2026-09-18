<?php
/**
 * Module Name: Staff Manager
 * Description: Add staff accounts to your store with scoped capabilities so team members can manage products, orders, and coupons from the StoreSuite dashboard without full WordPress admin access.
 * Version: 1.0.0
 * Author: StoreSuite
 *
 * @package StoreSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/Installer.php';
require_once __DIR__ . '/includes/Settings.php';
require_once __DIR__ . '/includes/ScreenController.php';
require_once __DIR__ . '/includes/Module.php';

return new \PluginizeLab\StoreSuite\Modules\StaffManager\Module( __FILE__ );
