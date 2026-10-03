<?php
/**
 * Navigation helpers: menu sources (WordPress menus, custom item trees, legacy link lists)
 * normalized into one item shape, and the markup for desktop bars, dropdowns, mega panels
 * and the mobile menu.
 *
 * @package Brik
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * WordPress menus.
 * ---------------------------------------------------------------------- */

/**
 * Menu to show: the chosen one, the one in the "primary" location, or 0 for the page list.
 */
function brik_site_menu_id( $chosen ) {
	$chosen = (int) $chosen;
	if ( $chosen && wp_get_nav_menu_object( $chosen ) ) {
		return $chosen;
	}
	$locations = get_nav_menu_locations();
	foreach ( array( 'primary', 'main', 'header' ) as $location ) {
		if ( ! empty( $locations[ $location ] ) && wp_get_nav_menu_object( $locations[ $location ] ) ) {
			return (int) $locations[ $location ];
		}
	}
	return 0;
}

/**
 * Menu items as a tree: [ id, title, url, target, rel, desc, classes, current, ancestor, children ].
 */
function brik_site_menu_tree( $menu_id ) {
	$items = $menu_id ? wp_get_nav_menu_items( $menu_id, array( 'update_post_term_cache' => false ) ) : brik_site_page_items();
	if ( ! $items ) {
		return array();
	}
	if ( $menu_id && function_exists( '_wp_menu_item_classes_by_context' ) && ! wp_is_json_request() ) {
		_wp_menu_item_classes_by_context( $items );
	}

	$nodes = array();
	foreach ( $items as $item ) {
		if ( ! empty( $item->_invalid ) ) {
			continue;
		}
		$nodes[ (int) $item->ID ] = array(
			'id'       => (int) $item->ID,
			'parent'   => (int) $item->menu_item_parent,
			'title'    => $item->title,
			'url'      => $item->url,
			'target'   => $item->target,
			'rel'      => $item->xfn,
			'desc'     => isset( $item->description ) ? $item->description : '',
			'classes'  => array_filter( (array) $item->classes ),
			'current'  => ! empty( $item->current ),
			'ancestor' => ! empty( $item->current_item_ancestor ) || ! empty( $item->current_item_parent ),
			'children' => array(),
		);
	}

	$tree = array();
	foreach ( $nodes as $node ) {
		if ( ! $node['parent'] || ! isset( $nodes[ $node['parent'] ] ) ) {
			$tree[] = brik_site_menu_branch( $node, $nodes );
		}
	}
	return $tree;
}

/**
 * A menu node with its descendants; parents of the current item are flagged as ancestors.
 */
function brik_site_menu_branch( array $node, array $index ) {
	$children = array();
	foreach ( $index as $candidate ) {
		if ( $candidate['parent'] === $node['id'] ) {
			$children[] = brik_site_menu_branch( $candidate, $index );
		}
	}
	$node['children'] = $children;
	if ( ! $node['ancestor'] ) {
		foreach ( $children as $child ) {
			if ( $child['current'] || $child['ancestor'] ) {
				$node['ancestor'] = true;
				break;
			}
		}
	}
	return $node;
}

/**
 * Pages shaped like menu items, for sites without a menu.
 */
function brik_site_page_items() {
	$pages = get_pages(
		array(
			'sort_column' => 'menu_order,post_title',
			'post_status' => 'publish',
			'number'      => 40,
		)
	);
	$items   = array();
	$current = is_page() ? get_queried_object_id() : 0;
	$ids     = wp_list_pluck( $pages, 'ID' );
	foreach ( $pages as $page ) {
		if ( $page->post_parent && ! in_array( $page->post_parent, $ids, true ) ) {
			continue;
		}
		$items[] = (object) array(
			'ID'                    => $page->ID,
			'menu_item_parent'      => $page->post_parent,
			'title'                 => get_the_title( $page ),
			'url'                   => get_permalink( $page ),
			'target'                => '',
			'xfn'                   => '',
			'description'           => '',
			'classes'               => array(),
			'current'               => $current === $page->ID,
			'current_item_ancestor' => $current && in_array( $page->ID, get_post_ancestors( $current ), true ),
		);
	}
	return array_slice( $items, 0, 12 );
}

/**
 * Menu items from a list of custom links (the old "links" repeater value).
 */
function brik_site_custom_items( $links ) {
	$out = array();
	foreach ( brik_nav_normalize( brik_nav_from_links( $links ) ) as $i => $item ) {
		$out[] = array(
			'id'       => $i + 1,
			'parent'   => 0,
			'title'    => $item['label'],
			'url'      => $item['url'],
			'target'   => $item['target'],
			'rel'      => $item['rel'],
			'desc'     => '',
			'classes'  => array(),
			'current'  => $item['current'],
			'ancestor' => false,
			'children' => array(),
		);
	}
	return $out;
}

/**
 * Anchor attributes for a menu tree item (brik_site_menu_tree shape).
 */
function brik_site_item_attrs( array $item, $class ) {
	$rel = $item['rel'];
	if ( '_blank' === $item['target'] ) {
		$rel = trim( $rel . ' noopener' );
	}
	return brik_attrs(
		array(
			'href'         => $item['url'] ? $item['url'] : '#',
			'class'        => brik_cls( $class, array( 'is-current' => $item['current'], 'is-ancestor' => $item['ancestor'] ) ),
			'target'       => $item['target'] ? $item['target'] : null,
			'rel'          => $rel ? $rel : null,
			'aria-current' => $item['current'] ? 'page' : null,
		)
	);
}

/**
 * Whether a menu item is only a dropdown label (no destination of its own).
 */
function brik_site_is_label( array $item ) {
	$url = trim( (string) ( isset( $item['url'] ) ? $item['url'] : '' ) );
	return '' === $url || '#' === $url || ( '#' === $url[0] && ! empty( $item['children'] ) );
}

/* -------------------------------------------------------------------------
 * Item model.
 *
 * Every source ends up as a list of items:
 * [ id, label, url, target, rel, icon, badge, badge_variant, description, type, children,
 *   mega_layout, mega_columns, mega_width, featured, layout_id, button_variant, classes,
 *   current, ancestor ].
 * ---------------------------------------------------------------------- */

function brik_nav_types() {
	return array( 'link', 'dropdown', 'mega', 'heading', 'button', 'divider' );
}

/**
 * Normalize a raw item tree (menu_tree field value, converted WordPress menu, legacy links).
 */
function brik_nav_normalize( $items, $depth = 0 ) {
	$out = array();
	if ( ! is_array( $items ) || $depth > 5 ) {
		return $out;
	}
	foreach ( array_values( $items ) as $raw ) {
		$item = brik_nav_item( $raw, $depth );
		if ( $item ) {
			$out[] = $item;
		}
	}
	return $out;
}

/**
 * One normalized item, or null when it has nothing to show.
 */
