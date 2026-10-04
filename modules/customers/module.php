<?php
/**
 * Module Name: Customer CRM
 * Description: A customer relationship view inside the StoreSuite dashboard — a searchable, filterable customer list (registered and guest), plus a per-customer profile with purchase history, lifetime value, internal notes and tags.
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
require_once __DIR__ . '/includes/RestController.php';
require_once __DIR__ . '/includes/Assets.php';
require_once __DIR__ . '/includes/Module.php';

return new \PluginizeLab\StoreSuite\Modules\Customers\Module( __FILE__ );
