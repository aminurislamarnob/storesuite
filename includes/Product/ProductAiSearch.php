<?php

namespace PluginizeLab\StoreSuite\Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Natural-language search for the Products list.
 *
 * "out-of-stock products under $10" is sent to the WordPress AI Client, which
 * must answer with a strict JSON object using only the filter keys the list
 * already understands. Every key and value is validated against an allow-list
 * built from the live store (categories, brands, product types, statuses), so
 * the AI can only ever produce a filtered list URL the user could have clicked
 * together by hand. Nothing about the catalog is sent to the model beyond
 * the names of taxonomies, types and statuses.
 */
class ProductAiSearch {

	use AiRequestTrait;

	const NONCE_ACTION = '_storesuite_ai_';

	/**
	 * The constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_storesuite_ai_product_search', array( $this, 'handle_search' ) );
	}

	/**
	 * Whether the search box should render at all.
	 *
	 * @return bool
	 */
	public static function is_available() {
		/**
		 * Whether the AI product search is offered. Defaults to the AI Client's text support.
		 *
		 * @param bool $available Availability.
		 */
		return (bool) apply_filters( 'storesuite_ai_search_available', ProductAI::is_text_supported() );
	}

	/**
	 * AJAX: translate a natural-language query into product list filters.
	 *
	 * @return void
	 */
	public function handle_search() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		$this->guard_ai_request(
			self::is_available(),
			__( 'AI search is not available. Connect an AI provider to use this feature.', 'storesuite' )
		);

		$query = isset( $_POST['query'] ) ? sanitize_text_field( wp_unslash( $_POST['query'] ) ) : '';
		$query = trim( $query );

		if ( '' === $query ) {
			wp_send_json_error( array( 'message' => __( 'Describe what you are looking for first.', 'storesuite' ) ) );
		}

		if ( mb_strlen( $query ) > 300 ) {
			wp_send_json_error( array( 'message' => __( 'Keep the search under 300 characters.', 'storesuite' ) ) );
		}

		$raw = $this->ask_model( $query );

		if ( is_wp_error( $raw ) ) {
			wp_send_json_error( array( 'message' => $raw->get_error_message() ) );
		}

		$filters = $this->parse_response( $raw );

		if ( empty( $filters ) ) {
			wp_send_json_error(
				array(
					'reason'  => 'no_filters',
					'message' => __( 'That could not be turned into product filters. Try naming a category, stock status, price or product status.', 'storesuite' ),
				)
			);
		}