function brik_nav_item( $raw, $depth = 0 ) {
	if ( ! is_array( $raw ) ) {
		return null;
	}
	$str = static function ( $key ) use ( $raw ) {
		return isset( $raw[ $key ] ) && is_scalar( $raw[ $key ] ) ? trim( (string) $raw[ $key ] ) : '';
	};

	$type  = in_array( $str( 'type' ), brik_nav_types(), true ) ? $str( 'type' ) : '';
	$label = '' !== $str( 'label' ) ? $str( 'label' ) : $str( 'text' );
	if ( '' === $label && 'divider' !== $type ) {
		return null;
	}

	$link = isset( $raw['link'] ) ? $raw['link'] : ( isset( $raw['url'] ) ? $raw['url'] : array() );
	$link = is_string( $link ) ? array( 'url' => $link ) : ( is_array( $link ) ? $link : array() );
	$url  = isset( $link['url'] ) && is_scalar( $link['url'] ) ? trim( (string) $link['url'] ) : '';

	$children    = isset( $raw['children'] ) ? brik_nav_normalize( $raw['children'], $depth + 1 ) : array();
	$featured    = isset( $raw['featured'] ) && is_array( $raw['featured'] ) ? $raw['featured'] : array();
	$layout_id   = isset( $raw['layout_id'] ) && is_numeric( $raw['layout_id'] ) ? absint( $raw['layout_id'] ) : 0;
	$mega_layout = in_array( $str( 'mega_layout' ), array( 'columns', 'grid', 'featured', 'layout' ), true ) ? $str( 'mega_layout' ) : 'columns';
	$columns     = (int) $str( 'mega_columns' );

	if ( '' === $type || 'link' === $type ) {
		$type = $children ? 'dropdown' : 'link';
	} elseif ( 'dropdown' === $type && ! $children ) {
		$type = 'link';
	} elseif ( 'mega' === $type && ! $children && ! ( 'layout' === $mega_layout && $layout_id ) && ! ( 'featured' === $mega_layout && $featured ) ) {
		$type = 'link';
	}

	$ancestor = ! empty( $raw['_ancestor'] );
	foreach ( $children as $child ) {
		if ( $child['current'] || $child['ancestor'] ) {
			$ancestor = true;
			break;
		}
	}

	$badge_variant  = $str( 'badge_variant' );
	$button_variant = $str( 'button_variant' );
	$description    = '' !== $str( 'description' ) ? $str( 'description' ) : $str( 'desc' );

	return array(
		'id'             => sanitize_html_class( $str( 'id' ) ),
		'label'          => $label,
		'url'            => $url,
		'target'         => ! empty( $link['new_tab'] ) ? '_blank' : '',
		'rel'            => ! empty( $link['nofollow'] ) ? 'nofollow' : ( isset( $raw['_rel'] ) && is_string( $raw['_rel'] ) ? $raw['_rel'] : '' ),
		'icon'           => $str( 'icon' ),
		'badge'          => $str( 'badge' ),
		'badge_variant'  => array_key_exists( $badge_variant, brik_badge_variant_options() ) ? $badge_variant : 'secondary',
		'description'    => $description,
		'type'           => $type,
		'children'       => $children,
		'mega_layout'    => $mega_layout,
		'mega_columns'   => $columns ? max( 1, min( 5, $columns ) ) : 0,
		'mega_width'     => in_array( $str( 'mega_width' ), array( 'auto', 'container', 'full' ), true ) ? $str( 'mega_width' ) : '',
		'featured'       => $featured,
		'layout_id'      => $layout_id,
		'button_variant' => in_array( $button_variant, array( 'default', 'secondary', 'outline', 'ghost' ), true ) ? $button_variant : 'default',
		'classes'        => isset( $raw['_classes'] ) ? array_map( 'sanitize_html_class', (array) $raw['_classes'] ) : array(),
		'current'        => ! empty( $raw['_current'] ) || brik_nav_is_current( $url ),
		'ancestor'       => $ancestor,
	);
}

/**
 * Whether a URL points at the page being viewed (path comparison, query and hash ignored).
 */
function brik_nav_is_current( $url ) {
	static $here = null;
	$url = trim( (string) $url );
	if ( '' === $url || '#' === $url[0] || 0 === strpos( $url, 'mailto:' ) || 0 === strpos( $url, 'tel:' ) ) {
		return false;
	}
	if ( null === $here ) {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$here = array(
			'host' => (string) wp_parse_url( home_url(), PHP_URL_HOST ),
			'path' => untrailingslashit( (string) wp_parse_url( $uri, PHP_URL_PATH ) ),
		);
	}
	$parts = wp_parse_url( $url );
	if ( ! is_array( $parts ) || ( ! empty( $parts['host'] ) && strtolower( $parts['host'] ) !== strtolower( $here['host'] ) ) ) {
		return false;
	}
	$path = untrailingslashit( isset( $parts['path'] ) ? $parts['path'] : '' );
	return $path === $here['path'];
}

/**
 * Items from the legacy "links" repeater ([ text, link ]).
 */
function brik_nav_from_links( $links ) {
	$out = array();
	foreach ( is_array( $links ) ? $links : array() as $link ) {
		if ( is_array( $link ) && isset( $link['text'] ) && '' !== $link['text'] ) {
			$out[] = array(
				'label' => $link['text'],
				'link'  => isset( $link['link'] ) ? $link['link'] : array(),
			);
		}
	}
	return $out;
}

/**
 * Raw items from a WordPress menu tree.
 *
 * CSS classes set in Appearance → Menus shape the item: mega, mega-grid, mega-featured,
 * mega-cols-N, mega-container, mega-full, nav-heading, nav-divider, nav-button
 * (nav-button-outline …) and icon-{lucide name}. The item description becomes the description.
 *
 * @param array  $nodes   brik_site_menu_tree() result.
 * @param string $layout  Module dropdown layout: auto|list|mega (applies to top-level dropdowns without a mega class).
 * @param int    $columns Module default mega columns.
 */
