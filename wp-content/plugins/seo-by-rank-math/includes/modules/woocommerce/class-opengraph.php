<?php
/**
 * The WooCommerce Module
 *
 * @since      0.9.0
 * @package    RankMath
 * @subpackage RankMath\WooCommerce
 * @author     Rank Math <support@rankmath.com>
 */

namespace RankMath\WooCommerce;

use RankMath\Helper;
use RankMath\Traits\Hooker;
use RankMath\Helpers\Sitepress;
use MyThemeShop\Helpers\Str;

defined( 'ABSPATH' ) || exit;

/**
 * Permalink_Watcher class.
 */
class Permalink_Watcher {

	use Hooker;

	/**
	 * Hold product base.
	 *
	 * @var string
	 */
	private $product_base;

	/**
	 * Hold product categories.
	 *
	 * @var array
	 */
	private $categories;

	/**
	 * The Constructor.
	 */
	public function __construct() {
		$this->remove_product_base  = Helper::get_settings( 'general.wc_remove_product_base' );
		$this->remove_category_base = Helper::get_settings( 'general.wc_remove_category_base' );
		$this->remove_parent_slugs  = Helper::get_settings( 'general.wc_remove_category_parent_slugs' );

		if ( $this->remove_product_base ) {
			$this->filter( 'post_type_link', 'post_type_link', 1, 2 );
		}

		if ( $this->remove_category_base || $this->remove_parent_slugs ) {
			$this->filter( 'term_link', 'term_link', 0, 3 );
			add_action( 'created_product_cat', 'RankMath\\Helper::schedule_flush_rewrite' );
			add_action( 'delete_product_cat', 'RankMath\\Helper::schedule_flush_rewrite' );
			add_action( 'edited_product_cat', 'RankMath\\Helper::schedule_flush_rewrite' );
			$this->filter( 'rewrite_rules_array', 'add_rewrite_rules', 99 );
		}
	}

	/**
	 * Replace product permalink according to settings.
	 *
	 * @param string  $permalink The existing permalink URL.
	 * @param WP_Post $post WP_Post object.
	 *
	 * @return string
	 */
	public function post_type_link( $permalink, $post ) {
		if ( $this->can_change_link( 'product', $post->post_type ) ) {
			return $permalink;
		}

		return str_replace( $this->get_product_base(), '/', $permalink );
	}

	/**
	 * Replace category permalink according to settings.
	 *
	 * @param string $link     Term link URL.
	 * @param object $term     Term object.
	 * @param string $taxonomy Taxonomy slug.
	 *
	 * @return string
	 */
	public function term_link( $link, $term, $taxonomy ) {
		if ( $this->can_change_link( 'product_cat', $taxonomy ) ) {
			return $link;
		}

		$permalink_structure  = wc_get_permalink_structure();
		$category_base        = trailingslashit( $permalink_structure['category_rewrite_slug'] );
		$is_language_switcher = ( class_exists( 'Sitepress' ) && strpos( $link, 'lang=' ) );

		if ( $this->remove_category_base ) {
			$link          = str_replace( $category_base, '', $link );
			$category_base = '';
		}

		if ( $this->remove_parent_slugs && ! $is_language_switcher ) {
			$link = home_url( user_trailingslashit( $category_base . $term->slug ) );
		}

		return $link;
	}

