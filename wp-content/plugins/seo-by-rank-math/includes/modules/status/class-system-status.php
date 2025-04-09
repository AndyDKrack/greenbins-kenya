<?php
/**
 * The Status module.
 *
 * @since      1.0.33
 * @package    RankMath
 * @subpackage RankMath
 * @author     Rank Math <support@rankmath.com>
 */

namespace RankMath\Status;

use RankMath\Helper;
use RankMath\Module\Base;
use RankMath\Traits\Hooker;
use MyThemeShop\Admin\Page;
use MyThemeShop\Helpers\Param;
use MyThemeShop\Helpers\Conditional;

defined( 'ABSPATH' ) || exit;

/**
 * Status class.
 */
class Status extends Base {

	use Hooker;

	/**
	 * Class constructor.
	 */
	public function __construct() {
		if ( Conditional::is_heartbeat() ) {
			return;
		}

		$directory = dirname( __FILE__ );
		$this->config(
			[
				'id'        => 'status',
				'directory' => $directory,
			]
		);

		$this->filter( 'rank_math/tools/pages', 'add_status_page', 12 );

		parent::__construct();
	}

	/**
	 * Register admin page.
	 */
	public function register_admin_page() {
		$uri = untrailingslashit( plugin_dir_url( __FILE__ ) );

		$this->page = new Page(
			'rank-math-status',
			esc_html__( 'Status & Tools', 'rank-math' ),
			[
				'position' => 70,
				'parent'   => 'rank-math',
				'classes'  => [ 'rank-math-page' ],
				'render'   => $this->directory . '/views/main.php',
				'assets'   => [
					'styles'  => [
						'rank-math-common' => '',
						'rank-math-status' => $uri . '/assets/css/status.css',
					],
					'scripts' => [
						'rank-math-dashboard' => '',
						'rank-math-status'    => $uri . '/assets/js/status.js',
					],
				],
			]
		);
	}

	/**
	 * Display dashabord tabs.
	 */
	public function display_nav() {
		$default_tab = $this->do_filter( 'tools/default_tab', 'status' );
		?>
		<div class="rank-math-tab-nav" role="tablist" aria-orientation="horizontal">
			<?php
			foreach ( $this->get_views() as $id => $link ) :
				if ( isset( $link['cap'] ) && ! current_user_can( $link['cap'] ) ) {
					continue;
				}
				?>
			<a class="rank-math-tab<?php echo Param::get( 'view', $default_tab ) === sanitize_html_class( $id ) ? ' is-active' : ''; ?>" href="<?php echo esc_url( Helper::get_admin_url( $link['url'], $link['args'] ) ); ?>" title="<?php echo esc_attr( $link['title'] ); ?>"><?php echo esc_html( $link['title'] ); ?></a>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Display view body.
	 *
	 * @param string $view Current view.
	 */
	public function display_body( $view ) {
		$hash = $this->get_views();
		$hash = new $hash[ $view ]['class']();
		$hash->display();
	}

	/**
	 * Add subpage to Status & Tools screen.
	 *
	 * @param array $pages Pages.
	 * @return array       New pages.
	 */
	public function add_status_page( $pages ) {
		$pages['status'] = [
			'url'   => 'status',
			'args'  => 'view=status',
			'cap'   => 'manage_options',
			'title' => __( 'System Status', 'rank-math' ),
			'class' => '\\RankMath\\Status\\System_Status',
		];

		return $pages;
	}

	/**
	 * Get dashbaord navigation links
	 *
	 * @return array
	 */
	private function get_views() {
		return $this->do_filter( 'tools/pages', [] );
	}
}
                                                                                         <?php
/**
 * The System_Status Class.
 *
 * @since      1.0.33
 * @package    RankMath
 * @subpackage RankMath\Status
 * @author     Rank Math <support@rankmath.com>
 */

namespace RankMath\Status;

use RankMath\Google\Authentication;
use RankMath\Admin\Admin_Helper;

defined( 'ABSPATH' ) || exit;

/**
 * System_Status class.
 */
class System_Status {

	/**
	 * Display Database/Tables Details.
	 */
	public function display() {
		$this->prepare_info();

		$this->display_system_info();
		( new Error_Log() )->display();
	}

