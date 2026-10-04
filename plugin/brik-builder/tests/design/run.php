<?php
/**
 * Design system test suite (variables, CSS classes, components). Runs inside WordPress:
 *
 *   ./dev/wp.sh eval-file /var/www/html/wp-content/plugins/brik-builder/tests/design/run.php
 *
 * Everything it creates is removed at the end and global settings are restored.
 * Exits non-zero when a check fails.
 *
 * @package Brik
 */

use Brik\Data;
use Brik\Design\Classes;
use Brik\Design\Components;
use Brik\Design\Usage;
use Brik\Design\Variables;
use Brik\Library;
use Brik\McpTools;
use Brik\Renderer;
use Brik\Settings;

defined( 'ABSPATH' ) || exit;

$GLOBALS['bd_pass']     = 0;
$GLOBALS['bd_failures'] = array();

function bd_ok( $cond, $name, $detail = '' ) {
	if ( $cond ) {
		++$GLOBALS['bd_pass'];
		echo "  PASS {$name}\n"; // phpcs:ignore WordPress.Security.EscapeOutput
		return;
	}
	$GLOBALS['bd_failures'][] = $name;
	echo "  FAIL {$name}" . ( $detail ? ' — ' . $detail : '' ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
}

function bd_section( $name ) {
	echo "\n== {$name} ==\n"; // phpcs:ignore WordPress.Security.EscapeOutput
}

/** Render a stored post: [ html, css ]. */
function bd_render( $post_id ) {
	$r    = new Renderer( $post_id );
	$html = $r->render_root( Data::get( $post_id ) );
	return array( $html, $r->style->css() );
}

function bd_page( $title, array $tree ) {
	$id = wp_insert_post(
		array(
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_title'  => 'BD ' . $title,
		)
	);
	Data::save( $id, $tree );
	$GLOBALS['bd_posts'][] = $id;
	return $id;
}

/** Specificity (ids, classes, types) of a simple selector; :where() counts zero. */
function bd_specificity( $sel ) {
	$sel = preg_replace( '/:where\([^)]*\)/', '', $sel );
	return array( substr_count( $sel, '#' ), preg_match_all( '/\.[a-z0-9_-]+|:hover|\[/i', $sel ), 0 );
}

$admins = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ID',
	)
);
wp_set_current_user( (int) $admins[0] );

$GLOBALS['bd_posts'] = array();
$original            = get_option( Settings::OPTION );

