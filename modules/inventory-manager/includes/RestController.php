<?php
/**
 * Inventory Manager — REST controller (low-stock widget feed).
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Modules\InventoryManager;

use PluginizeLab\StoreSuite\Cache;
use WP_REST_Controller;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exposes the low-stock list at `storesuite/v1/inventory/low-stock` for the
 * dashboard-home widget. Cached briefly and busted on stock changes.
 */
class RestController extends WP_REST_Controller {

	const CACHE_KEY = 'inventory_low_stock';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace = 'storesuite/v1';
		$this->rest_base = 'inventory';
	}

	/**
	 * Register routes + cache-busting on stock changes. Called on rest_api_init.
	 *
	 * @return void
	 */
	public function register_routes() {
		$permission = array( $this, 'permissions_check' );

		// Paginated stock list for the React screen.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_list' ),
					'permission_callback' => $permission,
					'args'                => $this->list_args(),
				),
			)
		);

		// Set a single item's stock quantity.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/stock',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'set_item_stock' ),
					'permission_callback' => $permission,
					'args'                => array(
						'qty' => array(
							'required'          => true,
							'type'              => 'number',
							'sanitize_callback' => 'wc_stock_amount',
						),
					),
				),
			)
		);

		// Bulk stock update across selected items.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/bulk',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'bulk_update' ),
					'permission_callback' => $permission,
					'args'                => array(
						'ids' => array(
							'required' => true,
							'type'     => 'array',
							'items'    => array( 'type' => 'integer' ),
						),
						'op'  => array(
							'type'    => 'string',
							'enum'    => array( 'set', 'increase', 'decrease' ),
							'default' => 'set',
						),
						'qty' => array(
							'type'              => 'integer',
							'default'           => 0,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		// Movement log.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/log',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_log' ),
					'permission_callback' => $permission,
					'args'                => array(
						'product'  => array(
							'type'              => 'integer',
							'default'           => 0,
							'sanitize_callback' => 'absint',
						),
						'page'     => array(
							'type'              => 'integer',
							'default'           => 1,
							'sanitize_callback' => 'absint',
						),
						'per_page' => array(
							'type'              => 'integer',
							'default'           => 30,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		// Low-stock feed (kept for the dashboard-home widget / banner).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/low-stock',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_low_stock' ),
					'permission_callback' => $permission,
				),
			)
		);
	}

	/**
	 * Argument schema for the list route.
	 *
	 * @return array
	 */
	private function list_args() {
		return array(
			'search'       => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'stock_status' => array(
				'type'    => 'string',
				'enum'    => array( '', 'instock', 'outofstock', 'onbackorder' ),
				'default' => '',
			),
			'low_only'     => array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
			),
			'page'         => array(
				'type'              => 'integer',
				'default'           => 1,
				'sanitize_callback' => 'absint',
			),
			'per_page'     => array(
				'type'              => 'integer',
				'default'           => 20,
				'sanitize_callback' => 'absint',
			),
		);
	}

	/**
	 * Bust the cache when stock changes. Registered from Module::boot().
	 *
	 * @return void
	 */
	public static function register_cache_busting() {
		$bust = function () {
			if ( class_exists( Cache::class ) ) {
				Cache::delete( self::CACHE_KEY );
			}
		};
		add_action( 'woocommerce_product_set_stock', $bust );
		add_action( 'woocommerce_variation_set_stock', $bust );
	}

	/**
	 * @return bool
	 */
	public function permissions_check( $request ) {
		unset( $request );
		return storesuite_current_user_can( 'manage_inventory' );
	}

	/**
	 * GET the paginated stock list.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_list( $request ) {
		$per_page = max( 1, (int) $request->get_param( 'per_page' ) );
		$paged    = max( 1, (int) $request->get_param( 'page' ) );
		$low_only = (bool) $request->get_param( 'low_only' );

		$result = StockRepository::get_paginated(
			array(
				'search'       => (string) $request->get_param( 'search' ),
				'stock_status' => (string) $request->get_param( 'stock_status' ),
				'low_only'     => $low_only,
				'paged'        => $paged,
				'per_page'     => $per_page,
			)
		);

		// Low-stock total for the banner. When already filtering low-only it's
		// just the current total; otherwise run a cheap count-only query.
		if ( $low_only ) {
			$low_total = $result['total'];
		} else {
			$low_total = StockRepository::get_paginated(
				array(
					'low_only' => true,
					'per_page' => 1,
					'paged'    => 1,
				)
			)['total'];
		}

		$response = rest_ensure_response(
			array(
				'items'       => $result['items'],
				'total'       => $result['total'],
				'total_pages' => $result['total_pages'],
				'totals'      => array( 'low' => (int) $low_total ),
			)
		);
		$response->header( 'X-WP-Total', (string) $result['total'] );
		$response->header( 'X-WP-TotalPages', (string) $result['total_pages'] );

		return $response;
	}

	/**
	 * POST — set a single item's stock quantity.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function set_item_stock( $request ) {
		$id  = absint( $request->get_param( 'id' ) );
		$qty = wc_stock_amount( $request->get_param( 'qty' ) );

		$result = StockRepository::set_quantity( $id, $qty, 'manual' );
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 400 ) );
			return $result;
		}

		return rest_ensure_response(
			array(
				'message' => __( 'Stock updated.', 'storesuite' ),
				'item'    => StockRepository::describe( $id ),
			)
		);
	}

	/**
	 * POST — bulk stock update across selected items.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function bulk_update( $request ) {
		$ids = array_map( 'absint', (array) $request->get_param( 'ids' ) );
		$ids = array_filter( $ids );
		$op  = (string) $request->get_param( 'op' );
		$qty = (int) $request->get_param( 'qty' );

		if ( empty( $ids ) ) {
			return new \WP_Error(
				'storesuite_no_products',
				__( 'No products selected.', 'storesuite' ),
				array( 'status' => 400 )
			);
		}

		$updated = 0;
		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product ) {
				continue;
			}

			$current = (int) $product->get_stock_quantity();
			switch ( $op ) {
				case 'increase':
					$new = $current + $qty;
					break;
				case 'decrease':
					$new = max( 0, $current - $qty );
					break;
				case 'set':
				default:
					$new = $qty;
					break;
			}

			if ( ! is_wp_error( StockRepository::set_quantity( $id, $new, 'bulk' ) ) ) {
				++$updated;
			}
		}

		return rest_ensure_response(
			array(
				/* translators: %d: number of products updated */
				'message' => sprintf( _n( '%d product updated.', '%d products updated.', $updated, 'storesuite' ), $updated ),
				'updated' => $updated,
			)
		);
	}

	/**
	 * GET the movement log, with rows enriched for display.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_log( $request ) {
		$result = StockLog::query(
			array(
				'product_id' => absint( $request->get_param( 'product' ) ),
				'paged'      => max( 1, (int) $request->get_param( 'page' ) ),
				'per_page'   => max( 1, (int) $request->get_param( 'per_page' ) ),
			)
		);

		$date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		$items       = array();
		foreach ( $result['items'] as $row ) {
			$product = wc_get_product( (int) $row['product_id'] );
			$user    = get_userdata( (int) $row['user_id'] );

			$items[] = array(
				'id'           => (int) $row['id'],
				'product_id'   => (int) $row['product_id'],
				'product_name' => $product ? $product->get_name() : '#' . (int) $row['product_id'],
				'qty_before'   => null === $row['qty_before'] ? null : (int) $row['qty_before'],
				'qty_after'    => null === $row['qty_after'] ? null : (int) $row['qty_after'],
				'change_type'  => $row['change_type'],
				'reference'    => $row['reference'],
				'user_name'    => $user ? $user->display_name : __( 'System', 'storesuite' ),
				'created_at'   => date_i18n( $date_format, strtotime( $row['created_at'] ) ),
			);
		}

		$response = rest_ensure_response(
			array(
				'items'       => $items,
				'total'       => $result['total'],
				'total_pages' => $result['total_pages'],
			)
		);
		$response->header( 'X-WP-Total', (string) $result['total'] );
		$response->header( 'X-WP-TotalPages', (string) $result['total_pages'] );

		return $response;
	}

	/**
	 * GET the low-stock list (cached 5 minutes).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_low_stock( $request ) {
		$limit = $request->get_param( 'per_page' ) ? absint( $request->get_param( 'per_page' ) ) : 20;

		if ( class_exists( Cache::class ) ) {
			$cached = Cache::get( self::CACHE_KEY );
			if ( is_array( $cached ) ) {
				return rest_ensure_response( array_slice( $cached, 0, $limit ) );
			}
		}

		$result = StockRepository::get_paginated(
			array(
				'low_only' => true,
				'per_page' => 100,
				'paged'    => 1,
			)
		);

		$items = array();
		foreach ( $result['items'] as $item ) {
			$items[] = array(
				'id'        => $item['id'],
				'name'      => $item['name'],
				'sku'       => $item['sku'],
				'stock_qty' => $item['stock_qty'],
				'low_stock' => $item['low_stock'],
			);
		}

		if ( class_exists( Cache::class ) ) {
			Cache::set( self::CACHE_KEY, $items, 5 * MINUTE_IN_SECONDS );
		}

		return rest_ensure_response( array_slice( $items, 0, $limit ) );
	}
}
