<?php
/**
 * Redirect Core Sitemap class.
 *
 * @since      1.0.47
 * @package    RankMath
 * @subpackage RankMath\Sitemap
 * @author     Rank Math <support@rankmath.com>
 */

namespace RankMath\Sitemap;

use RankMath\Traits\Hooker;
use MyThemeShop\Helpers\Str;

defined( 'ABSPATH' ) || exit;

/**
 * Redirect Core Sitemaps class
 *
 * @copyright Copyright (C) 2008-2019, Yoast BV
 * The following code is a derivative work of the code from the Yoast(https://github.com/Yoast/wordpress-seo/), which is licensed under GPL v3.
 */
class Redirect_Core_Sitemaps {

	use Hooker;

	/**
	 * The Constructor.
	 */
	public function __construct() {
		/**
		 * Disable the WP core XML sitemaps.
		 */
		add_filter( 'wp_sitemaps_enabled', '__return_false' );
		$this->action( 'template_redirect', 'redirect_core_sitemaps' );
	}

	/**
	 * Redirect Core sitemap links to Rank Math Sitemaps.
	 */
	public function redirect_core_sitemaps() {
		// Early Bail.
		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}

		$path = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );

		// Early bail if it's not a WP Core sitemap link.
		if ( ! Str::starts_with( '/wp-sitemap', $path ) ) {
			return;
		}

		$redirect = $this->get_redirect_url( $path );

		if ( ! $redirect ) {
			return;
		}

		wp_safe_redirect( home_url( $redirect ), 301 );
		exit;
	}

	/**
	 * Get Redirect URL by path.
	 *
	 * @param string $path The original path.
	 *
	 * @return string The URL to redirect.
	 */
	private function get_redirect_url( $path ) {
		if ( '/wp-sitemap.xml' === $path ) {
			return '/sitemap_index.xml';
		}

		if ( preg_match( '/^\/wp-sitemap-(posts|taxonomies)-(\w+)-(\d+)\.xml$/', $path, $matches ) ) {
			$index = ( (int) $matches[3] - 1 );
			$index = 0 !== $index ? (string) $index : '';

			return '/' . $matches[2] . '-sitemap' . $index . '.xml';
		}

		if ( preg_match( '/^\/wp-sitemap-users-(\d+)\.xml$/', $path, $matches ) ) {
			$index = ( (int) $matches[1] - 1 );
			$index = 0 !== $index ? (string) $index : '';

			return '/author-sitemap' . $index . '.xml';
		}

		return false;
	}
}
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    <?php
/**
 * The Sitemap Stylesheet
 *
 * @since      0.9.0
 * @package    RankMath
 * @subpackage RankMath\Sitemap
 * @author     Rank Math <support@rankmath.com>
 *
 * @copyright Copyright (C) 2008-2019, Yoast BV
 * The following code is a derivative work of the code from the Yoast(https://github.com/Yoast/wordpress-seo/), which is licensed under GPL v3.
 */

namespace RankMath\Sitemap;

use RankMath\Helper;
use RankMath\Traits\Hooker;

defined( 'ABSPATH' ) || exit;

/**
 * Stylesheet class.
 */
class Stylesheet extends XML {

	use Hooker;

	/**
	 * Spits out the XSL for the XML sitemap.
	 *
	 * @param string $type Sitemap type.
	 */
	public function output( $type ) {
		$this->type = $type;
		$this->send_headers();

		/* translators: 1. separator, 2. blogname */
		$title = sprintf( __( 'XML Sitemap %1$s %2$s', 'rank-math' ), '-', get_bloginfo( 'name', 'display' ) );

		if ( 'main' !== $type ) {
			/**
			 * Fires for the output of XSL for XML sitemaps, other than type "main".
			 */
			$this->do_action( "sitemap/xsl_{$type}", $title );
			die;
		}

		require_once 'sitemap-xsl.php';
		die;
	}
}
