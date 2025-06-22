<?php
/**
 * Core Plugin Class
 *
 * @package AboveTheFoldLinkTracker\Core
 */namespace ABOVE_THE_FOLD_LINK_TRACKER;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class. Manages initialization, loads components, and handles lifecycle hooks.
 */
final class Core {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	const VERSION = '0.1.0';

	/**
	 * Plugin text domain.
	 *
	 * @var string
	 */
	const TEXT_DOMAIN = 'above-the-fold-link-tracker';

	/**
	 * Minimum PHP version required.	 *
	 * @var string
	 */
	const MIN_PHP_VERSION = '7.3';

	/**
	 * Minimum WordPress version required.
	 *
	 * @var string
	 */
	const MIN_WP_VERSION = '6.0';

	/**
	 * Database version option name.
	 *
	 * @var string
	 */
	const DB_VERSION_OPTION_NAME = 'atf_lt_db_version';

	/**
	 * Current database version.
	 *	 * @var string
	 */
	const CURRENT_DB_VERSION = '1.0';

	/**
	 * Cron hook for cleaning old data.
	 *
	 * @var string
	 */	const CRON_HOOK = 'atf_lt_cleanup_old_data_hook';

	/**
	 * Suffix for the links database table name (will be prefixed by $wpdb->prefix).
	 *
	 * @var string
	 */
	const LINKS_TABLE_NAME_SUFFIX = 'atf_lt_links';

	/**
	 * Suffix for the visits database table name (will be prefixed by $wpdb->prefix).
	 *
	 * @var string
	 */
	const VISITS_TABLE_NAME_SUFFIX = 'atf_lt_visits';

	/**
	 * The single instance of the class.
	 *
	 * @var Core|null
	 */
	private static $instance = null;	/**
	 * Instance of the Database handler.
	 *
	 * @var Database
	 */
	public $database;

	/**
	 * Instance of the Tracker.
	 *
	 * @var Tracker
	 */
	public $tracker;

	/**
	 * Instance of the AdminPage.
	 *
	 * @var AdminPage
	 */
	public $admin_page;

	/**
	 * Instance of the Cron handler.
	 *
	 * @var Cron
	 */
	public $cron;

	/**
	 * Ensures only one instance of the class is loaded or can be loaded.
	 *
	 * @return Core An instance of this class.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->init();
		}
		return self::$instance;
	}

	/**
	 * Private constructor to prevent direct object creation.
	 */
	private function __construct() {
		// Constructor logic can go here if needed before init.
	}

	/**
	 * Initializes the plugin by setting up hooks and loading components.
	 */
	private function init() {
		$this->check_requirements();
		$this->load_dependencies();
		$this->setup_components();
		$this->add_hooks();
		$this->load_textdomain();
	}

	/**
	 * Checks PHP and WordPress version requirements.
	 */
	private function check_requirements() {
		if ( version_compare( PHP_VERSION, self::MIN_PHP_VERSION, '<' ) ) {
			add_action( 'admin_notices', [ $this, 'php_version_notice' ] );			// Potentially deactivate plugin or prevent further loading.
			return;
		}
		if ( version_compare( get_bloginfo( 'version' ), self::MIN_WP_VERSION, '<' ) ) {
			add_action( 'admin_notices', [ $this, 'wp_version_notice' ] );
			// Potentially deactivate plugin or prevent further loading.
			return;
		}
	}

	/**
	 * Display admin notice for PHP version.
	 */
	public function php_version_notice() {
		$message = sprintf(
			/* translators: 1: Plugin Name, 2: Required PHP version, 3: Current PHP version */
			esc_html__( '%1$s requires PHP version %2$s or higher. You are running version %3$s.', 'above-the-fold-link-tracker' ),
			'<strong>Above The Fold Link Tracker</strong>',
			self::MIN_PHP_VERSION,
			PHP_VERSION
		);
		printf( '<div class="notice notice-error"><p>%s</p></div>', wp_kses_post( $message ) );
	}

