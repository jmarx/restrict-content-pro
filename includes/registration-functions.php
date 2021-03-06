<?php
/**
 * Registration Functions
 *
 * Processes the registration form
 *
 * @package     Restrict Content Pro
 * @subpackage  Login Functions
 * @copyright   Copyright (c) 2013, Pippin Williamson
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.5
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;


/**
 * Register a new user
 *
 * @access      public
 * @since       1.0
 */
function rcp_process_registration() {

  	if ( isset( $_POST["rcp_register_nonce"] ) && wp_verify_nonce( $_POST['rcp_register_nonce'], 'rcp-register-nonce' ) ) {

		global $rcp_options, $user_ID;

		$full_discount   = false;
		$subscription_id = isset( $_POST['rcp_level'] ) ? absint( $_POST['rcp_level'] ) : false;
		$discount        = isset( $_POST['rcp_discount'] ) ? sanitize_text_field( $_POST['rcp_discount'] ) : '';
		$discount_valid  = false;
		$price           = number_format( (float) rcp_get_subscription_price( $subscription_id ), 2 );
		$price           = str_replace( ',', '', $price );
		$base_price      = $price; // Used for discount calculations later
		$expiration      = rcp_get_subscription_length( $subscription_id );
		$subscription    = rcp_get_subscription_details( $subscription_id );

		// get the selected payment method/gateway
		if( ! isset( $_POST['rcp_gateway'] ) ) {
			$gateway = 'paypal';
		} else {
			$gateway = sanitize_text_field( $_POST['rcp_gateway'] );
		}

		/***********************
		* validate the form
		***********************/

		do_action( 'rcp_before_form_errors', $_POST );

		$is_ajax   = isset( $_POST['rcp_ajax'] );

		$user_data = rcp_validate_user_data();

		if( ! $subscription_id ) {
			// no subscription level was chosen
			rcp_errors()->add( 'no_level', __( 'Please choose a subscription level', 'rcp' ), 'register' );
		}

		if( $subscription_id ) {

			if( $price == 0 && $expiration->duration > 0 && rcp_has_used_trial( $user_data['id'] ) ) {
				// this ensures that users only sign up for a free trial once
				rcp_errors()->add( 'free_trial_used', __( 'You may only sign up for a free trial once', 'rcp' ), 'register' );
			}
		}

		if( ! empty( $discount ) ) {

			if( rcp_validate_discount( $discount, $subscription_id ) ) {

				$discount_valid = true;

			} else {

				// the entered discount code is incorrect
				rcp_errors()->add( 'invalid_discount', __( 'The discount you entered is invalid', 'rcp' ), 'register' );

			}

			if( $discount_valid && $price > 0 ) {

				if( ! $user_data['need_new'] && rcp_user_has_used_discount( $user_data['id'] , $discount ) && apply_filters( 'rcp_discounts_once_per_user', false ) ) {

					$discount_valid = false;
					rcp_errors()->add( 'discount_already_used', __( 'You can only use the discount code once', 'rcp' ), 'register' );
				}

				if( $discount_valid ) {

					$discounts    = new RCP_Discounts();
					$discount_obj = $discounts->get_by( 'code', $discount );

					if( is_object( $discount_obj ) ) {
						// calculate the after-discount price
						$discounted_price = $discounts->calc_discounted_price( $base_price, $discount_obj->amount, $discount_obj->unit );
						if( 0 == $discounted_price ) {
							$full_discount = true;
						}

					}

				}
			
			}

		}

		// Validate extra fields in gateways with the 2.1+ gateway API
		if( ! has_action( 'rcp_gateway_' . $gateway ) && $price > 0 && ! $full_discount ) {
		
			$gateways    = new RCP_Payment_Gateways;
			$gateway_var = $gateways->get_gateway( $gateway );
			$gateway_obj = new $gateway_var['class'];
			$gateway_obj->validate_fields();
		}

		do_action( 'rcp_form_errors', $_POST );

		// retrieve all error messages, if any
		$errors = rcp_errors()->get_error_messages();

		if ( ! empty( $errors ) && $is_ajax ) {
			wp_send_json_error( array( 'success' => false, 'errors' => rcp_get_error_messages_html( 'register' ), 'nonce' => wp_create_nonce( 'rcp-register-nonce' ) ) );
		} elseif( $is_ajax ) {
			wp_send_json_success( array( 'success' => true ) );
		}

		// only create the user if there are no errors
		if( ! empty( $errors ) || $is_ajax ) {
			return;
		}

		if( $user_data['need_new'] ) {

			$user_data['id'] = wp_insert_user( array(
					'user_login'		=> $user_data['login'],
					'user_pass'	 		=> $user_data['password'],
					'user_email'		=> $user_data['email'],
					'first_name'		=> $user_data['first_name'],
					'last_name'			=> $user_data['last_name'],
					'display_name'      => $user_data['first_name'] . ' ' . $user_data['last_name'],
					'user_registered'	=> date( 'Y-m-d H:i:s' )
				)
			);

		}

		// Setup the member object
		$member = new RCP_Member( $user_data['id'] );

		if( $user_data['id'] ) {

			update_user_meta( $user_data['id'], '_rcp_new_subscription', '1' );

			$subscription_key = rcp_generate_subscription_key();

			if( ! rcp_is_active( $user_data['id'] ) ) {

				rcp_set_status( $user_data['id'], 'pending' );
				update_user_meta( $user_data['id'], 'rcp_subscription_level', $subscription_id );
				update_user_meta( $user_data['id'], 'rcp_subscription_key', $subscription_key );
	
			} else {

				// If the member is already active, we need to set these as pending changes
				update_user_meta( $user_data['id'], 'rcp_pending_subscription_level', $subscription_id );
				update_user_meta( $user_data['id'], 'rcp_pending_subscription_key', $subscription_key );

			}

			// Calculate the expiration date for the member
			$member_expires = $member->calculate_expiration();

			// Set the user's role
			$role = ! empty( $subscription->role ) ? $subscription->role : 'subscriber';
			$user = new WP_User( $user_data['id'] );
			$user->add_role( apply_filters( 'rcp_default_user_level', $role, $subscription_id ) );

			do_action( 'rcp_form_processing', $_POST, $user_data['id'], $price );

			// process a paid subscription
			if( $price > '0' ) {

				if( ! empty( $discount ) ) {

					// record the usage of this discount code
					$discounts->add_to_user( $user_data['id'], $discount );

					// incrase the usage count for the code
					$discounts->increase_uses( $discount_obj->id );

					// if the discount is 100%, log the user in and redirect to success page
					if( $full_discount ) {
						rcp_set_expiration_date( $user_data['id'], $member_expires );
						rcp_set_status( $user_data['id'], 'active' );
						rcp_login_user_in( $user_data['id'], $user_data['login'] );
						wp_redirect( rcp_get_return_url( $user_data['id'] ) ); exit;
					}

				}

				// Determine auto renew behavior
				if( '3' == rcp_get_auto_renew_behavior() && isset( $_POST['rcp_auto_renew'] ) ) {

					$auto_renew = true;

				} elseif( '1' == rcp_get_auto_renew_behavior() ) {

					$auto_renew = true;

				} else {

					$auto_renew = false;

				}

				// Remove trialing status, if it exists
				delete_user_meta( $user_data['id'], 'rcp_is_trialing' );

				// log the new user in
				rcp_login_user_in( $user_data['id'], $user_data['login'] );

				$redirect = rcp_get_return_url( $user_data['id'] );

				$subscription_data = array(
					'price'             => ! empty( $discounted_price ) ? $discounted_price : $price,
					'discount'          => ! empty( $discounted_price ) ? $base_price - $discounted_price : 0,
					'discount_code'     => $discount,
					'fee' 			    => ! empty( $subscription->fee ) ? number_format( $subscription->fee, 2 ) : 0,
					'length' 			=> $expiration->duration,
					'length_unit' 		=> strtolower( $expiration->duration_unit ),
					'subscription_id'   => $subscription->id,
					'subscription_name' => $subscription->name,
					'key' 				=> $subscription_key,
					'user_id' 			=> $user_data['id'],
					'user_name' 		=> $user_data['login'],
					'user_email' 		=> $user_data['email'],
					'currency' 			=> $rcp_options['currency'],
					'auto_renew' 		=> $auto_renew,
					'return_url' 		=> $redirect,
					'new_user' 			=> $user_data['need_new'],
					'post_data' 		=> $_POST
				);

				// send all of the subscription data off for processing by the gateway
				rcp_send_to_gateway( $gateway, apply_filters( 'rcp_subscription_data', $subscription_data ) );

			// process a free or trial subscription
			} else {

				// This is a free user registration or trial
				rcp_set_expiration_date( $user_data['id'], $member_expires );

				// if the subscription is a free trial, we need to record it in the user meta
				if( $member_expires != 'none' ) {

					// activate the user's trial subscription
					rcp_set_status( $user_data['id'], 'active' );

					// this is so that users can only sign up for one trial
					update_user_meta( $user_data['id'], 'rcp_has_trialed', 'yes' );
					update_user_meta( $user_data['id'], 'rcp_is_trialing', 'yes' );
					rcp_email_subscription_status( $user_data['id'], 'trial' );

				} else {

					// set the user's status to free
					rcp_set_status( $user_data['id'], 'free' );
					rcp_email_subscription_status( $user_data['id'], 'free' );

				}

				if( $user_data['need_new'] ) {

					if( ! isset( $rcp_options['disable_new_user_notices'] ) ) {

						// send an email to the admin alerting them of the registration
						wp_new_user_notification( $user_data['id']) ;

					}

					// log the new user in
					rcp_login_user_in( $user_data['id'], $user_data['login'] );

				}
				// send the newly created user to the redirect page after logging them in
				wp_redirect( rcp_get_return_url( $user_data['id'] ) ); exit;

			} // end price check

		} // end if new user id

	} // end nonce check
}
add_action( 'init', 'rcp_process_registration', 100 );
add_action( 'wp_ajax_rcp_process_register_form', 'rcp_process_registration', 100 );
add_action( 'wp_ajax_nopriv_rcp_process_register_form', 'rcp_process_registration', 100 );


