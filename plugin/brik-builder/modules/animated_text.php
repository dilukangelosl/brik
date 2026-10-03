<?php
/**
 * Animated text: headline with a text effect.
 *
 * The final, readable text is always in the markup. Scripts only add motion on top of it,
 * so search engines, screen readers, no-JS visitors and reduced motion all get plain text.
 *
 * @package Brik
 * @author  Diluk Angelo
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'animated_text',
	'title'       => __( 'Animated Text', 'brik-builder' ),
	'category'    => 'effects',
	'icon'        => 'type',
	'description' => 'Headline with a text effect. effect: typewriter|rotate (cycle "words" between "before" and "after") | blur_in|generate|scramble|flip3d|scroll_reveal (animate the whole line: before + text + after) | shimmer|gradient|aurora|highlight|sparkles (style "text" only, before/after stay plain). words: textarea, one phrase per line. before, text, after: inline HTML. tag: h1-h6|p. size: display|h1|h2|h3|lead. align: left|center|right. color (text), accent_color, accent_color_2, accent_color_3 (gradient/aurora/highlight/sparkle colors). accent_style: plain|color|gradient (typewriter, rotate, sparkles). rotate_style: slide|flip|blur. highlight_style: marker|underline. speed: 0.25-3 multiplier. loop: bool. trigger: load|in-view. caret: bool (typewriter). Respects prefers-reduced-motion (shows the final text).',
	'fields'      => array_merge(
		array(
			'effect'          => Fields::field(
				'select',
				__( 'Effect', 'brik-builder' ),
				'content',
				array(
					'default' => 'rotate',
					'options' => Fields::opts(
						array(
							'typewriter'    => __( 'Typewriter', 'brik-builder' ),
							'rotate'        => __( 'Rotating words', 'brik-builder' ),
							'blur_in'       => __( 'Blur in', 'brik-builder' ),
							'generate'      => __( 'Generate (streaming)', 'brik-builder' ),
							'scramble'      => __( 'Scramble / decrypt', 'brik-builder' ),
							'flip3d'        => __( '3D letter flip', 'brik-builder' ),
							'scroll_reveal' => __( 'Reveal on scroll', 'brik-builder' ),
							'shimmer'       => __( 'Shimmer', 'brik-builder' ),
							'gradient'      => __( 'Animated gradient', 'brik-builder' ),
							'aurora'        => __( 'Aurora', 'brik-builder' ),
							'highlight'     => __( 'Highlight', 'brik-builder' ),
							'sparkles'      => __( 'Sparkles', 'brik-builder' ),
						)
					),
				)
			),
			'before'          => Fields::field( 'text', __( 'Text before', 'brik-builder' ), 'content', array( 'default' => __( 'Build websites that feel', 'brik-builder' ), 'inline' => true ) ),
			'words'           => Fields::field(
				'textarea',
				__( 'Animated words', 'brik-builder' ),
				'content',
				array(
					'default'     => "fast\npolished\neffortless\nalive",
					'description' => __( 'One per line.', 'brik-builder' ),
					'show_if'     => array( 'effect' => array( 'typewriter', 'rotate' ) ),
				)
			),
			'text'            => Fields::field(
				'text',
				__( 'Text', 'brik-builder' ),
				'content',
				array(
					'default' => __( 'truly alive', 'brik-builder' ),
					'inline'  => true,
					'show_if' => array( 'effect' => array( 'blur_in', 'generate', 'scramble', 'flip3d', 'scroll_reveal', 'shimmer', 'gradient', 'aurora', 'highlight', 'sparkles' ) ),
				)
			),
			'after'           => Fields::field( 'text', __( 'Text after', 'brik-builder' ), 'content', array( 'inline' => true ) ),
			'tag'             => Fields::field( 'select', __( 'HTML tag', 'brik-builder' ), 'content', array( 'default' => 'h2', 'options' => Fields::opts( array( 'h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6', 'p' => 'p' ) ) ) ),
			'size'            => Fields::field( 'select', __( 'Size', 'brik-builder' ), 'content', array( 'default' => 'h1', 'options' => Fields::opts( brik_fx_size_options() ) ) ),
			'align'           => Fields::field( 'align', __( 'Alignment', 'brik-builder' ), 'content', array( 'responsive' => true, 'css' => array( Fields::WRAP, 'text-align' ) ) ),
			'trigger'         => Fields::field( 'select', __( 'Start', 'brik-builder' ), 'content', array( 'default' => 'in-view', 'options' => Fields::opts( array( 'in-view' => __( 'When scrolled into view', 'brik-builder' ), 'load' => __( 'On page load', 'brik-builder' ) ) ), 'show_if' => array( 'effect' => array( 'typewriter', 'blur_in', 'generate', 'scramble', 'flip3d', 'highlight' ) ) ) ),
			'speed'           => Fields::field( 'range', __( 'Speed', 'brik-builder' ), 'content', array( 'default' => 1, 'min' => 0.25, 'max' => 3, 'step' => 0.25, 'description' => __( '1 is normal, higher is faster.', 'brik-builder' ) ) ),
			'loop'            => Fields::field( 'toggle', __( 'Loop', 'brik-builder' ), 'content', array( 'default' => true, 'show_if' => array( 'effect' => array( 'typewriter', 'rotate', 'blur_in', 'generate', 'scramble', 'flip3d', 'highlight' ) ) ) ),
			'caret'           => Fields::field( 'toggle', __( 'Blinking caret', 'brik-builder' ), 'content', array( 'default' => true, 'show_if' => array( 'effect' => 'typewriter' ) ) ),
			'rotate_style'    => Fields::field( 'select', __( 'Transition', 'brik-builder' ), 'content', array( 'default' => 'slide', 'options' => Fields::opts( array( 'slide' => __( 'Slide up', 'brik-builder' ), 'flip' => __( 'Flip', 'brik-builder' ), 'blur' => __( 'Blur', 'brik-builder' ) ) ), 'show_if' => array( 'effect' => 'rotate' ) ) ),
			'highlight_style' => Fields::field( 'select', __( 'Highlight style', 'brik-builder' ), 'content', array( 'default' => 'marker', 'options' => Fields::opts( array( 'marker' => __( 'Marker', 'brik-builder' ), 'underline' => __( 'Underline', 'brik-builder' ) ) ), 'show_if' => array( 'effect' => 'highlight' ) ) ),
			'accent_style'    => Fields::field( 'select', __( 'Accent text', 'brik-builder' ), 'content', array( 'default' => 'gradient', 'options' => Fields::opts( array( 'gradient' => __( 'Gradient', 'brik-builder' ), 'color' => __( 'Accent color', 'brik-builder' ), 'plain' => __( 'Same as text', 'brik-builder' ) ) ), 'show_if' => array( 'effect' => array( 'typewriter', 'rotate', 'sparkles' ) ) ) ),
			'color'           => Fields::field( 'color', __( 'Text color', 'brik-builder' ), 'colors', array( 'tab' => 'design', 'group_label' => __( 'Effect colors', 'brik-builder' ) ) ),
			'accent_color'    => Fields::field( 'color', __( 'Accent color 1', 'brik-builder' ), 'colors', array( 'tab' => 'design', 'group_label' => __( 'Effect colors', 'brik-builder' ), 'placeholder' => '#6366f1' ) ),
			'accent_color_2'  => Fields::field( 'color', __( 'Accent color 2', 'brik-builder' ), 'colors', array( 'tab' => 'design', 'group_label' => __( 'Effect colors', 'brik-builder' ), 'placeholder' => '#a855f7' ) ),
			'accent_color_3'  => Fields::field( 'color', __( 'Accent color 3', 'brik-builder' ), 'colors', array( 'tab' => 'design', 'group_label' => __( 'Effect colors', 'brik-builder' ), 'placeholder' => '#ec4899' ) ),
		),
		Fields::typography( 'title', __( 'Headline', 'brik-builder' ), Fields::WRAP . ' .brik-at-heading' ),
		Fields::typography( 'accent', __( 'Animated part', 'brik-builder' ), Fields::WRAP . ' .brik-at-fx' )
	),
	'render'      => static function ( $a, $ctx ) {
		$effects = array( 'typewriter', 'rotate', 'blur_in', 'generate', 'scramble', 'flip3d', 'scroll_reveal', 'shimmer', 'gradient', 'aurora', 'highlight', 'sparkles' );
		$effect  = in_array( $a['effect'], $effects, true ) ? $a['effect'] : 'rotate';
		$tag     = brik_fx_tag( $a['tag'], array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p' ), 'h2' );
		$sizes   = brik_fx_size_classes();
		$size    = isset( $sizes[ $a['size'] ] ) ? $sizes[ $a['size'] ] : $sizes['h1'];
		$speed   = brik_fx_num( $a['speed'], 1, 0.25, 3 );
		$cycling = in_array( $effect, array( 'typewriter', 'rotate' ), true );

		$parts = array();
		if ( '' !== trim( (string) $a['before'] ) ) {
			$parts[] = '<span class="brik-at-part brik-at-before"' . $ctx->inline( 'before' ) . '>' . brik_inline( $a['before'] ) . '</span>';
		}

		if ( $cycling ) {
			$words = brik_lines( wp_strip_all_tags( (string) $a['words'] ) );
			if ( ! $words && '' !== trim( (string) $a['text'] ) ) {
				$words = array( wp_strip_all_tags( $a['text'] ) );
			}
			if ( $words ) {
				$accent = 'brik-at-fx brik-at-accent--' . ( in_array( $a['accent_style'], array( 'gradient', 'color', 'plain' ), true ) ? $a['accent_style'] : 'gradient' );
				if ( 'typewriter' === $effect ) {
					$longest = $words[0];
					foreach ( $words as $w ) {
						if ( strlen( $w ) > strlen( $longest ) ) {
							$longest = $w;
						}
					}
					$parts[] = '<span class="brik-at-slot brik-at-type ' . esc_attr( $accent ) . '"><span class="brik-at-sizer" aria-hidden="true">' . esc_html( $longest ) . '</span><span class="brik-at-typing"><span class="brik-at-typed">' . esc_html( $words[0] ) . '</span>'
						. ( ! empty( $a['caret'] ) ? '<span class="brik-at-caret" aria-hidden="true"></span>' : '' ) . '</span></span>';
				} else {
					$items = '';
					foreach ( $words as $i => $w ) {
						$items .= '<span class="brik-at-word' . ( 0 === $i ? ' is-active' : '' ) . '"' . ( $i ? ' aria-hidden="true"' : '' ) . '>' . esc_html( $w ) . '</span>';
					}
					$parts[] = '<span class="brik-at-slot brik-at-rotate brik-at-rotate--' . esc_attr( in_array( $a['rotate_style'], array( 'slide', 'flip', 'blur' ), true ) ? $a['rotate_style'] : 'slide' ) . ' ' . esc_attr( $accent ) . '">' . $items . '</span>';
				}
			}
		} elseif ( '' !== trim( (string) $a['text'] ) ) {
			$text = '<span class="brik-at-part brik-at-text brik-at-fx"' . $ctx->inline( 'text' ) . '>' . brik_inline( $a['text'] ) . '</span>';
			if ( 'sparkles' === $effect ) {
				$accent = in_array( $a['accent_style'], array( 'gradient', 'color', 'plain' ), true ) ? $a['accent_style'] : 'gradient';
				$text   = '<span class="brik-at-sparkle-wrap brik-at-accent--' . esc_attr( $accent ) . '">' . $text . '<span class="brik-at-sparkles" aria-hidden="true"></span></span>';
			} elseif ( 'highlight' === $effect ) {
				$text = str_replace( 'brik-at-part brik-at-text', 'brik-at-part brik-at-text brik-at-hl--' . ( 'underline' === $a['highlight_style'] ? 'underline' : 'marker' ), $text );
			}
			$parts[] = $text;
		}

		if ( '' !== trim( (string) $a['after'] ) ) {
			$parts[] = '<span class="brik-at-part brik-at-after"' . $ctx->inline( 'after' ) . '>' . brik_inline( $a['after'] ) . '</span>';
		}
		if ( ! $parts ) {
			return $ctx->placeholder( __( 'Add some text', 'brik-builder' ) );
		}

		$ctx->script( 'animated-text' );

		$data = array(
			'class'        => 'brik-at brik-at--' . str_replace( '_', '-', $effect ),
			'data-brik-at' => $effect,
			'data-trigger' => 'load' === $a['trigger'] ? 'load' : 'in-view',
			'data-loop'    => ! empty( $a['loop'] ) ? '1' : '0',
			'data-speed'   => (string) $speed,
			'style'        => brik_fx_vars(
				array(
					'--brik-at-speed' => (string) $speed,
					'--brik-at-c1'    => $a['accent_color'],
					'--brik-at-c2'    => $a['accent_color_2'],
					'--brik-at-c3'    => $a['accent_color_3'],
					'color'           => $a['color'],
				)
			),
		);
		if ( $cycling && ! empty( $words ) ) {
			$data['data-words'] = array_values( $words );
		}
		if ( in_array( $effect, array( 'shimmer', 'gradient', 'aurora', 'sparkles', 'rotate', 'typewriter' ), true ) ) {
			$data['data-brik-fx-pause'] = true;
		}

		return '<div' . brik_attrs( $data ) . '><' . $tag . ' class="brik-at-heading font-heading text-balance ' . esc_attr( $size ) . '">' . implode( ' ', $parts ) . '</' . $tag . '></div>';
	},
);
