<?php
/**
 * Module Name: Inventory Manager
 * Description: Full stock control for the StoreSuite dashboard — a stock list across all product types with inline and bulk quantity updates, a low-stock view, a stock movement log, and low-stock email alerts.
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
require_once __DIR__ . '/includes/StockRepository.php';
require_once __DIR__ . '/includes/StockLog.php';
require_once __DIR__ . '/includes/RestController.php';
// The WC_Email subclasses in includes/Emails/ are required lazily by the
// Manager once WooCommerce's mailer (and the WC_Email base class) is loaded.
require_once __DIR__ . '/includes/Emails/Manager.php';
require_once __DIR__ . '/includes/Module.php';

return new \PluginizeLab\StoreSuite\Modules\InventoryManager\Module( __FILE__ );
