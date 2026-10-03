<?php
/**
 * 3D marquee: an isometric wall of images whose columns drift up and down at different speeds.
 *
 * @package Brik
 * @author  Diluk Angelo
 */

use Brik\Fields;
use Brik\Style;

defined( 'ABSPATH' ) || exit;

$brik_m3d_default = array();
foreach (
	array(
		'1493246507139-91e8fad9978e', '1618005182384-a83a8bd57fbe', '1477959858617-67f85cf4f1df', '1462331940025-496dfbfc7564',
		'1505144808419-1957a94ca61e', '1508739773434-c26b3d09e071', '1620641788421-7a1c342ea42e', '1447752875215-b2761acb3c5d',
		'1449824913935-59a10b8d2000', '1634017839464-5c339ebe3cb4', '1433086966358-54859d0ed716', '1502134249126-9f3755a50d78',
		'1482192596544-9eb780fc7f66', '1579546929518-9e396f3cc809', '1480714378408-67cf0d13bc1b', '1446776811953-b23d57bd21aa',
		'1469474968028-56623f02e42e', '1557682250-33bd709cbe85', '1500382017468-9049fed747ef', '1614850523459-c2f4c699c52e',
	) as $brik_photo
) {
	$brik_m3d_default[] = array( 'url' => brik_sample_image( $brik_photo, 640, 480 ), 'alt' => '' );
}

