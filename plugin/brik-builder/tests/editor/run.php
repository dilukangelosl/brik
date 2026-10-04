<?php
/**
 * Editor tools test suite. Runs inside WordPress through WP-CLI:
 *
 *   ./dev/wp.sh eval-file /var/www/html/wp-content/plugins/brik-builder/tests/editor/run.php
 *
 * Creates pages titled "BET …" and removes them at the end.
 *
 * @package Brik
 * @author  Diluk Angelo
 */

use Brik\Data;
use Brik\Editor\Css;
use Brik\Editor\Export;
use Brik\Editor\Fluid;
use Brik\Renderer;

defined( 'ABSPATH' ) || exit;

final class Brik_Editor_Test_Runner {

	private $pass = 0;

	private $fail = 0;

	private $failures = array();

	private $section = '';

	public function section( $name ) {
		$this->section = $name;
		echo "\n== " . $name . " ==\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	public function ok( $condition, $name, $detail = '' ) {
		if ( $condition ) {
			++$this->pass;
			echo '  PASS ' . $name . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
			return true;
		}
		++$this->fail;
		$line             = '  FAIL ' . $name . ( '' !== $detail ? ' — ' . $detail : '' );
		$this->failures[] = '[' . $this->section . '] ' . trim( $line );
		echo $line . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
		return false;
	}

	public function eq( $expected, $actual, $name ) {
		$same = $expected === $actual || ( is_numeric( $expected ) && is_numeric( $actual ) && abs( $expected - $actual ) < 0.011 );
		return $this->ok( $same, $name, $same ? '' : 'expected ' . wp_json_encode( $expected ) . ', got ' . wp_json_encode( $actual ) );
	}

	public function finish() {
		echo "\n" . str_repeat( '-', 60 ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
		printf( "PASS %d  FAIL %d\n", (int) $this->pass, (int) $this->fail );
		foreach ( $this->failures as $f ) {
			echo $f . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		exit( $this->fail ? 1 : 0 );
	}
}

$t = new Brik_Editor_Test_Runner();

$admins = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ID',
	)
);
wp_set_current_user( (int) $admins[0] );

