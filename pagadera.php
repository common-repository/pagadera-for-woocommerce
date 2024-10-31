<?php

/**
 * Plugin Name: PAGADERA
 * Plugin URI: https://wordpress.com/plugins/pagadera-for-woocommerce
 * Description: This is a payment gateway plugin for the Pagadera Payment Network which integrates into the WooCommerce plugin as a payment option.
 * Version: 2.2.0
 * Author: Pagadera
 * Author URI: https://www.pagadera.com
 * */
if (!defined('ABSPATH')) {
    exit;
}

/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter('woocommerce_payment_gateways', 'pagadera_add_gateway_class'); 

function pagadera_add_gateway_class($gateways) {
    $gateways[] = 'WC_Pagadera_Gateway'; // your class name is here
    return $gateways;
}

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action('plugins_loaded', 'pagadera_init_gateway_class');

function pagadera_init_gateway_class() {

    class WC_Pagadera_Gateway extends WC_Payment_Gateway {

        /**
         * Class constructor, more about it in Step 3
         */
        public function __construct() {

            $this->id = 'pagadera'; // payment gateway plugin ID
            $this->icon = plugins_url( 'img/pagadera-smaller.png', __FILE__ );  // URL of the icon that will be displayed on checkout page near your gateway name
            $this->has_fields = true; // in case you need a custom credit card form
            $this->method_title = 'Pagadera Payment Gateway';
            $this->method_description = 'Accept local payments without sharing any financial information. Fill in the form below to activate your Pagadera Payment method on your checkout page. The required information can be retrieved from your Pagadera Business Wallet API page.'; // will be displayed on the options page
            // gateways can support subscriptions, refunds, saved payment methods,
            // but in this tutorial we begin with simple payments
            $this->supports = array(
                'products'
            );

            // Method with all the options fields
            $this->init_form_fields();

            // Load the settings.
            $this->init_settings();
            $this->title = 'Pagadera';
            $this->description = 'You will be redirected to the Pagadera gateway where you will get your code to scan and pay safely with your Pagadera APP or portal.';
            $this->enabled = $this->get_option('enabled');
            $this->api_url = $this->get_option('api_url');
            $this->api_currency = $this->get_option('api_currency');
            $this->api_key = $this->get_option('api_key');
            $this->api_secret = $this->get_option('api_secret');
            $this->api_returnurl = $this->get_option('api_returnurl');
            $this->api_debug = $this->get_option('api_debug');

            $this->APIServiceURL = '/api/shop/sitelink';
            $this->RedirectServiceURL = '/api/shop/gateway/woocommerce';

            // This action hook saves the settings
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            // You can also register a webhook here
            add_action('woocommerce_api_pagaderaresult', array($this, 'webhook'));
        }

        /**
         * Plugin options, we deal with it in Step 3 too
         */
        public function init_form_fields() {

            $this->form_fields = array(
                'enabled' => array(
                    'title' => 'Enable/Disable',
                    'label' => 'Enable Pagadera Gateway',
                    'type' => 'checkbox',
                    'description' => 'Return URL to enter in your Pagadera API settings: '.get_site_url(),
                    'default' => 'no'
                ),
                'api_url' => array(
                    'title' => 'Provider API URL',
                    'type' => 'text',
                    'description' => 'Copy this information after creating your Pagadera API.',
                ),
                'api_key' => array(
                    'title' => 'API Key',
                    'type' => 'text',
                    'description' => 'Copy this information after creating your Pagadera API.',
                ),
                'api_secret' => array(
                    'title' => 'API Secret',
                    'type' => 'password',
                    'description' => 'Copy this information after creating your Pagadera API.',
                ) ,  
                    'api_currency' => array(
                    'title' => 'Currency',
                    'type' => 'text',
                    'description' => 'Copy this information after creating your Pagadera API.',
                ),      
                    'api_debug' => array(
                    'title' => 'Debug',
                    'label' => 'Enable Debug',
                    'type' => 'checkbox',
                    'description' => 'To view the Pagadera log go to WooCommerce => System Status => Logs and select the file pagadera-....log.',
                )     
            );
        }

        /*
         * We're processing the payments here, everything about it is in Step 5
         */

        public function process_payment($order_id) {

            global $woocommerce;
            global $logger;

            // LOAD THE WC LOGGER
            $logger = wc_get_logger();  

            // we need it to get any order detailes
            $order = wc_get_order($order_id);
            $order_data = $order->get_data(); // The Order data

            $timestamp = date('Y-m-d H:i:s');
            /*
             * Array with parameters for API interaction
             */

            $ClientSignature = hash('sha256', $this->api_key . $timestamp . $order_id . $order->get_total() . $this->api_currency . '' . $this->api_secret);

	        if($this->api_debug == 'yes')
 	        {
                $logger->debug('Token Requested', array( 'source' => 'pagadera' ) );
	        }

            $args = array(
                'method' => 'POST',
                'timeout' => 45,
                'blocking' => true,
                'body' => array(
                    'APIKey' => $this->api_key,
                    'Timestamp' => (string) $timestamp,
                    'OrderReference' => $order_id,
                    'TotalAmount' => (string) $order->get_total(),
                    'Currency' => $this->api_currency,
                    'ClientSignature' => $ClientSignature));

            $callURL = $this->api_url . $this->APIServiceURL . '/RequestToken';

            $response = wp_remote_post($callURL, $args);

            if (!is_wp_error($response)) {

                $body = json_decode($response['body'], true);

                if ($body['ServiceCode'] == '4') {

	                if($this->api_debug == 'yes')
 	                {
                        $logger->debug('Token Request Successful', array( 'source' => 'pagadera' ) );
	                }

                    $ServiceSignatureText = $ClientSignature . $body['ServiceCode'] . $body['ServiceMessage'] . $body['OrderReference'] . $body['TotalAmount'] . $body['Currency'] . $body['CurrencyDisplay'] . $body['Note'] . $body['TokenCode'] . $body['TokenCodeDisplay'] . $this->api_secret;

                    if (hash('sha256', $ServiceSignatureText) != strtolower($body['ServiceSignature'])) {
                        error_log('Pagadera - Integrity check failed.');
			            $logger->error('Integrity check failed.' . $body['TokenCode'], array( 'source' => 'pagadera' ) );
                        wc_add_notice('Integrity error.', 'error');
                        return;
                    }

                    $TokenCode = $body['TokenCode'];

                    wc_add_notice('Token: ' . $body['TokenCode'], 'info');

	                if($this->api_debug == 'yes')
 	                {
                        $logger->debug('Received token ' . $body['TokenCode'], array( 'source' => 'pagadera' ) );
	                }

                    return array(
                        'result' => 'success',
                        'redirect' => $this->api_url . $this->RedirectServiceURL . '/' . $this->api_key . '/' . $timestamp . '/' . $TokenCode . '/' . $order_id . '/' . hash('sha256', $this->api_key . $timestamp . $TokenCode . $order_id . $this->api_secret)
                    );
                } else {
                    error_log('Pagadera - Service error. - ' . $body['ServiceMessage']);
		            $logger->error('Service Error - ' . $body['ServiceMessage'], array( 'source' => 'pagadera' ) );
                    wc_add_notice('Service error. Please try again.', 'error');
                    return;
                }
            } else {
                error_log('Pagadera - Connection error.');
    	        $logger->error('Connection Error', array( 'source' => 'pagadera' ) );
                wc_add_notice('Connection error. Please try again.', 'error');
                return;
            }
        }

        public function webhook() {

            global $woocommerce;
            global $logger;

            // LOAD THE WC LOGGER
            $logger = wc_get_logger();  

            $order = wc_get_order(sanitize_text_field($_GET['OrderId']));

            $Result = sanitize_text_field($_GET['Result']);
            $ErrorMessage = sanitize_text_field($_GET['errorMessage']);

            if (strtolower($Result) == 'cancelled') {
                error_log('Pagadera - ' . $ErrorMessage);
    	        $logger->error($Result . ' - ' . $ErrorMessage, array( 'source' => 'pagadera' ) );
                wc_add_notice('Payment was cancelled. Please try again.', 'error');
                wp_redirect(wc_get_checkout_url());
                die();
            }

            if (strtolower($Result) == 'expired') {
                error_log('Pagadera - ' . $ErrorMessage);
    	        $logger->error($Result . ' - ' . $ErrorMessage, array( 'source' => 'pagadera' ) );
                wc_add_notice('Token has expired. Please try again.', 'error');
                wp_redirect(wc_get_checkout_url());
                die();
            }

            if (strtolower($Result) == 'failed') {
                error_log('Pagadera - ' . $ErrorMessage);
    	        $logger->error($Result . ' - ' . $ErrorMessage, array( 'source' => 'pagadera' ) );
                wc_add_notice('Payment failed. Please try again.', 'error');
                wp_redirect(wc_get_checkout_url());
                die();
            }

            if (strtolower($Result) == 'success') {
                if ($order != null) {
                    //Check if order is available for payment processing
                    if ('processing' != $order->status && 'completed' != $order->status && 'cancelled' != $order->status) {
                        $Timestamp = sanitize_text_field($_GET['Timestamp']);
                        $TokenCode = sanitize_text_field($_GET['TokenCode']);
                        $OrderID = sanitize_text_field($_GET['OrderId']);
                        $ServiceSignature = sanitize_text_field($_GET['ServiceSignature']);

                        //Check message signature
                        if (hash('sha256', $this->api_key . $Timestamp . $TokenCode . $OrderID . $Result . $ErrorMessage . $this->api_secret) == strtolower($ServiceSignature)) {

	                        if($this->api_debug == true)
 	                        {
                                $logger->debug('Signature correct.', array( 'source' => 'pagadera' ) );
	                        }

                            $TokenCode = sanitize_text_field($_GET['TokenCode']);
                            $order->set_transaction_id($TokenCode);

                            if($this->api_debug == 'yes')
                            {
                                $logger->debug('Payment successful', array( 'source' => 'pagadera' ) );
                            }

                            $order->payment_complete();

                            // some notes to customer (replace true with false to make it private)
                            $order->add_order_note('Your order is paid! Thank you!', true);

                            $woocommerce->cart->empty_cart();

                            update_option('webhook_debug', $_GET);

                            wp_redirect($this->get_return_url($order));
                            exit;
                        } else {
                            error_log('Pagadera - Signature failed.');
          	                $logger->error('Pagadera - Signature failed.', array( 'source' => 'pagadera' ) );
                            wc_add_notice('Payment failed. Please try again.', 'error');
                            wp_redirect(wc_get_checkout_url());
                            die();
                        }
                    } else {
                        error_log('Pagadera - Order is no longer available for payment confirmation. - Status: ' . $order->status);
          	            $logger->error('Pagadera - Order is no longer available for payment confirmation. - Status: ' . $order->status, array( 'source' => 'pagadera' ) );
                        wc_add_notice('Order not available for payment confirmation. Please contact us.', 'error');
                        wp_redirect(wc_get_checkout_url());
                        die();
                    }
                }
                else
                {
                    error_log('Pagadera - Order is not available for payment confirmation.');
          	        $logger->error('Pagadera - Order is not available for payment confirmation.', array( 'source' => 'pagadera' ) );
                    wc_add_notice('Order not available for payment confirmation. Please contact us.', 'error');
                    wp_redirect(wc_get_checkout_url());
                    die();
                }
            }

            error_log('Pagadera - ' . $ErrorMessage);
            $logger->error('No valid Result received - ' . $Result . ' - ' . $ErrorMessage, array( 'source' => 'pagadera' ) );
            wc_add_notice('Payment failed. Please try again.', 'error');
            wp_redirect(wc_get_checkout_url());
            die();
        }

    }

}
