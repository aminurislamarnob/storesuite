<?php
/**
 * Disk-discovery fixture: a minimal valid module bootstrap.
 *
 * Because Manager::discover() uses include_once, this file returns its Module
 * instance only on the FIRST inclusion per PHP process — exactly one test may
 * discover this directory from disk.
 *
 * @package StoreSuite
 */

use PluginizeLab\StoreSuite\Tests\Fixtures\FixtureModule;

return new FixtureModule( 'alpha' );
