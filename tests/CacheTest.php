<?php
/**
 * Cache wrapper tests: transient and object-cache backends, key prefixing,
 * defaults, and bulk deletion.
 *
 * @package StoreSuite
 */

namespace PluginizeLab\StoreSuite\Tests;

use PluginizeLab\StoreSuite\Cache;
use WP_UnitTestCase;

/**
 * Tests for PluginizeLab\StoreSuite\Cache.
 *
 * Cache is a static wrapper: transients by default, object cache when
 * `$use_transient` is false. Keys get the `storesuite_` prefix; the object
 * cache additionally uses the `storesuite` group.
 */
class CacheTest extends WP_UnitTestCase {

	/**
	 * Restore the static prefix/group in case a test changed them, and clear
	 * the shared object cache (WP_UnitTestCase already resets transients via
	 * the DB rollback).
	 */
	public function tear_down() {
		Cache::set_prefix( 'storesuite_' );
		Cache::set_group( 'storesuite' );
		wp_cache_flush();
		parent::tear_down();
	}

	public function test_set_and_get_round_trip_via_transients() {
		$this->assertTrue( Cache::set( 'answer', 42 ) );
		$this->assertSame( 42, Cache::get( 'answer' ) );
	}

	public function test_keys_are_stored_with_the_storesuite_prefix() {
		Cache::set( 'answer', 42 );

		$this->assertSame( 42, get_transient( 'storesuite_answer' ), 'Transient must live under the prefixed key.' );
		$this->assertFalse( get_transient( 'answer' ), 'The bare key must not be written.' );
	}

	public function test_get_returns_false_for_a_missing_key() {
		$this->assertFalse( Cache::get( 'never-set' ) );
	}

	public function test_delete_removes_the_entry() {
		Cache::set( 'doomed', 'value' );

		$this->assertTrue( Cache::delete( 'doomed' ) );
		$this->assertFalse( Cache::get( 'doomed' ) );
	}

	public function test_object_cache_backend_uses_the_storesuite_group() {
		Cache::set( 'obj', array( 'a' => 1 ), HOUR_IN_SECONDS, false );

		$this->assertSame( array( 'a' => 1 ), Cache::get( 'obj', false ) );
		$this->assertSame(
			array( 'a' => 1 ),
			wp_cache_get( 'storesuite_obj', 'storesuite' ),
			'Object-cache entries must be prefixed and grouped.'
		);
		// The two backends are independent: nothing was written to transients.
		$this->assertFalse( Cache::get( 'obj' ) );

		$this->assertTrue( Cache::delete( 'obj', false ) );
		$this->assertFalse( Cache::get( 'obj', false ) );
	}

	public function test_get_or_default_falls_back_only_when_the_key_is_missing() {
		$this->assertSame( 'fallback', Cache::get_or_default( 'missing', 'fallback' ) );

		Cache::set( 'present', 'cached' );
		$this->assertSame( 'cached', Cache::get_or_default( 'present', 'fallback' ) );

		// Caveat baked into the API: don't cache `false` through this class.
		// The DB-backed transient store can't represent it (it comes back as
		// '', which then bypasses the default), and with an object cache a
		// stored `false` is indistinguishable from a miss. Pinning the
		// DB-backed behavior here so a change to it is noticed.
		Cache::set( 'falsy', false );
		$this->assertSame( '', Cache::get_or_default( 'falsy', 'fallback' ) );
	}

	public function test_has_reports_presence() {
		$this->assertFalse( Cache::has( 'nope' ) );

		Cache::set( 'yep', 'value' );
		$this->assertTrue( Cache::has( 'yep' ) );
	}

	public function test_delete_many_clears_every_listed_key() {
		Cache::set( 'one', 1 );
		Cache::set( 'two', 2 );
		Cache::set( 'kept', 3 );

		Cache::delete_many( array( 'one', 'two' ) );

		$this->assertFalse( Cache::get( 'one' ) );
		$this->assertFalse( Cache::get( 'two' ) );
		$this->assertSame( 3, Cache::get( 'kept' ) );
	}

	public function test_custom_prefix_is_applied_to_new_entries() {
		Cache::set_prefix( 'custom_' );
		Cache::set( 'key', 'value' );

		$this->assertSame( 'value', get_transient( 'custom_key' ) );
		$this->assertFalse( get_transient( 'storesuite_key' ) );
	}
}