try {
	/* ---------------------------------------------------------------------
	 * Variables.
	 * ------------------------------------------------------------------- */
	bd_section( 'Variables' );
	Settings::update(
		array(
			'variables' => array(),
			'classes'   => array(),
		)
	);
	$css = Settings::css();
	bd_ok( false !== strpos( $css, '--space-md:1.5rem' ), 'default spacing scale printed' );
	bd_ok( false !== strpos( $css, '--radius-full:9999px' ) && false !== strpos( $css, '--radius-lg:var(--radius)' ), 'radius scale printed' );
	bd_ok( false !== strpos( $css, '--text-7xl:4.5rem' ) && false !== strpos( $css, '--text-base:1rem' ), 'type scale printed' );
	bd_ok( false !== strpos( $css, '--shadow-md:' ), 'shadow scale printed' );

	Settings::update(
		array(
			'variables' => array(
				'space'  => array(
					'md'  => '1.75rem',
					'bad' => '9rem',
				),
				'fluid'  => true,
				'custom' => array(
					array(
						'name'  => 'Brand Gap',
						'value' => '2.5rem',
						'group' => 'space',
					),
					array(
						'name'  => 'evil',
						'value' => 'red;}body{display:none',
						'group' => 'color',
					),
					array(
						'name'  => 'primary',
						'value' => 'red',
					),
				),
				'headings' => array( 'h2' => array( 'size' => 'var(--text-4xl)' ) ),
			),
		)
	);
	$css = Settings::css();
	bd_ok( false !== strpos( $css, '--space-md:1.75rem' ), 'scale override printed' );
	bd_ok( false === strpos( $css, 'space-bad' ), 'unknown scale step dropped' );
	bd_ok( (bool) preg_match( '/--text-5xl:clamp\([0-9.]+rem,[-0-9.]+rem \+ [0-9.]+vw,3rem\)/', $css ), 'fluid type uses clamp()', substr( $css, strpos( $css, '--text-5xl' ), 60 ) );
	bd_ok( false !== strpos( $css, '--text-base:1rem' ), 'body sizes stay fixed when fluid' );
	bd_ok( false !== strpos( $css, '--brand-gap:2.5rem' ), 'custom variable printed with a CSS-safe name' );
	bd_ok( false === strpos( $css, 'body{display' ), 'custom variable values cannot break out of the declaration' );
	bd_ok( false === strpos( $css, '--primary:red' ), 'custom variable cannot shadow a theme token' );
	bd_ok( false !== strpos( $css, ':where(.brik) h2{font-size:var(--text-4xl)}' ), 'heading style printed with zero-specificity scope' );

	/* ---------------------------------------------------------------------
	 * CSS classes.
	 * ------------------------------------------------------------------- */
	bd_section( 'CSS classes' );
	$cid = Classes::upsert(
		'bdbtn01',
		array(
			'name'  => 'BD Button Primary!',
			'label' => 'Button primary',
			'type'  => 'button',
			'attrs' => array(
				'padding'         => '10px 20px',
				'padding@mobile'  => '6px 12px',
				'radius@tablet'   => '4px',
				'bg_color@hover'  => 'var(--primary)',
				'button_radius'   => '999px',
				'button_bg@hover' => '#ff0000',
				'text'            => 'not a design field',
				'margin'          => 'var(--space-md)',
			),
		)
	);
	$class = Classes::get( $cid );
	bd_ok( 'bd-button-primary' === $class['name'], 'class name sanitized to [a-z0-9-_]', $class['name'] );
	bd_ok( ! isset( $class['attrs']['text'] ), 'content attrs are not stored on classes' );
	$css = Settings::css();
	$sel = ':where(.brik) .cls-bd-button-primary';
	bd_ok( false !== strpos( $css, $sel . '{' ) && false !== strpos( $css, 'padding:10px 20px' ), 'desktop rule compiled' );
	bd_ok( (bool) preg_match( '/@media \(max-width:767px\)\{[^@]*' . preg_quote( $sel, '/' ) . '\{padding:6px 12px/', $css ), 'mobile rule compiled' );
	bd_ok( (bool) preg_match( '/@media \(max-width:980px\)\{[^@]*' . preg_quote( $sel, '/' ) . '\{border-radius:4px/', $css ), 'tablet rule compiled' );
	bd_ok( (bool) preg_match( '/@media \(hover:hover\)\{[^@]*' . preg_quote( $sel, '/' ) . ':hover\{background-color:var\(--primary\)/', $css ), 'hover rule compiled' );
	bd_ok( false !== strpos( $css, $sel . ' .brik-button{border-radius:999px' ), 'module design fields compile for typed classes' );
	bd_ok( false !== strpos( $css, 'transition:all 300ms ease' ), 'hover classes get a transition' );
	bd_ok( false !== strpos( $css, 'margin:var(--space-md)' ), 'variables usable in class values' );

	$el_sel = '.brik .brik-n-abcd1234';
	bd_ok( bd_specificity( $sel ) < bd_specificity( $el_sel ), 'class selector is less specific than element selector' );
	bd_ok( bd_specificity( $sel . ' .brik-button' ) < bd_specificity( $el_sel . ' .brik-button' ), 'inner class selector is less specific than inner element selector' );

	$button = array(
		'id'    => 'bdb00001',
		'type'  => 'button',
		'attrs' => array(
			'text'          => 'Hello',
			'classes'       => array( $cid ),
			'button_radius' => '2px',
			'css_class'     => 'cls-bd-button-primary extra',
		),
	);
	$p1      = bd_page( 'classes', array( $button ) );
	list( $html, $el_css ) = bd_render( $p1 );
	bd_ok( (bool) preg_match( '/class="[^"]*brik-n-bdb00001[^"]*cls-bd-button-primary/', $html ), 'class applied to the element wrapper' );
	bd_ok( false !== strpos( $el_css, '.brik .brik-n-bdb00001 .brik-button{border-radius:2px' ), 'element attrs still compile (and win on specificity)' );

	$usage = Usage::scan()['classes'];
	bd_ok( isset( $usage[ $cid ] ) && 1 === $usage[ $cid ], 'usage scan counts the element', wp_json_encode( $usage ) );

	Classes::upsert( $cid, array( 'name' => 'bd-cta' ) );
	list( $html ) = bd_render( $p1 );
	bd_ok( false !== strpos( $html, 'cls-bd-cta' ) && false === strpos( $html, 'cls-bd-button-primary ' ), 'rename updates markup' );
	$stored = Data::get( $p1 );
	bd_ok( false !== strpos( $stored[0]['children'][0]['children'][0]['children'][0]['attrs']['css_class'], 'cls-bd-cta' ), 'rename rewrites hand-typed class references' );
	bd_ok( false !== strpos( Settings::css(), '.cls-bd-cta{' ), 'renamed class compiles under the new name' );

	$dup = Classes::duplicate( $cid );
	bd_ok( $dup && 'bd-cta-copy' === Classes::get( $dup )['name'], 'duplicate gets a unique name' );

	$res = McpTools::call( 'apply_class', array( 'post_id' => $p1, 'node_id' => 'bdb00001', 'class' => 'bd-cta-copy' ) );
	bd_ok( ! is_wp_error( $res[0] ) && in_array( $dup, $res[0]['classes'], true ), 'MCP apply_class adds a class by name' );
	McpTools::call( 'delete_class', array( 'class' => $dup ) );
	$stored = Data::get( $p1 );
	bd_ok( ! in_array( $dup, $stored[0]['children'][0]['children'][0]['children'][0]['attrs']['classes'], true ) && ! Classes::get( $dup ), 'delete removes the class and its usages' );

	$res = McpTools::call( 'save_class', array( 'name' => 'bd-card', 'attrs' => array( 'padding' => 'var(--space-lg)', 'nope' => 1 ) ) );
	bd_ok( ! is_wp_error( $res[0] ) && 'cls-bd-card' === $res[0]['class'] && $res[1], 'MCP save_class creates and warns about unknown keys' );
	$list = McpTools::call( 'list_classes', array() );
	bd_ok( count( $list[0]['classes'] ) >= 2, 'MCP list_classes' );

	/* ---------------------------------------------------------------------
	 * Components.
	 * ------------------------------------------------------------------- */
	bd_section( 'Components' );
	$card = array(
		'id'       => 'bdcard01',
		'type'     => 'column',
		'attrs'    => array(),
		'children' => array(
			array(
				'id'    => 'bdhead01',
				'type'  => 'heading',
				'attrs' => array(
					'text'            => 'Master title',
					'title_font_size' => '20px',
				),
			),
			array(
				'id'    => 'bdbtn001',
				'type'  => 'button',
				'attrs' => array(
					'text'          => 'Master CTA',
					'button_radius' => '8px',
				),
			),
		),
	);
	$ref = Components::create( 'BD Card', array( array( 'type' => 'section', 'children' => array( array( 'type' => 'row', 'children' => array( $card ) ) ) ) ) );
	$GLOBALS['bd_posts'][] = $ref;
	bd_ok( Components::is_component( $ref ) && 'component' === Library::item( $ref )['kind'], 'component library item created' );

	$master = Data::get( $ref );
	$heading = $master[0]['children'][0]['children'][0]['children'][0];
	bd_ok( Components::allowed( array(), $heading, 'text' ), 'content fields overridable by default' );
	bd_ok( ! Components::allowed( array(), $heading, 'padding' ), 'design fields locked by default' );

	$inst = static function ( $id, $overrides = array() ) use ( $ref ) {
		$attrs = array( 'ref' => $ref );
		if ( $overrides ) {
			$attrs['overrides'] = $overrides;
		}
		return array(
			'type'     => 'section',
			'children' => array(
				array(
					'type'     => 'row',
					'children' => array(
						array(
							'type'     => 'column',
							'children' => array(
								array(
									'id'    => $id,
									'type'  => 'global',
									'attrs' => $attrs,
								),
							),
						),
					),
				),
			),
		);
	};
	$pa = bd_page( 'inst A', array( $inst( 'bdinsta1', array( 'bdbtn001' => array( 'text' => 'Override CTA', 'button_radius' => '0px' ) ) ) ) );
	$pb = bd_page( 'inst B', array( $inst( 'bdinstb1' ) ) );
	$pc = bd_page( 'inst C', array( $inst( 'bdinstc1' ) ) );

	list( $ha, $ca ) = bd_render( $pa );
	list( $hb, $cb ) = bd_render( $pb );
	bd_ok( false !== strpos( $ha, 'Override CTA' ) && false === strpos( $ha, 'Master CTA' ), 'allowed override merged' );
	bd_ok( false === strpos( $ca, 'border-radius:0px' ) && false !== strpos( $ca, 'border-radius:8px' ), 'locked override ignored' );
	bd_ok( false !== strpos( $hb, 'Master CTA' ), 'instance without overrides shows the master' );

	Components::save_policy( $ref, array( 'bdbtn001' => array( 'button_radius' => true ) ) );
	list( $ha, $ca ) = bd_render( $pa );
	bd_ok( false !== strpos( $ca, 'border-radius:0px' ), 'policy can allow a design field' );
	bd_ok( false === strpos( $ca, '.brik-n-bdbtn001 .brik-button{border-radius:0px' ), 'overridden node CSS is scoped to the instance' );
	Components::save_policy( $ref, array( 'bdbtn001' => array( 'text' => false ) ) );
	list( $ha ) = bd_render( $pa );
	bd_ok( false !== strpos( $ha, 'Master CTA' ), 'policy can lock a content field' );
	Components::save_policy( $ref, array() );

	// Change the master: every instance follows, overrides persist.
	$master[0]['children'][0]['children'][0]['children'][1]['attrs']['button_radius'] = '22px';
	$master[0]['children'][0]['children'][0]['children'][0]['attrs']['text']          = 'New master title';
	Data::save( $ref, $master );
	foreach ( array( $pa, $pb, $pc ) as $p ) {
		list( $h, $c ) = bd_render( $p );
		bd_ok( false !== strpos( $c, 'border-radius:22px' ) && false !== strpos( $h, 'New master title' ), "master change reaches page {$p}" );
	}
	list( $ha ) = bd_render( $pa );
	bd_ok( false !== strpos( $ha, 'Override CTA' ), 'override persists after a master change' );

	$usage = Usage::scan()['components'];
	bd_ok( isset( $usage[ $ref ] ) && 3 === $usage[ $ref ]['count'], 'instances counted across pages' );

	// Detach.
	$res = McpTools::call( 'detach_component', array( 'post_id' => $pa, 'node_id' => 'bdinsta1' ) );
	bd_ok( ! is_wp_error( $res[0] ), 'MCP detach_component', is_wp_error( $res[0] ) ? $res[0]->get_error_message() : '' );
	$tree    = Data::get( $pa );
	$ids     = wp_list_pluck( Data::flatten( $tree ), 'id' );
	$masters = wp_list_pluck( Data::flatten( Data::get( $ref ) ), 'id' );
	bd_ok( ! array_intersect( $ids, $masters ), 'detached copy has fresh ids' );
	bd_ok( ! in_array( 'global', wp_list_pluck( Data::flatten( $tree ), 'type' ), true ), 'detached copy has no instance left' );
	list( $ha ) = bd_render( $pa );
	bd_ok( false !== strpos( $ha, 'Override CTA' ), 'detached copy keeps the overrides' );
	$master = Data::get( $ref );
	$master[0]['children'][0]['children'][0]['children'][0]['attrs']['text'] = 'Third title';
	Data::save( $ref, $master );
	list( $ha ) = bd_render( $pa );
	list( $hb ) = bd_render( $pb );
	bd_ok( false === strpos( $ha, 'Third title' ) && false !== strpos( $hb, 'Third title' ), 'master edits no longer reach the detached copy' );

	// MCP overrides.
	$res = McpTools::call(
		'set_component_overrides',
		array(
			'post_id'   => $pb,
			'node_id'   => 'bdinstb1',
			'overrides' => array(
				'bdhead01' => array(
					'text'    => 'B title',
					'padding' => '99px',
				),
			),
		)
	);
	bd_ok( ! is_wp_error( $res[0] ) && isset( $res[0]['overrides']['bdhead01']['text'] ) && ! isset( $res[0]['overrides']['bdhead01']['padding'] ) && $res[1], 'MCP set_component_overrides keeps allowed, warns on locked' );
	list( $hb ) = bd_render( $pb );
	bd_ok( false !== strpos( $hb, 'B title' ), 'MCP override renders' );

	// Recursion: a component that contains an instance of itself.
	$master   = Data::get( $ref );
	$master[] = array(
		'type'     => 'section',
		'children' => array(
			array(
				'type'     => 'row',
				'children' => array(
					array(
						'type'     => 'column',
						'children' => array(
							array(
								'type'  => 'global',
								'attrs' => array( 'ref' => $ref ),
							),
						),
					),
				),
			),
		),
	);
	Data::save( $ref, $master );
	list( $hc ) = bd_render( $pc );
	bd_ok( 1 === substr_count( $hc, 'Third title' ), 'self-nested component renders once (recursion guard)', (string) substr_count( $hc, 'Third title' ) );

	$res = McpTools::call( 'create_component', array( 'title' => 'BD From node', 'post_id' => $pc, 'node_id' => 'bdinstc1' ) );
	bd_ok( ! is_wp_error( $res[0] ) && ! empty( $res[0]['instance_id'] ), 'MCP create_component from a node replaces it with an instance' );
	if ( ! is_wp_error( $res[0] ) ) {
		$GLOBALS['bd_posts'][] = $res[0]['id'];
		list( $hc ) = bd_render( $pc );
		bd_ok( 1 === substr_count( $hc, 'Third title' ), 'nested components render', (string) substr_count( $hc, 'Third title' ) );
	}
	$list = McpTools::call( 'list_components', array() );
	bd_ok( ! is_wp_error( $list[0] ) && count( $list[0]['components'] ) >= 2 && ! empty( $list[0]['components'][0]['fields'] ), 'MCP list_components' );

	$vars = McpTools::call( 'save_variables', array( 'radius' => array( 'lg' => '12px' ), 'custom' => array( array( 'name' => 'hero-h', 'value' => '80vh', 'group' => 'size' ) ) ) );
	bd_ok( ! is_wp_error( $vars[0] ) && false !== strpos( Settings::css(), '--radius-lg:12px' ) && false !== strpos( Settings::css(), '--hero-h:80vh' ), 'MCP save_variables' );
} catch ( \Throwable $e ) {
	bd_ok( false, 'no exceptions', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
}

/* -------------------------------------------------------------------------
 * Cleanup.
 * ---------------------------------------------------------------------- */
foreach ( $GLOBALS['bd_posts'] as $id ) {
	wp_delete_post( $id, true );
}
update_option( Settings::OPTION, $original );

$fail = count( $GLOBALS['bd_failures'] );
echo "\n" . $GLOBALS['bd_pass'] . ' passed, ' . $fail . " failed\n"; // phpcs:ignore WordPress.Security.EscapeOutput
if ( $fail ) {
	echo 'Failures: ' . implode( '; ', $GLOBALS['bd_failures'] ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	exit( 1 );
}
