<?php

namespace PluginizeLab\StoreSuite\EditHistory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cron and install hooks for the edit history.
 */
class EditHistoryHooks {

	const CLEANUP_HOOK = 'storesuite_edit_history_cleanup';

	/**
	 * The constructor.
	 */
	public function __construct() {
		add_action( 'init', array( EditHistoryInstaller::class, 'maybe_install' ), 5 );
		add_action( self::CLEANUP_HOOK, array( $this, 'cleanup_old_batches' ) );
	}

	/**
	 * Daily cron: delete batches (and their items) older than the retention window.
	 *
	 * @return void
	 */
	public function cleanup_old_batches() {
		( new EditHistoryManager() )->delete_older_than();
	}
}
