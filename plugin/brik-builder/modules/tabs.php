<?php
/**
 * Tabs (shadcn/ui tabs) with an ARIA tablist and keyboard navigation.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'tabs',
	'title'       => __( 'Tabs', 'brik-builder' ),
	'category'    => 'interactive',
	'icon'        => 'panel-top',
	'description' => 'Tabbed panels. items: repeater of {title, icon (Lucide, optional), content (HTML)}. variant: pills (shadcn segmented list) | underline. orientation: horizontal|vertical (vertical stacks on mobile). list_align: start|center|stretch (horizontal). panel_style: card|plain. active: 1-based index of the initially open tab. Arrow keys, Home and End move between tabs.',
	'fields'      => array_merge(
		array(
			'items'       => Fields::field(
				'repeater',
				__( 'Tabs', 'brik-builder' ),
				'content',
				array(
					'title_field' => 'title',
					'fields'      => array(
						'title'   => Fields::field( 'text', __( 'Title', 'brik-builder' ), 'content' ),
						'icon'    => Fields::field( 'icon', __( 'Icon', 'brik-builder' ), 'content' ),
						'content' => Fields::field( 'richtext', __( 'Content', 'brik-builder' ), 'content' ),
					),
					'default'     => array(
						array(
							'title'   => __( 'Overview', 'brik-builder' ),
							'icon'    => 'layout-template',
							'content' => '<h3>' . __( 'Everything in one place', 'brik-builder' ) . '</h3><p>' . __( 'Plan projects, track progress and share updates without switching tools. Your whole team sees the same picture, in real time.', 'brik-builder' ) . '</p>',
						),
						array(
							'title'   => __( 'Features', 'brik-builder' ),
							'icon'    => 'sparkles',
							'content' => '<h3>' . __( 'Built for speed', 'brik-builder' ) . '</h3><ul><li>' . __( 'Keyboard shortcuts for every action', 'brik-builder' ) . '</li><li>' . __( 'Offline mode that syncs when you reconnect', 'brik-builder' ) . '</li><li>' . __( 'Integrations with the tools you already use', 'brik-builder' ) . '</li></ul>',
						),
						array(
							'title'   => __( 'Pricing', 'brik-builder' ),
							'icon'    => 'gem',
							'content' => '<h3>' . __( 'Simple, honest pricing', 'brik-builder' ) . '</h3><p>' . __( 'Start free with up to three projects. Upgrade when you need more seats, advanced permissions or priority support.', 'brik-builder' ) . '</p>',
						),
					),
				)
			),
			'variant'     => Fields::field( 'select', __( 'Style', 'brik-builder' ), 'content', array( 'default' => 'pills', 'options' => Fields::opts( array( 'pills' => __( 'Pills', 'brik-builder' ), 'underline' => __( 'Underline', 'brik-builder' ) ) ) ) ),
			'orientation' => Fields::field( 'select', __( 'Orientation', 'brik-builder' ), 'content', array( 'default' => 'horizontal', 'options' => Fields::opts( array( 'horizontal' => __( 'Horizontal', 'brik-builder' ), 'vertical' => __( 'Vertical', 'brik-builder' ) ) ) ) ),
			'list_align'  => Fields::field( 'select', __( 'Tab list alignment', 'brik-builder' ), 'content', array( 'default' => 'start', 'options' => Fields::opts( array( 'start' => __( 'Left', 'brik-builder' ), 'center' => __( 'Center', 'brik-builder' ), 'stretch' => __( 'Full width', 'brik-builder' ) ) ), 'show_if' => array( 'orientation' => 'horizontal' ) ) ),
			'panel_style' => Fields::field( 'select', __( 'Panel', 'brik-builder' ), 'content', array( 'default' => 'card', 'options' => Fields::opts( array( 'card' => __( 'Card', 'brik-builder' ), 'plain' => __( 'Plain', 'brik-builder' ) ) ) ) ),
			'active'      => Fields::field( 'number', __( 'Open tab', 'brik-builder' ), 'content', array( 'default' => 1, 'min' => 1 ) ),
			'list_width'  => Fields::field( 'unit', __( 'Tab list width', 'brik-builder' ), 'content', array( 'responsive' => true, 'show_if' => array( 'orientation' => 'vertical' ), 'css' => array( Fields::WRAP . ' .brik-tabs-list', 'width' ) ) ),
		),
		Fields::box( 'list', __( 'Tab list', 'brik-builder' ), Fields::WRAP . ' .brik-tabs-list', array( 'bg', 'border_color', 'radius', 'padding' ) ),
		Fields::box( 'tab', __( 'Tab', 'brik-builder' ), Fields::WRAP . ' .brik-tabs-trigger' ),
		Fields::box( 'active_tab', __( 'Active tab', 'brik-builder' ), Fields::WRAP . ' .brik-tabs-trigger[aria-selected="true"]', array( 'bg', 'color', 'border_color', 'shadow' ) ),
		Fields::typography( 'tab', __( 'Tab text', 'brik-builder' ), Fields::WRAP . ' .brik-tabs-trigger' ),
		Fields::box( 'panel', __( 'Panel', 'brik-builder' ), Fields::WRAP . ' .brik-tabs-panel' ),
		Fields::typography( 'panel', __( 'Panel text', 'brik-builder' ), Fields::WRAP . ' .brik-tabs-panel' )
	),
	'render'      => static function ( $a, $ctx ) {
		$items = is_array( $a['items'] ) ? array_values( array_filter( $a['items'], 'is_array' ) ) : array();
		if ( ! $items ) {
			return $ctx->placeholder( __( 'Add tabs', 'brik-builder' ) );
		}
		$vertical  = 'vertical' === $a['orientation'];
		$underline = 'underline' === $a['variant'];
		$active    = max( 0, min( count( $items ) - 1, (int) $a['active'] - 1 ) );

		$root = $vertical ? 'brik-tabs flex flex-col gap-4 md:flex-row md:gap-6' : 'brik-tabs flex flex-col gap-4';

		$align = array(
			'start'   => '',
			'center'  => 'self-center',
			'stretch' => 'w-full',
		);
		$al = isset( $align[ $a['list_align'] ] ) ? $align[ $a['list_align'] ] : '';

		if ( $underline ) {
			$list    = $vertical
				? 'brik-tabs-list flex h-fit w-full shrink-0 flex-col items-stretch gap-1 border-l md:w-56'
				: 'brik-tabs-list flex max-w-full items-center gap-1 overflow-x-auto border-b [scrollbar-width:none] ' . ( 'stretch' === $a['list_align'] ? 'w-full' : 'w-fit ' . $al );
			$trigger = $vertical
				? 'brik-tabs-trigger relative inline-flex w-full items-center justify-start gap-2 rounded-md px-3 py-2 text-sm font-medium whitespace-nowrap text-muted-foreground transition-colors outline-none hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 after:absolute after:inset-y-1 after:-left-px after:w-0.5 after:rounded-full after:bg-foreground after:opacity-0 after:transition-opacity aria-selected:text-foreground aria-selected:after:opacity-100 [&_svg]:size-4 [&_svg]:shrink-0'
				: 'brik-tabs-trigger relative inline-flex items-center justify-center gap-2 px-3 pt-1 pb-2.5 text-sm font-medium whitespace-nowrap text-muted-foreground transition-colors outline-none hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 rounded-t-md after:absolute after:inset-x-0 after:-bottom-px after:h-0.5 after:rounded-full after:bg-foreground after:opacity-0 after:transition-opacity aria-selected:text-foreground aria-selected:after:opacity-100 [&_svg]:size-4 [&_svg]:shrink-0' . ( 'stretch' === $a['list_align'] ? ' flex-1' : '' );
		} else {
			$list    = $vertical
				? 'brik-tabs-list flex h-fit w-full shrink-0 flex-col items-stretch gap-1 rounded-lg bg-muted p-[3px] text-muted-foreground md:w-56'
				: 'brik-tabs-list inline-flex h-9 max-w-full items-center rounded-lg bg-muted p-[3px] text-muted-foreground overflow-x-auto [scrollbar-width:none] ' . ( 'stretch' === $a['list_align'] ? 'w-full' : 'w-fit ' . $al );
			$trigger = 'brik-tabs-trigger relative inline-flex items-center gap-1.5 rounded-md border border-transparent px-3 py-1 text-sm font-medium whitespace-nowrap text-foreground/60 transition-all outline-none hover:text-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-selected:bg-background aria-selected:text-foreground aria-selected:shadow-sm dark:text-muted-foreground dark:hover:text-foreground dark:aria-selected:border-input dark:aria-selected:bg-input/30 dark:aria-selected:text-foreground [&_svg]:size-4 [&_svg]:shrink-0'
				. ( $vertical ? ' w-full justify-start py-1.5' : ' h-[calc(100%-1px)] flex-1 justify-center' );
		}

		$panel = 'card' === $a['panel_style']
			? 'brik-tabs-panel brik-prose flex-1 rounded-xl border bg-card p-6 text-card-foreground shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50'
			: 'brik-tabs-panel brik-prose flex-1 outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50';

		$tabs   = '';
		$panels = '';
		foreach ( $items as $i => $item ) {
			$item  = array_merge( array( 'title' => '', 'icon' => '', 'content' => '' ), $item );
			$tab   = $ctx->uid( 'tab-' . $i );
			$pane  = $ctx->uid( 'panel-' . $i );
			$on    = $i === $active;
			$tabs .= sprintf(
				'<button type="button" role="tab" id="%1$s" class="%2$s" aria-controls="%3$s" aria-selected="%4$s" tabindex="%5$s">%6$s<span>%7$s</span></button>',
				esc_attr( $tab ),
				esc_attr( $trigger ),
				esc_attr( $pane ),
				$on ? 'true' : 'false',
				$on ? '0' : '-1',
				$item['icon'] ? brik_icon( $item['icon'] ) : '',
				brik_inline( $item['title'] )
			);
			$panels .= sprintf(
				'<div role="tabpanel" id="%1$s" class="%2$s" aria-labelledby="%3$s" tabindex="0"%4$s>%5$s</div>',
				esc_attr( $pane ),
				esc_attr( $panel ),
				esc_attr( $tab ),
				$on ? '' : ' hidden',
				brik_rich( $item['content'] )
			);
		}

		return sprintf(
			'<div class="%1$s" data-brik-tabs data-orientation="%2$s"><div role="tablist" class="%3$s" aria-orientation="%2$s">%4$s</div>%5$s</div>',
			esc_attr( $root ),
			$vertical ? 'vertical' : 'horizontal',
			esc_attr( $list ),
			$tabs,
			$vertical ? '<div class="flex min-w-0 flex-1 flex-col">' . $panels . '</div>' : $panels
		);
	},
);
