<?php
/**
 * Rich text.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'text',
	'title'       => __( 'Text', 'brik' ),
	'category'    => 'basic',
	'icon'        => 'text',
	'description' => 'Rich text block (HTML: p, ul, ol, blockquote, a, strong, h2-h4, code). Styled like shadcn typography.',
	'fields'      => array_merge(
		array(
			'content' => Fields::field( 'richtext', __( 'Content', 'brik' ), 'content', array( 'default' => '<p>' . __( 'Write something great. Select this text to edit it right on the page.', 'brik' ) . '</p>', 'inline' => true ) ),
			'size'    => Fields::field( 'select', __( 'Style', 'brik' ), 'content', array( 'options' => Fields::opts( array( '' => __( 'Body', 'brik' ), 'lead' => __( 'Lead', 'brik' ), 'large' => __( 'Large', 'brik' ), 'small' => __( 'Small', 'brik' ), 'muted' => __( 'Muted', 'brik' ) ) ) ) ),
			'columns' => Fields::field( 'number', __( 'Text columns', 'brik' ), 'content', array( 'responsive' => true, 'min' => 1, 'max' => 4, 'css' => array( Fields::WRAP . ' .brik-prose', 'columns' ) ) ),
			'dropcap' => Fields::field( 'toggle', __( 'Drop cap', 'brik' ), 'content' ),
		),
		Fields::typography( 'link', __( 'Links', 'brik' ), Fields::WRAP . ' .brik-prose a' ),
		Fields::typography( 'headings', __( 'Headings inside text', 'brik' ), Fields::WRAP . ' .brik-prose :is(h1,h2,h3,h4,h5,h6)' )
	),
	'render'      => static function ( $a ) {
		$sizes = array(
			'lead'  => 'text-xl text-muted-foreground',
			'large' => 'text-lg font-semibold',
			'small' => 'text-sm',
			'muted' => 'text-sm text-muted-foreground',
		);
		$class = brik_cls( 'brik-prose', isset( $sizes[ $a['size'] ] ) ? $sizes[ $a['size'] ] : '', array( 'brik-dropcap' => ! empty( $a['dropcap'] ) ) );
		return '<div class="' . esc_attr( $class ) . '">' . brik_rich( $a['content'] ) . '</div>';
	},
);
