<?php
/**
 * Image gallery: grid, masonry or justified rows with a shared lightbox.
 *
 * @package Brik
 */

use Brik\Fields;
use Brik\Style;

defined( 'ABSPATH' ) || exit;

$brik_gallery_samples = array(
	array( '1501785888041-af3ef285b470', 1200, 800, __( 'Lake between pine-covered mountains', 'brik-builder' ), __( 'Braies, Italy', 'brik-builder' ) ),
	array( '1470071459604-3b5ec3a7fe05', 900, 1200, __( 'Fog rolling over green hills', 'brik-builder' ), __( 'Morning fog', 'brik-builder' ) ),
	array( '1500530855697-b586d89ba3ee', 1200, 900, __( 'Road winding into the mountains', 'brik-builder' ), __( 'The long road', 'brik-builder' ) ),
	array( '1441974231531-c6227db76b6e', 1200, 800, __( 'Sunlight through a forest', 'brik-builder' ), __( 'Forest light', 'brik-builder' ) ),
	array( '1519681393784-d120267933ba', 1000, 1250, __( 'Starry sky over snowy peaks', 'brik-builder' ), __( 'Night sky', 'brik-builder' ) ),
	array( '1507525428034-b723cf961d3e', 1200, 800, __( 'Turquoise sea and sandy beach', 'brik-builder' ), __( 'Coastline', 'brik-builder' ) ),
	array( '1472214103451-9374bd1c798e', 1200, 900, __( 'Green valley at golden hour', 'brik-builder' ), __( 'Golden hour', 'brik-builder' ) ),
	array( '1464822759023-fed622ff2c3b', 900, 1200, __( 'Snow-capped mountain peak', 'brik-builder' ), __( 'Summit', 'brik-builder' ) ),
);

$brik_gallery_default = array();
foreach ( $brik_gallery_samples as $brik_sample ) {
	$brik_gallery_default[] = array(
		'url'     => brik_sample_image( $brik_sample[0], $brik_sample[1], $brik_sample[2] ),
		'alt'     => $brik_sample[3],
		'caption' => $brik_sample[4],
	);
}

