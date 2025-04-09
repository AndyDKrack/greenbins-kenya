<?php
/**
 * The Analytics Module
 *
 * @since      1.0.49
 * @package    RankMath
 * @subpackage RankMath\modules
 * @author     Rank Math <support@rankmath.com>
 */

namespace RankMath\Analytics;

use stdClass;
use Exception;
use WP_Error;
use WP_REST_Request;
use RankMath\Helper;
use RankMath\Google\Api;
use RankMath\Analytics\DB;
use RankMath\Traits\Hooker;
use MyThemeShop\Helpers\Param;

defined( 'ABSPATH' ) || exit;

/**
 * Posts class.
 */
class Posts extends Objects {

	/**
	 * Get post data.
	 *
	 * @param int $id Post id.
	 *
	 * @return object
	 */
	public function get_post( $id ) {
		$post = DB::objects()
			->where( 'object_id', $id )
			->one();

		if ( is_null( $post ) ) {
			return [ 'errorMessage' => esc_html__( 'Sorry, no post found for given id.', 'rank-math' ) ];
		}

		$metrices = $this->get_analytics_data(
			[
				'pages'     => [ $post->page ],
				'pageview'  => true,
				'sub_where' => " AND page = '{$post->page}'",
			]
		);
		if ( ! empty( $metrices ) ) {
			$metrices = current( $metrices );
		}

		// Keywords.
		$keywords = DB::analytics()
			->distinct()
			->selectCount( 'query', 'keywords' )
			->whereLike( 'page', $post->page, '%', '' )
			->whereBetween( 'created', [ $this->start_date, $this->end_date ] )
			->where( 'clicks', '>', 0 )
			->getVar();

		$old_keywords = DB::analytics()
			->distinct()
			->selectCount( 'query', 'keywords' )
			->whereLike( 'page', $post->page, '%', '' )
			->where( 'clicks', '>', 0 )
			->whereBetween( 'created', [ $this->compare_start_date, $this->compare_end_date ] )
			->getVar();

		$post->keywords = [
			'total'      => (int) $keywords,
			'previous'   => (int) $old_keywords,
			'difference' => $keywords - $old_keywords,
		];

		$post = apply_filters( 'rank_math/analytics/single/report', $post, $this );

		return array_merge(
			(array) $post,
			(array) $metrices
		);
	}

	/**
	 * Get posts by objects.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_posts_rows_by_objects( WP_REST_Request $request ) {
		$pre = apply_filters( 'rank_math/analytics/get_posts_rows_by_objects', false, $request );
		if ( false !== $pre ) {
			return $pre;
		}

		// Pagination.
		$per_page = 25;
		$offset   = ( $request->get_param( 'page' ) - 1 ) * $per_page;

		$objects = $this->get_objects_by_score( $request );
		$pages   = \array_keys( $objects['rows'] );
		$console = $this->get_analytics_data(
			[
				'limit'     => "LIMIT {$offset}, {$per_page}",
				'sub_where' => " AND page IN ('" . join( "', '", $pages ) . "')",
			]
		);

		$new_rows = [];
		foreach ( $objects['rows'] as $object ) {
			$page = $object['page'];

			if ( isset( $console[ $page ] ) ) {
				$object = \array_merge( $console[ $page ], $object );
			}

			if ( ! isset( $object['links'] ) ) {
				$object['links'] = new stdClass();
			}

			$new_rows[ $page ] = $object;
		}

		return [
			'rows'      => $new_rows,
			'rowsFound' => $objects['rowsFound'],
		];
	}
}
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     <?php
/**
 * The Global functionality of the plugin.
 *
 * Defines the functionality loaded on admin.
 *
 * @since      1.0.49
 * @package    RankMath
 * @subpackage RankMath\Rest
 * @author     Rank Math <support@rankmath.com>
 */

namespace RankMath\Analytics;

use WP_Error;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Controller;
use RankMath\Helper;
use RankMath\Google\Api;
use RankMath\SEO_Analysis\SEO_Analyzer;

defined( 'ABSPATH' ) || exit;

/**
 * Rest class.
 */
class Rest extends WP_REST_Controller {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace = \RankMath\Rest\Rest_Helper::BASE . '/an';
	}

