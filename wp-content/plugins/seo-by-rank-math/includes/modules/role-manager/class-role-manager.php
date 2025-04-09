<?php
/**
 * The Capability Manager.
 *
 * @since      0.9.0
 * @package    RankMath
 * @subpackage RankMath\Role_Manager
 * @author     Rank Math <support@rankmath.com>
 */

namespace RankMath\Role_Manager;

use RankMath\Helper;
use RankMath\Module;
use RankMath\Traits\Hooker;
use MyThemeShop\Admin\Page;
use MyThemeShop\Helpers\WordPress;

defined( 'ABSPATH' ) || exit;

/**
 * Capability_Manager class.
 */
class Capability_Manager {

	use Hooker;

	/**
	 * Registered capabilities.
	 *
	 * @var array
	 */
	protected $capabilities = [];

	/**
	 * Main instance
	 *
	 * Ensure only one instance is loaded or can be loaded.
	 *
	 * @return Capability_Manager
	 */
	public static function get() {
		static $instance;

		if ( is_null( $instance ) && ! ( $instance instanceof Capability_Manager ) ) {
			$instance = new Capability_Manager();
			$instance->set_capabilities();
		}

		return $instance;
	}

	/**
	 * Set default capabilities.
	 *
	 * @codeCoverageIgnore
	 */
	public function set_capabilities() {
		$this->register( 'rank_math_titles', esc_html__( 'Titles & Meta Settings', 'rank-math' ) );
		$this->register( 'rank_math_general', esc_html__( 'General Settings', 'rank-math' ) );
		$this->register( 'rank_math_sitemap', esc_html__( 'Sitemap Settings', 'rank-math' ) );
		$this->register( 'rank_math_404_monitor', esc_html__( '404 Monitor Log', 'rank-math' ) );
		$this->register( 'rank_math_link_builder', esc_html__( 'Link Builder', 'rank-math' ) );
		$this->register( 'rank_math_redirections', esc_html__( 'Redirections', 'rank-math' ) );
		$this->register( 'rank_math_role_manager', esc_html__( 'Role Manager', 'rank-math' ) );
		$this->register( 'rank_math_analytics', esc_html__( 'Analytics', 'rank-math' ) );
		$this->register( 'rank_math_site_analysis', esc_html__( 'Site-Wide Analysis', 'rank-math' ) );
		$this->register( 'rank_math_onpage_analysis', esc_html__( 'On-Page Analysis', 'rank-math' ) );
		$this->register( 'rank_math_onpage_general', esc_html__( 'On-Page General Settings', 'rank-math' ) );
		$this->register( 'rank_math_onpage_advanced', esc_html__( 'On-Page Advanced Settings', 'rank-math' ) );
		$this->register( 'rank_math_onpage_snippet', esc_html__( 'On-Page Schema Settings', 'rank-math' ) );
		$this->register( 'rank_math_onpage_social', esc_html__( 'On-Page Social Settings', 'rank-math' ) );
		$this->register( 'rank_math_admin_bar', esc_html__( 'Top Admin Bar', 'rank-math' ) );
	}

	/**
	 * Registers a capability.
	 *
	 * @param string $capability Capability to register.
	 * @param string $title      Capability human title.
	 */
	public function register( $capability, $title ) {
		$this->capabilities[ $capability ] = $title;
	}

	/**
	 * Returns the list of registered capabilitities.
	 *
	 * @param bool $caps Capabilities as keys.
	 *
	 * @return string[] Registered capabilities.
	 */
	public function get_capabilities( $caps = false ) {
		return $caps ? array_keys( $this->capabilities ) : $this->capabilities;
	}

	/**
	 * Add capabilities on install.
	 */
	public function create_capabilities() {
		foreach ( WordPress::get_roles() as $slug => $role ) {
			$role = get_role( $slug );
			if ( ! $role ) {
				continue;
			}

			$this->loop_capabilities( $this->get_default_capabilities_by_role( $slug ), 'add_cap', $role );
		}
	}

	/**
	 * Remove capabilities on uninstall.
	 */
	public function remove_capabilities() {
		$capabilities = $this->get_capabilities( true );
		foreach ( WordPress::get_roles() as $slug => $role ) {
			$role = get_role( $slug );
			if ( ! $role ) {
				continue;
			}

			$this->loop_capabilities( $capabilities, 'remove_cap', $role );
		}
	}

	/**
	 * Loop capabilities and perform action.
	 *
	 * @param array  $caps    Capabilities.
	 * @param string $perform Action to perform.
	 * @param object $role    Role object.
	 */
	private function loop_capabilities( $caps, $perform, $role ) {
		foreach ( $caps as $cap ) {
			$role->$perform( $cap );
		}
	}

