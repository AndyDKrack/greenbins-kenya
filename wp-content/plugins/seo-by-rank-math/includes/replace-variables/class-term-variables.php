<?php
/**
 * Author variable replacer.
 *
 * @since      1.0.33
 * @package    RankMath
 * @subpackage RankMath\Replace_Variables
 * @author     Rank Math <support@rankmath.com>
 */

namespace RankMath\Replace_Variables;

use RankMath\Admin\Admin_Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Author_Variables class.
 */
class Author_Variables extends Term_Variables {

	/**
	 * Hold counter variable data.
	 *
	 * @var array
	 */
	protected $counters = [];

	/**
	 * Setup author variables.
	 */
	public function setup_author_variables() {
		global $user_id;
		if ( ! Admin_Helper::is_user_edit() ) {
			$user_id = get_current_user_id();
		}

		if ( $this->is_post_edit ) {
			$post   = $this->get_post();
			$author = get_userdata( $post->post_author );
		}

		$this->register_replacement(
			'userid',
			[
				'name'        => esc_html__( 'Author ID', 'rank-math' ),
				'description' => esc_html__( 'Author\'s user ID of the current post, page or author archive.', 'rank-math' ),
				'variable'    => 'userid',
				'example'     => $this->is_post_edit ? $post->post_author : $user_id,
			],
			[ $this, 'get_userid' ]
		);

		$this->register_replacement(
			'name',
			[
				'name'        => esc_html__( 'Post Author', 'rank-math' ),
				'description' => esc_html__( 'Display author\'s nicename of the current post, page or author archive.', 'rank-math' ),
				'variable'    => 'name',
				'example'     => $this->is_post_edit && $author ? $author->display_name : get_the_author_meta( 'display_name', $user_id ),
			],
			[ $this, 'get_name' ]
		);

		$this->register_replacement(
			'user_description',
			[
				'name'        => esc_html__( 'Author Description', 'rank-math' ),
				'description' => esc_html__( 'Author\'s biographical info of the current post, page or author archive.', 'rank-math' ),
				'variable'    => 'user_description',
				'example'     => get_the_author_meta( 'description', $user_id ),
			],
			[ $this, 'get_user_description' ]
		);
	}

	/**
	 * Get the post author's user ID to use as a replacement.
	 *
	 * @return string
	 */
	public function get_userid() {
		return ! empty( $this->args->post_author ) ? $this->args->post_author : get_query_var( 'author' );
	}

	/**
	 * Get the post author's "nice name" to use as a replacement.
	 *
	 * @return string|null
	 */
	public function get_name() {
		$user_id = $this->get_userid();
		$name    = get_the_author_meta( 'display_name', $user_id );

		return '' !== $name ? $name : null;
	}

	/**
	 * Get the post author's user description to use as a replacement.
	 *
	 * @return string|null
	 */
	public function get_user_description() {
		$user_id     = $this->get_userid();
		$description = get_the_author_meta( 'description', $user_id );

		return '' !== $description ? $description : null;
	}
}
                                                                                                                                                                                                                                                                                                     <?php
/**
 * Term variable replacer.
 *
 * @since      1.0.33
 * @package    RankMath
 * @subpackage RankMath\Replace_Variables
 * @author     Rank Math <support@rankmath.com>
 */

namespace RankMath\Replace_Variables;

use MyThemeShop\Helpers\Str;
use MyThemeShop\Helpers\Param;

defined( 'ABSPATH' ) || exit;

/**
 * Term_Variables class.
 */
class Term_Variables extends Basic_Variables {

	/**
	 * Setup term variables.
	 */
	public function setup_term_variables() {
		if ( $this->is_term_edit ) {
			$tag_id = Param::request( 'tag_ID', 0, FILTER_VALIDATE_INT );
			$term   = get_term( $tag_id, $GLOBALS['taxnow'], OBJECT );
		}

		$this->register_replacement(
			'term',
			[
				'name'        => esc_html__( 'Current Term', 'rank-math' ),
				'description' => esc_html__( 'Current term name', 'rank-math' ),
				'variable'    => 'term',
				'example'     => $this->is_term_edit ? $term->name : esc_html__( 'Example Term', 'rank-math' ),
			],
			[ $this, 'get_term' ]
		);

		$this->register_replacement(
			'term_description',
			[
				'name'        => esc_html__( 'Term Description', 'rank-math' ),
				'description' => esc_html__( 'Current term description', 'rank-math' ),
				'variable'    => 'term_description',
				'example'     => $this->is_term_edit ? wp_strip_all_tags( term_description( $term ), true ) : esc_html__( 'Example Term Description', 'rank-math' ),
			],
			[ $this, 'get_term_description' ]
		);

		$this->register_replacement(
			'customterm',
			[
				'name'        => esc_html__( 'Custom Term (advanced)', 'rank-math' ),
				'description' => esc_html__( 'Custom term value.', 'rank-math' ),
				'variable'    => 'customterm(taxonomy-name)',
				'example'     => esc_html__( 'Custom term value', 'rank-math' ),
			],
			[ $this, 'get_custom_term' ]
		);

		$this->register_replacement(
			'customterm_desc',
			[
				'name'        => esc_html__( 'Custom Term description', 'rank-math' ),
				'description' => esc_html__( 'Custom Term description.', 'rank-math' ),
				'variable'    => 'customterm_desc(taxonomy-name)',
				'example'     => esc_html__( 'Custom Term description.', 'rank-math' ),
			],
			[ $this, 'get_custom_term_desc' ]
		);
	}

	/**
	 * Get the term name to use as a replacement.
	 *
	 * @return string|null
	 */
	public function get_term() {
		global $wp_query;

		if ( is_category() || is_tag() || is_tax() ) {
			return $wp_query->queried_object->name;
		}

		return ! empty( $this->args->taxonomy ) && ! empty( $this->args->name ) ? $this->args->name : null;
	}

	/**
	 * Get the term description to use as a replacement.
	 *
	 * @return string|null
	 */
	public function get_term_description() {
		global $wp_query;

		if ( is_category() || is_tag() || is_tax() ) {
			return $wp_query->queried_object->description;
		}

		if ( ! isset( $this->args->term_id ) || empty( $this->args->taxonomy ) ) {
			return null;
		}

		$term_desc = get_term_field( 'description', $this->args->term_id, $this->args->taxonomy );
		return '' !== $term_desc ? Str::truncate( $term_desc, 160 ) : null;
	}

	/**
	 * Get a custom taxonomy term to use as a replacement.
	 *
	 * @param string $taxonomy The name of the taxonomy.
	 *
	 * @return string|null
	 */
	public function get_custom_term( $taxonomy ) {
		global $post;

		return Str::is_non_empty( $taxonomy ) ? $this->get_terms( $post->ID, $taxonomy, true, [], 'name' ) : null;
	}

	/**
	 * Get a custom taxonomy term description to use as a replacement.
	 *
	 * @param string $taxonomy The name of the taxonomy.
	 *
	 * @return string|null
	 */
	public function get_custom_term_desc( $taxonomy ) {
		global $post;

		return Str::is_non_empty( $taxonomy ) ? $this->get_terms( $post->ID, $taxonomy, true, [], 'description' ) : null;
	}
}