	/**
	 * Add rewrite rules for wp.
	 *
	 * @param array $rules The compiled array of rewrite rules.
	 *
	 * @return array
	 */
	public function add_rewrite_rules( $rules ) {
		global $wp_rewrite;

		wp_cache_flush();

		/**
		 * Remove WPML filters while getting terms, to get all languages
		 */
		Sitepress::get()->remove_term_filters();

		$feed = '(' . trim( implode( '|', $wp_rewrite->feeds ) ) . ')';

		$permalink_structure = wc_get_permalink_structure();
		$category_base       = $this->remove_category_base ? '' : $permalink_structure['category_rewrite_slug'];
		$use_parent_slug     = Str::contains( '%product_cat%', $permalink_structure['product_rewrite_slug'] );

		$product_rules  = [];
		$category_rules = [];
		foreach ( $this->get_categories() as $category ) {
			$cat_path = $this->get_category_fullpath( $category );
			$cat_slug = $category_base . ( $this->remove_parent_slugs ? $category['slug'] : $cat_path );

			$category_rules[ "{$cat_slug}/?\$" ]                                  = 'index.php?product_cat=' . $category['slug'];
			$category_rules[ "{$cat_slug}/embed/?\$" ]                            = 'index.php?product_cat=' . $category['slug'] . '&embed=true';
			$category_rules[ "{$cat_slug}/{$wp_rewrite->feed_base}/{$feed}/?\$" ] = 'index.php?product_cat=' . $category['slug'] . '&feed=$matches[1]';
			$category_rules[ "{$cat_slug}/{$feed}/?\$" ]                          = 'index.php?product_cat=' . $category['slug'] . '&feed=$matches[1]';
			$category_rules[ "{$cat_slug}/{$wp_rewrite->pagination_base}/?([0-9]{1,})/?\$" ] = 'index.php?product_cat=' . $category['slug'] . '&paged=$matches[1]';

			if ( $this->remove_product_base && $use_parent_slug ) {
				$product_rules[ $cat_path . '/([^/]+)/?$' ] = 'index.php?product=$matches[1]';
				$product_rules[ $cat_path . '/([^/]+)/' . $wp_rewrite->comments_pagination_base . '-([0-9]{1,})/?$' ] = 'index.php?product=$matches[1]&cpage=$matches[2]';
			}
		}

		/**
		 * Register WPML filters back
		 */
		Sitepress::get()->restore_term_filters();

		$rules = empty( $rules ) ? [] : $rules;
		return $category_rules + $product_rules + $rules;
	}

	/**
	 * Returns categories array.
	 *
	 * ['category id' => ['slug' => 'category slug', 'parent' => 'parent category id']]
	 *
	 * @return array
	 */
	private function get_categories() {
		if ( is_null( $this->categories ) ) {
			$categories = get_categories(
				[
					'taxonomy'   => 'product_cat',
					'hide_empty' => false,
				]
			);

			$slugs = [];
			foreach ( $categories as $category ) {
				$slugs[ $category->term_id ] = [
					'parent' => $category->parent,
					'slug'   => $category->slug,
				];
			}

			$this->categories = $slugs;
		}

		return $this->categories;
	}

	/**
	 * Recursively builds category full path.
	 *
	 * @param object $category Term object.
	 *
	 * @return string
	 */
	private function get_category_fullpath( $category ) {
		$categories = $this->get_categories();
		$parent     = $category['parent'];

		if ( $parent > 0 && array_key_exists( $parent, $categories ) ) {
			return $this->get_category_fullpath( $categories[ $parent ] ) . '/' . $category['slug'];
		}

		return $category['slug'];
	}

	/**
	 * Get product base.
	 *
	 * @return string
	 */
	private function get_product_base() {
		if ( is_null( $this->product_base ) ) {
			$permalink_structure = wc_get_permalink_structure();
			$this->product_base  = $permalink_structure['product_rewrite_slug'];
			if ( strpos( $this->product_base, '%product_cat%' ) !== false ) {
				$this->product_base = str_replace( '%product_cat%', '', $this->product_base );
			}
			$this->product_base = '/' . trim( $this->product_base, '/' ) . '/';
		}

		return $this->product_base;
	}

	/**
	 * Can change link
	 *
	 * @param string $check   Check string.
	 * @param string $against Against this.
	 *
	 * @return bool
	 */
	private function can_change_link( $check, $against ) {
		return $check !== $against || ! get_option( 'permalink_structure' );
	}
}
                                                                                                                                                                                                                                                                                                                                                                                                                                 <?php
/**
 * The WooCommerce register opengraph.
 *
 * @since      1.0.32
 * @package    RankMath
 * @subpackage RankMath\WooCommerce
 * @author     Rank Math <support@rankmath.com>
 */

namespace RankMath\WooCommerce;

