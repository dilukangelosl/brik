<?php
/**
 * Field: shows one content field (Brik → Content) of the current post, a specific post,
 * the site options page or the current term, formatted by its type or a chosen display.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Field names defined under Brik → Content, offered as suggestions in the settings.
 */
$brik_field_names = static function () {
	$names = array();
	if ( did_action( 'init' ) && class_exists( 'Brik\\Content\\Registry' ) ) {
		foreach ( (array) Brik\Content\Registry::groups( true ) as $group ) {
			foreach ( isset( $group['fields'] ) ? (array) $group['fields'] : array() as $field ) {
				if ( ! empty( $field['name'] ) && ! in_array( $field['type'], array( 'message', 'tab' ), true ) ) {
					$names[ $field['name'] ] = isset( $field['label'] ) ? $field['label'] : $field['name'];
				}
			}
		}
	}
	return $names;
};

/**
 * Where the value is read from, in the form the content API expects, or null.
 */
$brik_field_target = static function ( array $a, Brik\Context $ctx ) {
	switch ( $a['source'] ) {
		case 'post_id':
			$id = absint( $a['post_id'] );
			return $id && get_post( $id ) ? $id : null;
		case 'option':
			return 'option';
		case 'term':
			$term = get_queried_object();
			if ( ! $term instanceof WP_Term && $ctx->canvas ) {
				$terms = get_terms( array( 'number' => 1, 'hide_empty' => true, 'taxonomy' => get_taxonomies( array( 'public' => true ) ) ) );
				$term  = $terms && ! is_wp_error( $terms ) ? $terms[0] : null;
			}
			return $term instanceof WP_Term ? 'term_' . $term->term_id : null;
		default:
			$post = brik_site_post( $ctx );
			return $post ? $post->ID : null;
	}
};

/**
 * Stored value without the content API: post meta, term meta or the options-page option.
 */
$brik_field_meta = static function ( $name, $target ) {
	if ( 'option' === $target ) {
		return get_option( 'brik_opt_' . $name, '' );
	}
	if ( is_string( $target ) && preg_match( '/^term_(\d+)$/', $target, $m ) ) {
		return get_term_meta( (int) $m[1], $name, true );
	}
	return get_post_meta( (int) $target, $name, true );
};

/**
 * A value as plain text, using choice labels and post titles where they apply.
 */
$brik_field_text = static function ( $value, $field ) {
	if ( $field && class_exists( 'Brik\\Content\\Fields' ) ) {
		return (string) Brik\Content\Fields::text( $field, $value );
	}
	if ( is_array( $value ) ) {
		$out = array();
		foreach ( $value as $v ) {
			if ( is_scalar( $v ) ) {
				$out[] = (string) $v;
			}
		}
		return implode( ', ', $out );
	}
	return is_scalar( $value ) ? (string) $value : '';
};

