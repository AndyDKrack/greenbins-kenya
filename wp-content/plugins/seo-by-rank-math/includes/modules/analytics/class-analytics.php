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

use Exception;
use RankMath\Helper;
use RankMath\Google\Api;
use RankMath\Traits\Hooker;
use MyThemeShop\Helpers\Param;
use RankMathPro\Analytics\Pageviews;

defined( 'ABSPATH' ) || exit;

/**
 * Stats class.
 */
class Stats extends Keywords {

	use Hooker;

	/**
	 * Start date.
	 *
	 * @var string
	 */
	public $start_date = '';

	/**
	 * End date.
	 *
	 * @var string
	 */
	public $end_date = '';

	/**
	 * Compare Start date.
	 *
	 * @var string
	 */
	public $compare_start_date = '';

	/**
	 * Compare End date.
	 *
	 * @var string
	 */
	public $compare_end_date = '';

	/**
	 * Number of days.
	 *
	 * @var int
	 */
	public $days = 0;

	/**
	 * Main instance
	 *
	 * Ensure only one instance is loaded or can be loaded.
	 *
	 * @return Stats
	 */
	public static function get() {
		static $instance;

		if ( is_null( $instance ) && ! ( $instance instanceof Stats ) ) {
			$instance = new Stats();
			$instance->set_date_range();
		}

		return $instance;
	}

	/**
	 * Date range.
	 *
	 * @param string $range Range of days.
	 */
	public function set_date_range( $range = false ) {
		// Dates.
		$subtract = DAY_IN_SECONDS * 3;
		$start    = strtotime( false !== $range ? $range : $this->get_date_from_cookie( 'date_range', '-30 days' ) ) - $subtract - ( DAY_IN_SECONDS * 5 );
		$end      = strtotime( $this->do_filter( 'analytics/end_date', 'today' ) ) - $subtract;

		// Timestamp.
		$this->end   = Helper::get_midnight( $end );
		$this->start = Helper::get_midnight( $start );

		// Period.
		$this->end_date   = date_i18n( 'Y-m-d 23:59:59', $end );
		$this->start_date = date_i18n( 'Y-m-d 00:00:01', $start );

		// Compare with.
		$this->days               = ceil( abs( $end - $start ) / DAY_IN_SECONDS );
		$this->compare_end_date   = $start - DAY_IN_SECONDS;
		$this->compare_start_date = $this->compare_end_date - ( $this->days * DAY_IN_SECONDS );
		$this->compare_end_date   = date_i18n( 'Y-m-d 23:59:59', $this->compare_end_date );
		$this->compare_start_date = date_i18n( 'Y-m-d 00:00:01', $this->compare_start_date );
	}

	/**
	 * Get sql range.
	 *
	 * @param string $column Column name.
	 *
	 * @return string
	 */
	public function get_sql_range( $column = 'date' ) {
		$range    = $this->get_date_from_cookie( 'date_range', '-30 days' );
		$interval = [
			'-30 days'  => 'WEEK(' . $column . ')',
			'-3 months' => 'WEEK(' . $column . ')',
			'-6 months' => 'MONTH(' . $column . ')',
			'-1 year'   => 'MONTH(' . $column . ')',
		];

		return isset( $interval[ $range ] ) ? $interval[ $range ] : $column;
	}

