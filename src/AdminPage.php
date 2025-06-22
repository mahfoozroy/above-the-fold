<?php
/**
 * Admin Page Class
 *
 * @package AboveTheFoldLinkTracker\Admin
 */

namespace ABOVE_THE_FOLD_LINK_TRACKER;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates and manages the admin page for displaying tracked data.
 */
class AdminPage {

	/**	 * Database handler instance.
	 *
	 * @var Database
	 */
	private $db;

	/**
	 * Slug for the admin page.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'atf-link-tracker-report';

	/**
	 * Constructor.
	 *
	 * @param Database $database Instance of the Database class.
	 */
	public function __construct( Database $database ) {
		$this->db = $database;
		$this->add_hooks();	}

	/**
	 * Adds WordPress hooks.
	 */
	private function add_hooks() {
		add_action( 'admin_menu', [ $this, 'add_admin_menu_page' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_styles' ] );
	}

	/**
	 * Adds the admin menu page.
	 */
	public function add_admin_menu_page() {
		add_menu_page(
			__( 'Above The Fold Links', 'above-the-fold-link-tracker' ), // Page title
			__( 'ATF Links', 'above-the-fold-link-tracker' ),           // Menu title
			'manage_options',                                            // Capability
			self::PAGE_SLUG,                                             // Menu slug
			[ $this, 'render_admin_page' ],                              // Callback function
			'dashicons-visibility',                                      // Icon URL
			80                                                           // Position
		);
	}

	/**
	 * Enqueues styles for the admin page.
	 *
	 * @param string $hook_suffix The current admin page hook.
	 */
	public function enqueue_admin_styles( $hook_suffix ) {
		// Only load on our admin page.
		// The hook_suffix for a top-level page is 'toplevel_page_{menu_slug}'.
		if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}
		wp_enqueue_style(			'atf-link-tracker-admin-css',
			ATF_LT_PLUGIN_URL . 'assets/css/atf-admin.css',
			[],
			Core::VERSION
		);	}

	/**
	 * Renders the admin page content.
	 */
	public function render_admin_page() {
		?>
		<div class="wrap atf-link-tracker-wrap">
			<h1><?php esc_html_e( 'Above The Fold Link Tracker Report', 'above-the-fold-link-tracker' ); ?></h1>
			<p><?php esc_html_e( 'This report shows hyperlinks that were visible above the fold on your homepage during visits over the past 7 days.', 'above-the-fold-link-tracker' ); ?></p>


			<?php
			$tracked_data = $this->db->get_tracked_data();

			if ( empty( $tracked_data ) ) {
				echo '<p>' . esc_html__( 'No link data has been tracked yet or data is older than 7 days.', 'above-the-fold-link-tracker' ) . '</p>';
			} else {
				$this->display_data_table( $tracked_data );
			}
			?>
		</div> <?php // Closing div.wrap from render_admin_page ?>
		<?php
	}

	/**
	 * Displays the tracked data in an HTML table.
	 *
	 * @param array $data Array of tracked data.
	 */

	private function display_data_table( array $data ) {
		?>
		<table class="wp-list-table widefat fixed striped">
			<thead>				<tr>
					<th scope="col"><?php esc_html_e( 'Visit Time (GMT)', 'above-the-fold-link-tracker' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Screen Size (WxH)', 'above-the-fold-link-tracker' ); ?></th>
					<th scope="col"><?php esc_html_e( 'User Agent', 'above-the-fold-link-tracker' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Link URL', 'above-the-fold-link-tracker' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Link Text', 'above-the-fold-link-tracker' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $data as $row ) : ?>
					<tr>
						<td><?php echo esc_html( gmdate( 'Y-m-d H:i:s', strtotime( $row['visit_time'] ) ) ); ?></td>
						<td><?php echo esc_html( $row['screen_width'] . 'x' . $row['screen_height'] ); ?></td>
						<td><?php echo esc_html( isset( $row['user_agent'] ) ? $row['user_agent'] : __( 'N/A', 'above-the-fold-link-tracker' ) ); ?></td>
						<td><a href="<?php echo esc_url( $row['link_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( urldecode( $row['link_url'] ) ); ?></a></td>
						<td><?php echo esc_html( $row['link_text'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
			<tfoot>
				<tr>
					<th scope="col"><?php esc_html_e( 'Visit Time (GMT)', 'above-the-fold-link-tracker' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Screen Size (WxH)', 'above-the-fold-link-tracker' ); ?></th>
					<th scope="col"><?php esc_html_e( 'User Agent', 'above-the-fold-link-tracker' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Link URL', 'above-the-fold-link-tracker' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Link Text', 'above-the-fold-link-tracker' ); ?></th>
				</tr>
			</tfoot>
		</table>
		<?php
	}
}
