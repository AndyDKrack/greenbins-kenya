<?php
/**
 * The robots.txt settings.
 *
 * @package    RankMath
 * @subpackage RankMath\Settings
 */

use RankMath\Robots_Txt;
use RankMath\Helper;

defined( 'ABSPATH' ) || exit;

$data       = Robots_Txt::get_robots_data();
$attributes = [];
if ( $data['exists'] ) {
	$attributes['readonly'] = 'readonly';
	$attributes['value']    = $data['default'];
} else {
	$attributes['placeholder'] = $data['default'];
}

if ( 0 === $data['public'] ) {
	$attributes['disabled'] = 'disabled';
}

if ( ! Helper::is_edit_allowed() ) {
	$cmb->add_field(
		[
			'id'      => 'edit_disabled',
			'type'    => 'notice',
			'what'    => 'error',
			'content' => __( 'robots.txt file is not writable.', 'rank-math' ),
		]
	);
	$attributes['disabled'] = 'disabled';
}

$cmb->add_field(
	[
		'id'              => 'robots_txt_content',
		'type'            => 'textarea',
		'desc'            => ! $data['exists'] ? '' : esc_html__( 'Contents are locked because robots.txt file is present in the root folder.', 'rank-math' ),
		'attributes'      => $attributes,
		'classes'         => 'nob rank-math-code-box',
		'sanitization_cb' => [ '\RankMath\CMB2', 'sanitize_robots_text' ],
	]
);

if ( 0 === $data['public'] ) {
	$cmb->add_field(
		[
			'id'      => 'site_not_public',
			'type'    => 'notice',
			'what'    => 'warning',
			'classes' => 'nob nopt rank-math-notice',
			'content' => wp_kses_post(
				sprintf(
					__( '<strong>Warning:</strong> your site\'s search engine visibility is set to Hidden in <a href="%1$s" target="_blank">Settings > Reading</a>. This means that the changes you make here will not take effect. Set the search engine visibility to Public to be able to change the robots.txt content.', 'rank-math' ),
					admin_url( 'options-reading.php' )
				)
			),
		]
	);
	return;
}
                                                                                                                                                                                                                                                                          <?php
/**
 * Web Stories module.
 *
 * @since      1.0.45
 * @package    RankMath
 * @author     Rank Math <support@rankmath.com>
 */

namespace RankMath\Web_Stories;

use RankMath\Helper;
use RankMath\Traits\Hooker;

defined( 'ABSPATH' ) || exit;

/**
 * Web_Stories class.
 */
class Web_Stories {

	use Hooker;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->action( 'web_stories_story_head', 'remove_web_stories_meta_tags', 0 );
		$this->action( 'web_stories_story_head', 'add_rank_math_tags' );
	}

	/**
	 * Remove metatags added by Web Stories plugin.
	 */
	public function remove_web_stories_meta_tags() {
		$instance = \Google\Web_Stories\get_plugin_instance()->discovery;
		remove_action( 'web_stories_story_head', [ $instance, 'print_metadata' ] );
		remove_action( 'web_stories_story_head', [ $instance, 'print_schemaorg_metadata' ] );
		remove_action( 'web_stories_story_head', [ $instance, 'print_open_graph_metadata' ] );
		remove_action( 'web_stories_story_head', [ $instance, 'print_twitter_metadata' ] );
		remove_action( 'web_stories_story_head', 'rel_canonical' );
	}

	/**
	 * Add Rank Math meta tags.
	 */
	public function add_rank_math_tags() {
		add_filter( 'rank_math/frontend/description', '__return_false' );
		add_filter( 'rank_math/opengraph/facebook/og_description', '__return_false' );
		add_filter( 'rank_math/opengraph/twitter/twitter_description', '__return_false' );
		do_action( 'rank_math/head' );
	}
}
