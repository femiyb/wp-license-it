<?php

// Add Stripe Scripts 
include('stripe-scripts.php');

function stripe_process_payment() {
	if(isset($_POST['action']) && $_POST['action'] == 'stripe' && wp_verify_nonce($_POST['stripe_nonce'], 'stripe-nonce')) {
 
        if (isset($_COOKIE['wplit_product_id'] )){
            $product_id = intval($_COOKIE['wplit_product_id']); 

            global $stripe_options;
    
            // load the stripe libraries

            require 'vendor/autoload.php';

            global $current_user;
            wp_get_current_user();

            // Continue if user doesn't have license for this product
            $customer_email = (string) $current_user->user_email;
            $customer_name = (string) $current_user->user_login;
            $description = get_the_title( $product_id ) . ', (' . $customer_email . ')' ;


            if(isset($_POST['stripeToken'])){
                // retrieve the token generated by stripe.js
                $token = $_POST['stripeToken'];
            }

            $stripe_options_mode = get_option('wplit-stripe-settings-test-mode');

            $stripeamount = get_post_meta( $product_id, 'wplit_product_price', true );

            // Convert amount to Stripe price
            $amount = $stripeamount*100;

            //	$amount = get_option('stripe_settings_amount')*100;
    
            // check if we are using test mode
            if(isset($stripe_options_mode) && $stripe_options_mode) {
            	$secret_key = get_option('wplit-stripe-settings-test-sk');
            } else {
            	$secret_key = get_option('wplit-stripe-settings-live-sk');
            }

            // Verifying that HTTPS is enabled
            if (isset($_SERVER['HTTPS'])) {

                // attempt to charge the customer's card

                try {
                    \Stripe\Stripe::setApiKey($secret_key);
                    \Stripe\Stripe::setApiVersion('2020-08-27');
                    \Stripe\Stripe::setAppInfo(
                        'WordPress WPLicenseIt',
                        '0.1',
                        'https://wplicenseit.com',
                    );

                    // Get Customer ID and Email
                    $customer = \Stripe\Customer::create(array(
                        'description' => $description,
                        'name' => $customer_name,
                        'email' => $customer_email,
                        'source'  => $token
                    ));

                    $charge = \Stripe\Charge::create(array(
                            'customer' => $customer->id,
                            'description' => $description,
                            'amount' => $amount, 
                            'currency' => 'usd',
                        )
                    );

                    $success = json_encode($charge->paid); // Expected value = true
                    $success = json_encode($charge->status); // Expected value = succeeded
                    if($success) { // or $success == "succeeded" depending on which array key you go for.
                        // Payment succeeded! Do something...
                        setcookie("payment_status", 'success', time()+5, '/');

                        // redirect on successful payment		
                        $WPLit_Add_License = new WPLit_Add_License;
                        $WPLit_Add_License->wplit_add_license();	

                        $url = get_permalink(get_option('wplit-licenses-page'));   

                        $redirect = esc_url_raw(add_query_arg('payment', 'success', $url ));

                        do_action('wplit_after_stripe_checkout');
                    }else
                    {
                        print_r(json_encode($charge->failure_message));
                    }

                    // Do something else...

                //	var_dump($charge);
                //	exit; 
                } catch (Exception $e) {
                    // redirect on failed payment

                    $redirect = esc_url_raw(add_query_arg('payment', 'failed', $_POST['redirect']));

                }
            }
    
            // redirect to Licenses page the added query variable
            wp_redirect($redirect); exit;
        }
	}
}
add_action('init', 'stripe_process_payment');