<?php
/**
 * Tests for the storesuite_term_form_after_fields hook in the six term form templates.
 *
 * @package StoreSuite\Tests
 */

namespace PluginizeLab\StoreSuite\Test;

/**
 * The term SEO integration (and any other extension) injects fields into the
 * category / tag / brand add + edit forms through a single action fired inside
 * each form, right before the submission group. Because every one of those
 * forms submits `new FormData( this )`, a field echoed by the hook is posted
 * automatically — so the contract under test is: the hook fires once per
 * template, with the right taxonomy and term (or null on add forms), inside
 * the form and before the submission group.
 *
 * @group storesuite-taxonomy
 */
class TermFormAfterFieldsTest extends StoreSuiteTestCase {

	/**
	 * Arguments captured from the hook, one entry per fire.
	 *
	 * @var array
	 */
	private $captured = array();

	/**
	 * Render a term form template with a probe callback on the hook.
	 *
	 * @param string $template Template slug for storesuite_get_template_part().
	 * @return string Rendered HTML.
	 */
	private function render_with_probe( $template ) {
		$this->captured = array();

		add_action(
			'storesuite_term_form_after_fields',
			function ( $taxonomy, $term ) {
				$this->captured[] = array( $taxonomy, $term );
				echo '<input type="hidden" name="storesuite_test_probe" value="probe">';
			},
			10,
			2
		);

		ob_start();
		storesuite_get_template_part( $template );
		return ob_get_clean();
	}

	/**
	 * Assert the probe input rendered inside the form, before the submission group.
	 *
	 * @param string $html Rendered template output.
	 */
	private function assert_probe_precedes_submission_group( $html ) {
		$probe = strpos( $html, 'storesuite_test_probe' );
		$group = strpos( $html, 'storesuite-form-submission-group' );
		$close = strpos( $html, '</form>' );

		$this->assertNotFalse( $probe, 'Hook output missing from rendered form.' );
		$this->assertNotFalse( $group, 'Submission group missing from rendered form.' );
		$this->assertNotFalse( $close, 'Form close tag missing from rendered output.' );
		$this->assertLessThan( $group, $probe, 'Hook output must render before the submission group.' );
		$this->assertLessThan( $close, $probe, 'Hook output must render inside the form.' );
	}

	public function test_add_category_form_fires_hook_with_null_term() {
		$html = $this->render_with_probe( 'categories/add-new-category' );

		$this->assertCount( 1, $this->captured );
		$this->assertSame( 'product_cat', $this->captured[0][0] );
		$this->assertNull( $this->captured[0][1] );
		$this->assert_probe_precedes_submission_group( $html );
	}

	public function test_edit_category_form_fires_hook_with_the_term() {
		$term = wp_insert_term( 'Edited Category', 'product_cat' );
		set_query_var( 'edit-category', $term['term_id'] );

		$html = $this->render_with_probe( 'categories/edit-category' );

		$this->assertCount( 1, $this->captured );
		$this->assertSame( 'product_cat', $this->captured[0][0] );
		$this->assertInstanceOf( \WP_Term::class, $this->captured[0][1] );
		$this->assertSame( $term['term_id'], $this->captured[0][1]->term_id );
		$this->assert_probe_precedes_submission_group( $html );
	}

	public function test_add_tag_form_fires_hook_with_null_term() {
		$html = $this->render_with_probe( 'tags/add-new-tag' );

		$this->assertCount( 1, $this->captured );
		$this->assertSame( 'product_tag', $this->captured[0][0] );
		$this->assertNull( $this->captured[0][1] );
		$this->assert_probe_precedes_submission_group( $html );
	}

	public function test_edit_tag_form_fires_hook_with_the_term() {
		$term = wp_insert_term( 'Edited Tag', 'product_tag' );
		set_query_var( 'edit-tag', $term['term_id'] );

		$html = $this->render_with_probe( 'tags/edit-tag' );

		$this->assertCount( 1, $this->captured );
		$this->assertSame( 'product_tag', $this->captured[0][0] );
		$this->assertInstanceOf( \WP_Term::class, $this->captured[0][1] );
		$this->assertSame( $term['term_id'], $this->captured[0][1]->term_id );
		$this->assert_probe_precedes_submission_group( $html );
	}

	public function test_add_brand_form_fires_hook_with_null_term() {
		$html = $this->render_with_probe( 'brands/add-new-brand' );

		$this->assertCount( 1, $this->captured );
		$this->assertSame( 'product_brand', $this->captured[0][0] );
		$this->assertNull( $this->captured[0][1] );
		$this->assert_probe_precedes_submission_group( $html );
	}

	public function test_edit_brand_form_fires_hook_with_the_term() {
		$term = wp_insert_term( 'Edited Brand', 'product_brand' );
		set_query_var( 'edit-brand', $term['term_id'] );

		$html = $this->render_with_probe( 'brands/edit-brand' );

		$this->assertCount( 1, $this->captured );
		$this->assertSame( 'product_brand', $this->captured[0][0] );
		$this->assertInstanceOf( \WP_Term::class, $this->captured[0][1] );
		$this->assertSame( $term['term_id'], $this->captured[0][1]->term_id );
		$this->assert_probe_precedes_submission_group( $html );
	}
}
