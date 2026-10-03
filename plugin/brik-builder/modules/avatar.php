<?php
/**
 * Avatar (shadcn/ui avatar) and avatar group.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

$brik_avatar_sizes = array(
	'sm'      => __( 'Small', 'brik-builder' ),
	'default' => __( 'Default', 'brik-builder' ),
	'lg'      => __( 'Large', 'brik-builder' ),
	'xl'      => __( 'Extra large', 'brik-builder' ),
	'2xl'     => '2XL',
	'3xl'     => '3XL',
);

return array(
	'type'        => 'avatar',
	'title'       => __( 'Avatar', 'brik-builder' ),
	'category'    => 'basic',
	'icon'        => 'circle-user',
	'description' => 'User avatar with initials fallback. mode: single|group. single: image, name (alt + initials), subtitle; show_name toggle prints name/subtitle beside it; status: none|online|away|busy|offline. group: people repeater [{image, name}], max (visible count, rest shown as +N). size: sm|default|lg|xl|2xl|3xl. shape: circle|rounded|square. align: left|center|right.',
	'fields'      => array_merge(
		array(
			'mode'      => Fields::field( 'select', __( 'Mode', 'brik-builder' ), 'content', array( 'default' => 'single', 'options' => Fields::opts( array( 'single' => __( 'Single avatar', 'brik-builder' ), 'group' => __( 'Avatar group', 'brik-builder' ) ) ) ) ),
			'image'     => Fields::field( 'image', __( 'Image', 'brik-builder' ), 'content', array( 'show_if' => array( 'mode' => 'single' ) ) ),
			'name'      => Fields::field( 'text', __( 'Name', 'brik-builder' ), 'content', array( 'default' => 'Olivia Martin', 'show_if' => array( 'mode' => 'single' ), 'description' => __( 'Used for the initials when there is no image.', 'brik-builder' ) ) ),
			'subtitle'  => Fields::field( 'text', __( 'Subtitle', 'brik-builder' ), 'content', array( 'default' => 'olivia@example.com', 'show_if' => array( 'mode' => 'single' ) ) ),
			'show_name' => Fields::field( 'toggle', __( 'Show name', 'brik-builder' ), 'content', array( 'default' => true, 'show_if' => array( 'mode' => 'single' ) ) ),
			'status'    => Fields::field( 'select', __( 'Status', 'brik-builder' ), 'content', array( 'default' => 'none', 'show_if' => array( 'mode' => 'single' ), 'options' => Fields::opts( array( 'none' => __( 'None', 'brik-builder' ), 'online' => __( 'Online', 'brik-builder' ), 'away' => __( 'Away', 'brik-builder' ), 'busy' => __( 'Busy', 'brik-builder' ), 'offline' => __( 'Offline', 'brik-builder' ) ) ) ) ),
			'people'    => Fields::field(
				'repeater',
				__( 'People', 'brik-builder' ),
				'content',
				array(
					'show_if'     => array( 'mode' => 'group' ),
					'title_field' => 'name',
					'fields'      => array(
						'image' => Fields::field( 'image', __( 'Image', 'brik-builder' ) ),
						'name'  => Fields::field( 'text', __( 'Name', 'brik-builder' ) ),
					),
					'default'     => array(
						array( 'name' => 'Olivia Martin' ),
						array( 'name' => 'Jackson Lee' ),
						array( 'name' => 'Isabella Nguyen' ),
						array( 'name' => 'William Kim' ),
						array( 'name' => 'Sofia Davis' ),
						array( 'name' => 'Lucas Brown' ),
					),
				)
			),
			'max'       => Fields::field( 'number', __( 'Visible avatars', 'brik-builder' ), 'content', array( 'default' => 4, 'min' => 1, 'max' => 20, 'show_if' => array( 'mode' => 'group' ) ) ),
			'size'      => Fields::field( 'select', __( 'Size', 'brik-builder' ), 'content', array( 'default' => 'lg', 'options' => Fields::opts( $brik_avatar_sizes ) ) ),
			'shape'     => Fields::field( 'select', __( 'Shape', 'brik-builder' ), 'content', array( 'default' => 'circle', 'options' => Fields::opts( array( 'circle' => __( 'Circle', 'brik-builder' ), 'rounded' => __( 'Rounded', 'brik-builder' ), 'square' => __( 'Square', 'brik-builder' ) ) ) ) ),
			'align'     => Fields::field(
				'align',
				__( 'Alignment', 'brik-builder' ),
				'content',
				array(
					'responsive' => true,
					'css'        => array(
						'selector' => Fields::WRAP . ' .brik-avatar-wrap',
						'map'      => array(
							'left'   => 'justify-content:flex-start',
							'center' => 'justify-content:center',
							'right'  => 'justify-content:flex-end',
						),
					),
				)
			),
			'overlap'   => Fields::field( 'unit', __( 'Overlap', 'brik-builder' ), 'avatar_style', array( 'tab' => 'design', 'group_label' => __( 'Avatar', 'brik-builder' ), 'placeholder' => '8px', 'show_if' => array( 'mode' => 'group' ), 'css' => array( 'selector' => Fields::WRAP . ' .brik-avatar-group > * + *', 'prop' => 'margin-left', 'value' => 'calc({{v}} * -1)' ) ) ),
			'avatar_size' => Fields::field( 'unit', __( 'Custom size', 'brik-builder' ), 'avatar_style', array( 'tab' => 'design', 'group_label' => __( 'Avatar', 'brik-builder' ), 'responsive' => true, 'css' => array( Fields::WRAP . ' .brik-avatar', array( 'width', 'height' ) ) ) ),
		),
		Fields::box( 'avatar', __( 'Avatar', 'brik-builder' ), Fields::WRAP . ' .brik-avatar', array( 'bg', 'color', 'border_width', 'border_color', 'radius', 'shadow' ) ),
		Fields::typography( 'name', __( 'Name', 'brik-builder' ), Fields::WRAP . ' .brik-avatar-name' ),
		Fields::typography( 'subtitle', __( 'Subtitle', 'brik-builder' ), Fields::WRAP . ' .brik-avatar-sub' )
	),
	'render'      => static function ( $a, $ctx ) {
		$size  = in_array( $a['size'], array( 'sm', 'default', 'lg', 'xl', '2xl', '3xl' ), true ) ? $a['size'] : 'lg';
		$shape = $a['shape'];

		if ( 'group' === $a['mode'] ) {
			$people = brik_items( $a['people'] );
			$max    = max( 1, (int) $a['max'] );
			$html   = '';
			foreach ( array_slice( $people, 0, $max ) as $p ) {
				$html .= brik_avatar( brik_item( $p, 'image' ), brik_item( $p, 'name' ), $size, $shape, 'ring-2 ring-background' );
			}
			$rest = count( $people ) - $max;
			if ( $rest > 0 ) {
				/* translators: %d: number of hidden people */
				$label = sprintf( _n( '%d more person', '%d more people', $rest, 'brik-builder' ), $rest );
				$html .= brik_avatar( '', $label, $size, $shape, 'ring-2 ring-background', '+' . $rest );
			}
			return '<div class="brik-avatar-wrap flex"><div class="brik-avatar-group flex [&>*+*]:-ml-2">' . $html . '</div></div>';
		}

		$status = array(
			'online'  => 'bg-emerald-500',
			'away'    => 'bg-amber-400',
			'busy'    => 'bg-destructive',
			'offline' => 'bg-muted-foreground/60',
		);
		$avatar = brik_avatar( $a['image'], $a['name'], $size, $shape );
		if ( isset( $status[ $a['status'] ] ) ) {
			$dots   = array(
				'sm'      => 'size-2',
				'default' => 'size-2.5',
				'lg'      => 'size-3',
				'xl'      => 'size-3.5',
				'2xl'     => 'size-5',
				'3xl'     => 'size-6',
			);
			$avatar = '<span class="relative inline-flex shrink-0">' . $avatar . '<span class="' . esc_attr( brik_cls( 'brik-avatar-status absolute right-0 bottom-0 rounded-full ring-2 ring-background', $status[ $a['status'] ], $dots[ $size ], 'circle' === $shape ? '' : 'translate-x-1/3 translate-y-1/3' ) ) . '"><span class="sr-only">' . esc_html( ucfirst( $a['status'] ) ) . '</span></span></span>';
		}

		$text = '';
		if ( ! empty( $a['show_name'] ) && ( '' !== trim( (string) $a['name'] ) || '' !== trim( (string) $a['subtitle'] ) ) ) {
			$text = '<span class="grid min-w-0 gap-0.5 text-left">';
			if ( '' !== trim( (string) $a['name'] ) ) {
				$text .= '<span class="brik-avatar-name truncate text-sm font-medium leading-none"' . $ctx->inline( 'name' ) . '>' . brik_inline( $a['name'] ) . '</span>';
			}
			if ( '' !== trim( (string) $a['subtitle'] ) ) {
				$text .= '<span class="brik-avatar-sub truncate text-sm text-muted-foreground"' . $ctx->inline( 'subtitle' ) . '>' . brik_inline( $a['subtitle'] ) . '</span>';
			}
			$text .= '</span>';
		}
		return '<div class="brik-avatar-wrap flex"><div class="inline-flex min-w-0 items-center gap-3">' . $avatar . $text . '</div></div>';
	},
);