function brik_nav_from_menu( array $nodes, $layout = 'auto', $columns = 0, $depth = 0 ) {
	$out = array();
	foreach ( $nodes as $node ) {
		$raw   = array(
			'label'       => $node['title'],
			'link'        => array( 'url' => $node['url'], 'new_tab' => '_blank' === $node['target'] ),
			'description' => wp_strip_all_tags( (string) $node['desc'] ),
			'children'    => brik_nav_from_menu( $node['children'], $layout, $columns, $depth + 1 ),
			'_current'    => $node['current'],
			'_ancestor'   => $node['ancestor'],
			'_rel'        => (string) $node['rel'],
			'_classes'    => array(),
		);
		$typed = false;
		foreach ( $node['classes'] as $class ) {
			$class = strtolower( trim( (string) $class ) );
			if ( in_array( $class, array( 'mega', 'mega-grid', 'mega-featured' ), true ) ) {
				$raw['type']        = 'mega';
				$raw['mega_layout'] = 'mega' === $class ? 'columns' : substr( $class, 5 );
				$typed              = true;
			} elseif ( preg_match( '/^mega-cols-([1-5])$/', $class, $m ) ) {
				$raw['mega_columns'] = (int) $m[1];
			} elseif ( in_array( $class, array( 'mega-container', 'mega-full', 'mega-auto' ), true ) ) {
				$raw['mega_width'] = substr( $class, 5 );
			} elseif ( in_array( $class, array( 'nav-heading', 'nav-divider', 'nav-button' ), true ) ) {
				$raw['type'] = substr( $class, 4 );
				$typed       = true;
			} elseif ( preg_match( '/^nav-button-(secondary|outline|ghost)$/', $class, $m ) ) {
				$raw['type']           = 'button';
				$raw['button_variant'] = $m[1];
				$typed                 = true;
			} elseif ( 0 === strpos( $class, 'icon-' ) ) {
				$raw['icon'] = substr( $class, 5 );
			} elseif ( '' !== $class && 0 !== strpos( $class, 'menu-item' ) && 0 !== strpos( $class, 'current' ) && 0 !== strpos( $class, 'page' ) ) {
				$raw['_classes'][] = $class;
			}
		}

		if ( ! $typed && 0 === $depth && $node['children'] && ( 'mega' === $layout || ( 'auto' === $layout && count( $node['children'] ) >= 6 ) ) ) {
			$raw['type']        = 'mega';
			$raw['mega_layout'] = 'columns';
		}
		if ( isset( $raw['type'] ) && 'mega' === $raw['type'] && empty( $raw['mega_columns'] ) && $columns ) {
			$raw['mega_columns'] = (int) $columns;
		}
		// The featured card of a WordPress mega menu describes the parent item itself.
		if ( isset( $raw['mega_layout'] ) && 'featured' === $raw['mega_layout'] ) {
			$raw['featured'] = array(
				'title'       => $node['title'],
				'text'        => $raw['description'],
				'link'        => array( 'url' => $node['url'] ),
				'button_text' => brik_site_is_label( $node ) ? '' : __( 'Learn more', 'brik-builder' ),
			);
		}
		$out[] = $raw;
	}
	return $out;
}

/**
 * Items for a menu module: custom tree, legacy links or a WordPress menu.
 *
 * @param array        $a   Resolved module attributes.
 * @param Brik\Context $ctx Render context (raw node attributes tell legacy nodes apart).
 */
function brik_nav_items( array $a, $ctx ) {
	if ( 'custom' === $a['source'] ) {
		$own = isset( $ctx->node['attrs'] ) ? (array) $ctx->node['attrs'] : array();
		// Nodes saved before the tree editor carry a flat "links" list and no "items".
		if ( empty( $own['items'] ) && ! empty( $a['links'] ) && is_array( $a['links'] ) ) {
			return brik_nav_normalize( brik_nav_from_links( $a['links'] ) );
		}
		return brik_nav_normalize( $a['items'] );
	}
	$layout = in_array( $a['dropdown_layout'], array( 'auto', 'list', 'mega' ), true ) ? $a['dropdown_layout'] : 'auto';
	return brik_nav_normalize( brik_nav_from_menu( brik_site_menu_tree( brik_site_menu_id( $a['menu'] ) ), $layout, (int) $a['mega_columns'] ) );
}

/**
 * Sample tree for new custom menus: a featured mega menu, a mega grid, a rich dropdown and links.
 */
function brik_nav_sample_items() {
	return array(
		array(
			'id'           => 'products',
			'label'        => __( 'Products', 'brik-builder' ),
			'type'         => 'mega',
			'mega_layout'  => 'featured',
			'mega_columns' => 2,
			'children'     => array(
				array( 'id' => 'p-analytics', 'label' => __( 'Analytics', 'brik-builder' ), 'icon' => 'chart-line', 'description' => __( 'Real-time dashboards for every metric that matters.', 'brik-builder' ), 'link' => array( 'url' => '#' ) ),
				array( 'id' => 'p-automation', 'label' => __( 'Automation', 'brik-builder' ), 'icon' => 'workflow', 'badge' => __( 'New', 'brik-builder' ), 'badge_variant' => 'success', 'description' => __( 'Build flows that run while you sleep.', 'brik-builder' ), 'link' => array( 'url' => '#' ) ),
				array( 'id' => 'p-security', 'label' => __( 'Security', 'brik-builder' ), 'icon' => 'shield-check', 'description' => __( 'SSO, audit logs and granular roles.', 'brik-builder' ), 'link' => array( 'url' => '#' ) ),
				array( 'id' => 'p-integrations', 'label' => __( 'Integrations', 'brik-builder' ), 'icon' => 'blocks', 'description' => __( 'Connect the 200+ tools your team uses.', 'brik-builder' ), 'link' => array( 'url' => '#' ) ),
				array( 'id' => 'p-api', 'label' => __( 'Developer API', 'brik-builder' ), 'icon' => 'code', 'description' => __( 'Typed SDKs, webhooks and a GraphQL API.', 'brik-builder' ), 'link' => array( 'url' => '#' ) ),
				array( 'id' => 'p-assist', 'label' => __( 'Assist', 'brik-builder' ), 'icon' => 'sparkles', 'badge' => __( 'Beta', 'brik-builder' ), 'badge_variant' => 'info', 'description' => __( 'Smart suggestions inside every workflow.', 'brik-builder' ), 'link' => array( 'url' => '#' ) ),
			),
			'featured'     => array(
				'eyebrow'     => __( 'What’s new', 'brik-builder' ),
				'title'       => __( 'Release 4.0 is here', 'brik-builder' ),
				'text'        => __( 'Faster pages, a new editor and dozens of improvements.', 'brik-builder' ),
				'button_text' => __( 'See what changed', 'brik-builder' ),
				'link'        => array( 'url' => '#' ),
			),
		),
		array(
			'id'           => 'solutions',
			'label'        => __( 'Solutions', 'brik-builder' ),
			'type'         => 'mega',
			'mega_layout'  => 'grid',
			'mega_columns' => 3,
			'children'     => array(
				array( 'id' => 's-startups', 'label' => __( 'Startups', 'brik-builder' ), 'icon' => 'rocket', 'description' => __( 'Launch fast and scale without rework.', 'brik-builder' ), 'link' => array( 'url' => '#' ) ),
				array( 'id' => 's-enterprise', 'label' => __( 'Enterprise', 'brik-builder' ), 'icon' => 'landmark', 'description' => __( 'Governance and support for large teams.', 'brik-builder' ), 'link' => array( 'url' => '#' ) ),
				array( 'id' => 's-agencies', 'label' => __( 'Agencies', 'brik-builder' ), 'icon' => 'briefcase', 'description' => __( 'Manage every client site in one place.', 'brik-builder' ), 'link' => array( 'url' => '#' ) ),
				array( 'id' => 's-commerce', 'label' => __( 'E-commerce', 'brik-builder' ), 'icon' => 'shopping-bag', 'description' => __( 'Storefronts that convert on every device.', 'brik-builder' ), 'link' => array( 'url' => '#' ) ),
				array( 'id' => 's-education', 'label' => __( 'Education', 'brik-builder' ), 'icon' => 'graduation-cap', 'description' => __( 'Course sites with members and payments.', 'brik-builder' ), 'link' => array( 'url' => '#' ) ),
				array( 'id' => 's-nonprofit', 'label' => __( 'Nonprofits', 'brik-builder' ), 'icon' => 'heart-pulse', 'description' => __( 'Campaign pages and donation forms.', 'brik-builder' ), 'link' => array( 'url' => '#' ) ),
			),
		),
		array(
			'id'       => 'resources',
			'label'    => __( 'Resources', 'brik-builder' ),
			'type'     => 'dropdown',
			'children' => array(
				array( 'id' => 'r-docs', 'label' => __( 'Documentation', 'brik-builder' ), 'icon' => 'book-open', 'description' => __( 'Guides and API reference.', 'brik-builder' ), 'link' => array( 'url' => '#' ) ),
				array( 'id' => 'r-blog', 'label' => __( 'Blog', 'brik-builder' ), 'icon' => 'newspaper', 'description' => __( 'Product news and stories.', 'brik-builder' ), 'link' => array( 'url' => '#' ) ),
				array( 'id' => 'r-community', 'label' => __( 'Community', 'brik-builder' ), 'icon' => 'users', 'description' => __( 'Ask questions, share builds.', 'brik-builder' ), 'link' => array( 'url' => '#' ) ),
				array( 'id' => 'r-sep', 'type' => 'divider' ),
				array( 'id' => 'r-support', 'label' => __( 'Help center', 'brik-builder' ), 'icon' => 'life-buoy', 'description' => __( 'Answers within hours.', 'brik-builder' ), 'link' => array( 'url' => '#' ) ),
			),
		),
		array(
			'id'    => 'pricing',
			'label' => __( 'Pricing', 'brik-builder' ),
			'link'  => array( 'url' => '#' ),
		),
	);
}

