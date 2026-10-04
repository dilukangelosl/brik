<?php
/**
 * Performance test suite.
 *
 * ./dev/wp.sh eval-file /var/www/html/wp-content/plugins/brik-builder/tests/perf/run.php
 *
 * @package Brik
 */

use Brik\Data;
use Brik\McpTools;
use Brik\Perf\Analyzer;
use Brik\Perf\Cleaner;
use Brik\Perf\Css;
use Brik\Perf\LocalFonts;
use Brik\Perf\Media;
use Brik\Perf\PageCss;
use Brik\Perf\Scripts;
use Brik\Perf\Usage;
use Brik\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Minimal assertion runner.
 */
final class Brik_Perf_Test {

	public $pass = 0;

	public $fail = 0;

	private $failures = array();

	private $section = '';

	public function section( $name ) {
		$this->section = $name;
		echo "\n== {$name} ==\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	public function ok( $condition, $name, $detail = '' ) {
		if ( $condition ) {
			++$this->pass;
			echo "  ok   {$name}\n"; // phpcs:ignore WordPress.Security.EscapeOutput
			return true;
		}
		++$this->fail;
		$this->failures[] = "[{$this->section}] {$name}" . ( $detail ? " — {$detail}" : '' );
		echo "  FAIL {$name}" . ( $detail ? " — {$detail}" : '' ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
		return false;
	}

	public function eq( $expected, $actual, $name ) {
		return $this->ok( $expected === $actual, $name, $expected === $actual ? '' : 'expected ' . wp_json_encode( $expected ) . ', got ' . substr( (string) wp_json_encode( $actual ), 0, 400 ) );
	}

	public function has( $needle, $haystack, $name ) {
		return $this->ok( false !== strpos( $haystack, $needle ), $name, 'missing ' . $needle );
	}

	public function lacks( $needle, $haystack, $name ) {
		return $this->ok( false === strpos( $haystack, $needle ), $name, 'unexpected ' . $needle );
	}

	public function skip( $name, $why ) {
		echo "  skip {$name} — {$why}\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	public function finish() {
		echo "\n" . str_repeat( '-', 60 ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
		printf( "PASS %d  FAIL %d\n", (int) $this->pass, (int) $this->fail );
		foreach ( $this->failures as $f ) {
			echo $f . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}
}

$t       = new Brik_Perf_Test();
$created = array();
wp_set_current_user( (int) ( get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] ?? 1 ) );

$settings_backup = get_option( Settings::OPTION );
Settings::update(
	array(
		'perf_assets'   => true,
		'perf_critical' => true,
		'perf_lazy'     => true,
	)
);

$make_page = static function ( $title, array $tree ) use ( &$created ) {
	$id        = wp_insert_post(
		array(
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_title'  => $title,
			'post_name'   => sanitize_title( $title . '-' . wp_generate_password( 5, false, false ) ),
		)
	);
	$created[] = $id;
	Data::save( $id, $tree );
	return $id;
};

$section = static function ( array $modules, array $attrs = array() ) {
	return array(
		'type'     => 'section',
		'attrs'    => $attrs,
		'children' => array(
			array(
				'type'     => 'row',
				'children' => array(
					array(
						'type'     => 'column',
						'children' => $modules,
					),
				),
			),
		),
	);
};

/* -------------------------------------------------------------------------
 * 1. Tree-shaker.
 * ---------------------------------------------------------------------- */

$t->section( 'Tree-shaker' );

$css = <<<'CSS'
/*! license */
:root{--v:1}
.a{color:red}
.b{color:blue}
.a .c{margin:0}
.hover\:bg-x:hover{background:red}
.\32 xl\:p-4{padding:1rem}
@media (min-width:768px){.md\:flex{display:flex}.unused{z-index:1}}
@media print{.gone{color:red}}
@media (min-width:1px){@supports (display:grid){.a{display:grid}.zz{color:red}}}
:is(.dark *,.dark) .d{color:white}
.e:is(.zz,.a){width:1px}
.f:where(.zz){width:2px}
.g:not(.zz){width:3px}
[data-brik-tabs] .t{order:1}
[data-brik-carousel]{order:2}
.a[aria-selected=true]{order:3}
.is-open .menu{order:4}
.is-open .c{order:5}
.spin{animation:brik-spin 1s linear infinite}
@keyframes brik-spin{to{transform:rotate(1turn)}}
@keyframes unused-k{to{opacity:0}}
.tx{translate:var(--tw-tx) 0}
@property --tw-tx{syntax:"*";inherits:false;initial-value:0}
@property --tw-ty{syntax:"*";inherits:false;initial-value:0}
@layer properties{*,:before,:after{--tw-tx:0;--tw-ty:0}}
.dup{color:green}
.mid{color:black}
.dup{color:green}
.x,.a,.y{outline:0}
CSS;

$html  = '<div class="a hover:bg-x 2xl:p-4 md:flex d e f g spin tx dup" data-brik-tabs><span class="c t"></span></div>';
$usage = ( new Usage() )->add_html( $html );
$out   = Css::serialize( Css::dedupe( Css::shake( Css::parse( $css ), $usage ) ) );

$t->has( ':root{--v:1}', $out, 'keeps :root tokens' );
$t->has( '.a{color:red}', $out, 'keeps used class' );
$t->lacks( '.b{', $out, 'drops unused class' );
$t->has( '.a .c{margin:0}', $out, 'keeps descendant rule when all classes used' );
$t->has( '.hover\:bg-x:hover', $out, 'decodes escaped variant classes' );
$t->has( '.\32 xl\:p-4', $out, 'decodes hex escapes (2xl:)' );
$t->has( '@media (min-width:768px){.md\:flex{display:flex}}', $out, 'filters inside @media' );
$t->lacks( '.unused', $out, 'drops unused rule inside @media' );
$t->lacks( '@media print', $out, 'drops emptied @media' );
$t->has( '@supports (display:grid){.a{display:grid}}', $out, 'filters nested @supports' );
$t->lacks( '.zz{', $out, 'drops unused rule in nested group' );
$t->has( ':is(.dark *,.dark) .d', $out, ':is() with always-on .dark' );
$t->has( '.e:is(.zz,.a)', $out, ':is() needs one alternative' );
$t->lacks( '.f:where(.zz)', $out, ':where() alternative missing drops rule' );
$t->has( '.g:not(.zz)', $out, ':not() classes do not count' );
$t->has( '[data-brik-tabs] .t', $out, 'keeps data-brik attribute present in markup' );
$t->lacks( '[data-brik-carousel]', $out, 'drops data-brik attribute absent from markup' );
$t->has( '.a[aria-selected=true]', $out, 'other attributes never narrow (set by scripts)' );
$t->lacks( '.menu', $out, 'state prefix alone does not keep unrelated classes' );
$t->has( '.is-open .c', $out, 'keeps JS state class rules (is-* safelist)' );
$t->has( '@keyframes brik-spin', $out, 'keeps referenced keyframes' );
$t->lacks( 'unused-k', $out, 'drops unreferenced keyframes' );
$t->has( '@property --tw-tx', $out, 'keeps referenced @property' );
$t->lacks( '--tw-ty', $out, 'drops unreferenced @property and its default' );
$t->eq( 1, substr_count( $out, '.dup{color:green}' ), 'dedupes identical rules' );
$t->ok( strpos( $out, '.mid{' ) < strpos( $out, '.dup{' ), 'dedupe keeps the last copy (cascade order)' );
$t->has( '.a{outline:0}', $out, 'selector lists keep only matching selectors' );

$full = (string) file_get_contents( BRIK_DIR . 'assets/build/frontend.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
$t->eq( trim( Css::strip_comments( $full ) ), Css::serialize( Css::parse( $full ) ), 'library round-trips through the parser unchanged' );

$js_usage = new Usage();
list( $tokens, $prefixes ) = Scripts::tokens( array( 'menu' ) );
$js_usage->add_tokens( $tokens )->add_prefixes( $prefixes );
$t->ok( in_array( 'aria-expanded', $tokens, true ) || $js_usage->has_class( 'is-open' ), 'manifest exposes classes/attributes scripts toggle' );

$t->eq( 'a{color:red}b{x:1}', Css::minify( "a { color : red ; }\n/* c */ b{ x:1 }" ), 'minifies whitespace and comments' );

/* -------------------------------------------------------------------------
 * 2. Fonts, media, settings.
 * ---------------------------------------------------------------------- */

$t->section( 'Fonts, media, settings' );

$faces = '';
foreach ( array( 300, 400, 700 ) as $w ) {
	$faces .= "@font-face { font-family: 'Inter'; font-style: normal; font-weight: {$w}; font-display: swap; src: url(/f/latin.woff2) format('woff2'); unicode-range: U+0000-00FF; }\n";
}
$faces .= "@font-face { font-family: 'Inter'; font-style: normal; font-weight: 400; font-display: swap; src: url(/f/greek.woff2) format('woff2'); unicode-range: U+0370-03FF; }";
$compact = LocalFonts::compact( $faces );
$t->eq( 2, substr_count( $compact, '@font-face' ), 'variable font faces merge per file' );
$t->has( 'font-weight:300 700', $compact, 'merged face gets a weight range' );
$t->has( 'font-display:swap', $compact, 'font-display swap kept' );
$t->has( 'system-ui', LocalFonts::system_stacks( '--f:"Inter",sans-serif' ), 'system mode swaps Google stacks' );

$t->eq( 'local', Settings::defaults()['fonts_mode'], 'fonts default to self-hosted' );
Settings::update( array( 'fonts_mode' => 'bogus' ) );
$t->eq( 'local', Settings::get( 'fonts_mode' ), 'invalid fonts_mode is sanitized' );
Settings::update( array( 'image_format' => 'gif' ) );
$t->eq( '', Settings::get( 'image_format' ), 'invalid image_format is sanitized' );

$supported = Media::supported();
Settings::update( array( 'image_format' => 'webp' ) );
$formats = apply_filters( 'image_editor_output_format', array(), '', 'image/jpeg' );
if ( in_array( 'webp', $supported, true ) ) {
	$t->eq( 'image/webp', isset( $formats['image/jpeg'] ) ? $formats['image/jpeg'] : '', 'uploads convert to WebP when supported' );
} else {
	$t->eq( array(), $formats, 'no conversion when the server cannot write WebP' );
}
Settings::update( array( 'image_format' => '' ) );
$t->eq( array(), apply_filters( 'image_editor_output_format', array(), '', 'image/jpeg' ), 'no conversion when switched off' );

$md = Media::process(
	'<section class="brik-n-s1"><img src="/a.jpg" loading="lazy" alt=""><img src="/b.jpg" loading="lazy" alt=""></section><section class="brik-n-s2"><img src="/c.jpg" loading="lazy" alt=""><iframe src="https://example.com/x"></iframe></section>',
	'main',
	array( 's1', 's2' )
);
$t->ok( (bool) preg_match( '/<img fetchpriority="high" src="\/a\.jpg"(?![^>]*loading)/', $md ), 'first image of the first section loads eagerly with high priority' );
$t->has( '<img src="/b.jpg" loading="lazy"', $md, 'other images stay lazy' );
$t->has( '<img src="/c.jpg" loading="lazy"', $md, 'images below the first section stay lazy' );
$t->has( '<iframe loading="lazy"', $md, 'iframes are lazy' );
$t->lacks( 'loading="lazy"', Media::process( '<img src="/logo.png" loading="lazy" alt="">', 'header' ), 'header images are never lazy' );

// <picture> with modern siblings, and dimensions for media images that lack them.
$dir  = wp_upload_dir();
$base = 'brik-perf-pic-' . wp_generate_password( 4, false, false );
$jpg  = $dir['path'] . '/' . $base . '.jpg';
if ( function_exists( 'imagecreatetruecolor' ) ) {
	$im = imagecreatetruecolor( 64, 48 );
	imagejpeg( $im, $jpg );
	imagewebp( $im, $dir['path'] . '/' . $base . '.webp' );
	imagedestroy( $im );
	Settings::update( array( 'perf_picture' => true ) );
	$pic = Media::process( '<img src="' . esc_url( $dir['url'] . '/' . $base . '.jpg' ) . '" alt="">' );
	Settings::update( array( 'perf_picture' => false ) );
	$t->has( '<picture style="display:contents"><source type="image/webp" srcset="' . $dir['url'] . '/' . $base . '.webp"', $pic, 'wraps images with a WebP sibling in <picture>' );
	$t->lacks( 'image/avif', $pic, 'no AVIF source when no AVIF file exists' );
	$t->has( 'width="64" height="48"', $pic, 'adds missing width/height from the file' );
	$t->lacks( '<picture', Media::process( '<img src="' . esc_url( $dir['url'] . '/' . $base . '.jpg' ) . '" alt="">' ), 'no <picture> when the option is off' );
	wp_delete_file( $jpg );
	wp_delete_file( $dir['path'] . '/' . $base . '.webp' );
} else {
	$t->skip( 'picture sources', 'GD not available' );
}

/* -------------------------------------------------------------------------
 * 3. Page pipeline over HTTP.
 * ---------------------------------------------------------------------- */

$t->section( 'Per-page assets' );

$tabs_page = $make_page(
	'Perf tabs',
	array(
		$section( array( array( 'type' => 'heading', 'attrs' => array( 'text' => 'Fast page', 'level' => 'h1', 'animation' => 'fade' ) ), array( 'type' => 'tabs' ) ) ),
		$section( array( array( 'type' => 'text' ) ) ),
		$section( array( array( 'type' => 'button', 'attrs' => array( 'text' => 'Below the fold' ) ) ) ),
		// Plenty below the fold, so inlining the first screen is worth it.
		$section( array( array( 'type' => 'pricing_table' ), array( 'type' => 'accordion' ) ) ),
		$section( array( array( 'type' => 'testimonial' ), array( 'type' => 'stats' ), array( 'type' => 'timeline' ) ) ),
		$section( array( array( 'type' => 'team_member' ), array( 'type' => 'cta' ), array( 'type' => 'icon_list' ), array( 'type' => 'table' ) ) ),
		$section( array( array( 'type' => 'code' ), array( 'type' => 'logo_cloud' ), array( 'type' => 'bento_grid' ), array( 'type' => 'social_follow' ) ) ),
		$section( array( array( 'type' => 'blurb' ), array( 'type' => 'card' ), array( 'type' => 'alert' ), array( 'type' => 'progress_bars' ), array( 'type' => 'star_rating' ) ) ),
		$section( array( array( 'type' => 'contact_form' ), array( 'type' => 'countdown' ), array( 'type' => 'author_box' ), array( 'type' => 'search' ) ) ),
	)
);

$fetch = static function ( $post_id ) {
	$url  = get_permalink( $post_id );
	$home = wp_parse_url( home_url(), PHP_URL_HOST ) . ( wp_parse_url( home_url(), PHP_URL_PORT ) ? ':' . wp_parse_url( home_url(), PHP_URL_PORT ) : '' );
	$path = (string) wp_parse_url( $url, PHP_URL_PATH ) . ( wp_parse_url( $url, PHP_URL_QUERY ) ? '?' . wp_parse_url( $url, PHP_URL_QUERY ) : '' );
	// The CLI container reaches the web server by service name; inside it, localhost works.
	foreach ( array( 'http://wordpress', 'http://localhost', untrailingslashit( home_url() ) ) as $base ) {
		$res = wp_remote_get(
			$base . $path,
			array(
				'timeout'   => 30,
				'headers'   => array( 'Host' => $home ),
				'sslverify' => false,
			)
		);
		if ( ! is_wp_error( $res ) && 200 === (int) wp_remote_retrieve_response_code( $res ) ) {
			return (string) wp_remote_retrieve_body( $res );
		}
	}
	return null;
};

$page_html = $fetch( $tabs_page );
if ( null === $page_html ) {
	$t->skip( 'front-end request', 'the web server is not reachable from here' );
} else {
	$t->has( 'id="brik-critical-css"', $page_html, 'critical CSS is inlined' );
	$t->ok( (bool) preg_match( '/<link rel="stylesheet" id="brik-css" href="[^"]*\/uploads\/brik\/css\/' . $tabs_page . '-[a-f0-9]{12}\.css" media="print"/', $page_html ), 'page stylesheet loads without blocking' );
	$t->has( '<noscript><link rel="stylesheet"', $page_html, 'noscript fallback for the page stylesheet' );
	$t->has( 'assets/build/frontend/core.js', $page_html, 'core script instead of the full bundle' );
	$t->lacks( 'assets/build/frontend.js', $page_html, 'full bundle not loaded' );
	$t->lacks( 'assets/build/frontend.css', $page_html, 'full library stylesheet not loaded' );
	$t->ok( (bool) preg_match( '/id=["\']brik-m-tabs-js["\']/', $page_html ), 'tabs script enqueued for the tabs module' );
	$t->lacks( 'brik-m-carousel-js', $page_html, 'carousel script not enqueued' );
	$t->lacks( 'brik-m-lightbox-js', $page_html, 'lightbox script not enqueued' );
	$t->lacks( '<style id="brik-css">', $page_html, 'element CSS no longer printed inline' );

	preg_match( '/<style id="brik-critical-css">(.*?)<\/style>/s', $page_html, $cm );
	$critical = isset( $cm[1] ) ? $cm[1] : '';
	$t->has( '.brik-section{', $critical, 'critical CSS covers the first sections' );
	$t->has( '.brik-anim-ready', $critical, 'critical CSS keeps classes scripts add (entrance animations), so nothing jumps when the page stylesheet lands' );
	$t->lacks( '.brik-progress-bar', $critical, 'critical CSS leaves out what is far below the fold' );
	$t->lacks( '.brik-accordion', $critical, 'critical CSS leaves out below-the-fold modules' );
}

$items = Analyzer::render( $tabs_page );
$first = PageCss::build( $items, array( 'key' => 'test-' . $tabs_page ) );
$t->ok( $first && is_readable( $first['path'] ), 'page stylesheet written to uploads/brik/css' );
$t->ok( $first && false !== strpos( $first['url'], '/uploads/brik/css/test-' . $tabs_page . '-' ), 'file named {key}-{hash}.css' );
$full_bytes = strlen( $full ) + strlen( Settings::css() );
$t->ok( $first && strlen( $first['css'] ) < $full_bytes / 3, 'page stylesheet is far smaller than the library', $first ? strlen( $first['css'] ) . ' vs ' . $full_bytes : '' );
$t->has( '.brik-n-', $first ? $first['css'] : '', 'element CSS is inside the file' );
$t->has( '--background:', $first ? $first['css'] : '', 'design tokens are inside the file' );

$mtime = $first ? filemtime( $first['path'] ) : 0;
$again = PageCss::build( $items, array( 'key' => 'test-' . $tabs_page ) );
$t->ok( $again && $first && $again['hash'] === $first['hash'] && filemtime( $again['path'] ) === $mtime, 'second build is served from cache' );

// Saving a change produces a new file (new content hash).
$tree = Data::get( $tabs_page );
$tree[0]['children'][0]['children'][0]['children'][0]['attrs']['title_text_color'] = '#ff0000';
$tree[0]['attrs']['bg_color'] = '#123456';
Data::save( $tabs_page, $tree );
$changed = PageCss::build( Analyzer::render( $tabs_page ), array( 'key' => 'test-' . $tabs_page ) );
$t->ok( $changed && $first && $changed['hash'] !== $first['hash'], 'saving the page invalidates the cached file' );
$t->has( '#123456', $changed ? $changed['css'] : '', 'new file has the new styles' );

// Design settings changes reach the file too.
$radius = Settings::get( 'radius' );
Settings::update( array( 'radius' => '0.3rem' ) );
$restyled = PageCss::build( Analyzer::render( $tabs_page ), array( 'key' => 'test-' . $tabs_page ) );
$t->ok( $restyled && $changed && $restyled['hash'] !== $changed['hash'] && false !== strpos( $restyled['css'], '--radius:0.3rem' ), 'design settings change invalidates the file' );
Settings::update( array( 'radius' => $radius ) );

$t->ok( PageCss::purge( 'test-' . $tabs_page, 0 ) >= 2, 'purge removes cached files' );
$t->ok( ! $first || ! file_exists( $first['path'] ), 'purged file is gone' );

$t->eq( array( 'tabs' ), Scripts::needed( '<div data-brik-tabs></div>' ), 'scripts chosen from markup' );
$t->eq( array( 'woo-product', 'woo-shop' ), Scripts::needed( '<div data-brik-mini-cart></div>' ), 'companion scripts follow' );
$t->eq( array(), Scripts::needed( '<p class="brik-text">x</p>' ), 'static content needs no module script' );

/* -------------------------------------------------------------------------
 * 4. Analyzer.
 * ---------------------------------------------------------------------- */

$t->section( 'Analyzer' );

$report = Analyzer::report( $tabs_page );
$t->ok( ! is_wp_error( $report ), 'report builds' );
if ( ! is_wp_error( $report ) ) {
	$t->ok( $report['score'] >= 0 && $report['score'] <= 100, 'score is 0-100', (string) $report['score'] );
	$weights = 0;
	foreach ( $report['weights'] as $w ) {
		$weights += $w;
	}
	$t->eq( 100, $weights, 'score weights add up to 100' );
	$types = wp_list_pluck( $report['modules']['list'], 'count', 'type' );
	$t->eq( 1, isset( $types['tabs'] ) ? $types['tabs'] : 0, 'counts modules by type' );
	$tabs_js = array_values( array_filter( $report['js']['scripts'], static function ( $s ) { return 'tabs' === $s['name']; } ) );
	$t->ok( $tabs_js && $tabs_js[0]['bytes'] === Scripts::bytes( 'tabs' ) && $tabs_js[0]['bytes'] > 0, 'reports script size from the build' );
	$t->ok( $tabs_js && ! empty( $tabs_js[0]['nodes'] ) && 'tabs' === $tabs_js[0]['nodes'][0]['type'], 'attributes the script to the tabs element' );
	$t->ok( $report['css']['optimized'] < $report['css']['full'], 'optimized CSS smaller than full' );
	$t->ok( $report['css']['optimized_gzip'] > 0 && $report['css']['optimized_gzip'] < $report['css']['optimized'], 'gzip estimate is sane' );
	$main_html = Analyzer::render( $tabs_page )[0]['html'];
	$t->ok( $report['dom']['elements'] >= preg_match_all( '/<[a-z][a-z0-9-]*[\s>\/]/i', $main_html ), 'element count includes the page markup' );
	$t->ok( $report['js']['total'] < $report['js']['full'], 'optimized JS smaller than the full bundle' );
	$t->ok( is_array( $report['findings'] ), 'findings listed' );
	$t->ok( is_string( $report['summary'] ) && '' !== $report['summary'], 'plain-language summary' );
}

// Oversized media-library image.
$upload = wp_upload_bits( 'brik-perf-big.jpg', null, '' );
$img_id = 0;
if ( empty( $upload['error'] ) && function_exists( 'imagecreatetruecolor' ) ) {
	$im = imagecreatetruecolor( 2400, 1600 );
	for ( $i = 0; $i < 4000; $i++ ) {
		imagefilledellipse( $im, wp_rand( 0, 2400 ), wp_rand( 0, 1600 ), wp_rand( 10, 200 ), wp_rand( 10, 200 ), imagecolorallocate( $im, wp_rand( 0, 255 ), wp_rand( 0, 255 ), wp_rand( 0, 255 ) ) );
	}
	imagejpeg( $im, $upload['file'], 95 );
	imagedestroy( $im );
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$img_id = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/jpeg',
			'post_title'     => 'Perf big',
			'post_status'    => 'inherit',
		),
		$upload['file']
	);
	wp_update_attachment_metadata( $img_id, wp_generate_attachment_metadata( $img_id, $upload['file'] ) );
}

/* -------------------------------------------------------------------------
 * 5. Cleaner.
 * ---------------------------------------------------------------------- */

$t->section( 'Cleaner' );

$dirty = array(
	array(
		'id'       => 'sec1',
		'type'     => 'section',
		'children' => array(
			array(
				'id'       => 'row1',
				'type'     => 'row',
				'attrs'    => array( 'columns' => '1/2,1/2' ),
				'children' => array(
					array(
						'id'       => 'col1',
						'type'     => 'column',
						'children' => array(
							array(
								'id'       => 'nrow',
								'type'     => 'row',
								'attrs'    => array( 'columns' => '1' ),
								'children' => array(
									array(
										'id'       => 'ncol',
										'type'     => 'column',
										'children' => array(
											array( 'id' => 'headkept', 'type' => 'heading', 'attrs' => array( 'text' => 'Kept', 'level' => 'h2' ) ),
										),
									),
								),
							),
							array( 'id' => 'hempty', 'type' => 'heading', 'attrs' => array( 'text' => '  ' ) ),
							array( 'id' => 'temp', 'type' => 'text', 'attrs' => array( 'content' => '<p>&nbsp;</p>' ) ),
							array( 'id' => 'div1', 'type' => 'divider' ),
							array( 'id' => 'div2', 'type' => 'divider' ),
							array( 'id' => 'hiddenbtn', 'type' => 'button', 'attrs' => array( 'text' => 'HiddenEverywhere', 'hide_on' => array( 'desktop', 'tablet', 'mobile' ) ) ),
							array( 'id' => 'css1', 'type' => 'button', 'attrs' => array( 'text' => 'Go', 'custom_css' => " \n " ) ),
						),
					),
					array(
						'id'       => 'col2',
						'type'     => 'column',
						'children' => array(),
					),
				),
			),
		),
	),
	array(
		'id'       => 'sec2',
		'type'     => 'section',
		'children' => array(
			array(
				'id'       => 'row2',
				'type'     => 'row',
				'children' => array(
					array(
						'id'       => 'col3',
						'type'     => 'column',
						'children' => array(),
					),
				),
			),
		),
	),
);
if ( $img_id ) {
	$dirty[0]['children'][0]['children'][0]['children'][] = array(
		'id'    => 'img1',
		'type'  => 'image',
		'attrs' => array(
			'image' => array( 'id' => $img_id ),
			'size'  => 'full',
		),
	);
}

$cleaner             = new Cleaner();
list( $clean, $fix ) = $cleaner->clean( Data::normalize( $dirty ) );
$kinds               = array_count_values( wp_list_pluck( $fix, 'kind' ) );
$ids                 = wp_list_pluck( Data::flatten( $clean ), 'id' );

$t->ok( ! in_array( 'nrow', $ids, true ) && ! in_array( 'ncol', $ids, true ) && in_array( 'headkept', $ids, true ), 'unwraps nested bare one-column row' );
$col1 = Data::find( $clean, 'col1' );
$t->eq( 'headkept', $col1 ? $col1['children'][0]['id'] : null, 'unwrapped content keeps its place' );
$t->ok( ! in_array( 'hempty', $ids, true ) && ! in_array( 'temp', $ids, true ), 'removes empty heading and text' );
$t->ok( in_array( 'div1', $ids, true ) && ! in_array( 'div2', $ids, true ), 'removes duplicate neighbour' );
$t->ok( ! in_array( 'hiddenbtn', $ids, true ), 'removes element hidden everywhere' );
$css_node = Data::find( $clean, 'css1' );
$t->ok( $css_node && ! isset( $css_node['attrs']['custom_css'] ), 'strips empty custom CSS' );
$h1 = Data::find( $clean, 'headkept' );
$t->ok( $h1 && ! isset( $h1['attrs']['level'] ) && 'Kept' === $h1['attrs']['text'], 'strips attrs equal to defaults, keeps the rest' );
$t->ok( in_array( 'col2', $ids, true ), 'keeps empty column next to others (grid track)' );
$t->ok( ! in_array( 'sec2', $ids, true ), 'removes empty section' );
if ( $img_id ) {
	$img = Data::find( $clean, 'img1' );
	$t->ok( $img && 'full' !== $img['attrs']['size'], 'oversized image switched to a smaller size', $img ? $img['attrs']['size'] : '' );
} else {
	$t->skip( 'image size', 'GD not available' );
}
$t->ok( ! empty( $kinds['wrapper'] ) && ! empty( $kinds['defaults'] ) && ! empty( $kinds['empty_structure'] ), 'reports fix kinds' );

list( $twice, $fix2 ) = ( new Cleaner() )->clean( $clean );
$t->eq( array(), $fix2, 'cleaning is idempotent (no further fixes)' );
$t->eq( wp_json_encode( $clean ), wp_json_encode( $twice ), 'cleaning is idempotent (same tree)' );

// Visible output: the cleaned tree renders the same text in the same order.
$text = static function ( array $tree ) {
	$r    = new Brik\Renderer( 0 );
	$text = html_entity_decode( wp_strip_all_tags( $r->render_root( $tree ) ), ENT_QUOTES, 'UTF-8' );
	// The hidden button was never visible.
	$text = str_replace( array( 'HiddenEverywhere', "\xc2\xa0" ), ' ', $text );
	return preg_replace( '/\s+/', '', $text );
};
$t->eq( $text( Data::normalize( $dirty ) ), $text( $clean ), 'cleaned page shows the same text' );

$only = new Cleaner( array( 'defaults' ) );
list( $partial ) = $only->clean( Data::normalize( $dirty ) );
$t->ok( null !== Data::find( $partial, 'hempty' ), 'fix kinds can be limited' );

// Page-level run with savings, then apply.
$clean_page = $make_page( 'Perf clean', $dirty );
$dry        = Cleaner::run( $clean_page );
$t->ok( $dry['changed'] && ! $dry['applied'], 'dry run reports without saving' );
$t->ok( $dry['savings']['dom'] > 0, 'dry run estimates DOM savings', (string) $dry['savings']['dom'] );
$t->ok( $dry['savings']['css'] >= 0 && $dry['savings']['js'] >= 0, 'dry run estimates CSS/JS savings' );
$t->ok( null !== Data::find( Data::get( $clean_page ), 'hempty' ), 'saved tree untouched by dry run' );
$applied = Cleaner::run( $clean_page, null, true );
$t->ok( $applied['applied'] && null === Data::find( Data::get( $clean_page ), 'hempty' ), 'apply saves the cleaned tree' );
$t->ok( ! Cleaner::run( $clean_page )['changed'], 'nothing left to clean after applying' );

/* -------------------------------------------------------------------------
 * 6. REST and MCP.
 * ---------------------------------------------------------------------- */

$t->section( 'REST and MCP' );

$routes = rest_get_server()->get_routes();
$t->ok( isset( $routes['/brik/v1/perf/(?P<id>\d+)'] ) && isset( $routes['/brik/v1/perf/(?P<id>\d+)/clean'] ), 'perf routes registered' );

$res = rest_do_request( new WP_REST_Request( 'GET', '/brik/v1/perf/' . $tabs_page ) );
$t->eq( 200, $res->get_status(), 'GET perf report' );
$t->ok( isset( $res->get_data()['score'] ), 'report has a score' );

$req = new WP_REST_Request( 'POST', '/brik/v1/perf/' . $tabs_page . '/clean' );
$req->set_header( 'content-type', 'application/json' );
$req->set_body( wp_json_encode( array( 'dry_run' => true ) ) );
$res = rest_do_request( $req );
$t->eq( 200, $res->get_status(), 'POST clean (dry run)' );
$t->ok( isset( $res->get_data()['savings'] ) && false === $res->get_data()['applied'], 'clean response has savings and was not applied' );

$uid = wp_insert_user(
	array(
		'user_login' => 'perf_sub_' . wp_generate_password( 5, false, false ),
		'user_pass'  => wp_generate_password(),
		'role'       => 'subscriber',
	)
);
$admin = get_current_user_id();
wp_set_current_user( $uid );
$res = rest_do_request( new WP_REST_Request( 'GET', '/brik/v1/perf/' . $tabs_page ) );
$t->ok( in_array( $res->get_status(), array( 401, 403 ), true ), 'subscribers cannot read reports' );
wp_set_current_user( $admin );
wp_delete_user( $uid );

$t->ok( McpTools::exists( 'performance_report' ) && McpTools::exists( 'clean_page' ), 'MCP tools registered' );
list( $mcp ) = McpTools::call( 'performance_report', array( 'post_id' => $tabs_page ) );
$t->ok( is_array( $mcp ) && isset( $mcp['score'] ), 'performance_report returns a report' );
list( $mcp, $warnings ) = McpTools::call( 'clean_page', array( 'post_id' => $tabs_page ) );
$t->ok( is_array( $mcp ) && isset( $mcp['savings'] ) && ! isset( $mcp['tree'] ), 'clean_page returns compact results' );
$t->has( '## Performance', McpTools::get_guide(), 'MCP guide mentions performance tools' );

/* -------------------------------------------------------------------------
 * Cleanup.
 * ---------------------------------------------------------------------- */

foreach ( $created as $id ) {
	PageCss::purge( (string) $id, 0 );
	wp_delete_post( $id, true );
}
if ( $img_id ) {
	wp_delete_attachment( $img_id, true );
}
update_option( Settings::OPTION, $settings_backup );

$t->finish();
if ( $t->fail ) {
	exit( 1 );
}
