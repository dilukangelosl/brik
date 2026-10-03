<?php
namespace Brik\Content;

defined( 'ABSPATH' ) || exit;

/**
 * Export definitions as JSON (for import elsewhere) or as PHP code for a theme or plugin.
 */
final class Export {

	/**
	 * Definitions without runtime-only keys.
	 */
	public static function json( array $only = array() ) {
		$data = Registry::export_data( $only );
		foreach ( array( 'post_types', 'taxonomies', 'groups' ) as $kind ) {
			foreach ( $data[ $kind ] as $i => $def ) {
				unset( $data[ $kind ][ $i ]['local'] );
			}
		}
		$data['generator'] = 'Brik ' . BRIK_VERSION;
		$data['exported']  = gmdate( 'c' );
		return $data;
	}

	public static function php( array $only = array() ) {
		$data  = self::json( $only );
		$lines = array( '<?php', '/**', ' * Content types and fields exported from Brik on ' . gmdate( 'Y-m-d' ) . '.', ' */', '' );

		if ( $data['post_types'] || $data['taxonomies'] ) {
			$lines[] = "add_action(\n\t'init',\n\tfunction () {";
			foreach ( $data['taxonomies'] as $def ) {
				if ( empty( $def['active'] ) ) {
					continue;
				}
				$types = $def['post_types'];
				foreach ( $data['post_types'] as $type ) {
					if ( in_array( $def['key'], $type['taxonomies'], true ) ) {
						$types[] = $type['key'];
					}
				}
				$args = Register::taxonomy_args( $def );
				unset( $args['brik_content'] );
				$lines[] = "\t\tregister_taxonomy(\n\t\t\t" . self::value( $def['key'], 3 ) . ",\n\t\t\t" . self::value( array_values( array_unique( $types ) ), 3 ) . ",\n\t\t\t" . self::value( $args, 3 ) . "\n\t\t);\n";
			}
			foreach ( $data['post_types'] as $def ) {
				if ( empty( $def['active'] ) ) {
					continue;
				}
				$args = Register::post_type_args( $def );
				unset( $args['brik_content'] );
				$lines[] = "\t\tregister_post_type(\n\t\t\t" . self::value( $def['key'], 3 ) . ",\n\t\t\t" . self::value( $args, 3 ) . "\n\t\t);\n";
			}
			$lines[] = "\t},\n\t0\n);\n";

			$builder = array();
			foreach ( $data['post_types'] as $def ) {
				if ( ! empty( $def['active'] ) && ! empty( $def['brik'] ) ) {
					$builder[] = $def['key'];
				}
			}
			if ( $builder ) {
				$lines[] = '// Edit these types with the Brik builder.';
				$lines[] = "add_filter(\n\t'brik/post_types',\n\tfunction ( \$types ) {\n\t\treturn array_merge( \$types, " . self::value( $builder, 2 ) . " );\n\t}\n);\n";
			}
		}

		foreach ( $data['groups'] as $group ) {
			unset( $group['version'] );
			$lines[] = "if ( function_exists( 'brik_register_field_group' ) ) {\n\tbrik_register_field_group(\n\t\t" . self::value( $group, 2 ) . "\n\t);\n}\n";
		}
		return implode( "\n", $lines );
	}

	/**
	 * PHP literal in WordPress style (long array syntax, tabs).
	 */
	public static function value( $v, $depth = 0 ) {
		if ( is_array( $v ) ) {
			if ( ! $v ) {
				return 'array()';
			}
			$pad  = str_repeat( "\t", $depth + 1 );
			$list = array_keys( $v ) === range( 0, count( $v ) - 1 );
			$out  = array();
			$wide = 0;
			if ( ! $list ) {
				foreach ( array_keys( $v ) as $k ) {
					$wide = max( $wide, strlen( var_export( (string) $k, true ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
				}
			}
			foreach ( $v as $k => $item ) {
				$literal = self::value( $item, $depth + 1 );
				if ( $list ) {
					$out[] = $pad . $literal . ',';
				} else {
					$key   = var_export( (string) $k, true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
					$out[] = $pad . str_pad( $key, $wide ) . ' => ' . $literal . ',';
				}
			}
			return "array(\n" . implode( "\n", $out ) . "\n" . str_repeat( "\t", $depth ) . ')';
		}
		if ( is_bool( $v ) ) {
			return $v ? 'true' : 'false';
		}
		if ( null === $v ) {
			return 'null';
		}
		if ( is_int( $v ) || is_float( $v ) ) {
			return (string) $v;
		}
		return var_export( (string) $v, true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
	}
}
