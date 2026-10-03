<?php
/**
 * Light/dark mode switch. Toggles the "dark" class on <html> and remembers the choice.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'theme_toggle',
	'title'       => __( 'Theme toggle', 'brik-builder' ),
	'category'    => 'site',
	'icon'        => 'sun-moon',
	'description' => 'Light/dark mode switch for visitors (stored in localStorage "brik-theme", follows prefers-color-scheme until chosen). style: icon (sun/moon button) | switch | segmented (light/dark/system). variant (icon style): ghost|outline|secondary. size: sm|default|lg. label (accessible name; shown next to the switch when show_label).',
	'fields'      => array_merge(
		array(
			'style'      => Fields::field( 'select', __( 'Style', 'brik-builder' ), 'content', array( 'default' => 'icon', 'options' => Fields::opts( array( 'icon' => __( 'Icon button', 'brik-builder' ), 'switch' => __( 'Switch', 'brik-builder' ), 'segmented' => __( 'Light / Dark / System', 'brik-builder' ) ) ) ) ),
			'variant'    => Fields::field( 'select', __( 'Button variant', 'brik-builder' ), 'content', array( 'default' => 'outline', 'options' => Fields::opts( array( 'ghost' => __( 'Ghost', 'brik-builder' ), 'outline' => __( 'Outline', 'brik-builder' ), 'secondary' => __( 'Secondary', 'brik-builder' ) ) ), 'show_if' => array( 'style' => 'icon' ) ) ),
			'size'       => Fields::field( 'select', __( 'Size', 'brik-builder' ), 'content', array( 'default' => 'default', 'options' => Fields::opts( array( 'sm' => __( 'Small', 'brik-builder' ), 'default' => __( 'Default', 'brik-builder' ), 'lg' => __( 'Large', 'brik-builder' ) ) ) ) ),
			'label'      => Fields::field( 'text', __( 'Label', 'brik-builder' ), 'content', array( 'default' => __( 'Dark mode', 'brik-builder' ) ) ),
			'show_label' => Fields::field( 'toggle', __( 'Show label', 'brik-builder' ), 'content', array( 'show_if' => array( 'style' => 'switch' ) ) ),
			'align'      => Fields::field( 'align', __( 'Alignment', 'brik-builder' ), 'content', array( 'responsive' => true, 'css' => array( Fields::WRAP, 'text-align' ) ) ),
		),
		Fields::box( 'toggle', __( 'Toggle', 'brik-builder' ), Fields::WRAP . ' .brik-theme-control' )
	),
	'render'      => static function ( array $a, Brik\Context $ctx ) {
		if ( ! $ctx->canvas ) {
			Brik\Forms::$theme_toggle = true;
		}
		$label = '' !== trim( (string) $a['label'] ) ? wp_strip_all_tags( $a['label'] ) : __( 'Dark mode', 'brik-builder' );
		$size  = in_array( $a['size'], array( 'sm', 'default', 'lg' ), true ) ? $a['size'] : 'default';

		if ( 'segmented' === $a['style'] ) {
			$btn   = array(
				'sm'      => 'h-7 px-2 text-xs',
				'default' => 'h-8 px-2.5 text-sm',
				'lg'      => 'h-9 px-3 text-sm',
			);
			$modes = array(
				'light'  => array( 'sun', __( 'Light', 'brik-builder' ) ),
				'dark'   => array( 'moon', __( 'Dark', 'brik-builder' ) ),
				'system' => array( 'monitor', __( 'System', 'brik-builder' ) ),
			);
			$out = '';
			foreach ( $modes as $mode => $m ) {
				$out .= '<button type="button" class="' . esc_attr( 'inline-flex items-center justify-center gap-1.5 rounded-md font-medium text-muted-foreground transition-colors outline-none cursor-pointer hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-pressed:bg-background aria-pressed:text-foreground aria-pressed:shadow-sm dark:aria-pressed:bg-input/50 ' . $btn[ $size ] ) . '" data-mode="' . esc_attr( $mode ) . '" aria-pressed="' . ( 'system' === $mode ? 'true' : 'false' ) . '" title="' . esc_attr( $m[1] ) . '">'
					. brik_icon( $m[0], 'size-4' ) . '<span class="sr-only sm:not-sr-only">' . esc_html( $m[1] ) . '</span></button>';
			}
			return '<div class="brik-theme-control brik-theme-segmented inline-flex items-center gap-0.5 rounded-lg border bg-muted p-0.5" role="group" aria-label="' . esc_attr( $label ) . '" data-brik-theme="segmented">' . $out . '</div>';
		}

		if ( 'switch' === $a['style'] ) {
			$track = array(
				'sm'      => 'h-5 w-9',
				'default' => 'h-6 w-11',
				'lg'      => 'h-7 w-13',
			);
			$thumb = array(
				'sm'      => 'size-4 dark:translate-x-4',
				'default' => 'size-5 dark:translate-x-5',
				'lg'      => 'size-6 dark:translate-x-6',
			);
			$text = brik_form_bool( $a['show_label'] ) ? '<span class="brik-theme-label text-sm font-medium">' . esc_html( $label ) . '</span>' : '';
			return '<button type="button" role="switch" aria-checked="false" aria-label="' . esc_attr( $label ) . '" class="brik-theme-switch group inline-flex items-center gap-2.5 align-middle outline-none cursor-pointer" data-brik-theme="switch">'
				. '<span class="' . esc_attr( 'brik-theme-control relative inline-flex shrink-0 items-center rounded-full border border-transparent bg-input p-px shadow-xs transition-colors group-focus-visible:ring-[3px] group-focus-visible:ring-ring/50 dark:bg-primary ' . $track[ $size ] ) . '">'
				. '<span class="' . esc_attr( 'pointer-events-none grid place-items-center rounded-full bg-background text-foreground shadow-sm ring-0 transition-transform translate-x-0 ' . $thumb[ $size ] ) . '">'
				. brik_icon( 'sun', 'size-3 dark:hidden' ) . brik_icon( 'moon', 'hidden size-3 dark:block' ) . '</span></span>'
				. $text . '</button>';
		}

		$variant = in_array( $a['variant'], array( 'ghost', 'outline', 'secondary' ), true ) ? $a['variant'] : 'outline';
		$sizes   = array(
			'sm'      => 'size-8',
			'default' => 'size-9',
			'lg'      => 'size-10',
		);
		return '<button type="button" class="' . esc_attr( brik_button_class( $variant, 'icon', 'brik-theme-control ' . $sizes[ $size ] ) ) . '" aria-label="' . esc_attr( $label ) . '" aria-pressed="false" data-brik-theme="icon">'
			. brik_icon( 'sun', 'size-4 transition-transform dark:hidden' ) . brik_icon( 'moon', 'hidden size-4 dark:block' ) . '</button>';
	},
);
