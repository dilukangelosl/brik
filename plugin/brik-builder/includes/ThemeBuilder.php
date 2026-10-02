<?php
namespace Brik;

defined( 'ABSPATH' ) || exit;

/**
 * Header, footer and body templates with display conditions.
 */
final class ThemeBuilder {

	const POST_TYPE  = 'brik_template';
	const META_AREA  = '_brik_area';
	const META_RULES = '_brik_conditions';

	private static $resolved = array();

	private static $printed = array();

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_filter( 'template_include', array( __CLASS__, 'template_include' ), 99 );
		add_action( 'get_header', array( __CLASS__, 'replace_header' ), 1 );
		add_action( 'get_footer', array( __CLASS__, 'replace_footer' ), 1 );
		add_filter( 'theme_templates', array( __CLASS__, 'page_templates' ), 10, 4 );
	}

	public static function areas() {
		return array(
			'header' => __( 'Header', 'brik' ),
			'body'   => __( 'Body', 'brik' ),
			'footer' => __( 'Footer', 'brik' ),
		);
	}

	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Templates', 'brik' ),
					'singular_name' => __( 'Template', 'brik' ),
					'add_new_item'  => __( 'Add template', 'brik' ),
					'edit_item'     => __( 'Edit template', 'brik' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => false,
				'show_in_rest'        => true,
				'exclude_from_search' => true,
				'capability_type'     => 'page',
				'map_meta_cap'        => true,
				'supports'            => array( 'title', 'author', 'revisions' ),
				'rewrite'             => false,
			)
		);
	}

	public static function is_template( $post_id ) {
		return self::POST_TYPE === get_post_type( $post_id );
	}

	public static function area( $post_id ) {
		$area = get_post_meta( $post_id, self::META_AREA, true );
		return isset( self::areas()[ $area ] ) ? $area : 'body';
	}

	public static function conditions( $post_id ) {
		$rules = get_post_meta( $post_id, self::META_RULES, true );
		return is_array( $rules ) ? $rules : array();
	}

	public static function save_conditions( $post_id, $rules ) {
		$clean = array();
		foreach ( (array) $rules as $rule ) {
			if ( empty( $rule['rule'] ) ) {
				continue;
			}
			$clean[] = array(
				'type'      => isset( $rule['type'] ) && 'exclude' === $rule['type'] ? 'exclude' : 'include',
				'rule'      => sanitize_key( $rule['rule'] ),
				'post_type' => isset( $rule['post_type'] ) ? sanitize_key( $rule['post_type'] ) : '',
				'taxonomy'  => isset( $rule['taxonomy'] ) ? sanitize_key( $rule['taxonomy'] ) : '',
				'ids'       => isset( $rule['ids'] ) ? array_values( array_filter( array_map( 'absint', (array) $rule['ids'] ) ) ) : array(),
			);
		}
		update_post_meta( $post_id, self::META_RULES, $clean );
		return $clean;
	}

	/**
	 * Rules the condition editor offers.
	 */
	public static function rule_types() {
		return array(
			'entire_site' => __( 'Entire site', 'brik' ),
			'front_page'  => __( 'Front page', 'brik' ),
			'blog'        => __( 'Blog (posts page)', 'brik' ),
			'singular'    => __( 'Single: post type', 'brik' ),
			'post'        => __( 'Single: specific posts or pages', 'brik' ),
			'archive'     => __( 'Archive: post type', 'brik' ),
			'term'        => __( 'Archive: taxonomy terms', 'brik' ),
			'in_term'     => __( 'Single: posts in terms', 'brik' ),
			'author'      => __( 'Author archive', 'brik' ),
			'date'        => __( 'Date archive', 'brik' ),
			'search'      => __( 'Search results', 'brik' ),
			'404'         => __( '404 page', 'brik' ),
		);
	}

	/**
	 * Score how specifically a rule matches the current request. 0 = no match.
	 */
	private static function score( array $rule ) {
		$ids = isset( $rule['ids'] ) ? (array) $rule['ids'] : array();
		switch ( $rule['rule'] ) {
			case 'entire_site':
				return 1;
			case 'front_page':
				return is_front_page() ? 40 : 0;
			case 'blog':
				return is_home() ? 40 : 0;
			case 'search':
				return is_search() ? 40 : 0;
			case '404':
				return is_404() ? 40 : 0;
			case 'author':
				return is_author() && ( ! $ids || in_array( get_queried_object_id(), $ids, true ) ) ? ( $ids ? 30 : 20 ) : 0;
			case 'date':
				return is_date() ? 20 : 0;
			case 'singular':
				if ( ! is_singular() ) {
					return 0;
				}
				return ! $rule['post_type'] || is_singular( $rule['post_type'] ) ? ( $rule['post_type'] ? 10 : 5 ) : 0;
			case 'post':
				return is_singular() && in_array( get_queried_object_id(), $ids, true ) ? 50 : 0;
			case 'in_term':
				if ( ! is_singular() || ! $rule['taxonomy'] ) {
					return 0;
				}
				return has_term( $ids ? $ids : '', $rule['taxonomy'], get_queried_object_id() ) ? 30 : 0;
			case 'archive':
				if ( ! ( is_archive() || is_home() ) ) {
					return 0;
				}
				if ( ! $rule['post_type'] ) {
					return 5;
				}
				return is_post_type_archive( $rule['post_type'] ) || ( 'post' === $rule['post_type'] && ( is_home() || is_category() || is_tag() || is_date() || is_author() ) ) ? 10 : 0;
			case 'term':
				if ( ! $rule['taxonomy'] ) {
					return 0;
				}
				$tax = $rule['taxonomy'];
				$on  = 'category' === $tax ? is_category( $ids ) : ( 'post_tag' === $tax ? is_tag( $ids ) : is_tax( $tax, $ids ) );
				return $on ? ( $ids ? 30 : 20 ) : 0;
		}
		return (int) apply_filters( 'brik/condition_score', 0, $rule );
	}

	/**
	 * Template id that applies to an area for the current request, or 0.
	 */
	public static function resolve( $area ) {
		if ( isset( self::$resolved[ $area ] ) ) {
			return self::$resolved[ $area ];
		}

		$best  = 0;
		$score = 0;
		$query = new \WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'no_found_rows'  => true,
				'fields'         => 'ids',
				'meta_key'       => self::META_AREA, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'     => $area, // phpcs:ignore WordPress.DB.SlowDBQuery
				'orderby'        => 'menu_order date',
				'order'          => 'ASC',
			)
		);

		foreach ( $query->posts as $id ) {
			$s = 0;
			foreach ( self::conditions( $id ) as $rule ) {
				$match = self::score( $rule );
				if ( $match && 'exclude' === $rule['type'] ) {
					$s = 0;
					break;
				}
				if ( 'include' === $rule['type'] ) {
					$s = max( $s, $match );
				}
			}
			if ( $s > $score ) {
				$score = $s;
				$best  = $id;
			}
		}

		$best = (int) apply_filters( 'brik/template', $best, $area );
		self::$resolved[ $area ] = $best;
		return $best;
	}

	/**
	 * Pre-render templates for this request so their CSS lands in the head.
	 */
	public static function prepare() {
		if ( is_admin() || Builder::is_canvas_template() ) {
			return;
		}
		$canvas = self::page_template() === 'brik-canvas';
		foreach ( array_keys( self::areas() ) as $area ) {
			if ( $canvas && 'body' !== $area ) {
				continue;
			}
			$id = self::resolve( $area );
			if ( $id ) {
				Frontend::render( $id );
			}
		}
	}

	/**
	 * Print a location. Returns false when no template applies, so themes can fall back.
	 */
	public static function render_location( $area ) {
		if ( Builder::is_canvas_template() ) {
			return false;
		}
		$id = self::resolve( $area );
		if ( ! $id ) {
			return false;
		}
		Frontend::enqueue();
		$result = Frontend::render( $id );
		$tag    = 'header' === $area ? 'header' : ( 'footer' === $area ? 'footer' : 'div' );
		printf(
			'<%1$s class="brik-location brik-location-%2$s" data-brik-template="%3$d">%4$s</%1$s>',
			$tag, // phpcs:ignore WordPress.Security.EscapeOutput
			esc_attr( $area ),
			(int) $id,
			$result['html'] // phpcs:ignore WordPress.Security.EscapeOutput
		);
		self::$printed[ $area ] = true;
		return true;
	}

	private static function theme_supports() {
		return current_theme_supports( 'brik' );
	}

	/* ---------------------------------------------------------------------
	 * Template loading.
	 * ------------------------------------------------------------------- */

	private static function page_template() {
		if ( ! is_singular() ) {
			return '';
		}
		$slug = get_page_template_slug( get_queried_object_id() );
		if ( 'brik-canvas.php' === $slug ) {
			return 'brik-canvas';
		}
		if ( 'brik-full-width.php' === $slug ) {
			return 'brik-full-width';
		}
		return '';
	}

	public static function page_templates( $templates, $theme, $post, $post_type ) {
		if ( Plugin::supports( $post ) || in_array( $post_type, Plugin::post_types(), true ) ) {
			$templates['brik-canvas.php']     = __( 'Brik Canvas (no header or footer)', 'brik' );
			$templates['brik-full-width.php'] = __( 'Brik Full Width', 'brik' );
		}
		return $templates;
	}

	public static function template_include( $template ) {
		if ( Builder::is_canvas_template() ) {
			return BRIK_DIR . 'templates/canvas-template.php';
		}

		$page = self::page_template();
		if ( 'brik-canvas' === $page ) {
			return BRIK_DIR . 'templates/canvas.php';
		}

		$body = self::resolve( 'body' );
		if ( $body || 'brik-full-width' === $page ) {
			// Block themes don't have classic header/footer files to hook into.
			if ( wp_is_block_theme() && ! self::theme_supports() ) {
				return BRIK_DIR . 'templates/full-page.php';
			}
			return BRIK_DIR . 'templates/body.php';
		}

		if ( wp_is_block_theme() && ! self::theme_supports() && ( self::resolve( 'header' ) || self::resolve( 'footer' ) ) ) {
			return BRIK_DIR . 'templates/full-page.php';
		}
		return $template;
	}

	/**
	 * Swap a classic theme's header.php for the Brik header template.
	 */
	public static function replace_header( $name ) {
		if ( self::theme_supports() || ! self::resolve( 'header' ) || is_admin() ) {
			return;
		}
		require BRIK_DIR . 'templates/header.php';
		self::swallow( 'header', $name );
	}

	public static function replace_footer( $name ) {
		if ( self::theme_supports() || ! self::resolve( 'footer' ) || is_admin() ) {
			return;
		}
		require BRIK_DIR . 'templates/footer.php';
		self::swallow( 'footer', $name );
	}

	/**
	 * Load the theme's own header/footer file and discard its output.
	 */
	private static function swallow( $part, $name ) {
		$templates = array();
		if ( $name ) {
			$templates[] = "{$part}-{$name}.php";
		}
		$templates[] = "{$part}.php";

		if ( 'header' === $part ) {
			remove_all_actions( 'wp_head' );
			remove_all_actions( 'wp_body_open' );
		} else {
			remove_all_actions( 'wp_footer' );
		}
		ob_start();
		locate_template( $templates, true, false );
		ob_end_clean();
	}
}
