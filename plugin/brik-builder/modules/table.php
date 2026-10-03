<?php
/**
 * Table (shadcn/ui table) from rows or pasted CSV.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'table',
	'title'       => __( 'Table', 'brik-builder' ),
	'category'    => 'content',
	'icon'        => 'table',
	'description' => 'Data table. source: rows|csv. rows: header (cells separated by "|") + rows repeater [{cells: "A | B | C"}] + footer (optional, "|" separated). csv: csv textarea, first line is the header when csv_header is on. caption (text below table). striped, hoverable, bordered (outer border + rounded), first_bold, last_right (right-align last column, for amounts).',
	'fields'      => array_merge(
		array(
			'source'     => Fields::field( 'select', __( 'Data source', 'brik-builder' ), 'content', array( 'default' => 'rows', 'options' => Fields::opts( array( 'rows' => __( 'Rows', 'brik-builder' ), 'csv' => __( 'CSV', 'brik-builder' ) ) ) ) ),
			'header'     => Fields::field( 'text', __( 'Header row', 'brik-builder' ), 'content', array( 'default' => __( 'Invoice | Status | Method | Amount', 'brik-builder' ), 'description' => __( 'Separate cells with |', 'brik-builder' ), 'show_if' => array( 'source' => 'rows' ) ) ),
			'rows'       => Fields::field(
				'repeater',
				__( 'Rows', 'brik-builder' ),
				'content',
				array(
					'show_if'     => array( 'source' => 'rows' ),
					'title_field' => 'cells',
					'fields'      => array(
						'cells' => Fields::field( 'text', __( 'Cells', 'brik-builder' ), 'content', array( 'description' => __( 'Separate cells with |', 'brik-builder' ) ) ),
					),
					'default'     => array(
						array( 'cells' => 'INV001 | Paid | Credit Card | $250.00' ),
						array( 'cells' => 'INV002 | Pending | PayPal | $150.00' ),
						array( 'cells' => 'INV003 | Unpaid | Bank Transfer | $350.00' ),
						array( 'cells' => 'INV004 | Paid | Credit Card | $450.00' ),
						array( 'cells' => 'INV005 | Paid | PayPal | $550.00' ),
					),
				)
			),
			'footer'     => Fields::field( 'text', __( 'Footer row', 'brik-builder' ), 'content', array( 'default' => 'Total | | | $1,750.00', 'show_if' => array( 'source' => 'rows' ) ) ),
			'csv'        => Fields::field( 'textarea', __( 'CSV', 'brik-builder' ), 'content', array( 'placeholder' => "Name,Role,Location\nOlivia,Designer,Lisbon", 'show_if' => array( 'source' => 'csv' ) ) ),
			'csv_header' => Fields::field( 'toggle', __( 'First line is the header', 'brik-builder' ), 'content', array( 'default' => true, 'show_if' => array( 'source' => 'csv' ) ) ),
			'caption'    => Fields::field( 'text', __( 'Caption', 'brik-builder' ), 'content', array( 'default' => __( 'A list of your recent invoices.', 'brik-builder' ) ) ),
			'striped'    => Fields::field( 'toggle', __( 'Striped rows', 'brik-builder' ), 'content' ),
			'hoverable'  => Fields::field( 'toggle', __( 'Highlight on hover', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'bordered'   => Fields::field( 'toggle', __( 'Outer border', 'brik-builder' ), 'content' ),
			'first_bold' => Fields::field( 'toggle', __( 'Bold first column', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'last_right' => Fields::field( 'toggle', __( 'Right-align last column', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'header_bg'  => Fields::field( 'color', __( 'Header background', 'brik-builder' ), 'cells', array( 'tab' => 'design', 'group_label' => __( 'Cells', 'brik-builder' ), 'css' => array( Fields::WRAP . ' thead tr', 'background-color' ) ) ),
			'stripe_bg'  => Fields::field( 'color', __( 'Stripe color', 'brik-builder' ), 'cells', array( 'tab' => 'design', 'group_label' => __( 'Cells', 'brik-builder' ), 'show_if' => array( 'striped' => true ), 'css' => array( Fields::WRAP . ' tbody tr:nth-child(even)', 'background-color' ) ) ),
			'row_line'   => Fields::field( 'color', __( 'Row border color', 'brik-builder' ), 'cells', array( 'tab' => 'design', 'group_label' => __( 'Cells', 'brik-builder' ), 'css' => array( Fields::WRAP . ' .brik-table-el tr, ' . Fields::WRAP . ' .brik-table-scroll', 'border-color' ) ) ),
			'cell_pad'   => Fields::field( 'spacing', __( 'Cell padding', 'brik-builder' ), 'cells', array( 'tab' => 'design', 'group_label' => __( 'Cells', 'brik-builder' ), 'responsive' => true, 'css' => array( Fields::WRAP . ' :is(th,td)', 'padding' ) ) ),
		),
		Fields::typography( 'head', __( 'Header cells', 'brik-builder' ), Fields::WRAP . ' thead th' ),
		Fields::typography( 'cell', __( 'Body cells', 'brik-builder' ), Fields::WRAP . ' tbody td' ),
		Fields::typography( 'caption', __( 'Caption', 'brik-builder' ), Fields::WRAP . ' caption' )
	),
	'render'      => static function ( $a, $ctx ) {
		$split = static function ( $line ) {
			return array_map( 'trim', explode( '|', (string) $line ) );
		};

		$head = array();
		$body = array();
		$foot = array();
		if ( 'csv' === $a['source'] ) {
			$rows = brik_parse_csv( $a['csv'] );
			if ( $rows && ! empty( $a['csv_header'] ) ) {
				$head = array_shift( $rows );
			}
			$body = $rows;
		} else {
			if ( '' !== trim( (string) $a['header'] ) ) {
				$head = $split( $a['header'] );
			}
			foreach ( brik_items( $a['rows'] ) as $row ) {
				if ( '' !== trim( (string) brik_item( $row, 'cells' ) ) ) {
					$body[] = $split( $row['cells'] );
				}
			}
			if ( '' !== trim( str_replace( '|', '', (string) $a['footer'] ) ) ) {
				$foot = $split( $a['footer'] );
			}
		}
		if ( ! $head && ! $body ) {
			return $ctx->placeholder( __( 'Add table rows or paste CSV', 'brik-builder' ) );
		}

		$cols = max( count( $head ), count( $foot ), $body ? max( array_map( 'count', $body ) ) : 0 );
		$cell = static function ( $tag, $value, $i, $class ) use ( $cols, $a ) {
			$classes = brik_cls( $class, array( 'text-right' => ! empty( $a['last_right'] ) && $cols > 1 && $i === $cols - 1 ) );
			return '<' . $tag . ' class="' . esc_attr( $classes ) . '"' . ( 'th' === $tag ? ' scope="col"' : '' ) . '>' . brik_inline( $value ) . '</' . $tag . '>';
		};
		$pad  = static function ( array $row ) use ( $cols ) {
			return array_pad( $row, $cols, '' );
		};

		$th   = 'h-10 px-3 text-left align-middle font-medium whitespace-nowrap text-foreground';
		$td   = 'p-3 align-middle';
		$html = '';
		if ( '' !== trim( (string) $a['caption'] ) ) {
			$caption = ! empty( $a['bordered'] ) ? 'border-t py-3' : 'mt-4';
			$html   .= '<caption class="' . esc_attr( 'caption-bottom text-sm text-muted-foreground ' . $caption ) . '"' . $ctx->inline( 'caption' ) . '>' . brik_inline( $a['caption'] ) . '</caption>';
		}
		if ( $head ) {
			$html .= '<thead class="[&_tr]:border-b"><tr class="border-b">';
			foreach ( $pad( $head ) as $i => $value ) {
				$html .= $cell( 'th', $value, $i, $th );
			}
			$html .= '</tr></thead>';
		}
		$row_class = brik_cls(
			'border-b transition-colors',
			array(
				'hover:bg-muted/50'   => ! empty( $a['hoverable'] ),
				'even:bg-muted/40'    => ! empty( $a['striped'] ),
			)
		);
		$html .= '<tbody class="[&_tr:last-child]:border-0">';
		foreach ( $body as $row ) {
			$html .= '<tr class="' . esc_attr( $row_class ) . '">';
			foreach ( $pad( $row ) as $i => $value ) {
				$html .= $cell( 'td', $value, $i, brik_cls( $td, array( 'font-medium' => 0 === $i && ! empty( $a['first_bold'] ) ) ) );
			}
			$html .= '</tr>';
		}
		$html .= '</tbody>';
		if ( $foot ) {
			$html .= '<tfoot class="border-t bg-muted/50 font-medium"><tr>';
			foreach ( $pad( $foot ) as $i => $value ) {
				$html .= $cell( 'td', $value, $i, $td );
			}
			$html .= '</tr></tfoot>';
		}

		$scroll = brik_cls( 'brik-table-scroll relative w-full overflow-x-auto', array( 'rounded-lg border' => ! empty( $a['bordered'] ) ) );
		return '<div class="' . esc_attr( $scroll ) . '"><table class="brik-table-el w-full caption-bottom text-sm">' . $html . '</table></div>';
	},
);
