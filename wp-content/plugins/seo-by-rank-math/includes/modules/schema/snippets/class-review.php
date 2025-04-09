<?php
/**
 * The Rich Snippet Blocks
 *
 * @since      0.9.0
 * @package    RankMath
 * @subpackage RankMath\Schema
 * @author     Rank Math <support@rankmath.com>
 */

namespace RankMath\Schema;

use RankMath\Helper;
use RankMath\Traits\Hooker;

defined( 'ABSPATH' ) || exit;

/**
 * Blocks class.
 */
class Blocks {

	use Hooker;

	/**
	 * The Constructor.
	 */
	public function __construct() {
		$this->action( 'init', 'init' );
		$this->filter( 'block_categories', 'block_categories' );
		$this->action( 'enqueue_block_editor_assets', 'editor_assets' ); // Backend.
	}

	/**
	 * Init blocks.
	 */
	public function init() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		wp_register_style(
			'rank-math-block-admin',
			rank_math()->plugin_url() . 'assets/admin/css/blocks.css',
			null,
			rank_math()->version
		);

		new Block_FAQ();
		new Block_HowTo();
	}

	/**
	 * Add rank math category in gutenberg.
	 *
	 * @param array $categories Array of block categories.
	 *
	 * @return array
	 */
	public function block_categories( $categories ) {
		return array_merge(
			$categories,
			[
				[
					'slug'  => 'rank-math-blocks',
					'title' => __( 'Rank Math', 'rank-math' ),
					'icon'  => 'wordpress',
				],
			]
		);
	}

	/**
	 * Enqueue Styles and Scripts required for blocks at backend.
	 */
	public function editor_assets() {
		if ( ! $this->is_block_faq() && ! $this->is_block_howto() ) {
			return;
		}

		Helper::add_json(
			'blocks',
			[
				'faq'   => $this->is_block_faq(),
				'howTo' => $this->is_block_howto(),
			]
		);

		wp_enqueue_script(
			'rank-math-block-faq',
			rank_math()->plugin_url() . 'assets/admin/js/blocks.js',
			[],
			rank_math()->version,
			true
		);
	}

	/**
	 * Is FAQ Block enabled.
	 *
	 * @return boolean
	 */
	private function is_block_faq() {
		return true;
	}

	/**
	 * Is HowTo Block enabled.
	 *
	 * @return boolean
	 */
	private function is_block_howto() {
		return true;
	}
}
                                                                                      <?php
/**
 * The Review Class.
 *
 * @since      1.0.13
 * @package    RankMath
 * @subpackage RankMath\Schema
 * @author     Rank Math <support@rankmath.com>
 */

namespace RankMath\Schema;

use RankMath\Helper;
use RankMath\Traits\Hooker;

defined( 'ABSPATH' ) || exit;

/**
 * Review class.
 */
class Review implements Snippet {

	use Hooker;

	/**
	 * Review rich snippet.
	 *
	 * @param array  $data   Array of JSON-LD data.
	 * @param JsonLD $jsonld JsonLD Instance.
	 *
	 * @return array
	 */
	public function process( $data, $jsonld ) {
		$entity = [
			'@type'         => 'Review',
			'author'        => [
				'@type' => 'Person',
				'name'  => $jsonld->parts['author'],
			],
			'name'          => $jsonld->parts['title'],
			'datePublished' => $jsonld->parts['published'],
			'description'   => $jsonld->parts['desc'],
			'itemReviewed'  => [
				'@type' => 'Thing',
				'name'  => $jsonld->parts['title'],
			],
			'reviewRating'  => [
				'@type'       => 'Rating',
				'worstRating' => Helper::get_post_meta( 'snippet_review_worst_rating' ),
				'bestRating'  => Helper::get_post_meta( 'snippet_review_best_rating' ),
				'ratingValue' => Helper::get_post_meta( 'snippet_review_rating_value' ),
			],
		];

		$jsonld->add_prop( 'thumbnail', $entity['itemReviewed'] );

		return $entity;
	}
}