		wp_send_json_success(
			array(
				'filters' => $filters,
				'url'     => add_query_arg( $filters, storesuite_get_navigation_url( 'products' ) ),
			)
		);
	}

	/**
	 * Send the query to the AI Client and return the raw text answer.
	 *
	 * Split out so tests can short-circuit the model with a filter.
	 *
	 * @param string $query Natural-language query.
	 * @return string|\WP_Error
	 */
	protected function ask_model( $query ) {
		/**
		 * Short-circuit the AI call. Return a string (the model's JSON answer) to skip the request.
		 *
		 * @param string|null $response Pre-computed answer, or null to call the model.
		 * @param string      $query    The user's query.
		 */
		$pre = apply_filters( 'storesuite_ai_search_pre_response', null, $query );
		if ( is_string( $pre ) ) {
			return $pre;
		}

		try {
			$result = wp_ai_client_prompt( $query )
				->using_system_instruction( $this->get_system_instruction() )
				->generate_text();
		} catch ( \Throwable $e ) {
			return new \WP_Error( 'storesuite_ai_search_failed', __( 'The AI provider did not answer. Please try again.', 'storesuite' ) );
		}

		if ( ! is_string( $result ) || '' === trim( $result ) ) {
			return new \WP_Error( 'storesuite_ai_search_empty', __( 'The AI provider returned nothing. Please try again.', 'storesuite' ) );
		}

		return $result;
	}

	/**
	 * The allow-listed vocabulary the model may use, built from the live store.
	 *
	 * @return array<string, array<string, string>> key => ( value => label ).
	 */
	public function get_vocabulary() {
		$categories = array();
		foreach ( (array) get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'number'     => 200,
			)
		) as $term ) {
			if ( $term instanceof \WP_Term ) {
				$categories[ (string) $term->term_id ] = $term->name;
			}
		}

		$brands = array();
		if ( taxonomy_exists( 'product_brand' ) ) {
			foreach ( (array) get_terms(
				array(
					'taxonomy'   => 'product_brand',
					'hide_empty' => false,
					'number'     => 200,
				)
			) as $term ) {
				if ( $term instanceof \WP_Term ) {
					$brands[ (string) $term->term_id ] = $term->name;
				}
			}
		}

		$statuses = array_intersect_key(
			(array) storesuite_get_post_status(),
			array_flip( apply_filters( 'storesuite_product_listing_post_statuses', array( 'publish', 'draft', 'pending', 'future' ) ) )
		);

		return array(
			'product_cat'   => $categories,
			'product_brand' => $brands,
			'product_type'  => wc_get_product_types(),
			'stock_status'  => wc_get_product_stock_status_options(),
			'post_status'   => array_map( 'strval', $statuses ),
			'orderby'       => array(
				'title' => __( 'Name', 'storesuite' ),
				'date'  => __( 'Date created', 'storesuite' ),
				'price' => __( 'Price', 'storesuite' ),
				'sku'   => __( 'SKU', 'storesuite' ),
				'stock' => __( 'Stock', 'storesuite' ),
			),
		);
	}

	/**
	 * System instruction that pins the model to the JSON contract.
	 *
	 * @return string
	 */
	private function get_system_instruction() {
		$vocab = $this->get_vocabulary();
		$lines = array(
			'You translate a shop manager\'s natural-language request into filters for a WooCommerce product list.',
			'Respond with ONLY a JSON object, no prose, no code fences. Use only these keys, and omit any key the request does not mention:',
			'- "search_by": free text to match against product names and SKUs.',
			'- "product_cat": one category ID from: ' . $this->describe( $vocab['product_cat'] ),
			'- "product_brand": one brand ID from: ' . $this->describe( $vocab['product_brand'] ),
			'- "product_type": one of: ' . $this->describe( $vocab['product_type'] ),
			'- "stock_status": one of: ' . $this->describe( $vocab['stock_status'] ),
			'- "post_status": one of: ' . $this->describe( $vocab['post_status'] ),
			'- "price_min" and "price_max": numbers in the store currency (' . get_woocommerce_currency() . '). "under 10" means price_max 10; "over 50" means price_min 50; "between 10 and 20" sets both.',
			'- "date_from" and "date_to": creation dates as YYYY-MM-DD. Today is ' . current_time( 'Y-m-d' ) . '. "this month" means date_from is the first of this month.',
			'- "orderby": one of: ' . $this->describe( $vocab['orderby'] ) . '; and "order": "asc" or "desc". "cheapest first" means orderby price asc; "newest" means orderby date desc.',
			'Match category and brand names loosely (plurals, case) but always output the ID. If nothing matches, leave the key out. Never invent values.',
			'Example: "out of stock hoodies under $10" -> {"search_by":"hoodie","stock_status":"outofstock","price_max":10}',
		);

		return implode( "\n", $lines );
	}

	/**
	 * Render "value = label" pairs for the instruction.
	 *
	 * @param array<string, string> $map Value => label.
	 * @return string
	 */
	private function describe( array $map ) {
		if ( empty( $map ) ) {
			return '(none available, leave this key out)';
		}

		$pairs = array();
		foreach ( $map as $value => $label ) {
			$pairs[] = $value . ' = ' . wp_strip_all_tags( (string) $label );
		}

		return implode( ', ', $pairs );
	}

	/**
	 * Turn the model's answer into a validated filter array.
	 *
	 * Unknown keys are dropped, every value is checked against the live
	 * allow-list, and numbers and dates are normalised. Returns an empty array
	 * when nothing usable survives.
	 *
	 * @param string $raw Model answer.
	 * @return array<string, string>
	 */
	public function parse_response( $raw ) {
		$raw = trim( (string) $raw );

		// Tolerate code fences and leading prose around the object.
		$start = strpos( $raw, '{' );
		$end   = strrpos( $raw, '}' );
		if ( false === $start || false === $end || $end <= $start ) {
			return array();
		}

		$data = json_decode( substr( $raw, $start, $end - $start + 1 ), true );
		if ( ! is_array( $data ) ) {
			return array();
		}

		$vocab   = $this->get_vocabulary();
		$filters = array();

		if ( isset( $data['search_by'] ) && is_scalar( $data['search_by'] ) ) {
			$search = sanitize_text_field( (string) $data['search_by'] );
			if ( '' !== $search ) {
				$filters['search_by'] = mb_substr( $search, 0, 100 );
			}
		}

		foreach ( array( 'product_cat', 'product_brand', 'product_type', 'stock_status', 'post_status', 'orderby' ) as $key ) {
			if ( ! isset( $data[ $key ] ) || ! is_scalar( $data[ $key ] ) ) {
				continue;
			}
			$value = (string) $data[ $key ];
			if ( isset( $vocab[ $key ][ $value ] ) ) {
				$filters[ $key ] = $value;
			}
		}

		foreach ( array( 'price_min', 'price_max' ) as $key ) {
			if ( ! isset( $data[ $key ] ) || ! is_scalar( $data[ $key ] ) ) {
				continue;
			}
			$number = preg_replace( '/[^0-9.]/', '', (string) $data[ $key ] );
			if ( '' !== $number && is_numeric( $number ) && (float) $number >= 0 ) {
				$filters[ $key ] = wc_format_decimal( $number );
			}
		}

		foreach ( array( 'date_from', 'date_to' ) as $key ) {
			if ( ! isset( $data[ $key ] ) || ! is_scalar( $data[ $key ] ) ) {
				continue;
			}
			$date = (string) $data[ $key ];
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
				$parsed = \DateTime::createFromFormat( 'Y-m-d', $date );
				if ( $parsed && $parsed->format( 'Y-m-d' ) === $date ) {
					$filters[ $key ] = $date;
				}
			}
		}

		if ( isset( $filters['orderby'] ) ) {
			$order            = isset( $data['order'] ) && is_scalar( $data['order'] ) ? strtolower( (string) $data['order'] ) : 'asc';
			$filters['order'] = 'desc' === $order ? 'desc' : 'asc';
		}

		/**
		 * Filters produced by the AI search after validation.
		 *
		 * @param array  $filters Validated filters.
		 * @param array  $data    Raw decoded model answer.
		 */
		return apply_filters( 'storesuite_ai_search_filters', $filters, $data );
	}
}
