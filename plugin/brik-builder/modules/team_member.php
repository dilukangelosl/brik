<?php
/**
 * Team member: photo, name, role, bio and social links.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'team_member',
	'title'       => __( 'Team Member', 'brik-builder' ),
	'category'    => 'content',
	'icon'        => 'id-card',
	'description' => 'Person card. photo (image; initials fallback), photo_style: avatar|cover (full-width photo), name, role, bio, socials repeater [{network: brand slug from brands list e.g. x|linkedin|github|instagram|dribbble|email|website, url}]. style: card|plain. align: center|left.',
	'fields'      => array_merge(
		array(
			'photo'       => Fields::field( 'image', __( 'Photo', 'brik-builder' ), 'content' ),
			'photo_style' => Fields::field( 'select', __( 'Photo style', 'brik-builder' ), 'content', array( 'default' => 'avatar', 'options' => Fields::opts( array( 'avatar' => __( 'Avatar', 'brik-builder' ), 'cover' => __( 'Full width', 'brik-builder' ) ) ) ) ),
			'name'        => Fields::field( 'text', __( 'Name', 'brik-builder' ), 'content', array( 'default' => 'Jackson Lee', 'inline' => true ) ),
			'role'        => Fields::field( 'text', __( 'Role', 'brik-builder' ), 'content', array( 'default' => __( 'Co-founder & CTO', 'brik-builder' ), 'inline' => true ) ),
			'bio'         => Fields::field( 'textarea', __( 'Bio', 'brik-builder' ), 'content', array( 'default' => __( 'Previously led platform engineering at a fintech scale-up. Loves type systems, trail running and very strong coffee.', 'brik-builder' ), 'inline' => true ) ),
			'socials'     => Fields::field(
				'repeater',
				__( 'Social links', 'brik-builder' ),
				'content',
				array(
					'title_field' => 'network',
					'fields'      => array(
						'network' => Fields::field( 'select', __( 'Network', 'brik-builder' ), 'content', array( 'default' => 'x', 'options' => Fields::opts( brik_brand_options() ) ) ),
						'url'     => Fields::field( 'text', __( 'URL', 'brik-builder' ), 'content', array( 'placeholder' => 'https://' ) ),
					),
					'default'     => array(
						array( 'network' => 'x', 'url' => '#' ),
						array( 'network' => 'linkedin', 'url' => '#' ),
						array( 'network' => 'github', 'url' => '#' ),
					),
				)
			),
			'style'       => Fields::field( 'select', __( 'Style', 'brik-builder' ), 'content', array( 'default' => 'card', 'options' => Fields::opts( array( 'card' => __( 'Card', 'brik-builder' ), 'plain' => __( 'Plain', 'brik-builder' ) ) ) ) ),
			'align'       => Fields::field( 'select', __( 'Alignment', 'brik-builder' ), 'content', array( 'default' => 'center', 'options' => Fields::opts( array( 'center' => __( 'Center', 'brik-builder' ), 'left' => __( 'Left', 'brik-builder' ) ) ) ) ),
			'social_color' => Fields::field( 'color', __( 'Social icon color', 'brik-builder' ), 'card', array( 'tab' => 'design', 'group_label' => __( 'Card', 'brik-builder' ), 'hover' => true, 'css' => array( 'selector' => Fields::WRAP . ' .brik-team-social', 'prop' => 'color', 'hover_selector' => Fields::WRAP . ' .brik-team-social:hover' ) ) ),
		),
		Fields::box( 'card', __( 'Card', 'brik-builder' ), Fields::WRAP . ' .brik-team-box' ),
		Fields::box( 'photo', __( 'Photo', 'brik-builder' ), Fields::WRAP . ' .brik-team-photo', array( 'border_width', 'border_color', 'radius', 'shadow' ) ),
		Fields::typography( 'name', __( 'Name', 'brik-builder' ), Fields::WRAP . ' .brik-team-name' ),
		Fields::typography( 'role', __( 'Role', 'brik-builder' ), Fields::WRAP . ' .brik-team-role' ),
		Fields::typography( 'bio', __( 'Bio', 'brik-builder' ), Fields::WRAP . ' .brik-team-bio' )
	),
	'render'      => static function ( $a, $ctx ) {
		$card   = 'plain' !== $a['style'];
		$center = 'left' !== $a['align'];
		$cover  = 'cover' === $a['photo_style'] && brik_has_image( $a['photo'] );

		if ( $cover ) {
			$photo = '<div class="' . esc_attr( brik_cls( 'brik-team-photo overflow-hidden bg-muted', $card ? '' : 'rounded-xl' ) ) . '">' . brik_image( $a['photo'], 'large', array( 'class' => 'aspect-[4/5] w-full object-cover', 'alt' => wp_strip_all_tags( (string) $a['name'] ) ) ) . '</div>';
		} else {
			$photo = brik_avatar( $a['photo'], $a['name'], '3xl', 'circle', 'brik-team-photo' );
		}

		$text = '';
		if ( '' !== trim( (string) $a['name'] ) ) {
			$text .= '<h3 class="brik-team-name font-heading text-lg leading-snug font-semibold tracking-tight"' . $ctx->inline( 'name' ) . '>' . brik_inline( $a['name'] ) . '</h3>';
		}
		if ( '' !== trim( (string) $a['role'] ) ) {
			$text .= '<p class="brik-team-role text-sm font-medium text-primary"' . $ctx->inline( 'role' ) . '>' . brik_inline( $a['role'] ) . '</p>';
		}
		if ( '' !== trim( (string) $a['bio'] ) ) {
			$text .= '<p class="brik-team-bio mt-2 text-sm leading-relaxed text-muted-foreground"' . $ctx->inline( 'bio' ) . '>' . nl2br( brik_inline( $a['bio'] ) ) . '</p>';
		}

		$links = '';
		foreach ( brik_items( $a['socials'] ) as $s ) {
			$brand = brik_brand( brik_item( $s, 'network', 'website' ) );
			$url   = brik_item( $s, 'url', '#' );
			if ( 'email' === brik_item( $s, 'network' ) && false === strpos( $url, ':' ) && is_email( $url ) ) {
				$url = 'mailto:' . $url;
			}
			$links .= '<a class="brik-team-social inline-flex size-8 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-foreground outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50" href="' . esc_url( $url ) . '" aria-label="' . esc_attr( $brand['title'] ) . '" target="_blank" rel="noopener">' . brik_icon( $brand['icon'], 'size-4' ) . '</a>';
		}
		if ( '' !== $links ) {
			$text .= '<div class="' . esc_attr( brik_cls( 'mt-3 flex flex-wrap gap-1', array( 'justify-center' => $center, '-ml-2' => ! $center ) ) ) . '">' . $links . '</div>';
		}

		$body_pad = $cover && $card ? 'p-6' : '';
		$box      = brik_cls(
			'brik-team-box flex h-full flex-col gap-5 overflow-hidden',
			array(
				'rounded-xl border bg-card text-card-foreground shadow-sm' => $card,
				'p-6'                    => $card && ! $cover,
				'items-center text-center' => $center,
			)
		);
		return '<div class="' . esc_attr( $box ) . '">' . $photo . '<div class="' . esc_attr( brik_cls( 'brik-team-body grid w-full gap-1', $body_pad, $cover && $card ? 'pt-0' : '' ) ) . '">' . $text . '</div></div>';
	},
);
