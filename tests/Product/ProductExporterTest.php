<?php
/**
 * ProductExporter tests: frontend-safe construction, product-type expansion,
 * and an end-to-end CSV generation pass.
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Tests\Product;

use PluginizeLab\StoreSuite\Product\ProductExporter;
use PluginizeLab\StoreSuite\Product\ProductManager;
use ReflectionProperty;
use WP_UnitTestCase;

/**
 * Tests for PluginizeLab\StoreSuite\Product\ProductExporter.
 *
 * The exporter subclasses WooCommerce's admin CSV exporter but must work on
 * the frontend dashboard, where the admin exporter helpers are unavailable.
 */
class ProductExporterTest extends WP_UnitTestCase {

	/**
	 * Read the protected product_types_to_export property.
	 *
	 * @param ProductExporter $exporter Exporter instance.
	 * @return array
	 */
	private function get_types( ProductExporter $exporter ) {
		$prop = new ReflectionProperty( \WC_Product_CSV_Exporter::class, 'product_types_to_export' );
		$prop->setAccessible( true );
		return $prop->getValue( $exporter );
	}

	public function test_constructor_seeds_every_product_type_plus_variations() {
		$types = $this->get_types( new ProductExporter() );

		foreach ( array( 'simple', 'grouped', 'external', 'variable', 'variation' ) as $type ) {
			$this->assertContains( $type, $types );
		}
	}

	public function test_variable_variation_ui_option_expands_to_both_types() {
		$exporter = new ProductExporter();
		$exporter->set_product_types_to_export( array( 'simple', 'variable-variation' ) );

		$types = $this->get_types( $exporter );
		$this->assertContains( 'simple', $types );
		$this->assertContains( 'variable', $types );
		$this->assertContains( 'variation', $types );
		$this->assertNotContains( 'variable-variation', $types, 'The synthetic UI value must not leak into the query.' );
	}

	public function test_expansion_does_not_duplicate_variation() {
		$exporter = new ProductExporter();
		$exporter->set_product_types_to_export( array( 'variable-variation', 'variation' ) );

		$this->assertSame( 1, count( array_keys( $this->get_types( $exporter ), 'variation', true ) ) );
	}

	public function test_generate_file_exports_matching_products_as_csv() {
		$manager    = new ProductManager();
		$product_id = $manager->storesuite_save_product(
			array(
				'product_title'             => 'Exportable Product',
				'product_description'       => 'Desc',
				'product_short_description' => 'Short',
				'_sku'                      => 'EXPORT-1',
				'regular_price'             => '49',
			)
		);

		$exporter = new ProductExporter();
		$exporter->set_product_types_to_export( array( 'simple' ) );
		$exporter->generate_file();

		$csv = $exporter->get_file();

		$this->assertSame( 100, (int) $exporter->get_percent_complete() );
		$this->assertStringContainsString( 'Exportable Product', $csv );
		$this->assertStringContainsString( 'EXPORT-1', $csv );
		$this->assertStringContainsString( (string) $product_id, $csv );
	}

	public function test_generate_file_skips_products_of_other_types() {
		$manager = new ProductManager();
		$manager->storesuite_save_product(
			array(
				'product_title'             => 'External Only',
				'product_description'       => 'Desc',
				'product_short_description' => 'Short',
				'post_type'                 => 'external',
				'_product_url'              => 'https://example.com',
			)
		);

		$exporter = new ProductExporter();
		$exporter->set_product_types_to_export( array( 'simple' ) );
		$exporter->generate_file();

		$this->assertStringNotContainsString( 'External Only', $exporter->get_file() );
	}
}
