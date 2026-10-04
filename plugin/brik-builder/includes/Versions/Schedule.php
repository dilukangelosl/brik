<?php
/**
 * Scheduled deploys and rollbacks.
 *
 * @package Brik
 * @author  Diluk Angelo
 */

namespace Brik\Versions;

defined( 'ABSPATH' ) || exit;

/**
 * Each scheduled action is a single wp-cron event plus an entry in the document's
 * _brik_schedules meta (so the builder can list and cancel them).
 */
final class Schedule {

	const META = '_brik_schedules';
	const HOOK = 'brik_versions_scheduled';

	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'run' ), 10, 2 );
		add_action( 'before_delete_post', array( __CLASS__, 'clear' ) );
	}

	/** Drop every pending action of a document (it is being deleted). */
	public static function clear( $post_id ) {
		foreach ( self::raw( $post_id ) as $entry ) {
			wp_unschedule_event( $entry['time'], self::HOOK, array( (int) $post_id, $entry['id'] ) );
		}
	}

	private static function raw( $post_id ) {
		$items = get_post_meta( $post_id, self::META, true );
		return is_array( $items ) ? $items : array();
	}

	/**
	 * Parse a date-time. Strings without a timezone are read in the site timezone.
	 *
	 * @return int|null Unix timestamp.
	 */
	public static function parse_time( $value ) {
		if ( is_numeric( $value ) ) {
			return (int) $value;
		}
		try {
			$date = new \DateTimeImmutable( (string) $value, wp_timezone() );
		} catch ( \Exception $e ) {
			return null;
		}
		return $date->getTimestamp();
	}

	/**
	 * @param string $action deploy|rollback.
	 * @return array|\WP_Error The schedule entry.
	 */
	public static function add( $post_id, $action, $time, $version_id = 0 ) {
		$ts = self::parse_time( $time );
		if ( ! $ts ) {
			return new \WP_Error( 'brik_invalid', __( 'Invalid date or time.', 'brik-builder' ), array( 'status' => 400 ) );
		}
		if ( $ts < time() - 60 ) {
			return new \WP_Error( 'brik_invalid', __( 'Pick a time in the future.', 'brik-builder' ), array( 'status' => 400 ) );
		}
		if ( 'rollback' === $action ) {
			if ( ! Versions::get( $version_id, $post_id ) ) {
				return new \WP_Error( 'brik_not_found', __( 'Version not found.', 'brik-builder' ), array( 'status' => 404 ) );
			}
		} elseif ( 'deploy' !== $action ) {
			return new \WP_Error( 'brik_invalid', __( 'Unknown action.', 'brik-builder' ), array( 'status' => 400 ) );
		} elseif ( ! Staging::exists( $post_id ) ) {
			return new \WP_Error( 'brik_no_staging', __( 'Save changes to staging before scheduling a deploy.', 'brik-builder' ), array( 'status' => 400 ) );
		}

		$entry = array(
			'id'      => substr( md5( uniqid( '', true ) ), 0, 10 ),
			'action'  => $action,
			'time'    => $ts,
			'version' => 'rollback' === $action ? (int) $version_id : 0,
			'user'    => get_current_user_id(),
			'created' => time(),
		);
		$items   = self::raw( $post_id );
		$items[] = $entry;
		update_post_meta( $post_id, self::META, $items );
		wp_schedule_single_event( $ts, self::HOOK, array( (int) $post_id, $entry['id'] ) );
		return self::describe( $post_id, $entry );
	}

	public static function cancel( $post_id, $id ) {
		$items = self::raw( $post_id );
		$found = false;
		foreach ( $items as $k => $entry ) {
			if ( $entry['id'] === $id ) {
				wp_unschedule_event( $entry['time'], self::HOOK, array( (int) $post_id, $entry['id'] ) );
				unset( $items[ $k ] );
				$found = true;
			}
		}
		update_post_meta( $post_id, self::META, array_values( $items ) );
		return $found;
	}

	/**
	 * Cron callback. Runs as the user who scheduled it, so sanitizing keeps the same rules.
	 */
	public static function run( $post_id, $id ) {
		$entry = null;
		foreach ( self::raw( $post_id ) as $item ) {
			if ( $item['id'] === $id ) {
				$entry = $item;
			}
		}
		if ( ! $entry ) {
			return;
		}
		// Also covers runs triggered by hand before the event's time.
		wp_unschedule_event( $entry['time'], self::HOOK, array( (int) $post_id, $entry['id'] ) );
		$items = array_values(
			array_filter(
				self::raw( $post_id ),
				static function ( $item ) use ( $id ) {
					return $item['id'] !== $id;
				}
			)
		);
		update_post_meta( $post_id, self::META, $items );

		$previous_user = get_current_user_id();
		wp_set_current_user( (int) $entry['user'] );
		if ( ! Endpoints::can_publish( $post_id ) ) {
			wp_set_current_user( $previous_user );
			return;
		}
		if ( 'deploy' === $entry['action'] ) {
			if ( Staging::exists( $post_id ) ) {
				Staging::deploy( $post_id, 'schedule' );
			}
		} else {
			Versions::restore( $post_id, (int) $entry['version'], null, 'live', null, 'schedule' );
		}
		wp_set_current_user( $previous_user );
		do_action( 'brik/schedule_ran', $post_id, $entry );
	}

	public static function describe( $post_id, array $entry ) {
		$version = $entry['version'] ? Versions::get( $entry['version'], $post_id ) : null;
		$user    = get_userdata( (int) $entry['user'] );
		return array(
			'id'          => $entry['id'],
			'action'      => $entry['action'],
			'time'        => gmdate( 'c', $entry['time'] ),
			'local'       => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $entry['time'] ),
			'in'          => $entry['time'] > time() ? human_time_diff( $entry['time'] ) : __( 'due now', 'brik-builder' ),
			'version'     => (int) $entry['version'],
			'version_num' => $version ? (int) get_post_meta( $version->ID, Versions::M_NUMBER, true ) : 0,
			'by'          => $user ? $user->display_name : '',
		);
	}

	/** Versions a pending rollback points at; pruning keeps them. */
	public static function referenced_versions( $post_id ) {
		return array_filter( array_map( 'intval', wp_list_pluck( self::raw( $post_id ), 'version' ) ) );
	}

	public static function all( $post_id ) {
		$items = self::raw( $post_id );
		usort(
			$items,
			static function ( $a, $b ) {
				return $a['time'] - $b['time'];
			}
		);
		return array_map(
			static function ( $entry ) use ( $post_id ) {
				return self::describe( $post_id, $entry );
			},
			$items
		);
	}
}
