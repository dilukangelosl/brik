<?php
/**
 * Alert (shadcn/ui alert) with tinted status variants.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'alert',
	'title'       => __( 'Alert', 'brik-builder' ),
	'category'    => 'basic',
	'icon'        => 'triangle-alert',
	'description' => 'Callout box. title, description (rich text), icon (Lucide name, empty for none). variant: default|destructive|info|success|warning. dismissible: toggle adds a close button.',
	'fields'      => array_merge(
		array(
			'title'       => Fields::field( 'text', __( 'Title', 'brik-builder' ), 'content', array( 'default' => __( 'Heads up!', 'brik-builder' ), 'inline' => true ) ),
			'description' => Fields::field( 'richtext', __( 'Description', 'brik-builder' ), 'content', array( 'default' => __( 'Your trial ends in 3 days. Upgrade to keep access to every feature and your saved projects.', 'brik-builder' ), 'inline' => true ) ),
			'icon'        => Fields::field( 'icon', __( 'Icon', 'brik-builder' ), 'content', array( 'default' => 'info' ) ),
			'variant'     => Fields::field(
				'select',
				__( 'Variant', 'brik-builder' ),
				'content',
				array(
					'default' => 'default',
					'options' => Fields::opts(
						array(
							'default'     => __( 'Default', 'brik-builder' ),
							'destructive' => __( 'Destructive', 'brik-builder' ),
							'info'        => __( 'Info', 'brik-builder' ),
							'success'     => __( 'Success', 'brik-builder' ),
							'warning'     => __( 'Warning', 'brik-builder' ),
						)
					),
				)
			),
			'dismissible' => Fields::field( 'toggle', __( 'Dismissible', 'brik-builder' ), 'content', array( 'description' => __( 'Adds a close button.', 'brik-builder' ) ) ),
		),
		Fields::box( 'alert', __( 'Alert box', 'brik-builder' ), Fields::WRAP . ' .brik-alert-box' ),
		array(
			'alert_icon_color' => Fields::field( 'color', __( 'Icon color', 'brik-builder' ), 'alert', array( 'tab' => 'design', 'group_label' => __( 'Alert box', 'brik-builder' ), 'css' => array( Fields::WRAP . ' .brik-alert-box > svg', 'color' ) ) ),
			'alert_icon_size'  => Fields::field( 'unit', __( 'Icon size', 'brik-builder' ), 'alert', array( 'tab' => 'design', 'group_label' => __( 'Alert box', 'brik-builder' ), 'css' => array( Fields::WRAP . ' .brik-alert-box > svg', array( 'width', 'height' ) ) ) ),
		),
		Fields::typography( 'title', __( 'Title', 'brik-builder' ), Fields::WRAP . ' .brik-alert-title' ),
		Fields::typography( 'desc', __( 'Description', 'brik-builder' ), Fields::WRAP . ' .brik-alert-desc' )
	),
	'render'      => static function ( $a, $ctx ) {
		$variants = array(
			'default'     => 'bg-card text-card-foreground',
			'destructive' => 'border-destructive/30 bg-card text-destructive [&_.brik-alert-desc]:text-destructive/90',
			'info'        => 'border-sky-500/30 bg-sky-500/10 text-sky-900 dark:text-sky-100 [&>svg]:text-sky-600 dark:[&>svg]:text-sky-400 [&_.brik-alert-desc]:text-sky-900/80 dark:[&_.brik-alert-desc]:text-sky-100/80',
			'success'     => 'border-emerald-500/30 bg-emerald-500/10 text-emerald-900 dark:text-emerald-100 [&>svg]:text-emerald-600 dark:[&>svg]:text-emerald-400 [&_.brik-alert-desc]:text-emerald-900/80 dark:[&_.brik-alert-desc]:text-emerald-100/80',
			'warning'     => 'border-amber-500/30 bg-amber-500/10 text-amber-900 dark:text-amber-100 [&>svg]:text-amber-600 dark:[&>svg]:text-amber-400 [&_.brik-alert-desc]:text-amber-900/80 dark:[&_.brik-alert-desc]:text-amber-100/80',
		);
		$variant  = isset( $variants[ $a['variant'] ] ) ? $a['variant'] : 'default';
		$icon     = ! empty( $a['icon'] ) ? brik_icon( $a['icon'], 'brik-alert-icon' ) : '';
		$class    = brik_cls(
			'brik-alert-box relative grid w-full grid-cols-[0_1fr] items-start gap-y-0.5 rounded-lg border px-4 py-3 text-sm has-[>svg]:grid-cols-[calc(var(--spacing)*4)_1fr] has-[>svg]:gap-x-3 [&>svg]:size-4 [&>svg]:translate-y-0.5',
			$variants[ $variant ],
			array( 'pr-10' => ! empty( $a['dismissible'] ) )
		);

		$html = $icon;
		if ( '' !== trim( (string) $a['title'] ) ) {
			$html .= '<div class="brik-alert-title col-start-2 min-h-4 font-medium tracking-tight"' . $ctx->inline( 'title' ) . '>' . brik_inline( $a['title'] ) . '</div>';
		}
		if ( '' !== trim( wp_strip_all_tags( (string) $a['description'] ) ) ) {
			$html .= '<div class="brik-alert-desc col-start-2 grid justify-items-start gap-1 text-sm text-muted-foreground [&_p]:leading-relaxed"' . $ctx->inline( 'description' ) . '>' . brik_rich( $a['description'] ) . '</div>';
		}
		if ( ! empty( $a['dismissible'] ) ) {
			$html .= '<button type="button" class="brik-alert-close absolute top-2.5 right-2.5 inline-flex size-6 items-center justify-center rounded-md opacity-60 transition-opacity hover:opacity-100 focus-visible:ring-[3px] focus-visible:ring-ring/50 outline-none" aria-label="' . esc_attr__( 'Dismiss', 'brik-builder' ) . '" data-brik-dismiss>' . brik_icon( 'x', 'size-4' ) . '</button>';
		}

		$role = in_array( $variant, array( 'destructive', 'warning' ), true ) ? 'alert' : 'status';
		return '<div class="' . esc_attr( $class ) . '" role="' . $role . '">' . $html . '</div>';
	},
);
