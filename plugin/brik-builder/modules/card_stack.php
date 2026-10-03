<?php
/**
 * Card stack: cards piled on top of each other; the front card moves to the back on a timer.
 *
 * @package Brik
 * @author  Diluk Angelo
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

$brik_stack_people = array(
	array( '1494790108377-be9c29b29330', 'Maya Chen', __( 'Head of Design, Northwind', 'brik-builder' ), __( 'We rebuilt our marketing site in a week. The team actually enjoys making pages now, and it shows in how often we ship.', 'brik-builder' ) ),
	array( '1507003211169-0a1dd7228f2d', 'Daniel Okafor', __( 'Founder, Lumen Labs', 'brik-builder' ), __( 'It feels like a design tool, but the output is clean, fast HTML. Our Lighthouse scores went up, not down.', 'brik-builder' ) ),
	array( '1438761681033-6461ffad8d80', 'Sofia Martins', __( 'Marketing Lead, Evergreen', 'brik-builder' ), __( 'Landing pages used to wait on a developer. Now I launch a campaign page before lunch and test two versions by the afternoon.', 'brik-builder' ) ),
	array( '1500648767791-00dcc994a43e', 'James Whitfield', __( 'CTO, Orbital', 'brik-builder' ), __( 'Finally a builder that respects the codebase. Tokens, components and no surprise markup when we hand it over.', 'brik-builder' ) ),
);
$brik_stack_default = array();
foreach ( $brik_stack_people as $brik_person ) {
	$brik_stack_default[] = array(
		'quote'  => $brik_person[3],
		'name'   => $brik_person[1],
		'role'   => $brik_person[2],
		'avatar' => array( 'url' => brik_sample_image( $brik_person[0], 160, 160 ), 'alt' => $brik_person[1] ),
		'icon'   => '',
	);
}

return array(
	'type'        => 'card_stack',
	'title'       => __( 'Card Stack', 'brik-builder' ),
	'category'    => 'effects',
	'icon'        => 'square-stack',
	'description' => 'Stacked cards; every few seconds the front card moves to the back. variant: testimonial (quote, avatar, name, role) | feature (icon, name as title, quote as text). items: repeater of {quote, name, role, avatar, icon}. interval: seconds (2-15). autoplay: bool. visible: cards peeking behind (1-4). offset: px between cards. pause_on_hover: bool. controls: bool, previous/next buttons. stack_width: CSS length. Click or press Enter on the stack to advance. No autoplay with reduced motion or in the builder.',
	'fields'      => array_merge(
		array(
			'variant'        => Fields::field( 'select', __( 'Card type', 'brik-builder' ), 'content', array( 'default' => 'testimonial', 'options' => Fields::opts( array( 'testimonial' => __( 'Testimonial', 'brik-builder' ), 'feature' => __( 'Feature', 'brik-builder' ) ) ) ) ),
			'items'          => Fields::field(
				'repeater',
				__( 'Cards', 'brik-builder' ),
				'content',
				array(
					'title_field' => 'name',
					'default'     => $brik_stack_default,
					'fields'      => array(
						'quote'  => Fields::field( 'textarea', __( 'Quote / text', 'brik-builder' ), 'content' ),
						'name'   => Fields::field( 'text', __( 'Name / title', 'brik-builder' ), 'content' ),
						'role'   => Fields::field( 'text', __( 'Role / caption', 'brik-builder' ), 'content' ),
						'avatar' => Fields::field( 'image', __( 'Avatar', 'brik-builder' ), 'content' ),
						'icon'   => Fields::field( 'icon', __( 'Icon (feature cards)', 'brik-builder' ), 'content' ),
					),
				)
			),
			'autoplay'       => Fields::field( 'toggle', __( 'Autoplay', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'interval'       => Fields::field( 'range', __( 'Interval (seconds)', 'brik-builder' ), 'content', array( 'default' => 5, 'min' => 2, 'max' => 15, 'step' => 0.5, 'show_if' => array( 'autoplay' => '!' ) ) ),
			'pause_on_hover' => Fields::field( 'toggle', __( 'Pause on hover', 'brik-builder' ), 'content', array( 'default' => true, 'show_if' => array( 'autoplay' => '!' ) ) ),
			'controls'       => Fields::field( 'toggle', __( 'Previous / next buttons', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'visible'        => Fields::field( 'number', __( 'Cards peeking behind', 'brik-builder' ), 'stack_style', array( 'tab' => 'design', 'group_label' => __( 'Stack', 'brik-builder' ), 'default' => 2, 'min' => 1, 'max' => 4 ) ),
			'offset'         => Fields::field( 'range', __( 'Offset between cards', 'brik-builder' ), 'stack_style', array( 'tab' => 'design', 'group_label' => __( 'Stack', 'brik-builder' ), 'default' => 14, 'min' => 4, 'max' => 40, 'unit' => 'px' ) ),
			'stack_width'    => Fields::field( 'unit', __( 'Stack width', 'brik-builder' ), 'stack_style', array( 'tab' => 'design', 'group_label' => __( 'Stack', 'brik-builder' ), 'default' => '30rem', 'responsive' => true, 'css' => array( Fields::WRAP . ' .brik-cardstack', 'max-width' ) ) ),
		),
		Fields::box( 'card', __( 'Card', 'brik-builder' ), Fields::WRAP . ' .brik-cardstack-card' ),
		Fields::typography( 'quote', __( 'Quote / text', 'brik-builder' ), Fields::WRAP . ' .brik-cardstack-quote' ),
		Fields::typography( 'name', __( 'Name / title', 'brik-builder' ), Fields::WRAP . ' .brik-cardstack-name' )
	),
	'render'      => static function ( $a, $ctx ) {
		$items = brik_3d_rows( $a['items'] );
		if ( ! $items ) {
			return $ctx->placeholder( __( 'Add cards', 'brik-builder' ) );
		}
		$ctx->script( 'card-stack' );

		$feature = 'feature' === $a['variant'];
		$count   = count( $items );
		$cards   = '';
		foreach ( $items as $i => $item ) {
			$item = array_merge( array( 'quote' => '', 'name' => '', 'role' => '', 'avatar' => '', 'icon' => '' ), $item );
			if ( $feature ) {
				$body = ( $item['icon'] ? '<span class="mb-6 inline-flex size-11 items-center justify-center rounded-xl bg-primary text-primary-foreground">' . brik_icon( $item['icon'], 'size-5' ) . '</span>' : '' )
					. ( '' !== (string) $item['name'] ? '<p class="brik-cardstack-name text-lg font-semibold tracking-tight">' . brik_inline( $item['name'] ) . '</p>' : '' )
					. ( '' !== (string) $item['quote'] ? '<p class="brik-cardstack-quote mt-2 text-sm leading-relaxed text-muted-foreground">' . esc_html( $item['quote'] ) . '</p>' : '' )
					. ( '' !== (string) $item['role'] ? '<p class="mt-6 text-xs font-medium tracking-wider text-muted-foreground uppercase">' . esc_html( $item['role'] ) . '</p>' : '' );
			} else {
				$avatar = brik_image( $item['avatar'], 'thumbnail', array( 'class' => 'size-11 shrink-0 rounded-full object-cover', 'alt' => '' ) );
				$body   = brik_icon( 'quote', 'mb-4 size-7 text-muted-foreground/40' )
					. ( '' !== (string) $item['quote'] ? '<blockquote class="brik-cardstack-quote text-base leading-relaxed text-pretty md:text-lg">' . esc_html( $item['quote'] ) . '</blockquote>' : '' )
					. '<div class="mt-6 flex items-center gap-3">' . $avatar . '<div class="min-w-0">'
					. ( '' !== (string) $item['name'] ? '<p class="brik-cardstack-name text-sm font-semibold">' . brik_inline( $item['name'] ) . '</p>' : '' )
					. ( '' !== (string) $item['role'] ? '<p class="text-xs text-muted-foreground">' . esc_html( $item['role'] ) . '</p>' : '' )
					. '</div></div>';
			}
			/* translators: 1: card number, 2: total cards */
			$label  = sprintf( __( '%1$d of %2$d', 'brik-builder' ), $i + 1, $count );
			$cards .= '<li class="brik-cardstack-card flex flex-col rounded-2xl border bg-card p-6 text-card-foreground shadow-xl md:p-8" style="--i:' . (int) $i . '" role="group" aria-roledescription="' . esc_attr__( 'card', 'brik-builder' ) . '" aria-label="' . esc_attr( $label ) . '"' . ( $i ? ' aria-hidden="true"' : '' ) . '>' . $body . '</li>';
		}

		$visible  = (int) brik_3d_num( $a['visible'], 2, 1, 4 );
		$offset   = (int) brik_3d_num( $a['offset'], 14, 4, 40 );
		$controls = '';
		if ( ! empty( $a['controls'] ) && $count > 1 ) {
			$btn      = 'brik-cardstack-btn inline-flex size-9 items-center justify-center rounded-full border bg-background text-foreground shadow-xs transition-colors outline-none hover:bg-accent focus-visible:ring-[3px] focus-visible:ring-ring/50';
			$controls = sprintf(
				'<div class="brik-cardstack-nav mt-6 flex items-center justify-center gap-2"><button type="button" class="%1$s" data-dir="prev" aria-label="%2$s">%3$s</button><button type="button" class="%1$s" data-dir="next" aria-label="%4$s">%5$s</button></div>',
				esc_attr( $btn ),
				esc_attr__( 'Previous card', 'brik-builder' ),
				brik_icon( 'chevron-left', 'size-4' ),
				esc_attr__( 'Next card', 'brik-builder' ),
				brik_icon( 'chevron-right', 'size-4' )
			);
		}

		return sprintf(
			'<div class="brik-cardstack mx-auto w-full max-w-[30rem]" role="region" aria-roledescription="%1$s" aria-label="%2$s" data-interval="%3$d"%4$s%5$s style="%6$s"><ol class="brik-cardstack-cards" tabindex="0" aria-live="%9$s">%7$s</ol>%8$s</div>',
			esc_attr__( 'carousel', 'brik-builder' ),
			esc_attr( $feature ? __( 'Features', 'brik-builder' ) : __( 'Testimonials', 'brik-builder' ) ),
			(int) ( brik_3d_num( $a['interval'], 5, 2, 15 ) * 1000 ),
			! empty( $a['autoplay'] ) ? ' data-autoplay' : '',
			! empty( $a['pause_on_hover'] ) ? ' data-pause-hover' : '',
			esc_attr( '--brik-cardstack-visible:' . $visible . ';--brik-cardstack-offset:' . $offset . 'px;--brik-cardstack-count:' . $count ),
			$cards,
			$controls,
			// Announcing every automatic change would be noisy; the script turns this on once the visitor takes over.
			! empty( $a['autoplay'] ) ? 'off' : 'polite'
		);
	},
);
