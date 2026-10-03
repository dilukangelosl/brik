<?php
/**
 * Comments.
 *
 * @package Brik
 */

defined( 'ABSPATH' ) || exit;

if ( post_password_required() ) {
	return;
}
?>
<section id="comments" class="comments-area">
	<?php if ( have_comments() ) : ?>
		<h2 class="comments-title">
			<?php
			$brik_theme_count = (int) get_comments_number();
			/* translators: %s: number of comments. */
			echo esc_html( sprintf( _n( '%s comment', '%s comments', $brik_theme_count, 'brik' ), number_format_i18n( $brik_theme_count ) ) );
			?>
		</h2>

		<ol class="comment-list">
			<?php
			wp_list_comments(
				array(
					'style'       => 'ol',
					'short_ping'  => true,
					'avatar_size' => 40,
					'callback'    => 'brik_theme_comment',
				)
			);
			?>
		</ol>

		<?php
		the_comments_navigation(
			array(
				'prev_text' => brik_theme_icon( 'chevron-left' ) . esc_html__( 'Older comments', 'brik' ),
				'next_text' => esc_html__( 'Newer comments', 'brik' ) . brik_theme_icon( 'chevron-right' ),
			)
		);
		?>

		<?php if ( ! comments_open() ) : ?>
			<p class="no-comments"><?php esc_html_e( 'Comments are closed.', 'brik' ); ?></p>
		<?php endif; ?>
	<?php endif; ?>

	<?php
	comment_form(
		array(
			'class_container'    => 'comment-respond card',
			'class_form'         => 'comment-form',
			'class_submit'       => 'btn submit',
			'title_reply'        => __( 'Leave a comment', 'brik' ),
			'title_reply_before' => '<h2 id="reply-title" class="comment-reply-title">',
			'title_reply_after'  => '</h2>',
			'label_submit'       => __( 'Post comment', 'brik' ),
		)
	);
	?>
</section>