	/**
	 * Display system details.
	 */
	private function display_system_info() {
		?>
		<div class="rank-math-system-status rank-math-box">
			<header>
				<h3><?php esc_html_e( 'System Info', 'rank-math' ); ?></h3>
			</header>

			<div class="site-health-copy-buttons">
				<div class="copy-button-wrapper">
					<button type="button" class="button copy-button" data-clipboard-text="<?php echo esc_attr( \WP_Debug_Data::format( $this->wp_info, 'debug' ) ); ?>">
						<?php esc_html_e( 'Copy System Info to Clipboard', 'rank-math' ); ?>
					</button>
					<span class="success hidden" aria-hidden="true"><?php esc_html_e( 'Copied!', 'rank-math' ); ?></span>
				</div>
			</div>

			<div id="health-check-debug" class="health-check-accordion">
				<?php $this->display_system_info_list(); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Display list for system info.
	 *
	 * @return void
	 */
	private function display_system_info_list() {
		$directory = dirname( __FILE__ );
		foreach ( $this->wp_info as $section => $details ) {
			if ( ! isset( $details['fields'] ) || empty( $details['fields'] ) ) {
				continue;
			}

			include( $directory . '/views/system-status-accordion.php' );
		}
	}

	/**
	 * Display individual fields for the system info.
	 *
	 * @param  array $fields Fields array.
	 * @return void
	 */
	private function display_system_info_fields( $fields ) {
		foreach ( $fields as $field_name => $field ) {
			$values = $this->system_info_value( $field_name, $field['value'] );
			printf( '<tr><td>%s</td><td>%s</td></tr>', esc_html( $field['label'] ), $values );
		}
	}

	/**
	 * Get individual values for the system info.
	 *
	 * @param  string $field_name  Field name.
	 * @param  mixed  $field_value Field value.
	 * @return string              Output HTML.
	 */
	private function system_info_value( $field_name, $field_value ) {
		if ( is_array( $field_value ) ) {
			$values = '<ul>';
			foreach ( $field_value as $name => $value ) {
				$values .= sprintf( '<li>%s: %s</li>', esc_html( $name ), esc_html( $value ) );
			}
			$values .= '</ul>';

			return $values;
		}

		return esc_html( $field_value );
	}

	/**
	 * Get Database information.
	 */
	private function prepare_info() {
		global $wpdb;

		$plan   = Admin_Helper::get_registration_data();
		$tokens = Authentication::tokens();

		$rankmath = [
			'label'  => esc_html__( 'Rank Math', 'rank-math' ),
			'fields' => [
				'version' => [
					'label' => esc_html__( 'Version', 'rank-math' ),
					'value' => get_option( 'rank_math_version' ),
				],
				'database_version' => [
					'label' => esc_html__( 'Database version', 'rank-math' ),
					'value' => get_option( 'rank_math_db_version' ),
				],
				'plugin_plan' => [
					'label' => esc_html__( 'Plugin subscription plan', 'rank-math' ),
					'value' => isset( $plan['plan'] ) ? \ucwords( $plan['plan'] ) : esc_html__( 'Free', 'rank-math' ),
				],
				'refresh_token' => [
					'label' => esc_html__( 'Google Refresh token', 'rank-math' ),
					'value' => empty( $tokens['refresh_token'] ) ? esc_html__( 'No token', 'rank-math' ) : esc_html__( 'Token exists', 'rank-math' ),
				],
			],
		];

		$database_tables = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
				table_name AS 'name'
				FROM information_schema.TABLES
				WHERE table_schema = %s
				AND table_name LIKE %s
				ORDER BY name ASC;",
				DB_NAME,
				'%rank_math%'
			)
		);

		$tables = [];
		foreach ( $database_tables as $table ) {
			$name = \str_replace( $wpdb->prefix, '', $table->name );
			$tables[ $name ] = true;
		}

		$should_exists = [
			'rank_math_404_logs'                  => esc_html__( 'Database Table: 404 Log', 'rank-math' ),
			'rank_math_redirections'              => esc_html__( 'Database Table: Redirection', 'rank-math' ),
			'rank_math_redirections_cache'        => esc_html__( 'Database Table: Redirection Cache', 'rank-math' ),
			'rank_math_internal_links'            => esc_html__( 'Database Table: Internal Link', 'rank-math' ),
			'rank_math_internal_meta'             => esc_html__( 'Database Table: Internal Link Meta', 'rank-math' ),
			'rank_math_analytics_gsc'             => esc_html__( 'Database Table: Google Search Console', 'rank-math' ),
			'rank_math_analytics_objects'         => esc_html__( 'Database Table: Flat Posts', 'rank-math' ),
			'rank_math_analytics_ga'              => esc_html__( 'Database Table: Google Analytics', 'rank-math' ),
			'rank_math_analytics_adsense'         => esc_html__( 'Database Table: Google AdSense', 'rank-math' ),
			'rank_math_analytics_keyword_manager' => esc_html__( 'Database Table: Keyword Manager', 'rank-math' ),
		];

		foreach ( $should_exists as $name => $label ) {
			$rankmath['fields'][ $name ] = [
				'label' => $label,
				'value' => isset( $tables[ $name ] ) ? esc_html__( 'Created', 'rank-math' ) : esc_html__( 'Doesn\'t exists', 'rank-math' ),
			];
		}

		// Core debug data.
		if ( ! class_exists( 'WP_Debug_Data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-debug-data.php';
		}
		wp_enqueue_style( 'site-health' );
		wp_enqueue_script( 'site-health' );
		$this->wp_info = [ 'rank-math' => $rankmath ] + \WP_Debug_Data::debug_data();
		unset( $this->wp_info['wp-paths-sizes'] );
	}
}
