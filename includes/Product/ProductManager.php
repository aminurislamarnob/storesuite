<?php

namespace PluginizeLab\StoreSuite\Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Internal\CostOfGoodsSold\CostOfGoodsSoldController;
use Automattic\WooCommerce\Internal\ProductFeed\Integrations\POSCatalog\POSProductVisibilitySync;
use WP_Error;

/**
 * Plugin product manager class
 */
class ProductManager {
	/**
	 * Innsert new product
	 *
	 * @param array $args
	 *
	 * @return int|bool|WP_Error
	 */
	public function storesuite_save_product( $args ) {
		$defaults = array(
			'post_title'   => '',
			'post_content' => '',
			'post_excerpt' => '',
			'post_status'  => '',
			'post_type'    => 'product',
			'product_tag'  => array(),
			'_visibility'  => 'visible',
		);

		$data = wp_parse_args( $args, $defaults );

		if ( empty( $data['product_title'] ) ) {
			return new WP_Error( 'no-title', __( 'Please enter product title', 'storesuite' ) );
		}

		$post_status = ! empty( $data['post_status'] ) ? sanitize_text_field( $data['post_status'] ) : 'publish';

		if ( ! empty( $data['product_id'] ) ) {
			$post_arr['product_id'] = absint( $data['product_id'] );
			$is_updating            = true;
		} else {
			$is_updating = false;
		}

		// Handle slug.
		$slug_provided = isset( $data['product_slug'] ) && '' !== trim( (string) $data['product_slug'] );
		if ( $slug_provided ) {
			$product_slug = sanitize_title( $data['product_slug'] );
		} else {
			// Auto-generate slug from title.
			$product_slug = sanitize_title( $data['product_title'] );
		}

		// Only block duplicates when the user explicitly chose a slug. When the
		// slug is auto-generated from the title, let WordPress append a numeric
		// suffix on save (product-slug-1, product-slug-2, …) instead of erroring.
		if ( $slug_provided && ! empty( $product_slug ) ) {
			$slug_exists = get_page_by_path( $product_slug, OBJECT, 'product' );
			if ( $slug_exists && ( ! $is_updating || $slug_exists->ID !== $post_arr['product_id'] ) ) {
				return new WP_Error( 'slug-exists', __( 'This slug already exists. Please choose a different slug.', 'storesuite' ) );
			}
		}

		$post_data = array(
			'id'                => $is_updating ? $post_arr['product_id'] : '',
			'name'              => sanitize_text_field( $data['product_title'] ),
			'slug'              => $product_slug,
			'type'              => ! empty( $data['post_type'] ) ? $data['post_type'] : 'simple',
			'description'       => wp_kses_post( $data['product_description'] ),
			'short_description' => wp_kses_post( $data['product_short_description'] ),
			'status'            => $post_status,
		);

		// if ( ! isset( $data['chosen_product_cat'] ) ) {
		// if ( Helper::product_category_selection_is_single() ) {
		// $cat_ids[] = $data['product_cat'];
		// } elseif ( ! empty( $data['product_cat'] ) ) {
		// $cat_ids = array_map( 'absint', (array) $data['product_cat'] );
		// }
		// $post_data['categories'] = $cat_ids;
		// }

		if ( ! empty( $data['product_category'] ) ) {
			$post_data['categories'] = array_map( 'absint', (array) $data['product_category'] );
		}

		$post_data['brands'] = isset( $data['product_brand'] ) ? array_map( 'absint', (array) $data['product_brand'] ) : array();

		if ( isset( $data['product_thumbnail_id'] ) ) {
			$post_data['featured_image_id'] = ! empty( $data['product_thumbnail_id'] ) ? absint( $data['product_thumbnail_id'] ) : '';
		}

		if ( isset( $data['product_image_gallery'] ) ) {
			$post_data['gallery_image_ids'] = ! empty( $data['product_image_gallery'] ) ? wc_clean( $data['product_image_gallery'] ) : '';
		}

		$post_data['tags'] = isset( $data['product_tags'] ) ? array_map( 'absint', (array) $data['product_tags'] ) : array();

		if ( isset( $data['regular_price'] ) ) {
			$post_data['regular_price'] = $data['regular_price'] === '' ? '' : wc_format_decimal( $data['regular_price'] );
		}

		if ( isset( $data['sale_price'] ) ) {
			$post_data['sale_price'] = wc_format_decimal( $data['sale_price'] );
		}

		// Need to implement later
		if ( isset( $data['_sale_price_dates_from'] ) ) {
			$post_data['date_on_sale_from'] = wc_clean( $data['_sale_price_dates_from'] );
		}

		// Need to implement later
		if ( isset( $data['_sale_price_dates_to'] ) ) {
			$post_data['date_on_sale_to'] = wc_clean( $data['_sale_price_dates_to'] );
		}

		if ( isset( $data['_visibility'] ) ) {
			$post_data['visibility'] = wc_clean( $data['_visibility'] );
		}

		if ( isset( $data['weight'] ) ) {
			$post_data['weight'] = wc_clean( $data['weight'] );
		}

		if ( isset( $data['length'] ) ) {
			$post_data['length'] = wc_clean( $data['length'] );
		}

		if ( isset( $data['width'] ) ) {
			$post_data['width'] = wc_clean( $data['width'] );
		}

		if ( isset( $data['height'] ) ) {
			$post_data['height'] = wc_clean( $data['height'] );
		}

		if ( isset( $data['_sku'] ) ) {
			$post_data['_sku'] = wc_clean( wp_unslash( $data['_sku'] ) );
		}

		if ( isset( $data['_global_unique_id'] ) ) {
			$post_data['_global_unique_id'] = wc_clean( wp_unslash( $data['_global_unique_id'] ) );
		}

		if ( isset( $data['_manage_stock'] ) && 'grouped' !== $post_data['type'] ) {
			$post_data['_manage_stock'] = 'yes';
		} else {
			$post_data['_manage_stock'] = 'no';
		}

		if ( isset( $data['_stock_quantity'] ) ) {
			$post_data['_stock_quantity'] = wc_stock_amount( wp_unslash( $data['_stock_quantity'] ) );
		}

		if ( isset( $data['_low_stock_amount'] ) ) {
			$post_data['_low_stock_amount'] = wc_stock_amount( wp_unslash( $data['_low_stock_amount'] ) );
		}

		if ( isset( $data['_backorders'] ) ) {
			$post_data['_backorders'] = wc_clean( wp_unslash( $data['_backorders'] ) );
		}

		if ( isset( $data['_sold_individually'] ) && 'yes' === $data['_sold_individually'] ) {
			$post_data['_sold_individually'] = 'yes';
		} else {
			$post_data['_sold_individually'] = 'no';
		}

		if ( isset( $data['_stock_status'] ) ) {
			$post_data['_stock_status'] = wc_clean( wp_unslash( $data['_stock_status'] ) );
		}

		$post_data['reviews_allowed'] = isset( $data['comment_status'] ) ? wc_clean( wp_unslash( $data['comment_status'] ) ) : 'no';

		if ( isset( $data['_featured'] ) && 'yes' === $data['_featured'] ) {
			// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$post_data['featured'] = 'on';
			// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		} else {
			$post_data['featured'] = 'off';
		}

		$post_data['virtual']      = ( isset( $data['_virtual'] ) && 'yes' === $data['_virtual'] );
		$post_data['downloadable'] = ( isset( $data['_downloadable'] ) && 'yes' === $data['_downloadable'] );

		if ( isset( $data['_product_url'] ) ) {
			$post_data['external_url'] = esc_url_raw( wp_unslash( $data['_product_url'] ) );
		}
		if ( isset( $data['_button_text'] ) ) {
			$post_data['button_text'] = wc_clean( wp_unslash( $data['_button_text'] ) );
		}
		if ( isset( $data['_cogs_value'] ) ) {
			$post_data['cogs_value'] = wc_clean( wp_unslash( $data['_cogs_value'] ) );
		}

		if ( isset( $data['menu_order'] ) ) {
			$post_data['menu_order'] = wc_clean( wp_unslash( $data['menu_order'] ) );
		}

		if ( isset( $data['_purchase_note'] ) ) {
			$post_data['purchase_note'] = wp_kses_post( wp_unslash( $data['_purchase_note'] ) );
		}

		// Upsells - always set even if empty to clear previous values.
		$post_data['upsell_ids'] = isset( $data['upsell_ids'] ) ? array_map( 'intval', (array) wp_unslash( $data['upsell_ids'] ) ) : array();

		// Cross-sells - always set even if empty to clear previous values.
		$post_data['cross_sell_ids'] = isset( $data['crosssell_ids'] ) ? array_map( 'intval', (array) wp_unslash( $data['crosssell_ids'] ) ) : array();

		// Grouped children - always set even if empty to clear previous values.
		$post_data['grouped_products'] = isset( $data['grouped_products'] ) ? array_map( 'intval', (array) wp_unslash( $data['grouped_products'] ) ) : array();

		// Downloadable files and options.
		if ( isset( $data['downloads'] ) ) {
			$post_data['downloads'] = $data['downloads'];
		}
		if ( isset( $data['_download_limit'] ) ) {
			$post_data['download_limit'] = '' === $data['_download_limit'] ? '' : absint( $data['_download_limit'] );
		}
		if ( isset( $data['_download_expiry'] ) ) {
			$post_data['download_expiry'] = '' === $data['_download_expiry'] ? '' : absint( $data['_download_expiry'] );
		}

		// Save shipping class.
		if ( isset( $data['product_shipping_class'] ) && 'external' !== $post_data['type'] ) {
			$post_data['product_shipping_class'] = absint( $data['product_shipping_class'] );
		}

		// Attributes (already prepared in ProductController::sanitize_product_data).
		if ( isset( $data['attributes'] ) ) {
			$post_data['attributes'] = $data['attributes'];
		}

		$product = $this->create_product( $post_data );

		if ( $product && storesuite_is_pos_feature_enabled() ) {
			$visible_in_pos = ! empty( $data['_visible_in_pos'] );
			wc_get_container()->get( POSProductVisibilitySync::class )->set_product_pos_visibility( $product->get_id(), $visible_in_pos );
		}

		if ( ! $is_updating ) {
			do_action( 'storesuite_new_product_added', $product->get_id(), $data );
		} else {
			do_action( 'storesuite_product_updated', $product->get_id(), $data );
		}

		if ( $product ) {
			return $product->get_id();
		}

		return false;
	}

