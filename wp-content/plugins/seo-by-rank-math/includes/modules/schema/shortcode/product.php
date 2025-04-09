<?php
/**
 * Shortcode - Recipe
 *
 * @package    RankMath
 * @subpackage RankMath\Schema
 */

defined( 'ABSPATH' ) || exit;

$this->get_title();
$this->get_image();
?>
<div class="rank-math-review-data">

	<?php $this->get_description(); ?>

	<?php
	$this->get_field(
		esc_html__( 'Type', 'rank-math' ),
		'recipeCategory'
	);
	?>

	<?php
	$this->get_field(
		esc_html__( 'Cuisine', 'rank-math' ),
		'recipeCuisine'
	);
	?>

	<?php
	$this->get_field(
		esc_html__( 'Keywords', 'rank-math' ),
		'keywords'
	);
	?>

	<?php
	$this->get_field(
		esc_html__( 'Recipe Yield', 'rank-math' ),
		'recipeYield'
	);
	?>

	<?php
	$this->get_field(
		esc_html__( 'Calories', 'rank-math' ),
		'nutrition.calories'
	);
	?>

	<?php
	$this->get_field(
		esc_html__( 'Preparation Time', 'rank-math' ),
		'prepTime'
	);
	?>

	<?php
	$this->get_field(
		esc_html__( 'Cooking Time', 'rank-math' ),
		'cookTime'
	);<?php
/**
 * Shortcode - Product
 *
 * @package    RankMath
 * @subpackage RankMath\Schema
 */

defined( 'ABSPATH' ) || exit;

$this->get_title();
$this->get_image();
?>
<div class="rank-math-review-data">

	<?php $this->get_description(); ?>

	<?php
	$this->get_field(
		esc_html__( 'Product SKU', 'rank-math' ),
		'sku'
	);
	?>

	<?php
	$this->get_field(
		esc_html__( 'Product Brand', 'rank-math' ),
		'brand.name'
	);
	?>

	<?php
	$this->get_field(
		esc_html__( 'Product Currency', 'rank-math' ),
		'offers.priceCurrency'
	);
	?>

	<?php
	$this->get_field(
		esc_html__( 'Product Price', 'rank-math' ),
		'offers.price'
	);
	?>

	<?php
	$this->get_field(
		esc_html__( 'Price Valid Until', 'rank-math' ),
		'offers.priceValidUntil'
	);
	?>

	<?php
	$this->get_field(
		esc_html__( 'Product In-Stock', 'rank-math' ),
		'offers.availability'
	);
	?>

	<?php $this->show_ratings(); ?>

</div>
