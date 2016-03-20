<?php

/*******************************************
* Restrict Content Short Codes
*******************************************/

// shortcode or restricting content to registered users and or user roles
function rcp_restrict_shortcode( $atts, $content = null ) {
	extract( shortcode_atts( array(
		'userlevel' 	=> 'none',
		'message' 		=> '',
		'paid' 			=> false,
		'level' 		=> 0,
		'subscription' 	=> ''
	), $atts ) );

	global $rcp_options, $user_ID;

	if( strlen( trim( $message ) ) > 0 ) {
		$teaser = $message;
	} elseif( $paid ) {
		$teaser = $rcp_options['paid_message'];
	} else {
		$teaser = $rcp_options['free_message'];
	}

	$subscription = explode( ',', $subscription );

	if( $paid ) {

		$has_access = false;
		if( rcp_is_active( $user_ID ) && rcp_user_has_access( $user_ID, $level ) ) {
			$has_access = true;

			if( ! empty( $subscription ) && ! empty( $subscription[0] ) ) {
				if( ! in_array( rcp_get_subscription_id( $user_ID ), $subscription ) ) {
					$has_access = false;
				}
			}
		}

		if ( $userlevel == 'admin' && current_user_can( 'switch_themes' ) && $has_access ) {
			return apply_filters( 'rcp_restrict_shortcode_return', $content );
		}
		if ( $userlevel == 'editor' && current_user_can( 'moderate_comments' ) && $has_access ) {
			return apply_filters( 'rcp_restrict_shortcode_return', $content );
		}
		if ( $userlevel == 'author' && current_user_can( 'upload_files' ) && $has_access ) {
			return do_shortcode(  wpautop( $content ) );
		}
		if ( $userlevel == 'contributor' && current_user_can( 'edit_posts' ) && $has_access ) {
		 	return apply_filters( 'rcp_restrict_shortcode_return', $content );
		}
		if ( $userlevel == 'subscriber' && current_user_can( 'read' ) && $has_access ) {
		 	return apply_filters( 'rcp_restrict_shortcode_return', $content );
		}
		if ( $userlevel == 'none' && is_user_logged_in() && $has_access ) {
		 	return apply_filters( 'rcp_restrict_shortcode_return', $content );
		} else {
			return '<div class="rcp_restricted rcp_paid_only">' . rcp_format_teaser( $teaser ) . '</div>';
		}

	} else {

		$has_access = false;
		if(rcp_user_has_access($user_ID, $level)) {
			$has_access = true;
			if( ! empty( $subscription ) && ! empty( $subscription[0] ) ) {
				if( in_array( rcp_get_subscription_id( $user_ID ), $subscription ) ) {
					$has_access = false;
				}
			}
		}

		if ( $userlevel == 'admin' && current_user_can( 'switch_themes' ) && $has_access ) {
			return apply_filters( 'rcp_restrict_shortcode_return', $content );
		} elseif ( $userlevel == 'editor' && current_user_can( 'moderate_comments' ) && $has_access ) {
			return apply_filters( 'rcp_restrict_shortcode_return', $content );
		} elseif ( $userlevel == 'author' && current_user_can( 'upload_files' ) && $has_access ) {
			return apply_filters( 'rcp_restrict_shortcode_return', $content );
		} elseif ( $userlevel == 'contributor' && current_user_can( 'edit_posts' ) && $has_access ) {
		 	return apply_filters( 'rcp_restrict_shortcode_return', $content );
		} elseif ( $userlevel == 'subscriber' && current_user_can( 'read' ) && $has_access ) {
		 	return apply_filters( 'rcp_restrict_shortcode_return', $content );
		} elseif ( $userlevel == 'none' && is_user_logged_in() && $has_access ) {
		 	return apply_filters( 'rcp_restrict_shortcode_return', $content );
		} else {
			return '<div class="rcp_restricted">' . rcp_format_teaser( $teaser ) . '</div>';
		}
	}
}
add_shortcode( 'restrict', 'rcp_restrict_shortcode' );
add_filter( 'rcp_restrict_shortcode_return', 'wpautop' );
add_filter( 'rcp_restrict_shortcode_return', 'do_shortcode' );

// shows content only to active, paid users
function rcp_is_paid_user_shortcode( $atts, $content = null ) {

	global $user_ID;

	if( rcp_is_active( $user_ID ) ) {
		return do_shortcode( $content );
	}
}
add_shortcode( 'is_paid', 'rcp_is_paid_user_shortcode' );

