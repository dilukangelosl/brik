<?php
namespace Brik\Woo;

use Brik\Builder;
use Brik\Context;
use Brik\Fields;
use Brik\ThemeBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * Product lookup and shared markup for the single product modules.
 */
final class Product {

	/** Sample products by template id, so the builder preview stays stable within a request. */
	private static $samples = array();

	/**
	 * Optional "Product ID" field every product module carries, for using a module outside
	 * product templates (landing pages, posts).
	 */
	public static function field() {
		return array(
			'product' => Fields::field(
				'number',
				__( 'Product ID', 'brik-builder' ),
				'content',
				array(
					'min'         => 0,
					'description' => __( 'Leave empty to use the current product (template, loop item or product page).', 'brik-builder' ),
				)
			),
		);
	}

	/**
	 * The product a module describes, or null.
	 *
	 * A fixed product id wins. Loop items and Brik-built products describe themselves,
	 * templates describe the queried product (or the loop product), and templates open in
	 * the builder borrow a sample product so the preview looks real.
	 */
	public static function current( Context $ctx, array $a = array() ) {
		if ( ! empty( $a['product'] ) ) {
			$product = self::visible( (int) $a['product'] );
			if ( $product ) {
				return $product;
			}
		}

		$post_id = (int) $ctx->post_id;
		if ( $post_id && 'product' === get_post_type( $post_id ) ) {
			return self::visible( $post_id );
		}
		if ( $post_id && ! brik_site_is_layout( $post_id ) ) {
			return null;
		}
		if ( $ctx->canvas ) {
			return self::sample( $post_id );
		}
		return self::from_request();
	}