/* -------------------------------------------------------------------------
 * Markup pieces.
 * ---------------------------------------------------------------------- */

/**
 * Unique DOM id for a part of a menu module.
 */
function brik_nav_uid( $ctx, $prefix ) {
	static $count = array();
	$key           = $ctx->id . $prefix;
	$count[ $key ] = isset( $count[ $key ] ) ? $count[ $key ] + 1 : 1;
	return $ctx->uid( $prefix . '-' . $count[ $key ] );
}

/**
 * Anchor attributes for a normalized item.
 */
function brik_nav_attrs( array $item, $class, array $extra = array() ) {
	$rel = $item['rel'];
	if ( '_blank' === $item['target'] ) {
		$rel = trim( $rel . ' noopener' );
	}
	return brik_attrs(
		array_merge(
			array(
				'href'         => '' !== $item['url'] ? $item['url'] : '#',
				'class'        => brik_cls( $class, array( 'is-current' => $item['current'], 'is-ancestor' => $item['ancestor'] ) ),
				'target'       => $item['target'] ? $item['target'] : null,
				'rel'          => $rel ? $rel : null,
				'aria-current' => $item['current'] ? 'page' : null,
			),
			$extra
		)
	);
}

function brik_nav_has_url( array $item ) {
	return ! brik_site_is_label( $item );
}

function brik_nav_badge( array $item ) {
	if ( '' === $item['badge'] ) {
		return '';
	}
	return '<span class="' . esc_attr( brik_badge_class( $item['badge_variant'], 'sm', 'brik-menu-badge' ) ) . '">' . esc_html( $item['badge'] ) . '</span>';
}

/**
 * Icon, label and badge of a top-level link.
 */
function brik_nav_label( array $item ) {
	$icon = '' !== $item['icon'] ? brik_icon( $item['icon'], 'brik-menu-licon size-4 shrink-0' ) : '';
	return $icon . '<span class="brik-menu-text">' . brik_inline( $item['label'] ) . '</span>' . brik_nav_badge( $item );
}

/**
 * Link inside a dropdown or mega panel: optional icon tile, title, badge and description.
 */
function brik_nav_panel_link( array $item, array $o, $extra = '' ) {
	$desc = $o['show_desc'] && '' !== $item['description'] ? '<span class="brik-menu-desc">' . esc_html( $item['description'] ) . '</span>' : '';
	$icon = '' !== $item['icon'] ? '<span class="brik-menu-icon" aria-hidden="true">' . brik_icon( $item['icon'], 'size-4' ) . '</span>' : '';
	$cls  = brik_cls( 'brik-menu-sublink', array( 'has-icon' => '' !== $icon, 'has-desc' => '' !== $desc ), $extra );
	return '<a' . brik_nav_attrs( $item, $cls ) . '>' . $icon . '<span class="brik-menu-subtext"><span class="brik-menu-subtitle">' . brik_inline( $item['label'] ) . brik_nav_badge( $item ) . '</span>' . $desc . '</span></a>';
}

/**
 * List items of a dropdown panel (links, headings, separators, nested lists).
 */
function brik_nav_panel_items( array $items, array $o ) {
	$out = '';
	foreach ( $items as $item ) {
		switch ( $item['type'] ) {
			case 'divider':
				$out .= '<li class="brik-menu-sep" role="separator"></li>';
				break;
			case 'heading':
				$out .= '<li class="brik-menu-subitem"><p class="brik-menu-heading">' . brik_inline( $item['label'] ) . '</p>';
				$out .= $item['children'] ? '<ul class="brik-menu-group">' . brik_nav_panel_items( $item['children'], $o ) . '</ul>' : '';
				$out .= '</li>';
				break;
			case 'button':
				$out .= '<li class="brik-menu-subitem brik-menu-subitem--button"><a' . brik_nav_attrs( $item, brik_button_class( $item['button_variant'], 'sm', 'brik-menu-button w-full' ) ) . '>' . brik_nav_label( $item ) . '</a></li>';
				break;
			default:
				$out .= '<li class="brik-menu-subitem">' . brik_nav_panel_link( $item, $o );
				$out .= $item['children'] ? '<ul class="brik-menu-nested">' . brik_nav_panel_items( $item['children'], $o ) . '</ul>' : '';
				$out .= '</li>';
		}
	}
	return $out;
}

/**
 * Whether any item in a list shows an icon or a description (switches to the rich list style).
 */
function brik_nav_is_rich( array $items, array $o ) {
	foreach ( $items as $item ) {
		if ( '' !== $item['icon'] || ( $o['show_desc'] && '' !== $item['description'] ) ) {
			return true;
		}
	}
	return false;
}

