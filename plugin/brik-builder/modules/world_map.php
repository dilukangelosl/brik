<?php
/**
 * World map: dotted SVG map with animated arcs and pulsing markers. The dots come from the same
 * land mask as the globe, rendered as a dot pattern clipped to the land shape.
 *
 * @package Brik
 * @author  Diluk Angelo
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'world_map',
	'title'       => __( 'World Map', 'brik-builder' ),
	'category'    => 'effects',
	'icon'        => 'map',
	'description' => 'Dotted world map (SVG) with animated arcs and pulsing markers. routes: repeater of {from_label, from_lat, from_lng, to_label, to_lat, to_lng, color}. dot_color (default faded text color). arc_color / arc_color_2: arc gradient. labels: bool, city labels (hidden on phones). animate: bool, arcs draw in one after another. duration: seconds per arc. label: accessible description. Works without JavaScript; arcs show fully drawn with reduced motion.',
	'fields'      => array(
		'routes'      => Fields::field(
			'repeater',
			__( 'Connections', 'brik-builder' ),
			'content',
			array(
				'title_field' => 'to_label',
				'default'     => brik_3d_default_routes( array( array( 'sfo', 'nyc' ), array( 'nyc', 'lon' ), array( 'lon', 'nbo' ), array( 'lon', 'dxb' ), array( 'dxb', 'bom' ), array( 'bom', 'sin' ), array( 'sin', 'tyo' ), array( 'sin', 'syd' ), array( 'nyc', 'sao' ), array( 'sao', 'los' ) ) ),
				'fields'      => brik_3d_route_fields(),
			)
		),
		'labels'      => Fields::field( 'toggle', __( 'City labels', 'brik-builder' ), 'content' ),
		'animate'     => Fields::field( 'toggle', __( 'Animate arcs', 'brik-builder' ), 'content', array( 'default' => true ) ),
		'duration'    => Fields::field( 'range', __( 'Seconds per arc', 'brik-builder' ), 'content', array( 'default' => 2.5, 'min' => 1, 'max' => 8, 'step' => 0.5, 'show_if' => array( 'animate' => '!' ) ) ),
		'label'       => Fields::field( 'text', __( 'Accessible description', 'brik-builder' ), 'content', array( 'default' => __( 'World map with connections between cities', 'brik-builder' ) ) ),
		'dot_color'   => Fields::field( 'color', __( 'Dot color', 'brik-builder' ), 'map_style', array( 'tab' => 'design', 'group_label' => __( 'Map', 'brik-builder' ), 'css' => array( Fields::WRAP . ' .brik-wm', '--brik-wm-dot' ) ) ),
		'arc_color'   => Fields::field( 'color', __( 'Arc color', 'brik-builder' ), 'map_style', array( 'tab' => 'design', 'group_label' => __( 'Map', 'brik-builder' ), 'default' => '#0ea5e9' ) ),
		'arc_color_2' => Fields::field( 'color', __( 'Arc end color', 'brik-builder' ), 'map_style', array( 'tab' => 'design', 'group_label' => __( 'Map', 'brik-builder' ), 'default' => '#8b5cf6' ) ),
	),
	'render'      => static function ( $a, $ctx ) {
		$ctx->script( 'world-map' );

		$uid    = $ctx->uid( 'wm' );
		$c1     = brik_3d_color( $a['arc_color'] ) ? brik_3d_color( $a['arc_color'] ) : '#0ea5e9';
		$c2     = brik_3d_color( $a['arc_color_2'] ) ? brik_3d_color( $a['arc_color_2'] ) : '#8b5cf6';
		$dur    = brik_3d_num( $a['duration'], 2.5, 1, 8 );
		$routes = brik_3d_routes( $a['routes'] );

		$xy = static function ( $p ) {
			return array( round( $p[1] + 180, 2 ), round( 90 - $p[0], 2 ) );
		};

		$defs    = '<pattern id="' . esc_attr( $uid ) . '-dots" width="2" height="2" patternUnits="userSpaceOnUse"><circle cx="1" cy="1" r="0.6" fill="currentColor"/></pattern>';
		$arcs    = '';
		$markers = array();
		foreach ( $routes as $i => $route ) {
			list( $x1, $y1 ) = $xy( $route['from'] );
			list( $x2, $y2 ) = $xy( $route['to'] );
			$dist = sqrt( ( $x2 - $x1 ) ** 2 + ( $y2 - $y1 ) ** 2 );
			if ( $dist < 0.5 ) {
				continue;
			}
			// Control point above the midpoint; longer hops arc higher.
			$cx    = round( ( $x1 + $x2 ) / 2, 2 );
			$cy    = round( min( $y1, $y2 ) - $dist * 0.3, 2 );
			$from  = $route['color'] ? $route['color'] : $c1;
			$to    = $route['color'] ? $route['color'] : $c2;
			$grad  = $uid . '-g' . $i;
			$defs .= sprintf(
				'<linearGradient id="%1$s" gradientUnits="userSpaceOnUse" x1="%2$s" y1="%3$s" x2="%4$s" y2="%5$s"><stop offset="0" stop-color="%6$s" stop-opacity="0"/><stop offset="0.25" stop-color="%6$s"/><stop offset="1" stop-color="%7$s"/></linearGradient>',
				esc_attr( $grad ),
				$x1,
				$y1,
				$x2,
				$y2,
				esc_attr( $from ),
				esc_attr( $to )
			);
			$arcs .= sprintf(
				'<path class="brik-wm-arc" d="M%1$s %2$s Q%3$s %4$s %5$s %6$s" pathLength="1" stroke="url(#%7$s)" style="--i:%8$d"/>',
				$x1,
				$y1,
				$cx,
				$cy,
				$x2,
				$y2,
				esc_attr( $grad ),
				(int) $i
			);
			$markers[ $x1 . ',' . $y1 ] = array( $x1, $y1, $from, $route['from_label'], $i );
			$markers[ $x2 . ',' . $y2 ] = array( $x2, $y2, $to, $route['to_label'], $i );
		}

		$dots   = '';
		$labels = '';
		foreach ( array_values( $markers ) as $n => $m ) {
			$dots .= sprintf(
				'<g class="brik-wm-marker" style="--i:%4$d;color:%3$s"><circle class="brik-wm-ping" cx="%1$s" cy="%2$s" r="1.4" fill="currentColor"/><circle cx="%1$s" cy="%2$s" r="0.85" fill="currentColor"/></g>',
				$m[0],
				$m[1],
				esc_attr( $m[2] ),
				(int) $n
			);
			if ( ! empty( $a['labels'] ) && '' !== $m[3] ) {
				$labels .= sprintf(
					'<span class="brik-wm-label max-md:hidden" style="left:%1$s%%;top:%2$s%%">%3$s</span>',
					esc_attr( round( $m[0] / 360 * 100, 3 ) ),
					esc_attr( round( ( $m[1] - 6 ) / 142 * 100, 3 ) ),
					esc_html( $m[3] )
				);
			}
		}

		return sprintf(
			'<div class="%1$s" style="%2$s"><svg class="brik-wm-svg block h-auto w-full" viewBox="0 6 360 142" role="img" aria-label="%3$s"><defs>%4$s</defs><path class="brik-wm-land" d="%5$s" fill="url(#%6$s-dots)"/><g class="brik-wm-arcs" fill="none">%7$s</g><g class="brik-wm-markers">%8$s</g></svg>%9$s</div>',
			esc_attr( brik_cls( 'brik-wm relative w-full', array( 'brik-wm--animate' => ! empty( $a['animate'] ) ) ) ),
			esc_attr( '--brik-wm-dur:' . $dur . 's' ),
			esc_attr( '' !== (string) $a['label'] ? wp_strip_all_tags( $a['label'] ) : __( 'World map', 'brik-builder' ) ),
			$defs,
			esc_attr( brik_3d_world_path() ),
			esc_attr( $uid ),
			$arcs,
			$dots,
			$labels
		);
	},
);
