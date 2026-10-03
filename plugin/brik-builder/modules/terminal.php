<?php
/**
 * Terminal: a window that types commands and prints output line by line.
 *
 * All lines are in the markup from the start (and keep their space), so the window never
 * changes height while it plays and the text is readable without JavaScript.
 *
 * @package Brik
 * @author  Diluk Angelo
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'terminal',
	'title'       => __( 'Terminal', 'brik-builder' ),
	'category'    => 'effects',
	'icon'        => 'terminal',
	'description' => 'Terminal window that types commands and reveals output lines in order. lines: repeater [{type: command|output|success|error|info|comment, text, delay (ms before the line, optional)}]. title: window title. prompt: prompt symbol for commands (default $). typing_speed: ms per character. line_delay: ms between lines. loop: bool; loop_pause: ms before replaying. theme: dark|auto (follows the section colors). chrome: bool, window buttons. trigger: in-view|load. Reduced motion shows all lines at once.',
	'fields'      => array_merge(
		array(
			'lines'        => Fields::field(
				'repeater',
				__( 'Lines', 'brik-builder' ),
				'content',
				array(
					'title_field' => 'text',
					'fields'      => array(
						'type'  => Fields::field( 'select', __( 'Type', 'brik-builder' ), 'content', array( 'default' => 'output', 'options' => Fields::opts( array( 'command' => __( 'Command (typed)', 'brik-builder' ), 'output' => __( 'Output', 'brik-builder' ), 'success' => __( 'Success', 'brik-builder' ), 'error' => __( 'Error', 'brik-builder' ), 'info' => __( 'Info', 'brik-builder' ), 'comment' => __( 'Comment', 'brik-builder' ) ) ) ) ),
						'text'  => Fields::field( 'text', __( 'Text', 'brik-builder' ) ),
						'delay' => Fields::field( 'number', __( 'Delay before (ms)', 'brik-builder' ) ),
					),
					'default'     => array(
						array( 'type' => 'command', 'text' => 'npx create-brik-site my-site' ),
						array( 'type' => 'output', 'text' => 'Downloading template…' ),
						array( 'type' => 'success', 'text' => 'Installed WordPress 6.8 and Brik Builder' ),
						array( 'type' => 'success', 'text' => 'Imported design system "Midnight"' ),
						array( 'type' => 'success', 'text' => 'Created 6 pages and 2 templates' ),
						array( 'type' => 'info', 'text' => 'Starting dev server on http://localhost:8888' ),
						array( 'type' => 'command', 'text' => 'brik deploy --prod', 'delay' => 700 ),
						array( 'type' => 'success', 'text' => 'Live at https://my-site.dev in 4.2s' ),
					),
				)
			),
			'title'        => Fields::field( 'text', __( 'Window title', 'brik-builder' ), 'content', array( 'default' => 'zsh — my-site' ) ),
			'prompt'       => Fields::field( 'text', __( 'Prompt', 'brik-builder' ), 'content', array( 'default' => '$' ) ),
			'typing_speed' => Fields::field( 'range', __( 'Typing speed (ms per character)', 'brik-builder' ), 'content', array( 'default' => 45, 'min' => 10, 'max' => 200, 'step' => 5 ) ),
			'line_delay'   => Fields::field( 'range', __( 'Delay between lines (ms)', 'brik-builder' ), 'content', array( 'default' => 380, 'min' => 0, 'max' => 3000, 'step' => 20 ) ),
			'loop'         => Fields::field( 'toggle', __( 'Loop', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'loop_pause'   => Fields::field( 'range', __( 'Pause before replay (ms)', 'brik-builder' ), 'content', array( 'default' => 4000, 'min' => 500, 'max' => 15000, 'step' => 250, 'show_if' => array( 'loop' => true ) ) ),
			'trigger'      => Fields::field( 'select', __( 'Start', 'brik-builder' ), 'content', array( 'default' => 'in-view', 'options' => Fields::opts( array( 'in-view' => __( 'When scrolled into view', 'brik-builder' ), 'load' => __( 'On page load', 'brik-builder' ) ) ) ) ),
			'theme'        => Fields::field( 'select', __( 'Theme', 'brik-builder' ), 'content', array( 'default' => 'dark', 'options' => Fields::opts( array( 'dark' => __( 'Always dark', 'brik-builder' ), 'auto' => __( 'Follow section colors', 'brik-builder' ) ) ) ) ),
			'chrome'       => Fields::field( 'toggle', __( 'Window buttons', 'brik-builder' ), 'content', array( 'default' => true ) ),
		),
		Fields::box( 'window', __( 'Window', 'brik-builder' ), Fields::WRAP . ' .brik-term' ),
		Fields::typography( 'code', __( 'Code', 'brik-builder' ), Fields::WRAP . ' .brik-term-body' )
	),
	'render'      => static function ( $a, $ctx ) {
		$types  = array(
			'command' => '',
			'output'  => '',
			'success' => 'check',
			'error'   => 'x',
			'info'    => 'info',
			'comment' => '',
		);
		$prompt = '' !== trim( (string) $a['prompt'] ) ? wp_strip_all_tags( $a['prompt'] ) : '$';
		$lines  = '';
		foreach ( brik_items( $a['lines'] ) as $line ) {
			$text = (string) brik_item( $line, 'text' );
			if ( '' === trim( $text ) ) {
				continue;
			}
			$type  = isset( $types[ brik_item( $line, 'type' ) ] ) ? $line['type'] : 'output';
			$delay = is_numeric( brik_item( $line, 'delay' ) ) ? ' data-delay="' . (int) $line['delay'] . '"' : '';
			$lead  = 'command' === $type
				? '<span class="brik-term-prompt" aria-hidden="true">' . esc_html( $prompt ) . '</span>'
				: ( $types[ $type ] ? brik_icon( $types[ $type ], 'brik-term-mark size-[1.05em] shrink-0' ) : '' );
			$lines .= '<div class="brik-term-line brik-term-line--' . $type . '"' . $delay . '>' . $lead . '<span class="brik-term-text">' . esc_html( $text ) . '</span></div>';
		}
		if ( '' === $lines ) {
			return $ctx->placeholder( __( 'Add terminal lines', 'brik-builder' ) );
		}
		$ctx->script( 'terminal' );

		$head = '';
		if ( ! empty( $a['chrome'] ) || '' !== trim( (string) $a['title'] ) ) {
			$head = '<div class="brik-term-head" aria-hidden="true">'
				. ( ! empty( $a['chrome'] ) ? '<span class="brik-term-dots"><i></i><i></i><i></i></span>' : '' )
				. '<span class="brik-term-title">' . esc_html( wp_strip_all_tags( (string) $a['title'] ) ) . '</span></div>';
		}
		$data = array(
			'class'        => 'brik-term brik-term--' . ( 'auto' === $a['theme'] ? 'auto' : 'dark' ),
			'role'         => 'group',
			'aria-label'   => '' !== trim( (string) $a['title'] ) ? wp_strip_all_tags( $a['title'] ) : __( 'Terminal', 'brik-builder' ),
			'data-speed'   => (int) brik_fx_num( $a['typing_speed'], 45, 10, 200 ),
			'data-gap'     => (int) brik_fx_num( $a['line_delay'], 380, 0, 3000 ),
			'data-loop'    => ! empty( $a['loop'] ) ? '1' : '0',
			'data-pause'   => (int) brik_fx_num( $a['loop_pause'], 4000, 500, 15000 ),
			'data-trigger' => 'load' === $a['trigger'] ? 'load' : 'in-view',
		);
		return '<div' . brik_attrs( $data ) . '>' . $head . '<div class="brik-term-body">' . $lines . '</div></div>';
	},
);
