<?php
/**
 * Product gallery: main image slider (swipe on touch screens), thumbnails, hover zoom,
 * lightbox, sale badge, and the chosen variation's image.
 *
 * @package Brik
 */

use Brik\Fields;
use Brik\Woo\Product;

defined( 'ABSPATH' ) || exit;

if ( ! brik_woo_active() ) {
	return null;
}

return array(
	'type'        => 'product_gallery',
	'title'       => __( 'Product gallery', 'brik-builder' ),
	'category'    => 'shop',
	'icon'        => 'images',
	'description' => 'Featured image and gallery of the current product. thumbs: bottom|left|none (phones always show dots). ratio: 1:1|4:5|3:4|4:3|auto. fit: cover|contain. zoom: bool (magnify on hover). lightbox: bool. badge: bool (sale / sold out badge on the image). arrows: bool. rounded: none|sm|md|lg|xl|2xl. Switches to the variation image when a variation is chosen. product: optional fixed product id.',
	'fields'      => array_merge(
		array(
			'thumbs'   => Fields::field( 'select', __( 'Thumbnails', 'brik-builder' ), 'content', array( 'default' => 'bottom', 'options' => Fields::opts( array( 'bottom' => __( 'Below', 'brik-builder' ), 'left' => __( 'Left', 'brik-builder' ), 'none' => __( 'Hidden', 'brik-builder' ) ) ) ) ),
			'ratio'    => Fields::field( 'select', __( 'Aspect ratio', 'brik-builder' ), 'content', array( 'default' => '1:1', 'options' => Fields::opts( array( '1:1' => '1:1', '4:5' => '4:5', '3:4' => '3:4', '4:3' => '4:3', 'auto' => __( 'Original', 'brik-builder' ) ) ) ) ),
			'fit'      => Fields::field( 'select', __( 'Image fit', 'brik-builder' ), 'content', array( 'default' => 'cover', 'options' => Fields::opts( array( 'cover' => __( 'Cover (crop)', 'brik-builder' ), 'contain' => __( 'Contain', 'brik-builder' ) ) ) ) ),
			'zoom'     => Fields::field( 'toggle', __( 'Zoom on hover', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'lightbox' => Fields::field( 'toggle', __( 'Open in lightbox', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'badge'    => Fields::field( 'toggle', __( 'Sale badge', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'arrows'   => Fields::field( 'toggle', __( 'Arrows', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'size'     => Fields::field( 'select', __( 'Image size', 'brik-builder' ), 'content', array( 'default' => 'woocommerce_single', 'options' => Fields::opts( array_merge( array( 'woocommerce_single' => __( 'Shop single', 'brik-builder' ) ), brik_image_size_options() ) ) ) ),
			'rounded'  => Fields::field( 'select', __( 'Rounded corners', 'brik-builder' ), 'image_style', array( 'tab' => 'design', 'group_label' => __( 'Image style', 'brik-builder' ), 'default' => 'xl', 'options' => Fields::opts( brik_radius_options() ) ) ),
			'bg'       => Fields::field( 'color', __( 'Image background', 'brik-builder' ), 'image_style', array( 'tab' => 'design', 'group_label' => __( 'Image style', 'brik-builder' ), 'css' => array( Fields::WRAP . ' .brik-pg-slide', 'background-color' ) ) ),
		),
		Product::field()
	),
	'render'      => static function ( $a, $ctx ) {
		$product = brik_woo_product( $ctx, $a );
		if ( ! $product ) {
			return $ctx->placeholder( __( 'Product gallery', 'brik-builder' ) );
		}

		$ids    = Product::image_ids( $product );
		$size   = $a['size'] ? $a['size'] : 'woocommerce_single';
		$ratio  = 'auto' === $a['ratio'] ? '' : brik_aspect_class( $a['ratio'] ? $a['ratio'] : '1:1' );
		$radius = brik_radius_class( $a['rounded'] );
		$fit    = 'contain' === $a['fit'] ? 'object-contain' : 'object-cover';
		$thumbs = in_array( $a['thumbs'], array( 'bottom', 'left', 'none' ), true ) ? $a['thumbs'] : 'bottom';
		$group  = $ctx->uid( 'gallery' );
		$name   = $product->get_name();
		$count  = max( 1, count( $ids ) );

		$slides = '';
		$thumbs_html = '';
		$dots   = '';
		$list   = $ids ? $ids : array( 0 );
		foreach ( $list as $i => $id ) {
			if ( $id ) {
				$full = wp_get_attachment_image_src( $id, 'full' );
				$alt  = trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) );
				$attr = array(
					'class'   => brik_cls( 'brik-pg-img size-full', $ratio ? $fit : 'h-auto' ),
					'alt'     => '' !== $alt ? $alt : $name,
					'loading' => 0 === $i ? 'eager' : 'lazy',
					'sizes'   => '(max-width: 767px) 100vw, 50vw',
				);
				if ( 0 === $i ) {
					$attr['fetchpriority'] = 'high';
				}
				$img  = wp_get_attachment_image( $id, $size, false, $attr );
				$href = $full ? $full[0] : '';
			} else {
				$img  = wc_placeholder_img( $size, array( 'class' => brik_cls( 'brik-pg-img size-full', $fit ) ) );
				$href = wc_placeholder_img_src( 'full' );
			}

			$inner = ! empty( $a['lightbox'] ) && $href
				? '<a class="brik-pg-link block size-full" href="' . esc_url( $href ) . '" data-brik-lightbox="' . esc_attr( $group ) . '" data-caption="' . esc_attr( $name ) . '">' . $img . '</a>'
				: '<div class="brik-pg-link block size-full">' . $img . '</div>';

			/* translators: 1: image number, 2: number of images */
			$label   = sprintf( __( 'Image %1$d of %2$d', 'brik-builder' ), $i + 1, $count );
			$slides .= '<figure class="brik-pg-slide relative shrink-0 basis-full snap-center overflow-hidden bg-muted ' . esc_attr( $ratio ) . '" data-image-id="' . (int) $id . '" role="group" aria-roledescription="slide" aria-label="' . esc_attr( $label ) . '">' . $inner . '</figure>';

			if ( count( $list ) > 1 ) {
				$thumb        = $id ? wp_get_attachment_image( $id, 'woocommerce_gallery_thumbnail', false, array( 'class' => 'size-full object-cover', 'alt' => '', 'loading' => 'lazy' ) ) : '';
				$thumbs_html .= '<button type="button" class="brik-pg-thumb relative aspect-square shrink-0 overflow-hidden rounded-lg border bg-muted" data-index="' . (int) $i . '" aria-label="' . esc_attr( $label ) . '"' . ( 0 === $i ? ' aria-current="true"' : '' ) . '>' . $thumb . '</button>';
				$dots        .= '<span class="brik-pg-dot"' . ( 0 === $i ? ' aria-current="true"' : '' ) . '></span>';
			}
		}

		$badge = '';
		if ( ! empty( $a['badge'] ) ) {
			if ( ! $product->is_in_stock() ) {
				$badge = Product::badge( __( 'Sold out', 'brik-builder' ), 'secondary', 'shadow-sm' );
			} elseif ( $product->is_on_sale() ) {
				$badge = Product::badge( Product::sale_label( $product ), 'destructive', 'shadow-sm' );
			}
			$badge = $badge ? '<div class="brik-pg-badges pointer-events-none absolute top-3 left-3 z-10 flex flex-col items-start gap-1.5">' . $badge . '</div>' : '';
		}

		$many   = count( $list ) > 1;
		$arrows = '';
		if ( $many && ! empty( $a['arrows'] ) ) {
			$btn    = 'brik-pg-arrow absolute top-1/2 z-10 inline-flex size-9 -translate-y-1/2 items-center justify-center rounded-full border bg-background/90 text-foreground shadow-sm backdrop-blur transition-all hover:bg-background';
			$arrows = '<button type="button" class="' . esc_attr( $btn . ' left-3' ) . '" data-brik-pg="-1" aria-label="' . esc_attr__( 'Previous image', 'brik-builder' ) . '">' . brik_icon( 'chevron-left', 'size-4' ) . '</button>'
				. '<button type="button" class="' . esc_attr( $btn . ' right-3' ) . '" data-brik-pg="1" aria-label="' . esc_attr__( 'Next image', 'brik-builder' ) . '">' . brik_icon( 'chevron-right', 'size-4' ) . '</button>';
		}

		$stage = '<div class="brik-pg-stage relative min-w-0 overflow-hidden ' . esc_attr( $radius ) . '">'
			. '<div class="brik-pg-track flex snap-x snap-mandatory overflow-x-auto overscroll-x-contain" tabindex="0" aria-label="' . esc_attr__( 'Product images', 'brik-builder' ) . '">' . $slides . '</div>'
			. $badge . $arrows
			. ( ! empty( $a['zoom'] ) ? '<span class="brik-pg-hint pointer-events-none absolute right-3 bottom-3 z-10 hidden items-center gap-1.5 rounded-full border bg-background/90 px-2.5 py-1 text-xs font-medium text-muted-foreground shadow-sm backdrop-blur md:inline-flex">' . brik_icon( 'zoom-in', 'size-3.5' ) . esc_html__( 'Hover to zoom', 'brik-builder' ) . '</span>' : '' )
			. ( $many ? '<div class="brik-pg-dots pointer-events-none absolute inset-x-0 bottom-3 z-10 flex justify-center gap-1.5 md:hidden">' . $dots . '</div>' : '' )
			. '</div>';

		$thumbs_wrap = $many && 'none' !== $thumbs ? '<div class="brik-pg-thumbs" role="group" aria-label="' . esc_attr__( 'Choose an image', 'brik-builder' ) . '">' . $thumbs_html . '</div>' : '';

		$attrs = array(
			'class'             => brik_cls( 'brik-pg', 'brik-pg--thumbs-' . $thumbs, array( 'brik-pg--zoom' => ! empty( $a['zoom'] ) ) ),
			'data-brik-gallery' => $product->get_id(),
			'data-brik-product' => $product->get_id(),
		);
		return '<div' . brik_attrs( $attrs ) . '>' . $stage . $thumbs_wrap . '</div>';
	},
);
