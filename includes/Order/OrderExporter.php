<?php

namespace PluginizeLab\StoreSuite\Order;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// WooCommerce loads its exporters only in wp-admin; pull the batch base in for the frontend dashboard.
if ( ! class_exists( '\WC_CSV_Batch_Exporter', false ) && defined( 'WC_ABSPATH' ) ) {
	include_once WC_ABSPATH . 'includes/export/abstract-wc-csv-batch-exporter.php';
}

/**
 * Batched order CSV exporter.
 *
 * WooCommerce ships no order exporter, so this extends its batch CSV base the
 * same way the product export does. One row per order; line items are
 * flattened into a single "Items" column. Rows are selected by status, date
 * range or an explicit list of order IDs.
 */
class OrderExporter extends \WC_CSV_Batch_Exporter {

	/**
	 * Type of export used in filter names.
	 *
	 * @var string
	 */
	protected $export_type = 'order';

	/**
	 * Statuses to export (without the wc- prefix). Empty means all.
	 *
	 * @var string[]
	 */
	protected $statuses_to_export = array();

	/**
	 * Inclusive creation-date range as Y-m-d strings. Empty means unbounded.
	 *
	 * @var array{0:string,1:string}
	 */
	protected $date_range = array( '', '' );

	/**
	 * Explicit order IDs to export. Empty means "use the filters".
	 *
	 * @var int[]
	 */
	protected $order_ids_to_export = array();

	/**
	 * Restrict the export to these statuses.
	 *
	 * @param string[] $statuses Statuses without the wc- prefix.
	 * @return void
	 */
	public function set_statuses_to_export( $statuses ) {
		$known = array_keys( wc_get_order_statuses() );

		$this->statuses_to_export = array_values(
			array_filter(
				array_map(
					function ( $status ) use ( $known ) {
						$status = 'wc-' . preg_replace( '/^wc-/', '', wc_clean( $status ) );
						return in_array( $status, $known, true ) ? $status : '';
					},
					(array) $statuses
				)
			)
		);
	}

	/**
	 * Restrict the export to orders created inside a date range.
	 *
	 * @param string $from Y-m-d or empty.
	 * @param string $to   Y-m-d or empty.
	 * @return void
	 */
	public function set_date_range( $from, $to ) {
		$this->date_range = array( self::normalize_date( $from ), self::normalize_date( $to ) );
	}

	/**
	 * Restrict the export to an explicit list of orders.
	 *
	 * @param int[] $order_ids Order IDs.
	 * @return void
	 */
	public function set_order_ids_to_export( $order_ids ) {
		$this->order_ids_to_export = array_values( array_filter( array_map( 'absint', (array) $order_ids ) ) );
	}