	/**
	 * Create Product
	 *
	 * @throws \WC_Data_Exception
	 * @return WC_Product|null|false
	 */
	public function create_product( $args = array() ) {
		$id = isset( $args['id'] ) ? absint( $args['id'] ) : 0;

		// Using the correct class and methods.
		if ( isset( $args['type'] ) ) {
			$classname = \WC_Product_Factory::get_classname_from_product_type( $args['type'] );

			if ( ! class_exists( $classname ) ) {
				$classname = 'WC_Product_Simple';
			}

			$product = new $classname( $id );
		} elseif ( isset( $args['id'] ) ) {
			$product = wc_get_product( $id );
		} else {
			$product = new \WC_Product_Simple();
		}

		// Post title.
		if ( isset( $args['name'] ) ) {
			$product->set_name( wp_filter_post_kses( $args['name'] ) );
		}

		// Post content.
		if ( isset( $args['description'] ) ) {
			$product->set_description( wp_filter_post_kses( $args['description'] ) );
		}

		// Post excerpt.
		if ( isset( $args['short_description'] ) ) {
			$product->set_short_description( wp_filter_post_kses( $args['short_description'] ) );
		}

		// Post status.
		if ( isset( $args['status'] ) ) {
			$product->set_status( get_post_status_object( $args['status'] ) ? $args['status'] : 'draft' );
		}

		// Post slug.
		if ( isset( $args['slug'] ) ) {
			$product->set_slug( $args['slug'] );
		}

		// Menu order.
		if ( isset( $args['menu_order'] ) ) {
			$product->set_menu_order( $args['menu_order'] );
		}

		// Comment status.
		if ( isset( $args['reviews_allowed'] ) ) {
			$product->set_reviews_allowed( $args['reviews_allowed'] );
		}

		// Virtual.
		if ( isset( $args['virtual'] ) ) {
			$product->set_virtual( $args['virtual'] );
		}

		// Tax status.
		if ( isset( $args['tax_status'] ) ) {
			$product->set_tax_status( $args['tax_status'] );
		}

		// Tax Class.
		if ( isset( $args['tax_class'] ) ) {
			$product->set_tax_class( $args['tax_class'] );
		}

		// Catalog Visibility.
		if ( isset( $args['visibility'] ) ) {
			$product->set_catalog_visibility( $args['visibility'] );
		}

		// Purchase Note.
		if ( isset( $args['purchase_note'] ) ) {
			$product->set_purchase_note( wp_kses_post( wp_unslash( $args['purchase_note'] ) ) );
		}

		// Featured Product.
		if ( isset( $args['featured'] ) ) {
			$product->set_featured( $args['featured'] === 'on' ? true : false );
		}

		// Shipping data.
		$product = $this->save_product_shipping_data( $product, $args );

		// SKU.
		if ( isset( $args['_sku'] ) ) {
			$product->set_sku( wc_clean( $args['_sku'] ) );
		}

		// Unique ID.
		if ( isset( $args['_global_unique_id'] ) ) {
			$product->set_global_unique_id( wc_clean( $args['_global_unique_id'] ) );
		}

		// Attributes.
		if ( isset( $args['attributes'] ) ) {
			$product->set_attributes( $args['attributes'] );
		}

		// Sales and prices.
		if ( in_array( $product->get_type(), array( 'variable', 'grouped' ), true ) ) {
			$product->set_regular_price( '' );
			$product->set_sale_price( '' );
			$product->set_date_on_sale_to( '' );
			$product->set_date_on_sale_from( '' );
			$product->set_price( '' );
		} else {
			// Regular Price.
			if ( isset( $args['regular_price'] ) ) {
				$product->set_regular_price( $args['regular_price'] );
			}

			// Sale Price.
			if ( isset( $args['sale_price'] ) ) {
				$product->set_sale_price( $args['sale_price'] );
			}

			if ( isset( $args['date_on_sale_from'] ) ) {
				$product->set_date_on_sale_from( $args['date_on_sale_from'] );
			}

			if ( isset( $args['date_on_sale_from_gmt'] ) ) {
				$product->set_date_on_sale_from( $args['date_on_sale_from_gmt'] ? strtotime( $args['date_on_sale_from_gmt'] ) : null );
			}

			if ( isset( $args['date_on_sale_to'] ) ) {
				$product->set_date_on_sale_to( $args['date_on_sale_to'] );
			}

			if ( isset( $args['date_on_sale_to_gmt'] ) ) {
				$product->set_date_on_sale_to( $args['date_on_sale_to_gmt'] ? strtotime( $args['date_on_sale_to_gmt'] ) : null );
			}
		}

		// Product parent ID.
		if ( isset( $args['parent_id'] ) ) {
			$product->set_parent_id( $args['parent_id'] );
		}

		// Sold individually.
		if ( isset( $args['_sold_individually'] ) ) {
			$product->set_sold_individually( $args['_sold_individually'] );
		}

		// Stock status; stock_status has priority over in_stock.
		if ( isset( $args['_stock_status'] ) ) {
			$stock_status = $args['_stock_status'];
		} else {
			$stock_status = $product->get_stock_status();
		}

		// Stock data.
		if ( 'yes' === get_option( 'woocommerce_manage_stock' ) ) {
			// Manage stock.
			if ( isset( $args['_manage_stock'] ) ) {
				$product->set_manage_stock( $args['_manage_stock'] );
			}

			// Backorders.
			if ( isset( $args['_backorders'] ) ) {
				$product->set_backorders( $args['_backorders'] );
			}

			if ( $product->is_type( 'grouped' ) ) {
				$product->set_manage_stock( 'no' );
				$product->set_backorders( 'no' );
				$product->set_stock_quantity( '' );
				$product->set_stock_status( $stock_status );
			} elseif ( $product->is_type( 'external' ) ) {
				$product->set_manage_stock( 'no' );
				$product->set_backorders( 'no' );
				$product->set_stock_quantity( '' );
				$product->set_stock_status( 'instock' );
			} elseif ( $product->get_manage_stock() ) {
				// Stock status is always determined by children so sync later.
				if ( ! $product->is_type( 'variable' ) ) {
					$product->set_stock_status( $stock_status );
				}

				// Stock quantity.
				if ( isset( $args['_stock_quantity'] ) ) {
					$product->set_stock_quantity( wc_stock_amount( $args['_stock_quantity'] ) );
				} elseif ( isset( $args['inventory_delta'] ) ) {
					$stock_quantity  = wc_stock_amount( $product->get_stock_quantity() );
					$stock_quantity += wc_stock_amount( $args['inventory_delta'] );
					$product->set_stock_quantity( wc_stock_amount( $stock_quantity ) );
				}

				if ( isset( $args['_low_stock_amount'] ) ) {
					$product->set_low_stock_amount( wc_stock_amount( $args['_low_stock_amount'] ) );
				} else {
					$product->set_low_stock_amount( '' );
				}
			} else {
				// Don't manage stock.
				$product->set_manage_stock( 'no' );
				$product->set_stock_quantity( '' );
				$product->set_stock_status( $stock_status );
				$product->set_low_stock_amount( '' );
			}
		} elseif ( ! $product->is_type( 'variable' ) ) {
			$product->set_stock_status( $stock_status );
		}

		// sync stock status
		$product = $this->maybe_update_stock_status( $product, $stock_status );

		// Upsells.
		if ( isset( $args['upsell_ids'] ) ) {
			$upsells = array();
			$ids     = $args['upsell_ids'];

			if ( ! empty( $ids ) ) {
				foreach ( $ids as $id ) {
					if ( $id && $id > 0 ) {
						$upsells[] = $id;
					}
				}
			}

			$product->set_upsell_ids( $upsells );
		}

		// Cross sells.
		if ( isset( $args['cross_sell_ids'] ) ) {
			$crosssells = array();
			$ids        = $args['cross_sell_ids'];

			if ( ! empty( $ids ) ) {
				foreach ( $ids as $id ) {
					if ( $id && $id > 0 ) {
						$crosssells[] = $id;
					}
				}
			}

			$product->set_cross_sell_ids( $crosssells );
		}

		// Product categories.
		if ( isset( $args['categories'] ) && is_array( $args['categories'] ) ) {
			$product->set_category_ids( $args['categories'] );
		}

		// Product brands. WC_Product::set_brand_ids() arrived in WooCommerce
		// 10.3; older versions get the terms assigned after the save below.
		$legacy_brands = null;
		if ( isset( $args['brands'] ) && is_array( $args['brands'] ) ) {
			if ( method_exists( $product, 'set_brand_ids' ) ) {
				$product->set_brand_ids( $args['brands'] );
			} else {
				$legacy_brands = array_map( 'absint', $args['brands'] );
			}
		}

		// Product tags.
		if ( isset( $args['tags'] ) && is_array( $args['tags'] ) ) {
			$product->set_tag_ids( $args['tags'] );
		}

		// Downloadable.
		if ( isset( $args['downloadable'] ) ) {
			$product->set_downloadable( $args['downloadable'] );
		}

		// Downloadable options.
		if ( $product->get_downloadable() ) {

			// Downloadable files.
			if ( isset( $args['downloads'] ) && is_array( $args['downloads'] ) ) {
				$product = $this->save_downloadable_files( $product, $args['downloads'] );
			}

			// Download limit.
			if ( isset( $args['download_limit'] ) ) {
				$product->set_download_limit( $args['download_limit'] );
			}

			// Download expiry.
			if ( isset( $args['download_expiry'] ) ) {
				$product->set_download_expiry( $args['download_expiry'] );
			}
		}

		// Cost of Goods Sold value.
		if ( wc_get_container()->get( CostOfGoodsSoldController::class )->feature_is_enabled() ) {
			$cogs_value = wc_clean( wp_unslash( $args['cogs_value'] ?? null ) );
			$product->set_cogs_value( is_null( $cogs_value ) ? null : (float) wc_format_decimal( $cogs_value ) );
		}

		// Product url and button text for external products.
		if ( $product->is_type( 'external' ) ) {
			if ( isset( $args['external_url'] ) ) {
				$product->set_product_url( $args['external_url'] );
			}

			if ( isset( $args['button_text'] ) ) {
				$product->set_button_text( $args['button_text'] );
			}
		}

		// Save default attributes for variable products.
		if ( $product->is_type( 'variable' ) ) {
			$product = $this->save_default_attributes( $product, $args );
		}

		// Set children for a grouped product.
		if ( $product->is_type( 'grouped' ) && isset( $args['grouped_products'] ) ) {
			$product->set_children( $args['grouped_products'] );
		}

		// Set featured image id
		if ( ! empty( $args['featured_image_id'] ) ) {
			$product->set_image_id( $args['featured_image_id'] );
		} else {
			$product->set_image_id( '' );
		}

		// Set gallery image ids
		if ( ! empty( $args['gallery_image_ids'] ) ) {
			$product->set_gallery_image_ids( $args['gallery_image_ids'] );
		} else {
			$product->set_gallery_image_ids( array() );
		}

		// Allow set meta_data.
		if ( ! empty( $args['meta_data'] ) && is_array( $args['meta_data'] ) ) {
			foreach ( $args['meta_data'] as $meta ) {
				$product->update_meta_data( $meta['key'], $meta['value'], isset( $meta['id'] ) ? $meta['id'] : '' );
			}
		}

		if ( ! empty( $args['date_created'] ) ) {
			$date = rest_parse_date( $args['date_created'] );

			if ( $date ) {
				$product->set_date_created( $date );
			}
		}

		if ( ! empty( $args['date_created_gmt'] ) ) {
			$date = rest_parse_date( $args['date_created_gmt'], true );

			if ( $date ) {
				$product->set_date_created( $date );
			}
		}

		// Set total sales for newly created product
		if ( ! empty( $id ) ) {
			$product->set_total_sales( 0 );
		}

		$product_id = $product->save();

		if ( null !== $legacy_brands && $product_id ) {
			wp_set_object_terms( $product_id, $legacy_brands, 'product_brand' );
		}

		return wc_get_product( $product_id );
	}

