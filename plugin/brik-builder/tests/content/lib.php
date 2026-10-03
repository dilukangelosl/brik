<?php
/**
 * Helpers for the content test suite.
 *
 * @package Brik
 */

use Brik\Content\Registry;

defined( 'ABSPATH' ) || exit;

/**
 * A tiny assertion runner: prints one line per failure and a summary.
 */
final class Brik_Content_Test_Runner {

	private $pass = 0;

	private $fail = 0;

	private $section = '';

	private $failures = array();

	public function section( $name ) {
		$this->section = $name;
		echo "\n== " . $name . " ==\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	public function ok( $condition, $name, $detail = '' ) {
		if ( $condition ) {
			++$this->pass;
			echo '  ok   ' . $name . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
			return true;
		}
		++$this->fail;
		$line             = '  FAIL ' . $name . ( '' !== $detail ? ' — ' . $detail : '' );
		$this->failures[] = '[' . $this->section . '] ' . trim( $line );
		echo $line . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
		return false;
	}

	public function eq( $expected, $actual, $name ) {
		$same = $expected === $actual || ( is_float( $expected ) && ( is_int( $actual ) || is_float( $actual ) ) && abs( $expected - $actual ) < 1e-9 );
		return $this->ok( $same, $name, $same ? '' : 'expected ' . self::show( $expected ) . ', got ' . self::show( $actual ) );
	}

