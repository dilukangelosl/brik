<?php
/**
 * Audit tests. Run inside WordPress:
 *   ./dev/wp.sh eval-file /var/www/html/wp-content/plugins/brik-builder/tests/audit/run.php
 *
 * Prints PASS/FAIL per assertion and exits non-zero when anything fails.
 *
 * @package Brik
 */

use Brik\Audit\Color;
use Brik\Audit\Fixer;
use Brik\Audit\Links;
use Brik\Audit\Scanner;
use Brik\Data;
use Brik\McpTools;
use Brik\Renderer;

defined( 'ABSPATH' ) || exit;

$GLOBALS['brik_audit_failures'] = 0;
$GLOBALS['brik_audit_passes']   = 0;

function brik_audit_t( $name, $ok, $detail = '' ) {
	if ( $ok ) {
		++$GLOBALS['brik_audit_passes'];
		echo "PASS  {$name}\n";
	} else {
		++$GLOBALS['brik_audit_failures'];
		echo "FAIL  {$name}" . ( '' !== $detail ? "  — {$detail}" : '' ) . "\n";
	}
}

/** Findings matching a rule (and node). */
function brik_audit_find( array $report, $rule, $node = null ) {
	return array_values(
		array_filter(
			$report['findings'],
			static function ( $f ) use ( $rule, $node ) {
				return $f['rule'] === $rule && ( null === $node || $f['node'] === $node );
			}
		)
	);
}

function brik_audit_fix( array $finding, $id ) {
	foreach ( isset( $finding['fixes'] ) ? $finding['fixes'] : array() as $fix ) {
		if ( $fix['id'] === $id ) {
			return $fix;
		}
	}
	return null;
}

function brik_audit_attr( array $tree, $id, $key ) {
	$node = Data::find( $tree, $id );
	return $node && isset( $node['attrs'][ $key ] ) ? $node['attrs'][ $key ] : null;
}

