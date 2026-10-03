<?php
/**
 * Pricing table: plans side by side, optional monthly/yearly switch.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'pricing_table',
	'title'       => __( 'Pricing Table', 'brik-builder' ),
	'category'    => 'content',
	'icon'        => 'badge-dollar-sign',
	'description' => 'Pricing plans. plans repeater [{name, price (e.g. "$29"), period (e.g. "/month"), price_yearly, period_yearly, description, features (one per line; prefix "-" = not included), button_text, button_link, featured (toggle), badge (featured label, default "Most popular")}]. columns: 1-4 (responsive). billing_toggle: shows Monthly/Yearly switch using price_yearly (monthly_label, yearly_label, yearly_note). align: left|center.',
	'fields'      => array_merge(
		array(
			'plans'          => Fields::field(
				'repeater',
				__( 'Plans', 'brik-builder' ),
				'content',
				array(
					'title_field' => 'name',
					'fields'      => array(
						'name'          => Fields::field( 'text', __( 'Name', 'brik-builder' ) ),
						'price'         => Fields::field( 'text', __( 'Price', 'brik-builder' ) ),
						'period'        => Fields::field( 'text', __( 'Period', 'brik-builder' ), 'content', array( 'placeholder' => '/month' ) ),
						'price_yearly'  => Fields::field( 'text', __( 'Yearly price', 'brik-builder' ), 'content', array( 'description' => __( 'Used when the billing switch is on.', 'brik-builder' ) ) ),
						'period_yearly' => Fields::field( 'text', __( 'Yearly period', 'brik-builder' ), 'content', array( 'placeholder' => '/month, billed yearly' ) ),
						'description'   => Fields::field( 'text', __( 'Description', 'brik-builder' ) ),
						'features'      => Fields::field( 'textarea', __( 'Features', 'brik-builder' ), 'content', array( 'description' => __( 'One per line. Start a line with "-" for a feature that is not included.', 'brik-builder' ) ) ),
						'button_text'   => Fields::field( 'text', __( 'Button text', 'brik-builder' ) ),
						'button_link'   => Fields::field( 'link', __( 'Button link', 'brik-builder' ) ),
						'featured'      => Fields::field( 'toggle', __( 'Featured', 'brik-builder' ) ),
						'badge'         => Fields::field( 'text', __( 'Badge', 'brik-builder' ), 'content', array( 'placeholder' => __( 'Most popular', 'brik-builder' ) ) ),
					),
					'default'     => array(
						array(
							'name'          => __( 'Starter', 'brik-builder' ),
							'price'         => '$0',
							'period'        => __( '/month', 'brik-builder' ),
							'price_yearly'  => '$0',
							'period_yearly' => __( '/month', 'brik-builder' ),
							'description'   => __( 'For side projects and personal sites.', 'brik-builder' ),
							'features'      => __( "1 website\nUnlimited pages\nCommunity support\n-Custom domain\n-Team members", 'brik-builder' ),
							'button_text'   => __( 'Get started', 'brik-builder' ),
							'button_link'   => array( 'url' => '#' ),
							'featured'      => false,
						),
						array(
							'name'          => __( 'Pro', 'brik-builder' ),
							'price'         => '$29',
							'period'        => __( '/month', 'brik-builder' ),
							'price_yearly'  => '$24',
							'period_yearly' => __( '/month, billed yearly', 'brik-builder' ),
							'description'   => __( 'For growing businesses that need more.', 'brik-builder' ),
							'features'      => __( "10 websites\nUnlimited pages\nPriority email support\nCustom domains\n-Team members", 'brik-builder' ),
							'button_text'   => __( 'Start free trial', 'brik-builder' ),
							'button_link'   => array( 'url' => '#' ),
							'featured'      => true,
							'badge'         => __( 'Most popular', 'brik-builder' ),
						),
						array(
							'name'          => __( 'Team', 'brik-builder' ),
							'price'         => '$79',
							'period'        => __( '/month', 'brik-builder' ),
							'price_yearly'  => '$64',
							'period_yearly' => __( '/month, billed yearly', 'brik-builder' ),
							'description'   => __( 'For agencies and teams working together.', 'brik-builder' ),
							'features'      => __( "Unlimited websites\nUnlimited pages\nDedicated support\nCustom domains\nUp to 20 team members", 'brik-builder' ),
							'button_text'   => __( 'Contact sales', 'brik-builder' ),
							'button_link'   => array( 'url' => '#' ),
							'featured'      => false,
						),
					),
				)
			),
			'columns'        => Fields::field( 'number', __( 'Columns', 'brik-builder' ), 'content', array( 'default' => 3, 'min' => 1, 'max' => 4, 'responsive' => true ) ),
			'align'          => Fields::field( 'select', __( 'Text alignment', 'brik-builder' ), 'content', array( 'default' => 'left', 'options' => Fields::opts( array( 'left' => __( 'Left', 'brik-builder' ), 'center' => __( 'Center', 'brik-builder' ) ) ) ) ),
			'billing_toggle' => Fields::field( 'toggle', __( 'Monthly / yearly switch', 'brik-builder' ), 'billing', array( 'group_label' => __( 'Billing switch', 'brik-builder' ) ) ),
			'monthly_label'  => Fields::field( 'text', __( 'Monthly label', 'brik-builder' ), 'billing', array( 'default' => __( 'Monthly', 'brik-builder' ), 'group_label' => __( 'Billing switch', 'brik-builder' ), 'show_if' => array( 'billing_toggle' => true ) ) ),
			'yearly_label'   => Fields::field( 'text', __( 'Yearly label', 'brik-builder' ), 'billing', array( 'default' => __( 'Yearly', 'brik-builder' ), 'group_label' => __( 'Billing switch', 'brik-builder' ), 'show_if' => array( 'billing_toggle' => true ) ) ),
			'yearly_note'    => Fields::field( 'text', __( 'Yearly note', 'brik-builder' ), 'billing', array( 'default' => __( 'Save 20%', 'brik-builder' ), 'group_label' => __( 'Billing switch', 'brik-builder' ), 'show_if' => array( 'billing_toggle' => true ) ) ),
			'gap'            => Fields::field( 'unit', __( 'Gap', 'brik-builder' ), 'plan', array( 'tab' => 'design', 'group_label' => __( 'Plan card', 'brik-builder' ), 'responsive' => true, 'css' => array( Fields::WRAP . ' .brik-pricing-grid', 'gap' ) ) ),
			'check_color'    => Fields::field( 'color', __( 'Check icon color', 'brik-builder' ), 'plan', array( 'tab' => 'design', 'group_label' => __( 'Plan card', 'brik-builder' ), 'css' => array( Fields::WRAP . ' .brik-pricing-check', 'color' ) ) ),
		),
		Fields::box( 'plan', __( 'Plan card', 'brik-builder' ), Fields::WRAP . ' .brik-pricing-plan' ),
		Fields::box( 'featured', __( 'Featured plan', 'brik-builder' ), Fields::WRAP . ' .brik-pricing-plan.is-featured', array( 'bg', 'color', 'border_color', 'shadow' ) ),
		Fields::typography( 'name', __( 'Plan name', 'brik-builder' ), Fields::WRAP . ' .brik-pricing-name' ),
		Fields::typography( 'price', __( 'Price', 'brik-builder' ), Fields::WRAP . ' .brik-pricing-amount' ),
		Fields::typography( 'desc', __( 'Description', 'brik-builder' ), Fields::WRAP . ' .brik-pricing-desc' ),
		Fields::typography( 'feature', __( 'Features', 'brik-builder' ), Fields::WRAP . ' .brik-pricing-feature' )
	),
	'css'         => static function ( $a, $wrap ) {
		return brik_grid_css( $a, $wrap . ' .brik-pricing-grid', 'columns', 3, 2, 1 );
	},
	'render'      => static function ( $a, $ctx ) {
		$plans = brik_items( $a['plans'] );
		if ( ! $plans ) {
			return $ctx->placeholder( __( 'Add a pricing plan', 'brik-builder' ) );
		}
		$center = 'center' === $a['align'];
		$toggle = ! empty( $a['billing_toggle'] );

		$cards = '';
		foreach ( $plans as $plan ) {
			$featured = ! empty( $plan['featured'] );

			$price = '<span class="brik-pricing-amount font-heading text-4xl font-bold tracking-tight">' . brik_inline( brik_item( $plan, 'price' ) ) . '</span>';
			if ( '' !== brik_item( $plan, 'period' ) ) {
				$price .= '<span class="brik-pricing-period text-sm text-muted-foreground">' . brik_inline( $plan['period'] ) . '</span>';
			}
			$price = '<div class="' . esc_attr( brik_cls( 'brik-price-m flex flex-wrap items-baseline gap-1', array( 'justify-center' => $center ) ) ) . '">' . $price . '</div>';
			if ( $toggle ) {
				$yearly = '<span class="brik-pricing-amount font-heading text-4xl font-bold tracking-tight">' . brik_inline( brik_item( $plan, 'price_yearly', brik_item( $plan, 'price' ) ) ) . '</span>';
				$period = brik_item( $plan, 'period_yearly', brik_item( $plan, 'period' ) );
				if ( '' !== $period ) {
					$yearly .= '<span class="brik-pricing-period text-sm text-muted-foreground">' . brik_inline( $period ) . '</span>';
				}
				$price .= '<div class="' . esc_attr( brik_cls( 'brik-price-y flex flex-wrap items-baseline gap-1', array( 'justify-center' => $center ) ) ) . '">' . $yearly . '</div>';
			}

			$features = '';
			foreach ( brik_lines( brik_item( $plan, 'features' ) ) as $line ) {
				$excluded = 0 === strpos( $line, '-' );
				$label    = brik_inline( $excluded ? ltrim( substr( $line, 1 ) ) : $line );
				if ( $excluded ) {
					$features .= '<li class="brik-pricing-feature is-excluded flex items-start gap-2.5 text-sm text-muted-foreground">' . brik_icon( 'x', 'mt-0.5 size-4 shrink-0 opacity-60' ) . '<span><span class="sr-only">' . esc_html__( 'Not included:', 'brik-builder' ) . ' </span>' . $label . '</span></li>';
				} else {
					$features .= '<li class="brik-pricing-feature flex items-start gap-2.5 text-sm">' . brik_icon( 'check', 'brik-pricing-check mt-0.5 size-4 shrink-0 text-primary' ) . '<span>' . $label . '</span></li>';
				}
			}

			$head = '<div class="grid gap-2">';
			if ( '' !== brik_item( $plan, 'name' ) ) {
				$head .= '<h3 class="brik-pricing-name font-heading text-lg font-semibold">' . brik_inline( $plan['name'] ) . '</h3>';
			}
			if ( '' !== brik_item( $plan, 'description' ) ) {
				$head .= '<p class="brik-pricing-desc text-sm text-muted-foreground">' . brik_inline( $plan['description'] ) . '</p>';
			}
			$head .= '</div>';

			$button = '';
			if ( '' !== brik_item( $plan, 'button_text' ) ) {
				$button = '<a' . brik_link_attrs( brik_item( $plan, 'button_link', '#' ), array( 'class' => brik_button_class( $featured ? 'default' : 'outline', 'lg', 'w-full' ) ) ) . '>' . brik_inline( $plan['button_text'] ) . '</a>';
			}

			$badge = '';
			if ( $featured ) {
				$badge = '<span class="' . esc_attr( brik_badge_class( 'default', 'default', 'brik-pricing-badge absolute -top-3 left-1/2 -translate-x-1/2 shadow-xs' ) ) . '">' . brik_inline( brik_item( $plan, 'badge', __( 'Most popular', 'brik-builder' ) ) ) . '</span>';
			}

			$card   = brik_cls(
				'brik-pricing-plan relative flex h-full flex-col gap-6 rounded-xl border bg-card p-6 text-card-foreground shadow-sm',
				array(
					'is-featured border-primary shadow-lg ring-1 ring-primary' => $featured,
					'text-center'                     => $center,
				)
			);
			$cards .= '<div class="' . esc_attr( $card ) . '">' . $badge . $head . $price
				. ( '' !== $features ? '<ul class="' . esc_attr( brik_cls( 'grid gap-3 border-t pt-6', array( 'justify-items-center' => $center ) ) ) . '">' . $features . '</ul>' : '' )
				. ( '' !== $button ? '<div class="mt-auto pt-2">' . $button . '</div>' : '' )
				. '</div>';
		}

		$switch = '';
		if ( $toggle ) {
			$note   = '' !== trim( (string) $a['yearly_note'] ) ? '<span class="' . esc_attr( brik_badge_class( 'success', 'sm' ) ) . '">' . brik_inline( $a['yearly_note'] ) . '</span>' : '';
			$switch = '<div class="mb-10 flex justify-center"><div class="brik-billing inline-flex items-center gap-1 rounded-lg bg-muted p-1" role="group" aria-label="' . esc_attr__( 'Billing period', 'brik-builder' ) . '">'
				. '<button type="button" class="brik-billing-opt inline-flex h-8 items-center rounded-md px-3 text-sm font-medium text-muted-foreground transition-colors aria-pressed:bg-background aria-pressed:text-foreground aria-pressed:shadow-sm" data-brik-billing="monthly" aria-pressed="true">' . brik_inline( $a['monthly_label'] ) . '</button>'
				. '<button type="button" class="brik-billing-opt inline-flex h-8 items-center gap-2 rounded-md px-3 text-sm font-medium text-muted-foreground transition-colors aria-pressed:bg-background aria-pressed:text-foreground aria-pressed:shadow-sm" data-brik-billing="yearly" aria-pressed="false">' . brik_inline( $a['yearly_label'] ) . $note . '</button>'
				. '</div></div>';
		}

		return $switch . '<div class="brik-pricing-grid grid items-stretch gap-6 pt-3" data-billing="monthly">' . $cards . '</div>';
	},
);
