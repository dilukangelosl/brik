<?php
/**
 * Sidebar: widgets from a registered widget area.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

$brik_sidebars = array( '' => __( 'First available', 'brik-builder' ) );
foreach ( isset( $GLOBALS['wp_registered_sidebars'] ) ? (array) $GLOBALS['wp_registered_sidebars'] : array() as $brik_sidebar_id => $brik_sidebar ) {
	$brik_sidebars[ $brik_sidebar_id ] = isset( $brik_sidebar['name'] ) ? $brik_sidebar['name'] : $brik_sidebar_id;
}

return array(
	'type'        => 'sidebar',
	'title'       => __( 'Sidebar', 'brik-builder' ),
	'category'    => 'site',
	'icon'        => 'panel-right',
	'description' => 'Widgets from a registered widget area (Appearance → Widgets). sidebar: widget area id, empty uses the first one. gap: space between widgets.',
	'fields'      => array_merge(
		array(
			'sidebar' => Fields::field( 'select', __( 'Widget area', 'brik-builder' ), 'content', array( 'options' => Fields::opts( $brik_sidebars ) ) ),
			'gap'     => Fields::field( 'unit', __( 'Space between widgets', 'brik-builder' ), 'content', array( 'responsive' => true, 'css' => array( Fields::WRAP . ' .brik-widgets', 'gap' ) ) ),
		),
		Fields::typography( 'widget_title', __( 'Widget titles', 'brik-builder' ), Fields::WRAP . ' .brik-widgets :is(.widget-title,.widgettitle,.wp-block-heading)' ),
		Fields::typography( 'widget_link', __( 'Widget links', 'brik-builder' ), Fields::WRAP . ' .brik-widgets a' )
	),
	'render'      => static function ( $a, $ctx ) {
		global $wp_registered_sidebars;
		$id = (string) $a['sidebar'];
		if ( '' === $id || ! isset( $wp_registered_sidebars[ $id ] ) ) {
			$ids = array_keys( (array) $wp_registered_sidebars );
			$id  = $ids ? (string) $ids[0] : '';
		}
		if ( '' === $id ) {
			return $ctx->placeholder( __( 'Your theme has no widget areas', 'brik-builder' ) );
		}
		if ( ! is_active_sidebar( $id ) ) {
			return $ctx->placeholder( __( 'Add widgets to this area under Appearance → Widgets', 'brik-builder' ) );
		}
		ob_start();
		dynamic_sidebar( $id );
		$html = (string) ob_get_clean();
		return '' === trim( $html ) ? '' : '<div class="brik-widgets flex flex-col gap-8">' . $html . '</div>';
	},
);
