<?php
/**
 * Module Name: Custom Order Statuses
 * Description: Create unlimited custom WooCommerce order statuses with colors, transition rules, paid/reporting behaviour and bulk apply — all from the StoreSuite dashboard.
 * Version: 1.0.0
 * Author: StoreSuite
 *
 * @package StoreSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/StatusRepository.php';
require_once __DIR__ . '/includes/Registrar.php';
require_once __DIR__ . '/includes/AjaxController.php';
require_once __DIR__ . '/includes/Module.php';

return new \PluginizeLab\StoreSuite\Modules\OrderStatuses\Module( __FILE__ );
