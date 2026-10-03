<?php
/**
 * Image with optional link or lightbox, caption and framing presets.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'image',
	'title'       => __( 'Image', 'brik-builder' ),
	'category'    => 'media',
	'icon'        => 'image',
	'description' => 'Single image. image: {id,url,alt} or URL. alt overrides the media alt text. size: WP image size (thumbnail|medium|large|full…). action: none|link|lightbox (link uses "link"). caption: text. ratio: auto|16:9|4:3|3:2|1:1|21:9|2:3|3:4|4:5|9:16 with fit: cover|contain. rounded: none|sm|md|lg|xl|2xl|full. shadow: none|sm|md|lg|xl|2xl. hover_zoom: bool. full: stretch to column width. align: left|center|right.',
	'fields'      => array_merge(
		array(
			'image'      => Fields::field( 'image', __( 'Image', 'brik-builder' ), 'content', array( 'default' => array( 'url' => brik_sample_image( '1506905925346-21bda4d32df4', 1600, 1000 ), 'alt' => __( 'Mountain ridge above the clouds at sunrise', 'brik-builder' ) ) ) ),
			'alt'        => Fields::field( 'text', __( 'Alt text', 'brik-builder' ), 'content', array( 'description' => __( 'Leave empty to use the alt text from the media library.', 'brik-builder' ) ) ),
			'size'       => Fields::field( 'select', __( 'Image size', 'brik-builder' ), 'content', array( 'default' => 'large', 'options' => Fields::opts( brik_image_size_options() ) ) ),
			'action'     => Fields::field( 'select', __( 'On click', 'brik-builder' ), 'content', array( 'default' => 'none', 'options' => Fields::opts( array( 'none' => __( 'Nothing', 'brik-builder' ), 'link' => __( 'Open link', 'brik-builder' ), 'lightbox' => __( 'Open in lightbox', 'brik-builder' ) ) ) ) ),
			'link'       => Fields::field( 'link', __( 'Link', 'brik-builder' ), 'content', array( 'show_if' => array( 'action' => 'link' ) ) ),
			'caption'    => Fields::field( 'text', __( 'Caption', 'brik-builder' ), 'content', array( 'inline' => true ) ),
			'ratio'      => Fields::field( 'select', __( 'Aspect ratio', 'brik-builder' ), 'content', array( 'default' => 'auto', 'responsive' => true, 'options' => Fields::opts( brik_aspect_options() ) ) ),
			'fit'        => Fields::field( 'select', __( 'Image fit', 'brik-builder' ), 'content', array( 'default' => 'cover', 'options' => Fields::opts( array( 'cover' => __( 'Cover (crop)', 'brik-builder' ), 'contain' => __( 'Contain', 'brik-builder' ) ) ), 'show_if' => array( 'ratio' => array( '16:9', '4:3', '3:2', '1:1', '21:9', '2:3', '3:4', '4:5', '9:16' ) ) ) ),
			'focal'      => Fields::field( 'select', __( 'Focal point', 'brik-builder' ), 'content', array( 'options' => Fields::opts( Fields::positions() ), 'css' => array( Fields::WRAP . ' .brik-image-img', 'object-position' ), 'show_if' => array( 'fit' => 'cover' ) ) ),
			'full'       => Fields::field( 'toggle', __( 'Stretch to full width', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'align'      => Fields::field( 'select', __( 'Alignment', 'brik-builder' ), 'content', array( 'responsive' => true, 'options' => Fields::opts( array( '' => __( 'Default', 'brik-builder' ), 'left' => __( 'Left', 'brik-builder' ), 'center' => __( 'Center', 'brik-builder' ), 'right' => __( 'Right', 'brik-builder' ) ) ), 'css' => array( 'selector' => Fields::WRAP . ' .brik-image-figure', 'map' => array( 'left' => 'margin-right:auto;margin-left:0', 'center' => 'margin-inline:auto', 'right' => 'margin-left:auto;margin-right:0' ) ) ) ),
			'rounded'    => Fields::field( 'select', __( 'Rounded corners', 'brik-builder' ), 'image_style', array( 'tab' => 'design', 'group_label' => __( 'Image style', 'brik-builder' ), 'default' => 'lg', 'options' => Fields::opts( brik_radius_options() ) ) ),
			'img_shadow' => Fields::field( 'select', __( 'Shadow', 'brik-builder' ), 'image_style', array( 'tab' => 'design', 'group_label' => __( 'Image style', 'brik-builder' ), 'default' => 'none', 'options' => Fields::opts( brik_shadow_options() ) ) ),
			'hover_zoom' => Fields::field( 'toggle', __( 'Zoom on hover', 'brik-builder' ), 'image_style', array( 'tab' => 'design', 'group_label' => __( 'Image style', 'brik-builder' ) ) ),
			'img_width'  => Fields::field( 'unit', __( 'Image width', 'brik-builder' ), 'image_style', array( 'tab' => 'design', 'group_label' => __( 'Image style', 'brik-builder' ), 'responsive' => true, 'css' => array( Fields::WRAP . ' .brik-image-figure', 'width' ) ) ),
		),
		Fields::typography( 'caption', __( 'Caption', 'brik-builder' ), Fields::WRAP . ' .brik-image-caption' )
	),
	'render'      => static function ( $a, $ctx ) {
		$items = brik_media_items( $a['image'], $a['size'] ? $a['size'] : 'large' );
		if ( ! $items ) {
			return $ctx->placeholder( __( 'Choose an image', 'brik-builder' ) );
		}
		$item  = $items[0];
		$ratio = brik_aspect_class( $a['ratio'] );
		$alt   = '' !== $a['alt'] ? $a['alt'] : $item['alt'];

		// Responsive ratios: the desktop value drives the class, smaller screens override via CSS.
		foreach ( array( 'tablet', 'mobile' ) as $state ) {
			$r = Brik\Style::raw_value( $a, 'ratio', $state );
			if ( $r ) {
				$ctx->css( Fields::WRAP . ' .brik-image-frame', 'auto' === $r ? 'aspect-ratio:auto' : 'aspect-ratio:' . str_replace( ':', '/', $r ), $state );
				$ctx->css( Fields::WRAP . ' .brik-image-img', 'auto' === $r ? 'height:auto' : 'width:100%;height:100%;object-fit:' . ( 'contain' === $a['fit'] ? 'contain' : 'cover' ), $state );
			}
		}

		$img = brik_media_img(
			$item,
			$a['size'] ? $a['size'] : 'large',
			array(
				'alt'   => $alt,
				'class' => brik_cls(
					'brik-image-img block max-w-full',
					$ratio ? 'h-full w-full ' . brik_object_fit_class( $a['fit'] ) : 'h-auto',
					array(
						'w-full'                                                    => ! empty( $a['full'] ) && ! $ratio,
						'transition-transform duration-500 ease-out group-hover:scale-105' => ! empty( $a['hover_zoom'] ),
					)
				),
			)
		);

		$frame_class = brik_cls(
			'brik-image-frame group relative block overflow-hidden',
			$ratio,
			brik_radius_class( $a['rounded'] ),
			brik_shadow_class( $a['img_shadow'] )
		);

		if ( 'lightbox' === $a['action'] ) {
			$inner = sprintf(
				'<a href="%1$s" class="%2$s" data-brik-lightbox="%3$s" data-caption="%4$s" aria-label="%5$s">%6$s<span class="brik-image-zoom pointer-events-none absolute right-3 bottom-3 inline-flex size-9 items-center justify-center rounded-full bg-background/80 text-foreground opacity-0 shadow-sm backdrop-blur transition-opacity group-hover:opacity-100 group-focus-visible:opacity-100">%7$s</span></a>',
				esc_url( $item['full'] ),
				esc_attr( $frame_class . ' cursor-zoom-in outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50' ),
				esc_attr( $ctx->uid() ),
				esc_attr( wp_strip_all_tags( '' !== $a['caption'] ? $a['caption'] : $item['caption'] ) ),
				esc_attr__( 'Open image', 'brik-builder' ),
				$img,
				brik_icon( 'zoom-in', 'size-4' )
			);
		} elseif ( 'link' === $a['action'] && ! empty( $a['link']['url'] ) ) {
			$inner = '<a' . brik_link_attrs( $a['link'], array( 'class' => $frame_class . ' outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50' ) ) . '>' . $img . '</a>';
		} else {
			$inner = '<div class="' . esc_attr( $frame_class ) . '">' . $img . '</div>';
		}

		$caption = '' !== $a['caption'] ? '<figcaption class="brik-image-caption mt-3 text-sm text-muted-foreground"' . $ctx->inline( 'caption' ) . '>' . brik_inline( $a['caption'] ) . '</figcaption>' : '';
		$figure  = brik_cls( 'brik-image-figure max-w-full', $ratio || ! empty( $a['full'] ) ? 'w-full' : 'w-fit' );

		return '<figure class="' . esc_attr( $figure ) . '">' . $inner . $caption . '</figure>';
	},
);