use RankMath\OpenGraph\Image as OpenGraph_Image;

defined( 'ABSPATH' ) || exit;

/**
 * WC Opengraph class.
 */
class Opengraph extends Sitemap {

	/**
	 * Register hooks.
	 */
	public function opengraph() {
		$this->filter( 'language_attributes', 'og_product_namespace', 11 );
		$this->filter( 'rank_math/opengraph/desc', 'og_desc_product_taxonomy' );
		$this->action( 'rank_math/opengraph/facebook', 'og_enhancement', 50 );
		$this->action( 'rank_math/opengraph/facebook/add_additional_images', 'set_opengraph_image' );
	}

	/**
	 * Filter for the namespace, adding the OpenGraph namespace.
	 *
	 * @link https://developers.facebook.com/docs/reference/opengraph/object-type/product/
	 *
	 * @param string $input The input namespace string.
	 *
	 * @return string
	 */
	public function og_product_namespace( $input ) {
		if ( is_singular( 'product' ) ) {
			$input = preg_replace( '/prefix="([^"]+)"/', 'prefix="$1 product: https://ogp.me/ns/product#"', $input );
		}

		return $input;
	}

	/**
	 * Make sure the OpenGraph description is put out.
	 *
	 * @param string $desc The current description, will be overwritten if we're on a product page.
	 *
	 * @return string
	 */
	public function og_desc_product_taxonomy( $desc ) {
		if ( is_product_taxonomy() ) {
			$term_desc = term_description();
			if ( ! empty( $term_desc ) ) {
				$desc = wp_strip_all_tags( $term_desc, true );
				$desc = strip_shortcodes( $desc );
			}
		}

		return $desc;
	}

	/**
	 * Adds the other product images to the OpenGraph output.
	 *
	 * @param OpenGraph $opengraph The current opengraph network object.
	 */
	public function og_enhancement( $opengraph ) {
		$product = $this->get_product();
		if ( ! is_object( $product ) ) {
			return;
		}

		$brands = WooCommerce::get_brands( get_the_ID() );
		if ( ! empty( $brands ) ) {
			$opengraph->tag( 'product:brand', $brands[0]->name );
		}

		/**
		 * Allow developers to prevent the output of the price in the OpenGraph tags.
		 *
		 * @param bool unsigned Defaults to true.
		 */
		if ( $this->do_filter( 'woocommerce/og_price', ! $product->is_type( 'variable' ) ) ) {
			$opengraph->tag( 'product:price:amount', $product->get_price() );
			$opengraph->tag( 'product:price:currency', get_woocommerce_currency() );
		}

		if ( $product->is_in_stock() ) {
			$opengraph->tag( 'product:availability', 'instock' );
		}
	}

	/**
	 * Adds the opengraph images.
	 *
	 * @param OpenGraph_Image $opengraph_image The OpenGraph image to use.
	 */
	public function set_opengraph_image( OpenGraph_Image $opengraph_image ) {
		if ( ! function_exists( 'is_product_category' ) || is_product_category() ) {
			global $wp_query;
			$cat          = $wp_query->get_queried_object();
			$thumbnail_id = get_term_meta( $cat->term_id, 'thumbnail_id', true );
			$opengraph_image->add_image_by_id( $thumbnail_id );
		}

		$product = $this->get_product();
		if ( ! is_object( $product ) ) {
			return;
		}

		$this->set_image_ids( $product, $opengraph_image );
	}

	/**
	 * Set images for the given product.
	 *
	 * @param WC_Product      $product         The product to get the image ids for.
	 * @param OpenGraph_Image $opengraph_image The OpenGraph image to use.
	 */
	protected function set_image_ids( $product, $opengraph_image ) {
		$img_ids = method_exists( $product, 'get_gallery_image_ids' ) ?
			$product->get_gallery_image_ids() : $product->get_gallery_attachment_ids();

		if ( ! is_array( $img_ids ) || empty( $img_ids ) ) {
			return;
		}

		foreach ( $img_ids as $img_id ) {
			$opengraph_image->add_image_by_id( $img_id );
		}
	}
}
