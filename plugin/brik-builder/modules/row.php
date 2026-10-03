<?php
/**
 * Row: a grid of columns inside a section (or nested inside a column).
 *
 * @package Brik
 */

use Brik\Fields;
use Brik\Style;

defined( 'ABSPATH' ) || exit;

$brik_layouts = array(
	'1'                   => '1',
	'1/2,1/2'             => '1/2 + 1/2',
	'1/3,1/3,1/3'         => '1/3 × 3',
	'1/4,1/4,1/4,1/4'     => '1/4 × 4',
	'1/5,1/5,1/5,1/5,1/5' => '1/5 × 5',
	'1/6,1/6,1/6,1/6,1/6,1/6' => '1/6 × 6',
	'1/3,2/3'             => '1/3 + 2/3',
	'2/3,1/3'             => '2/3 + 1/3',
	'1/4,3/4'             => '1/4 + 3/4',
	'3/4,1/4'             => '3/4 + 1/4',
	'1/4,1/2,1/4'         => '1/4 + 1/2 + 1/4',
	'1/2,1/4,1/4'         => '1/2 + 1/4 + 1/4',
	'1/4,1/4,1/2'         => '1/4 + 1/4 + 1/2',
	'2/5,3/5'             => '2/5 + 3/5',
	'3/5,2/5'             => '3/5 + 2/5',
	'1/5,3/5,1/5'         => '1/5 + 3/5 + 1/5',
);

return array(
	'type'        => 'row',
	'title'       => __( 'Row', 'brik-builder' ),
	'category'    => 'structure',
	'icon'        => 'columns-3',
	'structural'  => true,
	'children'    => array( 'column' ),
	'description' => 'Holds columns. "columns" is a comma list of fractions matching the number of child columns, e.g. "1/3,2/3". direction: "" (auto: horizontal, stacked on phones)|horizontal|vertical|horizontal-reverse|vertical-reverse (responsive; set direction@mobile "horizontal" to keep columns side by side on phones). sizing: structure (use columns fractions) | equal | auto (columns fit their content or their own width). justify: start|center|end|space-between|space-around|space-evenly. align_items: start|center|end|stretch. wrap: toggle (auto/equal sizing).',
	'fields'      => array(
		'direction'   => Fields::field(
			'select',
			__( 'Direction', 'brik-builder' ),
			'content',
			array(
				'responsive' => true,
				'options'    => Fields::opts(
					array(
						''                   => __( 'Auto (horizontal, stacked on phones)', 'brik-builder' ),
						'horizontal'         => __( 'Horizontal', 'brik-builder' ),
						'vertical'           => __( 'Vertical (stacked)', 'brik-builder' ),
						'horizontal-reverse' => __( 'Horizontal, reversed', 'brik-builder' ),
						'vertical-reverse'   => __( 'Vertical, reversed', 'brik-builder' ),
					)
				),
				'description' => __( 'Phones stack columns vertically unless you set a phone value.', 'brik-builder' ),
			)
		),
		'columns'     => Fields::field( 'columns', __( 'Column structure', 'brik-builder' ), 'content', array( 'default' => '1', 'responsive' => true, 'options' => Fields::opts( $brik_layouts ) ) ),
		'sizing'      => Fields::field( 'select', __( 'Column sizing', 'brik-builder' ), 'content', array( 'responsive' => true, 'options' => Fields::opts( array( '' => __( 'Use column structure', 'brik-builder' ), 'equal' => __( 'Equal widths', 'brik-builder' ), 'auto' => __( 'Fit content', 'brik-builder' ) ) ), 'description' => __( 'With "Fit content", a column\'s own width setting is respected.', 'brik-builder' ) ) ),
		'justify'     => Fields::field( 'select', __( 'Horizontal alignment', 'brik-builder' ), 'content', array( 'responsive' => true, 'options' => Fields::opts( array( '' => __( 'Start', 'brik-builder' ), 'center' => __( 'Center', 'brik-builder' ), 'end' => __( 'End', 'brik-builder' ), 'space-between' => __( 'Space between', 'brik-builder' ), 'space-around' => __( 'Space around', 'brik-builder' ), 'space-evenly' => __( 'Space evenly', 'brik-builder' ) ) ) ) ),
		'align_items' => Fields::field( 'select', __( 'Vertical alignment', 'brik-builder' ), 'content', array( 'responsive' => true, 'options' => Fields::opts( array( '' => __( 'Stretch', 'brik-builder' ), 'start' => __( 'Top', 'brik-builder' ), 'center' => __( 'Middle', 'brik-builder' ), 'end' => __( 'Bottom', 'brik-builder' ) ) ), 'css' => array( Fields::WRAP, 'align-items' ) ) ),
		'wrap'        => Fields::field( 'toggle', __( 'Wrap columns onto new lines', 'brik-builder' ), 'content', array( 'responsive' => true, 'show_if' => array( 'sizing' => array( 'equal', 'auto' ) ) ) ),
		'gap'         => Fields::field( 'unit', __( 'Column gap', 'brik-builder' ), 'content', array( 'responsive' => true, 'css' => array( Fields::WRAP, 'column-gap' ) ) ),
		'row_gap'     => Fields::field( 'unit', __( 'Row gap', 'brik-builder' ), 'content', array( 'responsive' => true, 'css' => array( Fields::WRAP, 'row-gap' ) ) ),
		'reverse'     => Fields::field( 'toggle', __( 'Reverse order when stacked on phones', 'brik-builder' ), 'content' ),
		'full'        => Fields::field( 'toggle', __( 'Full width', 'brik-builder' ), 'content', array( 'description' => __( 'Ignore the site container width.', 'brik-builder' ) ) ),
	),
	'class'       => static function ( $a ) {
		return ! empty( $a['full'] ) ? 'brik-row--full' : '';
	},
	'css'         => static function ( $a, $wrap, $node ) {
		$ids   = array();
		foreach ( isset( $node['children'] ) ? (array) $node['children'] : array() as $child ) {
			if ( ! empty( $child['id'] ) ) {
				$ids[] = $child['id'];
			}
		}
		$out = '';
		foreach ( array( 'desktop', 'tablet', 'mobile' ) as $state ) {
			$css = brik_row_layout_css( $a, $state, $wrap, $ids );
			if ( 'tablet' === $state ) {
				$css = '@media (max-width:' . Style::TABLET . 'px){' . $css . '}';
			} elseif ( 'mobile' === $state ) {
				$css = '@media (max-width:' . Style::MOBILE . 'px){' . $css . '}';
			}
			$out .= $css;
		}
		return $out;
	},
	'render'      => static function ( $a, $ctx ) {
		return $ctx->children();
	},
);