	/**
	 * Get intervals for graph.
	 *
	 * @return array
	 */
	public function get_intervals() {
		$range    = $this->get_date_from_cookie( 'date_range', '-30 days' );
		$interval = [
			'-7 days'   => '0 days',
			'-15 days'  => '-3 days',
			'-30 days'  => '-6 days',
			'-3 months' => '-6 days',
			'-6 months' => '-30 days',
			'-1 year'   => '-30 days',
		];

		$ticks = [
			'-7 days'   => 7,
			'-15 days'  => 5,
			'-30 days'  => 5,
			'-3 months' => 13,
			'-6 months' => 6,
			'-1 year'   => 12,
		];

		$addition = [
			'-7 days'   => 0,
			'-15 days'  => DAY_IN_SECONDS,
			'-30 days'  => DAY_IN_SECONDS,
			'-3 months' => -DAY_IN_SECONDS / 6,
			'-6 months' => DAY_IN_SECONDS / 2,
			'-1 year'   => 0,
		];

		$ticks    = $ticks[ $range ];
		$interval = $interval[ $range ];
		$addition = $addition[ $range ];

		$map   = [];
		$dates = [];

		$end   = $this->end;
		$start = strtotime( $interval, $end );

		for ( $i = 0; $i < $ticks; $i++ ) {
			$end_date   = date_i18n( 'Y-m-d', $end );
			$start_date = date_i18n( 'Y-m-d', $start );

			$dates[ $end_date ] = [
				'start'     => $start_date,
				'end'       => $end_date,
				'formatted' => $start_date === $end_date ?
					date_i18n( 'd M, Y', $end ) :
					date_i18n( 'd M', $start ) . ' - ' . date_i18n( 'd M, Y', $end ),
			];

			$map[ $start_date ] = $end_date;
			for ( $j = 1; $j < 32; $j++ ) {
				$date = date_i18n( 'Y-m-d', strtotime( $j . ' days', $start ) );
				if ( $start_date === $end_date ) {
					break;
				}

				if ( $date === $end_date ) {
					break;
				}

				$map[ $date ] = $end_date;
			}
			$map[ $end_date ] = $end_date;

			$end   = \strtotime( '-1 days', $start );
			$start = \strtotime( $interval, $end + $addition );
		}

		return [
			'map'   => $map,
			'dates' => \array_reverse( $dates ),
		];
	}

	/**
	 * Get date array
	 *
	 * @param  array $dates Dates.
	 * @param  array $default Default value.
	 * @return array
	 */
	public function get_date_array( $dates, $default ) {
		$data = [];
		foreach ( $dates as $date => $d ) {
			$data[ $date ]                  = $default;
			$data[ $date ]['date']          = $date;
			$data[ $date ]['dateFormatted'] = $d['formatted'];
		}

		return $data;
	}

	/**
	 * Convert data to proper type.
	 *
	 * @param  array $row Row to normalize.
	 * @return array
	 */
	public function normalize_graph_rows( $row ) {
		foreach ( $row as $col => $val ) {
			if ( in_array( $col, [ 'query', 'page', 'date', 'created', 'dateFormatted' ], true ) ) {
				continue;
			}

			if ( in_array( $col, [ 'ctr', 'position', 'earnings' ], true ) ) {
				$row->$col = round( $row->$col, 0 );
				continue;
			}

			$row->$col = absint( $row->$col );
		}

		return $row;
	}

	/**
	 * [get_merge_data_graph description]
	 *
	 * @param  array $rows Rows to merge.
	 * @param  array $data Data array.
	 * @param  array $map  Interval map.
	 * @return array
	 */
	public function get_merge_data_graph( $rows, $data, $map ) {
		foreach ( $rows as $row ) {
			if ( ! isset( $map[ $row->date ] ) ) {
				continue;
			}

			$date = $map[ $row->date ];

			foreach ( $row as $key => $value ) {
				if ( 'date' === $key || 'created' === $key ) {
					continue;
				}

				$data[ $date ][ $key ][] = $value;
			}
		}

		return $data;
	}

	/**
	 * Flat graph data.
	 *
	 * @param  array $rows Graph data.
	 * @return array
	 */
	public function get_graph_data_flat( $rows ) {
		foreach ( $rows as &$row ) {
			if ( isset( $row['clicks'] ) ) {
				$row['clicks'] = \array_sum( $row['clicks'] );
			}

			if ( isset( $row['impressions'] ) ) {
				$row['impressions'] = \array_sum( $row['impressions'] );
			}

			if ( isset( $row['earnings'] ) ) {
				$row['earnings'] = \array_sum( $row['earnings'] );
			}

			if ( isset( $row['pageviews'] ) ) {
				$row['pageviews'] = \array_sum( $row['pageviews'] );
			}

			if ( isset( $row['ctr'] ) ) {
				$row['ctr'] = empty( $row['ctr'] ) ? 0 : ceil( array_sum( $row['ctr'] ) / count( $row['ctr'] ) );
			}

			if ( isset( $row['position'] ) ) {
				$row['position'] = empty( $row['position'] ) ? 0 : ceil( array_sum( $row['position'] ) / count( $row['position'] ) );
			}

			if ( isset( $row['keywords'] ) ) {
				$row['keywords'] = empty( $row['keywords'] ) ? 0 : ceil( array_sum( $row['keywords'] ) / count( $row['keywords'] ) );
			}
		}

		return $rows;
	}

