<?php
/**
 * Tracker Class
 *
 * @package AboveTheFoldLinkTracker\Tracker
 */

namespace ABOVE_THE_FOLD_LINK_TRACKER;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages JS script enqueuing and AJAX endpoint for receiving data.
 */
class Tracker {

	/**
	 * Database handler instance.
	 *
	 * @var Database
	 */
	private $db;

	/**
	 * AJAX action name.
	 *	 * @var string
	 */
	const AJAX_ACTION = 'atf_lt_track_links';

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
	 * Adds WordPress hooks.
	 */
	private function add_hooks() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, [ $this, 'handle_ajax_request' ] );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, [ $this, 'handle_ajax_request' ] ); // For non-logged-in users
	}

	/**
	 * Enqueues the tracker script on the homepage.
	 */
	public function enqueue_scripts() {		// Only enqueue on the front page or the main blog posts page.
		if ( is_front_page() || ( is_home() && 'page' !== get_option( 'show_on_front' ) ) ) {
			wp_enqueue_script(
				'atf-link-tracker-js',				ATF_LT_PLUGIN_URL . 'assets/js/atf-tracker.js',
				[], // Dependencies
				Core::VERSION,
				true // In footer
			);

			wp_localize_script(				'atf-link-tracker-js',
				'atfLinkTracker', // Object name in JavaScript
				[
					'ajax_url' => admin_url( 'admin-ajax.php' ),					'nonce'    => wp_create_nonce( self::AJAX_ACTION . '_nonce' ),					'action'   => self::AJAX_ACTION,
				]
			);
		}
	}

	/**
	 * Handles the AJAX request to save tracked data.
	 */
	public function handle_ajax_request() {
		// --- ATF Debug --- Log raw POST data at the very beginning
		// error_log('[ATF Plugin Debug] Raw $_POST data in handle_ajax_request: ' . print_r($_POST, true));
		// --- End ATF Debug ---

		// Check if the action parameter is set and correct.
		$received_action = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '';
		if ( $received_action !== self::AJAX_ACTION ) {
			wp_send_json_error(
				[ 'message' => __( 'Invalid or missing AJAX action parameter.', 'above-the-fold-link-tracker' ) . ' Expected: ' . self::AJAX_ACTION . '; Received: ' . ( $received_action ?: 'None' ) ],
				400 // Bad Request
			);
		}

		// Verify nonce. The second parameter 'nonce' tells check_ajax_referer to look for a POST variable named 'nonce'.
		$nonce_check_result = check_ajax_referer( self::AJAX_ACTION . '_nonce', 'nonce', false ); // false = do not die.
		if ( false === $nonce_check_result ) {
			wp_send_json_error(
				[ 'message' => __( 'Nonce verification failed. The security token is invalid or has expired.', 'above-the-fold-link-tracker' ) ],
				403 // Forbidden - A more appropriate status for nonce failure.
			);
		}

		// Sanitize and validate input.
		$links_data_raw = [];
		if ( isset( $_POST['links'] ) && is_array( $_POST['links'] ) ) {
			$links_data_raw = $_POST['links']; // Still needs sanitization per item.
			// --- ATF Debug --- Log received links data
			// error_log('[ATF Plugin Debug] Received links_data_raw from $_POST: ' . print_r($links_data_raw, true));
			// --- End ATF Debug ---
		} else {
			// --- ATF Debug ---
			// error_log('[ATF Plugin Debug] $_POST["links"] was not set or not an array.');
			// --- End ATF Debug ---
		}

		$screen_width  = isset( $_POST['screen_width'] ) ? absint( $_POST['screen_width'] ) : 0;
		$screen_height = isset( $_POST['screen_height'] ) ? absint( $_POST['screen_height'] ) : 0;

		if ( empty( $links_data_raw ) ) {
			wp_send_json_error( [ 'message' => __( 'No link data provided in the request.', 'above-the-fold-link-tracker' ) ], 400 );
		}

		if ( ! $screen_width || ! $screen_height ) {
			wp_send_json_error( [ 'message' => __( 'Missing or invalid screen dimensions.', 'above-the-fold-link-tracker' ) . " Width: {$screen_width}, Height: {$screen_height}" ], 400 );
		}


		// Get User Agent.
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		$visit_id = $this->db->insert_visit_data( $screen_width, $screen_height, $user_agent );

		if ( ! $visit_id ) {
			wp_send_json_error( [ 'message' => __( 'Failed to save visit data to the database.', 'above-the-fold-link-tracker' ) ], 500 );
		}

		$links_saved_count = 0;
		foreach ( $links_data_raw as $link_item_raw ) {
			if ( is_array( $link_item_raw ) && isset( $link_item_raw['url'] ) && isset( $link_item_raw['text'] ) ) {
				// Sanitize URL and text. wp_unslash is important for data from $_POST.
				$url  = esc_url_raw( wp_unslash( $link_item_raw['url'] ) );
				$text = sanitize_textarea_field( wp_unslash( $link_item_raw['text'] ) );

				// Ensure URL is not empty after sanitization and is a valid URL format.
				if ( ! empty( $url ) && filter_var( $url, FILTER_VALIDATE_URL ) ) {
					if ( $this->db->insert_link_data( $visit_id, $url, $text ) ) {
						$links_saved_count++;
					}
				}
			}
		}

		if ( $links_saved_count > 0 ) {
			wp_send_json_success( [
				'message' => sprintf(					/* translators: %d: number of links processed */
					_n( '%d link processed and saved.', '%d links processed and saved.', $links_saved_count, 'above-the-fold-link-tracker' ),
					$links_saved_count
				),
				'visit_id' => $visit_id,
			] );
		} else {
			// If visit was saved but no valid links were provided or saved after filtering.
			wp_send_json_error( [ 'message' => __( 'Visit data saved, but no valid links were processed or saved from the submitted data. Ensure links have valid URLs.', 'above-the-fold-link-tracker' ) ], 400 );
		}
	}

	/**	 * Get client IP address, trying various headers.
	 *
	 * @return string Client IP address.
	 */
	private function get_client_ip() {
		$ip_address = '';
		// Check for standard headers first.
		$headers_to_check = [
			'HTTP_CLIENT_IP',			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR', // Fallback
		];

		foreach ( $headers_to_check as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				// Sanitize the entire header string first.
				$header_value = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
				// If HTTP_X_FORWARDED_FOR or similar, it might contain a list of IPs. Take the first one.
				if ( strpos( $header_value, ',' ) !== false ) {
					$ip_list    = explode( ',', $header_value );
					$ip_address = trim( $ip_list[0] );
				} else {
					$ip_address = $header_value;
				}
				// If a valid-looking IP is found, break.
				// Basic check if it's not a private or reserved IP if needed, but for logging, any IP is fine.
				// A more robust IP validation could be filter_var($ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
				// For simplicity, we take the first non-empty sanitized value.
				if ( ! empty( $ip_address ) ) {
					break;
				}
			}
		}
		return ! empty( $ip_address ) ? $ip_address : 'UNKNOWN';
	}
}
