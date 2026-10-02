#!/usr/bin/env bash
# Create or update a published page from a Brik tree JSON file and print its URL.
# Usage: dev/brik-page.sh <slug> <tree.json> [post_type]
set -e
cd "$(dirname "$0")"
slug="$1"; file="$2"; type="${3:-page}"
TREE="$(cat "$file")" docker compose run --rm -T -e TREE -e SLUG="$slug" -e PTYPE="$type" cli eval '
$slug = getenv("SLUG"); $type = getenv("PTYPE");
wp_set_current_user( 1 );
$post = get_page_by_path( $slug, OBJECT, $type );
$id = $post ? $post->ID : wp_insert_post( array( "post_type" => $type, "post_name" => $slug, "post_title" => ucwords( str_replace( "-", " ", $slug ) ), "post_status" => "publish" ) );
$tree = json_decode( getenv("TREE"), true );
if ( null === $tree ) { fwrite( STDERR, "Invalid JSON\n" ); exit( 1 ); }
Brik\Data::save( $id, $tree );
echo get_permalink( $id ), "\n";
' 2>&1 | grep -v -E "Container|Creating|Created|Starting|Started|Running"
