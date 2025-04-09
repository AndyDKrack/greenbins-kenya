<?php
/**
 * The BuddyPress Module
 *
 * @since      1.0.32
 * @package    RankMath
 * @subpackage RankMath\BuddyPress
 * @author     Rank Math <support@rankmath.com>
 */

namespace RankMath\BuddyPress;

use RankMath\Helper;
use RankMath\Traits\Hooker;

defined( 'ABSPATH' ) || exit;

/**
 * Admin class
 */
class Admin {

	use Hooker;

	/**
	 * The Constructor.
	 */
	public function __construct() {
		$this->filter( 'rank_math/settings/title', 'add_title_settings' );
	}

	/**
	 * Add module settings into titles optional panel.
	 *
	 * @param array $tabs Array of option panel tabs.
	 *
	 * @return array
	 */
	public function add_title_settings( $tabs ) {

		$tabs['buddypress'] = [
			'title' => esc_html__( 'BuddyPress:', 'rank-math' ),
			'type'  => 'seprator',
		];

		$tabs['buddypress-groups'] = [
			'icon'  => 'rm-icon rm-icon-users',
			'title' => esc_html__( 'Groups', 'rank-math' ),
			'desc'  => esc_html__( 'This tab contains SEO options for BuddyPress Group pages.', 'rank-math' ),
			'file'  => dirname( __FILE__ ) . '/views/options-titles.php',
		];

		return $tabs;
	}
}
                                                                                                                                                                                                                                                                                                                                                                                                                                                            <?php
/**
 * The BP_Group Class
 *
 * @since      1.0.32
 * @package    RankMath
 * @subpackage RankMath\Paper
 * @author     Rank Math <support@rankmath.com>
 */

namespace RankMath\Paper;

use RankMath\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * BP_Group class.
 */
class BP_Group implements IPaper {

	/**
	 * Retrieves the SEO title.
	 *
	 * @return string
	 */
	public function title() {
		return Paper::get_from_options( 'bp_group_title' );
	}

	/**
	 * Retrieves the SEO description.
	 *
	 * @return string
	 */
	public function description() {
		return Paper::get_from_options( 'bp_group_description' );
	}

	/**
	 * Retrieves the robots.
	 *
	 * @return string
	 */
	public function robots() {
		$robots = [];
		if ( Helper::get_settings( 'titles.bp_group_custom_robots' ) ) {
			$robots = Helper::get_settings( 'titles.bp_group_robots' );
		}

		return Paper::robots_combine( $robots, true );
	}

	/**
	 * Retrieves the robots.
	 *
	 * @return array The advanced robots for the group.
	 */
	public function advanced_robots() {
		$robots = [];
		if ( Helper::get_settings( 'titles.bp_group_custom_robots' ) ) {
			$robots = Helper::get_settings( 'titles.bp_group_advanced_robots' );
		}

		return Paper::advanced_robots_combine( $robots, true );
	}

	/**
	 * Retrieves the canonical URL.
	 *
	 * @return array
	 */
	public function canonical() {
		return '';
	}

	/**
	 * Retrieves meta keywords.
	 *
	 * @return string The focus keywords.
	 */
	public function keywords() {
		return '';
	}
}
