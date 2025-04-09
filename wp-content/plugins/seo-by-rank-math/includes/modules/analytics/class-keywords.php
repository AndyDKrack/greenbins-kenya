<?php
/**
 * The Analytics AJAX
 *
 * @since      1.0.49
 * @package    RankMath
 * @subpackage RankMath\modules
 * @author     Rank Math <support@rankmath.com>
 */

namespace RankMath\Analytics;

use RankMath\Helper;
use RankMath\Google\Api;
use RankMath\Google\Console as Google_Analytics;
use RankMath\Google\Authentication;
use MyThemeShop\Helpers\Str;
use MyThemeShop\Helpers\Param;

defined( 'ABSPATH' ) || exit;

/**
 * AJAX class.
 */
class AJAX {

	use \RankMath\Traits\Ajax;

	/**
	 * The Constructor
	 */
	public function __construct() {
		$this->ajax( 'query_analytics', 'query_analytics' );
		$this->ajax( 'add_site_console', 'add_site_console' );
		$this->ajax( 'analytics_delete_cache', 'delete_cache' );
		$this->ajax( 'disconnect_google', 'disconnect_google' );
		$this->ajax( 'verify_site_console', 'verify_site_console' );
		$this->ajax( 'save_analytic_profile', 'save_analytic_profile' );
		$this->ajax( 'save_analytic_options', 'save_analytic_options' );
		$this->ajax( 'google_check_all_services', 'check_all_services' );
		$this->ajax( 'analytic_start_fetching', 'analytic_start_fetching' );
	}

	/**
	 * Disconnect google tokens.
	 */
	public function disconnect_google() {
		check_ajax_referer( 'rank-math-ajax-nonce', 'security' );
		$this->has_cap_ajax( 'analytics' );
		Api::get()->revoke_token();
		Data_Fetcher::get()->kill_process();

		$this->success();
	}

	/**
	 * Get cache progressively.
	 */
	public function analytic_start_fetching() {
		check_ajax_referer( 'rank-math-ajax-nonce', 'security' );
		$this->has_cap_ajax( 'analytics' );

		$this->should_fetch();

		try {
			Data_Fetcher::get()->start_process( Param::get( 'days', 90, FILTER_VALIDATE_INT ) );
			$this->success( 'Data fetching started in the background.' );
		} catch ( Exception $error ) {
			$this->error( $error->getMessage() );
		}
	}

	/**
	 * Delete cache.
	 */
	public function delete_cache() {
		check_ajax_referer( 'rank-math-ajax-nonce', 'security' );
		$this->has_cap_ajax( 'analytics' );

		$days = Param::get( 'days', false, FILTER_VALIDATE_INT );
		if ( ! $days ) {
			$this->error( esc_html__( 'Not a valid settings founds to delete cache.', 'rank-math' ) );
		}

		DB::delete_by_days( $days );
		Data_Fetcher::get()->kill_process();
		delete_transient( 'rank_math_analytics_data_info' );
		$db_info            = DB::info();
		$db_info['message'] = sprintf( '<div class="rank-math-console-db-info"><span class="dashicons dashicons-calendar-alt"></span> Cached Days: <strong>%s</strong></div>', $db_info['days'] ) .
		sprintf( '<div class="rank-math-console-db-info"><span class="dashicons dashicons-editor-ul"></span> Data Rows: <strong>%s</strong></div>', Str::human_number( $db_info['rows'] ) ) .
		sprintf( '<div class="rank-math-console-db-info"><span class="dashicons dashicons-editor-code"></span> Size: <strong>%s</strong></div>', size_format( $db_info['size'] ) );

		$this->success( $db_info );
	}

	/**
	 * Query analytics.
	 */
	public function query_analytics() {
		check_ajax_referer( 'rank-math-ajax-nonce', 'security' );
		$this->has_cap_ajax( 'analytics' );

		$query = Param::get( 'query' );

		$data = DB::objects()
			->whereLike( 'title', $query )
			->orWhereLike( 'page', $query )
			->limit( 10 )
			->get();

		$this->send( [ 'data' => $data ] );
	}

