<?php
/**
 * Minimal template for the demo page only.
 *
 * @package careconnect-headless
 */

defined( 'ABSPATH' ) || exit;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body>
<main>
<?php
while ( have_posts() ) {
	the_post();
	echo '<h1>' . esc_html( get_the_title() ) . '</h1>';
	the_content();
}
?>
</main>
<?php wp_footer(); ?>
</body>
</html>