/**
 * Validate and setup the user data for registration
 *
 * @access      public
 * @since       1.5
 * @return      array
 */
function rcp_validate_user_data() {

	$user = array();

	if( ! is_user_logged_in() ) {
		$user['id']		          = 0;
		$user['login']		      = sanitize_text_field( $_POST['rcp_user_login'] );
		$user['email']		      = sanitize_text_field( $_POST['rcp_user_email'] );
		$user['first_name'] 	  = sanitize_text_field( $_POST['rcp_user_first'] );
		$user['last_name']	 	  = sanitize_text_field( $_POST['rcp_user_last'] );
		$user['password']		  = sanitize_text_field( $_POST['rcp_user_pass'] );
		$user['password_confirm'] = sanitize_text_field( $_POST['rcp_user_pass_confirm'] );
		$user['need_new']         = true;
	} else {
		$userdata 		  = get_userdata( get_current_user_id() );
		$user['id']       = $userdata->ID;
		$user['login'] 	  = $userdata->user_login;
		$user['email'] 	  = $userdata->user_email;
		$user['need_new'] = false;
	}


	if( $user['need_new'] ) {
		if( username_exists( $user['login'] ) ) {
			// Username already registered
			rcp_errors()->add( 'username_unavailable', __( 'Username already taken', 'rcp' ), 'register' );
		}
		if( ! rcp_validate_username( $user['login'] ) ) {
			// invalid username
			rcp_errors()->add( 'username_invalid', __( 'Invalid username', 'rcp' ), 'register' );
		}
		if( empty( $user['login'] ) ) {
			// empty username
			rcp_errors()->add( 'username_empty', __( 'Please enter a username', 'rcp' ), 'register' );
		}
		if( ! is_email( $user['email'] ) ) {
			//invalid email
			rcp_errors()->add( 'email_invalid', __( 'Invalid email', 'rcp' ), 'register' );
		}
		if( email_exists( $user['email'] ) ) {
			//Email address already registered
			rcp_errors()->add( 'email_used', __( 'Email already registered', 'rcp' ), 'register' );
		}
		if( empty( $user['password'] ) ) {
			// passwords do not match
			rcp_errors()->add( 'password_empty', __( 'Please enter a password', 'rcp' ), 'register' );
		}
		if( $user['password'] !== $user['password_confirm'] ) {
			// passwords do not match
			rcp_errors()->add( 'password_mismatch', __( 'Passwords do not match', 'rcp' ), 'register' );
		}
	}

	return apply_filters( 'rcp_user_registration_data', $user );
}


