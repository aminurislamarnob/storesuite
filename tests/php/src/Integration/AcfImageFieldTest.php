<?php
/**
 * Tests for the ACF image type on the product form.
 *
 * @package StoreSuite\Tests
 */

namespace PluginizeLab\StoreSuite\Test\Integration;

use PluginizeLab\StoreSuite\Integration\Acf\AcfIntegration;
use PluginizeLab\StoreSuite\Integration\Acf\FieldRenderer;
use PluginizeLab\StoreSuite\Integration\Acf\FieldSanitizer;
use PluginizeLab\StoreSuite\Test\StoreSuiteAjaxTestCase;

/**
 * @covers \PluginizeLab\StoreSuite\Integration\Acf\FieldSanitizer
 * @covers \PluginizeLab\StoreSuite\Integration\Acf\FieldRenderer
 * @group storesuite-acf
 * @group storesuite-ajax
 */
class AcfImageFieldTest extends StoreSuiteAjaxTestCase {

	use AcfTestHelpers;

	const GROUP_KEY = 'group_storesuite_image';

	/**
	 * Tear down the test fixture.
	 *
	 * @return void
	 */
	public function tear_down() {
		$this->remove_acf_field_groups();
		parent::tear_down();
	}

	/**
	 * Create an image attachment from the WP test suite's sample image.
	 *
	 * @return int Attachment ID.
	 */
	private function create_image_attachment() {
		return self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
	}

	/**
	 * Register a group with two independent image fields.
	 *
	 * @return void
	 */
	private function register_image_group() {
		$this->register_acf_field_group(
			array(
				'key'      => self::GROUP_KEY,
				'title'    => 'Images',
				'fields'   => array(
					array(
						'key'          => 'field_ss_img_front',
						'name'         => 'ss_img_front',
						'label'        => 'Front view',
						'type'         => 'image',
						'preview_size' => 'thumbnail',
						'mime_types'   => 'jpg, png',
					),
					array(
						'key'   => 'field_ss_img_back',
						'name'  => 'ss_img_back',
						'label' => 'Back view',
						'type'  => 'image',
					),
				),
				'location' => $this->acf_product_location(),
			)
		);
	}

	// -------------------------------------------------------------------------
	// Sanitiser and helpers (no ACF needed).
	// -------------------------------------------------------------------------

	public function test_sanitizer_accepts_only_existing_image_attachments() {
		$sanitizer = new FieldSanitizer();
		$field     = array( 'type' => 'image' );
		$image_id  = $this->create_image_attachment();
		$post_id   = self::factory()->post->create();
		$file_id   = self::factory()->attachment->create_object(
			array(
				'file'           => 'doc.pdf',
				'post_mime_type' => 'application/pdf',
				'post_parent'    => 0,
			)
		);

		$this->assertSame( $image_id, $sanitizer->sanitize( $field, (string) $image_id ) );
		$this->assertSame( '', $sanitizer->sanitize( $field, '' ), 'Empty clears the image.' );
		$this->assertSame( '', $sanitizer->sanitize( $field, '0' ) );
		$this->assertNull( $sanitizer->sanitize( $field, (string) $post_id ), 'A non-attachment post is rejected.' );
		$this->assertNull( $sanitizer->sanitize( $field, (string) $file_id ), 'A non-image attachment is rejected.' );
		$this->assertNull( $sanitizer->sanitize( $field, '999999' ), 'A missing attachment is rejected.' );
		$this->assertNull( $sanitizer->sanitize( $field, 'abc' ) );
		$this->assertNull( $sanitizer->sanitize( $field, '-5' ) );
		$this->assertNull( $sanitizer->sanitize( $field, array( $image_id ) ) );
	}

	public function test_mime_types_are_resolved_from_acf_extensions() {
		$renderer = new FieldRenderer( new AcfIntegration() );

		$this->assertSame( array( 'image/jpeg', 'image/png' ), $renderer->get_mime_types( 'jpg, PNG' ) );
		$this->assertSame( array( 'image/jpeg' ), $renderer->get_mime_types( 'jpg,jpeg' ), 'Aliases collapse to one MIME type.' );
		$this->assertSame( array( 'image/gif' ), $renderer->get_mime_types( '.gif, nope' ), 'Unknown extensions are ignored.' );
		$this->assertSame( array(), $renderer->get_mime_types( '' ) );
	}

