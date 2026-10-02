<?php
namespace Brik;

defined( 'ABSPATH' ) || exit;

/**
 * Shared field definitions and helpers used by module definitions.
 *
 * Every element gets the design + advanced fields defined here. Modules add their own
 * content fields and element-level style fields (built with the helpers below).
 */
final class Fields {

	const WRAP = '{{wrap}}';

	private static $common;

	public static function common() {
		if ( null === self::$common ) {
			self::$common = array_merge( self::design(), self::advanced() );
		}
		return self::$common;
	}

	private static function design() {
		$w = self::WRAP;
		$f = array();

		// Spacing.
		$f['margin']  = self::field( 'spacing', __( 'Margin', 'brik' ), 'spacing', array( 'responsive' => true, 'css' => array( $w, 'margin' ) ) );
		$f['padding'] = self::field( 'spacing', __( 'Padding', 'brik' ), 'spacing', array( 'responsive' => true, 'css' => array( $w, 'padding' ) ) );

		// Sizing.
		$f['width']      = self::field( 'unit', __( 'Width', 'brik' ), 'sizing', array( 'responsive' => true, 'css' => array( $w, 'width' ) ) );
		$f['max_width']  = self::field( 'unit', __( 'Max width', 'brik' ), 'sizing', array( 'responsive' => true, 'css' => array( $w, 'max-width' ) ) );
		$f['min_height'] = self::field( 'unit', __( 'Min height', 'brik' ), 'sizing', array( 'responsive' => true, 'css' => array( $w, 'min-height' ) ) );
		$f['height']     = self::field( 'unit', __( 'Height', 'brik' ), 'sizing', array( 'responsive' => true, 'css' => array( $w, 'height' ) ) );
		$f['self_align'] = self::field(
			'select',
			__( 'Element alignment', 'brik' ),
			'sizing',
			array(
				'responsive' => true,
				'options'    => self::opts( array( '' => __( 'Default', 'brik' ), 'left' => __( 'Left', 'brik' ), 'center' => __( 'Center', 'brik' ), 'right' => __( 'Right', 'brik' ) ) ),
				'css'        => array(
					'selector' => $w,
					'map'      => array(
						'left'   => 'margin-left:0;margin-right:auto',
						'center' => 'margin-left:auto;margin-right:auto',
						'right'  => 'margin-left:auto;margin-right:0',
					),
				),
			)
		);
		$f['overflow'] = self::field( 'select', __( 'Overflow', 'brik' ), 'sizing', array( 'options' => self::opts( array( '' => __( 'Default', 'brik' ), 'visible' => 'Visible', 'hidden' => 'Hidden', 'auto' => 'Auto' ) ), 'css' => array( $w, 'overflow' ) ) );

		// Background.
		$f['bg_color']      = self::field( 'color', __( 'Background color', 'brik' ), 'background', array( 'responsive' => true, 'hover' => true, 'css' => array( $w, 'background-color' ) ) );
		$f['bg_gradient']   = self::field( 'gradient', __( 'Background gradient', 'brik' ), 'background', array( 'responsive' => true, 'hover' => true, 'composite' => 'background' ) );
		$f['bg_image']      = self::field( 'image', __( 'Background image', 'brik' ), 'background', array( 'responsive' => true, 'composite' => 'background' ) );
		$f['bg_overlay']    = self::field( 'color', __( 'Image overlay', 'brik' ), 'background', array( 'responsive' => true, 'hover' => true, 'composite' => 'background' ) );
		$f['bg_size']       = self::field( 'select', __( 'Background size', 'brik' ), 'background', array( 'responsive' => true, 'options' => self::opts( array( '' => 'Default', 'cover' => 'Cover', 'contain' => 'Contain', 'auto' => 'Actual size' ) ), 'css' => array( $w, 'background-size' ) ) );
		$f['bg_position']   = self::field( 'select', __( 'Background position', 'brik' ), 'background', array( 'responsive' => true, 'options' => self::opts( self::positions() ), 'css' => array( $w, 'background-position' ) ) );
		$f['bg_repeat']     = self::field( 'select', __( 'Background repeat', 'brik' ), 'background', array( 'options' => self::opts( array( '' => 'Default', 'no-repeat' => 'No repeat', 'repeat' => 'Repeat', 'repeat-x' => 'Repeat X', 'repeat-y' => 'Repeat Y' ) ), 'css' => array( $w, 'background-repeat' ) ) );
		$f['bg_attachment'] = self::field( 'select', __( 'Parallax', 'brik' ), 'background', array( 'options' => self::opts( array( '' => __( 'Off', 'brik' ), 'fixed' => __( 'Fixed (CSS parallax)', 'brik' ) ) ), 'css' => array( $w, 'background-attachment' ) ) );

		// Typography (inherited by the element's content).
		$f += self::typography( '', __( 'Text', 'brik' ), $w, 'text' );

		// Border.
		$f['border_width'] = self::field( 'spacing', __( 'Border width', 'brik' ), 'border', array( 'responsive' => true, 'css' => array( $w, 'border-width' ) ) );
		$f['border_style'] = self::field( 'select', __( 'Border style', 'brik' ), 'border', array( 'options' => self::opts( array( '' => 'Default', 'solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted', 'double' => 'Double', 'none' => 'None' ) ), 'css' => array( $w, 'border-style' ) ) );
		$f['border_color'] = self::field( 'color', __( 'Border color', 'brik' ), 'border', array( 'hover' => true, 'css' => array( $w, 'border-color' ) ) );
		$f['radius']       = self::field( 'spacing', __( 'Corner radius', 'brik' ), 'border', array( 'responsive' => true, 'hover' => true, 'css' => array( $w, 'border-radius' ) ) );

		// Box shadow.
		$f['shadow'] = self::field( 'shadow', __( 'Box shadow', 'brik' ), 'shadow', array( 'hover' => true, 'css' => array( 'selector' => $w, 'prop' => 'box-shadow', 'map' => self::shadow_map() ) ) );

		// Filters.
		$f['opacity']    = self::field( 'range', __( 'Opacity', 'brik' ), 'filters', array( 'hover' => true, 'min' => 0, 'max' => 1, 'step' => 0.05, 'css' => array( $w, 'opacity' ) ) );
		$f['blur']       = self::field( 'range', __( 'Blur', 'brik' ), 'filters', array( 'hover' => true, 'min' => 0, 'max' => 40, 'unit' => 'px', 'composite' => 'filter' ) );
		$f['brightness'] = self::field( 'range', __( 'Brightness', 'brik' ), 'filters', array( 'hover' => true, 'min' => 0, 'max' => 300, 'unit' => '%', 'composite' => 'filter' ) );
		$f['contrast']   = self::field( 'range', __( 'Contrast', 'brik' ), 'filters', array( 'hover' => true, 'min' => 0, 'max' => 300, 'unit' => '%', 'composite' => 'filter' ) );
		$f['grayscale']  = self::field( 'range', __( 'Grayscale', 'brik' ), 'filters', array( 'hover' => true, 'min' => 0, 'max' => 100, 'unit' => '%', 'composite' => 'filter' ) );
		$f['saturate']   = self::field( 'range', __( 'Saturation', 'brik' ), 'filters', array( 'hover' => true, 'min' => 0, 'max' => 300, 'unit' => '%', 'composite' => 'filter' ) );
		$f['hue_rotate'] = self::field( 'range', __( 'Hue rotate', 'brik' ), 'filters', array( 'hover' => true, 'min' => 0, 'max' => 360, 'unit' => 'deg', 'composite' => 'filter' ) );
		$f['blend_mode'] = self::field( 'select', __( 'Blend mode', 'brik' ), 'filters', array( 'options' => self::opts( array( '' => 'Normal', 'multiply' => 'Multiply', 'screen' => 'Screen', 'overlay' => 'Overlay', 'darken' => 'Darken', 'lighten' => 'Lighten', 'color-dodge' => 'Color dodge', 'difference' => 'Difference', 'luminosity' => 'Luminosity' ) ), 'css' => array( $w, 'mix-blend-mode' ) ) );
		$f['backdrop_blur'] = self::field( 'range', __( 'Backdrop blur', 'brik' ), 'filters', array( 'min' => 0, 'max' => 40, 'unit' => 'px', 'css' => array( 'selector' => $w, 'prop' => 'backdrop-filter', 'value' => 'blur({{v}})' ) ) );

		// Transform.
		$f['rotate']      = self::field( 'range', __( 'Rotate', 'brik' ), 'transform', array( 'responsive' => true, 'hover' => true, 'min' => -180, 'max' => 180, 'unit' => 'deg', 'composite' => 'transform' ) );
		$f['scale']       = self::field( 'range', __( 'Scale', 'brik' ), 'transform', array( 'responsive' => true, 'hover' => true, 'min' => 0, 'max' => 3, 'step' => 0.01, 'composite' => 'transform' ) );
		$f['translate_x'] = self::field( 'unit', __( 'Move horizontally', 'brik' ), 'transform', array( 'responsive' => true, 'hover' => true, 'composite' => 'transform' ) );
		$f['translate_y'] = self::field( 'unit', __( 'Move vertically', 'brik' ), 'transform', array( 'responsive' => true, 'hover' => true, 'composite' => 'transform' ) );
		$f['skew_x']      = self::field( 'range', __( 'Skew', 'brik' ), 'transform', array( 'hover' => true, 'min' => -60, 'max' => 60, 'unit' => 'deg', 'composite' => 'transform' ) );
		$f['transition']  = self::field( 'range', __( 'Hover transition (ms)', 'brik' ), 'transform', array( 'min' => 0, 'max' => 2000, 'step' => 50, 'default' => '' ) );

		// Position.
		$f['position'] = self::field( 'select', __( 'Position', 'brik' ), 'position', array( 'responsive' => true, 'options' => self::opts( array( '' => 'Default', 'relative' => 'Relative', 'absolute' => 'Absolute', 'fixed' => 'Fixed', 'sticky' => 'Sticky' ) ), 'css' => array( $w, 'position' ) ) );
		foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
			$f[ $side ] = self::field( 'unit', ucfirst( $side ), 'position', array( 'responsive' => true, 'css' => array( $w, $side ), 'show_if' => array( 'position' => array( 'relative', 'absolute', 'fixed', 'sticky' ) ) ) );
		}
		$f['z_index'] = self::field( 'number', __( 'Z index', 'brik' ), 'position', array( 'css' => array( $w, 'z-index' ) ) );