/**
 * Get the registration success/return URL
 *
 * @access      public
 * @since       1.5
 * @param       $user_id int The user ID we have just registered
 * @return      array
 */
function rcp_get_return_url( $user_id = 0 ) {

	global $rcp_options;

	if( isset( $rcp_options['redirect'] ) ) {
		$redirect = get_permalink( $rcp_options['redirect'] );
	} else {
		$redirect = home_url();
	}
	return apply_filters( 'rcp_return_url', $redirect, $user_id );
}

/**
 * Determine if the current page is a registration page
 *
 * @access      public
 * @since       2.0
 * @return      bool
 */
function rcp_is_registration_page() {

	global $rcp_options, $post;

	$ret = false;

	if ( isset( $rcp_options['registration_page'] ) ) {
		$ret = is_page( $rcp_options['registration_page'] );
	}

	if ( ! empty( $post ) && has_shortcode( $post->post_content, 'register_form' ) ) {
		$ret = true;
	}

	return apply_filters( 'rcp_is_registration_page', $ret );
}

/**
 * Get the auto renew behavior
 *
 * 1 == All subscriptions auto renew
 * 2 == No subscriptions auto renew
 * 3 == Customer chooses whether to auto renew
 *
 * @access      public
 * @since       2.0
 * @return      int
 */