function brik_nav_dropdown_panel( array $item, array $o ) {
	$class = brik_cls( 'brik-menu-panel brik-menu-panel--list', array( 'brik-menu-panel--rich' => brik_nav_is_rich( $item['children'], $o ) ) );
	return '<ul class="' . esc_attr( $class ) . '">' . brik_nav_panel_items( $item['children'], $o ) . '</ul>';
}

/**
 * Column groups: a child with children is a heading plus links, a lone link fills a cell.
 */
function brik_nav_mega_columns( array $children, array $o ) {
	$out = '';
	foreach ( $children as $child ) {
		if ( 'divider' === $child['type'] ) {
			continue;
		}
		if ( $child['children'] || 'heading' === $child['type'] ) {
			$head = brik_nav_has_url( $child ) && 'heading' !== $child['type']
				? '<a' . brik_nav_attrs( $child, 'brik-menu-heading brik-menu-heading--link' ) . '>' . brik_inline( $child['label'] ) . brik_icon( 'arrow-right', 'size-3' ) . '</a>'
				: '<p class="brik-menu-heading">' . brik_inline( $child['label'] ) . '</p>';
			$out .= '<div class="brik-mega-col">' . $head . ( $child['children'] ? '<ul class="brik-mega-links">' . brik_nav_panel_items( $child['children'], $o ) . '</ul>' : '' ) . '</div>';
		} else {
			$out .= '<div class="brik-mega-col"><ul class="brik-mega-links">' . brik_nav_panel_items( array( $child ), $o ) . '</ul></div>';
		}
	}
	return '<div class="brik-mega-cols">' . $out . '</div>';
}

/**
 * Card grid: icon tile, title and description per child.
 */
function brik_nav_mega_grid( array $children, array $o ) {
	$out = '';
	foreach ( $children as $child ) {
		if ( 'divider' === $child['type'] ) {
			$out .= '<li class="brik-mega-span brik-menu-sep" role="separator"></li>';
		} elseif ( 'heading' === $child['type'] && ! $child['children'] ) {
			$out .= '<li class="brik-mega-span"><p class="brik-menu-heading">' . brik_inline( $child['label'] ) . '</p></li>';
		} elseif ( 'button' === $child['type'] ) {
			$out .= '<li class="brik-mega-span">' . brik_nav_panel_items( array( $child ), $o ) . '</li>';
		} else {
			$out .= '<li>' . brik_nav_panel_link( $child, $o, 'brik-mega-card' );
			$out .= $child['children'] ? '<ul class="brik-menu-nested">' . brik_nav_panel_items( $child['children'], $o ) . '</ul>' : '';
			$out .= '</li>';
		}
	}
	return '<ul class="brik-mega-grid">' . $out . '</ul>';
}

/**
 * Featured card of a mega panel; falls back to the parent item's label and description.
 */
function brik_nav_feature( array $item, $compact = false ) {
	$f      = $item['featured'];
	$get    = static function ( $key ) use ( $f ) {
		return isset( $f[ $key ] ) && is_scalar( $f[ $key ] ) ? trim( (string) $f[ $key ] ) : '';
	};
	$title  = '' !== $get( 'title' ) ? $get( 'title' ) : $item['label'];
	$text   = '' !== $get( 'text' ) ? $get( 'text' ) : $item['description'];
	$link   = isset( $f['link'] ) ? $f['link'] : array();
	$link   = is_string( $link ) ? array( 'url' => $link ) : ( is_array( $link ) ? $link : array() );
	$url    = isset( $link['url'] ) && is_scalar( $link['url'] ) && '' !== trim( (string) $link['url'] ) ? $link['url'] : ( brik_nav_has_url( $item ) ? $item['url'] : '' );
	$image  = isset( $f['image'] ) && brik_site_has_image( $f['image'] ) ? brik_image( $f['image'], 'medium_large', array( 'class' => 'brik-mega-feature-img', 'alt' => '' ) ) : '';
	$button = $get( 'button_text' );

	$body  = '' !== $get( 'eyebrow' ) ? '<span class="brik-mega-feature-eyebrow">' . brik_inline( $get( 'eyebrow' ) ) . '</span>' : '';
	$body .= '<span class="brik-mega-feature-title">' . brik_inline( $title ) . '</span>';
	$body .= '' !== $text ? '<span class="brik-mega-feature-text">' . brik_inline( $text ) . '</span>' : '';
	$body .= '' !== $button ? '<span class="' . esc_attr( brik_button_class( $image ? 'secondary' : 'default', 'sm', 'brik-mega-feature-button mt-2 w-fit' ) ) . '">' . brik_inline( $button ) . brik_icon( 'arrow-right', 'size-3.5' ) . '</span>' : '';

	$class = brik_cls( 'brik-mega-feature', array( 'has-image' => '' !== $image, 'brik-mega-feature--compact' => $compact ) );
	$inner = $image . '<span class="brik-mega-feature-body">' . $body . '</span>';
	if ( '' === $url ) {
		return '<div class="' . esc_attr( $class ) . '">' . $inner . '</div>';
	}
	return '<a' . brik_link_attrs( array_merge( $link, array( 'url' => $url ) ), array( 'class' => $class ) ) . '>' . $inner . '</a>';
}

/**
 * A library item rendered inside a panel, so mega menus can be designed with the builder.
 */
function brik_nav_layout_html( $layout_id, $ctx ) {
	$layout_id = absint( $layout_id );
	if ( ! $layout_id || ! Brik\Library::item( $layout_id ) || ( ! $ctx->canvas && 'publish' !== get_post_status( $layout_id ) ) ) {
		return '';
	}
	$renderer = $ctx->renderer;
	// Same key as the global module, so a menu inside its own mega layout can't loop.
	if ( ! $renderer->enter( 'global:' . $layout_id ) ) {
		return '';
	}
	$canvas           = $renderer->canvas;
	$renderer->canvas = false;
	$html             = $renderer->render_nodes( Brik\Library::nodes_for( $layout_id, 'root' ) );
	$renderer->canvas = $canvas;
	$renderer->leave( 'global:' . $layout_id );
	return $html;
}

/**
 * Default column count for a mega layout.
 */
function brik_nav_mega_cols( array $item ) {
	if ( $item['mega_columns'] ) {
		return $item['mega_columns'];
	}
	$count = count( $item['children'] );
	switch ( $item['mega_layout'] ) {
		case 'grid':
			return $count >= 6 || 3 === $count ? 3 : 2;
		case 'featured':
			return brik_nav_has_groups( $item['children'] ) ? max( 1, min( 3, $count ) ) : ( $count > 3 ? 2 : 1 );
		default:
			return max( 2, min( 4, $count ) );
	}
}

function brik_nav_has_groups( array $items ) {
	foreach ( $items as $item ) {
		if ( $item['children'] ) {
			return true;
		}
	}
	return false;
}

/**
 * Wide panel of a mega item.
 */
