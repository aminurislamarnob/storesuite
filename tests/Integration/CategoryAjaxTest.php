<?php
/**
 * Category AJAX integration tests: the full add / edit / delete form flow
 * through wp_ajax_storesuite_*_product_category — nonce, capability check,
 * validation, persistence, and lifecycle actions.
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Tests\Integration;

/**
 * Exercises CategoryController exactly as the frontend dashboard forms do.
 */
class CategoryAjaxTest extends StoreSuiteAjaxTestCase {

	/**
	 * Log in as a shop manager — the primary dashboard persona.
	 */
	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );
	}

	public function test_add_category_persists_term_meta_and_fires_created_action() {
		$parent   = self::factory()->term->create( array(
			'taxonomy' => 'product_cat',
			'slug'     => 'clothing',
		) );
		$fired    = 0;
		add_action(
			'storesuite_product_category_created',
			function () use ( &$fired ) {
				++$fired;
			}
		);

		$response = $this->dispatch(
			'storesuite_add_product_category',
			$this->nonce_field( 'add_product_category' ) + array(
				'product_category_name'         => 'Hoodies',
				'product_category_slug'         => 'hoodies',
				'product_parent_category'       => 'clothing',
				'product_category_description'  => 'Warm things.',
				'product_category_thumbnail_id' => 123,
				'display_type'                  => 'products',
			)
		);

		$this->assertTrue( $response['success'], 'Add must succeed for a shop manager with a valid nonce.' );

		$term = get_term_by( 'slug', 'hoodies', 'product_cat' );
		$this->assertNotFalse( $term );
		$this->assertSame( 'Hoodies', $term->name );
		$this->assertSame( $parent, $term->parent );
		$this->assertSame( 'Warm things.', $term->description );
		$this->assertSame( '123', get_term_meta( $term->term_id, 'thumbnail_id', true ) );
		$this->assertSame( 'products', get_term_meta( $term->term_id, 'display_type', true ) );
		$this->assertSame( 1, $fired );
	}

	public function test_add_category_without_valid_nonce_is_rejected_and_persists_nothing() {
		$response = $this->dispatch(
			'storesuite_add_product_category',
			array(
				'storesuite_add_product_category_nonce' => 'forged',
				'product_category_name'                 => 'Sneaky',
			)
		);

		$this->assertFalse( $response['success'] );
		$this->assertFalse( get_term_by( 'name', 'Sneaky', 'product_cat' ) );
	}

	public function test_add_category_requires_manage_woocommerce() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'customer' ) ) );

		$response = $this->dispatch(
			'storesuite_add_product_category',
			$this->nonce_field( 'add_product_category' ) + array(
				'product_category_name' => 'Customer Cat',
			)
		);

		$this->assertFalse( $response['success'] );
		$this->assertFalse( get_term_by( 'name', 'Customer Cat', 'product_cat' ) );
	}

	public function test_add_category_rejects_duplicate_slug() {
		self::factory()->term->create( array(
			'taxonomy' => 'product_cat',
			'slug'     => 'taken',
		) );

		$response = $this->dispatch(
			'storesuite_add_product_category',
			$this->nonce_field( 'add_product_category' ) + array(
				'product_category_name' => 'Another',
				'product_category_slug' => 'taken',
			)
		);

		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'slug already exists', $response['data']['error'] );
	}

	public function test_add_category_requires_a_name() {
		$response = $this->dispatch(
			'storesuite_add_product_category',
			$this->nonce_field( 'add_product_category' )
		);

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Category Name is required', $response['data']['error'] );
	}

	public function test_edit_category_updates_fields_and_clears_meta_when_emptied() {
		$term_id = self::factory()->term->create( array(
			'taxonomy' => 'product_cat',
			'name'     => 'Old Name',
			'slug'     => 'old-name',
		) );
		update_term_meta( $term_id, 'thumbnail_id', 55 );

		$response = $this->dispatch(
			'storesuite_edit_product_category',
			$this->nonce_field( 'edit_product_category' ) + array(
				'category_id'                  => $term_id,
				'product_category_name'        => 'New Name',
				'product_category_slug'        => 'new-name',
				'product_category_description' => 'Updated.',
			)
		);

		$this->assertTrue( $response['success'] );

		$term = get_term( $term_id, 'product_cat' );
		$this->assertSame( 'New Name', $term->name );
		$this->assertSame( 'new-name', $term->slug );
		$this->assertSame( 'Updated.', $term->description );
		$this->assertSame( '', get_term_meta( $term_id, 'thumbnail_id', true ), 'Omitting the thumbnail clears it.' );
	}

	public function test_delete_category_removes_the_term_and_fires_deleted_action() {
		$term_id    = self::factory()->term->create( array( 'taxonomy' => 'product_cat' ) );
		$deleted_id = null;
		add_action(
			'storesuite_product_category_deleted',
			function ( $id ) use ( &$deleted_id ) {
				$deleted_id = $id;
			}
		);

		// Note: delete uses its own nonce action stem.
		$response = $this->dispatch(
			'storesuite_delete_product_category',
			array(
				'storesuite_delete_product_category_nonce' => wp_create_nonce( '_storesuite_delete_nonce_' ),
				'id' => $term_id,
			)
		);

		$this->assertTrue( $response['success'] );
		$this->assertNull( get_term( $term_id, 'product_cat' ) );
		$this->assertSame( $term_id, $deleted_id );
	}

	public function test_delete_category_with_unknown_id_errors() {
		$response = $this->dispatch(
			'storesuite_delete_product_category',
			array(
				'storesuite_delete_product_category_nonce' => wp_create_nonce( '_storesuite_delete_nonce_' ),
				'id' => 999999,
			)
		);

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Invalid category ID', $response['data']['error'] );
	}
}
