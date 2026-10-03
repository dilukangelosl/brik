<?php
/**
 * Breadcrumbs (shadcn breadcrumb) with optional schema.org markup.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'breadcrumbs',
	'title'       => __( 'Breadcrumbs', 'brik-builder' ),
	'category'    => 'site',
	'icon'        => 'chevron-right',
	'description' => 'Home › parents/terms › current page. Pages show their ancestors, posts their primary category, plus archives, search and 404. separator: chevron|slash|dot|arrow. home_label: text ("" hides the text), home_icon: bool. show_current: bool. schema: bool, prints BreadcrumbList JSON-LD.',
	'fields'      => array_merge(
		array(
			'home_label'   => Fields::field( 'text', __( 'Home label', 'brik-builder' ), 'content', array( 'default' => __( 'Home', 'brik-builder' ) ) ),
			'home_icon'    => Fields::field( 'toggle', __( 'Home icon', 'brik-builder' ), 'content' ),
			'separator'    => Fields::field( 'select', __( 'Separator', 'brik-builder' ), 'content', array( 'default' => 'chevron', 'options' => Fields::opts( array( 'chevron' => __( 'Chevron', 'brik-builder' ), 'slash' => __( 'Slash', 'brik-builder' ), 'dot' => __( 'Dot', 'brik-builder' ), 'arrow' => __( 'Arrow', 'brik-builder' ) ) ) ) ),
			'show_current' => Fields::field( 'toggle', __( 'Show current page', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'schema'       => Fields::field( 'toggle', __( 'Add schema.org markup', 'brik-builder' ), 'content', array( 'description' => __( 'Turn off if an SEO plugin already outputs breadcrumb markup.', 'brik-builder' ) ) ),
			'align'        => Fields::field( 'align', __( 'Alignment', 'brik-builder' ), 'content', array( 'responsive' => true, 'css' => array( 'selector' => Fields::WRAP . ' .brik-breadcrumb-list', 'map' => array( 'left' => 'justify-content:flex-start', 'center' => 'justify-content:center', 'right' => 'justify-content:flex-end' ) ) ) ),
		),
		Fields::typography( 'link', __( 'Links', 'brik-builder' ), Fields::WRAP . ' .brik-breadcrumb-link' ),
		Fields::typography( 'current', __( 'Current page', 'brik-builder' ), Fields::WRAP . ' .brik-breadcrumb-page' )
	),
	'render'      => static function ( $a, $ctx ) {
		if ( brik_site_is_preview( $ctx ) ) {
			$sample = brik_site_sample_post();
			$trail  = $sample ? brik_site_post_trail( $sample ) : array( array( __( 'Sample page', 'brik-builder' ), '' ) );
			if ( $sample && brik_site_preview_is_listing( $ctx ) ) {
				$term  = brik_site_primary_term( $sample, 'category' );
				$trail = $term ? brik_site_term_trail( $term ) : array( array( __( 'Blog', 'brik-builder' ), '' ) );
			}
		} elseif ( brik_site_is_layout( $ctx->post_id ) ) {
			$trail = brik_site_request_trail();
		} else {
			$post  = get_post( $ctx->post_id );
			$trail = $post && (int) get_option( 'page_on_front' ) !== $post->ID ? brik_site_post_trail( $post ) : array();
		}

		$home_label = trim( wp_strip_all_tags( (string) $a['home_label'] ) );
		$home_text  = '' !== $home_label ? $home_label : __( 'Home', 'brik-builder' );
		array_unshift( $trail, array( $home_text, home_url( '/' ) ) );

		$seps = array(
			'chevron' => brik_icon( 'chevron-right', 'size-3.5' ),
			'slash'   => brik_icon( 'slash', 'size-3.5 -rotate-12' ),
			'dot'     => '<span class="block size-1 rounded-full bg-current opacity-60"></span>',
			'arrow'   => brik_icon( 'arrow-right', 'size-3.5' ),
		);
		$sep = isset( $seps[ $a['separator'] ] ) ? $seps[ $a['separator'] ] : $seps['chevron'];

		$show_current = ! empty( $a['show_current'] );
		$last         = count( $trail ) - 1;
		$items        = array();
		foreach ( $trail as $i => $crumb ) {
			list( $label, $url ) = $crumb;
			$url                 = is_wp_error( $url ) ? '' : (string) $url;
			$is_home             = 0 === $i;
			$text                = esc_html( wp_strip_all_tags( (string) $label ) );
			if ( $is_home ) {
				$icon = ! empty( $a['home_icon'] ) ? brik_icon( 'house', 'size-4' ) : '';
				if ( '' !== $home_label ) {
					$text = $icon . '<span' . $ctx->inline( 'home_label' ) . '>' . $text . '</span>';
				} elseif ( $icon ) {
					$text = $icon . '<span class="sr-only">' . $text . '</span>';
				}
			}
			if ( $i === $last && $last > 0 ) {
				if ( ! $show_current ) {
					break;
				}
				$items[] = '<li class="inline-flex items-center gap-1.5"><span class="brik-breadcrumb-page font-normal text-foreground" aria-current="page">' . $text . '</span></li>';
			} elseif ( '' === $url ) {
				$items[] = '<li class="inline-flex items-center gap-1.5"><span class="brik-breadcrumb-text inline-flex items-center gap-1.5">' . $text . '</span></li>';
			} else {
				$items[] = '<li class="inline-flex items-center gap-1.5"><a class="brik-breadcrumb-link inline-flex items-center gap-1.5 transition-colors hover:text-foreground" href="' . esc_url( $url ) . '">' . $text . '</a></li>';
			}
		}

		$sep_html = '<li role="presentation" aria-hidden="true" class="brik-breadcrumb-sep inline-flex items-center [&>svg]:size-3.5">' . $sep . '</li>';
		$html     = '<nav class="brik-breadcrumb" aria-label="' . esc_attr__( 'Breadcrumb', 'brik-builder' ) . '"><ol class="brik-breadcrumb-list flex flex-wrap items-center gap-1.5 text-sm break-words text-muted-foreground sm:gap-2.5">' . implode( $sep_html, $items ) . '</ol></nav>';

		if ( ! empty( $a['schema'] ) && ! $ctx->canvas ) {
			$list = array();
			foreach ( $trail as $i => $crumb ) {
				$url = is_wp_error( $crumb[1] ) ? '' : (string) $crumb[1];
				if ( '' === $url && $i !== $last ) {
					continue;
				}
				$entry = array(
					'@type'    => 'ListItem',
					'position' => count( $list ) + 1,
					'name'     => wp_strip_all_tags( (string) $crumb[0] ),
				);
				if ( '' !== $url ) {
					$entry['item'] = $url;
				}
				$list[] = $entry;
			}
			$html .= '<script type="application/ld+json">' . wp_json_encode(
				array(
					'@context'        => 'https://schema.org',
					'@type'           => 'BreadcrumbList',
					'itemListElement' => $list,
				)
			) . '</script>';
		}
		return $html;
	},
);
