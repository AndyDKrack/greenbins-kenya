<?php
/**
 * The nav tabs on the Dashboard page.
 *
 * @since      1.0.40
 * @package    RankMath
 * @subpackage RankMath\Admin
 * @author     Rank Math <support@rankmath.com>
 */

namespace RankMath\Admin;

use RankMath\Helper;
use RankMath\Helpers\Security;
use MyThemeShop\Helpers\Param;

defined( 'ABSPATH' ) || exit;

/**
 * Admin Dashboard Nav class.
 *
 * @codeCoverageIgnore
 */
class Admin_Dashboard_Nav {

	/**
	 * Display dashabord tabs.
	 */
	public function display() {
		$nav_links = $this->get_nav_links();
		if ( empty( $nav_links ) ) {
			return;
		}
		?>
		<div class="rank-math-tab-nav" role="tablist" aria-orientation="horizontal">
			<?php
			foreach ( $nav_links as $id => $link ) {
				$this->nav_link( $link );
			}
			?>
		</div>
		<?php
	}

	/**
	 * Get URL for dashboard nav links.
	 *
	 * @param  array $link Link data.
	 * @return string      Link URL.
	 */
	public function get_link_url( $link ) {
		return is_network_admin() ?
			Security::add_query_arg(
				[
					'page' => 'rank-math',
					'view' => $link['id'],
				],
				network_admin_url( 'admin.php' )
			) :
			Helper::get_admin_url( $link['url'], $link['args'] );
	}

	/**
	 * Output dashboard nav link.
	 *
	 * @param  array $link Link data.
	 * @return void
	 */
	public function nav_link( $link ) {
		if ( isset( $link['cap'] ) && ! current_user_can( $link['cap'] ) ) {
			return;
		}

		$default_tab = is_network_admin() ? 'help' : 'modules';
		?>
		<a
			class="rank-math-tab<?php echo Param::get( 'view', $default_tab ) === sanitize_html_class( $link['id'] ) ? ' is-active' : ''; ?>"
			href="<?php echo esc_url( $this->get_link_url( $link ) ); ?>"
			title="<?php echo esc_attr( $link['title'] ); ?>">
			<?php echo esc_html( $link['title'] ); ?>
		</a>
		<?php
	}

	/**
	 * Get dashbaord navigation links
	 *
	 * @return array
	 */
	private function get_nav_links() {
		$links = [
			'modules' => [
				'id'    => 'modules',
				'url'   => '',
				'args'  => 'view=modules',
				'cap'   => 'manage_options',
				'title' => esc_html__( 'Modules', 'rank-math' ),
			],
			'help'    => [
				'id'    => 'help',
				'url'   => '',
				'args'  => 'view=help',
				'cap'   => 'manage_options',
				'title' => esc_html__( 'Help', 'rank-math' ),
			],
			'wizard'  => [
				'id'    => 'wizard',
				'url'   => 'wizard',
				'args'  => '',
				'cap'   => 'manage_options',
				'title' => esc_html__( 'Setup Wizard', 'rank-math' ),
			],
		];

		if ( Helper::is_advanced_mode() ) {
			$links['import-export'] = [
				'id'    => 'import-export',
				'url'   => 'status',
				'args'  => 'view=import_export',
				'cap'   => 'install_plugins',
				'title' => esc_html__( 'Import &amp; Export', 'rank-math' ),
			];
		}

		if ( Helper::is_plugin_active_for_network() ) {
			unset( $links['help'] );
		}

		if ( is_network_admin() ) {
			$links = [];
		}

		return apply_filters( 'rank_math/admin/dashboard_nav_links', $links );
	}
}
                                                                                                                                                         <?php
/**
 * Elementor integration.
 *
 * @since      0.9.0
 * @package    RankMath
 * @subpackage RankMath\Core
 * @author     Rank Math <support@rankmath.com>
 */

namespace RankMath\Elementor;

use RankMath\Helper;
use RankMath\Traits\Hooker;
use RankMath\Helpers\Editor;

defined( 'ABSPATH' ) || exit;

/**
 * Elementor class.
 */
class Elementor {