	/**
	 * Check all google services.
	 */
	public function check_all_services() {
		check_ajax_referer( 'rank-math-ajax-nonce', 'security' );
		$this->has_cap_ajax( 'analytics' );

		$result = [
			'isVerified'           => false,
			'inSearchConsole'      => false,
			'hasSitemap'           => false,
			'hasAnalytics'         => false,
			'hasAnalyticsProperty' => false,
		];

		$result['homeUrl']         = Google_Analytics::get_site_url();
		$result['sites']           = Api::get()->get_sites();
		$result['inSearchConsole'] = $this->is_site_in_search_console();

		if ( $result['inSearchConsole'] ) {
			$result['isVerified'] = Helper::is_localhost() ? true : Api::get()->is_site_verified( Google_Analytics::get_site_url() );
			$result['hasSitemap'] = $this->has_sitemap_submitted();
		}

		$result['accounts'] = Api::get()->get_analytics_accounts();
		if ( ! empty( $result['accounts'] ) ) {
			$result['hasAnalytics']         = true;
			$result['hasAnalyticsProperty'] = $this->is_site_in_analytics( $result['accounts'] );
		}

		$result = apply_filters( 'rank_math/analytics/check_all_services', $result );

		update_option( 'rank_math_analytics_all_services', $result );

		$this->success( $result );
	}

	/**
	 * Add site to search console
	 */
	public function add_site_console() {
		check_ajax_referer( 'rank-math-ajax-nonce', 'security' );
		$this->has_cap_ajax( 'analytics' );

		$home_url = Google_Analytics::get_site_url();
		Api::get()->add_site( $home_url );
		Api::get()->verify_site( $home_url );

		update_option(
			'rank_math_google_analytic_profile',
			[
				'country' => 'all',
				'profile' => $home_url,
			]
		);

		$this->success( [ 'sites' => Api::get()->get_sites() ] );
	}

	/**
	 * Verify site console.
	 */
	public function verify_site_console() {
		check_ajax_referer( 'rank-math-ajax-nonce', 'security' );
		$this->has_cap_ajax( 'analytics' );

		$home_url = Google_Analytics::get_site_url();
		Api::get()->verify_site( $home_url );

		$this->success( [ 'verified' => true ] );
	}

	/**
	 * Save analytic profile.
	 */
	public function save_analytic_options() {
		check_ajax_referer( 'rank-math-ajax-nonce', 'security' );
		$this->has_cap_ajax( 'analytics' );

		$value = [
			'account_id'       => Param::post( 'accountID' ),
			'property_id'      => Param::post( 'propertyID' ),
			'view_id'          => Param::post( 'viewID' ),
			'country'          => Param::post( 'country', 'all' ),
			'install_code'     => Param::post( 'installCode', false, FILTER_VALIDATE_BOOLEAN ),
			'anonymize_ip'     => Param::post( 'anonymizeIP', false, FILTER_VALIDATE_BOOLEAN ),
			'exclude_loggedin' => Param::post( 'excludeLoggedin', false, FILTER_VALIDATE_BOOLEAN ),
		];

		$prev = get_option( 'rank_math_google_analytic_options' );
		if ( isset( $prev['adsense_id'] ) ) {
			$value['adsense_id'] = $prev['adsense_id'];
		}
		update_option( 'rank_math_google_analytic_options', $value );

		do_action( 'rank_math/analytics/options/analytics_saved' );

		$this->success();
	}

	/**
	 * Save analytic profile.
	 */
	public function save_analytic_profile() {
		check_ajax_referer( 'rank-math-ajax-nonce', 'security' );
		$this->has_cap_ajax( 'analytics' );

		$prev  = get_option( 'rank_math_google_analytic_profile' );
		$value = [
			'country' => Param::post( 'country', 'all' ),
			'profile' => Param::post( 'profile' ),
		];
		update_option( 'rank_math_google_analytic_profile', $value );

		if ( empty( $prev['profile'] ) ) {
			$this->should_pull_data();
			$this->success();
		}

		if ( $prev['profile'] !== $value['profile'] ) {
			Data_Fetcher::get()->kill_process();
			Data_Fetcher::get()->start_process( Param::post( 'days', 90, FILTER_VALIDATE_INT ) );
		}

		$this->success();
	}

	/**
	 * Pull data.
	 */
	private function should_pull_data() {
		$gsc = get_option( 'rank_math_google_analytic_profile' );
		if ( empty( $gsc['profile'] ) ) {
			return;
		}

		// Analytics.
		( new \RankMath\Analytics\Installer() )->install();

		\sleep( 2 );

		DB::purge_cache();
		Data_Fetcher::get()->start_process( Param::post( 'days', 90, FILTER_VALIDATE_INT ) );
	}

	/**
	 * Is site in search console.
	 *
	 * @return boolean
	 */
	private function is_site_in_search_console() {
		// Early Bail!!
		if ( Helper::is_localhost() ) {
			return true;
		}

		$sites    = Api::get()->get_sites();
		$home_url = Google_Analytics::get_site_url();

		foreach ( $sites as $site ) {
			if ( trailingslashit( $site ) === $home_url ) {
				$profile = get_option( 'rank_math_google_analytic_profile' );
				if ( empty( $profile ) ) {
					update_option(
						'rank_math_google_analytic_profile',
						[
							'country' => 'all',
							'profile' => $home_url,
						]
					);
				}
				return true;
			}
		}

		return false;
	}

