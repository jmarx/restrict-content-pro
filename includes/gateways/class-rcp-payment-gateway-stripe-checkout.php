<?php
/**
 * Payment Gateway For Stripe Checkout
 *
 * @package     Restrict Content Pro
 * @subpackage  Classes/Roles
 * @copyright   Copyright (c) 2012, Pippin Williamson
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.5
*/

class RCP_Payment_Gateway_Stripe_Checkout extends RCP_Payment_Gateway_Stripe {


	/**
	 * Print fields for this gateway
	 *
	 * @return string
	 */
	public function fields() {

		if( is_user_logged_in() ) {
			$email = wp_get_current_user()->user_email;
		} else {
			$email = false;
		}

		$data = apply_filters( 'rcp_stripe_checkout_form_data', array(
			'key'               => $this->publishable_key,
			'local'             => 'auto',
			'allow-remember-me' => true,
			'email'             => $email,
		) );


		$subscriptions = array();
		foreach ( rcp_get_subscription_levels( 'active' ) as $subscription ) {
			$subscriptions[ $subscription->id ] = array(
				'description' => $subscription->description,
				'name'        => $subscription->name,
				'label'       => sprintf( __( 'Join %s', 'rcp' ), $subscription->name ),
				'amount'      => '',
				'panelLabel'  => __( 'Register', 'rcp' ),
			);
		}

		$subscriptions = apply_filters( 'rcp_stripe_checkout_subscription_data', $subscriptions );

		ob_start(); ?>

		<script>
			var rcp_script_options;
			var rcpSubscriptions = <?php echo json_encode( $subscriptions ); ?>;
			var checkoutArgs     = <?php echo json_encode( $data ); ?>;

			// define the token function
			checkoutArgs.token = function(token){ jQuery('body').trigger('rcp_stripe_checkout_submit', token); };

			if( ! checkoutArgs.email ) {
				checkoutArgs.email = jQuery('#rcp_registration_form #rcp_user_email' ).val();
			}
			
			jQuery('#rcp_registration_form #rcp_submit').val( rcp_script_options.pay_now );

			jQuery('body').on('rcp_stripe_checkout_submit', function(e, token){
				jQuery('#rcp_registration_form').append('<input type="hidden" name="stripeToken" value="' + token.id + '" />').submit();
			});

			var rcpStripeCheckout = StripeCheckout.configure(checkoutArgs);

			jQuery('#rcp_registration_form #rcp_submit').on('click', function(e) {
				var $form = jQuery(this).closest('form');
				var $level = $form.find('input[name=rcp_level]:checked');

				if (!$level.length) {
					$level = $form.find('input[name=rcp_level]');
				}

				// Open Checkout with further options
				rcpStripeCheckout.open(rcpSubscriptions[$level.val()]);
				e.preventDefault();

				return false;
			});

			// Close Checkout on page navigation
			jQuery(window).on('popstate', function() {
				rcpStripeCheckout.close();
			});
		</script>

		<?php
		return ob_get_clean();
	}

	/**
	 * Load Stripe JS
	 *
	 * @since 2.5
	 */
	public function scripts() {
		parent::scripts();
		wp_enqueue_script( 'stripe-checkout', 'https://checkout.stripe.com/checkout.js', array( 'jquery' ) );

	}

	/**
	 * Validate fields
	 *
	 * @since 2.5
	 */
	public function validate_fields() {}

}