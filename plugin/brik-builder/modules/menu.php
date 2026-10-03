<?php
/**
 * Menu: navigation bar with dropdowns, mega menus and a mobile menu (drawer, full screen,
 * dropdown or bottom sheet). Items come from a WordPress menu or a custom item tree.
 * Markup helpers live in includes/helpers/navigation.php.
 *
 * @package Brik
 */

use Brik\Fields;
use Brik\Style;

defined( 'ABSPATH' ) || exit;

$brik_menu_mobile = array( 'breakpoint' => array( 'tablet', 'mobile' ) );

return array(
	'type'        => 'menu',
	'title'       => __( 'Menu', 'brik-builder' ),
	'category'    => 'site',
	'icon'        => 'menu',
	'description' => 'Navigation menu (shadcn navigation-menu) with dropdowns, mega menus and a mobile menu. '
		. 'source: menu|custom. "menu" = WordPress menu id in "menu" (empty uses the Primary location, else a page list); WP item description becomes the description; CSS classes on WP items: mega|mega-grid|mega-featured, mega-cols-N, mega-container|mega-full, nav-heading|nav-divider|nav-button(-outline|-secondary|-ghost), icon-{lucide}. '
		. '"custom" = "items" (menu_tree): list of {id, label (inline HTML), link:{url,new_tab}, icon (Lucide or brand:x), badge, badge_variant: default|secondary|outline|success|warning|info|destructive, description, type: link|dropdown|mega|heading|button|divider, button_variant: default|secondary|outline|ghost (type button), children:[items], '
		. 'mega_layout: columns (each child = column: label heading + its children links)|grid (children as icon cards)|featured (links + featured card on the right)|layout (renders library item layout_id), mega_columns 1-5, mega_width: auto|container|full, featured:{image:{url}, eyebrow, title, text, button_text, link:{url}}, layout_id}. A link with children acts as a dropdown; children with icon/description render as rich rows. '
		. 'orientation: horizontal|vertical. align: left|center|right|between. link_style: ghost|underline|underline-animated|pill-container|indicator (sliding highlight)|plain. trigger: hover|click. dropdown_animation: fade|slide|scale|none. chevron: toggle. show_desc: toggle. '
		. 'dropdown_layout: auto|list|mega and mega_columns apply to WordPress menus only. cta: ""|always|mobile with cta_text, cta_link, cta_variant. '
		. 'Mobile: breakpoint: tablet|mobile|never. mobile_type: drawer (sheet_side: right|left)|fullscreen|dropdown|bottom. mobile_animation: slide|fade|scale. mobile_stagger: toggle. submenu_mode: accordion|drilldown. toggle_style: morph|icon|text (toggle_icon, toggle_text). sheet_title. mobile_cta: toggle. mobile_socials: [{network: brand slug, url}]. mobile_text.',
	'fields'      => array_merge(
		array(
			'source'             => Fields::field( 'select', __( 'Links from', 'brik-builder' ), 'content', array( 'default' => 'menu', 'options' => Fields::opts( array( 'menu' => __( 'WordPress menu', 'brik-builder' ), 'custom' => __( 'Custom items', 'brik-builder' ) ) ) ) ),
			'menu'               => Fields::field( 'menu', __( 'Menu', 'brik-builder' ), 'content', array( 'show_if' => array( 'source' => 'menu' ), 'description' => __( 'Leave empty to use the menu assigned to the Primary location, or a list of pages. Add the CSS class "mega", "mega-grid" or "mega-featured" to a top-level item in Appearance → Menus to turn it into a mega menu.', 'brik-builder' ) ) ),
			'items'              => Fields::field( 'menu_tree', __( 'Menu items', 'brik-builder' ), 'content', array( 'show_if' => array( 'source' => 'custom' ), 'default' => brik_nav_sample_items() ) ),
			'orientation'        => Fields::field( 'select', __( 'Orientation', 'brik-builder' ), 'content', array( 'default' => 'horizontal', 'options' => Fields::opts( array( 'horizontal' => __( 'Horizontal', 'brik-builder' ), 'vertical' => __( 'Vertical (footer, sidebar)', 'brik-builder' ) ) ) ) ),
			'align'              => Fields::field(
				'select',
				__( 'Alignment', 'brik-builder' ),
				'content',
				array(
					'default'    => 'left',
					'responsive' => true,
					'options'    => Fields::opts( array( 'left' => __( 'Left', 'brik-builder' ), 'center' => __( 'Center', 'brik-builder' ), 'right' => __( 'Right', 'brik-builder' ), 'between' => __( 'Space between', 'brik-builder' ) ) ),
					'css'        => array(
						'selector' => Fields::WRAP . ' .brik-menu',
						'map'      => array(
							'left'    => '--brik-menu-justify:flex-start;--brik-menu-items:flex-start;--brik-menu-grow:0',
							'center'  => '--brik-menu-justify:center;--brik-menu-items:center;--brik-menu-grow:0',
							'right'   => '--brik-menu-justify:flex-end;--brik-menu-items:flex-end;--brik-menu-grow:0',
							'between' => '--brik-menu-justify:space-between;--brik-menu-items:stretch;--brik-menu-grow:1',
						),
					),
				)
			),
			'link_style'         => Fields::field(
				'select',
				__( 'Link style', 'brik-builder' ),
				'content',
				array(
					'default' => 'ghost',
					'options' => Fields::opts(
						array(
							'ghost'              => __( 'Pills', 'brik-builder' ),
							'underline'          => __( 'Underline', 'brik-builder' ),
							'underline-animated' => __( 'Animated underline', 'brik-builder' ),
							'pill-container'     => __( 'Pill container', 'brik-builder' ),
							'indicator'          => __( 'Sliding highlight', 'brik-builder' ),
							'plain'              => __( 'Plain', 'brik-builder' ),
						)
					),
				)
			),
			'gap'                => Fields::field( 'unit', __( 'Space between links', 'brik-builder' ), 'content', array( 'responsive' => true, 'css' => array( Fields::WRAP . ' .brik-menu-list', 'gap' ) ) ),
			'cta'                => Fields::field( 'select', __( 'Button', 'brik-builder' ), 'content', array( 'options' => Fields::opts( array( '' => __( 'None', 'brik-builder' ), 'always' => __( 'After the links', 'brik-builder' ), 'mobile' => __( 'Only in the mobile menu', 'brik-builder' ) ) ) ) ),
			'cta_text'           => Fields::field( 'text', __( 'Button text', 'brik-builder' ), 'content', array( 'default' => __( 'Get started', 'brik-builder' ), 'show_if' => array( 'cta' => '!' ) ) ),
			'cta_link'           => Fields::field( 'link', __( 'Button link', 'brik-builder' ), 'content', array( 'default' => array( 'url' => '#' ), 'show_if' => array( 'cta' => '!' ) ) ),
			'cta_variant'        => Fields::field( 'select', __( 'Button style', 'brik-builder' ), 'content', array( 'default' => 'default', 'show_if' => array( 'cta' => '!' ), 'options' => Fields::opts( brik_button_variants_labels() ) ) ),

			// Dropdowns and mega menus.
			'trigger'            => Fields::field( 'select', __( 'Open dropdowns on', 'brik-builder' ), 'dropdowns', array( 'group_label' => __( 'Dropdowns', 'brik-builder' ), 'default' => 'hover', 'show_if' => array( 'orientation' => 'horizontal' ), 'options' => Fields::opts( array( 'hover' => __( 'Hover', 'brik-builder' ), 'click' => __( 'Click', 'brik-builder' ) ) ) ) ),
			'dropdown_animation' => Fields::field( 'select', __( 'Animation', 'brik-builder' ), 'dropdowns', array( 'group_label' => __( 'Dropdowns', 'brik-builder' ), 'default' => 'scale', 'show_if' => array( 'orientation' => 'horizontal' ), 'options' => Fields::opts( array( 'fade' => __( 'Fade', 'brik-builder' ), 'slide' => __( 'Slide', 'brik-builder' ), 'scale' => __( 'Scale', 'brik-builder' ), 'none' => __( 'None', 'brik-builder' ) ) ) ) ),
			'chevron'            => Fields::field( 'toggle', __( 'Show arrow on dropdown links', 'brik-builder' ), 'dropdowns', array( 'group_label' => __( 'Dropdowns', 'brik-builder' ), 'default' => true, 'show_if' => array( 'orientation' => 'horizontal' ) ) ),
			'show_desc'          => Fields::field( 'toggle', __( 'Show link descriptions', 'brik-builder' ), 'dropdowns', array( 'group_label' => __( 'Dropdowns', 'brik-builder' ), 'default' => true ) ),
			'dropdown_layout'    => Fields::field( 'select', __( 'WordPress menu dropdowns', 'brik-builder' ), 'dropdowns', array( 'group_label' => __( 'Dropdowns', 'brik-builder' ), 'default' => 'auto', 'show_if' => array( 'source' => 'menu', 'orientation' => 'horizontal' ), 'options' => Fields::opts( array( 'auto' => __( 'Automatic', 'brik-builder' ), 'list' => __( 'List', 'brik-builder' ), 'mega' => __( 'Mega menu (columns)', 'brik-builder' ) ) ), 'description' => __( 'Automatic uses columns when a dropdown has six or more links. Items with a mega CSS class always become mega menus.', 'brik-builder' ) ) ),
			'mega_columns'       => Fields::field( 'select', __( 'Mega menu columns', 'brik-builder' ), 'dropdowns', array( 'group_label' => __( 'Dropdowns', 'brik-builder' ), 'default' => '2', 'show_if' => array( 'source' => 'menu', 'dropdown_layout' => array( 'auto', 'mega' ) ), 'options' => Fields::opts( array( '2' => '2', '3' => '3', '4' => '4', '5' => '5' ) ) ) ),

			// Mobile menu.
			'breakpoint'         => Fields::field( 'select', __( 'Collapse into a menu button', 'brik-builder' ), 'mobile_menu', array( 'group_label' => __( 'Mobile menu', 'brik-builder' ), 'default' => 'tablet', 'options' => Fields::opts( array( 'tablet' => __( 'On tablets and phones', 'brik-builder' ), 'mobile' => __( 'On phones', 'brik-builder' ), 'never' => __( 'Never', 'brik-builder' ) ) ) ) ),
			'mobile_type'        => Fields::field( 'select', __( 'Menu type', 'brik-builder' ), 'mobile_menu', array( 'group_label' => __( 'Mobile menu', 'brik-builder' ), 'default' => 'drawer', 'show_if' => $brik_menu_mobile, 'options' => Fields::opts( array( 'drawer' => __( 'Side drawer', 'brik-builder' ), 'fullscreen' => __( 'Full screen', 'brik-builder' ), 'dropdown' => __( 'Drop down under the header', 'brik-builder' ), 'bottom' => __( 'Bottom sheet', 'brik-builder' ) ) ) ) ),
			'sheet_side'         => Fields::field( 'select', __( 'Drawer slides in from', 'brik-builder' ), 'mobile_menu', array( 'group_label' => __( 'Mobile menu', 'brik-builder' ), 'default' => 'right', 'show_if' => array_merge( $brik_menu_mobile, array( 'mobile_type' => 'drawer' ) ), 'options' => Fields::opts( array( 'right' => __( 'Right', 'brik-builder' ), 'left' => __( 'Left', 'brik-builder' ) ) ) ) ),
			'mobile_animation'   => Fields::field( 'select', __( 'Animation', 'brik-builder' ), 'mobile_menu', array( 'group_label' => __( 'Mobile menu', 'brik-builder' ), 'default' => 'slide', 'show_if' => $brik_menu_mobile, 'options' => Fields::opts( array( 'slide' => __( 'Slide', 'brik-builder' ), 'fade' => __( 'Fade', 'brik-builder' ), 'scale' => __( 'Scale', 'brik-builder' ) ) ) ) ),
			'mobile_stagger'     => Fields::field( 'toggle', __( 'Reveal links one by one', 'brik-builder' ), 'mobile_menu', array( 'group_label' => __( 'Mobile menu', 'brik-builder' ), 'default' => true, 'show_if' => $brik_menu_mobile ) ),
			'submenu_mode'       => Fields::field( 'select', __( 'Submenus', 'brik-builder' ), 'mobile_menu', array( 'group_label' => __( 'Mobile menu', 'brik-builder' ), 'default' => 'accordion', 'show_if' => $brik_menu_mobile, 'options' => Fields::opts( array( 'accordion' => __( 'Expand in place', 'brik-builder' ), 'drilldown' => __( 'Slide in (drill down)', 'brik-builder' ) ) ) ) ),
			'toggle_style'       => Fields::field( 'select', __( 'Menu button', 'brik-builder' ), 'mobile_menu', array( 'group_label' => __( 'Mobile menu', 'brik-builder' ), 'default' => 'morph', 'show_if' => $brik_menu_mobile, 'options' => Fields::opts( array( 'morph' => __( 'Animated (lines to X)', 'brik-builder' ), 'icon' => __( 'Icon', 'brik-builder' ), 'text' => __( 'Text and icon', 'brik-builder' ) ) ) ) ),
			'toggle_icon'        => Fields::field( 'icon', __( 'Menu button icon', 'brik-builder' ), 'mobile_menu', array( 'group_label' => __( 'Mobile menu', 'brik-builder' ), 'default' => 'menu', 'show_if' => array_merge( $brik_menu_mobile, array( 'toggle_style' => array( 'icon', 'text' ) ) ) ) ),
			'toggle_text'        => Fields::field( 'text', __( 'Menu button text', 'brik-builder' ), 'mobile_menu', array( 'group_label' => __( 'Mobile menu', 'brik-builder' ), 'default' => __( 'Menu', 'brik-builder' ), 'show_if' => array_merge( $brik_menu_mobile, array( 'toggle_style' => 'text' ) ) ) ),
			'sheet_title'        => Fields::field( 'text', __( 'Mobile menu title', 'brik-builder' ), 'mobile_menu', array( 'group_label' => __( 'Mobile menu', 'brik-builder' ), 'default' => '{site_name}', 'show_if' => array_merge( $brik_menu_mobile, array( 'mobile_type' => array( 'drawer', 'fullscreen', 'bottom' ) ) ) ) ),
			'mobile_cta'         => Fields::field( 'toggle', __( 'Show the button in the mobile menu', 'brik-builder' ), 'mobile_menu', array( 'group_label' => __( 'Mobile menu', 'brik-builder' ), 'default' => true, 'show_if' => array_merge( $brik_menu_mobile, array( 'cta' => '!' ) ) ) ),
			'mobile_socials'     => Fields::field(
				'repeater',
				__( 'Social links', 'brik-builder' ),
				'mobile_menu',
				array(
					'group_label' => __( 'Mobile menu', 'brik-builder' ),
					'show_if'     => $brik_menu_mobile,
					'title_field' => 'network',
					'fields'      => array(
						'network' => Fields::field( 'select', __( 'Network', 'brik-builder' ), 'content', array( 'default' => 'x', 'options' => Fields::opts( brik_brand_options() ) ) ),
						'url'     => Fields::field( 'text', __( 'URL', 'brik-builder' ), 'content', array( 'placeholder' => 'https://' ) ),
					),
				)
			),
			'mobile_text'        => Fields::field( 'textarea', __( 'Text under the links', 'brik-builder' ), 'mobile_menu', array( 'group_label' => __( 'Mobile menu', 'brik-builder' ), 'show_if' => $brik_menu_mobile, 'placeholder' => __( 'hello@example.com', 'brik-builder' ) ) ),

			'label'              => Fields::field( 'text', __( 'Accessible name', 'brik-builder' ), 'attributes', array( 'tab' => 'advanced', 'default' => __( 'Main', 'brik-builder' ) ) ),

			// Design.
			'link_active_color'  => Fields::field( 'color', __( 'Active link color', 'brik-builder' ), 'link', array( 'tab' => 'design', 'group_label' => __( 'Links', 'brik-builder' ), 'css' => array( Fields::WRAP . ' :is(.brik-menu-link,.brik-menu-sublink,.brik-sheet-link):is(.is-current,.is-ancestor)', 'color' ) ) ),
			'link_active_bg'     => Fields::field( 'color', __( 'Active link background', 'brik-builder' ), 'link', array( 'tab' => 'design', 'group_label' => __( 'Links', 'brik-builder' ), 'css' => array( Fields::WRAP . ' .brik-menu-link.is-current', 'background-color' ) ) ),
			'indicator_color'    => Fields::field( 'color', __( 'Highlight color', 'brik-builder' ), 'link', array( 'tab' => 'design', 'group_label' => __( 'Links', 'brik-builder' ), 'description' => __( 'Sliding highlight, pill and underline color.', 'brik-builder' ), 'css' => array( Fields::WRAP . ' .brik-menu', '--brik-menu-highlight' ) ) ),
			'container_bg'       => Fields::field( 'color', __( 'Pill container background', 'brik-builder' ), 'link', array( 'tab' => 'design', 'group_label' => __( 'Links', 'brik-builder' ), 'css' => array( Fields::WRAP . ' .brik-menu--pill-container .brik-menu-bar > .brik-menu-list', 'background-color' ) ) ),
			'dropdown_width'     => Fields::field( 'unit', __( 'Dropdown width', 'brik-builder' ), 'panel', array( 'tab' => 'design', 'group_label' => __( 'Dropdown panel', 'brik-builder' ), 'css' => array( Fields::WRAP . ' .brik-menu-panel--list', 'width' ) ) ),
			'sheet_width'        => Fields::field( 'unit', __( 'Drawer width', 'brik-builder' ), 'sheet', array( 'tab' => 'design', 'group_label' => __( 'Mobile menu', 'brik-builder' ), 'css' => array( Fields::WRAP . ' .brik-mobile-menu--drawer .brik-menu-sheet', 'width' ) ) ),
			'backdrop_color'     => Fields::field( 'color', __( 'Backdrop color', 'brik-builder' ), 'sheet', array( 'tab' => 'design', 'group_label' => __( 'Mobile menu', 'brik-builder' ), 'css' => array( Fields::WRAP . ' .brik-mm-backdrop', 'background-color' ) ) ),
		),
		Fields::typography( 'link', __( 'Link text', 'brik-builder' ), Fields::WRAP . ' .brik-menu-link' ),
		Fields::box( 'link', __( 'Links', 'brik-builder' ), Fields::WRAP . ' .brik-menu-link', array( 'bg', 'border_width', 'border_color', 'radius', 'padding' ) ),
		Fields::box( 'panel', __( 'Dropdown panel', 'brik-builder' ), Fields::WRAP . ' .brik-menu-panel' ),
		Fields::typography( 'dropdown', __( 'Dropdown links', 'brik-builder' ), Fields::WRAP . ' .brik-menu-subtitle' ),
		Fields::typography( 'desc', __( 'Link descriptions', 'brik-builder' ), Fields::WRAP . ' :is(.brik-menu-desc,.brik-mm-desc)' ),
		Fields::typography( 'heading', __( 'Dropdown headings', 'brik-builder' ), Fields::WRAP . ' :is(.brik-menu-heading,.brik-mm-heading)' ),
		Fields::box( 'toggle', __( 'Menu button', 'brik-builder' ), Fields::WRAP . ' .brik-menu-toggle', array( 'bg', 'color', 'border_width', 'border_color', 'radius' ) ),
		Fields::box( 'sheet', __( 'Mobile menu', 'brik-builder' ), Fields::WRAP . ' .brik-menu-sheet', array( 'bg', 'color', 'border_color' ) ),
		Fields::typography( 'sheet', __( 'Mobile menu links', 'brik-builder' ), Fields::WRAP . ' .brik-sheet-link' )
	),
	// Menus saved before the panel settings were renamed used dropdown_* for the panel box.
	'css'         => static function ( $a, $wrap ) {
		$map = array(
			'bg'           => 'background-color',
			'color'        => 'color',
			'border_width' => 'border-width',
			'border_color' => 'border-color',
			'radius'       => 'border-radius',
			'padding'      => 'padding',
			'shadow'       => 'box-shadow',
		);
		$decl = array();
		foreach ( $map as $key => $prop ) {
			$old = isset( $a[ 'dropdown_' . $key ] ) && is_scalar( $a[ 'dropdown_' . $key ] ) ? (string) $a[ 'dropdown_' . $key ] : '';
			if ( '' === $old || ( isset( $a[ 'panel_' . $key ] ) && '' !== $a[ 'panel_' . $key ] ) ) {
				continue;
			}
			$shadows = Fields::shadow_map();
			$value   = 'shadow' === $key && isset( $shadows[ $old ] ) ? $shadows[ $old ] : $old;
			$value   = Style::clean( $value );
			if ( '' !== $value ) {
				$decl[] = $prop . ':' . $value;
			}
		}
		return $decl ? $wrap . ' .brik-menu-panel{' . implode( ';', $decl ) . '}' : '';
	},
	'render'      => static function ( $a, $ctx ) {
		$items = brik_nav_items( $a, $ctx );
		if ( ! $items ) {
			return $ctx->placeholder( __( 'This menu has no links yet', 'brik-builder' ) );
		}

		$vertical   = 'vertical' === $a['orientation'];
		$styles     = array( 'ghost', 'underline', 'underline-animated', 'pill-container', 'indicator', 'plain' );
		$style      = in_array( $a['link_style'], $styles, true ) ? $a['link_style'] : 'ghost';
		$style      = $vertical && in_array( $style, array( 'pill-container', 'indicator' ), true ) ? 'ghost' : $style;
		$breakpoint = in_array( $a['breakpoint'], array( 'tablet', 'mobile', 'never' ), true ) ? $a['breakpoint'] : 'tablet';
		$trigger    = 'click' === $a['trigger'] ? 'click' : 'hover';
		$animation  = in_array( $a['dropdown_animation'], array( 'fade', 'slide', 'scale', 'none' ), true ) ? $a['dropdown_animation'] : 'scale';
		$cta        = in_array( $a['cta'], array( 'always', 'mobile' ), true ) && '' !== trim( (string) $a['cta_text'] ) ? $a['cta'] : '';

		$o = array(
			'ctx'       => $ctx,
			'show_desc' => ! empty( $a['show_desc'] ),
			'chevron'   => ! empty( $a['chevron'] ),
			'cta'       => '' !== $cta,
		);

		if ( $vertical ) {
			$list = brik_nav_vertical_items( $items, $o );
		} else {
			$list = in_array( $style, array( 'indicator', 'pill-container' ), true ) ? '<li class="brik-menu-indicator" aria-hidden="true"></li>' : '';
			$list .= brik_nav_bar_items( $items, $o );
		}

		$bar = '<ul class="brik-menu-list">' . $list . '</ul>';
		if ( 'always' === $cta ) {
			$bar .= '<a' . brik_link_attrs( $a['cta_link'], array( 'class' => brik_button_class( $a['cta_variant'], 'default', 'brik-menu-cta' ) ) ) . '><span' . $ctx->inline( 'cta_text' ) . '>' . brik_inline( $a['cta_text'] ) . '</span></a>';
		}

		$mobile = '';
		if ( 'never' !== $breakpoint ) {
			$panel_id = $ctx->uid( 'mobile' );
			$bar     .= brik_nav_toggle( $a, $panel_id );
			$mobile   = brik_nav_mobile( $items, $a, $o, $panel_id );
		}

		$class = brik_cls(
			'brik-menu',
			'brik-menu--' . $style,
			$vertical ? 'brik-menu--vertical' : 'brik-menu--horizontal',
			'brik-menu--bp-' . $breakpoint,
			'brik-menu--anim-' . $animation
		);
		$attrs = array(
			'class'          => $class,
			'data-brik-menu' => true,
			'data-trigger'   => $trigger,
			'aria-label'     => wp_strip_all_tags( (string) $a['label'] ),
		);
		return '<nav' . brik_attrs( $attrs ) . '><div class="brik-menu-bar">' . $bar . '</div>' . $mobile . '</nav>';
	},
);
