<?php
/**
 * MCP tools for versions, staging and scheduled publishing.
 *
 * @package Brik
 * @author  Diluk Angelo
 */

namespace Brik\Versions;

use Brik\Data;
use Brik\McpTools;
use Brik\ThemeBuilder;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Mcp {

	public static function init() {
		add_filter( 'brik/mcp_tools', array( __CLASS__, 'tools' ) );
		add_filter( 'brik/mcp_guide', array( __CLASS__, 'guide' ) );
	}

	public static function tools( array $d ) {
		$post_id = array(
			'type'        => 'integer',
			'description' => 'ID of the page, post, template or library item.',
			'minimum'     => 1,
		);
		$version = array(
			'type'        => 'integer',
			'description' => 'Version id from list_versions.',
			'minimum'     => 1,
		);
		$at = array(
			'type'        => 'string',
			'description' => 'When to run, e.g. "2026-10-05 09:00" (site timezone) or an ISO 8601 date with offset.',
		);

		$d['list_versions'] = array(
			'title'       => 'List versions',
			'description' => 'Version history of a Brik document (every save is recorded), newest first and grouped by day, with id, number, summary of what changed, source (builder, mcp, staging, deploy, restore, schedule), author and name/pin. Also returns the staging/publishing state and scheduled actions.',
			'read_only'   => true,
			'props'       => array(
				'post_id' => $post_id,
				'limit'   => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'maximum'     => 200,
					'description' => 'Maximum versions to return (default 30).',
				),
			),
			'required'    => array( 'post_id' ),
			'callback'    => array( __CLASS__, 'list_versions' ),
		);
		$d['get_version'] = array(
			'title'       => 'Get a version',
			'description' => 'One version of a document, including its full tree and page settings.',
			'read_only'   => true,
			'props'       => array(
				'post_id'    => $post_id,
				'version_id' => $version,
			),
			'required'    => array( 'post_id', 'version_id' ),
			'callback'    => array( __CLASS__, 'get_version' ),
		);
		$d['compare_versions'] = array(
			'title'       => 'Compare versions',
			'description' => 'Section-level comparison of two states of a document. a/b are version ids or "live" (alias "current") or "staging". Returns each top-level section with status added/removed/changed/unchanged (relative to a → b) and a summary of changed attributes, e.g. "Heading typography changed".',
			'read_only'   => true,
			'props'       => array(
				'post_id' => $post_id,
				'a'       => array(
					'type'        => array( 'string', 'integer' ),
					'description' => 'Base side (default "live").',
				),
				'b'       => array(
					'type'        => array( 'string', 'integer' ),
					'description' => 'Other side (default "staging").',
				),
			),
			'required'    => array( 'post_id' ),
			'callback'    => array( __CLASS__, 'compare_versions' ),
		);
		$d['restore_version'] = array(
			'title'       => 'Restore a version',
			'description' => 'Restore a version onto the live page (default) or onto staging. Pass "sections" (top-level section ids from compare_versions) to restore only those sections: changed sections are replaced, removed ones re-inserted at their old position, sections that did not exist in the version are removed. The restore itself is recorded as a new version.',
			'destructive' => true,
			'props'       => array(
				'post_id'    => $post_id,
				'version_id' => $version,
				'sections'   => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'string' ),
					'description' => 'Only restore these top-level section ids.',
				),
				'target'     => array(
					'type' => 'string',
					'enum' => array( 'live', 'staging' ),
				),
			),
			'required'    => array( 'post_id', 'version_id' ),
			'callback'    => array( __CLASS__, 'restore_version' ),
		);
		$d['save_staging'] = array(
			'title'       => 'Save to staging',
			'description' => 'Save a tree as the STAGING copy of a document. The live page is not changed; staging can be reviewed at staging_preview_link and published with deploy_staging. Omit "tree" to copy the live tree into staging as a starting point.',
			'props'       => array(
				'post_id'       => $post_id,
				'tree'          => array(
					'type'        => 'array',
					'description' => 'Brik tree (same format as update_page).',
					'items'       => array( 'type' => 'object' ),
				),
				'page_settings' => array(
					'type'        => 'object',
					'description' => 'Page settings for the staging copy (dark, hide_title, body_class, custom_css).',
				),
			),
			'required'    => array( 'post_id' ),
			'callback'    => array( __CLASS__, 'save_staging' ),
		);
		$d['deploy_staging'] = array(
			'title'       => 'Deploy staging',
			'description' => 'Publish the staging copy: copies it to the live page (publishing the post if it was a draft), records a "deploy" version and clears staging.',
			'destructive' => true,
			'props'       => array( 'post_id' => $post_id ),
			'required'    => array( 'post_id' ),
			'callback'    => array( __CLASS__, 'deploy_staging' ),
		);
		$d['schedule_deploy'] = array(
			'title'       => 'Schedule a deploy',
			'description' => 'Deploy the staging copy automatically at a date and time (whatever is in staging at that moment goes live).',
			'props'       => array(
				'post_id' => $post_id,
				'at'      => $at,
			),
			'required'    => array( 'post_id', 'at' ),
			'callback'    => array( __CLASS__, 'schedule_deploy' ),
		);
		$d['schedule_rollback'] = array(
			'title'       => 'Schedule a rollback',
			'description' => 'Restore a version onto the live page at a date and time, e.g. to end a promotion.',
			'props'       => array(
				'post_id'    => $post_id,
				'version_id' => $version,
				'at'         => $at,
			),
			'required'    => array( 'post_id', 'version_id', 'at' ),
			'callback'    => array( __CLASS__, 'schedule_rollback' ),
		);
		$d['cancel_schedule'] = array(
			'title'       => 'Cancel a scheduled action',
			'description' => 'Cancel a scheduled deploy or rollback (ids from list_versions → state.schedules).',
			'props'       => array(
				'post_id'     => $post_id,
				'schedule_id' => array( 'type' => 'string' ),
			),
			'required'    => array( 'post_id', 'schedule_id' ),
			'callback'    => array( __CLASS__, 'cancel_schedule' ),
		);
		$d['staging_preview_link'] = array(
			'title'       => 'Staging preview link',
			'description' => 'Secret link that shows the staging copy to anyone who has it (no login needed), for client review. regenerate: true invalidates old links.',
			'props'       => array(
				'post_id'    => $post_id,
				'regenerate' => array( 'type' => 'boolean' ),
			),
			'required'    => array( 'post_id' ),
			'callback'    => array( __CLASS__, 'staging_preview_link' ),
		);
		return $d;
	}

	/**
	 * The document, if the current user may edit it with Brik.
	 */
	private static function doc( $id, $publish = false ) {
		$post = get_post( (int) $id );
		if ( ! $post || 'trash' === $post->post_status ) {
			/* translators: %d: post id */
			return new WP_Error( 'brik_not_found', sprintf( __( 'No post with id %d.', 'brik-builder' ), (int) $id ) );
		}
		if ( ! Endpoints::can_edit( $post->ID ) || ( ThemeBuilder::is_template( $post->ID ) && ! current_user_can( 'edit_theme_options' ) ) ) {
			/* translators: %d: post id */
			return new WP_Error( 'brik_forbidden', sprintf( __( 'You are not allowed to edit post %d with Brik.', 'brik-builder' ), $post->ID ) );
		}
		if ( $publish && ! Endpoints::can_publish( $post->ID ) ) {
			return new WP_Error( 'brik_forbidden', __( 'Deploying and scheduling require permission to publish this content.', 'brik-builder' ) );
		}
		return $post;
	}

	public static function list_versions( array $a ) {
		$post = self::doc( $a['post_id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$limit  = isset( $a['limit'] ) ? (int) $a['limit'] : 30;
		$groups = array();
		$n      = 0;
		foreach ( Versions::grouped( $post->ID ) as $group ) {
			$items = array();
			foreach ( $group['items'] as $item ) {
				if ( $n++ >= $limit ) {
					break;
				}
				unset( $item['author']['avatar'] );
				$items[] = $item;
			}
			if ( $items ) {
				$group['items'] = $items;
				$groups[]       = $group;
			}
		}
		return array(
			'post_id' => $post->ID,
			'groups'  => $groups,
			'state'   => Staging::state( $post->ID ),
		);
	}

	public static function get_version( array $a ) {
		$post = self::doc( $a['post_id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$v = Versions::get( $a['version_id'], $post->ID );
		if ( ! $v ) {
			return new WP_Error( 'brik_not_found', __( 'Version not found.', 'brik-builder' ) );
		}
		$out = Versions::payload( $v, true );
		unset( $out['author']['avatar'] );
		return $out;
	}

	public static function compare_versions( array $a ) {
		$post = self::doc( $a['post_id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		return Endpoints::compare_refs( $post->ID, isset( $a['a'] ) ? $a['a'] : 'live', isset( $a['b'] ) ? $a['b'] : 'staging' );
	}

	public static function restore_version( array $a ) {
		$target = isset( $a['target'] ) ? $a['target'] : 'live';
		$post   = self::doc( $a['post_id'], 'live' === $target && 'publish' === get_post_status( (int) $a['post_id'] ) );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$sections = ! empty( $a['sections'] ) ? array_map( 'strval', (array) $a['sections'] ) : null;
		$result   = Versions::restore( $post->ID, $a['version_id'], $sections, $target, null, 'restore' );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return array(
			'restored' => true,
			'target'   => $target,
			'sections' => $sections ? $sections : 'all',
			'outline'  => self::outline( $result[0] ),
			'state'    => Staging::state( $post->ID ),
		);
	}

	public static function save_staging( array $a ) {
		$post = self::doc( $a['post_id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( isset( $a['tree'] ) ) {
			$tree = json_decode( wp_json_encode( $a['tree'] ), true );
		} else {
			$tree = Staging::exists( $post->ID ) ? Staging::tree( $post->ID ) : Data::get( $post->ID );
		}
		$page = isset( $a['page_settings'] ) ? array_merge( Staging::page( $post->ID ), (array) $a['page_settings'] ) : null;
		$tree = Staging::save( $post->ID, is_array( $tree ) ? $tree : array(), $page);
		return array(
			'post_id'     => $post->ID,
			'saved'       => 'staging',
			'outline'     => self::outline( $tree ),
			'preview_url' => Staging::preview_url( $post->ID ),
			'state'       => Staging::state( $post->ID ),
			'note'        => __( 'The live page is unchanged. Call deploy_staging to publish.', 'brik-builder' ),
		);
	}

	public static function deploy_staging( array $a ) {
		$post = self::doc( $a['post_id'], true );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$live = Staging::deploy( $post->ID );
		if ( is_wp_error( $live ) ) {
			return $live;
		}
		return array(
			'deployed' => true,
			'post_id'  => $post->ID,
			'status'   => get_post_status( $post->ID ),
			'url'      => get_permalink( $post->ID ),
			'outline'  => self::outline( $live ),
		);
	}

	public static function schedule_deploy( array $a ) {
		$post = self::doc( $a['post_id'], true );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$entry = Schedule::add( $post->ID, 'deploy', $a['at'] );
		return is_wp_error( $entry ) ? $entry : array(
			'scheduled' => $entry,
			'timezone'  => wp_timezone_string(),
		);
	}

	public static function schedule_rollback( array $a ) {
		$post = self::doc( $a['post_id'], true );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$entry = Schedule::add( $post->ID, 'rollback', $a['at'], (int) $a['version_id'] );
		return is_wp_error( $entry ) ? $entry : array(
			'scheduled' => $entry,
			'timezone'  => wp_timezone_string(),
		);
	}

	public static function cancel_schedule( array $a ) {
		$post = self::doc( $a['post_id'], true );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( ! Schedule::cancel( $post->ID, (string) $a['schedule_id'] ) ) {
			return new WP_Error( 'brik_not_found', __( 'Scheduled action not found.', 'brik-builder' ) );
		}
		return array(
			'cancelled' => true,
			'schedules' => Schedule::all( $post->ID ),
		);
	}

	public static function staging_preview_link( array $a ) {
		$post = self::doc( $a['post_id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( ! empty( $a['regenerate'] ) ) {
			Staging::token( $post->ID, true );
		}
		if ( ! Staging::exists( $post->ID ) ) {
			McpTools::warn( __( 'This document has no staging copy yet; the link shows the live page until save_staging is used.', 'brik-builder' ) );
		}
		return array(
			'post_id'     => $post->ID,
			'preview_url' => Staging::preview_url( $post->ID ),
			'staging'     => Staging::exists( $post->ID ),
		);
	}

	/**
	 * Compact list of top-level sections for tool results.
	 */
	private static function outline( array $tree ) {
		$out = array();
		foreach ( array_values( $tree ) as $i => $node ) {
			$out[] = array(
				'id'   => $node['id'],
				'name' => Diff::section_name( $node, $i ),
			);
		}
		return $out;
	}

	public static function guide( $guide ) {
		return $guide . <<<'MD'


## Versions, staging and publishing

Every save of a Brik document is recorded as a version (list_versions, get_version). Versions
carry a summary such as "Changed typography in Features" and a source badge. Restore a whole
version or only some top-level sections with restore_version (sections: [ids]); compare any two
states with compare_versions (a/b: version id, "live" or "staging").

Staging: save_staging writes a separate staging copy that visitors don't see; the live page and
its post_content stay untouched. Share staging_preview_link with reviewers (works logged out),
then deploy_staging to publish. For timed releases use schedule_deploy / schedule_rollback
(dates in the site timezone) and cancel_schedule. Prefer staging + preview for big changes on
pages that are already published.
MD;
	}
}
