<?php

/**
 * Determine if a member is a PayPal subscriber
 *
 * @since       v2.0
 * @access      public
 * @param       $user_id INT the ID of the user to check
 * @return      bool
*/
function rcp_is_paypal_subscriber( $user_id = 0 ) {

	if( empty( $user_id ) ) {
		$user_id = get_current_user_id();
	}

	$ret        = false;
	$member     = new RCP_Member( $user_id );
	$profile_id = $member->get_payment_profile_id();

	// Check if the member is a PayPal customer
	if( false !== strpos( $profile_id, 'I-' ) ) {

		$ret = true;

	} else {

		// The old way of identifying PayPal subscribers
		$ret = (bool) get_user_meta( $user_id, 'rcp_paypal_subscriber', true );

	}

	return (bool) apply_filters( 'rcp_is_paypal_subscriber', $ret, $user_id );
}

/**
 * Determine if PayPal API access is enabled
 *
 * @access      public
 * @since       2.1
 */
function rcp_has_paypal_api_access() {
	global $rcp_options;

	$ret    = false;
	$prefix = 'live_';

	if( isset( $rcp_options['sandbox'] ) ) {
		$prefix = 'test_';
	}

	$username  = $prefix . 'paypal_api_username';
	$password  = $prefix . 'paypal_api_password';
	$signature = $prefix . 'paypal_api_signature';

	if( ! empty( $rcp_options[ $username ] ) && ! empty( $rcp_options[ $password ] ) && ! empty( $rcp_options[ $signature ] ) ) {

		$ret = true;

	}

	return $ret;
}

/**
 * Retrieve PayPal API credentials
 *
 * @access      public
 * @since       2.1
 */
function rcp_get_paypal_api_credentials() {
	global $rcp_options;

	$ret    = false;
	$prefix = 'live_';

	if( isset( $rcp_options['sandbox'] ) ) {
		$prefix = 'test_';
	}

	$creds = array(
		'username'  => $rcp_options[ $prefix . 'paypal_api_username' ],
		'password'  => $rcp_options[ $prefix . 'paypal_api_password' ],
		'signature' => $rcp_options[ $prefix . 'paypal_api_signature' ]
	);

	return apply_filters( 'rcp_get_paypal_api_credentials', $creds );
}