	private static function show( $v ) {
		$out = is_object( $v ) ? get_class( $v ) : wp_json_encode( $v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		return strlen( (string) $out ) > 300 ? substr( $out, 0, 300 ) . '…' : (string) $out;
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

function bt_field( $key, $name, $type, array $options = array(), array $extra = array() ) {
	return array_merge(
		array(
			'key'     => 'field_bt' . $key,
			'name'    => $name,
			'label'   => ucwords( str_replace( '_', ' ', $name ) ),
			'type'    => $type,
			'options' => $options,
		),
		$extra
	);
}

/**
 * The main test group: one field of every type, for the bt_project post type.
 */
function bt_group_def() {
	$choices = array(
		array( 'value' => 'red', 'label' => 'Red' ),
		array( 'value' => 'blue', 'label' => 'Blue' ),
	);
	return array(
		'key'      => 'group_bttest',
		'title'    => 'Project details',
		'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'bt_project' ) ) ),
		'position' => 'normal',
		'fields'   => array(
			bt_field( 'tab1', '', 'tab', array(), array( 'label' => 'Basics' ) ),
			bt_field( 'tagline', 'tagline', 'text', array( 'maxlength' => 80 ), array( 'default' => 'Untitled', 'width' => 50 ) ),
			bt_field( 'price', 'price', 'number', array( 'min' => 0, 'max' => 2000, 'step' => 0.5, 'prepend' => '$', 'append' => 'USD', 'format' => 'thousands' ), array( 'width' => 50 ) ),
			bt_field( 'body', 'body', 'wysiwyg' ),
			bt_field( 'color', 'color', 'select', array( 'choices' => $choices, 'return_format' => 'label' ), array( 'width' => 33 ) ),
			bt_field( 'tags', 'tags', 'checkbox', array( 'choices' => "a : Apple\nb : Banana", 'layout' => 'horizontal' ), array( 'width' => 33 ) ),
			bt_field( 'featured', 'featured', 'toggle', array( 'on_text' => 'Featured!', 'off_text' => 'Regular' ), array( 'width' => 33 ) ),
			bt_field(
				'discount',
				'discount',
				'number',
				array( 'append' => '%' ),
				array( 'conditions' => array( array( array( 'field' => 'field_btfeatured', 'operator' => '==', 'value' => '1' ) ) ) )
			),
			bt_field( 'tab2', '', 'tab', array(), array( 'label' => 'Dates & media' ) ),
			bt_field( 'launch', 'launch', 'date', array( 'display_format' => 'F j, Y', 'return_format' => 'd/m/Y' ), array( 'width' => 33 ) ),
			bt_field( 'starts', 'starts', 'datetime', array(), array( 'width' => 33 ) ),
			bt_field( 'opens', 'opens', 'time', array(), array( 'width' => 33 ) ),
			bt_field( 'brand', 'brand', 'color', array(), array( 'width' => 50 ) ),
			bt_field( 'volume', 'volume', 'range', array( 'min' => 0, 'max' => 10 ), array( 'width' => 50 ) ),
			bt_field( 'year', 'year', 'number', array(), array( 'width' => 50 ) ),
			bt_field( 'photo', 'photo', 'image', array(), array( 'width' => 50 ) ),
			bt_field( 'brochure', 'brochure', 'file', array(), array( 'width' => 50 ) ),
			bt_field( 'shots', 'shots', 'gallery' ),
			bt_field( 'video', 'video', 'oembed' ),
			bt_field( 'tab3', '', 'tab', array(), array( 'label' => 'Relations' ) ),
			bt_field( 'website', 'website', 'link' ),
			bt_field( 'related', 'related', 'relationship', array( 'post_types' => array( 'post', 'page' ) ) ),
			bt_field( 'lead', 'lead', 'post_object', array( 'post_types' => array( 'post' ) ), array( 'width' => 50 ) ),
			bt_field( 'owner', 'owner', 'user', array(), array( 'width' => 50 ) ),
			bt_field( 'kinds', 'kinds', 'taxonomy', array( 'taxonomy' => 'category', 'field_type' => 'checkbox', 'save_terms' => true ) ),
			bt_field( 'where', 'where', 'map' ),
			bt_field( 'tab4', '', 'tab', array(), array( 'label' => 'Team' ) ),
			bt_field(
				'team',
				'team',
				'repeater',
				array(
					'button_label' => 'Add member',
					'sub_fields'   => array(
						bt_field( 'teamname', 'name', 'text', array(), array( 'required' => true, 'width' => 50 ) ),
						bt_field( 'teamrole', 'role', 'select', array( 'choices' => array( array( 'value' => 'lead', 'label' => 'Lead' ), array( 'value' => 'dev', 'label' => 'Developer' ) ) ), array( 'width' => 50 ) ),
						bt_field( 'teamphoto', 'photo', 'image' ),
						bt_field( 'skills', 'skills', 'repeater', array( 'layout' => 'table', 'sub_fields' => array( bt_field( 'skill', 'skill', 'text' ) ) ) ),
					),
				)
			),
			bt_field(
				'address',
				'address',
				'group',
				array(
					'sub_fields' => array(
						bt_field( 'city', 'city', 'text', array(), array( 'width' => 50 ) ),
						bt_field( 'zip', 'zip', 'text', array(), array( 'width' => 50 ) ),
					),
				)
			),
			bt_field( 'email', 'email', 'email', array(), array( 'width' => 50 ) ),
			bt_field( 'homepage', 'homepage', 'url', array(), array( 'width' => 50 ) ),
			bt_field( 'secret', 'secret', 'password', array(), array( 'width' => 50 ) ),
			bt_field( 'notes', 'notes', 'textarea', array( 'new_lines' => 'wpautop' ), array( 'width' => 50 ) ),
			bt_field( 'msg', '', 'message', array( 'message' => 'Values here appear on the project page.' ) ),
		),
	);
}

function bt_index( $name ) {
	foreach ( bt_group_def()['fields'] as $i => $f ) {
		if ( $f['name'] === $name ) {
			return $i;
		}
	}
	return -1;
}

/**
 * An attachment backed by a real file in uploads.
 */
function bt_attachment( $filename, $mime = 'image/png' ) {
	$uploads = wp_upload_dir();
	$path    = trailingslashit( $uploads['path'] ) . $filename;
	if ( 'application/pdf' === $mime ) {
		file_put_contents( $path, "%PDF-1.4\n%BT test\n" ); // phpcs:ignore
	} elseif ( function_exists( 'imagecreatetruecolor' ) ) {
		$im = imagecreatetruecolor( 40, 30 );
		imagefill( $im, 0, 0, imagecolorallocate( $im, 40, 90, 200 ) );
		imagepng( $im, $path );
		imagedestroy( $im );
	} else {
		file_put_contents( $path, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==' ) ); // phpcs:ignore
	}
	$id = wp_insert_attachment(
		array(
			'post_mime_type' => $mime,
			'post_title'     => pathinfo( $filename, PATHINFO_FILENAME ),
			'post_status'    => 'inherit',
		),
		$path
	);
	$size = 'image/png' === $mime && function_exists( 'getimagesize' ) ? getimagesize( $path ) : array( 0, 0 );
	wp_update_attachment_metadata(
		$id,
		array(
			'width'  => (int) $size[0],
			'height' => (int) $size[1],
			'file'   => _wp_relative_upload_path( $path ),
			'sizes'  => array(),
		)
	);
	if ( 'image/png' === $mime ) {
		update_post_meta( $id, '_wp_attachment_image_alt', 'BT alt text' );
	}
	return (int) $id;
}

/**
 * Dispatch a REST request in-process.
 *
 * @param string       $method HTTP method.
 * @param string       $route  Route.
 * @param array|string $params Query params (GET/DELETE), or a body (array or JSON string).
 */
function bt_rest( $method, $route, $params = array() ) {
	$req = new WP_REST_Request( $method, $route );
	if ( in_array( $method, array( 'GET', 'DELETE' ), true ) ) {
		$req->set_query_params( (array) $params );
	} else {
		$req->set_header( 'content-type', 'application/json' );
		$req->set_body( is_string( $params ) ? $params : wp_json_encode( $params ) );
	}
	return rest_do_request( $req );
}

/**
 * Remove everything the suite creates (also after an aborted run).
 */
function bt_cleanup() {
	foreach ( Registry::KINDS as $kind => $option ) {
		$list = get_option( $option, array() );
		$list = is_array( $list ) ? $list : array();
		$keep = array();
		foreach ( $list as $def ) {
			$key = isset( $def['key'] ) ? (string) $def['key'] : '';
			if ( 0 === strpos( $key, 'bt_' ) || 0 === strpos( $key, 'group_bt' ) || ( 'groups' === $kind && isset( $def['title'] ) && in_array( $def['title'], array( 'Recipe details', 'BT REST group' ), true ) ) ) {
				continue;
			}
			$keep[] = $def;
		}
		update_option( $option, $keep );
	}
	Registry::reset_cache();
	global $wpdb;
	$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type LIKE 'bt\\_%' OR post_title LIKE 'BT %'" ); // phpcs:ignore WordPress.DB
	foreach ( $ids as $id ) {
		wp_delete_post( (int) $id, true );
	}
	foreach ( array( 'bt_kind', 'bt_cuisine', 'bt_tone', 'bt_venue' ) as $tax ) {
		$terms = $wpdb->get_col( $wpdb->prepare( "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", $tax ) ); // phpcs:ignore WordPress.DB
		foreach ( $terms as $term ) {
			if ( taxonomy_exists( $tax ) ) {
				wp_delete_term( (int) $term, $tax );
			}
		}
	}
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'brik\\_opt\\_bt\\_%'" ); // phpcs:ignore WordPress.DB
	$user = get_user_by( 'login', 'bt_sub' );
	if ( $user ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $user->ID );
	}
	delete_option( Registry::OPT_FLUSH );
}