	/**
	 * Product for a dynamic tag rendered for $post_id.
	 */
	public static function for_post( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id && 'product' === get_post_type( $post_id ) ) {
			return self::visible( $post_id );
		}
		if ( $post_id && brik_site_is_layout( $post_id ) && self::previewing() ) {
			return self::sample( $post_id );
		}
		return self::from_request();
	}

	/**
	 * Whether the request is the builder canvas or one of its re-render calls.
	 */
	public static function previewing() {
		if ( Builder::is_canvas() ) {
			return true;
		}
		return defined( 'REST_REQUEST' ) && REST_REQUEST && current_user_can( 'edit_posts' );
	}

	/**
	 * Product of the current request: the loop product, the queried product, or the global one.
	 */
	public static function from_request() {
		if ( in_the_loop() && 'product' === get_post_type() ) {
			return self::visible( get_the_ID() );
		}
		if ( is_singular( 'product' ) ) {
			return self::visible( get_queried_object_id() );
		}
		$global = isset( $GLOBALS['product'] ) ? $GLOBALS['product'] : null;
		return $global instanceof \WC_Product ? $global : null;
	}

	/**
	 * A product the visitor may see: published, or any status for people who can edit it.
	 */
	public static function visible( $id ) {
		$product = $id ? wc_get_product( $id ) : null;
		if ( ! $product instanceof \WC_Product || $product->is_type( 'variation' ) ) {
			return null;
		}
		if ( 'publish' !== $product->get_status() && ! current_user_can( 'edit_post', $product->get_id() ) ) {
			return null;
		}
		return $product;
	}

	/**
	 * Sample product for the builder: ?brik_preview_post=ID, then a product the template's
	 * conditions point at, then the latest published product with an image.
	 */
	public static function sample( $template_id = 0 ) {
		$template_id = (int) $template_id;
		if ( array_key_exists( $template_id, self::$samples ) ) {
			return self::$samples[ $template_id ];
		}

		$id = self::requested_preview();
		if ( ! $id && $template_id ) {
			$id = (int) get_post_meta( $template_id, '_brik_preview_post', true );
		}
		if ( ! $id && $template_id && ThemeBuilder::is_template( $template_id ) ) {
			$id = self::id_from_conditions( ThemeBuilder::conditions( $template_id ) );
		}

		$product = $id ? self::visible( $id ) : null;
		if ( ! $product ) {
			$args = array(
				'status'  => 'publish',
				'limit'   => 1,
				'orderby' => 'date',
				'order'   => 'DESC',
				'return'  => 'ids',
			);
			$ids  = wc_get_products( array_merge( $args, array( 'meta_key' => '_thumbnail_id' ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
			$ids  = $ids ? $ids : wc_get_products( $args );
			$product = $ids ? wc_get_product( $ids[0] ) : null;
		}

		self::$samples[ $template_id ] = $product ? $product : null;
		return self::$samples[ $template_id ];
	}

	private static function requested_preview() {
		// phpcs:disable WordPress.Security.NonceVerification -- read-only preview selection.
		if ( isset( $_GET['brik_preview_post'] ) ) {
			return absint( $_GET['brik_preview_post'] );
		}
		// phpcs:enable
		// Builder re-renders come from the app page; honour the parameter it was opened with.
		$referer = wp_get_raw_referer();
		if ( $referer ) {
			$query = array();
			wp_parse_str( (string) wp_parse_url( $referer, PHP_URL_QUERY ), $query );
			if ( ! empty( $query['brik_preview_post'] ) ) {
				return absint( $query['brik_preview_post'] );
			}
		}
		return 0;
	}

	private static function id_from_conditions( array $rules ) {
		foreach ( $rules as $rule ) {
			if ( 'include' !== $rule['type'] || empty( $rule['ids'] ) ) {
				continue;
			}
			if ( in_array( $rule['rule'], array( 'product', 'post' ), true ) && 'product' === get_post_type( (int) $rule['ids'][0] ) ) {
				return (int) $rule['ids'][0];
			}
			$taxonomy = 'product_in_cat' === $rule['rule'] ? 'product_cat' : ( 'in_term' === $rule['rule'] ? $rule['taxonomy'] : '' );
			if ( in_array( $taxonomy, array( 'product_cat', 'product_tag' ), true ) ) {
				$ids = get_posts(
					array(
						'post_type'      => 'product',
						'post_status'    => 'publish',
						'posts_per_page' => 1,
						'fields'         => 'ids',
						'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery
							array(
								'taxonomy' => $taxonomy,
								'terms'    => array_map( 'intval', $rule['ids'] ),
							),
						),
					)
				);
				if ( $ids ) {
					return (int) $ids[0];
				}
			}
		}
		return 0;
	}

	/**
	 * Run a callback with the global post and product set to $product, so Woo template
	 * functions, hooks and filters see the right product.
	 */
	public static function with( \WC_Product $product, callable $callback ) {
		global $post;
		$prev_post    = $post;
		$prev_product = isset( $GLOBALS['product'] ) ? $GLOBALS['product'] : null;

		$post = get_post( $product->get_id() ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		setup_postdata( $post );
		$GLOBALS['product'] = $product; // phpcs:ignore WordPress.WP.GlobalVariablesOverride

		try {
			$result = $callback( $product );
		} finally {
			$post = $prev_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			if ( $prev_post instanceof \WP_Post ) {
				setup_postdata( $prev_post );
			}
			$GLOBALS['product'] = $prev_product; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		}
		return $result;
	}

	/**
	 * Output of a callback that prints.
	 */
	public static function capture( \WC_Product $product, callable $callback ) {
		return self::with(
			$product,
			static function ( $product ) use ( $callback ) {
				ob_start();
				$callback( $product );
				return (string) ob_get_clean();
			}
		);
	}

	/* ---------------------------------------------------------------------
	 * Prices, stock and badges.
	 * ------------------------------------------------------------------- */

	/**
	 * Largest discount in percent (variable products: across variations).
	 */
	public static function sale_percent( \WC_Product $product ) {
		if ( ! $product->is_on_sale() ) {
			return 0;
		}
		if ( $product->is_type( 'variable' ) ) {
			$best = 0;
			foreach ( $product->get_variation_prices()['regular_price'] as $id => $regular ) {
				$sale = $product->get_variation_prices()['sale_price'][ $id ];
				if ( (float) $regular > 0 && (float) $sale < (float) $regular ) {
					$best = max( $best, (int) round( 100 - ( (float) $sale / (float) $regular * 100 ) ) );
				}
			}
			return $best;
		}
		if ( $product->is_type( 'grouped' ) ) {
			return 0;
		}
		$regular = (float) $product->get_regular_price();
		$sale    = (float) $product->get_sale_price();
		return $regular > 0 && $sale < $regular ? (int) round( 100 - $sale / $regular * 100 ) : 0;
	}

	/**
	 * Plain formatted amount, e.g. "$18.00", without markup or screen reader text.
	 */
	public static function plain_price( $amount ) {
		if ( '' === $amount || null === $amount ) {
			return '';
		}
		return html_entity_decode( wp_strip_all_tags( wc_price( (float) $amount ) ), ENT_QUOTES, get_bloginfo( 'charset' ) );
	}

	/**
	 * Current price as plain text; variable products give their range.
	 */
	public static function plain_current_price( \WC_Product $product ) {
		if ( $product->is_type( 'variable' ) ) {
			$min = $product->get_variation_price( 'min', true );
			$max = $product->get_variation_price( 'max', true );
			if ( '' === $min ) {
				return '';
			}
			return $min === $max ? self::plain_price( $min ) : self::plain_price( $min ) . ' – ' . self::plain_price( $max );
		}
		if ( $product->is_type( 'grouped' ) ) {
			return html_entity_decode( wp_strip_all_tags( $product->get_price_html() ), ENT_QUOTES, get_bloginfo( 'charset' ) );
		}
		return self::plain_price( wc_get_price_to_display( $product ) );
	}

	/**
	 * Stock state for the stock module and variation updates: status, label, quantity.
	 */
	public static function stock( \WC_Product $product ) {
		$status = $product->get_stock_status();
		$text   = wp_strip_all_tags( (string) $product->get_availability()['availability'] );
		if ( '' === $text ) {
			$labels = wc_get_product_stock_status_options();
			$text   = isset( $labels[ $status ] ) ? $labels[ $status ] : '';
		}
		$qty = $product->managing_stock() ? (int) $product->get_stock_quantity() : null;
		$low = (int) get_option( 'woocommerce_notify_low_stock_amount', 2 );
		if ( 'instock' === $status && null !== $qty && $qty > 0 && $qty <= max( 1, $low ) ) {
			$status = 'lowstock';
		}
		return array(
			'status' => $status,
			'text'   => $text,
			'qty'    => $qty,
		);
	}

	/**
	 * Whether a product counts as new.
	 */
	public static function is_new( \WC_Product $product, $days = 30 ) {
		$created = $product->get_date_created();
		return $created && $created->getTimestamp() > time() - max( 1, (int) $days ) * DAY_IN_SECONDS;
	}

	/**
	 * Badge markup for one state.
	 */
	public static function badge( $text, $variant, $extra = '' ) {
		return '<span class="' . esc_attr( brik_badge_class( $variant, 'default', brik_cls( 'brik-product-badge', $extra ) ) ) . '">' . esc_html( $text ) . '</span>';
	}

	/**
	 * "-20%" / "Sale" label for a product.
	 */
	public static function sale_label( \WC_Product $product, $format = 'percent' ) {
		$percent = self::sale_percent( $product );
		if ( 'percent' === $format && $percent > 0 ) {
			/* translators: %d: discount percentage */
			return sprintf( $product->is_type( 'variable' ) && self::variable_discounts_differ( $product ) ? __( 'Up to −%d%%', 'brik-builder' ) : __( '−%d%%', 'brik-builder' ), $percent );
		}
		return __( 'Sale', 'brik-builder' );
	}

	private static function variable_discounts_differ( \WC_Product $product ) {
		$seen   = array();
		$prices = $product->get_variation_prices();
		foreach ( $prices['regular_price'] as $id => $regular ) {
			$sale         = $prices['sale_price'][ $id ];
			$seen[]       = (float) $regular > 0 ? (int) round( 100 - (float) $sale / (float) $regular * 100 ) : 0;
		}
		return count( array_unique( $seen ) ) > 1;
	}

	/* ---------------------------------------------------------------------
	 * Images.
	 * ------------------------------------------------------------------- */

	/**
	 * Featured image followed by the gallery, without duplicates.
	 */
	public static function image_ids( \WC_Product $product ) {
		$ids = array();
		if ( $product->get_image_id() ) {
			$ids[] = (int) $product->get_image_id();
		}
		foreach ( $product->get_gallery_image_ids() as $id ) {
			$ids[] = (int) $id;
		}
		// Imported products can reference attachments that no longer exist.
		return array_values( array_unique( array_filter( $ids, 'wp_attachment_is_image' ) ) );
	}

	/* ---------------------------------------------------------------------
	 * Attributes.
	 * ------------------------------------------------------------------- */

	/**
	 * Rows of the "Additional information" table, as WooCommerce builds them
	 * (weight, dimensions, visible attributes), passed through the same filter.
	 *
	 * @return array key => [ label, value (HTML) ]
	 */
	public static function attribute_rows( \WC_Product $product ) {
		$rows = array();
		if ( $product->has_weight() && apply_filters( 'wc_product_enable_dimensions_display', true ) ) {
			$rows['weight'] = array(
				'label' => __( 'Weight', 'brik-builder' ),
				'value' => wc_format_weight( $product->get_weight() ),
			);
		}
		if ( $product->has_dimensions() && apply_filters( 'wc_product_enable_dimensions_display', true ) ) {
			$rows['dimensions'] = array(
				'label' => __( 'Dimensions', 'brik-builder' ),
				'value' => wc_format_dimensions( $product->get_dimensions( false ) ),
			);
		}
		foreach ( $product->get_attributes() as $attribute ) {
			if ( ! $attribute instanceof \WC_Product_Attribute || ! $attribute->get_visible() ) {
				continue;
			}
			$values = array();
			if ( $attribute->is_taxonomy() ) {
				$taxonomy = $attribute->get_taxonomy_object();
				foreach ( $attribute->get_terms() as $term ) {
					$name = esc_html( $term->name );
					if ( $taxonomy && $taxonomy->attribute_public ) {
						$link = get_term_link( $term->term_id, $attribute->get_name() );
						$name = is_wp_error( $link ) ? $name : '<a href="' . esc_url( $link ) . '" rel="tag">' . $name . '</a>';
					}
					$values[] = $name;
				}
			} else {
				foreach ( $attribute->get_options() as $value ) {
					$values[] = esc_html( $value );
				}
			}
			$rows[ 'attribute_' . sanitize_title_with_dashes( $attribute->get_name() ) ] = array(
				'label' => wc_attribute_label( $attribute->get_name() ),
				'value' => apply_filters( 'woocommerce_attribute', wpautop( wptexturize( implode( ', ', $values ) ) ), $attribute, $values ),
			);
		}
		return apply_filters( 'woocommerce_display_product_attributes', $rows, $product );
	}

	/**
	 * Whether an attribute holds colors, judging by its name or label.
	 */
	public static function is_color_attribute( $attribute ) {
		$label = wc_attribute_label( $attribute );
		return (bool) preg_match( '/colou?r|farbe|couleur|kleur|colore/i', $attribute . ' ' . $label );
	}

	/**
	 * CSS color for an attribute option: term meta from common swatch plugins, else the
	 * option name when it is a CSS color name. Empty when unknown.
	 */
	public static function swatch_color( $taxonomy, $value, $label = '' ) {
		if ( taxonomy_exists( $taxonomy ) ) {
			$term = get_term_by( 'slug', $value, $taxonomy );
			if ( $term ) {
				foreach ( array( 'brik_color', 'color', 'swatch_color', 'product_attribute_color', 'pa_color_swatches_id_color' ) as $key ) {
					$meta = sanitize_hex_color( (string) get_term_meta( $term->term_id, $key, true ) );
					if ( $meta ) {
						return $meta;
					}
				}
				$label = $term->name;
			}
		}
		$name = strtolower( preg_replace( '/[^a-z]/i', '', $label ? $label : $value ) );
		$map  = self::color_names();
		return isset( $map[ $name ] ) ? $map[ $name ] : '';
	}

	private static function color_names() {
		return apply_filters(
			'brik/woo_swatch_colors',
			array(
				'black'     => '#0a0a0a',
				'white'     => '#ffffff',
				'gray'      => '#9ca3af',
				'grey'      => '#9ca3af',
				'silver'    => '#d1d5db',
				'charcoal'  => '#374151',
				'red'       => '#dc2626',
				'maroon'    => '#7f1d1d',
				'burgundy'  => '#7c2d3a',
				'pink'      => '#ec4899',
				'rose'      => '#f43f5e',
				'orange'    => '#f97316',
				'yellow'    => '#facc15',
				'gold'      => '#d4a72c',
				'beige'     => '#e7dcc6',
				'cream'     => '#f5efe0',
				'brown'     => '#8b5a2b',
				'tan'       => '#d2b48c',
				'khaki'     => '#bdb07a',
				'olive'     => '#6b7a2d',
				'green'     => '#16a34a',
				'mint'      => '#a7f3d0',
				'teal'      => '#0d9488',
				'turquoise' => '#2dd4bf',
				'cyan'      => '#06b6d4',
				'blue'      => '#2563eb',
				'navy'      => '#1e2a4a',
				'skyblue'   => '#7dd3fc',
				'lightblue' => '#93c5fd',
				'purple'    => '#9333ea',
				'violet'    => '#7c3aed',
				'lavender'  => '#c4b5fd',
				'indigo'    => '#4f46e5',
				'magenta'   => '#d946ef',
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Reviews.
	 * ------------------------------------------------------------------- */

	/**
	 * Whether reviews are switched on for a product.
	 */
	public static function reviews_open( \WC_Product $product ) {
		return wc_reviews_enabled() && $product->get_reviews_allowed();
	}

	/**
	 * Review summary, list and form.
	 *
	 * @param array $opt summary, list, form (bool), title (string), uid (DOM id prefix), canvas (bool).
	 */
	public static function reviews_html( \WC_Product $product, array $opt = array() ) {
		$opt = wp_parse_args(
			$opt,
			array(
				'summary' => true,
				'list'    => true,
				'form'    => true,
				'title'   => '',
				'uid'     => 'brik-reviews',
				'canvas'  => false,
				'limit'   => 0,
			)
		);
		if ( ! self::reviews_open( $product ) ) {
			return '';
		}

		$count   = (int) $product->get_review_count();
		$average = (float) $product->get_average_rating();
		$ratings = wc_review_ratings_enabled();
		$out     = '';

		if ( '' !== $opt['title'] ) {
			$out .= '<h2 class="brik-reviews-title font-heading text-xl font-semibold tracking-tight">' . esc_html( $opt['title'] ) . '</h2>';
		}

		$head = '';
		if ( $opt['summary'] && $ratings ) {
			$head .= self::reviews_summary( $product, $count, $average );
		}

		$body = '';
		if ( $opt['list'] ) {
			$body .= self::reviews_list( $product, $count, $opt );
		}
		if ( $opt['form'] ) {
			$body .= self::review_form( $product, $opt );
		}

		$out .= $head ? '<div class="brik-reviews-grid">' . $head . '<div class="brik-reviews-main flex min-w-0 flex-col gap-8">' . $body . '</div></div>' : '<div class="brik-reviews-main flex min-w-0 flex-col gap-8">' . $body . '</div>';
		return $out;
	}

	private static function reviews_summary( \WC_Product $product, $count, $average ) {
		$counts = $product->get_rating_counts();
		$bars   = '';
		for ( $star = 5; $star >= 1; $star-- ) {
			$n     = isset( $counts[ $star ] ) ? (int) $counts[ $star ] : 0;
			$pct   = $count > 0 ? round( $n / max( 1, array_sum( $counts ) ) * 100 ) : 0;
			$bars .= '<div class="brik-reviews-bar grid grid-cols-[2.5rem_1fr_2rem] items-center gap-3 text-sm">'
				. '<span class="inline-flex items-center gap-1 text-muted-foreground tabular-nums">' . (int) $star . brik_icon( 'star', 'size-3.5 fill-current text-amber-400' ) . '</span>'
				. '<span class="relative h-2 overflow-hidden rounded-full bg-muted"><span class="absolute inset-y-0 left-0 rounded-full bg-amber-400" style="width:' . esc_attr( $pct ) . '%"></span></span>'
				. '<span class="text-right text-muted-foreground tabular-nums">' . esc_html( number_format_i18n( $n ) ) . '</span>'
				. '</div>';
		}
		/* translators: %s: number of reviews */
		$based = $count ? sprintf( _n( 'Based on %s review', 'Based on %s reviews', $count, 'brik-builder' ), number_format_i18n( $count ) ) : __( 'No reviews yet', 'brik-builder' );
		return '<aside class="brik-reviews-summary flex h-fit flex-col gap-4 rounded-xl border bg-card p-6 text-card-foreground shadow-xs">'
			. '<div class="flex items-end gap-3"><span class="font-heading text-5xl leading-none font-semibold tracking-tight tabular-nums">' . esc_html( number_format_i18n( $average, 1 ) ) . '</span><span class="pb-1 text-sm text-muted-foreground">/ 5</span></div>'
			. brik_stars( $average, 5, 'size-5' )
			. '<p class="text-sm text-muted-foreground">' . esc_html( $based ) . '</p>'
			. '<div class="flex flex-col gap-2">' . $bars . '</div>'
			. '</aside>';
	}

	private static function reviews_list( \WC_Product $product, $count, array $opt ) {
		$args = array(
			'post_id' => $product->get_id(),
			'status'  => 'approve',
			'type'    => 'review',
			'orderby' => 'comment_date_gmt',
			'order'   => 'DESC',
		);
		if ( $opt['limit'] > 0 ) {
			$args['number'] = (int) $opt['limit'];
		}
		$comments = get_comments( apply_filters( 'brik/woo_review_query_args', $args, $product ) );

		// Show a review awaiting moderation to the visitor who wrote it.
		// Cookie or the ?unapproved=…&moderation-hash=… link WordPress redirects to after posting.
		$email = function_exists( 'wp_get_unapproved_comment_author_email' ) ? wp_get_unapproved_comment_author_email() : '';
		if ( ! $opt['canvas'] && $email ) {
			$pending  = get_comments(
				array(
					'post_id'      => $product->get_id(),
					'status'       => 'hold',
					'type'         => 'review',
					'author_email' => $email,
				)
			);
			$comments = array_merge( $pending, $comments );
		}

		if ( ! $comments ) {
			return '<div class="brik-reviews-empty flex flex-col items-center gap-2 rounded-xl border border-dashed px-6 py-10 text-center">'
				. brik_icon( 'message-square-text', 'size-7 text-muted-foreground/60' )
				. '<p class="font-medium">' . esc_html__( 'No reviews yet', 'brik-builder' ) . '</p>'
				. '<p class="text-sm text-muted-foreground">' . esc_html__( 'Be the first to share what you think.', 'brik-builder' ) . '</p></div>';
		}

		$items = '';
		foreach ( $comments as $comment ) {
			$rating   = (int) get_comment_meta( $comment->comment_ID, 'rating', true );
			$verified = wc_review_is_from_verified_owner( $comment->comment_ID );
			$name     = get_comment_author( $comment );
			$avatar   = get_avatar_url( $comment, array( 'size' => 80 ) );
			$date     = get_comment_date( '', $comment );
			$meta     = '<span class="font-medium text-foreground">' . esc_html( $name ) . '</span>';
			if ( $verified && 'yes' === get_option( 'woocommerce_review_rating_verification_label' ) ) {
				$meta .= '<span class="' . esc_attr( brik_badge_class( 'success', 'default', 'gap-1' ) ) . '">' . brik_icon( 'badge-check', 'size-3' ) . esc_html__( 'Verified owner', 'brik-builder' ) . '</span>';
			}
			$pending = '0' === (string) $comment->comment_approved ? '<p class="text-xs text-muted-foreground italic">' . esc_html__( 'Your review is awaiting approval.', 'brik-builder' ) . '</p>' : '';
			$items  .= '<li id="li-comment-' . (int) $comment->comment_ID . '" class="brik-review flex gap-4 py-6 first:pt-0 last:pb-0">'
				. ( $avatar ? '<img class="brik-review-avatar size-10 shrink-0 rounded-full bg-muted object-cover" src="' . esc_url( $avatar ) . '" alt="" width="40" height="40" loading="lazy" decoding="async">' : '' )
				. '<div id="comment-' . (int) $comment->comment_ID . '" class="flex min-w-0 flex-1 flex-col gap-2">'
				. '<div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">' . $meta . '<span class="text-muted-foreground" aria-hidden="true">·</span><time class="text-muted-foreground" datetime="' . esc_attr( get_comment_date( 'c', $comment ) ) . '">' . esc_html( $date ) . '</time></div>'
				. ( $rating && wc_review_ratings_enabled() ? brik_stars( $rating, 5, 'size-4' ) : '' )
				. $pending
				. '<div class="brik-review-text brik-prose text-sm leading-relaxed text-foreground/90">' . wp_kses_post( wpautop( get_comment_text( $comment ) ) ) . '</div>'
				. '</div></li>';
		}

		/* translators: %s: number of reviews */
		$heading = sprintf( _n( '%s review', '%s reviews', max( 1, $count ), 'brik-builder' ), number_format_i18n( $count ) );
		return '<div class="brik-reviews-list flex flex-col gap-4"><h3 class="font-heading text-base font-semibold">' . esc_html( $heading ) . '</h3><ol class="divide-y">' . $items . '</ol></div>';
	}

	/**
	 * The review form: WordPress comment_form() with WooCommerce's review fields, so plugins
	 * hooking the comment form and review rating checks keep working.
	 */
	private static function review_form( \WC_Product $product, array $opt ) {
		$verified_only = 'yes' === get_option( 'woocommerce_review_rating_verification_required' );
		if ( $verified_only && ! wc_customer_bought_product( '', get_current_user_id(), $product->get_id() ) ) {
			return '<p class="brik-reviews-locked rounded-lg border bg-muted/40 px-4 py-3 text-sm text-muted-foreground">' . esc_html__( 'Only logged in customers who have purchased this product may leave a review.', 'brik-builder' ) . '</p>';
		}
		if ( ! comments_open( $product->get_id() ) ) {
			return '';
		}

		$commenter = wp_get_current_commenter();
		$required  = (bool) get_option( 'require_name_email', 1 );
		$req_mark  = '<span class="brik-required text-destructive" aria-hidden="true">*</span>';
		$input     = 'brik-input flex h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm dark:bg-input/30';
		$label     = 'brik-label flex items-center gap-1 text-sm leading-none font-medium';

		$fields = array();
		foreach (
			array(
				'author' => array( __( 'Name', 'brik-builder' ), 'text', 'name' ),
				'email'  => array( __( 'Email', 'brik-builder' ), 'email', 'email' ),
			) as $key => $field
		) {
			$id             = $opt['uid'] . '-' . $key;
			$fields[ $key ] = '<p class="comment-form-' . esc_attr( $key ) . ' flex flex-col gap-2"><label class="' . esc_attr( $label ) . '" for="' . esc_attr( $id ) . '">' . esc_html( $field[0] ) . ( $required ? ' ' . $req_mark : '' ) . '</label>'
				. '<input class="' . esc_attr( $input ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $key ) . '" type="' . esc_attr( $field[1] ) . '" autocomplete="' . esc_attr( $field[2] ) . '" value="' . esc_attr( $commenter[ 'comment_author' . ( 'email' === $key ? '_email' : '' ) ] ) . '" size="30"' . ( $required ? ' required' : '' ) . '></p>';
		}

		$comment_field = '';
		if ( wc_review_ratings_enabled() ) {
			$stars = '';
			for ( $i = 5; $i >= 1; $i-- ) {
				$id     = $opt['uid'] . '-star-' . $i;
				/* translators: %d: number of stars */
				$title  = sprintf( _n( '%d star', '%d stars', $i, 'brik-builder' ), $i );
				$stars .= '<input class="brik-rate-input sr-only" type="radio" name="rating" id="' . esc_attr( $id ) . '" value="' . (int) $i . '"' . ( wc_review_ratings_required() && 5 === $i ? ' required' : '' ) . '>'
					. '<label class="brik-rate-star" for="' . esc_attr( $id ) . '" title="' . esc_attr( $title ) . '"><span class="sr-only">' . esc_html( $title ) . '</span>' . brik_icon( 'star', 'size-6' ) . '</label>';
			}
			$comment_field .= '<fieldset class="comment-form-rating brik-rate flex flex-col gap-2"><legend class="' . esc_attr( $label ) . ' mb-2">' . esc_html__( 'Your rating', 'brik-builder' ) . ( wc_review_ratings_required() ? ' ' . $req_mark : '' ) . '</legend><div class="brik-rate-stars">' . $stars . '</div></fieldset>';
		}
		$comment_id     = $opt['uid'] . '-comment';
		$comment_field .= '<p class="comment-form-comment flex flex-col gap-2"><label class="' . esc_attr( $label ) . '" for="' . esc_attr( $comment_id ) . '">' . esc_html__( 'Your review', 'brik-builder' ) . ' ' . $req_mark . '</label>'
			. '<textarea class="' . esc_attr( str_replace( 'h-9 ', '', $input ) . ' min-h-28 py-2' ) . '" id="' . esc_attr( $comment_id ) . '" name="comment" cols="45" rows="6" required></textarea></p>';

		$has     = $product->get_review_count() > 0;
		$account = wc_get_page_permalink( 'myaccount' );
		$args    = array(
			/* translators: %s: product name */
			'title_reply'          => $has ? esc_html__( 'Write a review', 'brik-builder' ) : sprintf( esc_html__( 'Be the first to review “%s”', 'brik-builder' ), esc_html( $product->get_name() ) ),
			/* translators: %s: reviewer name */
			'title_reply_to'       => esc_html__( 'Leave a reply to %s', 'brik-builder' ),
			'title_reply_before'   => '<h3 id="reply-title" class="comment-reply-title font-heading text-base font-semibold">',
			'title_reply_after'    => '</h3>',
			'comment_notes_before' => '<p class="comment-notes text-sm text-muted-foreground">' . esc_html__( 'Your email address will not be published.', 'brik-builder' ) . '</p>',
			'comment_notes_after'  => '',
			'label_submit'         => esc_html__( 'Submit review', 'brik-builder' ),
			'class_submit'         => brik_button_class( 'default', 'default', 'submit' ),
			'submit_button'        => '<button name="%1$s" type="submit" id="%2$s" class="%3$s">%4$s</button>',
			'class_form'           => 'comment-form brik-review-form flex flex-col gap-4',
			'logged_in_as'         => '',
			'comment_field'        => $comment_field,
			'fields'               => $fields,
			'id_form'              => $opt['uid'] . '-form',
		);
		if ( $account ) {
			/* translators: 1: opening link tag, 2: closing link tag */
			$args['must_log_in'] = '<p class="must-log-in text-sm text-muted-foreground">' . sprintf( esc_html__( 'You must be %1$slogged in%2$s to post a review.', 'brik-builder' ), '<a class="font-medium text-foreground underline underline-offset-4" href="' . esc_url( $account ) . '">', '</a>' ) . '</p>';
		}
		$args = apply_filters( 'woocommerce_product_review_comment_form_args', $args );

		$html = self::capture(
			$product,
			static function ( $product ) use ( $args ) {
				comment_form( $args, $product->get_id() );
			}
		);
		return '<div id="review_form_wrapper" class="brik-review-form-wrap rounded-xl border bg-card p-6 text-card-foreground shadow-xs">' . $html . '</div>';
	}

	/* ---------------------------------------------------------------------
	 * Product cards (related, upsells).
	 * ------------------------------------------------------------------- */

	/**
	 * A product card. Uses the shop card renderer when it is available so related products
	 * match the product grid; otherwise a compact card of our own.
	 */
	public static function card( \WC_Product $product, array $args = array() ) {
		if ( function_exists( 'brik_woo_product_card' ) ) {
			return brik_woo_product_card( $product, $args );
		}
		return self::with(
			$product,
			static function ( $product ) use ( $args ) {
				return Product::fallback_card( $product, $args );
			}
		);
	}

	/**
	 * Card used when the shop module set is not loaded.
	 */
	public static function fallback_card( \WC_Product $product, array $args = array() ) {
		$args  = wp_parse_args(
			$args,
			array(
				'ratio'  => '1:1',
				'button' => true,
				'rating' => true,
			)
		);
		$url   = $product->get_permalink();
		$image = $product->get_image_id()
			? wp_get_attachment_image( $product->get_image_id(), 'woocommerce_thumbnail', false, array( 'class' => 'brik-pc-img size-full object-cover transition-transform duration-500 ease-out group-hover:scale-105', 'loading' => 'lazy' ) )
			: wc_placeholder_img( 'woocommerce_thumbnail', array( 'class' => 'brik-pc-img size-full object-cover' ) );

		$badge = '';
		if ( ! $product->is_in_stock() ) {
			$badge = self::badge( __( 'Sold out', 'brik-builder' ), 'secondary' );
		} elseif ( $product->is_on_sale() ) {
			$badge = self::badge( self::sale_label( $product ), 'destructive' );
		}

		$rating = '';
		if ( $args['rating'] && wc_review_ratings_enabled() && $product->get_review_count() ) {
			$rating = '<div class="flex items-center gap-1.5 text-xs text-muted-foreground">' . brik_stars( (float) $product->get_average_rating(), 5, 'size-3.5' ) . '<span>(' . esc_html( number_format_i18n( $product->get_review_count() ) ) . ')</span></div>';
		}

		$button = '';
		if ( $args['button'] ) {
			$ajax   = $product->supports( 'ajax_add_to_cart' ) && $product->is_purchasable() && $product->is_in_stock();
			$attrs  = array(
				'href'             => $product->add_to_cart_url(),
				'class'            => brik_button_class( 'outline', 'sm', brik_cls( 'brik-pc-button w-full', array( 'add_to_cart_button ajax_add_to_cart' => $ajax ) ) ),
				'data-product_id'  => $product->get_id(),
				'data-product_sku' => $product->get_sku(),
				'data-quantity'    => 1,
				'aria-label'       => wp_strip_all_tags( $product->add_to_cart_description() ),
				'rel'              => 'nofollow',
			);
			$button = '<a' . brik_attrs( $attrs ) . '>' . brik_icon( $product->is_type( 'simple' ) && $ajax ? 'shopping-bag' : 'arrow-right', 'size-4' ) . esc_html( $product->add_to_cart_text() ) . '</a>';
		}

		return '<article class="brik-product-card group/card flex h-full flex-col overflow-hidden rounded-xl border bg-card text-card-foreground shadow-xs transition-shadow hover:shadow-md">'
			. '<a class="brik-pc-media group relative block overflow-hidden bg-muted ' . esc_attr( brik_aspect_class( $args['ratio'] ) ) . '" href="' . esc_url( $url ) . '" tabindex="-1" aria-hidden="true">' . $image
			. ( $badge ? '<span class="absolute top-3 left-3 flex gap-1.5">' . $badge . '</span>' : '' ) . '</a>'
			. '<div class="flex flex-1 flex-col gap-2 p-4">'
			. '<h3 class="brik-pc-title text-sm font-medium leading-snug"><a class="hover:underline underline-offset-4" href="' . esc_url( $url ) . '">' . esc_html( $product->get_name() ) . '</a></h3>'
			. $rating
			. '<div class="brik-pc-price brik-price-amount text-sm font-semibold">' . wp_kses_post( $product->get_price_html() ) . '</div>'
			. ( $button ? '<div class="mt-auto pt-2">' . $button . '</div>' : '' )
			. '</div></article>';
	}
}
