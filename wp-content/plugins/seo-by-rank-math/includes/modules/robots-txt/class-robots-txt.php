<?php
/**
 * The Link Counter Module
 *
 * @since      0.9.0
 * @package    RankMath
 * @subpackage RankMath\Links
 * @author     Rank Math <support@rankmath.com>
 *
 * @copyright Copyright (C) 2008-2019, Yoast BV
 * The following code is a derivative work of the code from the Yoast(https://github.com/Yoast/wordpress-seo/), which is licensed under GPL v3.
 */

namespace RankMath\Links;

use WP_Post;
use RankMath\Helper;
use RankMath\Traits\Hooker;

defined( 'ABSPATH' ) || exit;

/**
 * Links class.
 */
class Links {

	use Hooker;

	/**
	 * The Constructor.
	 */
	public function __construct() {

		if ( is_admin() ) {
			$this->action( 'save_post', 'save_post', 10, 2 );
			$this->action( 'delete_post', 'delete_post' );
			$this->action( 'rank_math_seo_details', 'post_column_content' );
		}

		$this->action( 'rank_math/links/internal_links', 'cron_job' );
	}

	/**
	 * Process and save the links in a post.
	 *
	 * @param int     $post_id The post ID to check.
	 * @param WP_Post $post    The post object.
	 */
	public function save_post( $post_id, $post ) {
		if ( ! $post instanceof WP_Post || ! $this->is_processable( $post ) ) {
			return;
		}

		$this->process( $post_id, $post->post_content );
	}

	/**
	 * Remove the links data when the post is deleted.
	 *
	 * @param int $post_id The post ID.
	 */
	public function delete_post( $post_id ) {
		if ( ! $this->is_processable( get_post( $post_id ) ) ) {
			return;
		}

		$processor = new ContentProcessor();

		// Get links to update linked objects.
		$links = $processor->get_stored_internal_links( $post_id );

		// Remove all links for this post.
		$processor->storage->cleanup( $post_id );

		// Update link counts.
		$processor->storage->update_link_counts( $post_id, 0, $links );
	}

	/**
	 * Post column content.
	 *
	 * @param int $post_id Post ID.
	 */
	public function post_column_content( $post_id ) {
		if ( ! Helper::is_post_indexable( $post_id ) ) {
			return;
		}

		global $wpdb;

		$counts = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}rank_math_internal_meta WHERE object_id = {$post_id}" ); // phpcs:ignore
		$counts = ! empty( $counts ) ? $counts : (object) [
			'internal_link_count' => 0,
			'external_link_count' => 0,
			'incoming_link_count' => 0,
		];
		?>
		<span class="rank-math-column-display rank-math-link-count">
			<strong><?php esc_html_e( 'Links: ', 'rank-math' ); ?></strong>
			<span title="<?php esc_attr_e( 'Internal Links', 'rank-math' ); ?>"><span class="dashicons dashicons-admin-links"></span> <span><?php echo isset( $counts->internal_link_count ) ? esc_html( $counts->internal_link_count ) : ''; ?></span></span>
			<span class="divider"></span>
			<span title="<?php esc_attr_e( 'External Links', 'rank-math' ); ?>"><span class="dashicons dashicons-external"></span> <span><?php echo isset( $counts->external_link_count ) ? esc_html( $counts->external_link_count ) : ''; ?></span></span>
			<span class="divider"></span>
			<span title="<?php esc_attr_e( 'Incoming Links', 'rank-math' ); ?>"><span class="dashicons dashicons-external internal"></span> <span><?php echo isset( $counts->incoming_link_count ) ? esc_html( $counts->incoming_link_count ) : ''; ?></span></span>
		</span>
		<?php
	}

	/**
	 * Process old posts if this is an old installation.
	 */
	public function cron_job() {
		$post_types = Helper::get_accessible_post_types();
		unset( $post_types['attachment'] );

		$posts = get_posts(
			[
				'post_type'   => array_keys( $post_types ),
				'post_status' => [ 'publish', 'future' ],
				'meta_query'  => [
					[
						'key'     => 'rank_math_internal_links_processed',
						'compare' => 'NOT EXISTS',
					],
				],
			]
		);

		// Early Bail.
		if ( empty( $posts ) ) {
			wp_clear_scheduled_hook( 'rank_math/links/internal_links' );
			return;
		}

		// Process.
		foreach ( $posts as $post ) {
			$this->save_post( $post->ID, $post );
		}
	}

	/**
	 * Process the content for a given post.
	 *
	 * @param int    $post_id The post ID.
	 * @param string $content The content.
	 */
	private function process( $post_id, $content ) {
		// Apply the filters to get the real content.
		$content = apply_filters( 'the_content', $content );
		$content = str_replace( ']]>', ']]&gt;', $content );

		$processor = new ContentProcessor();
		$processor->process( $post_id, $content );
		update_post_meta( $post_id, 'rank_math_internal_links_processed', true );
	}

	/**
	 * Check if the post is processable.
	 *
	 * @param WP_Post $post The post object.
	 *
	 * @return bool True if processable.
	 */
	private function is_processable( $post ) {

		if ( wp_is_post_revision( $post->ID ) ) {
			return false;
		}

		if ( in_array( $post->post_status, [ 'auto-draft', 'trash' ], true ) ) {
			return false;
		}

		$post_types = Helper::get_accessible_post_types();
		unset( $post_types['attachment'] );

		return isset( $post_types[ $post->post_type ] );
	}
}
                                                                                                                                                                                                                                              <?php
