<?php
/**
 * Product filters: price, categories, attributes, rating, stock, sale, search and sort for a
 * products module on the same page (or for WooCommerce's own shop query when no target is
 * set). A plain GET form underneath; the front-end script applies filters over AJAX, keeps
 * the URL in sync and turns the panel into a slide-in sheet on phones.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

if ( ! brik_woo_shop_enabled() ) {
	return null;
}

$brik_pf_defaults = array(
	array(
		'type'  => 'category',
		'label' => __( 'Category', 'brik-builder' ),
	),
	array(
		'type'  => 'price',
		'label' => __( 'Price', 'brik-builder' ),
	),
);
foreach ( brik_woo_attribute_options() as $brik_tax => $brik_label ) {
	$brik_pf_defaults[] = array(
		'type'      => 'attribute',
		'label'     => $brik_label,
		'attribute' => $brik_tax,
	);
}
$brik_pf_defaults[] = array(
	'type'  => 'rating',
	'label' => __( 'Rating', 'brik-builder' ),
);
$brik_pf_defaults[] = array(
	'type'  => 'sale',
	'label' => __( 'On sale', 'brik-builder' ),
);
$brik_pf_defaults[] = array(
	'type'  => 'stock',
	'label' => __( 'In stock', 'brik-builder' ),
);

/**
 * One checkbox/radio/pill/swatch option.
 */
$brik_pf_option = static function ( $ui, $type, $name, $value, $label, $checked, $count, $extra = '', $chip = null ) {
	$chip       = null === $chip ? wp_strip_all_tags( $label ) : $chip;
	$count_html = null !== $count ? '<span class="brik-pf-count ml-auto pl-2 text-xs text-muted-foreground tabular-nums">' . (int) $count . '</span>' : '';
	$input      = '<input' . brik_attrs(
		array(
			'type'       => $type,
			'name'       => $name,
			'value'      => $value,
			'checked'    => $checked,
			'class'      => 'peer sr-only',
			'data-chip'  => $chip,
		)
	) . '>';
	if ( 'swatches' === $ui ) {
		return '<label class="brik-pf-swatch relative cursor-pointer" title="' . esc_attr( wp_strip_all_tags( $label ) ) . '">' . $input
			. '<span class="block size-8 rounded-full border border-black/10 shadow-xs ring-offset-2 ring-offset-background transition-shadow peer-checked:ring-2 peer-checked:ring-foreground peer-focus-visible:ring-[3px] peer-focus-visible:ring-ring/50 dark:border-white/15" style="' . esc_attr( $extra ? 'background:' . $extra : '' ) . '"></span>'
			. ( $extra ? '' : '<span class="absolute inset-0 grid place-items-center text-[10px] font-medium uppercase">' . esc_html( substr( wp_strip_all_tags( $label ), 0, 2 ) ) . '</span>' )
			. '<span class="sr-only">' . esc_html( $label ) . '</span></label>';
	}
	if ( 'pills' === $ui ) {
		return '<label class="brik-pf-pill relative inline-flex cursor-pointer">' . $input
			. '<span class="inline-flex h-8 min-w-10 items-center justify-center rounded-md border border-input bg-background px-3 text-sm font-medium whitespace-nowrap shadow-xs transition-colors hover:bg-accent hover:text-accent-foreground peer-checked:border-primary peer-checked:bg-primary peer-checked:text-primary-foreground peer-focus-visible:ring-[3px] peer-focus-visible:ring-ring/50 dark:bg-input/30">' . esc_html( $label ) . '</span></label>';
	}
	$attrs = array(
		'name'      => $name,
		'value'     => $value,
		'checked'   => $checked,
		'data-chip' => $chip,
	);
	return str_replace( '<label class="brik-choice-label', '<label class="brik-pf-check w-full', brik_choice( $type, $attrs, '<span class="flex w-full items-center">' . $label . $count_html . '</span>' ) );
};

