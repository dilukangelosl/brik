<?php
/**
 * Comments: the comment list and reply form of the current post.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'comments',
	'title'       => __( 'Comments', 'brik-builder' ),
	'category'    => 'post',
	'icon'        => 'messages-square',
	'description' => 'Comment list and reply form for the current post (singular views only; a preview is shown in the builder). Uses the theme\'s comments.php when it has one. show_title: bool. form_title: heading above the form. button_variant: submit button style (default|secondary|outline).',
	'fields'      => array_merge(
		array(
			'show_title'     => Fields::field( 'toggle', __( 'Show comment count heading', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'form_title'     => Fields::field( 'text', __( 'Form title', 'brik-builder' ), 'content', array( 'default' => __( 'Leave a comment', 'brik-builder' ) ) ),
			'button_text'    => Fields::field( 'text', __( 'Button text', 'brik-builder' ), 'content', array( 'default' => __( 'Post comment', 'brik-builder' ) ) ),
			'button_variant' => Fields::field( 'select', __( 'Button style', 'brik-builder' ), 'content', array( 'default' => 'default', 'options' => Fields::opts( brik_button_variants_labels() ) ) ),
		),
		Fields::typography( 'heading', __( 'Headings', 'brik-builder' ), Fields::WRAP . ' :is(.brik-comments-title,.comment-reply-title)' ),
		Fields::typography( 'comment', __( 'Comment text', 'brik-builder' ), Fields::WRAP . ' .comment-content' ),
		Fields::box( 'input', __( 'Form fields', 'brik-builder' ), Fields::WRAP . ' .brik-comments :is(input[type=text],input[type=email],input[type=url],textarea)', array( 'bg', 'color', 'border_color', 'radius' ) ),
		Fields::box( 'form', __( 'Form box', 'brik-builder' ), Fields::WRAP . ' .comment-respond', array( 'bg', 'border_width', 'border_color', 'radius', 'padding', 'shadow' ) )
	),
	'render'      => static function ( $a, $ctx ) {
		$heading = static function ( $count ) {
			/* translators: %s: number of comments */
			return '<h2 class="brik-comments-title">' . esc_html( sprintf( _n( '%s comment', '%s comments', $count, 'brik-builder' ), number_format_i18n( $count ) ) ) . '</h2>';
		};
		$button  = '' !== trim( (string) $a['button_text'] ) ? wp_strip_all_tags( $a['button_text'] ) : __( 'Post comment', 'brik-builder' );
		$btn_cls = brik_button_class( $a['button_variant'], 'default', 'submit' );

		if ( $ctx->canvas ) {
			$avatar = static function ( $name ) {
				return '<span class="avatar brik-avatar-initials" aria-hidden="true">' . esc_html( brik_site_initials( $name ) ) . '</span>';
			};
			$comment = static function ( $name, $when, $text, $children = '' ) use ( $avatar ) {
				return '<li class="comment"><article class="comment-body"><footer class="comment-meta"><div class="comment-author vcard">' . $avatar( $name ) . '<b class="fn">' . esc_html( $name ) . '</b></div><div class="comment-metadata"><time>' . esc_html( $when ) . '</time></div></footer><div class="comment-content"><p>' . esc_html( $text ) . '</p></div><div class="reply"><a class="comment-reply-link" href="#">' . esc_html__( 'Reply', 'brik-builder' ) . '</a></div></article>' . $children . '</li>';
			};
			$list = $comment(
				'Alex Rivera',
				__( '2 days ago', 'brik-builder' ),
				__( 'This was exactly what I needed. The section on getting started cleared up a lot for me.', 'brik-builder' ),
				'<ol class="children">' . $comment( 'Sam Lee', __( '1 day ago', 'brik-builder' ), __( 'Same here, thanks for writing it up.', 'brik-builder' ) ) . '</ol>'
			);
			$form = '<div class="comment-respond"><h3 class="comment-reply-title"' . $ctx->inline( 'form_title' ) . '>' . brik_inline( $a['form_title'] ) . '</h3><form class="comment-form" action="#">'
				. '<p class="comment-notes">' . esc_html__( 'Your email address will not be published. Required fields are marked *', 'brik-builder' ) . '</p>'
				. '<p class="comment-form-comment"><label>' . esc_html__( 'Comment', 'brik-builder' ) . ' *</label><textarea rows="5" tabindex="-1"></textarea></p>'
				. '<p class="comment-form-author"><label>' . esc_html__( 'Name', 'brik-builder' ) . ' *</label><input type="text" tabindex="-1"></p>'
				. '<p class="comment-form-email"><label>' . esc_html__( 'Email', 'brik-builder' ) . ' *</label><input type="email" tabindex="-1"></p>'
				. '<p class="form-submit"><button type="button" class="' . esc_attr( $btn_cls ) . '" tabindex="-1">' . esc_html( $button ) . '</button></p></form></div>';
			return '<div class="brik-comments-inner">' . ( ! empty( $a['show_title'] ) ? $heading( 2 ) : '' ) . '<ol class="comment-list">' . $list . '</ol>' . $form . '</div>';
		}

		$post = brik_site_post( $ctx );
		if ( ! $post || ! is_singular() || post_password_required( $post ) ) {
			return '';
		}
		$count = (int) get_comments_number( $post );
		if ( ! comments_open( $post ) && ! $count ) {
			return '';
		}
		if ( get_option( 'thread_comments' ) && comments_open( $post ) ) {
			wp_enqueue_script( 'comment-reply' );
		}

		$theme = (bool) locate_template( 'comments.php' );
		$html  = brik_site_with_post(
			$post,
			static function ( $p ) use ( $a, $count, $heading, $button, $btn_cls, $theme ) {
				ob_start();
				if ( $theme ) {
					comments_template();
					return ob_get_clean();
				}

				$commenter = wp_get_current_commenter();
				$comments  = get_comments(
					array(
						'post_id'            => $p->ID,
						'status'             => 'approve',
						'order'              => 'ASC',
						'orderby'            => 'comment_date_gmt',
						'include_unapproved' => array( is_user_logged_in() ? get_current_user_id() : $commenter['comment_author_email'] ),
					)
				);
				if ( $comments ) {
					if ( ! empty( $a['show_title'] ) ) {
						echo $heading( $count ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above.
					}
					echo '<ol class="comment-list">';
					wp_list_comments(
						array(
							'style'       => 'ol',
							'short_ping'  => true,
							'avatar_size' => 40,
							'format'      => 'html5',
						),
						$comments
					);
					echo '</ol>';
				}
				if ( ! comments_open( $p ) ) {
					echo '<p class="no-comments">' . esc_html__( 'Comments are closed.', 'brik-builder' ) . '</p>';
				}
				comment_form(
					array(
						'title_reply'        => wp_strip_all_tags( $a['form_title'] ),
						'title_reply_before' => '<h3 id="reply-title" class="comment-reply-title">',
						'title_reply_after'  => '</h3>',
						'label_submit'       => $button,
						'class_submit'       => $btn_cls,
						'submit_button'      => '<button name="%1$s" type="submit" id="%2$s" class="%3$s">%4$s</button>',
					),
					$p->ID
				);
				return ob_get_clean();
			}
		);
		return '<div class="brik-comments-inner"' . ( $theme ? '' : ' id="comments"' ) . '>' . $html . '</div>';
	},
);