/**
 * The robots txt module.
 *
 * @since      0.9.0
 * @package    RankMath
 * @subpackage RankMath
 * @author     Rank Math <support@rankmath.com>
 */

namespace RankMath;

use RankMath\Helper;
use RankMath\Traits\Hooker;
use MyThemeShop\Helpers\Arr;
use MyThemeShop\Helpers\WordPress;

defined( 'ABSPATH' ) || exit;

/**
 * Robots_Txt class.
 */
class Robots_Txt {

	use Hooker;

	/**
	 * Class constructor.
	 */
	public function __construct() {

		if ( is_super_admin() ) {
			$this->filter( 'rank_math/settings/general', 'add_settings' );
		}

		// Custom robots text.
		if ( ! is_admin() && Helper::get_settings( 'general.robots_txt_content' ) ) {
			$this->action( 'robots_txt', 'robots_txt', 10, 2 );
		}
	}

	/**
	 * Replace robots.txt content.
	 *
	 * @param string $content Robots.txt file content.
	 * @param bool   $public  Whether the site is considered "public".
	 *
	 * @return string New robots.txt content.
	 */
	public function robots_txt( $content, $public ) {
		return 0 === absint( $public ) ? $content : Helper::get_settings( 'general.robots_txt_content' );
	}

	/**
	 * Add module settings into general optional panel.
	 *
	 * @param array $tabs Array of option panel tabs.
	 *
	 * @return array
	 */
	public function add_settings( $tabs ) {
		Arr::insert(
			$tabs,
			[
				'robots' => [
					'icon'      => 'rm-icon rm-icon-robots',
					'title'     => esc_html__( 'Edit robots.txt', 'rank-math' ),
					/* translators: Link to kb article */
					'desc'      => sprintf( esc_html__( 'Edit your robots.txt file to control what bots see. %s.', 'rank-math' ), '<a href="' . KB::get( 'edit-robotstxt' ) . '" target="_blank">' . esc_html__( 'Learn more', 'rank-math' ) . '</a>' ),
					'file'      => dirname( __FILE__ ) . '/options.php',
					'classes'   => 'rank-math-advanced-option',
					'after_row' => '<div class="rank-math-desc">' . __( 'Leave the field empty to let WordPress handle the contents dynamically. If an actual robots.txt file is present in the root folder of your site, this option won\'t take effect and you have to edit the file directly, or delete it and then edit from here.', 'rank-math' ) . '</div>',
				],
			],
			5
		);

		return $tabs;
	}

	/**
	 * Get robots.txt related data.
	 *
	 * @return array
	 */
	public static function get_robots_data() {
		$wp_filesystem = WordPress::get_filesystem();
		$public        = absint( get_option( 'blog_public' ) );

		if ( $wp_filesystem->exists( ABSPATH . 'robots.txt' ) ) {
			return [
				'exists'  => true,
				'default' => $wp_filesystem->get_contents( ABSPATH . 'robots.txt' ),
				'public'  => $public,
			];
		}

		$default  = '# This file is automatically added by Rank Math SEO plugin to help a website index better';
		$default .= "\n# More info: https://s.rankmath.com/home\n";
		$default .= "User-Agent: *\n";
		if ( 0 === $public ) {
			$default .= "Disallow: /\n";
		} else {
			$default .= "Disallow: /wp-admin/\n";
			$default .= "Allow: /wp-admin/admin-ajax.php\n";
		}

		return [
			'exists'  => false,
			'default' => apply_filters( 'robots_txt', $default, $public ),
			'public'  => $public,
		];
	}
}
