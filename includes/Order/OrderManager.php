<?php

namespace PluginizeLab\StoreSuite\Order;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WC_Order;
use Automattic\WooCommerce\Enums\OrderStatus;
use Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore;
use Automattic\WooCommerce\Utilities\OrderUtil;

use function Symfony\Component\VarDumper\Dumper\esc;

/**
 * Plugin order manager class
 */
class OrderManager {

	/**
	 * Get all orders.
	 *
	 * @param int   $orders_per_page Number of orders per page.
	 * @param int   $current_page Current page number.
	 * @param array $filters {
	 *     Filter options for orders query.
	 *
	 *     @type string $search_term Search term to filter orders.
	 *     @type string $search_filter Filter type (all, order_id, customer_email, customers, products).
	 *     @type string $order_status Filter by order status.
	 *     @type string $_customer_user Filter by customer user ID (WooCommerce-style).
	 *     @type string $order_channel Filter by sales channel (online, pos).
	 *     @type string $m WooCommerce-style month filter in YYYYMM format.
	 *     @type string $order_date Legacy presets for date range (today, week, month, quarter, year).
	 * }
	 * @return object
	 */
	public function get_all_orders( $orders_per_page, $current_page, $filters = array() ) {
		$args = array(
			'type'     => 'shop_order',
			'limit'    => $orders_per_page,
			'page'     => $current_page,
			'paginate' => true,
			'order'    => 'DESC',
			'orderby'  => 'date',
			'return'   => 'objects',
		);

		// Set default filter values
		$search_term   = isset( $filters['search_term'] ) ? $filters['search_term'] : '';
		$search_filter = isset( $filters['search_filter'] ) ? $filters['search_filter'] : 'all';
		$order_status  = isset( $filters['order_status'] ) ? $filters['order_status'] : '';
		$customer_id   = isset( $filters['_customer_user'] ) ? (int) $filters['_customer_user'] : 0;
		$order_channel = isset( $filters['order_channel'] ) ? $filters['order_channel'] : '';
		$order_month   = isset( $filters['m'] ) ? $filters['m'] : '';

		$allowed_search_filters = array( 'order_id', 'customer_email', 'customers', 'products', 'all' );
		if ( ! in_array( $search_filter, $allowed_search_filters, true ) ) {
			$search_filter = 'all';
		}

		// Add search parameter if provided.
		if ( ! empty( $search_term ) ) {
			// Follow WooCommerce HPOS search style: always pass the term and selected search filter.
			$args['s']             = $search_term;
			$args['search_filter'] = $search_filter;
		}

		// Filter by order status
		if ( ! empty( $order_status ) ) {
			$args['status'] = 'wc-' . $order_status;
		}

		// Filter by specific customer (WooCommerce-style)
		if ( $customer_id > 0 ) {
			$args['customer'] = $customer_id;
		}

		// Initialize meta_query array if we need to add filters
		$meta_query = isset( $args['meta_query'] ) ? $args['meta_query'] : array();

		// Filter by month (WooCommerce-style "m" param: YYYYMM)
		if ( ! empty( $order_month ) ) {
			$year_month = sanitize_text_field( wp_unslash( $order_month ) );
			if ( preg_match( '/^[0-9]{6}$/', $year_month ) ) {
				$year  = (int) substr( $year_month, 0, 4 );
				$month = (int) substr( $year_month, 4, 2 );
				if ( $month >= 1 && $month <= 12 ) {
					$start_date           = sprintf( '%04d-%02d-01', $year, $month );
					$last_day_of_month    = gmdate( 'Y-m-t', gmmktime( 0, 0, 0, $month, 1, $year ) );
					$args['date_created'] = $start_date . '...' . $last_day_of_month;
				}
			}
		}

		// Filter by sales channel (using WooCommerce created_via arg; supports comma-separated values)
		if ( ! empty( $order_channel ) ) {
			$created_via         = array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( $order_channel ) ) ) );
			$args['created_via'] = $created_via;
		}

		// Set meta_query if we have any
		if ( ! empty( $meta_query ) ) {
			$args['meta_query'] = $meta_query;
		}

		$orders = wc_get_orders( $args );
		return $orders;
	}

	/**
	 * Get month options for date filter (WooCommerce-style), mirroring core list table.
	 *
	 * @return array
	 */
	public function get_months_filter_options() {
		global $wpdb;

		if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$orders_table = esc_sql( OrdersTableDataStore::get_orders_table_name() );
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from WooCommerce core; cannot be a placeholder.
			$min_max_months = $wpdb->get_row(
				$wpdb->prepare(
					"
						SELECT MIN( t.date_created_gmt ) as min_date_gmt,
						       MAX( t.date_created_gmt ) as max_date_gmt
						FROM `{$orders_table}` t
						WHERE type = %s
						AND status != %s
					",
					'shop_order',
					OrderStatus::TRASH
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} else {
			// Legacy (CPT) storage: orders live in wp_posts.
			$min_max_months = $wpdb->get_row(
				$wpdb->prepare(
					"
						SELECT MIN( p.post_date_gmt ) as min_date_gmt,
						       MAX( p.post_date_gmt ) as max_date_gmt
						FROM {$wpdb->posts} p
						WHERE p.post_type = %s
						AND p.post_status != %s
					",
					'shop_order',
					'trash'
				)
			);
		}

		// Normalize "this month" to first day in site timezone.
		$this_month = new \WC_DateTime( 'now', new \DateTimeZone( 'UTC' ) );
		$this_month->setTimezone( wp_timezone() );
		$this_month->setDate( $this_month->format( 'Y' ), $this_month->format( 'm' ), 1 );
		$this_month->setTime( 0, 0 );

		$options = array();

		if ( isset( $min_max_months ) && ! is_null( $min_max_months->min_date_gmt ) ) {
			$start = new \WC_DateTime( $min_max_months->min_date_gmt, new \DateTimeZone( 'UTC' ) );
			$start->setTimezone( wp_timezone() );
			$start->setDate( $start->format( 'Y' ), $start->format( 'm' ), 1 );
			$start->setTime( 0, 0 );

			$end = new \WC_DateTime( $min_max_months->max_date_gmt, new \DateTimeZone( 'UTC' ) );
			$end->setTimezone( wp_timezone() );
			$end->setDate( $end->format( 'Y' ), $end->format( 'm' ), 1 );
			$end->setTime( 0, 0 );

			if ( $start > $this_month ) {
				$start = $this_month;
			}

			if ( $end < $this_month ) {
				$end = $this_month;
			}

			$intervals = new \DatePeriod( $start, new \DateInterval( 'P1M' ), $end );

			foreach ( $intervals as $interval ) {
				$option        = new \stdClass();
				$option->year  = $interval->format( 'Y' );
				$option->month = $interval->format( 'n' );
				$options[]     = $option;
			}

			$option        = new \stdClass();
			$option->year  = $end->format( 'Y' );
			$option->month = $end->format( 'n' );
			$options[]     = $option;
		}

		if ( count( $options ) < 1 ) {
			$option        = new \stdClass();
			$option->year  = $this_month->format( 'Y' );
			$option->month = $this_month->format( 'n' );
			$options[]     = $option;
		}

		return array_reverse( $options );
	}

	/**
	 * Renders the order number.
	 *
	 * @param WC_Order $order The order object for the current row.
	 *
	 * @return void
	 */
	public function get_order_number_column_value( WC_Order $order ): void {
		echo '<a href="' . esc_url( sprintf( storesuite_get_navigation_url( 'order-details' ) . '%s', $order->get_id() ) ) . '" class="order-view">#' . esc_attr( $order->get_order_number() ) . '</a>';
	}

	/**
	 * Renders the order customer name.
	 *
	 * @param WC_Order $order The order object for the current row.
	 *
	 * @return void
	 */
	public function get_order_customer_column_value( WC_Order $order ): void {
		$buyer = '';

		if ( $order->get_billing_first_name() || $order->get_billing_last_name() ) {
			/* translators: 1: first name 2: last name */
			$buyer = trim( sprintf( _x( '%1$s %2$s', 'full name', 'storesuite' ), $order->get_billing_first_name(), $order->get_billing_last_name() ) );
		} elseif ( $order->get_billing_company() ) {
			$buyer = trim( $order->get_billing_company() );
		} elseif ( $order->get_customer_id() ) {
			$user  = get_user_by( 'id', $order->get_customer_id() );
			$buyer = ucwords( $user->display_name );
		}
		echo esc_html( $buyer );
	}

	/**
	 * Renders order billing information.
	 *
	 * @param WC_Order $order The order object for the current row.
	 *
	 * @return void
	 */
	public function get_billing_address_column_value( WC_Order $order ): void {
		$address = $order->get_formatted_billing_address();

		if ( $address ) {
			echo esc_html( preg_replace( '#<br\s*/?>#i', ', ', $address ) );

			if ( $order->get_payment_method() ) {
				/* translators: %s: payment method */
				echo '<span class="description">' . sprintf( esc_html__( 'via %s', 'storesuite' ), esc_html( $order->get_payment_method_title() ) ) . '</span>';
			}
		} else {
			echo '&ndash;';
		}
	}

	/**
	 * Renders order shipping information.
	 *
	 * @param WC_Order $order The order object for the current row.
	 *
	 * @return void
	 */
	public function get_shipping_address_column_value( WC_Order $order ): void {
		$address = $order->get_formatted_shipping_address();

		if ( $address ) {
			echo '<a target="_blank" href="' . esc_url( $order->get_shipping_address_map_url() ) . '">' . esc_html( preg_replace( '#<br\s*/?>#i', ', ', $address ) ) . '</a>';
			if ( $order->get_shipping_method() ) {
				/* translators: %s: shipping method */
				echo '<span class="description">' . sprintf( esc_html__( 'via %s', 'storesuite' ), esc_html( $order->get_shipping_method() ) ) . '</span>';
			}
		} else {
			echo '&ndash;';
		}
	}

	/**
	 * Get the available order actions for a given order.
	 *
	 * @param WC_Order|null $order The order object or null if no order is available.
	 *
	 * @return array
	 */
	public static function get_available_order_actions_for_order( $order ) {
		$actions = array(
			'send_order_details'              => __( 'Send order details to customer', 'storesuite' ),
			'send_order_details_admin'        => __( 'Resend new order notification', 'storesuite' ),
			'regenerate_download_permissions' => __( 'Regenerate download permissions', 'storesuite' ),
		);

		/**
		 * Filter: woocommerce_order_actions
		 * Allows filtering of the available order actions for an order.
		 *
		 * @param array         $actions The available order actions for the order.
		 * @param WC_Order|null $order   The order object or null if no order is available.
		 */
		return apply_filters( 'woocommerce_order_actions', $actions, $order );
	}

	/**
	 * Renders the order date.
	 *
	 * @param WC_Order $order The order object for the current row.
	 *
	 * @return void
	 */
	public function get_order_date_column_value( WC_Order $order ): void {
		$order_timestamp = $order->get_date_created() ? $order->get_date_created()->getTimestamp() : '';

		if ( ! $order_timestamp ) {
			echo '&ndash;';
			return;
		}

		// Check if the order was created within the last 24 hours, and not in the future.
		if ( $order_timestamp > strtotime( '-1 day', time() ) && $order_timestamp <= time() ) {
			$show_date = sprintf(
			/* translators: %s: human-readable time difference */
				_x( '%s ago', '%s = human-readable time difference', 'storesuite' ),
				human_time_diff( $order->get_date_created()->getTimestamp(), time() )
			);
		} else {
			$show_date = $order->get_date_created()->date_i18n( apply_filters( 'woocommerce_admin_order_date_format', __( 'M j, Y', 'storesuite' ) ) ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment
		}
		printf(
			'<time datetime="%1$s" title="%2$s">%3$s</time>',
			esc_attr( $order->get_date_created()->date( 'c' ) ),
			esc_html( $order->get_date_created()->date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ),
			esc_html( $show_date )
		);
	}
}
