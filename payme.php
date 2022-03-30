<?php
/*
 * Plugin Name: Payme.vn Payment Gateway
 * Plugin URI: https://magepow.com
 * Description: Take Payme.vn payments on your store.
 * Author: David Duong of Magepow
 * Author URI: https://magepow.com
 * Version: 1.0.0
 */

 /*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
define( 'WC_GATEWAY_PAYME_VERSION', '1.0.1' );

add_filter( 'woocommerce_payment_gateways', 'payme_add_gateway_class' );
function payme_add_gateway_class( $gateways ) {
	$gateways[] = 'WC_Payme_Gateway'; // your class name is here
	return $gateways;
}

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action( 'plugins_loaded', 'magepow_init_gateway_class' );
function magepow_init_gateway_class() {

	class WC_Payme_Gateway extends WC_Payment_Gateway {
 		/**
 		 * Class constructor, more about it in Step 3
 		 */
 		public function __construct() {
            $this->id = 'payme'; // payment gateway plugin ID
            $this->icon = ''; // URL of the icon that will be displayed on checkout page near your gateway name
            $this->has_fields = true; // in case you need a custom credit card form
            $this->method_title = 'Payme Gateway';
            $this->method_description = 'Payment.vn payment gateway'; // will be displayed on the options page

            // gateways can support subscriptions, refunds, saved payment methods,
            // but in this tutorial we begin with simple payments
            $this->supports = array(
                'products'
            );

            // Method with all the options fields
            $this->init_form_fields();

            // Load the settings.
            $this->init_settings();
            $this->title = $this->get_option( 'title' );
            $this->description = $this->get_option( 'description' );
            $this->enabled = $this->get_option( 'enabled' );
            $this->testmode = 'yes' === $this->get_option( 'testmode' );
            $this->private_key = $this->testmode ? $this->get_option( 'test_private_key' ) : $this->get_option( 'private_key' );
            $this->publishable_key = $this->testmode ? $this->get_option( 'test_publishable_key' ) : $this->get_option( 'publishable_key' );

            // This action hook saves the settings
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

            // We need custom JavaScript to obtain a token
            add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
            
            // You can also register a webhook here
            //add_action( 'woocommerce_api_payme', array( $this, 'webhook' ) );
 		}

		/**
 		 * Plugin options, we deal with it in Step 3 too
 		 */
 		public function init_form_fields(){
            $this->form_fields = array(
                'enabled' => array(
                    'title'       => 'Enable/Disable',
                    'label'       => 'Enable Payme Gateway',
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ),
                'title' => array(
                    'title'       => 'Title',
                    'type'        => 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'default'     => 'Payme payment',
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => 'Description',
                    'type'        => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default'     => 'Pay with your payme payment via our super-cool payment gateway.',
                ),
                'testmode' => array(
                    'title'       => 'Test mode',
                    'label'       => 'Enable Test Mode',
                    'type'        => 'checkbox',
                    'description' => 'Place the payment gateway in test mode using test API keys.',
                    'default'     => 'yes',
                    'desc_tip'    => true,
                ),
                'test_publishable_key' => array(
                    'title'       => 'Test Publishable Key',
                    'type'        => 'password'
                ),
                'test_private_key' => array(
                    'title'       => 'Test Private Key',
                    'type'        => 'password',
                ),
                'test_accessToken' => array(
                    'title'       => 'Test Access Token',
                    'type'        => 'password'
                ),
                'test_appId' => array(
                    'title'       => 'Test AppId',
                    'type'        => 'text'
                ),
                'publishable_key' => array(
                    'title'       => 'Live Publishable Key',
                    'type'        => 'password'
                ),
                'private_key' => array(
                    'title'       => 'Live Private Key',
                    'type'        => 'password'
                ),
                'accessToken' => array(
                    'title'       => 'Live Access Token',
                    'type'        => 'password'
                ),'appId' => array(
                    'title'       => 'AppId',
                    'type'        => 'text'
                )
            );
	 	}

		/**
		 * You will need it if you want your custom credit card form, Step 4 is about it
		 */
		public function payment_fields() {
            // ok, let's display some description before the payment form
            if ( $this->description ) {
                // you can instructions for test mode, I mean test card numbers etc.
                if ( $this->testmode ) {
                    $this->description .= ' TEST MODE ENABLED. In test mode, you can use the card numbers listed in <a href="#">documentation</a>.';
                    $this->description  = trim( $this->description );
                }
                // display the description with <p> tags etc.
                echo wpautop( wp_kses_post( $this->description ) );
            }
        
            // I will echo() the form, but you can close PHP tags and print it directly in HTML
            // echo '<fieldset id="wc-' . esc_attr( $this->id ) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';
        
            // Add this action hook if you want your custom payment gateway to support it
            // do_action( 'woocommerce_credit_card_form_start', $this->id );
        
            // I recommend to use inique IDs, because other gateways could already use #ccNo, #expdate, #cvc
            // echo '<div class="form-row form-row-wide"><label>Card Number <span class="required">*</span></label>
            //     <input id="misha_ccNo" type="text" autocomplete="off">
            //     </div>
            //     <div class="form-row form-row-first">
            //         <label>Expiry Date <span class="required">*</span></label>
            //         <input id="misha_expdate" type="text" autocomplete="off" placeholder="MM / YY">
            //     </div>
            //     <div class="form-row form-row-last">
            //         <label>Card Code (CVC) <span class="required">*</span></label>
            //         <input id="misha_cvv" type="password" autocomplete="off" placeholder="CVC">
            //     </div>
            //     <div class="clear"></div>';
        
            // do_action( 'woocommerce_credit_card_form_end', $this->id );
        
            echo '<div class="clear"></div></fieldset>';				 
		}

		/*
		 * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
		 */
	 	public function payment_scripts() {
             // we need JavaScript to process a token only on cart/checkout pages, right?
            if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) ) {
                return;
            }

            // if our payment gateway is disabled, we do not have to enqueue JS too
            if ( 'no' === $this->enabled ) {
                return;
            }

            // no reason to enqueue JavaScript if API keys are not set
            if ( empty( $this->private_key ) || empty( $this->publishable_key ) ) {
                return;
            }

            // do not work with card detailes without SSL unless your website is in a test mode
            if ( ! $this->testmode && ! is_ssl() ) {
                return;
            }

            // let's suppose it is our payment processor JavaScript that allows to obtain a token
            wp_enqueue_script( 'misha_js', 'https://www.mishapayments.com/api/token.js' );

            // and this is our custom JS in your plugin directory that works with token.js
            wp_register_script( 'woocommerce_misha', plugins_url( 'misha.js', __FILE__ ), array( 'jquery', 'misha_js' ) );

            // in most payment processors you have to use PUBLIC KEY to obtain a token
            wp_localize_script( 'woocommerce_misha', 'misha_params', array(
                'publishableKey' => $this->publishable_key
            ) );

            wp_enqueue_script( 'woocommerce_misha' );

	 	}

		/*
 		 * Fields validation, more in Step 5
		 */
		public function validate_fields() {
            // if( empty( $_POST[ 'billing_first_name' ]) ) {
            //     wc_add_notice(  'First name is required!', 'error' );
            //     return false;
            // }
            return true;
		}

        function payme_ipn_listener() {
            // check for your custom query var
            // If you are paranoid you can also check the value of the var
            if (!function_exists('write_log')) {

                function write_log($log) {
                    if (true === WP_DEBUG) {
                        if (is_array($log) || is_object($log)) {
                            error_log(print_r($log, true));
                        } else {
                            error_log($log);
                        }
                    }
                }
            
            }
            
            $plugin = new WC_Gateway_Payme_Plugin( __FILE__, WC_GATEWAY_PAYME_VERSION );

            $plugin->payme_ipn();
        }

		/*
		 * We're processing the payments here, everything about it is in Step 5
		 */
		public function process_payment( $order_id ) {
            global $woocommerce;
            
            // we need it to get any order detailes
            $order = wc_get_order( $order_id );

            $plugin = new WC_Gateway_Payme_Plugin( __FILE__, WC_GATEWAY_PAYME_VERSION );
            
            $redirect_url = $this->get_return_url( $order);
            $pay_url = $plugin->get_link_payment_payme($order_id, $redirect_url);
            
            try {
				return array(
					'result'   => 'success',
					'redirect' => $pay_url,
				);
			} catch ( PayPal_API_Exception $e ) {
				wc_add_notice( $e->getMessage(), 'error' );
			}
            exit;
	 	}
 	}
}

 /**
 * Return instance of WC_Gateway_PPEC_Plugin.
 *
 * @return WC_Gateway_Payme_Plugin
 */