function brik_nav_mega_panel( array $item, array $o ) {
	$layout = $item['mega_layout'];
	$body   = '';
	if ( 'layout' === $layout ) {
		$body = brik_nav_layout_html( $item['layout_id'], $o['ctx'] );
		if ( '' === $body && $o['ctx']->canvas ) {
			$body = $o['ctx']->placeholder( __( 'Choose a library item for this mega menu', 'brik-builder' ) );
		}
		if ( '' === $body && $item['children'] ) {
			$layout = 'columns';
		}
	}
	if ( 'grid' === $layout ) {
		$body = brik_nav_mega_grid( $item['children'], $o );
	} elseif ( 'featured' === $layout ) {
		$main = '';
		if ( $item['children'] ) {
			$main = brik_nav_has_groups( $item['children'] ) ? brik_nav_mega_columns( $item['children'], $o ) : brik_nav_mega_grid( $item['children'], $o );
		}
		$body = '<div class="brik-mega-main">' . $main . '</div>' . brik_nav_feature( $item );
	} elseif ( 'columns' === $layout ) {
		$body = brik_nav_mega_columns( $item['children'], $o );
	}
	return '<div class="brik-menu-panel brik-menu-panel--mega brik-mega brik-mega--' . esc_attr( $layout ) . '" style="--brik-mega-cols:' . (int) brik_nav_mega_cols( $item ) . '"><div class="brik-mega-inner">' . $body . '</div></div>';
}

/* -------------------------------------------------------------------------
 * Desktop bar.
 * ---------------------------------------------------------------------- */

/**
 * Top-level items of a horizontal menu.
 */
function brik_nav_bar_items( array $items, array $o ) {
	$out = '';
	foreach ( $items as $item ) {
		$extra = implode( ' ', $item['classes'] );
		switch ( $item['type'] ) {
			case 'divider':
				$out .= '<li class="brik-menu-item brik-menu-divider" aria-hidden="true"></li>';
				continue 2;
			case 'heading':
				$out .= '<li class="' . esc_attr( brik_cls( 'brik-menu-item', $extra ) ) . '"><span class="brik-menu-link brik-menu-label">' . brik_nav_label( $item ) . '</span></li>';
				continue 2;
			case 'button':
				$out .= '<li class="' . esc_attr( brik_cls( 'brik-menu-item brik-menu-item--button', $extra ) ) . '"><a' . brik_nav_attrs( $item, brik_button_class( $item['button_variant'], 'sm', 'brik-menu-button' ) ) . '>' . brik_nav_label( $item ) . '</a></li>';
				continue 2;
			case 'link':
				$out .= '<li class="' . esc_attr( brik_cls( 'brik-menu-item', $extra ) ) . '"><a' . brik_nav_attrs( $item, 'brik-menu-link' ) . '>' . brik_nav_label( $item ) . '</a></li>';
				continue 2;
		}

		$mega    = 'mega' === $item['type'];
		$sub_id  = brik_nav_uid( $o['ctx'], 'sub' );
		$chevron = brik_icon( 'chevron-down', 'brik-menu-chevron size-3' );
		$state   = array( 'is-current' => $item['current'], 'is-ancestor' => $item['ancestor'] );
		$toggle  = array(
			'type'          => 'button',
			'aria-expanded' => 'false',
			'aria-controls' => $sub_id,
		);
		if ( brik_nav_has_url( $item ) ) {
			$head = '<span class="' . esc_attr( brik_cls( 'brik-menu-link brik-menu-split', $state ) ) . '"><a' . brik_nav_attrs( $item, 'brik-menu-split-link' ) . '>' . brik_nav_label( $item ) . '</a>'
				/* translators: %s: menu item label */
				. '<button' . brik_attrs( array_merge( $toggle, array( 'class' => 'brik-menu-trigger', 'aria-label' => sprintf( __( '%s submenu', 'brik-builder' ), wp_strip_all_tags( $item['label'] ) ) ) ) ) . '>' . $chevron . '</button></span>';
		} else {
			$head = '<button' . brik_attrs( array_merge( $toggle, array( 'class' => brik_cls( 'brik-menu-link brik-menu-trigger', $state ) ) ) ) . '>' . brik_nav_label( $item ) . ( $o['chevron'] ? $chevron : '' ) . '</button>';
		}

		$width = 'auto';
		if ( $mega ) {
			$width = '' !== $item['mega_width'] ? $item['mega_width'] : ( 'layout' === $item['mega_layout'] ? 'container' : 'auto' );
		}
		$panel = $mega ? brik_nav_mega_panel( $item, $o ) : brik_nav_dropdown_panel( $item, $o );
		$li    = brik_cls( 'brik-menu-item brik-menu-has-sub', $mega ? 'brik-menu-item--mega' : 'brik-menu-item--dropdown', $extra );

		$out .= '<li class="' . esc_attr( $li ) . '" data-state="closed">' . $head;
		$out .= '<div class="brik-menu-sub" id="' . esc_attr( $sub_id ) . '" data-state="closed" data-kind="' . ( $mega ? 'mega' : 'dropdown' ) . '" data-width="' . esc_attr( $width ) . '">' . $panel . '</div></li>';
	}
	return $out;
}

/**
 * Nested list for vertical menus (footers, sidebars).
 */
function brik_nav_vertical_items( array $items, array $o ) {
	$out = '';
	foreach ( $items as $item ) {
		switch ( $item['type'] ) {
			case 'divider':
				$out .= '<li class="brik-menu-sep" aria-hidden="true"></li>';
				break;
			case 'button':
				$out .= '<li class="brik-menu-item brik-menu-item--button"><a' . brik_nav_attrs( $item, brik_button_class( $item['button_variant'], 'sm', 'brik-menu-button' ) ) . '>' . brik_nav_label( $item ) . '</a></li>';
				break;
			case 'heading':
				$out .= '<li class="brik-menu-item"><p class="brik-menu-heading">' . brik_inline( $item['label'] ) . '</p>';
				$out .= $item['children'] ? '<ul class="brik-menu-nested">' . brik_nav_vertical_items( $item['children'], $o ) . '</ul>' : '';
				$out .= '</li>';
				break;
			default:
				$out .= '<li class="brik-menu-item"><a' . brik_nav_attrs( $item, 'brik-menu-link' ) . '>' . brik_nav_label( $item ) . '</a>';
				$out .= $item['children'] ? '<ul class="brik-menu-nested">' . brik_nav_vertical_items( $item['children'], $o ) . '</ul>' : '';
				$out .= '</li>';
		}
	}
	return $out;
}

/* -------------------------------------------------------------------------
 * Mobile menu.
 * ---------------------------------------------------------------------- */

/**
 * Children shown one level down on mobile. Mega groups flatten into a heading plus its links.
 */