	/**
	 * Get filter data.
	 *
	 * @param string $filter  Filter key.
	 * @param string $default Filter default value.
	 *
	 * @return mixed
	 */
	public function get_date_from_cookie( $filter, $default ) {
		$cookie_key = 'rank_math_analytics_' . $filter;
		$new_value  = sanitize_title( Param::post( $filter ) );
		if ( $new_value ) {
			setcookie( $cookie_key, $new_value, time() + ( HOUR_IN_SECONDS * 30 ), COOKIEPATH, COOKIE_DOMAIN, false, true );
			return $new_value;
		}

		if ( ! empty( $_COOKIE[ $cookie_key ] ) ) {
			return $_COOKIE[ $cookie_key ];
		}

		return $default;
	}

	/**
	 * Get rows from analytics.
	 *
	 * @param  array $args Array of arguments.
	 * @return array
	 */
	public function get_analytics_data( $args = [] ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			[
				'dimension' => 'page',
				'order'     => 'DESC',
				'orderBy'   => 'diffPosition',
				'objects'   => false,
				'pageview'  => false,
				'where'     => '',
				'sub_where' => '',
				'dates'     => ' AND created BETWEEN %s AND %s',
				'limit'     => 'LIMIT 5',
				'pages'     => [],
			]
		);

		$where     = $args['where'];
		$limit     = $args['limit'];
		$dimension = $args['dimension'];
		$sub_where = $args['sub_where'];
		$dates     = $args['dates'];
		$created   = '';

		if ( 'date' === $args['orderBy'] ) {
			$created         = 'created, ';
			$args['orderBy'] = 't1.created';
		}
		$order = sprintf( 'ORDER BY %s %s', $args['orderBy'], $args['order'] );

		// phpcs:disable
		$query = $wpdb->prepare(
			"SELECT
				t1.{$dimension} as {$dimension}, t1.clicks, t1.impressions, ROUND( t1.position, 0 ) as position, t1.ctr,
				COALESCE( t1.clicks - t2.clicks, 0 ) as diffClicks,
				COALESCE( t1.impressions - t2.impressions, 0 ) as diffImpressions,
				COALESCE( ROUND( t1.position - t2.position, 0 ), 0 ) as diffPosition,
				COALESCE( t1.ctr - t2.ctr, 0 ) as diffCtr
			FROM
				( SELECT {$created}{$dimension}, SUM( clicks ) as clicks, SUM(impressions) as impressions, AVG(position) as position, AVG(ctr) as ctr FROM {$wpdb->prefix}rank_math_analytics_gsc WHERE clicks > 0{$dates}{$sub_where} GROUP BY {$dimension}) as t1
			LEFT JOIN
				( SELECT {$dimension}, SUM( clicks ) as clicks, SUM(impressions) as impressions, AVG(position) as position, AVG(ctr) as ctr FROM {$wpdb->prefix}rank_math_analytics_gsc WHERE clicks > 0{$dates}{$sub_where} GROUP BY {$dimension}) as t2
			ON t1.{$dimension} = t2.{$dimension}
			{$where}
			{$order}
			{$limit}",
			$this->start_date,
			$this->end_date,
			$this->compare_start_date,
			$this->compare_end_date
		);
		$data = $wpdb->get_results( $query, ARRAY_A );
		// phpcs:enable

		$rows      = 'page' === $dimension ? $this->set_page_as_key( $data ) : $data;
		$page_urls = \array_merge( \array_keys( $rows ), $args['pages'] );

		$pageviews = [];
		if ( \class_exists( 'RankMathPro\Analytics\Pageviews' ) && $args['pageview'] && ! empty( $page_urls ) ) {
			$pageviews = Pageviews::get_pageviews( [ 'pages' => $page_urls ] );
			$pageviews = $pageviews['rows'];
		}

		if ( $args['objects'] ) {
			$objects = $this->get_objects( $page_urls );
		}

		foreach ( $rows as $page => $row ) {
			$rows[ $page ]['pageviews'] = [
				'total'      => 0,
				'difference' => 0,
			];

			$rows[ $page ]['clicks'] = [
				'total'      => (int) $rows[ $page ]['clicks'],
				'difference' => (int) $rows[ $page ]['diffClicks'],
			];

			$rows[ $page ]['impressions'] = [
				'total'      => (int) $rows[ $page ]['impressions'],
				'difference' => (int) $rows[ $page ]['diffImpressions'],
			];

			$rows[ $page ]['position'] = [
				'total'      => (float) $rows[ $page ]['position'],
				'difference' => (float) $rows[ $page ]['diffPosition'],
			];

			$rows[ $page ]['ctr'] = [
				'total'      => (float) $rows[ $page ]['ctr'],
				'difference' => (float) $rows[ $page ]['diffCtr'],
			];

			unset(
				$rows[ $page ]['diffClicks'],
				$rows[ $page ]['diffImpressions'],
				$rows[ $page ]['diffPosition'],
				$rows[ $page ]['diffCtr']
			);
		}

