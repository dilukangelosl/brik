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
	'title'       => __( 'Text', 'brik-builder' ),
	'category'    => 'basic',
	'icon'        => 'type',
	'description' => 'Rich text block (HTML: p, ul, ol, blockquote, a, strong, h2-h4, code). Styled like shadcn typography.',
	'fields'      => array_merge(
		array(
			'content' => Fields::field( 'richtext', __( 'Content', 'brik-builder' ), 'content', array( 'default' => '<p>' . __( 'Write something great. Select this text to edit it right on the page.', 'brik-builder' ) . '</p>', 'inline' => true ) ),
			'size'    => Fields::field( 'select', __( 'Style', 'brik-builder' ), 'content', array( 'options' => Fields::opts( array( '' => __( 'Body', 'brik-builder' ), 'lead' => __( 'Lead', 'brik-builder' ), 'large' => __( 'Large', 'brik-builder' ), 'small' => __( 'Small', 'brik-builder' ), 'muted' => __( 'Muted', 'brik-builder' ) ) ) ) ),
			'columns' => Fields::field( 'number', __( 'Text columns', 'brik-builder' ), 'content', array( 'responsive' => true, 'min' => 1, 'max' => 4, 'css' => array( Fields::WRAP . ' .brik-prose', 'columns' ) ) ),
			'dropcap' => Fields::field( 'toggle', __( 'Drop cap', 'brik-builder' ), 'content' ),
		),
		Fields::typography( 'link', __( 'Links', 'brik-builder' ), Fields::WRAP . ' .brik-prose a' ),
		Fields::typography( 'headings', __( 'Headings inside text', 'brik-builder' ), Fields::WRAP . ' .brik-prose :is(h1,h2,h3,h4,h5,h6)' )
	),
	'render'      => static function ( $a, $ctx ) {
		$sizes = array(
			'lead'  => 'text-xl text-muted-foreground',
			'large' => 'text-lg font-semibold',
			'small' => 'text-sm',
			'muted' => 'text-sm text-muted-foreground',
		);
		$class = brik_cls( 'brik-prose', isset( $sizes[ $a['size'] ] ) ? $sizes[ $a['size'] ] : '', array( 'brik-dropcap' => ! empty( $a['dropcap'] ) ) );
		return '<div class="' . esc_attr( $class ) . '"' . $ctx->inline( 'content' ) . '>' . brik_rich( $a['content'] ) . '</div>';
	},
);
