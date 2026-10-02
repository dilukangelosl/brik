<?php
/**
 * Builder canvas for theme builder templates and library items.
 *
 * @package Brik
 */

defined( 'ABSPATH' ) || exit;

$brik_id     = Brik\Builder::canvas_post_id();
$brik_result = Brik\Frontend::render( $brik_id, true );
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'brik-canvas-page' ); ?>>
<?php
wp_body_open();
echo $brik_result['html']; // phpcs:ignore WordPress.Security.EscapeOutput
wp_footer();
?>
</body>
</html>