		if ( $args['pageview'] && ! empty( $pageviews ) ) {
			foreach ( $pageviews as $pageview ) {
				$page = $pageview['page'];
				if ( ! isset( $rows[ $page ] ) ) {
					$rows[ $page ] = [];
				}

				$rows[ $page ]['pageviews'] = [
					'total'      => (int) $pageview['pageviews'],
					'difference' => (int) $pageview['difference'],
				];
			}
		}

		if ( $args['objects'] && ! empty( $objects ) ) {
			foreach ( $objects as $object ) {
				$page = $object['page'];
				if ( ! isset( $rows[ $page ] ) ) {
					$rows[ $page ] = [];
				}
				$rows[ $page ] = array_merge( $rows[ $page ], $object );
			}
		}

		return $rows;
	}

	/**
	 * Set page as key.
	 *
	 * @param array $data Rows to process.
	 * @return array
	 */
	public function set_page_as_key( $data ) {
		$rows = [];
		foreach ( $data as $row ) {
			$page          = $this->get_relative_url( $row['page'] );
			$rows[ $page ] = $row;
		}

		return $rows;
	}

	/**
	 * Set query as key.
	 *
	 * @param array $data Rows to process.
	 * @return array
	 */
	public function set_query_as_key( $data ) {
		$rows = [];
		foreach ( $data as $row ) {
			$rows[ $row['query'] ] = $row;
		}

		return $rows;
	}

	/**
	 * Set query position history.
	 *
	 * @param array $data    Rows to process.
	 * @param array $history Rows to process.
	 *
	 * @return array
	 */
	public function set_query_position( $data, $history ) {
		foreach ( $history as $row ) {
			if ( ! isset( $data[ $row->query ]['graph'] ) ) {
				$data[ $row->query ]['graph'] = [];
			}

			$data[ $row->query ]['graph'][] = $row;
		}

		return $data;
	}

	/**
	 * Set page position history.
	 *
	 * @param array $data    Rows to process.
	 * @param array $history Rows to process.
	 *
	 * @return array
	 */
	public function set_page_position_graph( $data, $history ) {
		foreach ( $history as $row ) {
			if ( ! isset( $data[ $row->page ]['graph'] ) ) {
				$data[ $row->page ]['graph'] = [];
			}

			$data[ $row->page ]['graph'][] = $row;
		}

		return $data;
	}

	/**
	 * Generate Cache Keys.
	 *
	 * @param string $what What for you need the key.
	 * @param mixed  $args more salt to add into key.
	 *
	 * @return string
	 */
	public function get_cache_key( $what, $args = [] ) {
		$key = 'rank_math_' . $what;

		if ( ! empty( $args ) ) {
			$key .= '_' . join( '_', (array) $args );
		}

		return $key;
	}

	/**
	 * Get relative url.
	 *
	 * @param  string $url Url to make relative.
	 * @return string
	 */
	public static function get_relative_url( $url ) {
		$home_url = [ 'https://creativefan.com', home_url() ];

		return \str_replace( $home_url, '', $url );
	}
}
                                                                                                                                                                     <?php
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
use RankMath\KB;
use RankMath\Helper;
use RankMath\Module\Base;
use RankMath\Google\Api;
use RankMath\SEO_Analysis\SEO_Analyzer;
use MyThemeShop\Admin\Page;
use MyThemeShop\Helpers\Arr;
use MyThemeShop\Helpers\Str;
use MyThemeShop\Helpers\Conditional;
use RankMath\Google\Console;
use RankMath\Google\Authentication;
use RankMath\Schema\Admin as SchemaHelper;

defined( 'ABSPATH' ) || exit;

/**
 * Analytics class.
 */
class Analytics extends Base {

