<?php

namespace PluginizeLab\StoreSuite\ListTable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Show/hide columns on the dashboard list tables, per user.
 *
 * Each list table registers a column model (key, label, locked). Templates
 * tag every header and cell with `storesuite_list_column_attrs()`; hidden
 * columns get the `hidden` attribute. The per-user set of hidden keys lives
 * in user meta and is saved from the "Columns" toolbar dropdown over AJAX.
 */
class ColumnManager {

	const USER_META_KEY = 'storesuite_hidden_columns';

	const NONCE_ACTION = 'storesuite_columns';

	/**
	 * Per-request cache of hidden keys, table => keys[].
	 *
	 * @var array<string, string[]>|null
	 */
	private static $hidden_cache = null;

	/**
	 * The constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_storesuite_save_hidden_columns', array( $this, 'handle_save' ) );
	}

	/**
	 * Column models for every list table.
	 *
	 * @return array<string, array<string, array{label:string,locked?:bool}>>
	 */
	public static function get_all_columns() {
		$columns = array(
			'products'   => array(
				'image'    => array( 'label' => __( 'Image', 'storesuite' ) ),
				'name'     => array(
					'label'  => __( 'Name', 'storesuite' ),
					'locked' => true,
				),
				'category' => array( 'label' => __( 'Category', 'storesuite' ) ),
				'status'   => array( 'label' => __( 'Status', 'storesuite' ) ),
				'sku'      => array( 'label' => __( 'SKU', 'storesuite' ) ),
				'stock'    => array( 'label' => __( 'Stock', 'storesuite' ) ),
				'price'    => array( 'label' => __( 'Price', 'storesuite' ) ),
				'type'     => array( 'label' => __( 'Type', 'storesuite' ) ),
			),
			'inventory'  => array(
				'image'        => array( 'label' => __( 'Image', 'storesuite' ) ),
				'name'         => array(
					'label'  => __( 'Name', 'storesuite' ),
					'locked' => true,
				),
				'sku'          => array( 'label' => __( 'SKU', 'storesuite' ) ),
				'stock_qty'    => array( 'label' => __( 'Stock Qty', 'storesuite' ) ),
				'stock_status' => array( 'label' => __( 'Stock Status', 'storesuite' ) ),
				'backorders'   => array( 'label' => __( 'Backorders', 'storesuite' ) ),
				'price'        => array( 'label' => __( 'Price', 'storesuite' ) ),
			),
			'orders'     => array(
				'order'    => array(
					'label'  => __( 'Order', 'storesuite' ),
					'locked' => true,
				),
				'status'   => array( 'label' => __( 'Status', 'storesuite' ) ),
				'total'    => array( 'label' => __( 'Order Total', 'storesuite' ) ),
				'items'    => array( 'label' => __( 'Total Items', 'storesuite' ) ),
				'customer' => array( 'label' => __( 'Customer', 'storesuite' ) ),
				'phone'    => array( 'label' => __( 'Billing Phone', 'storesuite' ) ),
				'date'     => array( 'label' => __( 'Date', 'storesuite' ) ),
			),
			'coupons'    => array(
				'code'        => array(
					'label'  => __( 'Code', 'storesuite' ),
					'locked' => true,
				),
				'type'        => array( 'label' => __( 'Type', 'storesuite' ) ),
				'amount'      => array( 'label' => __( 'Amount', 'storesuite' ) ),
				'description' => array( 'label' => __( 'Description', 'storesuite' ) ),
				'usage'       => array( 'label' => __( 'Usage / Limit', 'storesuite' ) ),
				'expiry'      => array( 'label' => __( 'Expiry Date', 'storesuite' ) ),
				'status'      => array( 'label' => __( 'Status', 'storesuite' ) ),
			),
			'categories' => array(
				'image'       => array( 'label' => __( 'Image', 'storesuite' ) ),
				'name'        => array(
					'label'  => __( 'Name', 'storesuite' ),
					'locked' => true,
				),
				'description' => array( 'label' => __( 'Description', 'storesuite' ) ),
				'parent'      => array( 'label' => __( 'Parent', 'storesuite' ) ),
				'slug'        => array( 'label' => __( 'Slug', 'storesuite' ) ),
				'count'       => array( 'label' => __( 'Count', 'storesuite' ) ),
			),
			'tags'       => array(
				'name'        => array(
					'label'  => __( 'Name', 'storesuite' ),
					'locked' => true,
				),
				'description' => array( 'label' => __( 'Description', 'storesuite' ) ),
				'slug'        => array( 'label' => __( 'Slug', 'storesuite' ) ),
				'count'       => array( 'label' => __( 'Count', 'storesuite' ) ),
			),
			'brands'     => array(
				'image'       => array( 'label' => __( 'Image', 'storesuite' ) ),
				'name'        => array(
					'label'  => __( 'Name', 'storesuite' ),
					'locked' => true,
				),
				'description' => array( 'label' => __( 'Description', 'storesuite' ) ),
				'parent'      => array( 'label' => __( 'Parent', 'storesuite' ) ),
				'slug'        => array( 'label' => __( 'Slug', 'storesuite' ) ),
				'count'       => array( 'label' => __( 'Count', 'storesuite' ) ),
			),
		);

		/**
		 * Column models of the dashboard list tables.
		 *
		 * @param array $columns table => ( key => array( label, locked ) ).
		 */
		return apply_filters( 'storesuite_list_table_columns', $columns );
	}

