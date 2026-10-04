<?php
/**
 * Customer CRM — REST controller for notes & tags.
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Modules\Customers;

use WP_Error;
use WP_REST_Controller;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exposes per-customer notes and tags to the CRM React app under
 * `storesuite/v1/customers/*`. All rows key on the WooCommerce Analytics
 * customer id so guests are supported.
 */
class RestController extends WP_REST_Controller {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace = 'storesuite/v1';
		$this->rest_base = 'customers';
	}

	/**
	 * Register routes. Called on rest_api_init.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/notes',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_notes' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_note' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/notes/(?P<note_id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_note' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/tags',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_customer_tags' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'set_customer_tags' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/tags',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_all_tags' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);
	}

	/**
	 * Permission gate — the CRM management capability.
	 *
	 * @return bool
	 */
	public function permissions_check( $request ) {
		unset( $request );
		return storesuite_current_user_can( 'manage_customers' );
	}

	/* -----------------------------------------------------------------
	 * Notes
	 * --------------------------------------------------------------- */

	/**
	 * GET notes for a customer.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_notes( $request ) {
		global $wpdb;
		$customer_id = (int) $request['id'];
		$table       = Installer::notes_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE customer_id = %d ORDER BY created_at DESC", $customer_id ), ARRAY_A );

		$notes = array();
		foreach ( (array) $rows as $row ) {
			$author  = get_userdata( (int) $row['author_id'] );
			$notes[] = array(
				'id'          => (int) $row['id'],
				'note'        => $row['note'],
				'author'      => $author ? $author->display_name : __( 'System', 'storesuite' ),
				'created_at'  => $row['created_at'],
				'created_h'   => mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row['created_at'] ),
			);
		}

		return rest_ensure_response( $notes );
	}

	/**
	 * POST a new note.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function create_note( $request ) {
		global $wpdb;
		$customer_id = (int) $request['id'];
		$note        = sanitize_textarea_field( (string) $request->get_param( 'note' ) );

		if ( '' === trim( $note ) ) {
			return new WP_Error( 'storesuite_empty_note', __( 'The note cannot be empty.', 'storesuite' ), array( 'status' => 400 ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert(
			Installer::notes_table(),
			array(
				'customer_id' => $customer_id,
				'author_id'   => get_current_user_id(),
				'note'        => $note,
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s' )
		);

		return rest_ensure_response( array( 'id' => (int) $wpdb->insert_id ) );
	}

	/**
	 * DELETE a note.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function delete_note( $request ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( Installer::notes_table(), array( 'id' => (int) $request['note_id'] ), array( '%d' ) );
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	/* -----------------------------------------------------------------
	 * Tags
	 * --------------------------------------------------------------- */

	/**
	 * GET all defined tags.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_all_tags() {
		return rest_ensure_response( self::all_tags() );
	}

	/**
	 * GET a customer's tags.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_customer_tags( $request ) {
		global $wpdb;
		$customer_id = (int) $request['id'];
		$tags        = Installer::tags_table();
		$rel         = Installer::tag_rel_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT t.* FROM {$tags} t INNER JOIN {$rel} r ON r.tag_id = t.id WHERE r.customer_id = %d ORDER BY t.name ASC", $customer_id ), ARRAY_A );

		return rest_ensure_response( self::format_tags( $rows ) );
	}

	/**
	 * PUT a customer's full tag set (by tag names; unknown names are created).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function set_customer_tags( $request ) {
		global $wpdb;
		$customer_id = (int) $request['id'];
		$names       = (array) $request->get_param( 'tags' );
		$rel         = Installer::tag_rel_table();

		$tag_ids = array();
		foreach ( $names as $name ) {
			$name = sanitize_text_field( (string) $name );
			if ( '' === trim( $name ) ) {
				continue;
			}
			$tag_ids[] = self::ensure_tag( $name );
		}
		$tag_ids = array_values( array_unique( array_filter( $tag_ids ) ) );

		// Replace the relation set for this customer.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $rel, array( 'customer_id' => $customer_id ), array( '%d' ) );
		foreach ( $tag_ids as $tag_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->insert( $rel, array( 'customer_id' => $customer_id, 'tag_id' => $tag_id ), array( '%d', '%d' ) );
		}

		return $this->get_customer_tags( $request );
	}

	/* -----------------------------------------------------------------
	 * Helpers
	 * --------------------------------------------------------------- */

	/**
	 * Every defined tag.
	 *
	 * @return array
	 */
	private static function all_tags() {
		global $wpdb;
		$tags = Installer::tags_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( "SELECT * FROM {$tags} ORDER BY name ASC", ARRAY_A );
		return self::format_tags( $rows );
	}

	/**
	 * Ensure a tag exists by name, returning its id.
	 *
	 * @param string $name Tag name.
	 * @return int
	 */
	private static function ensure_tag( $name ) {
		global $wpdb;
		$tags = Installer::tags_table();
		$slug = sanitize_title( $name );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$tags} WHERE slug = %s", $slug ) );
		if ( $existing ) {
			return (int) $existing;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert( $tags, array( 'name' => $name, 'slug' => $slug ), array( '%s', '%s' ) );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Shape tag rows for the API.
	 *
	 * @param array $rows DB rows.
	 * @return array
	 */
	private static function format_tags( $rows ) {
		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[] = array(
				'id'    => (int) $row['id'],
				'name'  => $row['name'],
				'slug'  => $row['slug'],
				'color' => $row['color'],
			);
		}
		return $out;
	}
}