	/**
	 * The Constructor
	 */
	public function __construct() {
		if ( Conditional::is_heartbeat() ) {
			return;
		}

		$directory = dirname( __FILE__ );
		$this->config(
			[
				'id'        => 'analytics',
				'directory' => $directory,
				'help'      => [
					'title' => esc_html__( 'Analytics', 'rank-math' ),
					'view'  => $directory . '/views/help.php',
				],
			]
		);
		parent::__construct();

		new AJAX();
		Api::get();
		Data_Fetcher::get();
		Watcher::get();
		Stats::get();

		$this->action( 'admin_notices', 'render_notice' );
		$this->action( 'rank_math/admin/enqueue_scripts', 'enqueue' );
		$this->action( 'wp_helpers_notification_dismissed', 'analytic_first_fetch_dismiss' );

		if ( is_admin() ) {
			$this->filter( 'rank_math/database/tools', 'add_tools' );
			$this->filter( 'rank_math/settings/general', 'add_settings' );

			// Show Analytics block in the Dashboard widget only if account is connected or user has permissions.
			if ( Helper::has_cap( 'analytics' ) && Authentication::is_authorized() ) {
				$this->action( 'rank_math/dashboard/widget', 'dashboard_widget', 9 );
			}
		}
	}

	/**
	 * Hide fetch notice.
	 *
	 * @param  string $notification_id Notification id.
	 */
	public function analytic_first_fetch_dismiss( $notification_id ) {
		if ( 'rank_math_analytics_first_fetch' !== $notification_id ) {
			return;
		}

		update_option( 'rank_math_analytics_first_fetch', 'hidden' );
	}

	/**
	 * Add stats into admin dashboard.
	 *
	 * @codeCoverageIgnore
	 */
	public function dashboard_widget() {
		Stats::get()->set_date_range( '-30 days' );
		$data                   = Stats::get()->get_widget();
		$analytics              = get_option( 'rank_math_google_analytic_options' );
		$is_analytics_connected = ! empty( $analytics ) && ! empty( $analytics['view_id'] );
		?>
		<h3>
			<?php esc_html_e( 'Analytics', 'rank-math' ); ?>
			<span><?php esc_html_e( 'Last 30 Days', 'rank-math' ); ?></span>
			<a href="<?php echo esc_url( Helper::get_admin_url( 'analytics' ) ); ?>" class="rank-math-view-report" title="<?php esc_html_e( 'View Report', 'rank-math' ); ?>"><i class="dashicons dashicons-ellipsis"></i></a>
		</h3>
		<div class="rank-math-dashabord-block items-4">

			<?php if ( $is_analytics_connected && defined( 'RANK_MATH_PRO_FILE' ) ) : ?>
			<div>
				<h4>
					<?php esc_html_e( 'Search Traffic', 'rank-math' ); ?>
					<span class="rank-math-tooltip"><em class="dashicons-before dashicons-editor-help"></em><span><?php esc_html_e( 'This is the number of pageviews carried out by visitors from Google.', 'rank-math' ); ?></span></span>
				</h4>
				<?php $this->get_analytic_block( $data->pageviews ); ?>
			</div>
			<?php endif; ?>

			<div>
				<h4>
					<?php esc_html_e( 'Total Impressions', 'rank-math' ); ?>
					<span class="rank-math-tooltip"><em class="dashicons-before dashicons-editor-help"></em><span><?php esc_html_e( 'How many times your site showed up in the search results.', 'rank-math' ); ?></span></span>
				</h4>
				<?php $this->get_analytic_block( $data->impressions ); ?>
			</div>

			<?php if ( ! $is_analytics_connected || ( $is_analytics_connected && ! defined( 'RANK_MATH_PRO_FILE' ) ) ) : ?>
			<div>
				<h4>
					<?php esc_html_e( 'Total Clicks', 'rank-math' ); ?>
					<span class="rank-math-tooltip"><em class="dashicons-before dashicons-editor-help"></em><span><?php esc_html_e( 'This is the number of pageviews carried out by visitors from Google.', 'rank-math' ); ?></span></span>
				</h4>
				<?php $this->get_analytic_block( $data->clicks ); ?>
			</div>
			<?php endif; ?>

			<div>
				<h4>
					<?php esc_html_e( 'Total Keywords', 'rank-math' ); ?>
					<span class="rank-math-tooltip"><em class="dashicons-before dashicons-editor-help"></em><span><?php esc_html_e( 'Total number of keywords your site ranking below 100 position.', 'rank-math' ); ?></span></span>
				</h4>
				<?php $this->get_analytic_block( $data->keywords ); ?>
			</div>

			<div>
				<h4>
					<?php esc_html_e( 'Average Position', 'rank-math' ); ?>
					<span class="rank-math-tooltip"><em class="dashicons-before dashicons-editor-help"></em><span><?php esc_html_e( 'This is the number of pageviews carried out by visitors from Google.', 'rank-math' ); ?></span></span>
				</h4>
				<?php $this->get_analytic_block( $data->position ); ?>
			</div>

		</div>
		<?php
	}