	/**
	 * Column model for one table.
	 *
	 * @param string $table Table key.
	 * @return array<string, array{label:string,locked?:bool}>
	 */
	public static function get_columns( $table ) {
		$all = self::get_all_columns();

		return isset( $all[ $table ] ) ? $all[ $table ] : array();
	}

	/**
	 * Whether a column may be hidden.
	 *
	 * @param string $table Table key.
	 * @param string $key   Column key.
	 * @return bool
	 */
	public static function is_locked( $table, $key ) {
		$columns = self::get_columns( $table );

		return ! empty( $columns[ $key ]['locked'] );
	}

	/**
	 * Hidden column keys for a user and table.
	 *
	 * @param string   $table   Table key.
	 * @param int|null $user_id User ID (current user by default).
	 * @return string[]
	 */
	public static function get_hidden( $table, $user_id = null ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		if ( ! $user_id ) {
			return array();
		}

		if ( null === self::$hidden_cache || ! isset( self::$hidden_cache['__user'] ) || self::$hidden_cache['__user'] !== $user_id ) {
			$stored             = get_user_meta( $user_id, self::USER_META_KEY, true );
			self::$hidden_cache = is_array( $stored ) ? $stored : array();
			self::$hidden_cache['__user'] = $user_id;
		}

		$hidden  = isset( self::$hidden_cache[ $table ] ) && is_array( self::$hidden_cache[ $table ] ) ? self::$hidden_cache[ $table ] : array();
		$columns = self::get_columns( $table );

		// Only keys that still exist and are not locked count as hidden.
		return array_values(
			array_filter(
				array_map( 'sanitize_key', $hidden ),
				function ( $key ) use ( $columns ) {
					return isset( $columns[ $key ] ) && empty( $columns[ $key ]['locked'] );
				}
			)
		);
	}

	/**
	 * Whether a column is visible for the current user.
	 *
	 * @param string $table Table key.
	 * @param string $key   Column key.
	 * @return bool
	 */
	public static function is_visible( $table, $key ) {
		return ! in_array( $key, self::get_hidden( $table ), true );
	}

	/**
	 * Persist the hidden keys for a user and table.
	 *
	 * @param string   $table   Table key.
	 * @param string[] $hidden  Column keys to hide.
	 * @param int|null $user_id User ID (current user by default).
	 * @return string[] Hidden keys actually stored.
	 */
	public static function save_hidden( $table, array $hidden, $user_id = null ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		$columns = self::get_columns( $table );

		$hidden = array_values(
			array_unique(
				array_filter(
					array_map( 'sanitize_key', $hidden ),
					function ( $key ) use ( $columns ) {
						return isset( $columns[ $key ] ) && empty( $columns[ $key ]['locked'] );
					}
				)
			)
		);

		$stored = get_user_meta( $user_id, self::USER_META_KEY, true );
		$stored = is_array( $stored ) ? $stored : array();

		if ( empty( $hidden ) ) {
			unset( $stored[ $table ] );
		} else {
			$stored[ $table ] = $hidden;
		}

		if ( empty( $stored ) ) {
			delete_user_meta( $user_id, self::USER_META_KEY );
		} else {
			update_user_meta( $user_id, self::USER_META_KEY, $stored );
		}

		self::$hidden_cache = null;

		return $hidden;
	}

	/**
	 * AJAX: save the hidden columns of one table for the current user.
	 *
	 * @return void
	 */
	public function handle_save() {
		check_ajax_referer( self::NONCE_ACTION, 'security' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'storesuite' ) ), 403 );
		}

		$table = isset( $_POST['table'] ) ? sanitize_key( wp_unslash( $_POST['table'] ) ) : '';
		if ( ! $table || ! self::get_columns( $table ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown list table.', 'storesuite' ) ), 400 );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Keys sanitized in save_hidden().
		$hidden = isset( $_POST['hidden'] ) ? (array) wp_unslash( $_POST['hidden'] ) : array();

		wp_send_json_success(
			array(
				'table'  => $table,
				'hidden' => self::save_hidden( $table, $hidden ),
			)
		);
	}
}