	/**
	 * Save product shipping data.
	 *
	 * @param WC_Product $product Product instance.
	 * @param array      $data    Shipping data.
	 *
	 * @return WC_Product
	 */
	protected function save_product_shipping_data( $product, $data ) {

		if ( isset( $data['virtual'] ) && true === $data['virtual'] ) {
			$product->set_weight( '' );
			$product->set_height( '' );
			$product->set_length( '' );
			$product->set_width( '' );
		} else {
			if ( isset( $data['weight'] ) ) {
				$product->set_weight( $data['weight'] );
			}

			if ( isset( $data['height'] ) ) {
				$product->set_height( $data['height'] );
			}

			if ( isset( $data['width'] ) ) {
				$product->set_width( $data['width'] );
			}

			if ( isset( $data['length'] ) ) {
				$product->set_length( $data['length'] );
			}
		}

		// Set shipping class.
		if ( isset( $data['product_shipping_class'] ) ) {
			$product->set_shipping_class_id( absint( $data['product_shipping_class'] ) );
		} elseif ( isset( $data['shipping_class'] ) ) {
			$data_store        = $product->get_data_store();
			$shipping_class_id = $data_store->get_shipping_class_id_by_slug( wc_clean( $data['shipping_class'] ) );
			$product->set_shipping_class_id( $shipping_class_id );
		}

		return $product;
	}