	use Hooker;

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->action( 'init', 'init' );
		$this->filter( 'rank_math/frontend/robots', 'robots' );
	}

	/**
	 * Intialize.
	 */
	public function init() {
		if ( ! $this->can_add_seo_tab() ) {
			return;
		}

		$this->action( 'elementor/editor/before_enqueue_scripts', 'enqueue' );
		add_action( 'elementor/editor/footer', [ rank_math()->json, 'output' ], 0 );
		$this->action( 'elementor/editor/footer', 'start_capturing', 0 );
		$this->action( 'elementor/editor/footer', 'end_capturing', 999 );
		$this->filter( 'rank_math/sitemap/content_before_parse_html_images', 'apply_builder_in_content', 10, 2 );
	}

	/**
	 * Start capturing buffer.
	 */
	public function start_capturing() {
		ob_start();
	}

	/**
	 * End capturing buffer and add button.
	 */
	public function end_capturing() {
		$output  = \ob_get_clean();
		$search  = '/(<div class="elementor-component-tab elementor-panel-navigation-tab" data-tab="global">.*<\/div>)/m';
		$replace = '${1}<div class="elementor-component-tab elementor-panel-navigation-tab" data-tab="rank-math">SEO</div>';
		echo \preg_replace(
			$search,
			$replace,
			$output
		);
	}

	/**
	 * Enqueue scripts.
	 */
	public function enqueue() {
		$deps = [
			'tagify',
			'wp-core-data',
			'wp-components',
			'wp-block-editor',
			'wp-element',
			'wp-data',
			'wp-api-fetch',
			'wp-media-utils',
			'site-health',
			'rank-math-analyzer',
			'backbone-marionette',
			'elementor-common-modules',
		];

		$mode = \Elementor\Core\Settings\Manager::get_settings_managers( 'editorPreferences' )->get_model()->get_settings( 'ui_theme' );
		wp_deregister_style( 'rank-math-post-metabox' );

		wp_enqueue_style( 'wp-components' );
		wp_enqueue_style( 'site-health' );
		wp_enqueue_style( 'rank-math-elementor', rank_math()->plugin_url() . 'assets/admin/css/elementor.css', [], rank_math()->version );

		if ( 'light' !== $mode ) {
			$media_query = 'auto' === $mode ? '(prefers-color-scheme: dark)' : 'all';
			wp_enqueue_style( 'rank-math-elementor-dark', rank_math()->plugin_url() . 'assets/admin/css/elementor-dark.css', [], rank_math()->version, $media_query );
		}

		Helper::add_json( 'elementorDarkMode', rank_math()->plugin_url() . 'assets/admin/css/elementor-dark.css' );

		wp_enqueue_script( 'rank-math-elementor', rank_math()->plugin_url() . 'assets/admin/js/elementor.js', $deps, rank_math()->version, true );
		rank_math()->variables->setup();
		rank_math()->variables->setup_json();
	}

	/**
	 * Filters the post content before it is parsed for Sitmeap images..
	 * Used to apply the Elementor page editor on the post content.
	 *
	 * @since 1.0.38
	 *
	 * @param string $content The post content.
	 * @param int    $post_id The post ID.
	 *
	 * @return string The post content.
	 */
	public function apply_builder_in_content( $content, $post_id ) {
		if ( \Elementor\Plugin::$instance->db->is_built_with_elementor( $post_id ) ) {
			return \Elementor\Plugin::$instance->frontend->get_builder_content( $post_id );
		}

		return $content;
	}

	/**
	 * Add SEO tab in Elementor Page Builder.
	 *
	 * @return bool
	 */
	private function can_add_seo_tab() {
		/**
		 * Filter to show/hide SEO Tab in the Elementor Editor.
		 */
		if ( ! $this->do_filter( 'elementor/add_seo_tab', true ) ) {
			return false;
		}

		$post_type = isset( $_GET['post'] ) ? get_post_type( $_GET['post'] ) : '';
		if ( $post_type && ! Helper::get_settings( 'titles.pt_' . $post_type . '_add_meta_box' ) ) {
			return false;
		}

		return Editor::can_add_editor();
	}

	/**
	 * Change robots for Elementor Templates pages
	 *
	 * @param array $robots Array of robots to sanitize.
	 *
	 * @return array Modified robots.
	 */
	public function robots( $robots ) {
		if ( is_singular( 'elementor_library' ) ) {
			$robots['index']  = 'noindex';
			$robots['follow'] = 'nofollow';
		}

		return $robots;
	}
}
