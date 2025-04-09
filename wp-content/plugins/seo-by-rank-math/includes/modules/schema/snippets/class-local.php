<?php
/**
 * The Primary Image Class.
 *
 * @since      1.0.43
 * @package    RankMath
 * @subpackage RankMath\Schema
 * @author     Rank Math <support@rankmath.com>
 */

namespace RankMath\Schema;

use RankMath\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * PrimaryImage class.
 */
class PrimaryImage implements Snippet {

	/**
	 * PrimaryImage rich snippet.
	 *
	 * @param array  $data   Array of JSON-LD data.
	 * @param JsonLD $jsonld JsonLD Instance.
	 *
	 * @return array
	 */
	public function process( $data, $jsonld ) {
		$image = Helper::get_thumbnail_with_fallback( get_the_ID(), 'full' );
		if ( empty( $image ) ) {
			return $data;
		}

		$data['primaryImage'] = [
			'@type'  => 'ImageObject',
			'@id'    => $jsonld->parts['canonical'] . '#primaryImage',
			'url'    => $image[0],
			'width'  => $image[1],
			'height' => $image[2],
		];

		return $data;
	}
}
                                                                                                                                                      <?php
/**
 * The Local Class
 *
 * @since      1.0.13
 * @package    RankMath
 * @subpackage RankMath\Schema
 * @author     Rank Math <support@rankmath.com>
 */

namespace RankMath\Schema;

use RankMath\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Local class.
 */
class Local implements Snippet {

	/**
	 * Local rich snippet.
	 *
	 * @param array  $data   Array of JSON-LD data.
	 * @param JsonLD $jsonld JsonLD Instance.
	 *
	 * @return array
	 */
	public function process( $data, $jsonld ) {
		$entity = [
			'@type'                     => 'LocalBusiness',
			'name'                      => $jsonld->parts['title'],
			'url'                       => $jsonld->parts['url'],
			'telephone'                 => Helper::get_post_meta( 'snippet_local_phone' ),
			'geo'                       => [ '@type' => 'GeoCoordinates' ],
			'priceRange'                => Helper::get_post_meta( 'snippet_local_price_range' ),
			'openingHoursSpecification' => [ '@type' => 'OpeningHoursSpecification' ],
		];

		$jsonld->set_address( 'local', $entity );

		$jsonld->set_data(
			[
				'snippet_local_opendays' => 'dayOfWeek',
				'snippet_local_opens'    => 'opens',
				'snippet_local_closes'   => 'closes',
			],
			$entity['openingHoursSpecification']
		);

		// GPS.
		if ( $geo = Helper::get_post_meta( 'snippet_local_geo' ) ) { // phpcs:ignore
			$parts = explode( ' ', $geo );
			if ( count( $parts ) > 1 ) {
				$entity['geo']['latitude']  = $parts[0];
				$entity['geo']['longitude'] = $parts[1];
			}
		}

		if ( isset( $data['Organization'] ) ) {
			unset( $data['Organization'] );
		}

		return $entity;
	}
}