function brik_nav_mobile_children( array $item ) {
	if ( 'mega' !== $item['type'] ) {
		return $item['children'];
	}
	$out = array();
	foreach ( $item['children'] as $child ) {
		if ( $child['children'] ) {
			$out[] = array_merge( $child, array( 'type' => 'heading', 'children' => array() ) );
			foreach ( $child['children'] as $grandchild ) {
				$out[] = $grandchild;
			}
		} else {
			$out[] = $child;
		}
	}
	return $out;
}

function brik_nav_mobile_label( array $item, array $o ) {
	$icon = '' !== $item['icon'] ? '<span class="brik-mm-icon" aria-hidden="true">' . brik_icon( $item['icon'], 'size-4' ) . '</span>' : '';
	$desc = $o['show_desc'] && '' !== $item['description'] ? '<span class="brik-mm-desc">' . esc_html( $item['description'] ) . '</span>' : '';
	return $icon . '<span class="brik-mm-text"><span class="brik-mm-title">' . brik_inline( $item['label'] ) . brik_nav_badge( $item ) . '</span>' . $desc . '</span>';
}

/**
 * Extra content at the end of a mobile sub level: the featured card or a library layout.
 */
function brik_nav_mobile_extra( array $item, array $o ) {
	if ( 'mega' !== $item['type'] ) {
		return '';
	}
	if ( 'featured' === $item['mega_layout'] ) {
		return '<li class="brik-mm-feature">' . brik_nav_feature( $item, true ) . '</li>';
	}
	if ( 'layout' === $item['mega_layout'] && ! $item['children'] ) {
		$html = brik_nav_layout_html( $item['layout_id'], $o['ctx'] );
		return '' !== $html ? '<li class="brik-mm-layout">' . $html . '</li>' : '';
	}
	return '';
}

/**
 * One mobile list item that has no sub level.
 */
function brik_nav_mobile_leaf( array $item, array $o, $style ) {
	switch ( $item['type'] ) {
		case 'divider':
			return '<li class="brik-mm-sep" aria-hidden="true"' . $style . '></li>';
		case 'heading':
			return '<li class="brik-mm-item"' . $style . '><p class="brik-mm-heading">' . brik_inline( $item['label'] ) . '</p></li>';
		case 'button':
			return '<li class="brik-mm-item brik-mm-item--button"' . $style . '><a' . brik_nav_attrs( $item, brik_button_class( $item['button_variant'], 'lg', 'brik-mm-button w-full' ) ) . '>' . brik_nav_label( $item ) . '</a></li>';
	}
	return '<li class="brik-mm-item"' . $style . '><a' . brik_nav_attrs( $item, 'brik-sheet-link' ) . '>' . brik_nav_mobile_label( $item, $o ) . '</a></li>';
}

/**
 * Accordion list: sub levels expand in place.
 */
function brik_nav_mobile_accordion( array $items, array $o, $depth = 0 ) {
	$out = '';
	foreach ( array_values( $items ) as $i => $item ) {
		$style    = 0 === $depth ? ' style="--i:' . (int) $i . '"' : '';
		$children = 'heading' === $item['type'] ? array() : brik_nav_mobile_children( $item );
		if ( ! $children && ! ( 'mega' === $item['type'] && '' !== brik_nav_mobile_extra( $item, $o ) ) ) {
			$out .= brik_nav_mobile_leaf( $item, $o, $style );
			continue;
		}
		$sub_id  = brik_nav_uid( $o['ctx'], 'msub' );
		$open    = $item['ancestor'];
		$chevron = brik_icon( 'chevron-down', 'brik-mm-chevron size-4' );
		$expand  = array(
			'type'                => 'button',
			'aria-expanded'       => $open ? 'true' : 'false',
			'aria-controls'       => $sub_id,
			'data-brik-mm-expand' => true,
		);
		$out .= '<li class="brik-mm-item brik-mm-item--parent"' . $style . '><div class="brik-mm-row">';
		if ( brik_nav_has_url( $item ) ) {
			$out .= '<a' . brik_nav_attrs( $item, 'brik-sheet-link' ) . '>' . brik_nav_mobile_label( $item, $o ) . '</a>';
			/* translators: %s: menu item label */
			$out .= '<button' . brik_attrs( array_merge( $expand, array( 'class' => 'brik-mm-expand', 'aria-label' => sprintf( __( '%s submenu', 'brik-builder' ), wp_strip_all_tags( $item['label'] ) ) ) ) ) . '>' . $chevron . '</button>';
		} else {
			$out .= '<button' . brik_attrs( array_merge( $expand, array( 'class' => brik_cls( 'brik-sheet-link brik-mm-expand-row', array( 'is-ancestor' => $item['ancestor'] ) ) ) ) ) . '>' . brik_nav_mobile_label( $item, $o ) . $chevron . '</button>';
		}
		$out .= '</div><div class="' . esc_attr( brik_cls( 'brik-mm-sub', array( 'is-open' => $open ) ) ) . '" id="' . esc_attr( $sub_id ) . '"><div class="brik-mm-sub-inner"><ul class="brik-sheet-sub">'
			. brik_nav_mobile_accordion( $children, $o, $depth + 1 ) . brik_nav_mobile_extra( $item, $o ) . '</ul></div></div></li>';
	}
	return $out;
}

/**
 * Drilldown list: each sub level is its own panel that slides in. Levels are collected
 * into $levels so they can sit side by side in one container.
 */
function brik_nav_mobile_drill( array $items, array $o, array &$levels, $depth = 0 ) {
	$out = '';
	foreach ( array_values( $items ) as $i => $item ) {
		$style    = ' style="--i:' . (int) $i . '"';
		$children = 'heading' === $item['type'] ? array() : brik_nav_mobile_children( $item );
		$extra    = brik_nav_mobile_extra( $item, $o );
		if ( ! $children && '' === $extra ) {
			$out .= brik_nav_mobile_leaf( $item, $o, $style );
			continue;
		}
		$level_id = brik_nav_uid( $o['ctx'], 'level' );
		$out     .= '<li class="brik-mm-item brik-mm-item--parent"' . $style . '><button type="button" class="' . esc_attr( brik_cls( 'brik-sheet-link brik-mm-next', array( 'is-ancestor' => $item['ancestor'] ) ) ) . '" data-brik-mm-next="' . esc_attr( $level_id ) . '" aria-controls="' . esc_attr( $level_id ) . '">'
			. brik_nav_mobile_label( $item, $o ) . brik_icon( 'chevron-right', 'brik-mm-chevron size-4' ) . '</button></li>';

		$list = '';
		if ( brik_nav_has_url( $item ) ) {
			$list .= '<li class="brik-mm-item" style="--i:0"><a' . brik_nav_attrs( $item, 'brik-sheet-link brik-mm-overview' ) . '><span class="brik-mm-text"><span class="brik-mm-title">'
				/* translators: %s: menu item label */
				. esc_html( sprintf( __( 'All of %s', 'brik-builder' ), wp_strip_all_tags( $item['label'] ) ) ) . '</span></span>' . brik_icon( 'arrow-right', 'brik-mm-chevron size-4' ) . '</a></li>';
		}
		$list .= brik_nav_mobile_drill( $children, $o, $levels, $depth + 1 ) . $extra;

		$levels[] = '<div class="brik-mm-level" id="' . esc_attr( $level_id ) . '" data-brik-mm-level inert>'
			. '<div class="brik-mm-level-head"><button type="button" class="brik-mm-back" data-brik-mm-back>' . brik_icon( 'chevron-left', 'size-4' ) . '<span>' . esc_html__( 'Back', 'brik-builder' ) . '</span></button>'
			. '<p class="brik-mm-level-title">' . brik_inline( $item['label'] ) . '</p></div>'
			. '<ul class="brik-sheet-list">' . $list . '</ul></div>';
	}
	return $out;
}