wp_set_current_user( 1 );
$dir  = __DIR__ . '/fixtures/';
$bad  = json_decode( file_get_contents( $dir . 'bad.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
$good = json_decode( file_get_contents( $dir . 'good.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions

$cleanup = array();

// A post to audit (post type "post" also exercises the structured-data hint), and a heavy
// attachment for the image weight and alt-suggestion checks.
$post_id   = wp_insert_post(
	array(
		'post_type'   => 'post',
		'post_status' => 'draft',
		'post_title'  => 'Bad',
	)
);
$cleanup[] = $post_id;

$upload = wp_upload_dir();
$file   = trailingslashit( $upload['path'] ) . 'brik-audit-heavy-' . wp_rand() . '.jpg';
$img    = imagecreatetruecolor( 1400, 1000 );
for ( $y = 0; $y < 1000; $y += 2 ) {
	for ( $x = 0; $x < 1400; $x += 2 ) {
		imagefilledrectangle( $img, $x, $y, $x + 1, $y + 1, imagecolorallocate( $img, wp_rand( 0, 255 ), wp_rand( 0, 255 ), wp_rand( 0, 255 ) ) );
	}
}
imagejpeg( $img, $file, 100 );
imagedestroy( $img );
$att       = wp_insert_attachment(
	array(
		'post_title'     => 'Mountain lake at dawn',
		'post_mime_type' => 'image/jpeg',
		'post_status'    => 'inherit',
	),
	$file
);
$cleanup[] = $att;

$tree   = $bad;
$tree[] = array(
	'id'       => 'secheavy',
	'type'     => 'section',
	'children' => array(
		array(
			'type'     => 'row',
			'id'       => 'rowheavy',
			'children' => array(
				array(
					'type'     => 'column',
					'id'       => 'colheavy',
					'children' => array(
						array(
							'id'    => 'imgheavy',
							'type'  => 'image',
							'attrs' => array(
								'image' => array( 'id' => $att ),
								'size'  => 'full',
							),
						),
						array(
							'id'    => 'galbad',
							'type'  => 'gallery',
							'attrs' => array(
								'images' => array(
									array( 'url' => 'https://images.unsplash.com/photo-1501785888041-af3ef285b470?w=600&h=400&fit=crop' ),
									array(
										'url' => 'https://images.unsplash.com/photo-1441974231531-c6227db76b6e?w=600&h=400&fit=crop',
										'alt' => 'Forest light',
									),
								),
							),
						),
					),
				),
			),
		),
	),
);

echo "\n== Server scan of the bad page ==\n";
$r = Scanner::run( $post_id, $tree, array( 'links' => true ) );

$f = brik_audit_find( $r, 'heading-multiple-h1', 'h1second' );
brik_audit_t( 'multiple H1 flagged on the second H1', 1 === count( $f ) );
brik_audit_t( 'multiple H1 offers "Change to H2"', $f && brik_audit_fix( $f[0], 'level' ) && 'h2' === brik_audit_fix( $f[0], 'level' )['patch']['level'] );
brik_audit_t( 'first H1 not flagged', ! brik_audit_find( $r, 'heading-multiple-h1', 'h1first' ) );

$f = brik_audit_find( $r, 'heading-skipped', 'hskip' );
brik_audit_t( 'skipped level H1 → H4 flagged', 1 === count( $f ) );
brik_audit_t( 'skipped level fix sets H2', $f && 'h2' === brik_audit_fix( $f[0], 'level' )['patch']['level'] );
brik_audit_t( 'empty heading flagged', (bool) brik_audit_find( $r, 'heading-empty', 'hempty' ) );
$f = brik_audit_find( $r, 'h1-count' );
brik_audit_t( 'SEO H1 count error (2)', $f && 'error' === $f[0]['severity'] && 2 === $f[0]['data']['count'] );
brik_audit_t( 'outline lists 4 content headings before the accordion', count( $r['outline'] ) >= 4 && 1 === $r['outline'][0]['level'] );

$f = brik_audit_find( $r, 'img-alt-missing', 'imgnoalt' );
brik_audit_t( 'image without alt flagged', 1 === count( $f ) && 'error' === $f[0]['severity'] );
brik_audit_t( 'missing alt offers decorative + alt fixes', $f && brik_audit_fix( $f[0], 'decorative' ) && brik_audit_fix( $f[0], 'alt' ) );
brik_audit_t( 'alt fix prompts for a value', $f && ! empty( brik_audit_fix( $f[0], 'alt' )['prompt'] ) );
$f = brik_audit_find( $r, 'img-alt-missing', 'imgheavy' );
brik_audit_t( 'attachment image without alt flagged', 1 === count( $f ) );
brik_audit_t( 'alt suggestion comes from the attachment title (safe)', $f && 'Mountain lake at dawn' === brik_audit_fix( $f[0], 'alt' )['prompt']['value'] && brik_audit_fix( $f[0], 'alt' )['safe'] );
brik_audit_t( 'alt hint exposed for the builder', isset( $r['hints']['alt']['imgheavy'] ) );
brik_audit_t( 'file-name alt flagged', (bool) brik_audit_find( $r, 'img-alt-filename', 'imgfname' ) );
$f = brik_audit_find( $r, 'img-alt-missing', 'galbad' );
brik_audit_t( 'gallery counts images without alt (1 of 2)', $f && 1 === $f[0]['data']['missing'] && 2 === $f[0]['data']['total'] );
brik_audit_t( 'gallery offers "mark all decorative"', $f && brik_audit_fix( $f[0], 'decorative' ) );

$f = brik_audit_find( $r, 'name-missing', 'iconbtn' );
brik_audit_t( 'icon-only button has no accessible name', 1 === count( $f ) );
brik_audit_t( 'icon-only button fix prompts for text', $f && brik_audit_fix( $f[0], 'text' ) && 'text' === brik_audit_fix( $f[0], 'text' )['prompt']['key'] );
brik_audit_t( '"click here" link flagged', (bool) brik_audit_find( $r, 'link-generic', 'linkstext' ) );
$f = brik_audit_find( $r, 'link-blank-noopener', 'linkstext' );
brik_audit_t( 'target=_blank without noopener flagged', 1 === count( $f ) );
brik_audit_t( 'noopener fix rewrites the text HTML', $f && false !== strpos( brik_audit_fix( $f[0], 'rel' )['patch']['content'], 'rel="noopener"' ) );
brik_audit_t( 'affiliate link without sponsored noted', (bool) brik_audit_find( $r, 'link-sponsored', 'linkstext' ) );
$f = brik_audit_find( $r, 'link-broken', 'brokenbtn' );
brik_audit_t( 'broken internal link found', 1 === count( $f ) && 404 === $f[0]['data']['code'] );
brik_audit_t( 'working internal link not flagged', ! brik_audit_find( $r, 'link-broken', 'tinybtn' ) );

brik_audit_t( 'onclick div flagged', (bool) brik_audit_find( $r, 'div-button', 'rawhtml' ) );
$f = brik_audit_find( $r, 'input-label', 'rawhtml' );
brik_audit_t( 'placeholder-only input flagged (wrapped label passes)', 1 === count( $f ) );
brik_audit_t( 'tabindex > 0 flagged', (bool) brik_audit_find( $r, 'tabindex-positive', 'rawhtml' ) );
brik_audit_t( 'aria-hidden around a link flagged', (bool) brik_audit_find( $r, 'aria-hidden-focusable', 'rawhtml' ) );
brik_audit_t( 'autoplaying audio flagged', (bool) brik_audit_find( $r, 'autoplay-sound', 'rawhtml' ) );

$f = brik_audit_find( $r, 'contrast', 'lowcontrast' );
brik_audit_t( 'low contrast text on white flagged', 1 === count( $f ) && $f[0]['data']['ratio'] < 2 );
brik_audit_t( 'contrast fix switches to near-black', $f && '#0a0a0a' === brik_audit_fix( $f[0], 'contrast' )['patch']['text_color'] );
$f = brik_audit_find( $r, 'contrast', 'darktext' );
brik_audit_t( 'low contrast text on a dark section flagged', 1 === count( $f ) );
brik_audit_t( 'dark section fix switches to white', $f && '#ffffff' === brik_audit_fix( $f[0], 'contrast' )['patch']['text_color'] );

$f = brik_audit_find( $r, 'faq-schema', 'faqacc' );
brik_audit_t( 'FAQ-like accordion detected', 1 === count( $f ) && 3 === $f[0]['data']['questions'] );
brik_audit_t( 'FAQ fix enables faq_schema', $f && true === brik_audit_fix( $f[0], 'faq' )['patch']['faq_schema'] );
$f = brik_audit_find( $r, 'img-heavy', 'imgheavy' );
brik_audit_t( 'image over 500 KB flagged', 1 === count( $f ) && $f[0]['data']['bytes'] > Scanner::HEAVY_BYTES, $f ? '' : 'size ' . Scanner::file_size( $att, 'full' ) );
brik_audit_t( 'images without width/height noted', (bool) brik_audit_find( $r, 'img-dimensions' ) );
brik_audit_t( 'short title flagged', (bool) brik_audit_find( $r, 'title-length' ) );
brik_audit_t( 'missing meta description flagged', (bool) brik_audit_find( $r, 'meta-description' ) );
brik_audit_t( 'structured data hint for posts', (bool) brik_audit_find( $r, 'structured-data' ) );
brik_audit_t( 'accessibility score is low', $r['scores']['a11y'] < 40, (string) $r['scores']['a11y'] );
brik_audit_t( 'every finding has rule/category/severity/message', ! array_filter( $r['findings'], static function ( $x ) { return empty( $x['rule'] ) || empty( $x['message'] ) || ! in_array( $x['severity'], array( 'error', 'warning', 'info' ), true ) || ! in_array( $x['category'], array( 'a11y', 'seo' ), true ); } ) );

echo "\n== Safe fixes ==\n";
$res   = Fixer::apply_to_tree( $post_id, $tree );
$fixed = $res['tree'];
brik_audit_t( 'second H1 demoted to H2', 'h2' === brik_audit_attr( $fixed, 'h1second', 'level' ) );
brik_audit_t( 'skipped heading now H2', 'h2' === brik_audit_attr( $fixed, 'hskip', 'level' ) );
brik_audit_t( 'FAQ schema enabled', true === brik_audit_attr( $fixed, 'faqacc', 'faq_schema' ) );
brik_audit_t( 'noopener added to the inline link', false !== strpos( brik_audit_attr( $fixed, 'linkstext', 'content' ), 'rel="noopener"' ) );
brik_audit_t( 'alt filled from the attachment title', 'Mountain lake at dawn' === brik_audit_attr( $fixed, 'imgheavy', 'alt' ) );
brik_audit_t( 'unsafe fixes not applied (decorative, contrast, text)', null === brik_audit_attr( $fixed, 'imgnoalt', 'decorative' ) && '#c4c4c4' === brik_audit_attr( $fixed, 'lowcontrast', 'text_color' ) && '' === brik_audit_attr( $fixed, 'iconbtn', 'text' ) );
$after = Scanner::run( $post_id, $fixed );
brik_audit_t( 'heading issues resolved after fixes', ! brik_audit_find( $after, 'heading-multiple-h1' ) && ! brik_audit_find( $after, 'heading-skipped' ) );
brik_audit_t( 'FAQ finding resolved', ! brik_audit_find( $after, 'faq-schema', 'faqacc' ) );
brik_audit_t( 'noopener finding resolved', ! brik_audit_find( $after, 'link-blank-noopener' ) );
brik_audit_t( 'score improved', $after['scores']['overall'] > $r['scores']['overall'] );

echo "\n== Chosen fixes ==\n";
$res = Fixer::apply_to_tree(
	$post_id,
	$fixed,
	array(
		array(
			'rule' => 'img-alt-missing',
			'node' => 'imgnoalt',
			'fix'  => 'decorative',
		),
		array(
			'rule'  => 'name-missing',
			'node'  => 'iconbtn',
			'value' => 'Contact us',
		),
		array(
			'rule' => 'contrast',
			'node' => 'lowcontrast',
		),
		array(
			'rule' => 'img-alt-missing',
			'node' => 'galbad',
		),
		array(
			'rule' => 'img-alt-filename',
			'node' => 'imgfname',
			'fix'  => 'alt',
		),
		array(
			'rule' => 'heading-empty',
			'node' => 'hempty',
		),
	)
);
$t2  = $res['tree'];
brik_audit_t( 'image marked decorative', true === brik_audit_attr( $t2, 'imgnoalt', 'decorative' ) );
brik_audit_t( 'button text set from value', 'Contact us' === brik_audit_attr( $t2, 'iconbtn', 'text' ) );
brik_audit_t( 'contrast fixed', '#0a0a0a' === brik_audit_attr( $t2, 'lowcontrast', 'text_color' ) );
brik_audit_t( 'gallery marked decorative', true === brik_audit_attr( $t2, 'galbad', 'decorative' ) );
$reasons = wp_list_pluck( $res['skipped'], 'reason', 'node' );
brik_audit_t( 'file-name alt without value skipped as needs_value', isset( $reasons['imgfname'] ) && 'needs_value' === $reasons['imgfname'] );
brik_audit_t( 'finding without a fix reported as no_fix', isset( $reasons['hempty'] ) && 'no_fix' === $reasons['hempty'] );
$after2 = Scanner::run( $post_id, $t2 );
brik_audit_t( 'decorative image no longer flagged', ! brik_audit_find( $after2, 'img-alt-missing', 'imgnoalt' ) && ! brik_audit_find( $after2, 'img-alt-missing', 'galbad' ) );
brik_audit_t( 'button now has a name', ! brik_audit_find( $after2, 'name-missing', 'iconbtn' ) );
brik_audit_t( 'contrast resolved', ! brik_audit_find( $after2, 'contrast', 'lowcontrast' ) );

echo "\n== Decorative rendering ==\n";
$renderer = new Renderer( $post_id );
$html     = $renderer->render_nodes(
	array(
		array(
			'id'    => 'decoimg',
			'type'  => 'image',
			'attrs' => array(
				'image'      => array(
					'url' => 'https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=800&h=500',
					'alt' => 'Should be hidden',
				),
				'decorative' => true,
			),
		),
	)
);
brik_audit_t( 'decorative image renders alt="" role="presentation"', false !== strpos( $html, 'alt=""' ) && false !== strpos( $html, 'role="presentation"' ) && false === strpos( $html, 'Should be hidden' ) );
$html = $renderer->render_nodes(
	array(
		array(
			'id'    => 'decoatt',
			'type'  => 'image',
			'attrs' => array(
				'image'      => array( 'id' => $att ),
				'decorative' => true,
			),
		),
	)
);
brik_audit_t( 'decorative attachment image has no empty role attribute', false !== strpos( $html, 'role="presentation"' ) && false === strpos( $html, 'role=""' ) );
$html = $renderer->render_nodes(
	array(
		array(
			'id'    => 'nodeco',
			'type'  => 'image',
			'attrs' => array( 'image' => array( 'url' => 'https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=800&h=500', 'alt' => 'Visible alt' ) ),
		),
	)
);
brik_audit_t( 'normal image keeps its alt and no role', false !== strpos( $html, 'alt="Visible alt"' ) && false === strpos( $html, 'role=' ) );
$html = $renderer->render_nodes(
	array(
		array(
			'id'    => 'decogal',
			'type'  => 'gallery',
			'attrs' => array(
				'decorative' => true,
				'images'     => array(
					array(
						'url' => 'https://images.unsplash.com/photo-1501785888041-af3ef285b470?w=600&h=400',
						'alt' => 'Lake',
					),
				),
			),
		),
	)
);
brik_audit_t( 'decorative gallery hides alt but names its links', false !== strpos( $html, 'alt=""' ) && false !== strpos( $html, 'aria-label="Open image 1 of 1"' ) );

echo "\n== Good page ==\n";
wp_update_post(
	array(
		'ID'           => $post_id,
		'post_title'   => 'Analytics your whole team understands',
		'post_excerpt' => 'Aurora turns product events into answers. Track launches, find what moves retention and share dashboards your whole team can read.',
		'post_type'    => 'page',
	)
);
$g = Scanner::run( $post_id, $good, array( 'links' => true ) );
$errors = array_filter(
	$g['findings'],
	static function ( $x ) {
		return 'info' !== $x['severity'];
	}
);
brik_audit_t( 'good fixture has no errors or warnings', ! $errors, implode( ' | ', wp_list_pluck( $errors, 'message' ) ) );
brik_audit_t( 'good fixture scores 100/100', 100 === $g['scores']['a11y'] && 100 === $g['scores']['seo'], wp_json_encode( $g['scores'] ) );
brik_audit_t( 'single H1 not flagged for SEO', ! brik_audit_find( $g, 'h1-count' ) );
brik_audit_t( 'meta reports the excerpt as description', 'excerpt' === $g['meta']['description_source'] );

$aurora = get_page_by_path( 'aurora-product-analytics' );
if ( $aurora ) {
	$a = Scanner::run( $aurora->ID, null, array( 'links' => true ) );
	$a_errors = array_filter(
		$a['findings'],
		static function ( $x ) {
			return 'error' === $x['severity'];
		}
	);
	brik_audit_t( 'aurora page: no errors', ! $a_errors, implode( ' | ', wp_list_pluck( $a_errors, 'message' ) ) );
	brik_audit_t( 'aurora page: accessibility score ≥ 90', $a['scores']['a11y'] >= 90, (string) $a['scores']['a11y'] );
}

echo "\n== Links ==\n";
$aurora_url = $aurora ? get_permalink( $aurora ) : home_url( '/' );
$links      = Links::check( array( '/', '/this-page-does-not-exist-xyz/', $aurora_url, 'mailto:a@b.co', '#section', '/wp-content/uploads/nope-' . wp_rand() . '.jpg', 'https://example.org/' ) );
brik_audit_t( 'home resolves', 'ok' === $links['/']['status'] );
brik_audit_t( 'missing page is broken', 'broken' === $links['/this-page-does-not-exist-xyz/']['status'] );
brik_audit_t( 'existing page resolves', 'ok' === $links[ $aurora_url ]['status'] );
brik_audit_t( 'mailto and anchors skipped', 'skipped' === $links['mailto:a@b.co']['status'] && 'skipped' === $links['#section']['status'] );
$missing_file = array_values( array_filter( array_keys( $links ), static function ( $u ) { return false !== strpos( $u, 'nope-' ); } ) );
brik_audit_t( 'missing upload is broken', $missing_file && 'broken' === $links[ $missing_file[0] ]['status'] );
brik_audit_t( 'external links skipped unless asked', 'skipped' === $links['https://example.org/']['status'] && false === $links['https://example.org/']['internal'] );
brik_audit_t( 'results are cached', false !== get_transient( 'brik_audit_link_' . md5( '/this-page-does-not-exist-xyz/' ) ) );

echo "\n== Helpers ==\n";
brik_audit_t( 'oklch(1 0 0) is white', array( 255, 255, 255 ) === array_slice( Color::parse( 'oklch(1 0 0)' ), 0, 3 ) );
brik_audit_t( 'black on white is 21:1', 21.0 === (float) Color::ratio( array( 0, 0, 0, 1 ), array( 255, 255, 255, 1 ) ) );
$grey = Color::ratio( Color::parse( '#767676' ), array( 255, 255, 255, 1 ) );
brik_audit_t( '#767676 on white passes AA (≈4.54)', $grey >= 4.5 && $grey < 4.6, (string) $grey );
brik_audit_t( 'var(--background) resolves through tokens', null !== Color::parse( 'var(--background)' ) );
brik_audit_t( 'rgba alpha parsed', 0.5 === (float) Color::parse( 'rgba(0, 0, 0, .5)' )[3] );
brik_audit_t( 'file names detected', Scanner::filename_like( 'IMG_2041.jpg' ) && Scanner::filename_like( 'DSC 0042' ) && ! Scanner::filename_like( 'Team photo at the summit' ) );
brik_audit_t( 'rel rewrite keeps existing rel tokens', '<a href="x" target="_blank" rel="nofollow noopener">' === Scanner::rewrite_rel( '<a href="x" target="_blank" rel="nofollow">', 'noopener' ) );
brik_audit_t( 'rel rewrite leaves same-tab links alone', '<a href="x">' === Scanner::rewrite_rel( '<a href="x">', 'noopener' ) );
brik_audit_t( 'rel rewrite walks repeater items', false !== strpos( Scanner::rewrite_rel( array( array( 'content' => '<a href="x" target="_blank">y</a>' ) ), 'noopener' )[0]['content'], 'noopener' ) );
brik_audit_t( 'affiliate URLs recognised', Scanner::affiliate( 'https://amzn.to/abc' ) && Scanner::affiliate( 'https://shop.com/p?ref=me' ) && ! Scanner::affiliate( 'https://example.com/about' ) );
brik_audit_t( 'scoring: one error costs 12', 88 === Scanner::scores( array( array( 'rule' => 'x', 'category' => 'a11y', 'severity' => 'error' ) ) )['a11y'] );

echo "\n== REST and MCP ==\n";
// Save the bad tree so the endpoints and tools work on stored data.
Data::save( $post_id, $tree );
$req  = new WP_REST_Request( 'GET', '/brik/v1/audit/' . $post_id );
$resp = rest_do_request( $req );
brik_audit_t( 'GET /audit/{id} returns findings', 200 === $resp->get_status() && ! empty( $resp->get_data()['findings'] ) );
$req = new WP_REST_Request( 'POST', '/brik/v1/audit/' . $post_id );
$req->set_header( 'content-type', 'application/json' );
$req->set_body( wp_json_encode( array( 'tree' => $good ) ) );
$resp = rest_do_request( $req );
brik_audit_t( 'POST /audit/{id} audits the posted tree', 200 === $resp->get_status() && ! brik_audit_find( $resp->get_data(), 'heading-multiple-h1' ) );
$req = new WP_REST_Request( 'POST', '/brik/v1/audit/links' );
$req->set_param( 'urls', array( '/this-page-does-not-exist-xyz/' ) );
$req->set_param( 'post_id', $post_id );
$resp = rest_do_request( $req );
brik_audit_t( 'POST /audit/links reports broken links', 200 === $resp->get_status() && 'broken' === $resp->get_data()['results']['/this-page-does-not-exist-xyz/']['status'] );
$heavy_url = wp_get_attachment_url( $att );
$req       = new WP_REST_Request( 'POST', '/brik/v1/audit/media' );
$req->set_param( 'urls', array( $heavy_url, 'https://example.org/x.jpg' ) );
$resp  = rest_do_request( $req );
$sizes = (array) $resp->get_data()['sizes'];
brik_audit_t( 'POST /audit/media returns upload file sizes', isset( $sizes[ $heavy_url ] ) && $sizes[ $heavy_url ] > Scanner::HEAVY_BYTES && ! isset( $sizes['https://example.org/x.jpg'] ) );

wp_set_current_user( 0 );
$resp = rest_do_request( new WP_REST_Request( 'GET', '/brik/v1/audit/' . $post_id ) );
brik_audit_t( 'logged-out users are refused', in_array( $resp->get_status(), array( 401, 403 ), true ) );
$sub = wp_insert_user(
	array(
		'user_login' => 'brik_audit_sub_' . wp_rand(),
		'user_pass'  => wp_generate_password(),
		'role'       => 'subscriber',
	)
);
wp_set_current_user( $sub );
$resp = rest_do_request( new WP_REST_Request( 'GET', '/brik/v1/audit/' . $post_id ) );
brik_audit_t( 'subscribers are refused', 403 === $resp->get_status() );
list( $out ) = McpTools::call( 'audit_page', array( 'post_id' => $post_id ) );
brik_audit_t( 'MCP audit_page refuses users who cannot edit', is_wp_error( $out ) );
wp_set_current_user( 1 );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $sub );

brik_audit_t( 'MCP tools registered', McpTools::exists( 'audit_page' ) && McpTools::exists( 'apply_audit_fixes' ) );
brik_audit_t( 'MCP guide mentions the audit', false !== strpos( McpTools::guide(), 'audit_page' ) );
list( $out ) = McpTools::call( 'audit_page', array( 'post_id' => $post_id ) );
brik_audit_t( 'MCP audit_page returns scores and node ids', ! is_wp_error( $out ) && isset( $out['scores']['a11y'] ) && in_array( 'h1second', wp_list_pluck( $out['findings'], 'node_id' ), true ) );
$with_fix = array_values( array_filter( $out['findings'], static function ( $x ) { return 'imgnoalt' === $x['node_id'] && ! empty( $x['fixes'] ); } ) );
brik_audit_t( 'MCP findings list fix ids', $with_fix && in_array( 'decorative', wp_list_pluck( $with_fix[0]['fixes'], 'fix' ), true ) );
list( $out ) = McpTools::call( 'audit_page', array( 'post_id' => $post_id, 'category' => 'seo' ) );
brik_audit_t( 'MCP category filter', ! array_filter( $out['findings'], static function ( $x ) { return 'seo' !== $x['category']; } ) );

list( $out ) = McpTools::call( 'apply_audit_fixes', array( 'post_id' => $post_id ) );
$saved       = Data::get( $post_id );
brik_audit_t( 'MCP apply_audit_fixes applies safe fixes and saves', ! is_wp_error( $out ) && count( $out['applied'] ) >= 4 && 'h2' === brik_audit_attr( $saved, 'h1second', 'level' ) );
brik_audit_t( 'MCP apply reports before/after scores', $out['after']['overall'] > $out['before']['overall'], wp_json_encode( array( $out['before'], $out['after'] ) ) );
list( $out, $warnings ) = McpTools::call(
	'apply_audit_fixes',
	array(
		'post_id' => $post_id,
		'fixes'   => array(
			array(
				'rule'    => 'img-alt-missing',
				'node_id' => 'imgnoalt',
				'fix'     => 'alt',
				'value'   => 'Mountain ridge at sunrise',
			),
			array(
				'rule'    => 'heading-skipped',
				'node_id' => 'doesnotexist',
			),
		),
	)
);
$saved = Data::get( $post_id );
brik_audit_t( 'MCP apply with a value writes alt text', 'Mountain ridge at sunrise' === brik_audit_attr( $saved, 'imgnoalt', 'alt' ) );
brik_audit_t( 'MCP apply warns about unknown findings', (bool) array_filter( $warnings, static function ( $w ) { return false !== strpos( $w, 'doesnotexist' ); } ) );

// Cleanup.
foreach ( $cleanup as $id ) {
	if ( 'attachment' === get_post_type( $id ) ) {
		wp_delete_attachment( $id, true );
	} else {
		wp_delete_post( $id, true );
	}
}

printf( "\n%d passed, %d failed\n", $GLOBALS['brik_audit_passes'], $GLOBALS['brik_audit_failures'] );
exit( $GLOBALS['brik_audit_failures'] ? 1 : 0 );