// shows content only to logged-in free users, and can hide from paid
function rcp_is_free_user_shortcode( $atts, $content = null ) {
	extract( shortcode_atts( array(
		'hide_from_paid' => true
	), $atts ) );

	global $user_ID;

	if( $hide_from_paid ) {
		if( !rcp_is_active( $user_ID ) && is_user_logged_in() ) {
			return do_shortcode( $content );
		}
	} elseif( is_user_logged_in() ) {
		return do_shortcode( $content );
	}
}
add_shortcode( 'is_free', 'rcp_is_free_user_shortcode' );

function rcp_not_logged_in( $atts, $content = null ) {
	if( !is_user_logged_in() ) {
		return do_shortcode( $content );
	}
}
add_shortcode( 'not_logged_in', 'rcp_not_logged_in' );

// allows content to be shown to only users that don't have an active subscription
function rcp_is_not_paid( $atts, $content = null ) {
	global $user_ID;
	if( rcp_is_active( $user_ID ) )
		return;
	else
		return do_shortcode( $content );

}
add_shortcode( 'is_not_paid', 'rcp_is_not_paid' );

// shows the display name of the currently logged-in user
function rcp_user_name( $atts, $content = null ) {
	global $user_ID;
	if(is_user_logged_in()) {
		return get_userdata( $user_ID )->display_name;
	}

}
add_shortcode( 'user_name', 'rcp_user_name' );

// user registration login form
function rcp_registration_form( $atts, $content = null ) {
	extract( shortcode_atts( array(
		'id' => null,
		'registered_message' => __( 'You are already registered and have an active subscription.', 'rcp' )
	), $atts ) );

	global $user_ID;

	// only show the registration form to non-logged-in members
	if( ! rcp_is_active( $user_ID ) || rcp_is_trialing( $user_ID ) || rcp_subscription_upgrade_possible( $user_ID ) ) {

		global $rcp_options, $rcp_load_css, $rcp_load_scripts;

		// set this to true so the CSS and JS scripts are loaded
		$rcp_load_css = true;
		$rcp_load_scripts = true;

		$output = rcp_registration_form_fields( $id );

	} else {
		$output = $registered_message;
	}
	return $output;
}
add_shortcode( 'register_form', 'rcp_registration_form' );

/**
 * Shortcode for displaying stripe checkout
 *
 * @since 2.5
 * @param $atts
 *
 * @return mixed|void
 */
function rcp_register_form_stripe_checkout( $atts ) {
	global $rcp_options;

	if ( empty( $atts['id'] ) ) {
		return '';
	}

	// button is an alias for data-label
	if ( isset( $atts['button'] ) ) {
		$atts['data-label'] = $atts['button'];
	}

	$key = ( isset( $rcp_options['sandbox'] ) ) ? $rcp_options['stripe_test_publishable'] : $rcp_options['stripe_live_publishable'];

	$user         = wp_get_current_user();
	$subscription = rcp_get_subscription_details( $atts['id'] );

	$data = wp_parse_args( $atts, array(
		'id'                     => 0,
		'data-key'               => $key,
		'data-name'              => get_option( 'blogname' ),
		'data-description'       => $subscription->description,
		'data-label'             => sprintf( __( 'Join %s', 'rcp' ), $subscription->name ),
		'data-panel-label'       => __( 'Register - {{amount}}', 'rcp' ),
		'data-amount'            => $subscription->price * rcp_stripe_get_currency_multiplier(),
		'data-local'             => 'auto',
		'data-allow-remember-me' => true,
	) );

	if ( empty( $data['data-email'] ) && ! empty( $user->user_email ) ) {
		$data['data-email'] = $user->user_email;
	}

	if ( empty( $data['data-image'] ) && $image = get_site_icon_url() ) {
		$data['data-image'] = $image;
	}

	$data = apply_filters( 'rcp_stripe_checkout_data', $data );

	ob_start();
	?>
	<form action="" method="post">
		<?php do_action( 'register_form_stripe_fields', $data ); ?>
		<script src="https://checkout.stripe.com/checkout.js" class="stripe-button" <?php foreach( $data as $label => $value ) { printf( ' %s="%s" ', esc_attr( $label ), esc_attr( $value ) ); } ?> ></script>
		<input type="hidden" name="rcp_level" value="<?php echo $subscription->id ?>" />
		<input type="hidden" name="rcp_register_nonce" value="<?php echo wp_create_nonce('rcp-register-nonce' ); ?>"/>
		<input type="hidden" name="rcp_gateway" value="stripe_checkout"/>
	</form>
	<?php

	return apply_filters( 'register_form_stripe', ob_get_clean(), $atts );
}
add_shortcode( 'register_form_stripe', 'rcp_register_form_stripe_checkout' );

