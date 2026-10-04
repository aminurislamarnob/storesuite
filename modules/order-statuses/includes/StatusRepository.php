<?php
/**
 * Custom Order Statuses — storage & CRUD.
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Modules\OrderStatuses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persists custom statuses in the `storesuite_custom_order_statuses` option, a
 * map of slug => definition. Slugs are stored WITHOUT the `wc-` prefix
 * (WooCommerce adds it), and validated to <= 17 chars so `wc-{slug}` fits the
 * 20-char post_status limit.
 */
class StatusRepository {

	const OPTION_KEY = 'storesuite_custom_order_statuses';

	/**
	 * All custom statuses, keyed by slug.
	 *
	 * @return array<string,array>
	 */
	public static function all() {
		$stored = get_option( self::OPTION_KEY, array() );
		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * A single status definition, or null.
	 *
	 * @param string $slug Status slug (no wc- prefix).
	 * @return array|null
	 */
	public static function get( $slug ) {
		$all = self::all();
		return $all[ $slug ] ?? null;
	}

	/**
	 * Create or update a status.
	 *
	 * @param array $data {
	 *     @type string   $slug        Slug (<=17 chars). Required for new.
	 *     @type string   $label       Human label. Required.
	 *     @type string   $color       Hex colour (#rrggbb).
	 *     @type bool     $is_paid     Counts as a paid status.
	 *     @type bool     $in_reports  Included in analytics.
	 *     @type string[] $transitions Allowed next slugs (empty = any).
	 * }
	 * @return string|\WP_Error The saved slug or an error.
	 */
	public static function save( array $data ) {
		$label = isset( $data['label'] ) ? sanitize_text_field( $data['label'] ) : '';
		if ( '' === $label ) {
			return new \WP_Error( 'storesuite_status_label', __( 'A status name is required.', 'storesuite' ) );
		}

		$slug = isset( $data['slug'] ) && '' !== $data['slug']
			? sanitize_key( $data['slug'] )
			: sanitize_key( str_replace( '-', '_', sanitize_title( $label ) ) );

		if ( strlen( $slug ) > 17 ) {
			$slug = substr( $slug, 0, 17 );
		}
		if ( '' === $slug ) {
			return new \WP_Error( 'storesuite_status_slug', __( 'Could not derive a valid slug from that name.', 'storesuite' ) );
		}

		// Guard against colliding with a core/other-plugin status.
		$reserved = array_keys( wc_get_order_statuses() );
		if ( ! self::get( $slug ) && in_array( 'wc-' . $slug, $reserved, true ) ) {
			return new \WP_Error( 'storesuite_status_reserved', __( 'That status already exists.', 'storesuite' ) );
		}

		$color = isset( $data['color'] ) ? sanitize_hex_color( $data['color'] ) : '';

		$transitions = array();
		if ( ! empty( $data['transitions'] ) && is_array( $data['transitions'] ) ) {
			foreach ( $data['transitions'] as $t ) {
				$transitions[] = sanitize_key( ltrim( (string) $t, 'wc-' ) );
			}
		}

		$all          = self::all();
		$all[ $slug ] = array(
			'slug'        => $slug,
			'label'       => $label,
			'color'       => $color ? $color : '#94a3b8',
			'is_paid'     => ! empty( $data['is_paid'] ),
			'in_reports'  => ! empty( $data['in_reports'] ),
			'transitions' => array_values( array_unique( array_filter( $transitions ) ) ),
		);

		update_option( self::OPTION_KEY, $all );

		return $slug;
	}

	/**
	 * Delete a status, reassigning any orders in it to a fallback.
	 *
	 * @param string $slug     Status slug.
	 * @param string $fallback Fallback status slug (no wc- prefix).
	 * @return true|\WP_Error
	 */
	public static function delete( $slug, $fallback = 'on-hold' ) {
		$all = self::all();
		if ( ! isset( $all[ $slug ] ) ) {
			return new \WP_Error( 'storesuite_status_missing', __( 'That status does not exist.', 'storesuite' ) );
		}

		self::reassign_orders( $slug, $fallback );

		unset( $all[ $slug ] );
		update_option( self::OPTION_KEY, $all );

		return true;
	}

	/**
	 * Move every order in $from to $to (batched).
	 *
	 * @param string $from Source slug (no wc- prefix).
	 * @param string $to   Destination slug (no wc- prefix).
	 * @return void
	 */
	public static function reassign_orders( $from, $to ) {
		$paged = 1;
		do {
			$orders = wc_get_orders(
				array(
					'status'   => 'wc-' . $from,
					'limit'    => 50,
					'page'     => $paged,
					'return'   => 'ids',
				)
			);
			foreach ( (array) $orders as $order_id ) {
				$order = wc_get_order( $order_id );
				if ( $order ) {
					$order->update_status( $to, __( 'Status removed; order reassigned.', 'storesuite' ) );
				}
			}
			$paged++;
		} while ( ! empty( $orders ) );
	}
}