$brik_field_render = static function ( array $a, Brik\Context $ctx ) use ( $brik_field_target, $brik_field_meta, $brik_field_text ) {
	$name = trim( (string) $a['name'] );
	if ( ! preg_match( '/^[A-Za-z0-9_.\-]{1,100}$/', $name ) ) {
		return $ctx->placeholder( __( 'Enter a field name.', 'brik-builder' ) );
	}

	$target  = $brik_field_target( $a, $ctx );
	$api     = function_exists( 'brik_field_object' );
	$field   = null !== $target && $api ? brik_field_object( $name, $target ) : null;
	$raw     = null === $target ? '' : ( $field ? brik_raw_field( $name, $target ) : $brik_field_meta( $name, $target ) );
	$display = in_array( $a['show_as'], array( 'text', 'image', 'gallery', 'link', 'badges', 'list', 'table', 'map', 'embed', 'date' ), true ) ? $a['show_as'] : 'auto';
	$empty   = null === $raw || '' === $raw || false === $raw || ( is_array( $raw ) && ! array_filter( $raw, static function ( $v ) { return null !== $v && '' !== $v; } ) );
	if ( $field && class_exists( 'Brik\\Content\\Fields' ) && 'toggle' !== $field['type'] ) {
		$empty = Brik\Content\Fields::is_empty( $field, $raw );
	}

	$prefix = (string) $a['prefix'];
	$suffix = (string) $a['suffix'];
	$affix  = static function ( $html ) use ( $prefix, $suffix ) {
		return ( '' !== $prefix ? '<span class="brik-field-prefix">' . brik_inline( $prefix ) . '</span>' : '' ) . $html . ( '' !== $suffix ? '<span class="brik-field-suffix">' . brik_inline( $suffix ) . '</span>' : '' );
	};

	$size    = '' !== (string) $a['image_size'] ? sanitize_key( $a['image_size'] ) : 'large';
	$ratio   = brik_aspect_class( $a['image_ratio'] );
	$radius  = brik_radius_class( '' !== (string) $a['image_rounded'] ? $a['image_rounded'] : 'lg' );
	$columns = max( 1, min( 6, (int) ( '' !== (string) $a['gallery_columns'] ? $a['gallery_columns'] : 3 ) ) );
	$html    = '';

	if ( ! $empty ) {
		switch ( $display ) {
			case 'auto':
				if ( $field && function_exists( 'brik_field_html' ) ) {
					$html = brik_field_html(
						$name,
						$target,
						array(
							'size'        => $size,
							'columns'     => $columns,
							'date_format' => (string) $a['date_format'],
							'button'      => (string) $a['link_variant'],
							'lightbox'    => brik_form_bool( $a['lightbox'] ),
							'id'          => $ctx->uid( 'f' ),
						)
					);
					$html = in_array( $field['type'], array( 'text', 'number', 'range', 'date', 'datetime', 'time', 'email', 'url' ), true ) ? $affix( $html ) : $html;
				} else {
					$html = $affix( esc_html( $brik_field_text( $raw, $field ) ) );
				}
				break;

			case 'text':
				// Plain text means the stored value; only lists and choices need labels.
				$html = $affix( esc_html( is_scalar( $raw ) && ! ( $field && in_array( $field['type'], array( 'select', 'radio', 'button_group', 'post_object', 'taxonomy', 'user', 'toggle' ), true ) ) ? (string) $raw : $brik_field_text( $raw, $field ) ) );
				break;

			case 'date':
				$value = is_scalar( $raw ) ? (string) $raw : '';
				$time  = preg_match( '/^\d{8}$/', $value ) ? strtotime( substr( $value, 0, 4 ) . '-' . substr( $value, 4, 2 ) . '-' . substr( $value, 6, 2 ) ) : strtotime( $value );
				$text  = $time ? wp_date( '' !== (string) $a['date_format'] ? (string) $a['date_format'] : get_option( 'date_format' ), $time, new DateTimeZone( 'UTC' ) ) : $value;
				$html  = $affix( '<time datetime="' . esc_attr( $time ? gmdate( 'c', $time ) : '' ) . '">' . esc_html( $text ) . '</time>' );
				break;

			case 'image':
				$items = brik_media_items( $raw, $size );
				if ( $items ) {
					$html = '<div class="' . esc_attr( brik_cls( 'brik-field-image relative overflow-hidden bg-muted', $ratio, $radius ) ) . '">'
						. brik_media_img( $items[0], $size, array( 'class' => brik_cls( 'block w-full', $ratio ? 'h-full object-cover' : 'h-auto' ) ) )
						. '</div>';
				}
				break;

			case 'gallery':
				$items = brik_media_items( $raw, $size );
				$group = $ctx->uid( 'g' );
				$cols  = array(
					1 => 'grid-cols-1',
					2 => 'grid-cols-2',
					3 => 'grid-cols-2 sm:grid-cols-3',
					4 => 'grid-cols-2 sm:grid-cols-4',
					5 => 'grid-cols-3 sm:grid-cols-5',
					6 => 'grid-cols-3 sm:grid-cols-6',
				);
				$cells = '';
				foreach ( $items as $item ) {
					$frame = brik_cls( 'group relative block overflow-hidden bg-muted', $ratio ? $ratio : 'aspect-square', $radius );
					$img   = brik_media_img( $item, $size, array( 'class' => 'block size-full object-cover transition-transform duration-500 ease-out group-hover:scale-105' ) );
					$cells .= brik_form_bool( $a['lightbox'] ) && ! $ctx->canvas
						? '<a href="' . esc_url( $item['full'] ) . '" class="' . esc_attr( $frame . ' cursor-zoom-in outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50' ) . '" data-brik-lightbox="' . esc_attr( $group ) . '" data-caption="' . esc_attr( $item['caption'] ) . '">' . $img . '</a>'
						: '<div class="' . esc_attr( $frame ) . '">' . $img . '</div>';
				}
				$html = '' !== $cells ? '<div class="' . esc_attr( 'brik-field-gallery grid gap-3 ' . $cols[ $columns ] ) . '">' . $cells . '</div>' : '';
				break;

			case 'link':
				$url   = '';
				$title = '';
				$blank = false;
				if ( is_array( $raw ) && isset( $raw['url'] ) ) {
					$url   = (string) $raw['url'];
					$title = isset( $raw['title'] ) ? (string) $raw['title'] : '';
					$blank = ! empty( $raw['target'] ) && '_blank' === $raw['target'];
				} elseif ( is_numeric( $raw ) && get_post( (int) $raw ) ) {
					$url   = 'attachment' === get_post_type( (int) $raw ) ? (string) wp_get_attachment_url( (int) $raw ) : (string) get_permalink( (int) $raw );
					$title = get_the_title( (int) $raw );
				} elseif ( is_string( $raw ) ) {
					$url = $raw;
				}
				$url = esc_url( $url );
				if ( $url ) {
					$text  = '' !== trim( (string) $a['link_text'] ) ? brik_inline( $a['link_text'] ) : esc_html( '' !== $title ? $title : __( 'Visit', 'brik-builder' ) );
					$attrs = array(
						'class'  => brik_button_class( '' !== (string) $a['link_variant'] ? $a['link_variant'] : 'default', 'default', 'brik-field-link' ),
						'href'   => $url,
						'target' => $blank || brik_form_bool( $a['link_new_tab'] ) ? '_blank' : null,
						'rel'    => $blank || brik_form_bool( $a['link_new_tab'] ) ? 'noopener' : null,
					);
					$html = '<a' . brik_attrs( $attrs ) . '>' . $text . ( $blank || brik_form_bool( $a['link_new_tab'] ) ? brik_icon( 'arrow-up-right', 'size-4' ) : '' ) . '</a>';
				}
				break;

			case 'badges':
			case 'list':
				$values = is_array( $raw ) && ! isset( $raw['url'] ) ? $raw : array( $raw );
				$items  = array();
				foreach ( $values as $v ) {
					if ( is_array( $v ) ) {
						// Repeater rows: the row's values joined.
						$items[] = esc_html( implode( ' · ', array_filter( array_map( static function ( $x ) { return is_scalar( $x ) ? (string) $x : ''; }, $v ), 'strlen' ) ) );
					} elseif ( is_numeric( $v ) && $field && in_array( $field['type'], array( 'post_object', 'relationship' ), true ) && get_post( (int) $v ) ) {
						$items[] = '<a class="underline-offset-4 hover:underline" href="' . esc_url( get_permalink( (int) $v ) ) . '">' . esc_html( get_the_title( (int) $v ) ) . '</a>';
					} elseif ( is_numeric( $v ) && $field && 'taxonomy' === $field['type'] && get_term( (int) $v ) instanceof WP_Term ) {
						$items[] = esc_html( get_term( (int) $v )->name );
					} elseif ( is_scalar( $v ) && '' !== (string) $v ) {
						$items[] = esc_html( $field && class_exists( 'Brik\\Content\\Fields' ) ? Brik\Content\Fields::choice_label( $field, $v ) : (string) $v );
					}
				}
				$items = array_filter( $items, 'strlen' );
				if ( $items && 'badges' === $display ) {
					$variant = in_array( $a['badge_variant'], array( 'default', 'secondary', 'outline' ), true ) ? $a['badge_variant'] : 'secondary';
					$html    = '<div class="brik-field-badges flex flex-wrap gap-1.5">';
					foreach ( $items as $item ) {
						$html .= '<span class="' . esc_attr( brik_site_badge_class( $variant ) ) . '">' . $item . '</span>';
					}
					$html .= '</div>';
				} elseif ( $items ) {
					$html = '<ul class="brik-field-list grid list-disc gap-1.5 pl-5 marker:text-muted-foreground"><li>' . implode( '</li><li>', $items ) . '</li></ul>';
				}
				break;

			case 'table':
				$rows = is_array( $raw ) ? $raw : array();
				$subs = $field && ! empty( $field['options']['sub_fields'] ) ? (array) $field['options']['sub_fields'] : array();
				$cols = array();
				foreach ( $subs as $sub ) {
					if ( ! empty( $sub['name'] ) ) {
						$cols[ $sub['name'] ] = isset( $sub['label'] ) ? $sub['label'] : $sub['name'];
					}
				}
				if ( $rows && ! isset( $rows[0] ) ) {
					// A group: one label/value row per sub field.
					$body = '';
					foreach ( $rows as $k => $v ) {
						$body .= '<tr><th scope="row" class="font-medium">' . esc_html( isset( $cols[ $k ] ) ? $cols[ $k ] : $k ) . '</th><td>' . esc_html( $brik_field_text( $v, null ) ) . '</td></tr>';
					}
					$html = '<div class="brik-field-table overflow-x-auto rounded-lg border"><table class="w-full text-sm"><tbody>' . $body . '</tbody></table></div>';
				} elseif ( $rows ) {
					if ( ! $cols && is_array( $rows[0] ) ) {
						foreach ( array_keys( $rows[0] ) as $k ) {
							$cols[ $k ] = ucwords( str_replace( '_', ' ', $k ) );
						}
					}
					$head = '';
					foreach ( $cols as $label ) {
						$head .= '<th scope="col" class="bg-muted/50 font-medium">' . esc_html( $label ) . '</th>';
					}
					$body = '';
					foreach ( $rows as $row ) {
						$body .= '<tr>';
						foreach ( array_keys( $cols ) as $k ) {
							$body .= '<td>' . esc_html( $brik_field_text( is_array( $row ) && isset( $row[ $k ] ) ? $row[ $k ] : '', null ) ) . '</td>';
						}
						$body .= '</tr>';
					}
					$html = '<div class="brik-field-table overflow-x-auto rounded-lg border"><table class="w-full text-sm"><thead><tr>' . $head . '</tr></thead><tbody>' . $body . '</tbody></table></div>';
				}
				break;

			case 'map':
				$query = '';
				if ( is_array( $raw ) ) {
					$query = isset( $raw['lat'], $raw['lng'] ) && '' !== (string) $raw['lat'] ? (float) $raw['lat'] . ',' . (float) $raw['lng'] : ( isset( $raw['address'] ) ? (string) $raw['address'] : '' );
				} elseif ( is_scalar( $raw ) ) {
					$query = (string) $raw;
				}
				if ( '' !== trim( $query ) ) {
					$html = '<div class="' . esc_attr( brik_cls( 'brik-field-embed overflow-hidden border bg-muted', $ratio ? $ratio : 'aspect-video', $radius ) ) . '"><iframe src="' . esc_url( brik_map_embed_url( $query, 14 ) ) . '" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="' . esc_attr__( 'Map', 'brik-builder' ) . '"></iframe></div>';
				}
				break;

			case 'embed':
				$url = is_string( $raw ) ? esc_url_raw( $raw, array( 'http', 'https' ) ) : '';
				if ( $url ) {
					$embed = brik_oembed_html( $url );
					$html  = '' !== $embed
						? '<div class="' . esc_attr( brik_cls( 'brik-field-embed overflow-hidden', $ratio ? $ratio : 'aspect-video', $radius ) ) . '">' . $embed . '</div>'
						: '<a class="text-primary underline-offset-4 hover:underline break-all" href="' . esc_url( $url ) . '">' . esc_html( $url ) . '</a>';
				}
				break;
		}
	}

	if ( '' === $html ) {
		if ( '' !== trim( (string) $a['fallback'] ) ) {
			$html = '<span class="brik-field-fallback text-muted-foreground">' . brik_inline( $a['fallback'] ) . '</span>';
		} elseif ( $ctx->canvas ) {
			/* translators: %s: field name */
			return '<div class="brik-placeholder">' . esc_html( sprintf( __( 'Field: %s', 'brik-builder' ), $name ) ) . '</div>';
		} else {
			return '';
		}
	}

	$label = '';
	if ( brik_form_bool( $a['show_label'] ) ) {
		$text  = '' !== trim( (string) $a['label_text'] ) ? $a['label_text'] : ( $field && ! empty( $field['label'] ) ? esc_html( $field['label'] ) : esc_html( ucwords( str_replace( array( '_', '-', '.' ), ' ', $name ) ) ) );
		$label = '<div class="brik-field-label mb-1.5 text-xs font-medium uppercase tracking-wide text-muted-foreground">' . brik_inline( $text ) . '</div>';
	}
	return $label . '<div class="brik-field-value">' . $html . '</div>';
};