return array(
	'type'        => 'gallery',
	'title'       => __( 'Gallery', 'brik-builder' ),
	'category'    => 'media',
	'icon'        => 'images',
	'description' => 'Image gallery with lightbox (prev/next, keyboard, swipe). images: list of {id,url,alt,caption}. layout: grid|masonry|justified. columns: 1-8 (responsive, grid/masonry). gap: CSS length. ratio: grid tile ratio (auto|1:1|4:3|3:2|16:9|3:4|4:5…). row_height: justified row height. action: lightbox|file|none. captions: none|below|overlay. size: WP image size. rounded: none|sm|md|lg|xl|2xl. hover_zoom: bool.',
	'fields'      => array_merge(
		array(
			'images'     => Fields::field( 'gallery', __( 'Images', 'brik-builder' ), 'content', array( 'default' => $brik_gallery_default ) ),
			'layout'     => Fields::field( 'select', __( 'Layout', 'brik-builder' ), 'content', array( 'default' => 'grid', 'options' => Fields::opts( array( 'grid' => __( 'Grid', 'brik-builder' ), 'masonry' => __( 'Masonry', 'brik-builder' ), 'justified' => __( 'Justified rows', 'brik-builder' ) ) ) ) ),
			'columns'    => Fields::field( 'number', __( 'Columns', 'brik-builder' ), 'content', array( 'default' => 4, 'min' => 1, 'max' => 8, 'responsive' => true, 'show_if' => array( 'layout' => array( 'grid', 'masonry' ) ) ) ),
			'ratio'      => Fields::field( 'select', __( 'Tile aspect ratio', 'brik-builder' ), 'content', array( 'default' => '1:1', 'options' => Fields::opts( brik_aspect_options() ), 'show_if' => array( 'layout' => 'grid' ) ) ),
			'row_height' => Fields::field( 'unit', __( 'Row height', 'brik-builder' ), 'content', array( 'default' => '240px', 'responsive' => true, 'show_if' => array( 'layout' => 'justified' ), 'css' => array( Fields::WRAP . ' .brik-gallery-items', '--brik-row-h' ) ) ),
			'gap'        => Fields::field( 'unit', __( 'Gap', 'brik-builder' ), 'content', array( 'default' => '12px', 'responsive' => true, 'css' => array( Fields::WRAP . ' .brik-gallery-items', '--brik-gap' ) ) ),
			'size'       => Fields::field( 'select', __( 'Image size', 'brik-builder' ), 'content', array( 'default' => 'large', 'options' => Fields::opts( brik_image_size_options() ) ) ),
			'action'     => Fields::field( 'select', __( 'On click', 'brik-builder' ), 'content', array( 'default' => 'lightbox', 'options' => Fields::opts( array( 'lightbox' => __( 'Open lightbox', 'brik-builder' ), 'file' => __( 'Open image file', 'brik-builder' ), 'none' => __( 'Nothing', 'brik-builder' ) ) ) ) ),
			'captions'   => Fields::field( 'select', __( 'Captions', 'brik-builder' ), 'content', array( 'default' => 'overlay', 'options' => Fields::opts( array( 'none' => __( 'Hidden', 'brik-builder' ), 'below' => __( 'Below image', 'brik-builder' ), 'overlay' => __( 'Overlay on hover', 'brik-builder' ) ) ) ) ),
			'rounded'    => Fields::field( 'select', __( 'Rounded corners', 'brik-builder' ), 'image_style', array( 'tab' => 'design', 'group_label' => __( 'Image style', 'brik-builder' ), 'default' => 'md', 'options' => Fields::opts( brik_radius_options() ) ) ),
			'hover_zoom' => Fields::field( 'toggle', __( 'Zoom on hover', 'brik-builder' ), 'image_style', array( 'tab' => 'design', 'group_label' => __( 'Image style', 'brik-builder' ), 'default' => true ) ),
		),
		Fields::typography( 'caption', __( 'Captions', 'brik-builder' ), Fields::WRAP . ' .brik-gallery-caption' )
	),
	'render'      => static function ( $a, $ctx ) {
		$size  = $a['size'] ? $a['size'] : 'large';
		$items = brik_media_items( $a['images'], $size );
		if ( ! $items ) {
			return $ctx->placeholder( __( 'Add images to the gallery', 'brik-builder' ) );
		}

		$layout = in_array( $a['layout'], array( 'grid', 'masonry', 'justified' ), true ) ? $a['layout'] : 'grid';

		// Columns step down on smaller screens unless set explicitly.
		$cols = max( 1, min( 8, (int) $a['columns'] ? (int) $a['columns'] : 4 ) );
		$ctx->css( Fields::WRAP . ' .brik-gallery-items', '--brik-cols:' . $cols );
		$tab = Style::raw_value( $a, 'columns', 'tablet' );
		$tab = null !== $tab ? max( 1, (int) $tab ) : min( $cols, 3 );
		$ctx->css( Fields::WRAP . ' .brik-gallery-items', '--brik-cols:' . $tab, 'tablet' );
		$mob = Style::raw_value( $a, 'columns', 'mobile' );
		$ctx->css( Fields::WRAP . ' .brik-gallery-items', '--brik-cols:' . ( null !== $mob ? max( 1, (int) $mob ) : min( $tab, 2 ) ), 'mobile' );

		$ratio  = 'grid' === $layout ? brik_aspect_class( $a['ratio'] ) : '';
		$radius = brik_radius_class( $a['rounded'] );
		$group  = $ctx->uid();
		$out    = '';

		foreach ( $items as $item ) {
			$caption = $item['caption'];
			$img     = brik_media_img(
				$item,
				$size,
				array(
					'class' => brik_cls(
						'brik-gallery-img block w-full',
						$ratio || 'justified' === $layout ? 'h-full object-cover' : 'h-auto',
						array( 'transition-transform duration-500 ease-out group-hover:scale-105' => ! empty( $a['hover_zoom'] ) )
					),
				)
			);

			$frame = brik_cls( 'brik-gallery-frame group relative block overflow-hidden bg-muted', $ratio, $radius, array( 'h-full' => 'justified' === $layout ) );
			if ( 'overlay' === $a['captions'] && '' !== $caption ) {
				$img .= '<span class="brik-gallery-caption pointer-events-none absolute inset-x-0 bottom-0 translate-y-1 bg-linear-to-t from-black/70 to-transparent px-3 pt-8 pb-3 text-left text-sm font-medium text-white opacity-0 transition-all duration-300 group-hover:translate-y-0 group-hover:opacity-100 group-focus-visible:translate-y-0 group-focus-visible:opacity-100">' . esc_html( $caption ) . '</span>';
			}

			if ( 'lightbox' === $a['action'] ) {
				$link = sprintf(
					'<a href="%1$s" class="%2$s" data-brik-lightbox="%3$s" data-caption="%4$s">%5$s</a>',
					esc_url( $item['full'] ),
					esc_attr( $frame . ' cursor-zoom-in outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50' ),
					esc_attr( $group ),
					esc_attr( $caption ),
					$img
				);
			} elseif ( 'file' === $a['action'] ) {
				$link = '<a href="' . esc_url( $item['full'] ) . '" class="' . esc_attr( $frame . ' outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50' ) . '" target="_blank" rel="noopener">' . $img . '</a>';
			} else {
				$link = '<div class="' . esc_attr( $frame ) . '">' . $img . '</div>';
			}

			$below = 'below' === $a['captions'] && '' !== $caption ? '<figcaption class="brik-gallery-caption mt-2 text-sm text-muted-foreground">' . esc_html( $caption ) . '</figcaption>' : '';
			$style = '';
			if ( 'justified' === $layout ) {
				$r     = $item['width'] && $item['height'] ? round( $item['width'] / $item['height'], 4 ) : 1.5;
				$style = ' style="--brik-r:' . esc_attr( $r ) . '"';
			}
			$out .= '<figure class="brik-gallery-item"' . $style . '>' . $link . $below . '</figure>';
		}

		return '<div class="brik-gallery-items brik-gallery--' . esc_attr( $layout ) . '">' . $out . '</div>';
	},
);