/**
 * Menu button that opens the mobile menu.
 */
function brik_nav_toggle( array $a, $panel_id ) {
	$style = in_array( $a['toggle_style'], array( 'morph', 'icon', 'text' ), true ) ? $a['toggle_style'] : 'morph';
	$icon  = '' !== (string) $a['toggle_icon'] ? $a['toggle_icon'] : 'menu';
	$swap  = '<span class="brik-toggle-icons" aria-hidden="true"><span class="brik-toggle-open">' . brik_icon( $icon, 'size-5' ) . '</span><span class="brik-toggle-close">' . brik_icon( 'x', 'size-5' ) . '</span></span>';
	$attrs = array(
		'type'                  => 'button',
		'aria-expanded'         => 'false',
		'aria-controls'         => $panel_id,
		'data-brik-menu-toggle' => true,
	);
	if ( 'text' === $style ) {
		$text  = '' !== trim( (string) $a['toggle_text'] ) ? $a['toggle_text'] : __( 'Menu', 'brik-builder' );
		$inner = '<span class="brik-toggle-text">' . esc_html( wp_strip_all_tags( $text ) ) . '</span>' . $swap;
		$class = brik_button_class( 'outline', 'sm', 'brik-menu-toggle brik-menu-toggle--text' );
	} else {
		$attrs['aria-label'] = __( 'Open menu', 'brik-builder' );
		$inner               = 'morph' === $style ? '<span class="brik-burger" aria-hidden="true"><span></span><span></span><span></span></span>' : $swap;
		$class               = brik_button_class( 'ghost', 'icon', 'brik-menu-toggle brik-menu-toggle--' . $style );
	}
	$attrs['class'] = $class;
	return '<button' . brik_attrs( $attrs ) . '>' . $inner . '</button>';
}

/**
 * Mobile menu dialog.
 */
function brik_nav_mobile( array $items, array $a, array $o, $panel_id ) {
	$type   = in_array( $a['mobile_type'], array( 'drawer', 'fullscreen', 'dropdown', 'bottom' ), true ) ? $a['mobile_type'] : 'drawer';
	$anim   = in_array( $a['mobile_animation'], array( 'slide', 'fade', 'scale' ), true ) ? $a['mobile_animation'] : 'slide';
	$mode   = 'drilldown' === $a['submenu_mode'] ? 'drilldown' : 'accordion';
	$class  = brik_cls(
		'brik-mobile-menu',
		'brik-mobile-menu--' . $type,
		'drawer' === $type ? ( 'left' === $a['sheet_side'] ? 'brik-mobile-menu--left' : 'brik-mobile-menu--right' ) : '',
		'brik-mobile-menu--anim-' . $anim,
		'brik-mobile-menu--' . $mode,
		array( 'brik-mobile-menu--stagger' => ! empty( $a['mobile_stagger'] ) )
	);

	$head = '';
	if ( 'dropdown' !== $type ) {
		$title = '' !== trim( (string) $a['sheet_title'] ) ? '<p class="brik-sheet-title">' . brik_inline( $a['sheet_title'] ) . '</p>' : '<span></span>';
		$head  = ( 'bottom' === $type ? '<div class="brik-mm-handle" data-brik-mm-handle aria-hidden="true"><span></span></div>' : '' )
			. '<div class="brik-sheet-header">' . $title
			. '<button type="button" class="' . esc_attr( brik_button_class( 'ghost', 'icon', 'brik-sheet-close' ) ) . '" data-brik-mm-close aria-label="' . esc_attr__( 'Close menu', 'brik-builder' ) . '">' . brik_icon( 'x', 'size-5' ) . '</button></div>';
	}

	if ( 'drilldown' === $mode ) {
		$levels = array();
		$root   = brik_nav_mobile_drill( $items, $o, $levels );
		$body   = '<div class="brik-mm-levels"><div class="brik-mm-level is-active" data-brik-mm-level="root"><ul class="brik-sheet-list brik-mm-root">' . $root . '</ul></div>' . implode( '', $levels ) . '</div>';
	} else {
		$body = '<ul class="brik-sheet-list brik-mm-root">' . brik_nav_mobile_accordion( $items, $o ) . '</ul>';
	}

	$foot = '';
	if ( $o['cta'] && ! empty( $a['mobile_cta'] ) ) {
		$foot .= '<a' . brik_link_attrs( $a['cta_link'], array( 'class' => brik_button_class( $a['cta_variant'], 'lg', 'brik-menu-cta w-full' ) ) ) . '>' . brik_inline( $a['cta_text'] ) . '</a>';
	}
	$socials = '';
	foreach ( brik_items( $a['mobile_socials'] ) as $social ) {
		$network = isset( $social['network'] ) ? sanitize_key( $social['network'] ) : '';
		$url     = isset( $social['url'] ) ? trim( (string) $social['url'] ) : '';
		if ( '' === $network || '' === $url ) {
			continue;
		}
		$brand    = brik_brand( $network );
		$socials .= '<a class="brik-mm-social" href="' . esc_url( $url ) . '" target="_blank" rel="noopener" aria-label="' . esc_attr( $brand['title'] ) . '">' . brik_icon( $brand['icon'], 'size-4' ) . '</a>';
	}
	$foot .= '' !== $socials ? '<div class="brik-mm-socials">' . $socials . '</div>' : '';
	$foot .= '' !== trim( (string) $a['mobile_text'] ) ? '<div class="brik-mm-text-block">' . brik_inline( nl2br( (string) $a['mobile_text'] ) ) . '</div>' : '';

	return '<dialog class="' . esc_attr( $class ) . '" id="' . esc_attr( $panel_id ) . '" aria-label="' . esc_attr__( 'Menu', 'brik-builder' ) . '">'
		. '<div class="brik-mm-backdrop" data-brik-mm-close></div>'
		. '<div class="brik-menu-sheet brik-mm-panel">' . $head
		. '<div class="brik-mm-body">' . $body . '</div>'
		. ( '' !== $foot ? '<div class="brik-sheet-footer">' . $foot . '</div>' : '' )
		. '</div></dialog>';
}