foreach ( get_posts( array( 'post_type' => 'page', 'post_status' => 'any', 'title' => 'BET export', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $old ) {
	wp_delete_post( $old, true );
}

/* -------------------------------------------------------------------------
 * Fluid clamp math.
 * ---------------------------------------------------------------------- */

$t->section( 'Fluid values' );

$c = Fluid::clamp( 30, 48 );
$t->eq( 'clamp(30px, calc(23.3143px + 1.7143vw), 48px)', $c, 'clamp(30 → 48) formula' );
$p = Fluid::parse( $c );
$t->eq( 30.0, $p[0], 'value at 390px is the min' );
$t->eq( 48.0, $p[1], 'value at 1440px is the max' );

// Midpoint (915px) is exactly halfway for a linear ramp.
preg_match( '/calc\(([\d.-]+)px \+ ([\d.-]+)vw\)/', $c, $m );
$t->eq( 39.0, round( (float) $m[1] + (float) $m[2] * 915 / 100, 2 ), 'linear at the midpoint' );

$down = Fluid::clamp( 64, 32 );
$t->ok( false !== strpos( $down, ' - ' ), 'shrinking values use a negative slope', $down );
$pd = Fluid::parse( $down );
$t->eq( 64.0, $pd[0], 'negative slope: 64px at 390px' );
$t->eq( 32.0, $pd[1], 'negative slope: 32px at 1440px' );
$t->eq( '24px', Fluid::clamp( 24, 24 ), 'equal values give a plain length' );
$t->eq( 32.0, Fluid::to_px( '2rem' ), 'rem converts to px' );
$t->ok( null === Fluid::to_px( '50%' ), 'percent is not convertible' );
$t->ok( null === Fluid::parse( 'clamp(1rem, 2vw, 3rem)' ), 'foreign clamp() is not treated as ours' );

/* -------------------------------------------------------------------------
 * Forced hover state in the canvas.
 * ---------------------------------------------------------------------- */

$t->section( 'Hover forcing' );

$hover_tree = array(
	array(
		'id'    => 'bethov1',
		'type'  => 'button',
		'attrs' => array(
			'bg_color'       => '#111111',
			'bg_color@hover' => '#ff0000',
		),
	),
);
$canvas = new Renderer( 0, true );
$canvas->render_root( Data::normalize( $hover_tree ) );
$canvas_css = $canvas->style->css();
$front      = new Renderer( 0, false );
$front->render_root( Data::normalize( $hover_tree ) );
$front_css = $front->style->css();

$t->ok( false !== strpos( $canvas_css, '.brik-n-bethov1.bk-force-hover' ), 'canvas CSS has the forced hover selector', $canvas_css );
$t->ok( false !== strpos( $canvas_css, '.brik-n-bethov1:hover' ), 'canvas CSS keeps the real :hover selector' );
$t->ok( false === strpos( $front_css, 'bk-force-hover' ), 'front-end CSS has no forcing class' );
$t->ok( false !== strpos( $front_css, '.brik-n-bethov1:hover' ), 'front-end CSS has :hover' );

/* -------------------------------------------------------------------------
 * Page used for conversion.
 * ---------------------------------------------------------------------- */

$images = get_posts(
	array(
		'post_type'      => 'attachment',
		'post_mime_type' => 'image',
		'posts_per_page' => 3,
		'fields'         => 'ids',
	)
);
$img = $images ? array(
	'id'  => (int) $images[0],
	'url' => wp_get_attachment_url( $images[0] ),
) : array( 'url' => 'https://picsum.photos/800/500' );

$col = static function ( array $children, $id ) {
	return array(
		'id'       => $id,
		'type'     => 'column',
		'children' => $children,
	);
};

$tree = array(
	array(
		'id'       => 'betsec1',
		'type'     => 'section',
		'attrs'    => array(
			'bg_color'       => 'var(--muted)',
			'padding'        => '96px 24px',
			'padding@mobile' => '48px 16px',
		),
		'children' => array(
			array(
				'id'       => 'betrow1',
				'type'     => 'row',
				'attrs'    => array( 'columns' => '1/3,2/3' ),
				'children' => array(
					$col(
						array(
							array(
								'id'    => 'bethead',
								'type'  => 'heading',
								'attrs' => array(
									'text'       => 'Hello <em>world</em>',
									'level'      => 'h1',
									'text_align' => 'center',
								),
							),
							array(
								'id'    => 'bettext',
								'type'  => 'text',
								'attrs' => array( 'content' => "<p>First <strong>para</strong>.</p>\n<h3>Sub</h3><ul><li>One</li><li>Two<ul><li>Nested</li></ul></li></ul><ol><li>A</li></ol><blockquote><p>Quote</p><cite>Someone</cite></blockquote><p>Last</p>" ),
							),
						),
						'betcol1'
					),
					$col(
						array(
							array(
								'id'    => 'betbtn1',
								'type'  => 'button',
								'attrs' => array(
									'text' => 'Go',
									'link' => array(
										'url'     => 'https://example.com',
										'new_tab' => true,
									),
								),
							),
							array(
								'id'    => 'betbgrp',
								'type'  => 'button_group',
								'attrs' => array(
									'buttons' => array(
										array(
											'text'    => 'Primary',
											'link'    => array( 'url' => '#a' ),
											'variant' => 'default',
										),
										array(
											'text'    => 'Outline',
											'link'    => array( 'url' => '#b' ),
											'variant' => 'outline',
										),
									),
								),
							),
							array(
								'id'    => 'betimg1',
								'type'  => 'image',
								'attrs' => array(
									'image'   => $img,
									'alt'     => 'An image',
									'caption' => 'Caption',
								),
							),
							array(
								'id'    => 'betgal1',
								'type'  => 'gallery',
								'attrs' => array(
									'images'  => array( $img, $img ),
									'columns' => 2,
								),
							),
						),
						'betcol2'
					),
				),
			),
			array(
				'id'       => 'betrow2',
				'type'     => 'row',
				'children' => array(
					$col(
						array(
							array(
								'id'    => 'betvid1',
								'type'  => 'video',
								'attrs' => array( 'url' => 'https://www.youtube.com/watch?v=aqz-KE-bpKQ' ),
							),
							array(
								'id'    => 'betvid2',
								'type'  => 'video',
								'attrs' => array(
									'source' => 'file',
									'file'   => array( 'url' => 'https://example.com/clip.mp4' ),
								),
							),
							array(
								'id'   => 'betdiv1',
								'type' => 'divider',
							),
							array(
								'id'    => 'betspc1',
								'type'  => 'spacer',
								'attrs' => array( 'space' => '40px' ),
							),
							array(
								'id'    => 'betacc1',
								'type'  => 'accordion',
								'attrs' => array(
									'first_open' => true,
									'items'      => array(
										array(
											'title'   => 'Question one',
											'content' => '<p>Answer one</p>',
										),
										array(
											'title'   => 'Question two',
											'content' => 'Answer two',
										),
									),
								),
							),
							array(
								'id'    => 'betcode',
								'type'  => 'code',
								'attrs' => array( 'code' => '<div class="custom-embed">Raw</div>' ),
							),
							array(
								'id'    => 'betbadg',
								'type'  => 'badge',
								'attrs' => array( 'text' => 'Fallback badge' ),
							),
						),
						'betcol3'
					),
				),
			),
		),
	),
);

$page_id = wp_insert_post(
	array(
		'post_type'   => 'page',
		'post_status' => 'publish',
		'post_title'  => 'BET export',
	)
);
Data::save( $page_id, $tree );
$saved_tree = Data::get( $page_id );

/* -------------------------------------------------------------------------
 * Block conversion.
 * ---------------------------------------------------------------------- */

$t->section( 'Blocks conversion' );

$built = Export::build( $page_id, 'blocks' );
$t->ok( ! is_wp_error( $built ), 'build succeeds', is_wp_error( $built ) ? $built->get_error_message() : '' );
$content = $built['content'];

$parsed = parse_blocks( $content );
$t->eq( $content, serialize_blocks( $parsed ), 'parse_blocks / serialize_blocks round trip' );

$names    = array();
$registry = WP_Block_Type_Registry::get_instance();
$unknown  = array();
$freeform = 0;
$walk     = static function ( $blocks ) use ( &$walk, &$names, &$unknown, &$freeform, $registry ) {
	foreach ( $blocks as $b ) {
		if ( null === $b['blockName'] ) {
			if ( '' !== trim( $b['innerHTML'] ) ) {
				++$freeform;
			}
			continue;
		}
		$names[ $b['blockName'] ] = true;
		if ( ! $registry->is_registered( $b['blockName'] ) ) {
			$unknown[] = $b['blockName'];
		}
		$walk( $b['innerBlocks'] );
	}
};
$walk( $parsed );
$t->ok( ! $unknown, 'every block type is registered', implode( ', ', $unknown ) );
$t->eq( 0, $freeform, 'no stray HTML outside blocks' );

$expect = array(
	'section → group'           => 'core/group',
	'row → columns'             => 'core/columns',
	'column → column'           => 'core/column',
	'heading → heading'         => 'core/heading',
	'text → paragraph'          => 'core/paragraph',
	'text → list'               => 'core/list',
	'text → list item'          => 'core/list-item',
	'text → quote'              => 'core/quote',
	'button → buttons'          => 'core/buttons',
	'button → button'           => 'core/button',
	'image → image'             => 'core/image',
	'gallery → gallery'         => 'core/gallery',
	'video (YouTube) → embed'   => 'core/embed',
	'video (file) → video'      => 'core/video',
	'divider → separator'       => 'core/separator',
	'spacer → spacer'           => 'core/spacer',
	'accordion → details'       => 'core/details',
	'code / fallback → html'    => 'core/html',
);
foreach ( $expect as $label => $block ) {
	$t->ok( isset( $names[ $block ] ), $label );
}

$t->ok( false !== strpos( $content, '<h1 class="wp-block-heading has-text-align-center brik-n-bethead">Hello <em>world</em></h1>' ), 'heading markup keeps level, alignment and inline HTML' );
$t->ok( false !== strpos( $content, 'style="flex-basis:33.33%"' ) && false !== strpos( $content, '"width":"66.67%"' ), 'column widths from the 1/3,2/3 structure' );
$t->ok( false !== strpos( $content, '<li>Two<!-- wp:list' ), 'nested list stays inside its list item' );
$t->ok( false !== strpos( $content, '<cite>Someone</cite></blockquote>' ), 'quote keeps its citation' );
$t->ok( false !== strpos( $content, 'target="_blank" rel="noreferrer noopener"' ), 'new-tab button gets target and rel' );
$t->ok( false !== strpos( $content, 'is-style-outline' ), 'outline variant uses the outline block style' );
$t->ok( false !== strpos( $content, '<details class="wp-block-details" open><summary>Question one</summary>' ), 'first accordion item open' );
$t->ok( false !== strpos( $content, '<div class="custom-embed">Raw</div>' ), 'code module content kept as HTML' );
$t->ok( 1 === $built['stats']['fallback'] && isset( $built['stats']['types']['badge'] ), 'unmapped badge falls back to HTML', wp_json_encode( $built['stats'] ) );
$t->ok( false !== strpos( $content, 'Fallback badge' ), 'fallback keeps rendered markup' );
$t->ok( ! preg_match( '/data-brik-(id|type|root)=/', $content ), 'no builder attributes in the output' );
$t->ok( false === strpos( $content, '"background":"var(' ) && false !== strpos( $content, '"background":"oklch' ), 'section colour token resolved for the block attribute' );
$t->ok( 0 === strpos( $content, '<!-- wp:html -->' ) && false !== strpos( $content, '<style id="brik-export-css">' ), 'CSS block first' );
$t->ok( false !== strpos( $built['css'], ':root .brik-n-betsec1' ) && false !== strpos( $built['css'], '@media (max-width:767px)' ), 'element CSS with responsive rules re-scoped to the blocks' );
$t->ok( false !== strpos( $built['css'], '--primary:' ), 'design tokens included' );

// The padding is responsive, so it's left to the CSS instead of an inline style that would win.
$t->ok( false === strpos( $content, 'padding-top:96px' ), 'responsive padding not inlined' );

/* -------------------------------------------------------------------------
 * Static export.
 * ---------------------------------------------------------------------- */

$t->section( 'Static export' );

$static = Export::build( $page_id, 'static' );
$sc     = $static['content'];
$sp     = parse_blocks( $sc );
$t->ok( 1 === count( array_filter( $sp, static function ( $b ) { return null !== $b['blockName']; } ) ) && 'core/html' === $sp[0]['blockName'], 'one Custom HTML block' );
$t->ok( false !== strpos( $sc, '<style id="brik-export-css">' ), 'contains a style element' );
$t->ok( false !== strpos( $sc, '.brik .brik-n-betsec1' ), 'contains element CSS' );
$t->ok( (bool) preg_match( '/\.flex\{display:flex/', $sc ), 'contains framework utilities used by the markup' );
$t->ok( false === strpos( $sc, '.hover\:scale-' ) || false !== strpos( $sc, 'hover:scale-' ), 'unused utilities dropped' );
$t->ok( strlen( $static['css'] ) < 200000, 'CSS is purged (' . strlen( $static['css'] ) . ' bytes)' );
$t->ok( ! preg_match( '/data-brik-(id|type|root)=/', $sc ), 'no builder attributes' );
$t->ok( false !== strpos( $sc, 'Fallback badge' ) && false !== strpos( $sc, 'Question one' ), 'all content present' );

$t->section( 'CSS purge' );
$purged = Css::purge( '.a{color:red}.b .c{color:blue}@media (min-width:1px){.a:hover{x:1}.z{x:2}}@keyframes spin{to{a:b}}@keyframes nope{to{a:b}}.d{animation:spin 1s}.e:not(.missing){y:1}', array( 'a' => true, 'd' => true, 'e' => true ) );
$t->eq( '.a{color:red}@media (min-width:1px){.a:hover{x:1}}.d{animation:spin 1s}.e:not(.missing){y:1}@keyframes spin{to{a:b}}', $purged, 'keeps used rules, media and keyframes only' );

/* -------------------------------------------------------------------------
 * Convert and restore.
 * ---------------------------------------------------------------------- */

$escaped = Css::purge( ".content-\\[\\'x\\'\\]{content:'x'}.keep{c:d}.md\\:flex{display:flex}", array( 'keep' => true, 'md:flex' => true ) );
$t->eq( '.keep{c:d}.md\\:flex{display:flex}', $escaped, 'escaped quotes and colons in selectors are parsed' );

$t->eq( array( 'size-[var(--brik-icon-size,1.25rem)]' ), Css::classes_in( '.size-\\[var\\(--brik-icon-size\\,1\\.25rem\\)\\] a[href="x"]' ), 'arbitrary-value classes survive attribute stripping' );

$t->section( 'MCP tools' );

$t->ok( Brik\McpTools::exists( 'convert_page' ) && Brik\McpTools::exists( 'restore_brik_page' ) && Brik\McpTools::exists( 'make_fluid' ), 'tools registered' );
list( $dry ) = Brik\McpTools::call( 'convert_page', array( 'post_id' => $page_id, 'dry_run' => true ) );
$t->ok( is_array( $dry ) && isset( $dry['stats']['mapped'] ) && Data::enabled( $page_id ), 'convert_page dry run previews without converting', is_wp_error( $dry ) ? $dry->get_error_message() : '' );
list( $fl ) = Brik\McpTools::call(
	'make_fluid',
	array(
		'post_id' => $page_id,
		'node_id' => 'bethead',
		'key'     => 'font_size',
		'min_px'  => 30,
		'max_px'  => 48,
	)
);
$t->eq( 'clamp(30px, calc(23.3143px + 1.7143vw), 48px)', is_array( $fl ) ? $fl['value'] : null, 'make_fluid writes the clamp' );
$after = Data::get( $page_id );
$head  = Data::find( $after, 'bethead' );
$t->eq( 'clamp(30px, calc(23.3143px + 1.7143vw), 48px)', $head['attrs']['font_size'], 'make_fluid saved on the node' );
list( $bad ) = Brik\McpTools::call( 'make_fluid', array( 'post_id' => $page_id, 'node_id' => 'bethead', 'key' => 'text' ) );
$t->ok( is_wp_error( $bad ), 'make_fluid rejects non-length attributes' );
$t->ok( false !== strpos( Brik\McpTools::get_guide(), 'convert_page' ), 'guide mentions the tools' );
Data::save( $page_id, $saved_tree );
$saved_tree = Data::get( $page_id );

$t->section( 'Convert and restore' );

$result = Export::convert( $page_id, 'blocks' );
$t->ok( ! is_wp_error( $result ) && ! empty( $result['converted'] ), 'convert succeeds' );
clean_post_cache( $page_id );
$t->ok( ! Data::enabled( $page_id ), 'Brik disabled for the page' );
$t->eq( '0', (string) get_post_meta( $page_id, Data::META_ENABLED, true ), '_brik_enabled = 0' );
$t->ok( has_blocks( get_post( $page_id )->post_content ), 'post content is block markup' );
$t->ok( (bool) Export::backup( $page_id ), 'backup stored' );

$rendered = apply_filters( 'the_content', get_post( $page_id )->post_content );
$t->ok( false !== strpos( $rendered, 'Hello <em>world</em>' ) && false === strpos( $rendered, 'data-brik-id' ), 'content renders through the_content' );

$restored = Export::restore( $page_id );
clean_post_cache( $page_id );
$t->ok( ! is_wp_error( $restored ) && Data::enabled( $page_id ), 're-enabled after restore' );
$t->ok( Data::get( $page_id ) === $saved_tree, 'Brik tree restored exactly' );
$t->ok( false !== strpos( get_post( $page_id )->post_content, '<!-- brik -->' ), 'static Brik copy rewritten' );
$t->ok( ! Export::backup( $page_id ), 'backup cleared' );
$t->ok( is_wp_error( Export::restore( $page_id ) ), 'second restore reports no backup' );

wp_delete_post( $page_id, true );
$t->finish();
