<?php
/**
 * Bento grid: feature tiles of different sizes.
 *
 * @package Brik
 * @author  Diluk Angelo
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'bento_grid',
	'title'       => __( 'Bento Grid', 'brik-builder' ),
	'category'    => 'effects',
	'icon'        => 'layout-dashboard',
	'description' => 'Grid of feature tiles with different sizes. items: repeater [{title, description, icon, image, link, link_text, col_span: 1|2|3, row_span: 1|2, bg (color), bg_gradient (CSS gradient)}]. Spans are clamped to the column count; on mobile every tile is full width. columns: 1-4 (responsive, default 3). row_height: min tile height (CSS length). gap. hover: spotlight|lift|both|none. animate: bool, staggered entrance; tile_animation: fade|slide-up|zoom|blur; stagger: ms between tiles. pattern: bool, faint dot pattern on tiles without an image.',
	'fields'      => array_merge(
		array(
			'items'      => Fields::field(
				'repeater',
				__( 'Tiles', 'brik-builder' ),
				'content',
				array(
					'title_field' => 'title',
					'fields'      => array(
						'title'       => Fields::field( 'text', __( 'Title', 'brik-builder' ) ),
						'description' => Fields::field( 'textarea', __( 'Description', 'brik-builder' ) ),
						'icon'        => Fields::field( 'icon', __( 'Icon', 'brik-builder' ) ),
						'image'       => Fields::field( 'image', __( 'Image', 'brik-builder' ) ),
						'link'        => Fields::field( 'link', __( 'Link', 'brik-builder' ) ),
						'link_text'   => Fields::field( 'text', __( 'Link text', 'brik-builder' ), 'content', array( 'placeholder' => __( 'Learn more', 'brik-builder' ) ) ),
						'col_span'    => Fields::field( 'select', __( 'Width (columns)', 'brik-builder' ), 'content', array( 'default' => '1', 'options' => Fields::opts( array( '1' => '1', '2' => '2', '3' => '3' ) ) ) ),
						'row_span'    => Fields::field( 'select', __( 'Height (rows)', 'brik-builder' ), 'content', array( 'default' => '1', 'options' => Fields::opts( array( '1' => '1', '2' => '2' ) ) ) ),
						'bg'          => Fields::field( 'color', __( 'Background', 'brik-builder' ) ),
						'bg_gradient' => Fields::field( 'gradient', __( 'Background gradient', 'brik-builder' ) ),
					),
					'default'     => array(
						array(
							'title'       => __( 'Visual builder', 'brik-builder' ),
							'description' => __( 'Drag, drop and edit text in place. Every change is live.', 'brik-builder' ),
							'icon'        => 'mouse-pointer-click',
							'col_span'    => '2',
							'row_span'    => '1',
							'link'        => array( 'url' => '#' ),
							'bg_gradient' => 'radial-gradient(120% 120% at 100% 0%, rgb(99 102 241 / 0.22), transparent 55%)',
						),
						array(
							'title'       => __( 'Design system', 'brik-builder' ),
							'description' => __( 'Tokens for color, type and radius keep every page on brand.', 'brik-builder' ),
							'icon'        => 'palette',
							'col_span'    => '1',
							'row_span'    => '2',
							'link'        => array( 'url' => '#' ),
							'bg_gradient' => 'radial-gradient(120% 80% at 50% 0%, rgb(236 72 153 / 0.2), transparent 60%)',
						),
						array(
							'title'       => __( 'Theme builder', 'brik-builder' ),
							'description' => __( 'Headers, footers and templates, all with the same tools.', 'brik-builder' ),
							'icon'        => 'layout-template',
							'col_span'    => '1',
							'row_span'    => '1',
							'link'        => array( 'url' => '#' ),
						),
						array(
							'title'       => __( 'Fast by default', 'brik-builder' ),
							'description' => __( 'Server-rendered markup with tiny, cached stylesheets.', 'brik-builder' ),
							'icon'        => 'gauge',
							'col_span'    => '1',
							'row_span'    => '1',
							'link'        => array( 'url' => '#' ),
							'bg_gradient' => 'radial-gradient(100% 100% at 0% 100%, rgb(16 185 129 / 0.18), transparent 60%)',
						),
						array(
							'title'       => __( 'Works with your tools', 'brik-builder' ),
							'description' => __( 'A built-in MCP server lets your favourite tools build and edit pages.', 'brik-builder' ),
							'icon'        => 'plug',
							'col_span'    => '3',
							'row_span'    => '1',
							'link'        => array( 'url' => '#' ),
							'bg_gradient' => 'linear-gradient(90deg, rgb(99 102 241 / 0.12), rgb(168 85 247 / 0.08), rgb(236 72 153 / 0.12))',
						),
					),
				)
			),
			'columns'    => Fields::field( 'range', __( 'Columns', 'brik-builder' ), 'content', array( 'default' => 3, 'min' => 1, 'max' => 4, 'step' => 1, 'responsive' => true ) ),
			'row_height' => Fields::field( 'unit', __( 'Row height', 'brik-builder' ), 'content', array( 'default' => '14rem', 'responsive' => true, 'css' => array( Fields::WRAP . ' .brik-bento', '--brik-bento-row' ) ) ),
			'gap'        => Fields::field( 'unit', __( 'Gap', 'brik-builder' ), 'content', array( 'default' => '1rem', 'responsive' => true, 'css' => array( Fields::WRAP . ' .brik-bento', 'gap' ) ) ),
			'hover'      => Fields::field( 'select', __( 'Hover effect', 'brik-builder' ), 'content', array( 'default' => 'both', 'options' => Fields::opts( array( 'both' => __( 'Spotlight and lift', 'brik-builder' ), 'spotlight' => __( 'Spotlight', 'brik-builder' ), 'lift' => __( 'Lift', 'brik-builder' ), 'none' => __( 'None', 'brik-builder' ) ) ) ) ),
			'pattern'    => Fields::field( 'toggle', __( 'Dot pattern', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'animate'    => Fields::field( 'toggle', __( 'Animate tiles in', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'tile_animation' => Fields::field( 'select', __( 'Tile animation', 'brik-builder' ), 'content', array( 'default' => 'slide-up', 'options' => Fields::opts( array( 'fade' => __( 'Fade in', 'brik-builder' ), 'slide-up' => __( 'Slide up', 'brik-builder' ), 'zoom' => __( 'Zoom in', 'brik-builder' ), 'blur' => __( 'Blur in', 'brik-builder' ) ) ), 'show_if' => array( 'animate' => true ) ) ),
			'stagger'    => Fields::field( 'range', __( 'Stagger (ms)', 'brik-builder' ), 'content', array( 'default' => 90, 'min' => 0, 'max' => 400, 'step' => 10, 'show_if' => array( 'animate' => true ) ) ),
			'title_tag'  => Fields::field( 'select', __( 'Title tag', 'brik-builder' ), 'content', array( 'default' => 'h3', 'options' => Fields::opts( array( 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'div' => 'div' ) ) ) ),
		),
		Fields::box( 'tile', __( 'Tile', 'brik-builder' ), Fields::WRAP . ' .brik-bento-item' ),
		Fields::typography( 'title', __( 'Title', 'brik-builder' ), Fields::WRAP . ' .brik-bento-title' ),
		Fields::typography( 'body', __( 'Description', 'brik-builder' ), Fields::WRAP . ' .brik-bento-desc' )
	),
	'css'         => static function ( $a, $wrap ) {
		$cols = array(
			'desktop' => (int) brik_fx_num( $a['columns'], 3, 1, 4 ),
		);
		$cols['tablet'] = isset( $a['columns@tablet'] ) && '' !== $a['columns@tablet'] ? (int) brik_fx_num( $a['columns@tablet'], 2, 1, 4 ) : min( $cols['desktop'], 2 );
		$cols['mobile'] = isset( $a['columns@mobile'] ) && '' !== $a['columns@mobile'] ? (int) brik_fx_num( $a['columns@mobile'], 1, 1, 4 ) : 1;

		$rules = array(
			'desktop' => '',
			'tablet'  => '',
			'mobile'  => '',
		);
		foreach ( $rules as $state => $unused ) {
			$rules[ $state ] .= $wrap . ' .brik-bento{grid-template-columns:repeat(' . $cols[ $state ] . ',minmax(0,1fr))}';
		}
		foreach ( brik_items( $a['items'] ) as $i => $item ) {
			$col = (int) brik_fx_num( brik_item( $item, 'col_span', 1 ), 1, 1, 3 );
			$row = (int) brik_fx_num( brik_item( $item, 'row_span', 1 ), 1, 1, 2 );
			$sel = $wrap . ' .brik-bento-item:nth-child(' . ( $i + 1 ) . ')';
			foreach ( $cols as $state => $n ) {
				$c = min( $col, $n );
				$r = 1 === $n ? 1 : $row;
				if ( $c > 1 || $r > 1 ) {
					$rules[ $state ] .= $sel . '{grid-column:span ' . $c . ';grid-row:span ' . $r . '}';
				} elseif ( 'desktop' !== $state ) {
					$rules[ $state ] .= $sel . '{grid-column:span 1;grid-row:span 1}';
				}
			}
		}
		return $rules['desktop']
			. '@media (max-width:' . Brik\Style::TABLET . 'px){' . $rules['tablet'] . '}'
			. '@media (max-width:' . Brik\Style::MOBILE . 'px){' . $rules['mobile'] . '}';
	},
	'render'      => static function ( $a, $ctx ) {
		$items = brik_items( $a['items'] );
		if ( ! $items ) {
			return $ctx->placeholder( __( 'Add tiles', 'brik-builder' ) );
		}
		$hover   = in_array( $a['hover'], array( 'both', 'spotlight', 'lift', 'none' ), true ) ? $a['hover'] : 'both';
		$spot    = in_array( $hover, array( 'both', 'spotlight' ), true );
		$tag     = brik_fx_tag( $a['title_tag'], array( 'h2', 'h3', 'h4', 'div' ), 'h3' );
		$anim    = ! empty( $a['animate'] ) && in_array( $a['tile_animation'], array( 'fade', 'slide-up', 'zoom', 'blur' ), true ) ? $a['tile_animation'] : ( ! empty( $a['animate'] ) ? 'slide-up' : '' );
		$stagger = (int) brik_fx_num( $a['stagger'], 90, 0, 400 );

		$tiles = '';
		foreach ( $items as $i => $item ) {
			$has_link = brik_has_link( brik_item( $item, 'link' ) );
			$image    = brik_item( $item, 'image' );
			$deco     = '';
			if ( brik_has_image( $image ) ) {
				$deco = '<div class="brik-bento-media absolute inset-x-0 top-0 h-[70%] overflow-hidden" aria-hidden="true">' . brik_image( $image, 'large', array( 'class' => 'size-full object-cover transition-transform duration-700 group-hover/bento:scale-105', 'alt' => '' ) ) . '</div>';
			} elseif ( ! empty( $a['pattern'] ) ) {
				$deco = '<div class="brik-bento-pattern" aria-hidden="true"></div>';
				$icon = brik_item( $item, 'icon' );
				if ( $icon ) {
					$deco .= '<div class="brik-bento-ghost" aria-hidden="true">' . brik_icon( $icon, 'size-full' ) . '</div>';
				}
			}

			$body = '';
			if ( brik_item( $item, 'icon' ) ) {
				$body .= brik_icon( $item['icon'], 'brik-bento-icon mb-2 size-10 origin-left text-foreground/80 transition-transform duration-300 group-hover/bento:scale-90' );
			}
			if ( '' !== trim( (string) brik_item( $item, 'title' ) ) ) {
				$body .= '<' . $tag . ' class="brik-bento-title font-heading text-lg leading-snug font-semibold tracking-tight">' . brik_inline( $item['title'] ) . '</' . $tag . '>';
			}
			if ( '' !== trim( (string) brik_item( $item, 'description' ) ) ) {
				$body .= '<p class="brik-bento-desc max-w-lg text-sm leading-relaxed text-muted-foreground">' . brik_inline( $item['description'] ) . '</p>';
			}
			$cta = '';
			if ( $has_link ) {
				$stretch = $ctx->canvas ? '' : 'after:absolute after:inset-0 after:content-[\'\']';
				$cta     = '<div class="brik-bento-cta relative z-[3] mt-3"><a' . brik_link_attrs( $item['link'], array( 'class' => brik_cls( 'inline-flex items-center gap-1 text-sm font-medium text-foreground no-underline underline-offset-4 hover:underline', $stretch ) ) ) . '>' . brik_inline( brik_item( $item, 'link_text', __( 'Learn more', 'brik-builder' ) ) ) . brik_icon( 'arrow-right', 'size-4' ) . '</a></div>';
			}

			$style = brik_fx_vars(
				array(
					'--brik-bento-bg'   => brik_item( $item, 'bg' ),
					'--brik-bento-grad' => brik_item( $item, 'bg_gradient' ),
					'--brik-anim-delay' => $anim ? ( $i * $stagger ) . 'ms' : '',
				)
			);
			$attrs = array(
				'class'          => 'brik-bento-item group/bento relative isolate flex min-h-[var(--brik-bento-row,14rem)] flex-col justify-end overflow-hidden rounded-xl border bg-card text-card-foreground shadow-sm',
				'style'          => '' !== $style ? $style : null,
				'data-brik-anim' => $anim ? $anim : null,
			);
			$tiles .= '<div' . brik_attrs( $attrs ) . '>' . $deco . ( $spot ? '<div class="brik-bento-spot" aria-hidden="true"></div>' : '' )
				. '<div class="brik-bento-content relative z-[2] flex flex-col gap-1 p-6">' . $body . $cta . '</div></div>';
		}

		if ( $spot ) {
			$ctx->script( 'effect-card' );
		}
		$class = brik_cls(
			'brik-bento grid auto-rows-[minmax(var(--brik-bento-row,14rem),auto)] gap-4',
			array(
				'brik-bento--spotlight' => $spot,
				'brik-bento--lift'      => in_array( $hover, array( 'both', 'lift' ), true ),
			)
		);
		return '<div class="' . esc_attr( $class ) . '">' . $tiles . '</div>';
	},
);
