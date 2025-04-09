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

use RankMath\KB;
use RankMath\Helper;
use RankMath\Admin\Admin_Helper;
use RankMath\Module\Base;
use RankMath\Traits\Hooker;
use MyThemeShop\Helpers\Arr;

defined( 'ABSPATH' ) || exit;

/**
 * Admin class.
 */
class Admin extends Base {

	use Hooker;

	/**
	 * The Constructor.
	 */
	public function __construct() {

		$directory = dirname( __FILE__ );
		$this->config(
			[
				'id'        => 'woocommerce',
				'directory' => $directory,
			]
		);
		parent::__construct();

		// Permalink Manager.
		$this->filter( 'rank_math/settings/general', 'add_general_settings' );
		$this->filter( 'rank_math/flush_fields', 'flush_fields' );

		$this->action( 'rank_math/admin/enqueue_scripts', 'enqueue' );
	}

	/**
	 * Enqueue script to analyze product's short description.
	 */
	public function enqueue() {
		$screen = get_current_screen();
		if ( ! Admin_Helper::is_post_edit() || 'product' !== $screen->post_type || ! $this->do_filter( 'woocommerce/analyze_short_description', true ) ) {
			return;
		}

		wp_enqueue_script( 'rank-math-description-analysis', rank_math()->plugin_url() . 'assets/admin/js/product-description.js', [ 'rank-math-metabox' ], rank_math()->version, true );
	}

	/**
	 * Add module settings into general optional panel.
	 *
	 * @param array $tabs Array of option panel tabs.
	 *
	 * @return array
	 */
	public function add_general_settings( $tabs ) {
		Arr::insert(
			$tabs,
			[
				'woocommerce' => [
					'icon'  => 'rm-icon rm-icon-cart',
					'title' => esc_html__( 'WooCommerce', 'rank-math' ),
					/* translators: Link to kb article */
					'desc'  => sprintf( esc_html__( 'Choose how you want Rank Math to handle your WooCommerce SEO. %s.', 'rank-math' ), '<a href="' . KB::get( 'woocommerce-settings' ) . '" target="_blank">' . esc_html__( 'Learn more', 'rank-math' ) . '</a>' ),
					'file'  => $this->directory . '/views/options-general.php',
				],
			],
			7
		);

		return $tabs;
	}

	/**
	 * Fields after updation of which we need to flush rewrite rules.
	 *
	 * @param array $fields Fields to flush rewrite rules on.
	 *
	 * @return array
	 */
	public function flush_fields( $fields ) {
		$fields[] = 'wc_remove_product_base';
		$fields[] = 'wc_remove_category_base';
		$fields[] = 'wc_remove_category_parent_slugs';

		return $fields;
	}
}
                                                                        /*global rankMathEditor, tinymce, accounting*/

/**
 * External dependencies
 */
import $ from 'jquery'
import debounce from 'lodash/debounce'

/**
 * WordPress dependencies
 */
import { doAction, addFilter } from '@wordpress/hooks'

/**
 * Internal dependencies
 */
import { swapVariables } from '@helpers/swapVariables'

/**
 * RankMath custom fields integration class
 */
class RankMathProductDescription {
	constructor() {
		this.excerpt = $( '#excerpt' )
		this.elemPrice = $( '#_sale_price' )
		this.elemRegPrice = $( '#_regular_price' )

		// Refresh functions.
		this.refreshWCPrice = this.refreshWCPrice.bind( this )
		this.events()
		this.hooks()
	}

	/**
	 * Hook into Rank Math App eco-system
	 */
	hooks() {
		if ( undefined === this.excerpt ) {
			return
		}
		addFilter( 'rank_math_content', 'rank-math', this.getContent.bind( this ) )
	}

	/**
	 * Gather custom fields data for analysis
	 *
	 * @param {string} content Content
	 *
	 * @return {string} New content
	 */
	getContent( content ) {
		content += ( 'undefined' !== typeof tinymce && tinymce.activeEditor && 'excerpt' === tinymce.activeEditor.id ) ? tinymce.activeEditor.getContent() : this.excerpt.val()
		return content
	}

	/**
	 * Capture events from custom fields to refresh Rank Math analysis
	 */
	events() {
		if ( 'undefined' !== typeof tinymce && tinymce.activeEditor && 'undefined' !== typeof tinymce.editors.excerpt ) {
			tinymce.editors.excerpt.on( 'keyup change', debounce( () => {
				rankMathEditor.refresh( 'content' )
			}, 500 ) )
		}

		// WC Events
		this.debounceWCPrice = debounce( this.refreshWCPrice, 500 )
		this.elemPrice.on( 'input', this.debounceWCPrice )
		this.elemRegPrice.on( 'input', this.debounceWCPrice )
	}

	refreshWCPrice() {
		swapVariables.setVariable( 'wc_price', this.getWooCommerceProductPrice() )
		doAction( 'rank_math_update_description_preview' )
	}

	getWooCommerceProductPrice() {
		const price = this.elemPrice.val() ? this.elemPrice.val() : this.elemRegPrice.val()
		return accounting.formatMoney( price, {
			symbol: woocommerce_admin_meta_boxes.currency_format_symbol, // eslint-disable-line
			decimal: woocommerce_admin_meta_boxes.currency_format_decimal_sep, // eslint-disable-line
			thousand: woocommerce_admin_meta_boxes.currency_format_thousand_sep, // eslint-disable-line
			precision: woocommerce_admin_meta_boxes.currency_format_num_decimals, // eslint-disable-line
			format: woocommerce_admin_meta_boxes.currency_format // eslint-disable-line
		} )
	}
}

$( function() {
	new RankMathProductDescription()
} )
