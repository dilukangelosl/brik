<?php
/**
 * Hero parallax: rows of product shots that tilt flat and slide sideways as the page scrolls,
 * under a large headline.
 *
 * @package Brik
 * @author  Diluk Angelo
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

$brik_hp_default = array();
foreach (
	array(
		array( '1460925895917-afdab827c52f', __( 'Analytics', 'brik-builder' ) ),
		array( '1498050108023-c5249f4df085', __( 'Developer tools', 'brik-builder' ) ),
		array( '1517694712202-14dd9538aa97', __( 'Code editor', 'brik-builder' ) ),
		array( '1499951360447-b19be8fe80f5', __( 'Studio desk', 'brik-builder' ) ),
		array( '1496181133206-80ce9b88a853', __( 'Minimal workspace', 'brik-builder' ) ),
		array( '1541807084-5c52b6b3adef', __( 'Notebook', 'brik-builder' ) ),
		array( '1467232004584-a241de8bcf5d', __( 'Night shift', 'brik-builder' ) ),
		array( '1531297484001-80022131f5a1', __( 'Midnight', 'brik-builder' ) ),
		array( '1558655146-d09347e92766', __( 'Design system', 'brik-builder' ) ),
		array( '1559028012-481c04fa702d', __( 'Interface kit', 'brik-builder' ) ),
		array( '1586717791821-3f44a563fa4c', __( 'Sketchbook', 'brik-builder' ) ),
		array( '1593642632559-0c6d3fc62b89', __( 'Bright office', 'brik-builder' ) ),
		array( '1550745165-9bc0b252726f', __( 'Retro lab', 'brik-builder' ) ),
		array( '1525547719571-a2d4ac8945e2', __( 'Glow', 'brik-builder' ) ),
		array( '1555066931-4365d14bab8c', __( 'Source', 'brik-builder' ) ),
	) as $brik_item
) {
	$brik_hp_default[] = array(
		'image' => array( 'url' => brik_sample_image( $brik_item[0], 900, 700 ), 'alt' => '' ),
		'title' => $brik_item[1],
		'link'  => array( 'url' => '#' ),
	);
}

return array(
	'type'        => 'hero_parallax',
	'title'       => __( 'Hero Parallax', 'brik-builder' ),
	'category'    => 'effects',
	'icon'        => 'gallery-horizontal-end',
	'description' => 'Scroll scene: big headline above three rows of product cards that start tilted in 3D, settle flat and slide sideways in opposite directions as you scroll. eyebrow, title (inline HTML), text, title_tag, button_text, link. items: repeater of {image, title, link}, spread over 3 rows (about 15 works best). card_width: CSS length (responsive). card_ratio: 4:3|16:10|1:1|3:4. distance: sideways travel in px. tilt / twist: start angles in degrees. Place in a full-width section with no side padding. Static with reduced motion and in the builder.',
	'fields'      => array_merge(
		array(
			'eyebrow'    => Fields::field( 'text', __( 'Eyebrow', 'brik-builder' ), 'content', array( 'inline' => true ) ),
			'title'      => Fields::field( 'text', __( 'Title', 'brik-builder' ), 'content', array( 'default' => __( 'The ultimate<br>product studio', 'brik-builder' ), 'inline' => true ) ),
			'title_tag'  => Fields::field( 'select', __( 'Title tag', 'brik-builder' ), 'content', array( 'default' => 'h1', 'options' => Fields::opts( array( 'h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'p' => 'p' ) ) ) ),
			'text'       => Fields::field( 'textarea', __( 'Text', 'brik-builder' ), 'content', array( 'default' => __( 'We build beautiful products with the latest tools. A small team of developers and designers who love to ship.', 'brik-builder' ) ) ),
			'button_text' => Fields::field( 'text', __( 'Button text', 'brik-builder' ), 'content' ),
			'link'       => Fields::field( 'link', __( 'Button link', 'brik-builder' ), 'content', array( 'show_if' => array( 'button_text' => '!' ) ) ),
			'items'      => Fields::field(
				'repeater',
				__( 'Cards', 'brik-builder' ),
				'content',
				array(
					'title_field' => 'title',
					'default'     => $brik_hp_default,
					'fields'      => array(
						'image' => Fields::field( 'image', __( 'Image', 'brik-builder' ), 'content' ),
						'title' => Fields::field( 'text', __( 'Title', 'brik-builder' ), 'content' ),
						'link'  => Fields::field( 'link', __( 'Link', 'brik-builder' ), 'content' ),
					),
				)
			),
			'distance'   => Fields::field( 'range', __( 'Sideways travel', 'brik-builder' ), 'scroll', array( 'group_label' => __( 'Scroll effect', 'brik-builder' ), 'default' => 900, 'min' => 0, 'max' => 2000, 'step' => 50, 'unit' => 'px' ) ),
			'tilt'       => Fields::field( 'range', __( 'Start tilt', 'brik-builder' ), 'scroll', array( 'group_label' => __( 'Scroll effect', 'brik-builder' ), 'default' => 15, 'min' => 0, 'max' => 40, 'unit' => 'deg' ) ),
			'twist'      => Fields::field( 'range', __( 'Start twist', 'brik-builder' ), 'scroll', array( 'group_label' => __( 'Scroll effect', 'brik-builder' ), 'default' => 20, 'min' => 0, 'max' => 40, 'unit' => 'deg' ) ),
			'card_width' => Fields::field( 'unit', __( 'Card width', 'brik-builder' ), 'card_style', array( 'tab' => 'design', 'group_label' => __( 'Cards', 'brik-builder' ), 'default' => '28rem', 'responsive' => true, 'css' => array( Fields::WRAP . ' .brik-hpx', '--brik-hpx-w' ) ) ),
			'card_ratio' => Fields::field( 'select', __( 'Card ratio', 'brik-builder' ), 'card_style', array( 'tab' => 'design', 'group_label' => __( 'Cards', 'brik-builder' ), 'default' => '4:3', 'options' => Fields::opts( array( '4:3' => '4:3', '16:10' => '16:10', '1:1' => '1:1', '3:4' => '3:4' ) ) ) ),
			'rounded'    => Fields::field( 'select', __( 'Rounded corners', 'brik-builder' ), 'card_style', array( 'tab' => 'design', 'group_label' => __( 'Cards', 'brik-builder' ), 'default' => 'xl', 'options' => Fields::opts( brik_radius_options() ) ) ),
		),
		Fields::typography( 'eyebrow', __( 'Eyebrow', 'brik-builder' ), Fields::WRAP . ' .brik-hpx-eyebrow' ),
		Fields::typography( 'title', __( 'Title', 'brik-builder' ), Fields::WRAP . ' .brik-hpx-title' ),
		Fields::typography( 'text', __( 'Text', 'brik-builder' ), Fields::WRAP . ' .brik-hpx-text' ),
		Fields::typography( 'card_title', __( 'Card title', 'brik-builder' ), Fields::WRAP . ' .brik-hpx-card-title' )
	),
	'render'      => static function ( $a, $ctx ) {
		$ctx->script( 'hero-parallax' );

		$items = brik_3d_rows( $a['items'] );
		if ( ! $items && ! $ctx->canvas && '' === (string) $a['title'] ) {
			return '';
		}

		$tag  = in_array( $a['title_tag'], array( 'h1', 'h2', 'h3', 'p' ), true ) ? $a['title_tag'] : 'h1';
		$head = '';
		if ( '' !== (string) $a['eyebrow'] ) {
			$head .= '<p class="brik-hpx-eyebrow mb-4 text-sm font-semibold tracking-wider text-muted-foreground uppercase"' . $ctx->inline( 'eyebrow' ) . '>' . brik_inline( $a['eyebrow'] ) . '</p>';
		}
		if ( '' !== (string) $a['title'] ) {
			$head .= sprintf( '<%1$s class="brik-hpx-title font-heading text-5xl leading-[1.02] font-bold tracking-tight md:text-7xl"%3$s>%2$s</%1$s>', $tag, brik_inline( $a['title'] ), $ctx->inline( 'title' ) );
		}
		if ( '' !== (string) $a['text'] ) {
			$head .= '<p class="brik-hpx-text mt-6 max-w-2xl text-base text-muted-foreground md:text-xl">' . esc_html( $a['text'] ) . '</p>';
		}
		if ( '' !== (string) $a['button_text'] && ! empty( $a['link']['url'] ) ) {
			$head .= '<div class="mt-8"><a' . brik_link_attrs( $a['link'], array( 'class' => brik_button_class( 'default', 'lg' ) ) ) . '>' . brik_inline( $a['button_text'] ) . '</a></div>';
		}

		$ratio  = brik_3d_ratio_class( $a['card_ratio'] );
		$radius = brik_radius_class( $a['rounded'] );
		$rows   = array( array(), array(), array() );
		$count  = count( $items );
		foreach ( $items as $i => $item ) {
			$item = array_merge( array( 'image' => '', 'title' => '', 'link' => '' ), $item );
			$img  = brik_image( $item['image'], 'large', array( 'class' => 'brik-hpx-img absolute inset-0 h-full w-full object-cover', 'alt' => wp_strip_all_tags( $item['title'] ) ) );
			$body = ( $img ? $img : '<span class="absolute inset-0 bg-muted"></span>' )
				. '<span class="brik-hpx-shade pointer-events-none absolute inset-0 bg-linear-to-t from-black/70 via-black/0 to-black/0 opacity-0 transition-opacity duration-300 group-hover:opacity-100 group-focus-visible:opacity-100"></span>'
				. ( '' !== (string) $item['title'] ? '<span class="brik-hpx-card-title pointer-events-none absolute bottom-4 left-4 translate-y-1 text-base font-semibold text-white opacity-0 transition-all duration-300 group-hover:translate-y-0 group-hover:opacity-100 group-focus-visible:translate-y-0 group-focus-visible:opacity-100">' . brik_inline( $item['title'] ) . '</span>' : '' );
			$cls  = brik_cls( 'brik-hpx-card group relative block shrink-0 overflow-hidden bg-muted shadow-xl outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50', $ratio, $radius );
			$card = ! empty( $item['link']['url'] )
				? '<a' . brik_link_attrs( $item['link'], array( 'class' => $cls ) ) . '>' . $body . '</a>'
				: '<div class="' . esc_attr( $cls ) . '">' . $body . '</div>';
			// Fill the rows evenly in reading order, five per row for fifteen cards.
			$rows[ min( 2, (int) floor( $i * 3 / max( 1, $count ) ) ) ][] = '<li class="brik-hpx-item">' . $card . '</li>';
		}

		$rows_html = '';
		foreach ( $rows as $n => $cells ) {
			if ( ! $cells ) {
				continue;
			}
			$rows_html .= '<ul class="brik-hpx-row brik-hpx-row--' . ( 1 === $n ? 'b' : 'a' ) . '">' . implode( '', $cells ) . '</ul>';
		}

		return sprintf(
			'<div class="brik-hpx"%1$s>%2$s<div class="brik-hpx-scene"><div class="brik-hpx-plane">%3$s</div></div></div>',
			brik_3d_vars(
				array(
					'--brik-hpx-dist'  => (string) brik_3d_num( $a['distance'], 900, 0, 2000 ),
					'--brik-hpx-tilt'  => (string) brik_3d_num( $a['tilt'], 15, 0, 40 ),
					'--brik-hpx-twist' => (string) brik_3d_num( $a['twist'], 20, 0, 40 ),
				)
			),
			'' !== $head ? '<div class="brik-hpx-head relative z-10">' . $head . '</div>' : '',
			$rows_html
		);
	},
);