function wc_gateway_payme() {
    static $plugin;

    if ( ! isset( $plugin ) ) {
        require_once 'includes/ApiService.php';
        require_once 'includes/class-wc-gateway-payme-plugin.php';

        $plugin = new WC_Gateway_Payme_Plugin( __FILE__, WC_GATEWAY_PAYME_VERSION );
    }

    return $plugin;
}

wc_gateway_payme()->maybe_run();

function wc_payme_ipn_check(){
    static $Payme_Gateway;

    if ( ! isset( $Payme_Gateway ) ) {
        $Payme_Gateway = new WC_Payme_Gateway();
    }

    $Payme_Gateway->payme_ipn_listener();
}

add_action( 'init', 'wc_payme_ipn_check' );


function custom_checkout_create_order_line_item( $item, $cart_item_key, $values, $order ) {
    if (!function_exists('write_log')) {

        function write_log($log) {
            if (true === WP_DEBUG) {
                if (is_array($log) || is_object($log)) {
                    error_log(print_r($log, true));
                } else {
                    error_log($log);
                }
            }
        }
    
    }
    write_log(print_r($order, TRUE));
    $item->update_meta_data( 'Custom label', 'a custom code' );
}

add_action( 'woocommerce_checkout_create_order_line_item', 'custom_checkout_create_order_line_item', 20, 4 );