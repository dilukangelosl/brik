<?php
/**
 * Device mockup: a browser window, phone, tablet or laptop drawn in CSS around an image or video.
 *
 * @package Brik
 * @author  Diluk Angelo
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'device_mockup',
	'title'       => __( 'Device Mockup', 'brik-builder' ),
	'category'    => 'effects',
	'icon'        => 'monitor-smartphone',
	'description' => 'CSS device frame around an image or video. device: browser|phone|tablet|laptop. frame_theme: dark|light. image; video: mp4/YouTube/Vimeo URL (wins over image, plays muted on a loop). url: browser address bar text. ratio: screen ratio for browser/tablet/laptop (16:10|16:9|4:3|3:2|3:4…). angle: none|left|right, a resting 3D turn. float: bool, gentle floating motion. tilt: bool, follows the pointer on hover. glow: bool, soft colored halo behind the device. device_width: CSS length. alt: image alt text.',
	'fields'      => array(
		'device'      => Fields::field( 'select', __( 'Device', 'brik-builder' ), 'content', array( 'default' => 'browser', 'options' => Fields::opts( array( 'browser' => __( 'Browser window', 'brik-builder' ), 'phone' => __( 'Phone', 'brik-builder' ), 'tablet' => __( 'Tablet', 'brik-builder' ), 'laptop' => __( 'Laptop', 'brik-builder' ) ) ) ) ),
		'image'       => Fields::field( 'image', __( 'Screen image', 'brik-builder' ), 'content', array( 'default' => array( 'url' => brik_sample_image( '1618005182384-a83a8bd57fbe', 1600, 1000 ), 'alt' => __( 'Abstract blue and violet waves', 'brik-builder' ) ) ) ),
		'alt'         => Fields::field( 'text', __( 'Alt text', 'brik-builder' ), 'content' ),
		'video'       => Fields::field( 'text', __( 'Screen video URL', 'brik-builder' ), 'content', array( 'placeholder' => 'https://…/clip.mp4', 'description' => __( 'Optional. Plays muted on a loop instead of the image.', 'brik-builder' ) ) ),
		'url'         => Fields::field( 'text', __( 'Address bar text', 'brik-builder' ), 'content', array( 'default' => 'brik.design', 'show_if' => array( 'device' => 'browser' ) ) ),
		'ratio'       => Fields::field( 'select', __( 'Screen ratio', 'brik-builder' ), 'content', array( 'default' => '16:10', 'options' => Fields::opts( brik_3d_ratio_options() ), 'show_if' => array( 'device' => array( 'browser', 'tablet', 'laptop' ) ) ) ),
		'frame_theme' => Fields::field( 'select', __( 'Frame color', 'brik-builder' ), 'device_style', array( 'tab' => 'design', 'group_label' => __( 'Device', 'brik-builder' ), 'default' => 'dark', 'options' => Fields::opts( array( 'dark' => __( 'Dark', 'brik-builder' ), 'light' => __( 'Light', 'brik-builder' ) ) ) ) ),
		'angle'       => Fields::field( 'select', __( '3D angle', 'brik-builder' ), 'device_style', array( 'tab' => 'design', 'group_label' => __( 'Device', 'brik-builder' ), 'default' => 'none', 'options' => Fields::opts( array( 'none' => __( 'Straight', 'brik-builder' ), 'left' => __( 'Turned left', 'brik-builder' ), 'right' => __( 'Turned right', 'brik-builder' ) ) ) ) ),
		'float'       => Fields::field( 'toggle', __( 'Float animation', 'brik-builder' ), 'device_style', array( 'tab' => 'design', 'group_label' => __( 'Device', 'brik-builder' ), 'default' => true ) ),
		'tilt'        => Fields::field( 'toggle', __( 'Tilt on hover', 'brik-builder' ), 'device_style', array( 'tab' => 'design', 'group_label' => __( 'Device', 'brik-builder' ), 'default' => true ) ),
		'glow'        => Fields::field( 'toggle', __( 'Glow behind', 'brik-builder' ), 'device_style', array( 'tab' => 'design', 'group_label' => __( 'Device', 'brik-builder' ), 'default' => true ) ),
		'glow_color'  => Fields::field( 'color', __( 'Glow color', 'brik-builder' ), 'device_style', array( 'tab' => 'design', 'group_label' => __( 'Device', 'brik-builder' ), 'show_if' => array( 'glow' => '!' ), 'css' => array( Fields::WRAP . ' .brik-dm', '--brik-dm-glow' ) ) ),
		'device_width' => Fields::field( 'unit', __( 'Device width', 'brik-builder' ), 'device_style', array( 'tab' => 'design', 'group_label' => __( 'Device', 'brik-builder' ), 'responsive' => true, 'css' => array( Fields::WRAP . ' .brik-dm-device', 'max-width' ) ) ),
	),
	'render'      => static function ( $a, $ctx ) {
		$device = in_array( $a['device'], array( 'browser', 'phone', 'tablet', 'laptop' ), true ) ? $a['device'] : 'browser';
		$args   = array(
			'class' => 'absolute inset-0 h-full w-full object-cover',
			'size'  => 'phone' === $device ? 'large' : 'full',
		);
		if ( '' !== (string) $a['alt'] ) {
			$args['alt'] = wp_strip_all_tags( $a['alt'] );
		}
		$media = brik_3d_media( $a['image'], $a['video'], $args );
		if ( '' === $media ) {
			$media = $ctx->canvas ? '<div class="absolute inset-0 grid place-items-center bg-muted text-sm text-muted-foreground">' . esc_html__( 'Choose an image or video', 'brik-builder' ) . '</div>' : '';
		}
		$ctx->script( 'device-mockup' );

		$widths = array(
			'browser' => 'max-w-[1000px]',
			'laptop'  => 'max-w-[900px]',
			'tablet'  => 'max-w-[640px]',
			'phone'   => 'max-w-[300px]',
		);
		$frame  = brik_3d_device( $device, $media, array( 'theme' => $a['frame_theme'], 'ratio' => $a['ratio'], 'url' => $a['url'] ) );

		return sprintf(
			'<div class="%1$s"%2$s><div class="%3$s">%4$s<div class="brik-dm-float"><div class="brik-dm-tilt">%5$s</div></div></div></div>',
			esc_attr(
				brik_cls(
					'brik-dm',
					'brik-dm--' . ( in_array( $a['angle'], array( 'left', 'right' ), true ) ? $a['angle'] : 'none' ),
					array(
						'brik-dm--float' => ! empty( $a['float'] ),
						'brik-dm--tilt'  => ! empty( $a['tilt'] ),
					)
				)
			),
			! empty( $a['tilt'] ) ? ' data-brik-tilt' : '',
			esc_attr( brik_cls( 'brik-dm-device relative mx-auto w-full', $widths[ $device ] ) ),
			! empty( $a['glow'] ) ? '<div class="brik-dm-glow" aria-hidden="true"></div>' : '',
			$frame
		);
	},
);