		// Entrance animation.
		$f['animation']          = self::field( 'select', __( 'Entrance animation', 'brik' ), 'animation', array( 'options' => self::opts( self::animations() ) ) );
		$f['animation_duration'] = self::field( 'range', __( 'Duration (ms)', 'brik' ), 'animation', array( 'min' => 100, 'max' => 3000, 'step' => 50, 'default' => '', 'show_if' => array( 'animation' => '!' ) ) );
		$f['animation_delay']    = self::field( 'range', __( 'Delay (ms)', 'brik' ), 'animation', array( 'min' => 0, 'max' => 3000, 'step' => 50, 'default' => '', 'show_if' => array( 'animation' => '!' ) ) );

		foreach ( $f as &$field ) {
			$field['tab'] = 'design';
		}
		return $f;
	}

	private static function advanced() {
		$f = array(
			'css_id'     => self::field( 'text', __( 'CSS ID', 'brik' ), 'attributes' ),
			'css_class'  => self::field( 'text', __( 'CSS classes', 'brik' ), 'attributes', array( 'description' => __( 'Space separated.', 'brik' ) ) ),
			'el_link'    => self::field( 'link', __( 'Element link', 'brik' ), 'attributes', array( 'description' => __( 'Makes the whole element clickable.', 'brik' ) ) ),
			'custom_css' => self::field( 'code', __( 'Custom CSS', 'brik' ), 'custom_css', array( 'language' => 'css', 'description' => __( 'Use "selector" to target this element, e.g. selector:hover { opacity: .8 }. Plain declarations apply to the element.', 'brik' ) ) ),
			'hide_on'    => self::field( 'devices', __( 'Hide on', 'brik' ), 'visibility' ),
			'display'    => self::field( 'select', __( 'Show to', 'brik' ), 'visibility', array( 'options' => self::opts( array( '' => __( 'Everyone', 'brik' ), 'logged_in' => __( 'Logged-in users', 'brik' ), 'logged_out' => __( 'Logged-out visitors', 'brik' ) ) ) ) ),
			'show_from'  => self::field( 'date', __( 'Show from', 'brik' ), 'visibility' ),
			'show_until' => self::field( 'date', __( 'Show until', 'brik' ), 'visibility' ),
		);
		foreach ( $f as &$field ) {
			$field['tab'] = 'advanced';
		}
		return $f;
	}

	/* ---------------------------------------------------------------------
	 * Helpers for module definitions.
	 * ------------------------------------------------------------------- */

	public static function field( $type, $label, $group = '', array $extra = array() ) {
		$field = array_merge(
			array(
				'type'  => $type,
				'label' => $label,
				'group' => $group,
			),
			$extra
		);

		// Shorthand: 'css' => array( selector, prop ).
		if ( isset( $field['css'] ) && isset( $field['css'][0] ) ) {
			$field['css'] = array(
				'selector' => $field['css'][0],
				'prop'     => $field['css'][1],
			);
		}
		return $field;
	}

	/**
	 * Typography field set for an element inside a module.
	 *
	 * @param string $prefix   Attribute prefix, e.g. 'title' gives title_font_size.
	 * @param string $label    Group label shown in the settings panel.
	 * @param string $selector CSS selector, {{wrap}} is the element wrapper.
	 */
	public static function typography( $prefix, $label, $selector, $group = null ) {
		$p     = $prefix ? $prefix . '_' : '';
		$group = $group ? $group : $prefix . '_typography';
		$hover = array( 'hover_selector' => self::hover_selector( $selector ) );

		$fields = array(
			$p . 'font_family'    => self::field( 'font', __( 'Font', 'brik' ), $group, array( 'css' => array( 'selector' => $selector, 'prop' => 'font-family', 'font' => true ) ) ),
			$p . 'font_size'      => self::field( 'unit', __( 'Font size', 'brik' ), $group, array( 'responsive' => true, 'css' => array( $selector, 'font-size' ) ) ),
			$p . 'font_weight'    => self::field( 'select', __( 'Font weight', 'brik' ), $group, array( 'options' => self::opts( self::weights() ), 'css' => array( $selector, 'font-weight' ) ) ),
			$p . 'font_style'     => self::field( 'select', __( 'Font style', 'brik' ), $group, array( 'options' => self::opts( array( '' => 'Default', 'normal' => 'Normal', 'italic' => 'Italic' ) ), 'css' => array( $selector, 'font-style' ) ) ),
			$p . 'text_color'     => self::field( 'color', __( 'Text color', 'brik' ), $group, array( 'hover' => true, 'css' => array_merge( array( 'selector' => $selector, 'prop' => 'color' ), $hover ) ) ),
			$p . 'line_height'    => self::field( 'unit', __( 'Line height', 'brik' ), $group, array( 'responsive' => true, 'css' => array( $selector, 'line-height' ) ) ),
			$p . 'letter_spacing' => self::field( 'unit', __( 'Letter spacing', 'brik' ), $group, array( 'responsive' => true, 'css' => array( $selector, 'letter-spacing' ) ) ),
			$p . 'text_align'     => self::field( 'align', __( 'Text alignment', 'brik' ), $group, array( 'responsive' => true, 'css' => array( $selector, 'text-align' ) ) ),
			$p . 'text_transform' => self::field( 'select', __( 'Text transform', 'brik' ), $group, array( 'options' => self::opts( array( '' => 'Default', 'none' => 'None', 'uppercase' => 'UPPERCASE', 'lowercase' => 'lowercase', 'capitalize' => 'Capitalize' ) ), 'css' => array( $selector, 'text-transform' ) ) ),
			$p . 'text_decoration' => self::field( 'select', __( 'Decoration', 'brik' ), $group, array( 'options' => self::opts( array( '' => 'Default', 'none' => 'None', 'underline' => 'Underline', 'line-through' => 'Strikethrough' ) ), 'css' => array( $selector, 'text-decoration' ) ) ),
			$p . 'text_shadow'    => self::field( 'text', __( 'Text shadow', 'brik' ), $group, array( 'placeholder' => '0 2px 4px rgb(0 0 0 / .3)', 'css' => array( $selector, 'text-shadow' ) ) ),
		);

		if ( $prefix ) {
			foreach ( $fields as &$field ) {
				$field['tab']         = 'design';
				$field['group_label'] = $label;
			}
		}
		return $fields;
	}

	/**
	 * Box style field set (background, color, border, radius, padding, shadow) for an inner element.
	 */
	public static function box( $prefix, $label, $selector, array $only = array() ) {
		$p     = $prefix . '_';
		$hover = array( 'hover_selector' => self::hover_selector( $selector ) );
		$all   = array(
			'bg'           => self::field( 'color', __( 'Background', 'brik' ), $prefix, array( 'hover' => true, 'css' => array_merge( array( 'selector' => $selector, 'prop' => 'background-color' ), $hover ) ) ),
			'color'        => self::field( 'color', __( 'Text color', 'brik' ), $prefix, array( 'hover' => true, 'css' => array_merge( array( 'selector' => $selector, 'prop' => 'color' ), $hover ) ) ),
			'border_width' => self::field( 'spacing', __( 'Border width', 'brik' ), $prefix, array( 'css' => array( $selector, 'border-width' ) ) ),
			'border_color' => self::field( 'color', __( 'Border color', 'brik' ), $prefix, array( 'hover' => true, 'css' => array_merge( array( 'selector' => $selector, 'prop' => 'border-color' ), $hover ) ) ),
			'radius'       => self::field( 'spacing', __( 'Corner radius', 'brik' ), $prefix, array( 'css' => array( $selector, 'border-radius' ) ) ),
			'padding'      => self::field( 'spacing', __( 'Padding', 'brik' ), $prefix, array( 'responsive' => true, 'css' => array( $selector, 'padding' ) ) ),
			'shadow'       => self::field( 'shadow', __( 'Shadow', 'brik' ), $prefix, array( 'hover' => true, 'css' => array_merge( array( 'selector' => $selector, 'prop' => 'box-shadow', 'map' => self::shadow_map() ), $hover ) ) ),
		);
		$out = array();
		foreach ( $all as $key => $field ) {
			if ( $only && ! in_array( $key, $only, true ) ) {
				continue;
			}
			$field['tab']         = 'design';
			$field['group_label'] = $label;
			$out[ $p . $key ]     = $field;
		}
		return $out;
	}

	/**
	 * Hover rules for inner elements apply when the element itself is hovered.
	 */
	public static function hover_selector( $selector ) {
		if ( self::WRAP === trim( $selector ) ) {
			return self::WRAP . ':hover';
		}
		return $selector . ':hover';
	}

	public static function opts( array $map ) {
		$out = array();
		foreach ( $map as $value => $label ) {
			$out[] = array(
				'value' => (string) $value,
				'label' => $label,
			);
		}
		return $out;
	}

	public static function weights() {
		return array(
			''    => 'Default',
			'100' => 'Thin',
			'200' => 'Extra light',
			'300' => 'Light',
			'400' => 'Regular',
			'500' => 'Medium',
			'600' => 'Semibold',
			'700' => 'Bold',
			'800' => 'Extra bold',
			'900' => 'Black',
		);
	}

	public static function positions() {
		return array(
			''              => 'Default',
			'center'        => 'Center',
			'top'           => 'Top',
			'bottom'        => 'Bottom',
			'left'          => 'Left',
			'right'         => 'Right',
			'top left'      => 'Top left',
			'top right'     => 'Top right',
			'bottom left'   => 'Bottom left',
			'bottom right'  => 'Bottom right',
		);
	}

	public static function shadow_map() {
		return array(
			'none'  => 'none',
			'xs'    => '0 1px 2px 0 rgb(0 0 0 / 0.05)',
			'sm'    => '0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1)',
			'md'    => '0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1)',
			'lg'    => '0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1)',
			'xl'    => '0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1)',
			'2xl'   => '0 25px 50px -12px rgb(0 0 0 / 0.25)',
			'inner' => 'inset 0 2px 4px 0 rgb(0 0 0 / 0.05)',
		);
	}

	public static function animations() {
		return array(
			''            => __( 'None', 'brik' ),
			'fade'        => __( 'Fade in', 'brik' ),
			'slide-up'    => __( 'Slide up', 'brik' ),
			'slide-down'  => __( 'Slide down', 'brik' ),
			'slide-left'  => __( 'Slide from right', 'brik' ),
			'slide-right' => __( 'Slide from left', 'brik' ),
			'zoom'        => __( 'Zoom in', 'brik' ),
			'zoom-out'    => __( 'Zoom out', 'brik' ),
			'flip'        => __( 'Flip', 'brik' ),
			'bounce'      => __( 'Bounce', 'brik' ),
			'blur'        => __( 'Blur in', 'brik' ),
		);
	}

	/**
	 * Labels for the field groups in the settings panel.
	 */
	public static function group_labels() {
		return array(
			'content'     => __( 'Content', 'brik' ),
			'spacing'     => __( 'Spacing', 'brik' ),
			'sizing'      => __( 'Sizing', 'brik' ),
			'background'  => __( 'Background', 'brik' ),
			'text'        => __( 'Text', 'brik' ),
			'border'      => __( 'Border', 'brik' ),
			'shadow'      => __( 'Box shadow', 'brik' ),
			'filters'     => __( 'Filters', 'brik' ),
			'transform'   => __( 'Transform', 'brik' ),
			'position'    => __( 'Position', 'brik' ),
			'animation'   => __( 'Animation', 'brik' ),
			'attributes'  => __( 'Attributes', 'brik' ),
			'custom_css'  => __( 'Custom CSS', 'brik' ),
			'visibility'  => __( 'Visibility', 'brik' ),
		);
	}
}
