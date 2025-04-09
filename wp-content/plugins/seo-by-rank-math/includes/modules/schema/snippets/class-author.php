<?php
/**
 * The Easy Digital Downloads Product Class.
 *
 * @since      1.0.13
 * @package    RankMath
 * @subpackage RankMath\Schema
 * @author     Rank Math <support@rankmath.com>
 */

namespace RankMath\Schema;

use EDD_Download;
use RankMath\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Product_Edd class.
 */
class Product_Edd {

	/**
	 * Set product data for rich snippet.
	 *
	 * @param array  $entity Array of JSON-LD entity.
	 * @param JsonLD $jsonld JsonLD Instance.
	 */
	public function set_product( &$entity, $jsonld ) {
		$product_id = get_the_ID();
		$permalink  = get_permalink();
		$product    = new EDD_Download( $product_id );

		$entity['url']      = $permalink;
		$entity['name']     = $jsonld->post->post_title;
		$entity['category'] = Product::get_category( $product_id, 'downlo<?php
/**
 * The Author Class.
 *
 * @since      1.0.13
 * @package    RankMath
 * @subpackage RankMath\Schema
 * @author     Rank Math <support@rankmath.com>
 */

namespace RankMath\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Author class.
 */
class Author implements Snippet {

	/**
	 * Sets the Schema structured data for the ProfilePage.
	 *
	 * @link https://schema.org/ProfilePage
	 *
	 * @param array  $data   Array of JSON-LD data.
	 * @param JsonLD $jsonld JsonLD Instance.
	 *
	 * @return array
	 */
	public function process( $data, $jsonld ) {
		$data['ProfilePage'] = [
			'@type' => 'Person',
			'name'  => get_the_author(),
		];

		if ( ! empty( $data['WebPage'] ) ) {
			$data['ProfilePage']['mainEntityOfPage'] = [
				'@id' => $data['WebPage']['@id'],
			];
		}

		return $data;
	}
}
