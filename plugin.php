<?php
/**
 * Above The Fold Link Tracker
 *
 * This plugin tracks hyperlinks visible above the fold on the homepage
 * and provides a report in the WordPress admin area.
 *

 * @package     AboveTheFoldLinkTracker
 * @author      Roy Mahfooz
 * @copyright   YEAR Roy Mahfooz
 * @license     GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name: Above The Fold Link Tracker
 * Plugin URI:  https://github.com/roymahfooz/above-the-fold
 * Description: Tracks and displays above-the-fold hyperlinks on the homepage over the past 7 days.
 * Version:     0.1.0
 * Author:      Roy Mahfooz
 * Author URI:  https://github.com/roymahfooz
 * License:     GPL-2.0-or-later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: above-the-fold-link-tracker
 * Domain Path: /languages
 * Requires PHP: 7.3
 * Requires WP: 6.0
 */

// Define plugin namespace.
namespace ABOVE_THE_FOLD_LINK_TRACKER;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'WordPress not loaded. Cannot load the plugin.' );
}// Define essential plugin constants.
define( 'ATF_LT_PLUGIN_FILE', __FILE__ );define( 'ATF_LT_PLUGIN_BASENAME', plugin_basename( ATF_LT_PLUGIN_FILE ) );
define( 'ATF_LT_PLUGIN_DIR', plugin_dir_path( ATF_LT_PLUGIN_FILE ) );
define( 'ATF_LT_PLUGIN_URL', plugin_dir_url( ATF_LT_PLUGIN_FILE ) );

// Include Composer's autoloader.
if ( file_exists( ATF_LT_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once ATF_LT_PLUGIN_DIR . 'vendor/autoload.php';
}

// Include the main Core class.
require_once ATF_LT_PLUGIN_DIR . 'src/Core.php';

/**
 * Initializes the plugin. *
 * Loads the main plugin class and sets up WordPress hooks.
 */
function atf_lt_load_plugin() {
	Core::get_instance();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\atf_lt_load_plugin', 10 ); // Priority 10 is standard.

// Register activation, deactivation, and uninstall hooks.
register_activation_hook( ATF_LT_PLUGIN_FILE, [ Core::class, 'activate' ] );
register_deactivation_hook( ATF_LT_PLUGIN_FILE, [ Core::class, 'deactivate' ] );
register_uninstall_hook( ATF_LT_PLUGIN_FILE, [ Core::class, 'uninstall' ] );
