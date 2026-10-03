<?php
/**
 * Code: raw HTML, CSS or JavaScript embed.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'code',
	'title'       => __( 'Code', 'brik-builder' ),
	'category'    => 'basic',
	'icon'        => 'code-xml',
	'description' => 'Raw HTML embed (may include <style> and <script>). code: markup output as-is. Users without unfiltered_html get it sanitized on save.',
	'fields'      => array(
		'code' => Fields::field(
			'code',
			__( 'Code', 'brik-builder' ),
			'content',
			array(
				'language' => 'html',
				'default'  => '<div style="padding:1.5rem;border:1px dashed var(--border);border-radius:var(--radius);text-align:center;color:var(--muted-foreground)">' . esc_html__( 'Custom HTML goes here', 'brik-builder' ) . '</div>',
			)
		),
	),
	'render'      => static function ( $a, $ctx ) {
		$code = (string) $a['code'];
		if ( '' === trim( $code ) ) {
			return $ctx->placeholder( __( 'Add HTML, CSS or JavaScript', 'brik-builder' ) );
		}
		// Shortcodes are expanded too, matching the classic Custom HTML widget.
		return do_shortcode( $code );
	},
);
