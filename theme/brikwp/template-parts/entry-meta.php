<?php
/**
 * Byline. Pass array( 'context' => 'card' ) for the compact version.
 *
 * @package Brik
 */

defined( 'ABSPATH' ) || exit;

brik_theme_entry_meta( isset( $args['context'] ) ? $args['context'] : 'single' );