return array(
	'type'        => 'marquee_3d',
	'title'       => __( '3D Marquee', 'brik-builder' ),
	'category'    => 'effects',
	'icon'        => 'layers',
	'description' => 'Tilted 3D wall of images: columns scroll up and down at different speeds in an isometric perspective. images: gallery list. columns: 2-8 (responsive, phones default to 3). speed: seconds per loop (lower = faster). wall_height: visible height (CSS length, responsive). tilt: bool, isometric angle (off = flat). ratio: tile ratio 4:3|1:1|3:4|16:10. gap: CSS length. rounded: radius. fade: bool, fades the edges. pause_on_hover: bool. Pure CSS animation, paused off screen; still with reduced motion.',
	'fields'      => array(
		'images'         => Fields::field( 'gallery', __( 'Images', 'brik-builder' ), 'content', array( 'default' => $brik_m3d_default ) ),
		'columns'        => Fields::field( 'number', __( 'Columns', 'brik-builder' ), 'content', array( 'default' => 5, 'min' => 2, 'max' => 8, 'responsive' => true ) ),
		'speed'          => Fields::field( 'range', __( 'Loop duration (seconds)', 'brik-builder' ), 'content', array( 'default' => 40, 'min' => 8, 'max' => 120, 'step' => 1 ) ),
		'wall_height'    => Fields::field( 'unit', __( 'Height', 'brik-builder' ), 'content', array( 'default' => '640px', 'responsive' => true, 'css' => array( Fields::WRAP . ' .brik-m3d', 'height' ) ) ),
		'tilt'           => Fields::field( 'toggle', __( 'Isometric tilt', 'brik-builder' ), 'content', array( 'default' => true ) ),
		'pause_on_hover' => Fields::field( 'toggle', __( 'Pause on hover', 'brik-builder' ), 'content', array( 'default' => true ) ),
		'fade'           => Fields::field( 'toggle', __( 'Fade edges', 'brik-builder' ), 'content', array( 'default' => true ) ),
		'ratio'          => Fields::field( 'select', __( 'Tile ratio', 'brik-builder' ), 'tile_style', array( 'tab' => 'design', 'group_label' => __( 'Tiles', 'brik-builder' ), 'default' => '4:3', 'options' => Fields::opts( array( '4:3' => '4:3', '1:1' => '1:1', '3:4' => '3:4', '16:10' => '16:10' ) ) ) ),
		'gap'            => Fields::field( 'unit', __( 'Gap', 'brik-builder' ), 'tile_style', array( 'tab' => 'design', 'group_label' => __( 'Tiles', 'brik-builder' ), 'default' => '16px', 'responsive' => true, 'css' => array( Fields::WRAP . ' .brik-m3d', '--brik-m3d-gap' ) ) ),
		'rounded'        => Fields::field( 'select', __( 'Rounded corners', 'brik-builder' ), 'tile_style', array( 'tab' => 'design', 'group_label' => __( 'Tiles', 'brik-builder' ), 'default' => 'lg', 'options' => Fields::opts( brik_radius_options() ) ) ),
	),
	'render'      => static function ( $a, $ctx ) {
		$items = brik_media_items( $a['images'], 'medium_large' );
		if ( ! $items ) {
			return $ctx->placeholder( __( 'Add images', 'brik-builder' ) );
		}
		$ctx->script( 'marquee-3d' );

		// Markup holds the largest column count used on any screen; smaller screens hide the rest.
		$cols   = (int) brik_3d_num( $a['columns'], 5, 2, 8 );
		$tablet = Style::raw_value( $a, 'columns', 'tablet' );
		$mobile = Style::raw_value( $a, 'columns', 'mobile' );
		$tablet = null !== $tablet ? (int) brik_3d_num( $tablet, 4, 2, 8 ) : min( $cols, 4 );
		$mobile = null !== $mobile ? (int) brik_3d_num( $mobile, 3, 2, 8 ) : min( $tablet, 3 );
		$max = max( $cols, $tablet, $mobile );
		foreach ( array( 'desktop' => $cols, 'tablet' => $tablet, 'mobile' => $mobile ) as $state => $count ) {
			$ctx->css( Fields::WRAP . ' .brik-m3d', '--brik-m3d-cols:' . $count, $state );
			if ( $count < $max ) {
				$ctx->css( Fields::WRAP . ' .brik-m3d-col:nth-child(n+' . ( $count + 1 ) . ')', 'display:none', $state );
			}
			if ( 'desktop' !== $state ) {
				$ctx->css( Fields::WRAP . ' .brik-m3d-col:nth-child(-n+' . $count . ')', 'display:flex', $state );
			}
		}

		$ratio  = brik_3d_ratio_class( $a['ratio'] );
		$radius = brik_radius_class( $a['rounded'] );
		$speed  = brik_3d_num( $a['speed'], 40, 8, 120 );
		$n      = count( $items );
		$per    = max( 4, (int) ceil( $n / $max ) );
		$html   = '';

		for ( $c = 0; $c < $max; $c++ ) {
			$tiles = '';
			for ( $k = 0; $k < $per; $k++ ) {
				$item   = $items[ ( $c + $k * $max ) % $n ];
				$tiles .= '<li class="' . esc_attr( brik_cls( 'brik-m3d-tile overflow-hidden bg-muted', $ratio, $radius ) ) . '">' . brik_media_img( $item, 'medium_large', array( 'class' => 'h-full w-full object-cover', 'alt' => $item['alt'] ) ) . '</li>';
			}
			// The second copy makes the loop seamless and is hidden from assistive tech.
			$html .= sprintf(
				'<div class="brik-m3d-col" style="%1$s"><ul class="brik-m3d-track">%2$s</ul><ul class="brik-m3d-track" aria-hidden="true">%3$s</ul></div>',
				esc_attr( '--brik-m3d-dur:' . round( $speed * ( 1 + 0.18 * ( $c % 3 ) ), 2 ) . 's;--brik-m3d-dir:' . ( $c % 2 ? 'reverse' : 'normal' ) . ';--brik-m3d-n:' . $c ),
				$tiles,
				str_replace( '<img ', '<img alt="" ', preg_replace( '/\salt="[^"]*"/', '', $tiles ) )
			);
		}

		return sprintf(
			'<div class="%1$s"><div class="brik-m3d-plane">%2$s</div></div>',
			esc_attr(
				brik_cls(
					'brik-m3d relative overflow-hidden',
					array(
						'brik-m3d--tilt'  => ! empty( $a['tilt'] ),
						'brik-m3d--fade'  => ! empty( $a['fade'] ),
						'brik-m3d--hover' => ! empty( $a['pause_on_hover'] ),
					)
				)
			),
			$html
		);
	},
);
