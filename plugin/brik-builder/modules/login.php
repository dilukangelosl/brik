<?php
/**
 * Login form posting to wp-login.php, so security plugins and SSO hooks still apply.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'login',
	'title'       => __( 'Login', 'brik-builder' ),
	'category'    => 'forms',
	'icon'        => 'log-in',
	'description' => 'WordPress login form. title, description, label_username, label_password, button_text, remember (bool), show_lost (bool), show_register (bool, only shown when registration is open), redirect (URL after login, empty = current page), style: card|plain. Logged-in visitors see "Logged in as {name} · Log out" (logged_in_text) instead.',
	'fields'      => array_merge(
		array(
			'title'          => Fields::field( 'text', __( 'Title', 'brik-builder' ), 'content', array( 'default' => __( 'Welcome back', 'brik-builder' ), 'inline' => true ) ),
			'description'    => Fields::field( 'text', __( 'Description', 'brik-builder' ), 'content', array( 'default' => __( 'Sign in to your account to continue.', 'brik-builder' ) ) ),
			'label_username' => Fields::field( 'text', __( 'Username label', 'brik-builder' ), 'content', array( 'default' => __( 'Email or username', 'brik-builder' ) ) ),
			'label_password' => Fields::field( 'text', __( 'Password label', 'brik-builder' ), 'content', array( 'default' => __( 'Password', 'brik-builder' ) ) ),
			'button_text'    => Fields::field( 'text', __( 'Button text', 'brik-builder' ), 'content', array( 'default' => __( 'Sign in', 'brik-builder' ), 'inline' => true ) ),
			'button_variant' => Fields::field( 'select', __( 'Button variant', 'brik-builder' ), 'content', array( 'default' => 'default', 'options' => Fields::opts( brik_button_variants_labels() ) ) ),
			'remember'       => Fields::field( 'toggle', __( 'Remember me', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'show_lost'      => Fields::field( 'toggle', __( 'Lost password link', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'show_register'  => Fields::field( 'toggle', __( 'Register link', 'brik-builder' ), 'content', array( 'default' => true, 'description' => __( 'Shown only when registration is enabled.', 'brik-builder' ) ) ),
			'redirect'       => Fields::field( 'text', __( 'Redirect after login', 'brik-builder' ), 'content', array( 'placeholder' => 'https://', 'description' => __( 'Leave empty to return to this page.', 'brik-builder' ) ) ),
			'logged_in_text' => Fields::field( 'text', __( 'Logged-in message', 'brik-builder' ), 'content', array( 'default' => __( 'Logged in as {name}', 'brik-builder' ), 'description' => __( '{name} is replaced with the user\'s display name.', 'brik-builder' ) ) ),
			'style'          => Fields::field( 'select', __( 'Style', 'brik-builder' ), 'content', array( 'default' => 'card', 'options' => Fields::opts( array( 'card' => __( 'Card', 'brik-builder' ), 'plain' => __( 'Plain', 'brik-builder' ) ) ) ) ),
		),
		Fields::box( 'form', __( 'Container', 'brik-builder' ), Fields::WRAP . ' .brik-login-box' ),
		Fields::typography( 'title', __( 'Title', 'brik-builder' ), Fields::WRAP . ' .brik-form-title' ),
		Fields::typography( 'label', __( 'Labels', 'brik-builder' ), Fields::WRAP . ' .brik-form-label' ),
		Fields::box( 'input', __( 'Inputs', 'brik-builder' ), Fields::WRAP . ' .brik-input' ),
		Fields::typography( 'input', __( 'Input text', 'brik-builder' ), Fields::WRAP . ' .brik-input' ),
		Fields::box( 'submit', __( 'Button', 'brik-builder' ), Fields::WRAP . ' .brik-form-submit' ),
		Fields::typography( 'submit', __( 'Button text', 'brik-builder' ), Fields::WRAP . ' .brik-form-submit' ),
		Fields::typography( 'link', __( 'Links', 'brik-builder' ), Fields::WRAP . ' .brik-login-link' )
	),
	'render'      => static function ( array $a, Brik\Context $ctx ) {
		$card = 'plain' !== $a['style'];
		$box  = brik_cls( 'brik-login-box grid gap-6', $card ? 'rounded-xl border bg-card text-card-foreground p-6 shadow-sm sm:p-8' : '' );
		$link = 'brik-login-link text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline';

		// Inside a header/footer template, return to whatever page is being viewed.
		if ( $ctx->post_id && ! Brik\ThemeBuilder::is_template( $ctx->post_id ) ) {
			$here = get_permalink( $ctx->post_id );
		} else {
			$here = is_singular() ? get_permalink( get_queried_object_id() ) : '';
		}
		$here = $here ? $here : home_url( '/' );

		if ( is_user_logged_in() && ! $ctx->canvas ) {
			$user = wp_get_current_user();
			$text = str_replace( '{name}', '<strong class="font-medium text-foreground">' . esc_html( $user->display_name ) . '</strong>', esc_html( (string) $a['logged_in_text'] ) );
			return '<div class="' . esc_attr( brik_cls( $box, 'brik-login-status' ) ) . '"><p class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted-foreground">'
				. get_avatar( $user->ID, 32, '', '', array( 'class' => 'size-8 rounded-full' ) )
				. '<span>' . $text . '</span><span aria-hidden="true">·</span>'
				. '<a class="' . esc_attr( $link ) . ' inline-flex items-center gap-1.5" href="' . esc_url( wp_logout_url( $here ) ) . '">' . brik_icon( 'log-out', 'size-3.5' ) . esc_html__( 'Log out', 'brik-builder' ) . '</a></p></div>';
		}

		$redirect = trim( (string) $a['redirect'] );
		$redirect = '' !== $redirect ? $redirect : $here;
		$u_id     = $ctx->uid( 'user' );
		$p_id     = $ctx->uid( 'pass' );

		$head = '';
		if ( '' !== trim( (string) $a['title'] ) ) {
			$head .= '<h3 class="brik-form-title font-heading text-xl font-semibold tracking-tight"' . $ctx->inline( 'title' ) . '>' . brik_inline( $a['title'] ) . '</h3>';
		}
		if ( '' !== trim( (string) $a['description'] ) ) {
			$head .= '<p class="brik-form-description text-sm text-muted-foreground"' . $ctx->inline( 'description' ) . '>' . brik_inline( $a['description'] ) . '</p>';
		}

		$lost = brik_form_bool( $a['show_lost'] ) ? '<a class="' . esc_attr( $link ) . ' ml-auto" href="' . esc_url( wp_lostpassword_url( $here ) ) . '">' . esc_html__( 'Forgot password?', 'brik-builder' ) . '</a>' : '';

		$html  = '<div class="' . esc_attr( $box ) . '">';
		$html .= $head ? '<div class="brik-form-head grid gap-1.5">' . $head . '</div>' : '';
		$html .= '<form class="brik-login-form grid gap-5" method="post" action="' . esc_url( site_url( 'wp-login.php', 'login_post' ) ) . '">';
		$html .= '<div class="grid gap-2"><label class="' . esc_attr( brik_label_class() ) . '" for="' . esc_attr( $u_id ) . '">' . brik_inline( $a['label_username'] ) . '</label>'
			. '<input' . brik_attrs( array( 'id' => $u_id, 'type' => 'text', 'name' => 'log', 'class' => brik_input_class(), 'autocomplete' => 'username', 'autocapitalize' => 'off', 'spellcheck' => 'false', 'required' => true ) ) . '></div>';
		$html .= '<div class="grid gap-2"><div class="flex items-center gap-2"><label class="' . esc_attr( brik_label_class() ) . '" for="' . esc_attr( $p_id ) . '">' . brik_inline( $a['label_password'] ) . '</label>' . $lost . '</div>'
			. '<input' . brik_attrs( array( 'id' => $p_id, 'type' => 'password', 'name' => 'pwd', 'class' => brik_input_class(), 'autocomplete' => 'current-password', 'required' => true ) ) . '></div>';

		if ( brik_form_bool( $a['remember'] ) ) {
			$html .= brik_choice( 'checkbox', array( 'name' => 'rememberme', 'value' => 'forever' ), esc_html__( 'Remember me', 'brik-builder' ) );
		}

		$html .= '<input type="hidden" name="redirect_to" value="' . esc_url( $redirect ) . '">';
		$html .= '<button type="submit" name="wp-submit" class="' . esc_attr( brik_button_class( $a['button_variant'], 'lg', 'brik-form-submit w-full' ) ) . '"><span' . $ctx->inline( 'button_text' ) . '>' . brik_inline( $a['button_text'] ) . '</span></button>';

		ob_start();
		do_action( 'login_form' );
		$html .= ob_get_clean();
		$html .= '</form>';

		if ( brik_form_bool( $a['show_register'] ) && get_option( 'users_can_register' ) ) {
			$html .= '<p class="text-center text-sm text-muted-foreground">' . esc_html__( 'Don\'t have an account?', 'brik-builder' ) . ' <a class="' . esc_attr( $link ) . ' font-medium text-foreground underline" href="' . esc_url( wp_registration_url() ) . '">' . esc_html__( 'Sign up', 'brik-builder' ) . '</a></p>';
		}
		return $html . '</div>';
	},
);
