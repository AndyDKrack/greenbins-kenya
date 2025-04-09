<?php
/**
 * Search console options.
 *
 * @package Rank_Math
 */

use RankMath\Analytics\DB;
use MyThemeShop\Helpers\Str;
use RankMath\Google\Authentication;

defined( 'ABSPATH' ) || exit;

// phpcs:disable
$actions = \as_get_scheduled_actions(
	[
		'hook' => 'rank_math/analytics/get_analytics',
		'status' => \ActionScheduler_Store::STATUS_PENDING,
	]
);
$db_info        = DB::info();
$is_queue_empty = empty( $actions );
$disable        = ( ! Authentication::is_authorized() || ! $is_queue_empty ) ? true : false;

if ( ! empty( $db_info ) ) {
	$db_info = [
		/* translators: number of days */
		'<div class="rank-math-console-db-info"><i class="rm-icon rm-icon-calendar"></i> ' . spr<?php
/**
 * Dashboard page template.
 *
 * @package    RankMath
 * @subpackage RankMath\Admin
 */

use RankMath\Helper;
use RankMath\Google\Authentication;

defined( 'ABSPATH' ) || exit;

// Header.
rank_math()->admin->display_admin_header();
$path = rank_math()->admin_dir() . 'wizard/views/'; // phpcs:ignore
?>
<div class="wrap rank-math-wrap analytics">

	<span class="wp-header-end"></span>

	<?php
	if ( ! Helper::is_site_connected() ) {
		require_once $path . 'rank-math-connect.php';
	} elseif ( ! Authentication::is_authorized() ) {
		require_once $path . 'google-connect.php';
	} else {
		echo '<div class="rank-math-analytics" id="rank-math-analytics"></div>';
	}
	?>

</div>