	/**
	 * Get analytic block
	 *
	 * @param object $item Item.
	 */
	private function get_analytic_block( $item ) {
		$is_negative = absint( $item['difference'] ) !== $item['difference'];
		$diff_class  = $is_negative ? 'down' : 'up';
		if ( ! $is_negative && $item['difference'] > 0 ) {
			$diff_class = 'up';
		}
		?>
		<div class="rank-math-item-numbers">
			<strong class="text-large" title="<?php echo esc_html( Str::human_number( $item['total'] ) ); ?>"><?php echo esc_html( Str::human_number( $item['total'] ) ); ?></strong>
			<span class="rank-math-item-difference <?php echo esc_attr( $diff_class ); ?>" title="<?php echo esc_html( Str::human_number( $item['difference'] ) ); ?>"><?php echo esc_html( Str::human_number( $item['difference'] ) ); ?></span>
		</div>
		<?php
	}

	/**
	 * Admin init.
	 */
	public function render_notice() {
		$this->remove_action( 'admin_notices', 'render_notice' );
		if ( 'fetching' === get_option( 'rank_math_analytics_first_fetch' ) ) {
			$actions = as_get_scheduled_actions(
				[
					'order'  => 'DESC',
					'hook'   => 'rank_math/analytics/get_analytics',
					'status' => \ActionScheduler_Store::STATUS_PENDING,
				]
			);
			if ( empty( $actions ) ) {
				update_option( 'rank_math_analytics_first_fetch', 'hidden' );
				return;
			}

			$action         = current( $actions );
			$schedule       = $action->get_schedule();
			$next_timestamp = $schedule->get_date()->getTimestamp();
			$notification   = new \MyThemeShop\Notification(
				/* translators: delete counter */
				sprintf( '<i class="rm-icon rm-icon-rank-math"></i>' . esc_html__( 'Rank Math is importing latest data from connected Google Services, %s remaining.', 'rank-math' ), $this->human_interval( $next_timestamp - gmdate( 'U' ) ) ),
				[
					'type'    => 'info',
					'id'      => 'rank_math_analytics_first_fetch',
					'classes' => 'rank-math-notice',
				]
			);

			echo $notification; // phpcs:ignore
		}
	}

	/**
	 * Convert an interval of seconds into a two part human friendly string.
	 *
	 * The WordPress human_time_diff() function only calculates the time difference to one degree, meaning
	 * even if an action is 1 day and 11 hours away, it will display "1 day". This function goes one step
	 * further to display two degrees of accuracy.
	 *
	 * Inspired by the Crontrol::interval() function by Edward Dale: https://wordpress.org/plugins/wp-crontrol/
	 *
	 * @param int $interval A interval in seconds.
	 * @param int $periods_to_include Depth of time periods to include, e.g. for an interval of 70, and $periods_to_include of 2, both minutes and seconds would be included. With a value of 1, only minutes would be included.
	 * @return string A human friendly string representation of the interval.
	 */
	private function human_interval( $interval, $periods_to_include = 2 ) {
		$time_periods = [
			[
				'seconds' => YEAR_IN_SECONDS,
				/* translators: %s: amount of time */
				'names'   => _n_noop( '%s year', '%s years', 'rank-math' ),
			],
			[
				'seconds' => MONTH_IN_SECONDS,
				/* translators: %s: amount of time */
				'names'   => _n_noop( '%s month', '%s months', 'rank-math' ),
			],
			[
				'seconds' => WEEK_IN_SECONDS,
				/* translators: %s: amount of time */
				'names'   => _n_noop( '%s week', '%s weeks', 'rank-math' ),
			],
			[
				'seconds' => DAY_IN_SECONDS,
				/* translators: %s: amount of time */
				'names'   => _n_noop( '%s day', '%s days', 'rank-math' ),
			],
			[
				'seconds' => HOUR_IN_SECONDS,
				/* translators: %s: amount of time */
				'names'   => _n_noop( '%s hour', '%s hours', 'rank-math' ),
			],
			[
				'seconds' => MINUTE_IN_SECONDS,
				/* translators: %s: amount of time */
				'names'   => _n_noop( '%s minute', '%s minutes', 'rank-math' ),
			],
			[
				'seconds' => 1,
				/* translators: %s: amount of time */
				'names'   => _n_noop( '%s second', '%s seconds', 'rank-math' ),
			],
		];

		if ( $interval <= 0 ) {
			return __( 'Now!', 'rank-math' );
		}

		$output = '';

		for ( $time_period_index = 0, $periods_included = 0, $seconds_remaining = $interval; $time_period_index < count( $time_periods ) && $seconds_remaining > 0 && $periods_included < $periods_to_include; $time_period_index++ ) { // phpcs:ignore

			$periods_in_interval = floor( $seconds_remaining / $time_periods[ $time_period_index ]['seconds'] );

			if ( $periods_in_interval > 0 ) {
				if ( ! empty( $output ) ) {
					$output .= ' ';
				}
				$output .= sprintf( _n( $time_periods[ $time_period_index ]['names'][0], $time_periods[ $time_period_index ]['names'][1], $periods_in_interval, 'rank-math' ), $periods_in_interval ); // phpcs:ignore
				$seconds_remaining -= $periods_in_interval * $time_periods[ $time_period_index ]['seconds'];
				$periods_included++;
			}
		}

		return $output;
	}