function rcp_get_auto_renew_behavior() {

	global $rcp_options, $rcp_level;


	// Check for old disable auto renew option
	if( isset( $rcp_options['disable_auto_renew'] ) ) {
		$rcp_options['auto_renew'] = '2';
		unset( $rcp_options['disable_auto_renew'] );
		update_option( 'rcp_settings', $rcp_options );
	}

	$behavior = isset( $rcp_options['auto_renew'] ) ? $rcp_options['auto_renew'] : '3';

	if( $rcp_level ) {
		$level = rcp_get_subscription_details( $rcp_level );
		if( $level->price == '0' ) {
			$behavior = '2';
		}
	}

	return apply_filters( 'rcp_auto_renew_behavior', $behavior );
}

/**
 * When new subscriptions are registered, a flag is set
 *
 * This removes the flag as late as possible so other systems can hook into
 * rcp_set_status and perform actions on new subscriptions
 *
 * @access      public
 * @since       2.3.6
 * @return      void
 */
function rcp_remove_new_subscription_flag( $status, $user_id ) {

	if( 'active' !== $status ) {
		return;
	}

	delete_user_meta( $user_id, '_rcp_new_subscription' );
}
add_action( 'rcp_set_status', 'rcp_remove_new_subscription_flag', 999999999999, 2 );

/**
 * When upgrading subscriptions, the new level / key are stored as pending. Once payment is received, the pending
 * values are set as the permanent values.
 *
 * See https://github.com/pippinsplugins/restrict-content-pro/issues/294
 *
 * @access      public
 * @since       2.4.3
 * @return      void
 */
function rcp_set_pending_subscription_on_upgrade( $status, $user_id ) {

	if( 'active' !== $status ) {
		return;
	}

	$subscription_id  = get_user_meta( $user_id, 'rcp_pending_subscription_level', true );
	$subscription_key = get_user_meta( $user_id, 'rcp_pending_subscription_key', true );

	if( ! empty( $subscription_id ) && ! empty( $subscription_key ) ) {

		update_user_meta( $user_id, 'rcp_subscription_level', $subscription_id );
		update_user_meta( $user_id, 'rcp_subscription_key', $subscription_key );

		delete_user_meta( $user_id, 'rcp_pending_subscription_level' );
		delete_user_meta( $user_id, 'rcp_pending_subscription_key' );
	}
}
add_action( 'rcp_set_status', 'rcp_set_pending_subscription_on_upgrade', 10, 2 );
