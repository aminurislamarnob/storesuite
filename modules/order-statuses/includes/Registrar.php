<?php
/**
 * Custom Order Statuses — WooCommerce/WordPress registration & behaviour.
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Modules\OrderStatuses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Makes the stored custom statuses real: registers the post statuses, injects
 * them into WooCommerce's status list and dropdowns, colours their badges, and
 * applies paid/reporting semantics. HPOS-compatible via the WC filters.
 */
class Registrar {

	/**
	 * Register all hooks. Called from Module::boot().
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_post_statuses' ), 5 );
		add_filter( 'wc_order_statuses', array( $this, 'add_to_wc_statuses' ) );
		add_filter( 'woocommerce_register_shop_order_post_statuses', array( $this, 'add_to_post_statuses' ) );

		// Badge colours on StoreSuite order screens.
		add_filter( 'storesuite_get_order_status_class', array( $this, 'add_badge_classes' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'print_badge_css' ), 30 );

		// Payment + reporting semantics.
		add_filter( 'woocommerce_order_is_paid_statuses', array( $this, 'add_paid_statuses' ) );
		add_filter( 'woocommerce_reports_order_statuses', array( $this, 'add_report_statuses' ) );
		add_filter( 'woocommerce_analytics_excluded_order_statuses', array( $this, 'exclude_non_report_statuses' ) );

		// Bulk apply on the orders list (uses the Phase-0 filter).
		add_filter( 'storesuite_order_bulk_actions', array( $this, 'add_bulk_actions' ) );
	}

	/**
	 * Register each custom status as a WP post status (classic order storage).
	 *
	 * @return void
	 */
	public function register_post_statuses() {
		foreach ( StatusRepository::all() as $status ) {
			register_post_status(
				'wc-' . $status['slug'],
				array(
					'label'                     => $status['label'],
					'public'                    => false,
					'exclude_from_search'       => false,
					'show_in_admin_all_list'    => true,
					'show_in_admin_status_list' => true,
					/* translators: %s: number of orders */
					'label_count'               => _n_noop(
						$status['label'] . ' <span class="count">(%s)</span>',
						$status['label'] . ' <span class="count">(%s)</span>',
						'storesuite'
					),
				)
			);
		}
	}

	/**
	 * Append custom statuses to the WooCommerce status list (drives every
	 * dropdown via wc_get_order_statuses()).
	 *
	 * @param array $statuses Existing wc-prefixed statuses.
	 * @return array
	 */
	public function add_to_wc_statuses( $statuses ) {
		foreach ( StatusRepository::all() as $status ) {
			$statuses[ 'wc-' . $status['slug'] ] = $status['label'];
		}
		return $statuses;
	}

	/**
	 * HPOS: register the statuses with the orders datastore.
	 *
	 * @param array $statuses Existing status registrations.
	 * @return array
	 */
	public function add_to_post_statuses( $statuses ) {
		foreach ( StatusRepository::all() as $status ) {
			$statuses[ 'wc-' . $status['slug'] ] = array(
				'label'                     => $status['label'],
				'public'                    => false,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
			);
		}
		return $statuses;
	}

	/**
	 * Give each custom status a badge class the CSS can target.
	 *
	 * @param array $classes Status slug => badge class.
	 * @return array
	 */
	public function add_badge_classes( $classes ) {
		if ( ! is_array( $classes ) ) {
			return $classes;
		}
		foreach ( StatusRepository::all() as $status ) {
			$classes[ $status['slug'] ] = 'status-' . $status['slug'];
		}
		return $classes;
	}

	/**
	 * Print inline badge colours on StoreSuite order screens.
	 *
	 * @return void
	 */
	public function print_badge_css() {
		if ( ! function_exists( 'storesuite_is_dashboard_page' ) || ! storesuite_is_dashboard_page() ) {
			return;
		}

		$statuses = StatusRepository::all();
		if ( empty( $statuses ) ) {
			return;
		}

		$css = '';
		foreach ( $statuses as $status ) {
			$color = $status['color'];
			$css  .= sprintf(
				'.storesuite-badge-status-%1$s{background:%2$s1a;color:%2$s;}',
				esc_attr( $status['slug'] ),
				esc_attr( $color )
			);
		}

		wp_register_style( 'storesuite-order-statuses-inline', false, array(), STORESUITE_PLUGIN_VERSION );
		wp_enqueue_style( 'storesuite-order-statuses-inline' );
		wp_add_inline_style( 'storesuite-order-statuses-inline', $css );
	}

	/**
	 * Add is_paid statuses so revenue/payment logic treats them as paid.
	 *
	 * @param array $statuses Paid status slugs (no wc- prefix).
	 * @return array
	 */
	public function add_paid_statuses( $statuses ) {
		foreach ( StatusRepository::all() as $status ) {
			if ( ! empty( $status['is_paid'] ) ) {
				$statuses[] = $status['slug'];
			}
		}
		return $statuses;
	}

	/**
	 * Add report-included statuses (legacy reports).
	 *
	 * @param array $statuses Report status slugs (no wc- prefix).
	 * @return array
	 */
	public function add_report_statuses( $statuses ) {
		foreach ( StatusRepository::all() as $status ) {
			if ( ! empty( $status['in_reports'] ) ) {
				$statuses[] = $status['slug'];
			}
		}
		return $statuses;
	}

	/**
	 * Exclude statuses NOT marked for reporting from WC Admin analytics.
	 *
	 * @param array $excluded Excluded status slugs (no wc- prefix).
	 * @return array
	 */
	public function exclude_non_report_statuses( $excluded ) {
		foreach ( StatusRepository::all() as $status ) {
			if ( empty( $status['in_reports'] ) ) {
				$excluded[] = $status['slug'];
			}
		}
		return array_values( array_unique( $excluded ) );
	}

	/**
	 * Append a "mark_{slug}" bulk action per custom status.
	 *
	 * @param array $actions Bulk action value => label.
	 * @return array
	 */
	public function add_bulk_actions( $actions ) {
		if ( ! is_array( $actions ) ) {
			return $actions;
		}
		foreach ( StatusRepository::all() as $status ) {
			/* translators: %s: status label */
			$actions[ 'mark_' . $status['slug'] ] = sprintf( __( 'Change status to %s', 'storesuite' ), $status['label'] );
		}
		return $actions;
	}
}