// user login form
function rcp_login_form( $atts, $content = null ) {

	global $post;

	$current_page = rcp_get_current_url();

	extract( shortcode_atts( array(
		'redirect' 	=> $current_page,
		'class' 	=> 'rcp_form'
	), $atts ) );

	$output = '';

	global $rcp_load_css;

	// set this to true so the CSS is loaded
	$rcp_load_css = true;

	return rcp_login_form_fields( array( 'redirect' => $redirect, 'class' => $class ) );

}
add_shortcode( 'login_form', 'rcp_login_form' );

// password reset form
function rcp_reset_password_form() {
	if( is_user_logged_in() ) {

		global $rcp_options, $rcp_load_css, $rcp_load_scripts;
		// set this to true so the CSS is loaded
		$rcp_load_css = true;
		if( isset( $rcp_options['front_end_validate'] ) ) {
			$rcp_load_scripts = true;
		}

		// get the password reset form fields
		$output = rcp_change_password_form();

		return $output;
	}
}
add_shortcode( 'password_form', 'rcp_reset_password_form' );

// displays a list of premium posts
function rcp_list_paid_posts() {
	$paid_posts = rcp_get_paid_posts();
	$list = '';
	if( $paid_posts ) {
		$list .= '<ul class="rcp_paid_posts">';
		foreach( $paid_posts as $post_id ) {
			$list .= '<li><a href="' . esc_url( get_permalink( $post_id ) ) . '">' . get_the_title( $post_id ) . '</a></li>';
		}
		$list .= '</ul>';
	}
	return $list;
}
add_shortcode( 'paid_posts', 'rcp_list_paid_posts' );

// displays the current user's subscription details
function rcp_user_subscription_details( $atts, $content = null ) {
	extract( shortcode_atts( array(
		'option' => ''
	), $atts ) );

	global $user_ID, $rcp_options;

	ob_start();

	if( is_user_logged_in() ) {

		rcp_get_template_part( 'subscription' );

	} else {

		echo rcp_login_form_fields();

	}

	return ob_get_clean();
}
add_shortcode( 'subscription_details', 'rcp_user_subscription_details' );

add_filter( 'widget_text', 'do_shortcode' );


/**
 * Profile Editor Shortcode
 *
 * Outputs the EDD Profile Editor to allow users to amend their details from the
 * front-end
 *
 * @access      public
 * @since       1.5
 */
function rcp_profile_editor_shortcode( $atts, $content = null ) {

	global $rcp_load_css;

	$rcp_load_css = true;

	ob_start();

	rcp_get_template_part( 'profile', 'editor' );

	return ob_get_clean();
}
add_shortcode( 'rcp_profile_editor', 'rcp_profile_editor_shortcode' );


/**
 * Update card form short code
 *
 * Displays a form to update the billing credit / debit card
 *
 * @access      public
 * @since       2.1
 */
function rcp_update_billing_card_shortcode( $atts, $content = null ) {
	global $rcp_load_css, $rcp_load_scripts;

	$rcp_load_css = true;
	$rcp_load_scripts = true;

	ob_start();

	if( rcp_member_can_update_billing_card() ) {

		do_action( 'rcp_before_update_billing_card_form' );

		if( isset( $_GET['card'] ) ) {

			switch( $_GET['card'] ) {

				case 'updated' :

					echo '<p class="rcp_success"><span>' . __( 'Billing card updated successfully', 'rcp' ) . '</span></p>';

					break;

			}

		}

		rcp_get_template_part( 'card-update', 'form' );
		do_action( 'rcp_after_update_billing_card_form' );

	}

	return ob_get_clean();
}
add_shortcode( 'card_details', 'rcp_update_billing_card_shortcode' ); // Old version
add_shortcode( 'rcp_update_card', 'rcp_update_billing_card_shortcode' );

/**
 * Show User's Subscription ID Shortcode
 *
 * @since 2.5
 * @return string
 */
function rcp_user_subscription_id_shortcode() {
	if ( ! is_user_logged_in() ) {
		return '';
	}

	return rcp_get_subscription_id();
}
add_shortcode( 'subscription_id', 'rcp_user_subscription_id_shortcode' );

/**
 * Show User's Subscription ID Shortcode
 *
 * @since 2.5
 * @return string
 */
function rcp_user_subscription_name_shortcode() {
	if ( ! is_user_logged_in() ) {
		return '';
	}

	if ( ! $id = rcp_get_subscription_id() ) {
		return '';
	}

	return rcp_get_subscription_name( $id );
}
add_shortcode( 'subscription_name', 'rcp_user_subscription_name_shortcode' );

/**
 * Show User's Expiration Shortcode
 *
 * @since 2.5
 * @return string
 */
function rcp_user_expiration_shortcode() {
	if ( ! is_user_logged_in() ) {
		return '';
	}

	return rcp_get_expiration_date();
}
add_shortcode( 'user_expiration', 'rcp_user_expiration_shortcode' );
