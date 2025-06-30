<?php
/**
 * Database Handler Class
 *
 * @package AboveTheFoldLinkTracker\Database
 */

namespace ABOVE_THE_FOLD_LINK_TRACKER;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** * Handles all database interactions for the plugin.
 */
class Database {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Initialization for the database class, if any.
	}

	/**	 * Get the visits table name with WordPress prefix.
	 *
	 * @return string
	 */
	public static function get_visits_table_name() {
		global $wpdb;
		return $wpdb->prefix . Core::VISITS_TABLE_NAME_SUFFIX;
	}

	/**
	 * Get the links table name with WordPress prefix.
	 *
	 * @return string
	 */
	public static function get_links_table_name() {
		global $wpdb;
		return $wpdb->prefix . Core::LINKS_TABLE_NAME_SUFFIX;
	}

	/**
	 * Creates the necessary database tables on plugin activation.
	 */
	public static function create_tables() {
		global $wpdb;		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$visits_table_name = self::get_visits_table_name();
		$links_table_name = self::get_links_table_name();

		$sql_visits = "CREATE TABLE $visits_table_name (			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			visit_time DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			screen_width INT(10) UNSIGNED NOT NULL,
			screen_height INT(10) UNSIGNED NOT NULL,
			user_agent VARCHAR(255) DEFAULT '' NOT NULL,
			PRIMARY KEY  (id),
			KEY visit_time_idx (visit_time)
		) $charset_collate;";
		// The dbDelta call for $sql_visits was missing in your provided file structure for create_tables.		// It was only calling dbDelta($sql_links). Ensuring $sql_visits is also processed by dbDelta.		dbDelta( $sql_visits );

		$sql_links = "CREATE TABLE $links_table_name (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			visit_id BIGINT(20) UNSIGNED NOT NULL,
			link_url VARCHAR(2083) NOT NULL,
			link_text TEXT DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY visit_id_idx (visit_id),
			CONSTRAINT fk_atf_lt_visit_id FOREIGN KEY (visit_id) REFERENCES {$visits_table_name}(id) ON DELETE CASCADE
		) $charset_collate;";
		dbDelta( $sql_links );
		// Check if tables were created (optional logging or error handling).		// if ($wpdb->get_var("SHOW TABLES LIKE '$visits_table_name'") != $visits_table_name) {
		// Error logging
		// }
		// if ($wpdb->get_var("SHOW TABLES LIKE '$links_table_name'") != $links_table_name) {
		// Error logging
		// }
	}
	/**
	 * Drops the database tables on plugin uninstall.
	 */
	public static function drop_tables() {
		global $wpdb;
		$wpdb->query( "DROP TABLE IF EXISTS " . self::get_links_table_name() );
		$wpdb->query( "DROP TABLE IF EXISTS " . self::get_visits_table_name() );	}
	/**
	 * Inserts visit data into the database.
	 *
	 * @param int    $screen_width  Screen width in pixels.
	 * @param int    $screen_height Screen height in pixels.
	 * @param string $user_agent    The visitor's user agent.
	 * @return int|false The ID of the inserted row or false on failure.
	 */
	public function insert_visit_data( $screen_width, $screen_height, $user_agent ) {
		global $wpdb;
		$visits_table = self::get_visits_table_name();
		$result = $wpdb->insert(
			$visits_table,
			[
				'visit_time'    => current_time( 'mysql', 1 ), // GMT.
				'screen_width'  => absint( $screen_width ),
				'screen_height' => absint( $screen_height ),
				'user_agent'    => sanitize_text_field( $user_agent ),
			],
			[
				'%s', // visit_time.
				'%d', // screen_width.
				'%d', // screen_height.
				'%s', // user_agent.
			]		);
		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Inserts link data associated with a visit.
	 *
	 * @param int    $visit_id  The ID of the visit.
	 * @param string $link_url  The URL of the hyperlink.	 * @param string $link_text The anchor text of the hyperlink.
	 * @return int|false The ID of the inserted row or false on failure.
	 */
	public function insert_link_data( $visit_id, $link_url, $link_text ) {
		global $wpdb;
		$links_table = self::get_links_table_name();
		
		// Ensure link_url is a valid URL and not overly long.		
		$link_url = esc_url_raw( $link_url );
		 
		// Max URL length.
		if ( 2083 < strlen( $link_url ) ) {
			$link_url = substr( $link_url, 0, 2083 );
		}
     	// The $link_text parameter is already sanitized in Tracker.php
		$result = $wpdb->insert(
			$links_table,
			[
				'visit_id'  => absint( $visit_id ),
				'link_url'  => $link_url,
				'link_text' => $link_text, // Use pre-sanitized text
			],

			[
				'%d', // visit_id
				'%s', // link_url
				'%s', // link_text
			]
		);
		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Retrieves tracked link data from the last 7 days.
	 *
	 * @return array An array of tracked data.
	 */
	public function get_tracked_data() {
		global $wpdb;
		$visits_table = self::get_visits_table_name();
		$links_table  = self::get_links_table_name();

		$seven_days_ago = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$results = $wpdb->get_results(			$wpdb->prepare(
				"SELECT v.id as visit_id, v.visit_time, v.screen_width, v.screen_height, v.user_agent, l.link_url, l.link_text
				 FROM {$visits_table} v
				 JOIN {$links_table} l ON v.id = l.visit_id
				 WHERE v.visit_time >= %s
				 ORDER BY v.visit_time DESC, v.id DESC, l.id ASC",
				$seven_days_ago
			),
			ARRAY_A // Return associative array.
		);
		// phpcs:enable

		if ( ! is_array( $results ) ) {
			return [];
		}
		return $results;
	}


	/**
	 * Deletes visit data older than 7 days.
	 *
	 * @return int|false Number of rows deleted from visits table, or false on error.
	 */
	public function delete_old_visits() {
		global $wpdb;
		$visits_table = self::get_visits_table_name();

		$seven_days_ago = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$visits_table} WHERE visit_time < %s",
				$seven_days_ago			)
		);
		// phpcs:enable
		return $result;
	}

	/**
	 * Deletes orphaned rows from the links table where the corresponding visit_id no longer exists.
	 * This is a cleanup task that replaces the ON DELETE CASCADE foreign key functionality.
	 *
	 * @return int|false Number of rows deleted, or false on error.
	 */
	public function delete_orphaned_links() {
		global $wpdb;
		$visits_table = self::get_visits_table_name();
		$links_table  = self::get_links_table_name();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->query(
			"DELETE l FROM {$links_table} AS l
			 LEFT JOIN {$visits_table} AS v ON l.visit_id = v.id
			 WHERE v.id IS NULL"
		);
		// phpcs:enable
		return $result;
	}}
