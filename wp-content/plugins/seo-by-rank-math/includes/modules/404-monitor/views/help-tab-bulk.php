<?php
/**
 * Main template for 404 monitor
 *
 * @package    RankMath
 * @subpackage RankMath\Monitor
 */

use RankMath\Helper;

defined( 'ABSPATH' ) || exit;

$monitor = Helper::get_module( '404-monitor' )->admin;
$monitor->table->prepare_items();
?>
<div class="wrap rank-math-404-monitor-wrap">

	<h2>
		<?php echo esc_h<?php
/**
 * 404 Monitor inline help.
 *
 * @package    RankMath
 * @subpackage RankMath\Monitor
 */

defined( 'ABSPATH' ) || exit;

?>
<p>
	<?php esc_html_e( 'You can also redirect or delete multiple items at once. Selecting multiple items to redirect allows you to redirect them to a single URL.', 'rank-math' ); ?>
</p>