return array(
	'type'        => 'product_filters',
	'title'       => __( 'Product filters', 'brik-builder' ),
	'category'    => 'shop',
	'icon'        => 'sliders-horizontal',
	'description' => 'Filters for a products module on the same page. target: the products module\'s css_id (e.g. "shop"); empty = filter WooCommerce\'s own shop/archive query with its native parameters (min_price, max_price, filter_{attribute}, rating_filter, orderby). '
		. 'filters: repeater [{type: price|category|attribute|rating|stock|sale|search|sort, label, attribute (pa_color…, type attribute), ui: auto|checkboxes|pills|swatches|select (auto: swatches for color attributes, pills for other attributes, a tree of checkboxes for categories), show_counts (bool), collapsed (bool)}]. '
		. 'layout: vertical (sidebar)|horizontal (bar of dropdowns). apply: instant|button. chips (active filter chips with clear all). mobile_sheet: on phones the filters open in a slide-in sheet with a "Show N results" button. Values travel as ?bf_{target}_{filter}=…, so filtered pages are shareable and work without JavaScript.',
	'fields'      => array_merge(
		array(
			'target'       => Fields::field( 'text', __( 'Products CSS id', 'brik-builder' ), 'content', array( 'placeholder' => 'shop', 'description' => __( 'The CSS id of the products module to filter. Leave empty to filter WooCommerce\'s own shop pages.', 'brik-builder' ) ) ),
			'filters'      => Fields::field(
				'repeater',
				__( 'Filters', 'brik-builder' ),
				'content',
				array(
					'title_field' => 'label',
					'item_label'  => __( 'filter', 'brik-builder' ),
					'fields'      => array(
						'type'        => Fields::field( 'select', __( 'Type', 'brik-builder' ), 'content', array( 'default' => 'category', 'options' => Fields::opts( brik_woo_filter_types() ) ) ),
						'label'       => Fields::field( 'text', __( 'Label', 'brik-builder' ) ),
						'attribute'   => Fields::field( 'select', __( 'Attribute', 'brik-builder' ), 'content', array( 'options' => Fields::opts( array( '' => __( 'Choose…', 'brik-builder' ) ) + brik_woo_attribute_options() ), 'show_if' => array( 'type' => 'attribute' ) ) ),
						'ui'          => Fields::field( 'select', __( 'Control', 'brik-builder' ), 'content', array( 'default' => 'auto', 'options' => Fields::opts( brik_woo_filter_uis() ), 'show_if' => array( 'type' => array( 'category', 'attribute' ) ) ) ),
						'show_counts' => Fields::field( 'toggle', __( 'Show counts', 'brik-builder' ), 'content', array( 'default' => true, 'show_if' => array( 'type' => array( 'category', 'attribute' ) ) ) ),
						'collapsed'   => Fields::field( 'toggle', __( 'Start collapsed', 'brik-builder' ) ),
					),
					'default'     => $brik_pf_defaults,
				)
			),
			'layout'       => Fields::field( 'select', __( 'Layout', 'brik-builder' ), 'content', array( 'default' => 'vertical', 'options' => Fields::opts( array( 'vertical' => __( 'Sidebar', 'brik-builder' ), 'horizontal' => __( 'Bar of dropdowns', 'brik-builder' ) ) ) ) ),
			'apply'        => Fields::field( 'select', __( 'Apply filters', 'brik-builder' ), 'content', array( 'default' => 'instant', 'options' => Fields::opts( array( 'instant' => __( 'Instantly', 'brik-builder' ), 'button' => __( 'With a button', 'brik-builder' ) ) ) ) ),
			'chips'        => Fields::field( 'toggle', __( 'Active filter chips', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'title'        => Fields::field( 'text', __( 'Title', 'brik-builder' ), 'content', array( 'default' => __( 'Filters', 'brik-builder' ) ) ),
			'mobile_sheet' => Fields::field( 'toggle', __( 'Slide-in sheet on phones', 'brik-builder' ), 'content', array( 'default' => true ) ),
		),
		Fields::typography( 'label', __( 'Group titles', 'brik-builder' ), Fields::WRAP . ' .brik-pf-summary' )
	),
	'render'      => static function ( array $a, Brik\Context $ctx ) use ( $brik_pf_option ) {
		$renderer = $ctx->renderer;
		$canvas   = $ctx->canvas;
		$key      = brik_listing_key( $a['target'], '' );
		$native   = '' === $key;
		$defs     = brik_woo_filter_defs( $a['filters'] );
		if ( ! $defs ) {
			return $ctx->placeholder( __( 'Add some filters.', 'brik-builder' ) );
		}

		$params          = $canvas ? array() : brik_woo_request_params();
		list( , $state ) = brik_woo_filter_parse( $defs, $params, $native ? '-' : $key );
		$vertical        = 'horizontal' !== $a['layout'];
		$instant         = 'button' !== $a['apply'];
		$sheet           = brik_form_bool( $a['mobile_sheet'] );
		$uid             = $ctx->uid( 'pf' );
		$name_of         = static function ( $def_key, $part = '' ) use ( $key, $native ) {
			return brik_woo_filter_param( $key, $def_key, $native, $part );
		};
		$chips           = array();
		$groups          = '';

		foreach ( $defs as $def ) {
			$k     = $def['key'];
			$value = isset( $state[ $k ] ) ? $state[ $k ] : null;
			$body  = '';
			$name  = $name_of( $k );
			if ( $native && '' === $name && 'category' !== $def['type'] ) {
				continue; // WooCommerce's query has no parameter for this filter.
			}

			switch ( $def['type'] ) {
				case 'price':
					list( $lo, $hi ) = brik_woo_price_bounds();
					if ( null === $lo || $hi <= $lo ) {
						continue 2;
					}
					$cur_lo = is_array( $value ) && null !== $value['min'] ? max( $lo, min( $hi, $value['min'] ) ) : $lo;
					$cur_hi = is_array( $value ) && null !== $value['max'] ? max( $lo, min( $hi, $value['max'] ) ) : $hi;
					$symbol = html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' );
					$pos    = get_option( 'woocommerce_currency_pos', 'left' );
					$pre    = in_array( $pos, array( 'left', 'left_space' ), true ) ? $symbol . ( 'left_space' === $pos ? ' ' : '' ) : '';
					$suf    = in_array( $pos, array( 'right', 'right_space' ), true ) ? ( 'right_space' === $pos ? ' ' : '' ) . $symbol : '';
					$show   = static function ( $n ) use ( $pre, $suf ) {
						return $pre . number_format_i18n( $n ) . $suf;
					};
					$body = '<div class="brik-lf-range grid gap-3 pt-2" data-pf-range data-prefix="' . esc_attr( $pre ) . '" data-suffix="' . esc_attr( $suf ) . '">'
						. '<div class="brik-lf-range-track relative h-5" style="--lo:' . esc_attr( ( $cur_lo - $lo ) / ( $hi - $lo ) * 100 ) . '%;--hi:' . esc_attr( ( $cur_hi - $lo ) / ( $hi - $lo ) * 100 ) . '%">'
						. '<input' . brik_attrs( array( 'type' => 'range', 'class' => 'brik-lf-thumb', 'name' => $name_of( 'price', 'min' ), 'min' => $lo, 'max' => $hi, 'step' => 1, 'value' => floor( $cur_lo ), 'aria-label' => __( 'Minimum price', 'brik-builder' ), 'data-pf-min' => true ) ) . '>'
						. '<input' . brik_attrs( array( 'type' => 'range', 'class' => 'brik-lf-thumb', 'name' => $name_of( 'price', 'max' ), 'min' => $lo, 'max' => $hi, 'step' => 1, 'value' => ceil( $cur_hi ), 'aria-label' => __( 'Maximum price', 'brik-builder' ), 'data-pf-max' => true ) ) . '>'
						. '</div>'
						. '<div class="flex items-center justify-between gap-2 text-sm tabular-nums">'
						. '<output class="rounded-md border bg-background px-2.5 py-1 shadow-xs dark:bg-input/30" data-pf-out="min">' . esc_html( $show( $cur_lo ) ) . '</output>'
						. '<span class="h-px w-4 bg-border"></span>'
						. '<output class="rounded-md border bg-background px-2.5 py-1 shadow-xs dark:bg-input/30" data-pf-out="max">' . esc_html( $show( $cur_hi ) ) . '</output>'
						. '</div></div>';
					if ( is_array( $value ) ) {
						$chips[] = array( $show( $cur_lo ) . ' – ' . $show( $cur_hi ), array( $name_of( 'price', 'min' ), $name_of( 'price', 'max' ) ), null );
					}
					break;

				case 'category':
				case 'attribute':
					$tax   = $def['taxonomy'];
					$terms = get_terms(
						array(
							'taxonomy'   => $tax,
							'hide_empty' => true,
							'number'     => 200,
						)
					);
					$terms = is_wp_error( $terms ) ? array() : $terms;
					if ( 'product_cat' === $tax ) {
						$default = (int) get_option( 'default_product_cat' );
						$terms   = array_values(
							array_filter(
								$terms,
								static function ( $t ) use ( $default ) {
									return (int) $t->term_id !== $default;
								}
							)
						);
					}
					if ( ! $terms ) {
						if ( $canvas ) {
							$body = '<p class="text-xs text-muted-foreground">' . esc_html__( 'No terms yet.', 'brik-builder' ) . '</p>';
							break;
						}
						continue 2;
					}
					$selected = is_array( $value ) ? $value : array();
					foreach ( $terms as $t ) {
						if ( in_array( $t->slug, $selected, true ) ) {
							$chips[] = array( $t->name, array( $name ), $t->slug );
						}
					}

					// Categories without a parameter in WooCommerce's query are links to their archives.
					if ( $native && 'category' === $def['type'] ) {
						$current = is_tax( 'product_cat' ) ? get_queried_object_id() : 0;
						$links   = '';
						foreach ( $terms as $t ) {
							if ( $t->parent ) {
								continue;
							}
							$links .= '<li><a class="flex items-center justify-between rounded-md px-2 py-1.5 text-sm transition-colors hover:bg-accent' . ( $current === $t->term_id ? ' bg-accent font-medium' : '' ) . '" href="' . esc_url( get_term_link( $t ) ) . '">' . esc_html( $t->name ) . ( $def['show_counts'] ? '<span class="text-xs text-muted-foreground tabular-nums">' . (int) $t->count . '</span>' : '' ) . '</a></li>';
						}
						$body = '<ul class="-mx-2 grid gap-0.5">' . $links . '</ul>';
						break;
					}

					if ( 'select' === $def['ui'] ) {
						$opts = '<option value="">' . esc_html__( 'All', 'brik-builder' ) . '</option>';
						foreach ( $terms as $t ) {
							$opts .= '<option value="' . esc_attr( $t->slug ) . '"' . selected( in_array( $t->slug, $selected, true ), true, false ) . ' data-chip="' . esc_attr( $t->name ) . '">' . esc_html( ( $t->parent && 'product_cat' === $tax ? '— ' : '' ) . $t->name . ( $def['show_counts'] ? ' (' . $t->count . ')' : '' ) ) . '</option>';
						}
						$body = '<div class="relative"><select name="' . esc_attr( $name ) . '" class="' . esc_attr( brik_select_class() ) . '" aria-label="' . esc_attr( $def['label'] ) . '">' . $opts . '</select>'
							. brik_icon( 'chevron-down', 'pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-foreground opacity-60' ) . '</div>';
						break;
					}

					$ui    = $def['ui'];
					$field = $name . '[]';
					if ( 'product_cat' === $tax && 'checkboxes' === $ui ) {
						// Category tree: children under their parents.
						$by_parent = array();
						foreach ( $terms as $t ) {
							$by_parent[ (int) $t->parent ][] = $t;
						}
						$ids  = wp_list_pluck( $terms, 'term_id' );
						$tree = static function ( $parent, $depth ) use ( &$tree, $by_parent, $field, $selected, $def, $brik_pf_option, $ids ) {
							$out = '';
							foreach ( isset( $by_parent[ $parent ] ) ? $by_parent[ $parent ] : array() as $t ) {
								$out .= '<li>' . $brik_pf_option( 'checkboxes', 'checkbox', $field, $t->slug, esc_html( $t->name ), in_array( $t->slug, $selected, true ), $def['show_counts'] ? $t->count : null )
									. ( isset( $by_parent[ $t->term_id ] ) ? '<ul class="mt-2.5 ml-6 grid gap-2.5">' . $tree( (int) $t->term_id, $depth + 1 ) . '</ul>' : '' ) . '</li>';
							}
							return $out;
						};
						// Terms whose parent is hidden (empty) show at the top level.
						foreach ( $terms as $t ) {
							if ( $t->parent && ! in_array( (int) $t->parent, $ids, true ) ) {
								$by_parent[0][] = $t;
							}
						}
						$body = '<ul class="brik-pf-tree grid gap-2.5">' . $tree( 0, 0 ) . '</ul>';
						break;
					}

					$items = '';
					foreach ( $terms as $t ) {
						$color  = 'swatches' === $ui ? brik_woo_swatch_color( $t ) : '';
						$items .= $brik_pf_option( $ui, 'checkbox', $field, $t->slug, 'checkboxes' === $ui ? esc_html( $t->name ) : $t->name, in_array( $t->slug, $selected, true ), $def['show_counts'] && 'checkboxes' === $ui ? $t->count : null, $color );
					}
					$wrap = 'checkboxes' === $ui ? 'grid gap-2.5' : ( 'swatches' === $ui ? 'flex flex-wrap gap-2.5' : 'flex flex-wrap gap-2' );
					$body = '<div class="' . esc_attr( $wrap ) . '">' . $items . '</div>';
					break;

				case 'rating':
					$items = '';
					foreach ( array( 4, 3, 2, 1 ) as $n ) {
						/* translators: %d: star rating */
						$label  = sprintf( __( '%d stars & up', 'brik-builder' ), $n );
						$items .= $brik_pf_option( 'checkboxes', 'radio', $name, (string) $n, '<span class="inline-flex items-center gap-2">' . brik_stars( $n, 5, 'size-3.5' ) . '<span class="text-muted-foreground">' . esc_html__( '& up', 'brik-builder' ) . '</span></span>', (string) $n === $value, null, '', $label );
						if ( (string) $n === $value ) {
							$chips[] = array( $label, array( $name ), null );
						}
					}
					$body = '<div class="grid gap-2.5" data-pf-radio>' . $items . '</div>';
					break;

				case 'stock':
				case 'sale':
					$label = '' !== $def['label'] ? $def['label'] : brik_woo_filter_types()[ $def['type'] ];
					if ( '1' === $value ) {
						$chips[] = array( $label, array( $name ), null );
					}
					$groups .= '<div class="brik-pf-group brik-pf-toggle" data-pf-key="' . esc_attr( $k ) . '">'
						. '<label class="flex cursor-pointer items-center justify-between gap-3 py-1 text-sm font-medium">'
						. '<span>' . esc_html( $label ) . '</span>'
						. '<input' . brik_attrs( array( 'type' => 'checkbox', 'name' => $name, 'value' => '1', 'checked' => '1' === $value, 'class' => 'brik-pf-switch peer sr-only', 'data-chip' => $label ) ) . '>'
						. '<span class="brik-pf-switch-ui relative inline-flex h-5 w-9 shrink-0 items-center rounded-full border border-transparent bg-input shadow-xs transition-colors peer-checked:bg-primary peer-focus-visible:ring-[3px] peer-focus-visible:ring-ring/50 dark:bg-input/80" aria-hidden="true"><span class="block size-4 rounded-full bg-background shadow-sm transition-transform"></span></span>'
						. '</label></div>';
					continue 2;

				case 'search':
					if ( is_string( $value ) ) {
						$chips[] = array( '“' . $value . '”', array( $name ), null );
					}
					$body = '<div class="relative">' . brik_icon( 'search', 'pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground' )
						. '<input' . brik_attrs( array( 'type' => 'search', 'name' => $name, 'value' => is_string( $value ) ? $value : '', 'placeholder' => __( 'Search products…', 'brik-builder' ), 'class' => brik_input_class( 'pl-9' ), 'maxlength' => 100, 'data-pf-search' => true, 'aria-label' => $def['label'] ) ) . '></div>';
					break;

				case 'sort':
					$opts = '';
					foreach ( brik_woo_sort_options() as $v => $o ) {
						$opts .= '<option value="' . esc_attr( 'menu_order' === $v ? '' : $v ) . '"' . selected( $value, $v, false ) . '>' . esc_html( $o[0] ) . '</option>';
					}
					$body = '<div class="relative"><select name="' . esc_attr( $name ) . '" class="' . esc_attr( brik_select_class() ) . '" aria-label="' . esc_attr( $def['label'] ) . '">' . $opts . '</select>'
						. brik_icon( 'chevron-down', 'pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-foreground opacity-60' ) . '</div>';
					break;
			}

			$active  = null !== $value && 'sort' !== $def['type'];
			$open    = ! $def['collapsed'] || $active;
			$groups .= '<details class="brik-pf-group" data-pf-key="' . esc_attr( $k ) . '"' . ( $vertical && $open ? ' open' : '' ) . '>'
				. '<summary class="brik-pf-summary">'
				. '<span>' . esc_html( $def['label'] ) . '</span>'
				. '<span class="brik-pf-dot" aria-hidden="true"' . ( $active ? '' : ' hidden' ) . '></span>'
				. brik_icon( 'chevron-down', 'brik-pf-chevron ml-auto size-4 shrink-0 text-muted-foreground transition-transform duration-200' )
				. '</summary>'
				. '<div class="brik-pf-content">' . $body . '</div>'
				. '</details>';
		}

		// Other query parameters ride along in a GET form.
		$hidden  = '';
		$action  = '';
		$current = $canvas ? '' : brik_listing_current_url();
		$own     = static function ( $param ) use ( $key, $native ) {
			if ( $native ) {
				return in_array( $param, array( 'min_price', 'max_price', 'rating_filter', 'orderby', 'paged', 'product-page' ), true ) || 0 === strpos( $param, 'filter_' ) || 0 === strpos( $param, 'query_type_' );
			}
			return 0 === strpos( $param, 'bf_' . $key . '_' );
		};
		$keep    = array();
		if ( ! $canvas ) {
			$action = strtok( $current, '?' );
			if ( $native ) {
				// Filtering starts again on the first page of the archive.
				$action = preg_replace( '#/page/\d+/?$#', '/', $action );
			}
			parse_str( (string) wp_parse_url( $current, PHP_URL_QUERY ), $vars );
			foreach ( $vars as $p => $v ) {
				if ( is_string( $p ) && is_string( $v ) && ! $own( $p ) ) {
					$keep[ $p ] = $v;
					$hidden    .= '<input type="hidden" name="' . esc_attr( $p ) . '" value="' . esc_attr( $v ) . '">';
				}
			}
		}
		$clear_url = $canvas ? '#' : $action . ( $keep ? '?' . http_build_query( $keep ) : '' );

		// Active filter chips: links that drop one value, so they work without JavaScript too.
		$chips_html = '';
		if ( brik_form_bool( $a['chips'] ) ) {
			$items = '';
			foreach ( $chips as $chip ) {
				list( $label, $names, $slug ) = $chip;
				$url = $current;
				foreach ( $names as $n ) {
					if ( null === $slug ) {
						$url = remove_query_arg( $n, $url );
					} else {
						$rest = array_diff( brik_listing_list( isset( $params[ $n ] ) ? $params[ $n ] : '' ), array( $slug ) );
						$url  = $rest ? add_query_arg( $n, implode( ',', $rest ), remove_query_arg( $n, $url ) ) : remove_query_arg( $n, $url );
					}
				}
				$url    = remove_query_arg( $native ? 'paged' : brik_listing_param( $key, 'page' ), $url );
				$items .= '<a class="brik-pf-chip inline-flex h-7 items-center gap-1 rounded-full border bg-secondary pr-1.5 pl-3 text-xs font-medium text-secondary-foreground transition-colors hover:bg-secondary/70" href="' . esc_url( $canvas ? '#' : $url ) . '" data-pf-chip="' . esc_attr( implode( ' ', $names ) ) . '"' . ( null !== $slug ? ' data-pf-value="' . esc_attr( $slug ) . '"' : '' ) . '>'
					. '<span>' . esc_html( $label ) . '</span>' . brik_icon( 'x', 'size-3.5 opacity-60' ) . '</a>';
			}
			$chips_html = '<div class="brik-pf-chips flex flex-wrap items-center gap-2"' . ( $items ? '' : ' hidden' ) . ' data-pf-chips>'
				. '<span class="brik-pf-chip-list contents">' . $items . '</span>'
				. '<a class="text-xs font-medium text-muted-foreground underline-offset-4 hover:text-foreground hover:underline" href="' . esc_url( $clear_url ) . '" data-pf-clear>' . esc_html__( 'Clear all', 'brik-builder' ) . '</a>'
				. '</div>';
		}

		// Result total for the sheet's button.
		$total = null;
		if ( ! $native ) {
			$target = brik_woo_target_attrs( $renderer, $renderer->root, $key );
			if ( $target && ! $canvas ) {
				list( $clauses ) = brik_woo_filter_parse( brik_woo_filters_for( $renderer, $renderer->root, $key ), $params, $key );
				$main            = 'current' === $target['source'] && brik_woo_is_product_archive() ? brik_woo_main_context() : null;
				list( $query )   = brik_woo_products_query(
					$target,
					array(
						'page'    => isset( $params[ brik_listing_param( $key, 'page' ) ] ) && 'none' !== $target['pagination'] ? max( 1, absint( $params[ brik_listing_param( $key, 'page' ) ] ) ) : 1,
						'clauses' => $clauses,
						'main'    => $main,
						'product' => in_array( $target['source'], array( 'related', 'upsells', 'cross_sells' ), true ) ? brik_woo_context_product( $ctx ) : null,
					)
				);
				$total = max( 0, (int) $query->found_posts - max( 0, (int) $target['offset'] ) );
			}
		} elseif ( ! $canvas && brik_woo_is_product_archive() ) {
			global $wp_query;
			$total = (int) $wp_query->found_posts;
		}
		/* translators: %s: number of products */
		$show_text = null !== $total ? sprintf( _n( 'Show %s result', 'Show %s results', $total, 'brik-builder' ), '<span data-pf-total>' . number_format_i18n( $total ) . '</span>' ) : esc_html__( 'Show results', 'brik-builder' );

		$count  = count( $chips );
		$title  = '' !== trim( (string) $a['title'] ) ? $a['title'] : __( 'Filters', 'brik-builder' );
		$submit = '<button type="submit" class="' . esc_attr( brik_button_class( 'default', 'default', 'brik-pf-submit w-full' ) ) . '"' . ( $instant ? ' data-pf-js-hide' : '' ) . '>' . esc_html__( 'Apply filters', 'brik-builder' ) . '</button>';

		$trigger = '';
		if ( $sheet ) {
			$trigger = '<button type="button" class="' . esc_attr( brik_button_class( 'outline', 'default', 'brik-pf-open md:hidden' ) ) . '" data-pf-open aria-haspopup="dialog" aria-controls="' . esc_attr( $uid ) . '">'
				. brik_icon( 'sliders-horizontal' ) . '<span>' . brik_inline( $title ) . '</span>'
				. '<span class="brik-pf-badge grid h-5 min-w-5 place-items-center rounded-full bg-primary px-1 text-[11px] font-semibold text-primary-foreground tabular-nums" data-pf-count' . ( $count ? '' : ' hidden' ) . '>' . (int) $count . '</span>'
				. '</button>';
		}

		$panel = '<div class="brik-pf-panel">'
			. '<div class="brik-pf-head">'
			. '<p class="brik-pf-title text-base font-semibold" id="' . esc_attr( $uid . '-title' ) . '">' . brik_inline( $title ) . '</p>'
			. ( $sheet ? '<button type="button" class="brik-pf-close ml-auto inline-flex size-8 items-center justify-center rounded-md opacity-70 transition-opacity hover:opacity-100 focus-visible:ring-[3px] focus-visible:ring-ring/50 outline-none" data-pf-close aria-label="' . esc_attr__( 'Close filters', 'brik-builder' ) . '">' . brik_icon( 'x' ) . '</button>' : '' )
			. '</div>'
			. '<div class="brik-pf-body">' . $groups . '<div class="brik-pf-actions pt-4">' . $submit . '</div></div>'
			. ( $sheet ? '<div class="brik-pf-foot"><a class="' . esc_attr( brik_button_class( 'outline', 'lg' ) ) . '" href="' . esc_url( $clear_url ) . '" data-pf-clear>' . esc_html__( 'Clear all', 'brik-builder' ) . '</a><button type="button" class="' . esc_attr( brik_button_class( 'default', 'lg', 'flex-1' ) ) . '" data-pf-done><span>' . $show_text . '</span></button></div>' : '' )
			. '</div>';

		$form_attrs = array(
			'class'                     => brik_cls( 'brik-pf', $vertical ? 'brik-pf--vertical' : 'brik-pf--horizontal', array( 'brik-pf--sheet' => $sheet ) ),
			'method'                    => 'get',
			'action'                    => $canvas ? null : $action,
			'aria-label'                => __( 'Filter products', 'brik-builder' ),
			'data-brik-product-filter'  => $native ? '' : $key,
			'data-apply'                => $instant ? 'instant' : 'button',
			'data-native'               => $native ? '1' : null,
		);

		return '<form' . brik_attrs( $form_attrs ) . '>'
			. ( $trigger ? '<div class="brik-pf-bar flex items-center gap-2 md:hidden">' . $trigger . '</div>' : '' )
			. $chips_html
			. ( $sheet
				? '<dialog class="brik-pf-sheet" id="' . esc_attr( $uid ) . '" aria-labelledby="' . esc_attr( $uid . '-title' ) . '">' . $panel . '</dialog>'
				: '<div class="brik-pf-sheet brik-pf-static">' . $panel . '</div>' )
			. $hidden
			. '</form>';
	},
);