	// -------------------------------------------------------------------------
	// Rendering.
	// -------------------------------------------------------------------------

	public function test_pickers_render_independently_with_their_settings() {
		$this->require_acf();
		$this->register_image_group();

		$product_id = self::factory()->product->create()->get_id();
		$image_id   = $this->create_image_attachment();
		update_field( 'field_ss_img_front', $image_id, $product_id );

		$html = $this->render_acf_cards( $product_id );

		$this->assertSame( 2, substr_count( $html, 'data-storesuite-media-picker' ) );
		$this->assertMatchesRegularExpression( '/data-target="#storesuite_acf_field_ss_img_front"[^>]*data-preview-size="thumbnail"[^>]*data-mime-types="image\/jpeg,image\/png"/', $html );
		$this->assertMatchesRegularExpression( '/data-target="#storesuite_acf_field_ss_img_back"[^>]*data-preview-size="medium"[^>]*data-mime-types=""/', $html );

		// Filled picker: id in the hidden input, thumbnail preview, remove label.
		$this->assertStringContainsString( 'name="storesuite_acf[field_ss_img_front]" value="' . $image_id . '"', $html );
		$this->assertStringContainsString( wp_get_attachment_image_url( $image_id, 'thumbnail' ), $html );
		$this->assertMatchesRegularExpression( '/field_ss_img_front.*?image-drop-container storesuite-media-picker-drop image-drop-bg.*?Remove Image/s', $html );

		// Empty picker: hidden input still present (the key must be submitted), upload label.
		$this->assertStringContainsString( 'name="storesuite_acf[field_ss_img_back]" value=""', $html );
		$this->assertMatchesRegularExpression( '/field_ss_img_back.*?image-drop-container storesuite-media-picker-drop".*?Upload Image/s', $html );
	}

	// -------------------------------------------------------------------------
	// Saving.
	// -------------------------------------------------------------------------

	public function test_image_can_be_set_replaced_and_removed() {
		$this->require_acf();
		$this->register_image_group();

		$product_id = self::factory()->product->create()->get_id();
		$first      = $this->create_image_attachment();
		$second     = $this->create_image_attachment();
		$this->_setRole( 'shop_manager' );

		$edit = function ( array $acf ) use ( $product_id ) {
			return $this->do_ajax(
				'storesuite_edit_product_action',
				array(
					'product_id'     => $product_id,
					'product_title'  => get_the_title( $product_id ),
					'storesuite_acf' => $acf,
				),
				'_storesuite_edit_product_',
				'storesuite_edit_product_nonce'
			);
		};

		$this->assertTrue( $edit( array( 'field_ss_img_front' => (string) $first ) )['success'] );
		$this->assertSame( (string) $first, get_post_meta( $product_id, 'ss_img_front', true ) );
		$this->assertSame( 'field_ss_img_front', get_post_meta( $product_id, '_ss_img_front', true ) );
		$this->assertSame( $first, get_field( 'field_ss_img_front', $product_id )['ID'], 'ACF returns the image array for the stored id.' );

		$this->assertTrue( $edit( array( 'field_ss_img_front' => (string) $second ) )['success'] );
		$this->assertSame( (string) $second, get_post_meta( $product_id, 'ss_img_front', true ) );

		$this->assertTrue( $edit( array( 'field_ss_img_front' => '999999' ) )['success'] );
		$this->assertSame( (string) $second, get_post_meta( $product_id, 'ss_img_front', true ), 'A bogus id leaves the image alone.' );

		$this->assertTrue( $edit( array( 'field_ss_img_front' => '' ) )['success'] );
		$this->assertSame( '', get_post_meta( $product_id, 'ss_img_front', true ), 'Removing the image clears the stored value.' );
		$this->assertSame( '', get_post_meta( $product_id, 'ss_img_back', true ), 'The other picker is untouched.' );
	}
}