	/**
	 * Enqueue scripts for the metabox.
	 */
	public function enqueue() {
		$screen = get_current_screen();
		if ( 'rank-math_page_rank-math-analytics' !== $screen->id ) {
			return;
		}

		$uri = untrailingslashit( plugin_dir_url( __FILE__ ) );

		wp_enqueue_style(
			'rank-math-analytics',
			$uri . '/assets/css/stats.css',
			[],
			rank_math()->version
		);

		wp_register_script(
			'rank-math-analytics',
			$uri . '/assets/js/stats.js',
			[
				'wp-components',
				'wp-element',
				'wp-i18n',
				'wp-date',
				'wp-api-fetch',
				'wp-html-entities',
			],
			rank_math()->version,
			true
		);

		$this->action( 'admin_footer', 'dequeue_cmb2' );

		$preference = apply_filters(
			'rank_math/analytics/user_preference',
			[
				'topPosts'        => [
					'seo_score'       => false,
					'schemas_in_use'  => false,
					'impressions'     => true,
					'pageviews'       => true,
					'clicks'          => false,
					'position'        => true,
					'positionHistory' => true,
				],
				'siteAnalytics'   => [
					'seo_score'       => true,
					'schemas_in_use'  => true,
					'impressions'     => false,
					'pageviews'       => true,
					'links'           => true,
					'clicks'          => false,
					'position'        => false,
					'positionHistory' => false,
				],
				'performance'     => [
					'seo_score'       => true,
					'schemas_in_use'  => true,
					'impressions'     => true,
					'pageviews'       => true,
					'ctr'             => false,
					'clicks'          => true,
					'position'        => true,
					'positionHistory' => true,
				],
				'keywords'        => [
					'impressions'     => true,
					'ctr'             => false,
					'clicks'          => true,
					'position'        => true,
					'positionHistory' => true,
				],
				'topKeywords'     => [
					'impressions'     => true,
					'ctr'             => true,
					'clicks'          => true,
					'position'        => true,
					'positionHistory' => true,
				],
				'trackKeywords'   => [
					'impressions'     => true,
					'ctr'             => false,
					'clicks'          => true,
					'position'        => true,
					'positionHistory' => true,
				],
				'rankingKeywords' => [
					'impressions'     => true,
					'ctr'             => false,
					'clicks'          => true,
					'position'        => true,
					'positionHistory' => true,
				],
			]
		);

		$user_id = get_current_user_id();
		if ( metadata_exists( 'user', $user_id, 'rank_math_analytics_table_columns' ) ) {
			$preference = wp_parse_args(
				get_user_meta( $user_id, 'rank_math_analytics_table_columns', true ),
				$preference
			);
		}

		Helper::add_json( 'userColumnPreference', $preference );

		// Last Updated.
		$updated = get_option( 'rank_math_analytics_last_updated', false );
		$updated = $updated ? date_i18n( get_option( 'date_format' ), $updated ) : '';
		Helper::add_json( 'lastUpdated', $updated );

		Helper::add_json( 'singleImage', rank_math()->plugin_url() . 'includes/modules/analytics/assets/img/single-post-report.jpg' );

		// Global Schema.
		$post_types = Helper::get_accessible_post_types();
		$global_schemas = [];
		foreach ( $post_types as $post_type ) {
			$global_schemas[ $post_type ] = SchemaHelper::sanitize_schema_title(
				Helper::get_default_schema_type( $post_type )
			);
		}
		Helper::add_json( 'globalSchemaTypes', array_filter( $global_schemas ) );
	}

