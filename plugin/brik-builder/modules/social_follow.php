<?php
/**
 * Social follow: links to social profiles with brand icons.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'social_follow',
	'title'       => __( 'Social Follow', 'brik-builder' ),
	'category'    => 'basic',
	'icon'        => 'share-2',
	'description' => 'Social profile links. networks repeater [{network: brand slug (facebook|x|instagram|linkedin|youtube|tiktok|github|dribbble|discord|threads|bluesky|... |email|website|rss), url, label (optional, defaults to network name)}]. style: icon|filled|outline|labels. shape: circle|rounded|square. size: sm|default|lg. brand_colors: toggle uses each network colour. align: left|center|right. new_tab: toggle.',
	'fields'      => array_merge(
		array(
			'networks'     => Fields::field(
				'repeater',
				__( 'Networks', 'brik-builder' ),
				'content',
				array(
					'title_field' => 'network',
					'fields'      => array(
						'network' => Fields::field( 'select', __( 'Network', 'brik-builder' ), 'content', array( 'default' => 'x', 'options' => Fields::opts( brik_brand_options() ) ) ),
						'url'     => Fields::field( 'text', __( 'URL', 'brik-builder' ), 'content', array( 'placeholder' => 'https://' ) ),
						'label'   => Fields::field( 'text', __( 'Label', 'brik-builder' ), 'content', array( 'description' => __( 'Defaults to the network name.', 'brik-builder' ) ) ),
					),
					'default'     => array(
						array( 'network' => 'x', 'url' => '#' ),
						array( 'network' => 'instagram', 'url' => '#' ),
						array( 'network' => 'linkedin', 'url' => '#' ),
						array( 'network' => 'youtube', 'url' => '#' ),
						array( 'network' => 'github', 'url' => '#' ),
					),
				)
			),
			'style'        => Fields::field( 'select', __( 'Style', 'brik-builder' ), 'content', array( 'default' => 'outline', 'options' => Fields::opts( array( 'icon' => __( 'Icon only', 'brik-builder' ), 'filled' => __( 'Filled', 'brik-builder' ), 'outline' => __( 'Outline', 'brik-builder' ), 'labels' => __( 'With labels', 'brik-builder' ) ) ) ) ),
			'shape'        => Fields::field( 'select', __( 'Shape', 'brik-builder' ), 'content', array( 'default' => 'circle', 'show_if' => array( 'style' => array( 'filled', 'outline', 'labels' ) ), 'options' => Fields::opts( array( 'circle' => __( 'Circle', 'brik-builder' ), 'rounded' => __( 'Rounded', 'brik-builder' ), 'square' => __( 'Square', 'brik-builder' ) ) ) ) ),
			'size'         => Fields::field( 'select', __( 'Size', 'brik-builder' ), 'content', array( 'default' => 'default', 'options' => Fields::opts( array( 'sm' => __( 'Small', 'brik-builder' ), 'default' => __( 'Default', 'brik-builder' ), 'lg' => __( 'Large', 'brik-builder' ) ) ) ) ),
			'brand_colors' => Fields::field( 'toggle', __( 'Brand colors', 'brik-builder' ), 'content' ),
			'new_tab'      => Fields::field( 'toggle', __( 'Open in new tab', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'align'        => Fields::field(
				'align',
				__( 'Alignment', 'brik-builder' ),
				'content',
				array(
					'responsive' => true,
					'css'        => array(
						'selector' => Fields::WRAP . ' .brik-social-list',
						'map'      => array(
							'left'   => 'justify-content:flex-start',
							'center' => 'justify-content:center',
							'right'  => 'justify-content:flex-end',
						),
					),
				)
			),
			'gap'          => Fields::field( 'unit', __( 'Gap', 'brik-builder' ), 'item', array( 'tab' => 'design', 'group_label' => __( 'Icons', 'brik-builder' ), 'responsive' => true, 'css' => array( Fields::WRAP . ' .brik-social-list', 'gap' ) ) ),
			'icon_px'      => Fields::field( 'unit', __( 'Icon size', 'brik-builder' ), 'item', array( 'tab' => 'design', 'group_label' => __( 'Icons', 'brik-builder' ), 'responsive' => true, 'css' => array( Fields::WRAP . ' .brik-social svg', array( 'width', 'height' ) ) ) ),
		),
		Fields::box( 'item', __( 'Icons', 'brik-builder' ), Fields::WRAP . ' .brik-social' ),
		Fields::typography( 'label', __( 'Labels', 'brik-builder' ), Fields::WRAP . ' .brik-social-label' )
	),
	'render'      => static function ( $a, $ctx ) {
		$items = brik_items( $a['networks'] );
		if ( ! $items ) {
			return $ctx->placeholder( __( 'Add social networks', 'brik-builder' ) );
		}
		$style  = in_array( $a['style'], array( 'icon', 'filled', 'outline', 'labels' ), true ) ? $a['style'] : 'outline';
		$brand  = ! empty( $a['brand_colors'] );
		$shapes = array(
			'circle'  => 'rounded-full',
			'rounded' => 'rounded-md',
			'square'  => 'rounded-none',
		);
		$shape  = 'icon' === $style ? 'rounded-md' : ( isset( $shapes[ $a['shape'] ] ) ? $shapes[ $a['shape'] ] : $shapes['circle'] );
		$sizes  = array(
			'sm'      => array( 'size-8', 'size-3.5', 'h-8 px-3 text-xs' ),
			'default' => array( 'size-10', 'size-4', 'h-9 px-4 text-sm' ),
			'lg'      => array( 'size-12', 'size-5', 'h-11 px-5 text-base' ),
		);
		$size   = isset( $sizes[ $a['size'] ] ) ? $sizes[ $a['size'] ] : $sizes['default'];

		$looks = array(
			'icon'    => $brand ? 'text-[var(--brik-brand)] hover:opacity-80' : 'text-muted-foreground hover:text-foreground',
			'filled'  => $brand ? 'bg-[var(--brik-brand)] text-white hover:opacity-90' : 'bg-primary text-primary-foreground hover:bg-primary/90',
			'outline' => $brand ? 'border bg-background text-[var(--brik-brand)] shadow-xs hover:border-transparent hover:bg-[var(--brik-brand)] hover:text-white' : 'border bg-background text-foreground shadow-xs hover:bg-accent hover:text-accent-foreground',
			'labels'  => 'border bg-background font-medium text-foreground shadow-xs hover:bg-accent hover:text-accent-foreground',
		);

		$html = '';
		foreach ( $items as $item ) {
			$slug = brik_item( $item, 'network', 'website' );
			$info = brik_brand( $slug );
			$url  = brik_item( $item, 'url', '#' );
			if ( 'email' === $slug && false === strpos( $url, ':' ) && is_email( $url ) ) {
				$url = 'mailto:' . $url;
			}
			$label = brik_item( $item, 'label', $info['title'] );
			$icon  = brik_icon( $info['icon'], 'labels' === $style && $brand ? $size[1] . ' text-[var(--brik-brand)]' : $size[1] );

			$attrs = array(
				'href'  => $url,
				'class' => brik_cls( 'brik-social inline-flex shrink-0 items-center justify-center transition-all outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50', $shape, $looks[ $style ], 'labels' === $style ? 'gap-2 ' . $size[2] : $size[0] ),
				'style' => '--brik-brand:' . $info['hex'],
			);
			if ( ! empty( $a['new_tab'] ) && 'email' !== $slug ) {
				$attrs['target'] = '_blank';
				$attrs['rel']    = 'noopener';
			}
			if ( 'labels' === $style ) {
				$body = $icon . '<span class="brik-social-label">' . esc_html( wp_strip_all_tags( (string) $label ) ) . '</span>';
			} else {
				$attrs['aria-label'] = wp_strip_all_tags( (string) $label );
				$body                = $icon;
			}
			$html .= '<li><a' . brik_attrs( $attrs ) . '>' . $body . '</a></li>';
		}
		return '<ul class="' . esc_attr( brik_cls( 'brik-social-list flex flex-wrap items-center', 'icon' === $style ? 'gap-1' : 'gap-2' ) ) . '">' . $html . '</ul>';
	},
);
