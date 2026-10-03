<?php
/**
 * Builder canvas for theme builder templates and library items.
 *
 * Loop items (library kind "loop") are rendered against a preview post so dynamic tags,
 * {field:…} and post modules show real data: ?brik_preview_post=ID, the stored choice
 * (POST brik/v1/library/{id}/preview) or the latest post of the loop's post type.
 *
 * @package Brik
 */

defined( 'ABSPATH' ) || exit;

$brik_id      = Brik\Builder::canvas_post_id();
$brik_preview = Brik\Library::is_loop( $brik_id )
	? Brik\Library::preview_post( $brik_id, isset( $_GET['brik_preview_post'] ) ? absint( $_GET['brik_preview_post'] ) : 0 ) // phpcs:ignore WordPress.Security.NonceVerification
	: null;

if ( $brik_preview ) {
	$GLOBALS['post'] = $brik_preview; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
	setup_postdata( $brik_preview );

	$brik_renderer = new Brik\Renderer( $brik_preview->ID, true );
	$brik_html     = $brik_renderer->render_root( Brik\Data::get( $brik_id ), 'brik-content brik-loop-preview' );
	$brik_result   = array(
		// The root element survives live updates (only its content is swapped), so the width sits on it.
		'html' => preg_replace( '/^<div /', '<div style="--brik-loop-width:' . Brik\Library::preview_width( $brik_id ) . 'px" ', $brik_html, 1 ),
		'css'  => $brik_renderer->style->css(),
	);
	$brik_fonts = Brik\Fonts::url( $brik_renderer->style->fonts() );
	add_action(
		'wp_head',
		static function () use ( $brik_result, $brik_fonts ) {
			if ( $brik_fonts ) {
				echo '<link rel="stylesheet" id="brik-element-fonts" href="' . esc_url( $brik_fonts ) . '">' . "\n";
			}
			// Same id as the regular canvas stylesheet, so live updates replace it.
			echo '<style id="brik-css">' . $brik_result['css'] . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- generated, values cleaned in Style.
		},
		29
	);
	Brik\Frontend::enqueue();
} else {
	$brik_result = Brik\Frontend::render( $brik_id, true );
}
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
