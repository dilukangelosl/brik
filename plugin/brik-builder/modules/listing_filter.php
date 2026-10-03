<?php
/**
 * Listing filter: search, taxonomy, field, sort and reset controls for a listing on the
 * same page. Works as a plain GET form; the front-end script turns it into AJAX filtering.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * One option row of a choice control: checkbox, radio or pill.
 */
$brik_lf_choice = static function ( $ui, $name, $value, $label, $checked, $count, $id ) {
	$count_html = null !== $count ? '<span class="brik-lf-count ml-1 text-xs tabular-nums opacity-60">' . (int) $count . '</span>' : '';
	if ( 'pills' === $ui ) {
		return '<label class="brik-lf-pill relative inline-flex cursor-pointer">'
			. '<input class="peer sr-only" type="radio" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . checked( $checked, true, false ) . '>'
			. '<span class="inline-flex h-8 items-center rounded-full border border-border bg-background px-3.5 text-sm font-medium whitespace-nowrap transition-colors hover:bg-accent hover:text-accent-foreground peer-checked:border-primary peer-checked:bg-primary peer-checked:text-primary-foreground peer-focus-visible:ring-[3px] peer-focus-visible:ring-ring/50">' . esc_html( $label ) . $count_html . '</span>'
			. '</label>';
	}
	$attrs = array(
		'id'      => $id,
		'name'    => 'checkboxes' === $ui ? $name . '[]' : $name,
		'value'   => $value,
		'checked' => $checked,
	);
	return brik_choice( 'checkboxes' === $ui ? 'checkbox' : 'radio', $attrs, esc_html( $label ) . $count_html );
};