	/**
	 * Registers the routes for the objects of the controller.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/dashboard',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_dashboard' ],
				'permission_callback' => [ $this, 'has_permission' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/keywordsOverview',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_keywords_overview' ],
				'permission_callback' => [ $this, 'has_permission' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/postsSummary',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ Stats::get(), 'get_posts_summary' ],
				'permission_callback' => [ $this, 'has_permission' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/postsRowsByObjects',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ Stats::get(), 'get_posts_rows_by_objects' ],
				'permission_callback' => [ $this, 'has_permission' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/post/(?P<id>\d+)',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_post' ],
				'permission_callback' => [ $this, 'has_permission' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/keywordsSummary',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ Stats::get(), 'get_keywords_summary' ],
				'permission_callback' => [ $this, 'has_permission' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/analyticsSummary',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_analytics_summary' ],
				'permission_callback' => [ $this, 'has_permission' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/keywordsRows',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ Stats::get(), 'get_keywords_rows' ],
				'permission_callback' => [ $this, 'has_permission' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/userPreferences',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'update_user_preferences' ],
				'permission_callback' => [ $this, 'has_permission' ],
			]
		);
	}

	/**
	 * Determines if the current user can manage analytics.
	 *
	 * @return true
	 */
	public function has_permission() {
		return current_user_can( 'rank_math_analytics' );
	}

	/**
	 * Add track keyword to DB.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function update_user_preferences( WP_REST_Request $request ) {
		$pref = $request->get_param( 'preferences' );
		if ( empty( $pref ) ) {
			return new WP_Error(
				'param_value_empty',
				esc_html__( 'Sorry, no preference found.', 'rank-math' )
			);
		}

		update_user_meta(
			get_current_user_id(),
			'rank_math_analytics_table_columns',
			$pref
		);

		return true;
	}

	/**
	 * Get dashboard.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_post( WP_REST_Request $request ) {
		$id = $request->get_param( 'id' );
		if ( empty( $id ) ) {
			return new WP_Error(
				'param_value_empty',
				esc_html__( 'Sorry, no post id found.', 'rank-math' )
			);
		}

		return rest_ensure_response( Stats::get()->get_post( $id ) );
	}

	/**
	 * Get dashboard.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_dashboard( WP_REST_Request $request ) { // phpcs:ignore
		return rest_ensure_response(
			[
				'stats'        => Stats::get()->get_analytics_summary(),
				'optimization' => Stats::get()->get_optimization_summary(),
			]
		);
	}

	/**
	 * Get dashboard.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_analytics_summary( WP_REST_Request $request ) { // phpcs:ignore
		return rest_ensure_response(
			[
				'summary'      => Stats::get()->get_posts_summary(),
				'optimization' => Stats::get()->get_optimization_summary(),
			]
		);
	}

	/**
	 * Get keywords overview.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_keywords_overview( WP_REST_Request $request ) { // phpcs:ignore
		return rest_ensure_response(
			apply_filters(
				'rank_math/analytics/keywords_overview',
				[
					'topKeywords'   => Stats::get()->get_top_keywords(),
					'positionGraph' => Stats::get()->get_top_position_graph(),
				]
			)
		);
	}

	/**
	 * Should update pagespeed record.
	 *
	 * @param  int $id      Database row id.
	 * @return bool
	 */
	private function should_update_pagespeed( $id ) {
		$record = DB::objects()->where( 'id', $id )->one();

		return \time() > ( \strtotime( $record->pagespeed_refreshed ) + ( DAY_IN_SECONDS * 7 ) );
	}
}