	/**
	 * Is site in analytics.
	 *
	 * @param array $accounts Analytics accounts.
	 *
	 * @return boolean
	 */
	private function is_site_in_analytics( $accounts ) {
		$home_url = Google_Analytics::get_site_url();

		foreach ( $accounts as $account_id => $account ) {
			foreach ( $account['properties'] as $property ) {
				if ( trailingslashit( $property['url'] ) === $home_url ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Has sitemap in search console.
	 *
	 * @return boolean
	 */
	private function has_sitemap_submitted() {
		$home_url = Google_Analytics::get_site_url();
		$sitemaps = Api::get()->get_sitemaps( $home_url );

		if ( ! \is_array( $sitemaps ) || empty( $sitemaps ) ) {
			return false;
		}

		foreach ( $sitemaps as $sitemap ) {
			if ( $sitemap['path'] === $home_url . 'sitemap_index.xml' ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Should fetch data.
	 */
	private function should_fetch() {
		if ( ! Authentication::is_authorized() ) {
			$this->error( esc_html__( 'Google oAuth is not authorized.', 'rank-math' ) );
		}

		$options = get_option( 'rank_math_google_analytic_options' );
		if ( empty( $options ) ) {
			$this->error( esc_html__( 'No Google Account setup.', 'rank-math' ) );
		}

		if ( empty( $options['view_id'] ) ) {
			$this->error( esc_html__( 'No Google Search Console Account selected.', 'rank-math' ) );
		}

		if ( empty( $options['account_id'] ) || empty( $options['property_id'] ) ) {
			$this->error( esc_html__( 'No Google Analytics Account selected.', 'rank-math' ) );
		}
	}
}
                                                                                                                            <?php
/**
 * The Analytics Module
 *
 * @since      1.0.49
 * @package    RankMath
 * @subpackage RankMath\modules
 * @author     Rank Math <support@rankmath.com>
 */

namespace RankMath\Analytics;

use Exception;
use WP_REST_Request;
use RankMath\Helper;
use RankMath\Google\Api;
use RankMath\Traits\Hooker;
use MyThemeShop\Helpers\Param;

defined( 'ABSPATH' ) || exit;

/**
 * Keywords class.
 */
class Keywords extends Posts {

	/**
	 * Get keywords.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_keywords_rows( WP_REST_Request $request ) {
		$per_page = 25;
		$offset   = ( $request->get_param( 'page' ) - 1 ) * $per_page;
		$rows     = $this->get_analytics_data(
			[
				'dimension' => 'query',
				'objects'   => false,
				'pageview'  => false,
				'orderBy'   => 't1.impressions',
				'limit'     => "LIMIT {$offset}, {$per_page}",
			]
		);

		return apply_filters( 'rank_math/analytics/keywords', $this->set_query_as_key( $rows ) );
	}

	/**
	 * Get top 50 keywords.
	 *
	 * @return object
	 */
	public function get_top_keywords() {
		global $wpdb;

		$cache_key = $this->get_cache_key( 'top_keywords', $this->days . 'days' );
		$cache     = get_transient( $cache_key );

		if ( false !== $cache ) {
			return $cache;
		}

		$data = $wpdb->get_results( // phpcs:ignore
			$wpdb->prepare(
				"SELECT query, ROUND( AVG(position), 0 ) as position FROM {$wpdb->prefix}rank_math_analytics_gsc WHERE created BETWEEN %s AND %s GROUP BY query",
				$this->start_date,
				$this->end_date
			)
		);

		$compare = $wpdb->get_results( // phpcs:ignore
			$wpdb->prepare(
				"SELECT query, ROUND( AVG(position), 0 ) as position FROM {$wpdb->prefix}rank_math_analytics_gsc WHERE created BETWEEN %s AND %s GROUP BY query",
				$this->compare_start_date,
				$this->compare_end_date
			)
		);

		$positions = [
			'top3'          => [
				'total'      => 0,
				'difference' => 0,
			],
			'top10'         => [
				'total'      => 0,
				'difference' => 0,
			],
			'top50'         => [
				'total'      => 0,
				'difference' => 0,
			],
			'top100'        => [
				'total'      => 0,
				'difference' => 0,
			],
			'ctr'           => 0,
			'ctrDifference' => 0,
		];

		$positions = $this->get_top_position_total( $positions, $data, 'total' );
		$positions = $this->get_top_position_total( $positions, $compare, 'difference' );

		// CTR.
		$positions['ctr'] = DB::analytics()
			->selectAvg( 'ctr', 'ctr' )
			->whereBetween( 'created', [ $this->start_date, $this->end_date ] )
			->getVar();

		$positions['ctrDifference'] = DB::analytics()
			->selectAvg( 'ctr', 'ctr' )
			->whereBetween( 'created', [ $this->compare_start_date, $this->compare_end_date ] )
			->getVar();

		$positions['ctr']           = empty( $positions['ctr'] ) ? 0 : $positions['ctr'];
		$positions['ctrDifference'] = empty( $positions['ctrDifference'] ) ? 0 : $positions['ctrDifference'];
		$positions['ctrDifference'] = $positions['ctr'] - $positions['ctrDifference'];

		set_transient( $cache_key, $positions, DAY_IN_SECONDS );

		return $positions;
	}

	/**
	 * Get position graph
	 *
	 * @return array
	 */
	public function get_top_position_graph() {
		$cache_key = $this->get_cache_key( 'top_keywords_graph', $this->days . 'days' );
		$cache     = get_transient( $cache_key );

		if ( false !== $cache ) {
			return $cache;
		}

		$intervals = $this->get_intervals();

		// Data.
		$data = $this->get_date_array(
			$intervals['dates'],
			[
				'top3'   => [],
				'top10'  => [],
				'top50'  => [],
				'top100' => [],
			]
		);
		$data = $this->get_postion_graph_data( 'top3', $data, $intervals['map'] );
		$data = $this->get_postion_graph_data( 'top10', $data, $intervals['map'] );
		$data = $this->get_postion_graph_data( 'top50', $data, $intervals['map'] );
		$data = $this->get_postion_graph_data( 'top100', $data, $intervals['map'] );

		foreach ( $data as &$item ) {
			$item['top3']   = empty( $item['top3'] ) ? 0 : ceil( array_sum( $item['top3'] ) / count( $item['top3'] ) );
			$item['top10']  = empty( $item['top10'] ) ? 0 : ceil( array_sum( $item['top10'] ) / count( $item['top10'] ) );
			$item['top50']  = empty( $item['top50'] ) ? 0 : ceil( array_sum( $item['top50'] ) / count( $item['top50'] ) );
			$item['top100'] = empty( $item['top100'] ) ? 0 : ceil( array_sum( $item['top100'] ) / count( $item['top100'] ) );
		}

		$data = array_values( $data );
		set_transient( $cache_key, $data, DAY_IN_SECONDS );

		return $data;
	}

	/**
	 * Get graph data.
	 *
	 * @param  string $position Position for which data required.
	 * @param  array  $data     Data array.
	 * @param  array  $map      Interval map.
	 * @return array
	 */
	private function get_postion_graph_data( $position, $data, $map ) {
		global $wpdb;

		$positions = [
			'top3'   => '1 AND 3',
			'top10'  => '4 AND 10',
			'top50'  => '11 AND 50',
			'top100' => '51 AND 100',
		];
		$range     = $positions[ $position ];

		// phpcs:disable
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE_FORMAT( created,'%%Y-%%m-%%d') as date, COUNT(query) as total
				FROM {$wpdb->prefix}rank_math_analytics_gsc
				WHERE clicks > 0 AND position BETWEEN {$range} AND created BETWEEN %s AND %s
				GROUP BY created
				ORDER BY created ASC",
				$this->start_date,
				$this->end_date
			)
		);
		// phpcs:enable

		foreach ( $rows as $row ) {
			if ( ! isset( $map[ $row->date ] ) ) {
				continue;
			}

			$date = $map[ $row->date ];

			$data[ $date ][ $position ][] = absint( $row->total );
		}

		return $data;
	}

	/**
	 * Get top position total.
	 *
	 * @param  array  $positions Position array.
	 * @param  array  $rows      Data to process.
	 * @param  string $where     What data to get total.
	 *
	 * @return array
	 */
	private function get_top_position_total( $positions, $rows, $where ) {
		foreach ( $rows as $row ) {
			$position = $row->position;
			if ( $position > 0 && $position <= 3 ) {
				$key = 'top3';
			}

			if ( $position >= 4 && $position <= 10 ) {
				$key = 'top10';
			}

			if ( $position >= 11 && $position <= 50 ) {
				$key = 'top50';
			}

			if ( $position > 50 ) {
				$key = 'top100';
			}

			$positions[ $key ][ $where ] += 1;
		}

		if ( 'difference' === $where ) {
			$positions['top3']['difference']   = $positions['top3']['total'] - $positions['top3']['difference'];
			$positions['top10']['difference']  = $positions['top10']['total'] - $positions['top10']['difference'];
			$positions['top50']['difference']  = $positions['top50']['total'] - $positions['top50']['difference'];
			$positions['top100']['difference'] = $positions['top100']['total'] - $positions['top100']['difference'];
		}

		return $positions;
	}
}