	/**
	 * Sync stock stats for variable products.
	 *
	 * @param WC_Product $product
	 * @param string     $stock_status
	 *
	 * @return mixed
	 */
	protected function maybe_update_stock_status( $product, $stock_status ) {
		if ( $product->is_type( 'external' ) ) {
			// External products are always in stock.
			$product->set_stock_status( 'instock' );
		} elseif ( isset( $stock_status ) ) {
			if ( $product->is_type( 'variable' ) && ! $product->get_manage_stock() ) {
				// Stock status is determined by children.
				foreach ( $product->get_children() as $child_id ) {
					$child = wc_get_product( $child_id );
					if ( ! $product->get_manage_stock() ) {
						$child->set_stock_status( $stock_status );
						$child->save();
					}
				}
				$product = \WC_Product_Variable::sync( $product, false );
			} else {
				$product->set_stock_status( $stock_status );
			}
		}

		return $product;
	}

	/**
	 * Save downloadable files.
	 *
	 * @param WC_Product $product    Product instance.
	 * @param array      $downloads  Downloads data.
	 *
	 * @return WC_Product
	 */
	protected function save_downloadable_files( $product, $downloads ) {
		$files = array();
		foreach ( $downloads as $index => $file ) {
			if ( empty( $file['file'] ) ) {
				continue;
			}

			$key      = ! empty( $file['download_id'] ) ? $file['download_id'] : wp_generate_uuid4();
			$download = new \WC_Product_Download();
			$download->set_id( $key );
			$download->set_name( $file['name'] ? $file['name'] : wc_get_filename_from_url( $file['file'] ) );
			$download->set_file( apply_filters( 'woocommerce_file_download_path', $file['file'], $product, $key ) );
			$files[] = $download;
		}
		$product->set_downloads( $files );

		return $product;
	}