	/**
	 * Dequeue cmb2.
	 */
	public function dequeue_cmb2() {
		wp_dequeue_script( 'cmb2-scripts' );
	}

	/**
	 * Register admin page.
	 */
	public function register_admin_page() {
		$dot_color = '#ed5e5e';
		if ( Console::is_console_connected() ) {
			$dot_color = '#11ac84';
		}

		$this->page = new Page(
			'rank-math-analytics',
			esc_html__( 'Analytics', 'rank-math' ) . '<span class="rm-menu-new update-plugins" style="background: ' . $dot_color . '; margin-left: 5px;min-width: 10px;height: 10px;margin-top: 5px;"><span class="plugin-count"></span></span>',
			[
				'position'   => 5,
				'parent'     => 'rank-math',
				'capability' => 'rank_math_analytics',
				'render'     => $this->directory . '/views/dashboard.php',
				'classes'    => [ 'rank-math-page' ],
				'assets'     => [
					'styles'  => [
						'rank-math-common'    => '',
						'rank-math-analytics' => '',
					],
					'scripts' => [
						'rank-math-analytics' => '',
					],
				],
			]
		);
	}

	/**
	 * Add module settings into general optional panel.
	 *
	 * @param array $tabs Array of option panel tabs.
	 *
	 * @return array
	 */
	public function add_settings( $tabs ) {
		Arr::insert(
			$tabs,
			[
				'analytics' => [
					'icon'  => 'rm-icon rm-icon-search-console',
					'title' => esc_html__( 'Analytics', 'rank-math' ),
					/* translators: Link to kb article */
					'desc'  => sprintf( esc_html__( 'See your Google Search Console, Analyitcs and AdSense data without leaving your WP dashboard. %s.', 'rank-math' ), '<a href="' . KB::get( 'analytics-settings' ) . '" target="_blank">' . esc_html__( 'Learn more', 'rank-math' ) . '</a>' ),
					'file'  => $this->directory . '/views/options.php',
				],
			],
			9
		);

		return $tabs;
	}

	/**
	 * Add database tools.
	 *
	 * @param array $tools Array of tools.
	 *
	 * @return array
	 */
	public function add_tools( $tools ) {
		Arr::insert(
			$tools,
			[
				'analytics_clear_caches'  => [
					'title'       => __( 'Purge Analytics Cache', 'rank-math' ),
					/* translators: 1. Review Schema documentation link */
					'description' => sprintf( __( 'Clear analytics cache to re-calculate all the stats again.', 'rank-math' ), '<a href="https://rankmath.com/kb/how-to-fix-review-schema-errors/" target="_blank">' . esc_attr__( 'here', 'rank-math' ) . '</a>' ),
					'button_text' => __( 'Clear Cache', 'rank-math' ),
				],
				'analytics_reindex_posts' => [
					'title'       => __( 'Rebuild Index for Analytics', 'rank-math' ),
					/* translators: 1. Review Schema documentation link */
					'description' => sprintf( __( 'Missing some posts/pages in the Analytics data? Clear the index and build a new one for more accurate stats.', 'rank-math' ), '<a href="https://rankmath.com/kb/how-to-fix-review-schema-errors/" target="_blank">' . esc_attr__( 'here', 'rank-math' ) . '</a>' ),
					'button_text' => __( 'Rebuild Index', 'rank-math' ),
				],
				'analytics_recreate_table' => [
					'title'       => __( 'Re-create database Table', 'rank-math' ),
					/* translators: 1. Review Schema documentation link */
					'description' => __( 'Create database tables if missing.', 'rank-math' ),
					'button_text' => __( 'Re-create Tables', 'rank-math' ),
				],
			],
			3
		);

		return $tools;
	}
}