	/**
	 * Validate a Y-m-d date string.
	 *
	 * @param string $date Raw date.
	 * @return string Y-m-d or empty when invalid.
	 */
	private static function normalize_date( $date ) {
		$date = trim( (string) $date );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return '';
		}
		$parsed = \DateTime::createFromFormat( 'Y-m-d', $date );
		return $parsed && $parsed->format( 'Y-m-d' ) === $date ? $date : '';
	}

	/**
	 * Default column ids and their labels.
	 *
	 * @return array<string, string>
	 */
	public function get_default_column_names() {
		return apply_filters(
			"storesuite_{$this->export_type}_export_default_columns",
			array(
				'order_id'             => __( 'Order ID', 'storesuite' ),
				'order_number'         => __( 'Order number', 'storesuite' ),
				'status'               => __( 'Status', 'storesuite' ),
				'date_created'         => __( 'Date created', 'storesuite' ),
				'date_paid'            => __( 'Date paid', 'storesuite' ),
				'date_completed'       => __( 'Date completed', 'storesuite' ),
				'customer_id'          => __( 'Customer ID', 'storesuite' ),
				'customer_email'       => __( 'Customer email', 'storesuite' ),
				'billing_first_name'   => __( 'Billing first name', 'storesuite' ),
				'billing_last_name'    => __( 'Billing last name', 'storesuite' ),
				'billing_company'      => __( 'Billing company', 'storesuite' ),
				'billing_address_1'    => __( 'Billing address 1', 'storesuite' ),
				'billing_address_2'    => __( 'Billing address 2', 'storesuite' ),
				'billing_city'         => __( 'Billing city', 'storesuite' ),
				'billing_state'        => __( 'Billing state', 'storesuite' ),
				'billing_postcode'     => __( 'Billing postcode', 'storesuite' ),
				'billing_country'      => __( 'Billing country', 'storesuite' ),
				'billing_phone'        => __( 'Billing phone', 'storesuite' ),
				'shipping_first_name'  => __( 'Shipping first name', 'storesuite' ),
				'shipping_last_name'   => __( 'Shipping last name', 'storesuite' ),
				'shipping_company'     => __( 'Shipping company', 'storesuite' ),
				'shipping_address_1'   => __( 'Shipping address 1', 'storesuite' ),
				'shipping_address_2'   => __( 'Shipping address 2', 'storesuite' ),
				'shipping_city'        => __( 'Shipping city', 'storesuite' ),
				'shipping_state'       => __( 'Shipping state', 'storesuite' ),
				'shipping_postcode'    => __( 'Shipping postcode', 'storesuite' ),
				'shipping_country'     => __( 'Shipping country', 'storesuite' ),
				'payment_method_title' => __( 'Payment method', 'storesuite' ),
				'shipping_method'      => __( 'Shipping method', 'storesuite' ),
				'items'                => __( 'Items', 'storesuite' ),
				'item_count'           => __( 'Item count', 'storesuite' ),
				'coupons'              => __( 'Coupons', 'storesuite' ),
				'subtotal'             => __( 'Subtotal', 'storesuite' ),
				'discount_total'       => __( 'Discount total', 'storesuite' ),
				'shipping_total'       => __( 'Shipping total', 'storesuite' ),
				'total_tax'            => __( 'Tax total', 'storesuite' ),
				'total'                => __( 'Order total', 'storesuite' ),
				'currency'             => __( 'Currency', 'storesuite' ),
				'customer_note'        => __( 'Customer note', 'storesuite' ),
				'created_via'          => __( 'Created via', 'storesuite' ),
			)
		);
	}

	/**
	 * Load the page of orders for the current step.
	 *
	 * @return void
	 */
	public function prepare_data_to_export() {
		$args = array(
			'limit'    => $this->get_limit(),
			'page'     => $this->get_page(),
			'orderby'  => 'date',
			'order'    => 'DESC',
			'paginate' => true,
			'type'     => 'shop_order',
			'status'   => ! empty( $this->statuses_to_export ) ? $this->statuses_to_export : array_keys( wc_get_order_statuses() ),
		);

		if ( ! empty( $this->order_ids_to_export ) ) {
			$args['post__in'] = $this->order_ids_to_export;
		} else {
			list( $from, $to ) = $this->date_range;
			if ( $from && $to ) {
				$args['date_created'] = $from . '...' . $to;
			} elseif ( $from ) {
				$args['date_created'] = '>=' . $from;
			} elseif ( $to ) {
				$args['date_created'] = '<=' . $to;
			}
		}

		$results = wc_get_orders( apply_filters( "storesuite_{$this->export_type}_export_query_args", $args ) );

		$this->total_rows = $results->total;
		$this->row_data   = array();

		foreach ( $results->orders as $order ) {
			$this->row_data[] = $this->generate_row_data( $order );
		}
	}

	/**
	 * Build one CSV row for an order.
	 *
	 * @param \WC_Order $order Order.
	 * @return array<string, mixed>
	 */
	protected function generate_row_data( $order ) {
		$columns = $this->get_column_names();
		$row     = array();

		foreach ( $columns as $column_id => $column_name ) {
			$column_id = strstr( $column_id, ':' ) ? current( explode( ':', $column_id ) ) : $column_id;
			$value     = '';

			if ( ! $this->is_column_exporting( $column_id ) ) {
				continue;
			}

			if ( has_filter( "storesuite_{$this->export_type}_export_column_{$column_id}" ) ) {
				$value = apply_filters( "storesuite_{$this->export_type}_export_column_{$column_id}", '', $order, $column_id );
			} elseif ( method_exists( $this, "get_column_value_{$column_id}" ) ) {
				$value = $this->{"get_column_value_{$column_id}"}( $order );
			} elseif ( is_callable( array( $order, "get_{$column_id}" ) ) ) {
				$value = $order->{"get_{$column_id}"}( 'edit' );
			}

			$row[ $column_id ] = $value;
		}

		return apply_filters( "storesuite_{$this->export_type}_export_row_data", $row, $order );
	}

	/**
	 * Order ID column.
	 *
	 * @param \WC_Order $order Order.
	 * @return int
	 */
	protected function get_column_value_order_id( $order ) {
		return $order->get_id();
	}

	/**
	 * Status column, without the wc- prefix.
	 *
	 * @param \WC_Order $order Order.
	 * @return string
	 */
	protected function get_column_value_status( $order ) {
		return $order->get_status();
	}

	/**
	 * Customer email column (billing email).
	 *
	 * @param \WC_Order $order Order.
	 * @return string
	 */
	protected function get_column_value_customer_email( $order ) {
		return $order->get_billing_email();
	}

	/**
	 * Date created column.
	 *
	 * @param \WC_Order $order Order.
	 * @return string
	 */
	protected function get_column_value_date_created( $order ) {
		return $this->format_date( $order->get_date_created() );
	}

	/**
	 * Date paid column.
	 *
	 * @param \WC_Order $order Order.
	 * @return string
	 */
	protected function get_column_value_date_paid( $order ) {
		return $this->format_date( $order->get_date_paid() );
	}

	/**
	 * Date completed column.
	 *
	 * @param \WC_Order $order Order.
	 * @return string
	 */
	protected function get_column_value_date_completed( $order ) {
		return $this->format_date( $order->get_date_completed() );
	}

	/**
	 * Shipping method titles, comma separated.
	 *
	 * @param \WC_Order $order Order.
	 * @return string
	 */
	protected function get_column_value_shipping_method( $order ) {
		return $order->get_shipping_method();
	}

	/**
	 * Line items flattened as "Name × qty" entries.
	 *
	 * @param \WC_Order $order Order.
	 * @return string
	 */
	protected function get_column_value_items( $order ) {
		$items = array();

		foreach ( $order->get_items() as $item ) {
			$sku     = '';
			$product = is_callable( array( $item, 'get_product' ) ) ? $item->get_product() : null;
			if ( $product && $product->get_sku() ) {
				$sku = ' [' . $product->get_sku() . ']';
			}
			$items[] = sprintf( '%s%s × %s', $item->get_name(), $sku, $item->get_quantity() );
		}

		return $this->implode_values( $items );
	}

	/**
	 * Total quantity across line items.
	 *
	 * @param \WC_Order $order Order.
	 * @return int
	 */
	protected function get_column_value_item_count( $order ) {
		return $order->get_item_count();
	}

	/**
	 * Coupon codes used, comma separated.
	 *
	 * @param \WC_Order $order Order.
	 * @return string
	 */
	protected function get_column_value_coupons( $order ) {
		return $this->implode_values( $order->get_coupon_codes() );
	}

	/**
	 * Subtotal (line items before discounts).
	 *
	 * @param \WC_Order $order Order.
	 * @return string
	 */
	protected function get_column_value_subtotal( $order ) {
		return wc_format_decimal( $order->get_subtotal(), wc_get_price_decimals() );
	}

	/**
	 * Format a WC_DateTime for the sheet.
	 *
	 * @param \WC_DateTime|null $date Date.
	 * @return string
	 */
	private function format_date( $date ) {
		return $date ? $date->date( 'Y-m-d H:i:s' ) : '';
	}
}