	/**
	 * Save default attributes.
	 *
	 * @param WC_Product       $product Product instance.
	 * @param \WP_REST_Request $request Request data.
	 *
	 * @return WC_Product
	 */
	public function save_default_attributes( $product, $request ) {
		if ( isset( $request['default_attributes'] ) && is_array( $request['default_attributes'] ) ) {
			$attributes         = $product->get_attributes();
			$default_attributes = array();

			foreach ( $request['default_attributes'] as $attribute ) {
				$attribute_id   = 0;
				$attribute_name = '';

				// Check ID for global attributes or name for product attributes.
				if ( ! empty( $attribute['id'] ) ) {
					$attribute_id   = absint( $attribute['id'] );
					$attribute_name = wc_attribute_taxonomy_name_by_id( $attribute_id );
				} elseif ( ! empty( $attribute['name'] ) ) {
					$attribute_name = sanitize_title( $attribute['name'] );
				}

				if ( ! $attribute_id && ! $attribute_name ) {
					continue;
				}

				if ( isset( $attributes[ $attribute_name ] ) ) {
					$_attribute = $attributes[ $attribute_name ];

					if ( $_attribute['is_variation'] ) {
						$value = isset( $attribute['option'] ) ? wc_clean( stripslashes( $attribute['option'] ) ) : '';

						if ( ! empty( $_attribute['is_taxonomy'] ) ) {
							// If dealing with a taxonomy, we need to get the slug from the name posted to the API.
							$term = get_term_by( 'name', $value, $attribute_name );

							if ( $term && ! is_wp_error( $term ) ) {
								$value = $term->slug;
							} else {
								$value = sanitize_title( $value );
							}
						}

						if ( $value ) {
							$default_attributes[ $attribute_name ] = $value;
						}
					}
				}
			}

			$product->set_default_attributes( $default_attributes );
		}

		return $product;
	}

	/**
	 * Get product brands.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $fields
	 *
	 * @return array
	 */
	public function get_brands( int $product_id, string $fields = 'all' ): array {
		$brands = wp_get_post_terms( $product_id, 'product_brand', array( 'fields' => $fields ) );
		if ( is_wp_error( $brands ) ) {
			return array();
		}

		return $brands;
	}
}