	/**
	 * Get default capabilities by roles.
	 *
	 * @param  string $role Capabilities for this role.
	 * @return array
	 */
	private function get_default_capabilities_by_role( $role ) {

		if ( 'administrator' === $role ) {
			return $this->get_capabilities( true );
		}

		if ( 'editor' === $role ) {
			return [
				'rank_math_site_analysis',
				'rank_math_onpage_analysis',
				'rank_math_onpage_general',
				'rank_math_onpage_snippet',
				'rank_math_onpage_social',
			];
		}

		if ( 'author' === $role ) {
			return [
				'rank_math_onpage_analysis',
				'rank_math_onpage_general',
				'rank_math_onpage_snippet',
				'rank_math_onpage_social',
			];
		}

		return [];
	}
}
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            <?php
/**
 * The Role Manager Module.
 *
 * @since      0.9.0
 * @package    RankMath
 * @subpackage RankMath\Role_Manager
 * @author     Rank Math <support@rankmath.com>
 */

namespace RankMath\Role_Manager;

use RankMath\Helper;
use RankMath\Module\Base;
use MyThemeShop\Admin\Page;
use MyThemeShop\Helpers\WordPress;

defined( 'ABSPATH' ) || exit;

/**
 * Role_Manager class.
 */
class Role_Manager extends Base {

	/**
	 * The Constructor.
	 */
	public function __construct() {

		$directory = dirname( __FILE__ );
		$this->config(
			[
				'id'        => 'role-manager',
				'directory' => $directory,
			]
		);
		parent::__construct();

		$this->action( 'cmb2_admin_init', 'register_form' );
		add_filter( 'cmb2_override_option_get_rank-math-role-manager', [ '\RankMath\Helper', 'get_roles_capabilities' ] );
		$this->action( 'admin_post_rank_math_save_capabilities', 'save_capabilities' );

		if ( $this->page->is_current_page() ) {
			add_action( 'admin_enqueue_scripts', [ 'CMB2_Hookup', 'enqueue_cmb_css' ], 25 );
		}

		// Members plugin integration.
		if ( \function_exists( 'members_plugin' ) ) {
			new Members();
		}

		// User Role Editor plugin integration.
		if ( defined( 'URE_PLUGIN_URL' ) ) {
			new User_Role_Editor();
		}
	}

	/**
	 * Register admin page.
	 */
	public function register_admin_page() {
		$uri = untrailingslashit( plugin_dir_url( __FILE__ ) );

		$this->page = new Page(
			'rank-math-role-manager',
			esc_html__( 'Role Manager', 'rank-math' ),
			[
				'position'   => 20,
				'parent'     => 'rank-math',
				'capability' => 'rank_math_role_manager',
				'render'     => $this->directory . '/views/main.php',
				'classes'    => [ 'rank-math-page' ],
				'assets'     => [
					'styles' => [
						'rank-math-common'       => '',
						'rank-math-cmb2'         => '',
						'rank-math-role-manager' => $uri . '/assets/css/role-manager.css',
					],
				],
			]
		);
	}

	/**
	 * Register form for Add New Record.
	 */
	public function register_form() {

		$cmb = new_cmb2_box(
			[
				'id'           => 'rank-math-role-manager',
				'object_types' => [ 'options-page' ],
				'option_key'   => 'rank-math-role-manager',
				'hookup'       => false,
				'save_fields'  => false,
			]
		);

		$caps = Capability_Manager::get()->get_capabilities();

		foreach ( WordPress::get_roles() as $role => $label ) {
			$cmb->add_field(
				[
					'id'                => esc_attr( $role ),
					'type'              => 'multicheck_inline',
					'name'              => translate_user_role( $label ),
					'options'           => $caps,
					'select_all_button' => false,
					'classes'           => 'cmb-big-labels',
				]
			);
		}
	}

	/**
	 * Save capabilities form submit handler.
	 */
	public function save_capabilities() {

		// If no form submission, bail!
		if ( empty( $_POST ) ) {
			return false;
		}

		check_admin_referer( 'rank-math-save-capabilities', 'security' );

		if ( ! Helper::has_cap( 'role_manager' ) ) {
			Helper::add_notification( esc_html__( 'You are not authorized to perform this action.', 'rank-math' ), [ 'type' => 'error' ] );
			wp_safe_redirect( Helper::get_admin_url( 'role-manager' ) );
			exit;
		}

		$cmb = cmb2_get_metabox( 'rank-math-role-manager' );
		Helper::set_capabilities( $cmb->get_sanitized_values( $_POST ) );

		wp_safe_redirect( Helper::get_admin_url( 'role-manager' ) );
		exit;
	}
}