$brik_names = $brik_field_names();
$brik_media = array( 'show_if' => array( 'show_as' => array( 'auto', 'image', 'gallery', 'map', 'embed' ) ) );

return array(
	'type'        => 'field',
	'title'       => __( 'Field', 'brik-builder' ),
	'category'    => 'post',
	'icon'        => 'text-cursor-input',
	'description' => 'Shows one content field (defined under Brik → Content, or any post meta key). name: field name (dotted paths like "address.city" work)'
		. ( $brik_names ? ' — fields on this site: ' . implode( ', ', array_keys( $brik_names ) ) : '' )
		. '. source: post (current post; inside a listing loop item the listed post) | post_id (+ post_id) | option (site options page) | term (current term archive). show_as: auto (format by field type: images, galleries with lightbox, links as buttons, choices as badges, relationships as links, repeaters as lists, maps, oEmbeds, dates) | text | image | gallery | link | badges | list | table | map | embed | date. '
		. 'show_label (+ label_text), prefix, suffix, fallback (shown when empty), date_format (PHP format), image_size, image_ratio 16:9|4:3|3:2|1:1|…, image_rounded none|sm|md|lg|xl|2xl|full, gallery_columns 1-6, lightbox (bool), link_text, link_variant default|secondary|outline|ghost|link, link_new_tab, badge_variant default|secondary|outline. In the builder an empty field shows a "Field: name" placeholder.',
	'fields'      => array_merge(
		array(
			'name'            => Fields::field( 'text', __( 'Field name', 'brik-builder' ), 'content', array( 'placeholder' => 'price', 'suggestions' => array_keys( $brik_names ), 'description' => $brik_names ? sprintf( /* translators: %s: field names */ __( 'Available: %s', 'brik-builder' ), implode( ', ', array_slice( array_keys( $brik_names ), 0, 30 ) ) ) : __( 'A field name from Brik → Content, or any custom field key.', 'brik-builder' ) ) ),
			'source'          => Fields::field( 'select', __( 'Source', 'brik-builder' ), 'content', array( 'default' => 'post', 'options' => Fields::opts( array( 'post' => __( 'Current post', 'brik-builder' ), 'post_id' => __( 'Specific post', 'brik-builder' ), 'option' => __( 'Site options', 'brik-builder' ), 'term' => __( 'Current term', 'brik-builder' ) ) ) ) ),
			'post_id'         => Fields::field( 'number', __( 'Post ID', 'brik-builder' ), 'content', array( 'min' => 1, 'show_if' => array( 'source' => 'post_id' ) ) ),
			'show_as'         => Fields::field( 'select', __( 'Display as', 'brik-builder' ), 'content', array( 'default' => 'auto', 'options' => Fields::opts( array( 'auto' => __( 'Automatic (by field type)', 'brik-builder' ), 'text' => __( 'Text', 'brik-builder' ), 'image' => __( 'Image', 'brik-builder' ), 'gallery' => __( 'Gallery', 'brik-builder' ), 'link' => __( 'Link button', 'brik-builder' ), 'badges' => __( 'Badges', 'brik-builder' ), 'list' => __( 'List', 'brik-builder' ), 'table' => __( 'Table', 'brik-builder' ), 'map' => __( 'Map', 'brik-builder' ), 'embed' => __( 'Embed', 'brik-builder' ), 'date' => __( 'Date', 'brik-builder' ) ) ) ) ),
			'show_label'      => Fields::field( 'toggle', __( 'Show field label', 'brik-builder' ) ),
			'label_text'      => Fields::field( 'text', __( 'Label text', 'brik-builder' ), 'content', array( 'description' => __( 'Defaults to the field label.', 'brik-builder' ), 'show_if' => array( 'show_label' => true ) ) ),
			'prefix'          => Fields::field( 'text', __( 'Prefix', 'brik-builder' ), 'content', array( 'placeholder' => '$', 'show_if' => array( 'show_as' => array( 'auto', 'text', 'date' ) ) ) ),
			'suffix'          => Fields::field( 'text', __( 'Suffix', 'brik-builder' ), 'content', array( 'show_if' => array( 'show_as' => array( 'auto', 'text', 'date' ) ) ) ),
			'fallback'        => Fields::field( 'text', __( 'When empty', 'brik-builder' ), 'content', array( 'description' => __( 'Shown when the field has no value. Leave empty to hide the module.', 'brik-builder' ) ) ),
			'date_format'     => Fields::field( 'text', __( 'Date format', 'brik-builder' ), 'content', array( 'placeholder' => get_option( 'date_format' ), 'show_if' => array( 'show_as' => array( 'auto', 'date' ) ) ) ),
			'image_size'      => Fields::field( 'select', __( 'Image size', 'brik-builder' ), 'content', array_merge( $brik_media, array( 'default' => 'large', 'options' => Fields::opts( brik_image_size_options() ) ) ) ),
			'image_ratio'     => Fields::field( 'select', __( 'Aspect ratio', 'brik-builder' ), 'content', array_merge( $brik_media, array( 'options' => Fields::opts( brik_aspect_options() ) ) ) ),
			'image_rounded'   => Fields::field( 'select', __( 'Corners', 'brik-builder' ), 'content', array_merge( $brik_media, array( 'default' => 'lg', 'options' => Fields::opts( brik_radius_options() ) ) ) ),
			'gallery_columns' => Fields::field( 'number', __( 'Gallery columns', 'brik-builder' ), 'content', array( 'default' => 3, 'min' => 1, 'max' => 6, 'show_if' => array( 'show_as' => array( 'auto', 'gallery' ) ) ) ),
			'lightbox'        => Fields::field( 'toggle', __( 'Open images in a lightbox', 'brik-builder' ), 'content', array( 'default' => true, 'show_if' => array( 'show_as' => array( 'auto', 'gallery' ) ) ) ),
			'link_text'       => Fields::field( 'text', __( 'Link text', 'brik-builder' ), 'content', array( 'placeholder' => __( 'Visit website', 'brik-builder' ), 'show_if' => array( 'show_as' => 'link' ) ) ),
			'link_variant'    => Fields::field( 'select', __( 'Button variant', 'brik-builder' ), 'content', array( 'default' => 'default', 'options' => Fields::opts( brik_button_variants_labels() ), 'show_if' => array( 'show_as' => array( 'auto', 'link' ) ) ) ),
			'link_new_tab'    => Fields::field( 'toggle', __( 'Open in a new tab', 'brik-builder' ), 'content', array( 'show_if' => array( 'show_as' => 'link' ) ) ),
			'badge_variant'   => Fields::field( 'select', __( 'Badge style', 'brik-builder' ), 'content', array( 'default' => 'secondary', 'options' => Fields::opts( array( 'default' => __( 'Solid', 'brik-builder' ), 'secondary' => __( 'Subtle', 'brik-builder' ), 'outline' => __( 'Outline', 'brik-builder' ) ) ), 'show_if' => array( 'show_as' => 'badges' ) ) ),
		),
		Fields::typography( 'value', __( 'Value', 'brik-builder' ), Fields::WRAP . ' .brik-field-value' ),
		Fields::typography( 'label', __( 'Label', 'brik-builder' ), Fields::WRAP . ' .brik-field-label' )
	),
	'render'      => $brik_field_render,
);
