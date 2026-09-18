<?php
/**
 * Plugin Name: StoreSuite E2E fake AI generator
 * Description: Test-only helper installed by tests/pw/bin/e2e-provision.sh. Supplies a deterministic text generator so the Playwright suite can exercise AI Generate without a real provider. Never ship this file.
 */

add_filter(
	'storesuite_ai_text_generator',
	function () {
		return function ( $prompt, $instruction, $field ) {
			// Regenerate asks for something different from the previous attempt.
			$attempt = false !== strpos( $prompt, '<avoid>' ) ? 2 : 1;

			return sprintf( 'E2E generated %s #%d', str_replace( array( 'yoast_', '_', '-' ), array( '', ' ', ' ' ), $field ), $attempt );
		};
	}
);
