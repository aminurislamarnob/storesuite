<?php
/**
 * Disk-discovery fixture for the filter-merge test.
 *
 * Kept in its own directory (separate from fixtures/modules/) because
 * Manager::discover() uses include_once: each bootstrap file returns its
 * Module instance only on the FIRST inclusion per PHP process, so every test
 * that scans a fixture directory from disk needs its own copy.
 *
 * @package StoreSuite
 */

use PluginizeLab\StoreSuite\Tests\Fixtures\FixtureModule;

return new FixtureModule( 'beta' );
