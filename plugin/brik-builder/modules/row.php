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
	'title'       => __( 'Row', 'brik' ),
	'category'    => 'structure',
	'icon'        => 'columns-3',
	'structural'  => true,
	'children'    => array( 'column' ),
	'description' => 'Grid of columns. "columns" is a comma list of fractions matching the number of child columns, e.g. "1/3,2/3". On mobile columns stack unless columns@mobile is set.',
	'fields'      => array(
		'columns'     => Fields::field( 'columns', __( 'Column structure', 'brik' ), 'content', array( 'default' => '1', 'responsive' => true, 'options' => Fields::opts( $brik_layouts ) ) ),
		'gap'         => Fields::field( 'unit', __( 'Column gap', 'brik' ), 'content', array( 'responsive' => true, 'css' => array( Fields::WRAP, 'column-gap' ) ) ),
		'row_gap'     => Fields::field( 'unit', __( 'Row gap (when wrapped)', 'brik' ), 'content', array( 'responsive' => true, 'css' => array( Fields::WRAP, 'row-gap' ) ) ),
		'align_items' => Fields::field( 'select', __( 'Vertical alignment', 'brik' ), 'content', array( 'responsive' => true, 'options' => Fields::opts( array( '' => __( 'Stretch', 'brik' ), 'start' => __( 'Top', 'brik' ), 'center' => __( 'Middle', 'brik' ), 'end' => __( 'Bottom', 'brik' ) ) ), 'css' => array( Fields::WRAP, 'align-items' ) ) ),
		'reverse'     => Fields::field( 'toggle', __( 'Reverse column order on mobile', 'brik' ), 'content' ),
		'full'        => Fields::field( 'toggle', __( 'Full width', 'brik' ), 'content', array( 'description' => __( 'Ignore the site container width.', 'brik' ) ) ),
	),
	'class'       => static function ( $a ) {
		return brik_cls(
			array(
				'brik-row--full'    => ! empty( $a['full'] ),
				'brik-row--reverse' => ! empty( $a['reverse'] ),
			)
		);
	},
	'css'         => static function ( $a, $wrap ) {
		$out = '';
		foreach ( array( 'desktop', 'tablet', 'mobile' ) as $state ) {
			$v = Style::raw_value( $a, 'columns', $state );
			if ( null === $v ) {
				continue;
			}
			$rule = $wrap . '{grid-template-columns:' . brik_grid_template( $v ) . '}';
			if ( 'tablet' === $state ) {
				$rule = '@media (max-width:' . Style::TABLET . 'px){' . $rule . '}';
			} elseif ( 'mobile' === $state ) {
				$rule = '@media (max-width:' . Style::MOBILE . 'px){' . $rule . '}';
			} else {
				// Columns stack on phones unless a mobile structure is set.
				$rule = '@media (min-width:' . ( Style::MOBILE + 1 ) . 'px){' . $rule . '}';
			}
			$out .= $rule;
		}
		return $out;
	},
	'render'      => static function ( $a, $ctx ) {
		return $ctx->children();
	},
);
