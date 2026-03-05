<?php
/**
 * File: includes/class-tbfbkm-deactivator.php
 * Version: 6.9.6 (Big King Media Cleanup)
 * Description: Handles cleanup tasks when the plugin is deactivated.
 */

if ( ! defined('ABSPATH') ) exit;

class TBFBKM_Deactivator {

	public static function deactivate() {
		// --- 1. Flush Rewrite Rules ---
		// This removes the /photo/ gallery rules from WordPress so they stop working immediately.
		// Note: We do NOT delete the database tables here. Data is preserved until uninstall.
		flush_rewrite_rules();

		// --- 2. Clear Cron Jobs (If any were scheduled) ---
		// Currently, the Indexer runs on-demand, but if we add cron schedules later,
		// this is where we would unschedule them.
		wp_clear_scheduled_hook( 'tbfbkm_cron_sync' );
	}
}