	/**
	 * Display admin notice for WordPress version.
	 */
	public function wp_version_notice() {
		$message = sprintf(
			/* translators: 1: Plugin Name, 2: Required WP version, 3: Current WP version */
			esc_html__( '%1$s requires WordPress version %2$s or higher. You are running version %3$s.', 'above-the-fold-link-tracker' ),
			'<strong>Above The Fold Link Tracker</strong>',
			self::MIN_WP_VERSION,
			get_bloginfo( 'version' )
		);
		printf( '<div class="notice notice-error"><p>%s</p></div>', wp_kses_post( $message ) );
	}


	/**
	 * Loads required dependency files.
	 */
	private function load_dependencies() {
		require_once ATF_LT_PLUGIN_DIR . 'src/Database.php';
		require_once ATF_LT_PLUGIN_DIR . 'src/Tracker.php';
		require_once ATF_LT_PLUGIN_DIR . 'src/AdminPage.php';
		require_once ATF_LT_PLUGIN_DIR . 'src/Cron.php';
	}

	/**
	 * Instantiates and sets up plugin components.
	 */
	private function setup_components() {
		$this->database   = new Database();
		$this->tracker    = new Tracker( $this->database );
		$this->admin_page = new AdminPage( $this->database );
		$this->cron       = new Cron( $this->database );
	}

	/**
	 * Adds WordPress hooks.
	 */
	private function add_hooks() {
		// Hooks for components are usually added within their constructors.
		// Add other global plugin hooks here if needed.
	}

	/**
	 * Loads the plugin text domain for translation.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			self::TEXT_DOMAIN,
			false,
			dirname( ATF_LT_PLUGIN_BASENAME ) . '/languages/'
		);	}

	/**
	 * Plugin activation hook.
	 * Creates database tables and schedules cron jobs.
	 */
	public static function activate() {
		// Security checks.
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		// Check for `wp_unslash` as it was used in template, ensure it's appropriate here.
		// For activation, usually, we don't rely on `$_REQUEST` directly unless for specific redirect logic post-activation.
		// The `check_admin_referer` might not be strictly necessary here if no $_REQUEST data is processed for security.
		// Example from template was:
		// $plugin = isset( $_REQUEST['plugin'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['plugin'] ) ) : '';
		// check_admin_referer( "activate-plugin_{$plugin}" ); // This refers to the plugin slug.

		require_once ATF_LT_PLUGIN_DIR . 'src/Database.php';
		Database::create_tables();

		require_once ATF_LT_PLUGIN_DIR . 'src/Cron.php';
		Cron::schedule_event();

		// Set database version.
		update_option( self::DB_VERSION_OPTION_NAME, self::CURRENT_DB_VERSION );		// Flush rewrite rules if custom post types or taxonomies were registered (not in this plugin).
		// flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation hook.
	 * Clears scheduled cron jobs.	 */
	public static function deactivate() {
		// Security checks.
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		// Similar to activate, referer check might not be needed if not processing $_REQUEST.
		// $plugin = isset( $_REQUEST['plugin'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['plugin'] ) ) : '';
		// check_admin_referer( "deactivate-plugin_{$plugin}" );

		require_once ATF_LT_PLUGIN_DIR . 'src/Cron.php';
		Cron::clear_scheduled_event();		// Additional deactivation tasks if any.
	}

	/**
	 * Plugin uninstall hook.
	 * Removes database tables and options.
	 */
	public static function uninstall() {
		// Security checks.
		if ( ! current_user_can( 'activate_plugins' ) || ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			return;
		}
		// Verify the uninstall request.
		// check_admin_referer( 'bulk-plugins' ); // This is for bulk actions. For single, it's different or not needed for direct uninstall.php.
											  // If called via register_uninstall_hook, this check is not standard.

		require_once ATF_LT_PLUGIN_DIR . 'src/Database.php';
		Database::drop_tables();

		// Delete options.
		delete_option( self::DB_VERSION_OPTION_NAME );

		// Clear any remaining scheduled cron events (belt and suspenders).
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}
}