return array(
	'type'        => 'listing_filter',
	'title'       => __( 'Listing filter', 'brik-builder' ),
	'category'    => 'post',
	'icon'        => 'list-filter',
	'description' => 'Filter bar for a listing module on the same page. target: the listing\'s css_id (set css_id on the listing first, e.g. "projects"). filters: repeater [{type: search|taxonomy|field|sort|reset, label, taxonomy (type taxonomy), field (field / meta key; for sort adds "low to high / high to low" options by that number field), ui: select|checkboxes|radio|pills|range|date_range (taxonomy: select|checkboxes|radio|pills; field: all; range = dual slider with min/max computed from the values, optional min, max, step, prefix, suffix), placeholder, show_counts (bool)}]. layout: horizontal|vertical. apply: instant|button (+ button_text). show_reset (bool). '
		. 'Filtering runs over POST brik/v1/listing (results fade in) and writes the state to the URL as ?bf_{target}_{filter}=value (comma separated for multiple; ranges use _min/_max; page uses bf_{target}_page), so links are shareable and the form also works without JavaScript.',
	'fields'      => array_merge(
		array(
			'target'      => Fields::field( 'text', __( 'Listing CSS id', 'brik-builder' ), 'content', array( 'placeholder' => 'projects', 'description' => __( 'The CSS id of the listing to filter (Advanced → CSS id on the listing).', 'brik-builder' ) ) ),
			'filters'     => Fields::field(
				'repeater',
				__( 'Filters', 'brik-builder' ),
				'content',
				array(
					'title_field' => 'label',
					'item_label'  => __( 'filter', 'brik-builder' ),
					'fields'      => array(
						'type'        => Fields::field( 'select', __( 'Type', 'brik-builder' ), 'content', array( 'default' => 'search', 'options' => Fields::opts( brik_listing_filter_types() ) ) ),
						'label'       => Fields::field( 'text', __( 'Label', 'brik-builder' ) ),
						'taxonomy'    => Fields::field( 'taxonomy', __( 'Taxonomy', 'brik-builder' ), 'content', array( 'show_if' => array( 'type' => 'taxonomy' ) ) ),
						'field'       => Fields::field( 'text', __( 'Field name', 'brik-builder' ), 'content', array( 'placeholder' => 'year', 'show_if' => array( 'type' => array( 'field', 'sort' ) ) ) ),
						'ui'          => Fields::field( 'select', __( 'Control', 'brik-builder' ), 'content', array( 'default' => 'select', 'options' => Fields::opts( brik_listing_filter_uis() ), 'show_if' => array( 'type' => array( 'taxonomy', 'field' ) ) ) ),
						'placeholder' => Fields::field( 'text', __( 'Placeholder', 'brik-builder' ), 'content', array( 'show_if' => array( 'type' => array( 'search', 'taxonomy', 'field', 'sort' ) ) ) ),
						'min'         => Fields::field( 'number', __( 'Minimum', 'brik-builder' ), 'content', array( 'description' => __( 'Optional. Taken from the values when empty.', 'brik-builder' ), 'show_if' => array( 'ui' => 'range' ) ) ),
						'max'         => Fields::field( 'number', __( 'Maximum', 'brik-builder' ), 'content', array( 'show_if' => array( 'ui' => 'range' ) ) ),
						'step'        => Fields::field( 'number', __( 'Step', 'brik-builder' ), 'content', array( 'show_if' => array( 'ui' => 'range' ) ) ),
						'prefix'      => Fields::field( 'text', __( 'Prefix', 'brik-builder' ), 'content', array( 'placeholder' => '$', 'show_if' => array( 'ui' => 'range' ) ) ),
						'suffix'      => Fields::field( 'text', __( 'Suffix', 'brik-builder' ), 'content', array( 'show_if' => array( 'ui' => 'range' ) ) ),
						'show_counts' => Fields::field( 'toggle', __( 'Show counts', 'brik-builder' ), 'content', array( 'show_if' => array( 'type' => array( 'taxonomy', 'field' ) ) ) ),
					),
					'default'     => array(
						array(
							'type'        => 'search',
							'label'       => __( 'Search', 'brik-builder' ),
							'placeholder' => __( 'Search…', 'brik-builder' ),
						),
						array(
							'type'  => 'sort',
							'label' => __( 'Sort by', 'brik-builder' ),
						),
					),
				)
			),
			'layout'      => Fields::field( 'select', __( 'Layout', 'brik-builder' ), 'content', array( 'default' => 'horizontal', 'options' => Fields::opts( array( 'horizontal' => __( 'Horizontal bar', 'brik-builder' ), 'vertical' => __( 'Vertical (sidebar)', 'brik-builder' ) ) ) ) ),
			'apply'       => Fields::field( 'select', __( 'Apply filters', 'brik-builder' ), 'content', array( 'default' => 'instant', 'options' => Fields::opts( array( 'instant' => __( 'Instantly', 'brik-builder' ), 'button' => __( 'With a button', 'brik-builder' ) ) ) ) ),
			'button_text' => Fields::field( 'text', __( 'Button text', 'brik-builder' ), 'content', array( 'default' => __( 'Apply filters', 'brik-builder' ) ) ),
			'show_reset'  => Fields::field( 'toggle', __( 'Reset link', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'reset_text'  => Fields::field( 'text', __( 'Reset text', 'brik-builder' ), 'content', array( 'default' => __( 'Clear all', 'brik-builder' ), 'show_if' => array( 'show_reset' => true ) ) ),
		),
		Fields::typography( 'label', __( 'Labels', 'brik-builder' ), Fields::WRAP . ' .brik-lf-label' ),
		Fields::box( 'input', __( 'Inputs', 'brik-builder' ), Fields::WRAP . ' .brik-input' )
	),
	'render'      => static function ( array $a, Brik\Context $ctx ) use ( $brik_lf_choice ) {
		$renderer = $ctx->renderer;
		$key      = brik_listing_key( $a['target'], '' );
		$defs     = brik_listing_filter_defs( $a['filters'] );
		if ( '' === $key ) {
			return $ctx->placeholder( __( 'Set the CSS id of the listing to filter.', 'brik-builder' ) );
		}
		if ( ! $defs ) {
			return $ctx->placeholder( __( 'Add some filters.', 'brik-builder' ) );
		}

		$listing   = brik_listing_target_attrs( $renderer, $renderer->root, $key );
		$post_type = $listing ? brik_listing_post_type( $listing['post_type'] ) : 'post';
		if ( $listing && brik_form_bool( $listing['use_main_query'] ) && ! $ctx->canvas && ( is_archive() || is_home() || is_search() ) ) {
			$main      = brik_listing_main_context();
			$post_type = ! empty( $main['post_type'] ) ? $main['post_type'] : 'post';
		}
		$params        = $ctx->canvas ? array() : brik_listing_request_params();
		list( , $state ) = brik_listing_parse( $defs, $params, $key, $post_type );

		$vertical = 'vertical' === $a['layout'];
		$instant  = 'button' !== $a['apply'];
		$controls = '';
		$has_reset = brik_form_bool( $a['show_reset'] );

		foreach ( $defs as $def ) {
			$name  = brik_listing_param( $key, $def['key'] );
			$id    = $ctx->uid( 'lf-' . $def['key'] );
			$label = '' !== $def['label'] ? $def['label'] : '';
			$value = isset( $state[ $def['key'] ] ) ? $state[ $def['key'] ] : null;
			$body  = '';
			$group = false;

			switch ( $def['type'] ) {
				case 'search':
					$body = '<div class="relative">'
						. brik_icon( 'search', 'pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground' )
						. '<input' . brik_attrs(
							array(
								'type'         => 'search',
								'id'           => $id,
								'name'         => $name,
								'value'        => is_string( $value ) ? $value : '',
								'placeholder'  => '' !== $def['placeholder'] ? $def['placeholder'] : __( 'Search…', 'brik-builder' ),
								'class'        => brik_input_class( 'pl-9' ),
								'autocomplete' => 'off',
								'maxlength'    => 100,
								'data-lf-search' => true,
							)
						) . '></div>';
					break;

				case 'sort':
					$opts = '<option value="">' . esc_html( '' !== $def['placeholder'] ? $def['placeholder'] : __( 'Default order', 'brik-builder' ) ) . '</option>';
					foreach ( brik_listing_sort_options( $def['field'] ) as $v => $o ) {
						$opts .= '<option value="' . esc_attr( $v ) . '"' . selected( $value, $v, false ) . '>' . esc_html( $o[0] ) . '</option>';
					}
					$body = '<div class="relative"><select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" class="' . esc_attr( brik_select_class() ) . '">' . $opts . '</select>'
						. brik_icon( 'chevron-down', 'pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-foreground opacity-60' ) . '</div>';
					break;

				case 'reset':
					$has_reset = true;
					continue 2;

				case 'taxonomy':
				case 'field':
					if ( 'field' === $def['type'] && in_array( $def['ui'], array( 'range', 'date_range' ), true ) ) {
						$v = is_array( $value ) ? $value : array( 'min' => null, 'max' => null );
						if ( 'date_range' === $def['ui'] ) {
							$group = true;
							$body  = '<div class="grid grid-cols-2 gap-2">'
								. '<input' . brik_attrs( array( 'type' => 'date', 'name' => $name . '_min', 'value' => (string) $v['min'], 'class' => brik_input_class(), 'aria-label' => __( 'From', 'brik-builder' ) ) ) . '>'
								. '<input' . brik_attrs( array( 'type' => 'date', 'name' => $name . '_max', 'value' => (string) $v['max'], 'class' => brik_input_class(), 'aria-label' => __( 'To', 'brik-builder' ) ) ) . '>'
								. '</div>';
							break;
						}
						list( $lo, $hi ) = brik_listing_meta_range( $post_type, $def['field'] );
						$lo = null !== $def['min'] ? $def['min'] : $lo;
						$hi = null !== $def['max'] ? $def['max'] : $hi;
						if ( null === $lo || null === $hi || $hi <= $lo ) {
							$body = $ctx->canvas ? '<p class="text-xs text-muted-foreground">' . esc_html__( 'No numeric values yet.', 'brik-builder' ) . '</p>' : '';
							if ( '' === $body ) {
								continue 2;
							}
							break;
						}
						$step = null !== $def['step'] ? $def['step'] : ( floor( $lo ) == $lo && floor( $hi ) == $hi ? 1 : 0.01 ); // phpcs:ignore Universal.Operators.StrictComparisons
						$cur_lo = null !== $v['min'] ? max( $lo, min( $hi, $v['min'] ) ) : $lo;
						$cur_hi = null !== $v['max'] ? max( $lo, min( $hi, $v['max'] ) ) : $hi;
						$fmt    = static function ( $n ) {
							return (string) ( floor( $n ) == $n ? (int) $n : round( $n, 2 ) ); // phpcs:ignore Universal.Operators.StrictComparisons
						};
						// Years read better without a thousands separator.
						$show   = static function ( $n ) use ( $fmt, $lo, $hi ) {
							return $lo >= 1900 && $hi <= 2100 ? $fmt( $n ) : number_format_i18n( (float) $fmt( $n ), floor( $n ) == $n ? 0 : 2 ); // phpcs:ignore Universal.Operators.StrictComparisons
						};
						$group = true;
						$body  = '<div class="brik-lf-range grid gap-2 pt-1" data-lf-range data-plain="' . ( $lo >= 1900 && $hi <= 2100 ? '1' : '' ) . '" data-prefix="' . esc_attr( $def['prefix'] ) . '" data-suffix="' . esc_attr( $def['suffix'] ) . '">'
							. '<div class="brik-lf-range-track relative h-5" style="--lo:' . esc_attr( ( $cur_lo - $lo ) / ( $hi - $lo ) * 100 ) . '%;--hi:' . esc_attr( ( $cur_hi - $lo ) / ( $hi - $lo ) * 100 ) . '%">'
							. '<input' . brik_attrs( array( 'type' => 'range', 'class' => 'brik-lf-thumb', 'name' => $name . '_min', 'min' => $fmt( $lo ), 'max' => $fmt( $hi ), 'step' => $step, 'value' => $fmt( $cur_lo ), 'aria-label' => __( 'Minimum', 'brik-builder' ), 'data-lf-min' => true ) ) . '>'
							. '<input' . brik_attrs( array( 'type' => 'range', 'class' => 'brik-lf-thumb', 'name' => $name . '_max', 'min' => $fmt( $lo ), 'max' => $fmt( $hi ), 'step' => $step, 'value' => $fmt( $cur_hi ), 'aria-label' => __( 'Maximum', 'brik-builder' ), 'data-lf-max' => true ) ) . '>'
							. '</div>'
							. '<div class="flex items-center justify-between text-xs font-medium tabular-nums text-muted-foreground"><output data-lf-out="min">' . esc_html( $def['prefix'] . $show( $cur_lo ) . $def['suffix'] ) . '</output><output data-lf-out="max">' . esc_html( $def['prefix'] . $show( $cur_hi ) . $def['suffix'] ) . '</output></div>'
							. '</div>';
						break;
					}

					// Choices: taxonomy terms, field choices or the distinct stored values.
					$choices = array();
					if ( 'taxonomy' === $def['type'] ) {
						if ( ! taxonomy_exists( $def['taxonomy'] ) ) {
							$body = $ctx->canvas ? '<p class="text-xs text-muted-foreground">' . esc_html__( 'Unknown taxonomy.', 'brik-builder' ) . '</p>' : '';
							if ( '' === $body ) {
								continue 2;
							}
							break;
						}
						$terms = get_terms(
							array(
								'taxonomy'   => $def['taxonomy'],
								'hide_empty' => true,
								'number'     => 100,
							)
						);
						foreach ( is_wp_error( $terms ) ? array() : $terms as $term ) {
							$choices[ $term->slug ] = array( $term->name, (int) $term->count );
						}
						if ( '' === $label ) {
							$tax   = get_taxonomy( $def['taxonomy'] );
							$label = $tax ? $tax->labels->singular_name : '';
						}
					} else {
						$labels = brik_listing_field_choices( $def['field'], $post_type );
						$values = brik_listing_meta_values( $post_type, $def['field'] );
						if ( $labels ) {
							foreach ( $labels as $v => $l ) {
								if ( isset( $values[ $v ] ) ) {
									$choices[ $v ] = array( $l, $values[ $v ] );
								}
							}
						} else {
							foreach ( $values as $v => $c ) {
								$choices[ $v ] = array( $v, $c );
							}
						}
					}
					$selected = is_array( $value ) ? $value : array();
					$counts   = $def['show_counts'];

					if ( 'select' === $def['ui'] ) {
						$opts = '<option value="">' . esc_html( '' !== $def['placeholder'] ? $def['placeholder'] : __( 'All', 'brik-builder' ) ) . '</option>';
						foreach ( $choices as $v => $c ) {
							$opts .= '<option value="' . esc_attr( $v ) . '"' . selected( in_array( (string) $v, $selected, true ), true, false ) . '>' . esc_html( $c[0] . ( $counts ? ' (' . $c[1] . ')' : '' ) ) . '</option>';
						}
						$body = '<div class="relative"><select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" class="' . esc_attr( brik_select_class() ) . '">' . $opts . '</select>'
							. brik_icon( 'chevron-down', 'pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-foreground opacity-60' ) . '</div>';
						break;
					}

					$group = true;
					$items = '';
					if ( in_array( $def['ui'], array( 'radio', 'pills' ), true ) ) {
						$all    = '' !== $def['placeholder'] ? $def['placeholder'] : __( 'All', 'brik-builder' );
						$items .= $brik_lf_choice( $def['ui'], $name, '', $all, ! $selected, null, $id . '-all' );
					}
					$i = 0;
					foreach ( $choices as $v => $c ) {
						$items .= $brik_lf_choice( $def['ui'], $name, (string) $v, $c[0], in_array( (string) $v, $selected, true ), $counts ? $c[1] : null, $id . '-' . $i++ );
					}
					$wrap = 'pills' === $def['ui'] ? 'brik-lf-pills flex flex-wrap gap-2' : ( $vertical ? 'brik-lf-choices grid gap-2.5' : 'brik-lf-choices flex flex-wrap gap-x-4 gap-y-2' );
					$body = '<div class="' . esc_attr( $wrap ) . '">' . $items . '</div>';
					break;
			}

			// Groups use role="group" rather than <fieldset>: legends don't take part in grid layout.
			$label_html = '';
			if ( '' !== $label ) {
				$label_html = $group
					? '<div class="brik-lf-label text-sm font-medium leading-none" id="' . esc_attr( $id . '-label' ) . '">' . esc_html( $label ) . '</div>'
					: '<label class="brik-lf-label text-sm font-medium leading-none" for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
			}
			$fcls = brik_cls(
				'brik-lf-field grid min-w-0 content-start gap-2',
				'brik-lf-' . $def['type'],
				array(
					'sm:min-w-44 sm:flex-1'    => ! $vertical && in_array( $def['type'], array( 'search' ), true ),
					'sm:min-w-40'              => ! $vertical && ! in_array( $def['type'], array( 'search' ), true ) && ! $group,
					'sm:min-w-56'              => ! $vertical && in_array( $def['ui'], array( 'range', 'date_range' ), true ),
					'w-full'                   => ! $vertical && 'pills' === $def['ui'],
				)
			);
			$role      = $group ? ' role="group"' . ( '' !== $label ? ' aria-labelledby="' . esc_attr( $id . '-label' ) . '"' : '' ) : '';
			$controls .= '<div class="' . esc_attr( $fcls ) . '" data-lf-key="' . esc_attr( $def['key'] ) . '"' . $role . '>' . $label_html . $body . '</div>';
		}

		// A GET form drops the action's query string, so other parameters ride along as hidden inputs.
		$hidden = '';
		$action = '';
		$keep   = array();
		if ( ! $ctx->canvas ) {
			$current = brik_listing_current_url();
			$action  = strtok( $current, '?' );
			parse_str( (string) wp_parse_url( $current, PHP_URL_QUERY ), $vars );
			foreach ( $vars as $k => $v ) {
				if ( is_string( $k ) && is_string( $v ) && 0 !== strpos( $k, 'bf_' . $key . '_' ) ) {
					$keep[ $k ] = $v;
					$hidden    .= '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
				}
			}
		}

		$actions = '';
		$button  = '<button type="submit" class="' . esc_attr( brik_button_class( 'default', 'default', 'brik-lf-submit' ) ) . '"' . ( $instant ? ' data-lf-js-hide' : '' ) . '>' . brik_inline( '' !== trim( (string) $a['button_text'] ) ? $a['button_text'] : __( 'Apply filters', 'brik-builder' ) ) . '</button>';
		$reset   = '';
		if ( $has_reset ) {
			$reset_url = $ctx->canvas ? '#' : $action . ( $keep ? '?' . http_build_query( $keep ) : '' );
			$reset     = '<a class="' . esc_attr( brik_button_class( 'ghost', 'default', 'brik-lf-reset text-muted-foreground' ) ) . '" href="' . esc_url( $reset_url ) . '" data-lf-reset' . ( $state ? '' : ' data-idle' ) . '>' . brik_icon( 'x' ) . '<span>' . brik_inline( '' !== trim( (string) $a['reset_text'] ) ? $a['reset_text'] : __( 'Clear all', 'brik-builder' ) ) . '</span></a>';
		}
		$actions = '<div class="' . esc_attr( brik_cls( 'brik-lf-actions flex flex-wrap items-center gap-2', $vertical ? 'pt-1' : 'sm:ml-auto' ) ) . '">' . $button . $reset . '</div>';

		$form_cls = brik_cls(
			'brik-lf',
			$vertical ? 'grid gap-6' : 'flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-end'
		);

		return '<form' . brik_attrs(
			array(
				'class'                    => $form_cls,
				'method'                   => 'get',
				'action'                   => $ctx->canvas ? null : $action,
				'role'                     => 'search',
				'aria-label'               => __( 'Filter results', 'brik-builder' ),
				'data-brik-listing-filter' => $key,
				'data-apply'               => $instant ? 'instant' : 'button',
			)
		) . '>' . $controls . $actions . $hidden . '</form>';
	},
);
