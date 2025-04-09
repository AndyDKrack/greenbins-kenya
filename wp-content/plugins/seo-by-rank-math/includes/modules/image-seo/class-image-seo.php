<?php
/**
 * SEO Analysis admin page contents.
 *
 * @package   RANK_MATH
 * @author    Rank Math <support@rankmath.com>
 * @license   GPL-2.0+
 * @link      https://rankmath.com/wordpress/plugin/seo-suite/
 * @copyright 2019 Rank Math
 */

defined( 'ABSPATH' ) || exit;

?>
<h3 class="health-check-accordion-heading">
	<button aria-expanded="false" class="health-check-accordion-trigger" aria-controls="health-check-accordion-block-<?php echo esc_attr( $section ); ?>" type="button">
		<span class="title">
			<?php echo esc_html( $details['label'] ); ?>
			<?php

			if ( isset( $details['show_count'] ) && $details['show_count'] ) {
				printf( '(%d)', count( $details['field<?php
/**
 * Image SEO module.
 *
 * @since      1.0
 * @package    RankMath
 * @author     Rank Math <support@rankmath.com>
 */

namespace RankMath\Image_Seo;

use RankMath\KB;
use RankMath\Runner;
use RankMath\Helper;
use RankMath\Admin\Admin_Helper;
use RankMath\Traits\Hooker;
use MyThemeShop\Helpers\HTML;

defined( 'ABSPATH' ) || exit;

/**
 * Image_Seo class.
 *
 * @codeCoverageIgnore
 */
class Image_Seo {

	use Hooker;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->load_admin();

		new Add_Attributes();
	}

	/**
	 * Load admin functionality.
	 */
	private function load_admin() {
		if ( is_admin() ) {
			$this->admin = new Admin();
		}
	}

}
