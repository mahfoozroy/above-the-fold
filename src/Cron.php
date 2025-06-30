<?php
/**
 * Cron Handler Class *
 * @package AboveTheFoldLinkTracker\Cron
 */

namespace ABOVE_THE_FOLD_LINK_TRACKER;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages WP-Cron jobs for the plugin.
 */
class Cron {

	/**
	 * Database handler instance.
	 *
	 * @var Database
	 */
	private $db;

	/**
	 * Constructor.
	 *
	 * @param Database $database Instance of the Database class.
	 */
	public function __construct( Database $database ) {
		$this->db = $database;
		$this->add_hooks();
	}

	/**
	 * Adds WordPress hooks for cron events.
	 */
	private function add_hooks() {
		add_action( Core::CRON_HOOK, [ $this, 'cleanup_old_data_task' ] );
		// Activation and deactivation hooks handle scheduling/clearing.
	}

	/**
	 * Schedules the cron event if it's not already scheduled.
	 * This is typically called on plugin activation.
	 */
	public static function schedule_event() {
		if ( ! wp_next_scheduled( Core::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'daily', Core::CRON_HOOK );
		}
	}

	/**
	 * Clears the scheduled cron event.
	 * This is typically called on plugin deactivation.	 */
	public static function clear_scheduled_event() {
		wp_clear_scheduled_hook( Core::CRON_HOOK );
	}


	/**
	 * The actual task performed by the cron job: cleans up old data.
	 */
	public function cleanup_old_data_task() {
		// First, clean up orphaned links whose parent visits no longer exist.
		// This replaces the functionality of the ON DELETE CASCADE foreign key.
		$this->db->delete_orphaned_links();		// Then, delete the old visits.
		$this->db->delete_old_visits();
	}
}
