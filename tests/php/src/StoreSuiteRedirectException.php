<?php
/**
 * Control-flow exception for intercepted redirects.
 *
 * @package StoreSuite\Tests
 */

namespace PluginizeLab\StoreSuite\Test;

/**
 * Thrown from the wp_redirect filter by StoreSuiteFixtures::capture_redirect()
 * so a wp_safe_redirect() + exit code path unwinds back into the test instead
 * of ending the PHP process.
 */
class StoreSuiteRedirectException extends \Exception {
}
