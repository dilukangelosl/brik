<?php
/**
 * Countdown timer to a date in the site's timezone.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'countdown',
	'title'       => __( 'Countdown', 'brik-builder' ),
	'category'    => 'interactive',
	'icon'        => 'timer',
	'description' => 'Live countdown. date: target "YYYY-MM-DD HH:MM" in the site timezone (empty = 7 days from now, for previews). title (optional heading above), style: boxes|cards|plain, show_days/show_seconds (bool), separator (bool, colons between units), label_days/label_hours/label_minutes/label_seconds, expired: message|hide|zero, expired_message, align: left|center|right.',
	'fields'      => array_merge(
		array(
			'date'            => Fields::field( 'date', __( 'Count down to', 'brik-builder' ), 'content', array( 'description' => __( 'Uses the site timezone.', 'brik-builder' ) ) ),
			'title'           => Fields::field( 'text', __( 'Title', 'brik-builder' ), 'content', array( 'default' => __( 'Early-bird pricing ends in', 'brik-builder' ), 'inline' => true ) ),
			'style'           => Fields::field( 'select', __( 'Style', 'brik-builder' ), 'content', array( 'default' => 'boxes', 'options' => Fields::opts( array( 'boxes' => __( 'Boxes', 'brik-builder' ), 'cards' => __( 'Cards', 'brik-builder' ), 'plain' => __( 'Plain', 'brik-builder' ) ) ) ) ),
			'show_days'       => Fields::field( 'toggle', __( 'Show days', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'show_seconds'    => Fields::field( 'toggle', __( 'Show seconds', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'separator'       => Fields::field( 'toggle', __( 'Separators', 'brik-builder' ), 'content', array( 'default' => false ) ),
			'label_days'      => Fields::field( 'text', __( 'Days label', 'brik-builder' ), 'labels', array( 'default' => __( 'Days', 'brik-builder' ) ) ),
			'label_hours'     => Fields::field( 'text', __( 'Hours label', 'brik-builder' ), 'labels', array( 'default' => __( 'Hours', 'brik-builder' ) ) ),
			'label_minutes'   => Fields::field( 'text', __( 'Minutes label', 'brik-builder' ), 'labels', array( 'default' => __( 'Minutes', 'brik-builder' ) ) ),
			'label_seconds'   => Fields::field( 'text', __( 'Seconds label', 'brik-builder' ), 'labels', array( 'default' => __( 'Seconds', 'brik-builder' ) ) ),
			'expired'         => Fields::field( 'select', __( 'When finished', 'brik-builder' ), 'content', array( 'default' => 'message', 'options' => Fields::opts( array( 'message' => __( 'Show a message', 'brik-builder' ), 'hide' => __( 'Hide the countdown', 'brik-builder' ), 'zero' => __( 'Show zeros', 'brik-builder' ) ) ) ) ),
			'expired_message' => Fields::field( 'text', __( 'Finished message', 'brik-builder' ), 'content', array( 'default' => __( 'This offer has ended.', 'brik-builder' ), 'show_if' => array( 'expired' => 'message' ) ) ),
			'align'           => Fields::field(
				'select',
				__( 'Alignment', 'brik-builder' ),
				'content',
				array(
					'default'    => 'center',
					'responsive' => true,
					'options'    => Fields::opts( array( 'left' => __( 'Left', 'brik-builder' ), 'center' => __( 'Center', 'brik-builder' ), 'right' => __( 'Right', 'brik-builder' ) ) ),
					'css'        => array(
						'selector' => Fields::WRAP . ' .brik-countdown',
						'map'      => array(
							'left'   => 'justify-items:start;text-align:left',
							'center' => 'justify-items:center;text-align:center',
							'right'  => 'justify-items:end;text-align:right',
						),
					),
				)
			),
		),
		Fields::typography( 'title', __( 'Title', 'brik-builder' ), Fields::WRAP . ' .brik-countdown-title' ),
		Fields::box( 'unit', __( 'Unit boxes', 'brik-builder' ), Fields::WRAP . ' .brik-countdown-unit' ),
		Fields::typography( 'number', __( 'Numbers', 'brik-builder' ), Fields::WRAP . ' .brik-countdown-number' ),
		Fields::typography( 'label', __( 'Labels', 'brik-builder' ), Fields::WRAP . ' .brik-countdown-label' )
	),
	'render'      => static function ( array $a, Brik\Context $ctx ) {
		$tz     = wp_timezone();
		$target = null;
		if ( '' !== trim( (string) $a['date'] ) ) {
			try {
				$target = new DateTimeImmutable( str_replace( 'T', ' ', (string) $a['date'] ), $tz );
			} catch ( Exception $e ) {
				$target = null;
			}
		}
		if ( ! $target ) {
			if ( '' !== trim( (string) $a['date'] ) && $ctx->canvas ) {
				return $ctx->placeholder( __( 'Enter a valid date, e.g. 2026-12-31 18:00', 'brik-builder' ) );
			}
			// No date yet: a rolling week keeps the default preview alive.
			$target = ( new DateTimeImmutable( 'now', $tz ) )->setTime( 0, 0 )->modify( '+7 days' );
		}

		$ts   = $target->getTimestamp();
		$left = max( 0, $ts - time() );
		$done = 0 === $left;

		if ( $done && 'hide' === $a['expired'] && ! $ctx->canvas ) {
			return '';
		}

		$show_days = brik_form_bool( $a['show_days'] );
		$show_secs = brik_form_bool( $a['show_seconds'] );
		$parts     = array(
			'days'    => array( (int) floor( $left / DAY_IN_SECONDS ), $a['label_days'] ),
			'hours'   => array( (int) floor( $left / HOUR_IN_SECONDS ) % ( $show_days ? 24 : PHP_INT_MAX ), $a['label_hours'] ),
			'minutes' => array( (int) floor( $left / MINUTE_IN_SECONDS ) % 60, $a['label_minutes'] ),
			'seconds' => array( $left % 60, $a['label_seconds'] ),
		);
		if ( ! $show_days ) {
			unset( $parts['days'] );
		}
		if ( ! $show_secs ) {
			unset( $parts['seconds'] );
		}

		$style  = in_array( $a['style'], array( 'boxes', 'cards', 'plain' ), true ) ? $a['style'] : 'boxes';
		$unit   = array(
			'boxes' => 'brik-countdown-unit grid min-w-16 gap-1 rounded-lg border bg-background px-3 py-3 shadow-xs sm:min-w-20 sm:px-4',
			'cards' => 'brik-countdown-unit grid min-w-16 gap-1 rounded-xl bg-primary px-3 py-4 text-primary-foreground shadow-sm sm:min-w-22 sm:px-5',
			'plain' => 'brik-countdown-unit grid min-w-12 gap-0.5 px-1',
		);
		$number = array(
			'boxes' => 'brik-countdown-number font-heading text-3xl font-semibold tracking-tight tabular-nums sm:text-4xl',
			'cards' => 'brik-countdown-number font-heading text-3xl font-bold tracking-tight tabular-nums sm:text-5xl',
			'plain' => 'brik-countdown-number font-heading text-4xl font-bold tracking-tight tabular-nums sm:text-6xl',
		);
		$label  = array(
			'boxes' => 'brik-countdown-label text-xs font-medium tracking-wide text-muted-foreground uppercase',
			'cards' => 'brik-countdown-label text-xs font-medium tracking-wide uppercase opacity-80',
			'plain' => 'brik-countdown-label text-xs font-medium tracking-wide text-muted-foreground uppercase',
		);

		$units = '';
		$first = true;
		foreach ( $parts as $key => $part ) {
			if ( ! $first && brik_form_bool( $a['separator'] ) ) {
				$units .= '<span class="brik-countdown-sep self-start pt-2 font-heading text-3xl font-semibold text-muted-foreground/60 sm:text-4xl" aria-hidden="true">:</span>';
			}
			$first  = false;
			$units .= '<div class="' . esc_attr( $unit[ $style ] ) . '"><span class="' . esc_attr( $number[ $style ] ) . '" data-unit="' . esc_attr( $key ) . '">' . esc_html( str_pad( (string) $part[0], 2, '0', STR_PAD_LEFT ) ) . '</span>'
				. '<span class="' . esc_attr( $label[ $style ] ) . '">' . esc_html( $part[1] ) . '</span></div>';
		}

		$hide    = $done && 'message' === $a['expired'];
		$title   = '' !== trim( (string) $a['title'] ) ? '<p class="brik-countdown-title text-sm font-medium text-muted-foreground"' . ( $hide ? ' hidden' : '' ) . $ctx->inline( 'title' ) . '>' . brik_inline( $a['title'] ) . '</p>' : '';
		$message = 'message' === $a['expired'] ? '<p class="brik-countdown-expired text-lg font-medium"' . ( $done ? '' : ' hidden' ) . $ctx->inline( 'expired_message' ) . '>' . brik_inline( $a['expired_message'] ) . '</p>' : '';

		return '<div' . brik_attrs(
			array(
				'class'             => 'brik-countdown grid justify-items-center gap-4 text-center',
				'data-brik-countdown' => true,
				'data-target'       => $ts * 1000,
				'data-expired'      => $a['expired'],
				'data-days'         => $show_days ? '1' : '0',
			)
		) . '>' . $title
			. '<div class="brik-countdown-units flex flex-wrap items-stretch justify-center gap-2 sm:gap-3" role="timer" aria-live="off"' . ( $hide ? ' hidden' : '' ) . '>' . $units . '</div>'
			. $message . '</div>';
	},
);
