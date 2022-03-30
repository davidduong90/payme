<?php
/**
 * Payme Checkout Plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
class WC_Gateway_Payme_Plugin {
    const ALREADY_BOOTSTRAPED      = 1;
	const DEPENDENCIES_UNSATISFIED = 2;
	const NOT_CONNECTED            = 3;

	/**
	 * Filepath of main plugin file.
	 *
	 * @var string
	 */
	public $file;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	public $version;

	/**
	 * Absolute plugin path.
	 *
	 * @var string
	 */
	public $plugin_path;

	/**
	 * Absolute plugin URL.
	 *
	 * @var string
	 */
	public $plugin_url;

	/**
	 * Absolute path to plugin includes dir.
	 *
	 * @var string
	 */
	public $includes_path;

	
	/**
	 * Pay mode
	 *
	 * @var string
	 */
	public $pay_mode;

	
	/**
	 * Public key of payment
	 *
	 * @var string
	 */
	public $pay_publishable_key;

	
	/**
	 * Private key of payment
	 *
	 * @var string
	 */
	public $pay_private_key;

	
	/**
	 * AccessToken of payment
	 *
	 * @var string
	 */
	public $pay_accessToken;

	/**
	 * AccessToken of payment
	 *
	 * @var string
	 */
	public $pay_appId;

	
	/**
	 * AccessToken of payment
	 *
	 * @var string
	 */
	public $pay_domain;


	/**
	 * Constructor.
	 *
	 * @param string $file    Filepath of main plugin file
	 * @param string $version Plugin version
	 */
	public function __construct( $file, $version ) {
		$this->file    = $file;
		$this->version = $version;

		// Path.
		$this->plugin_path   = trailingslashit( plugin_dir_path( $this->file ) );
		$this->plugin_url    = trailingslashit( plugin_dir_url( $this->file ) );
		$this->includes_path = $this->plugin_path . trailingslashit( 'includes' );
	}

	/**
	 * Allow PayPal domains for redirect.
	 *
	 * @since 1.0.0
	 *
	 * @param array $domains Whitelisted domains for `wp_safe_redirect`
	 *
	 * @return array $domains Whitelisted domains for `wp_safe_redirect`
	 */
	public function whitelist_paypal_domains_for_redirect( $domains ) {
		$domains[] = 'www.pg.payme.vn';
		$domains[] = 'pg.payme.vn';
		$domains[] = 'www.sbx-pg.payme.vn';
		$domains[] = 'sbx-pg.payme.vn';
		return $domains;
	}

	public function get_config(){
		$settings_array = (array) get_option( 'woocommerce_payme_settings', array() );
		
		$this->pay_mode = $settings_array['testmode'];
		if($this->pay_mode == 'yes'){
			$this->pay_publishable_key = $settings_array['test_publishable_key'];
			$this->pay_private_key = $settings_array['test_private_key'];
			$this->pay_accessToken = $settings_array['test_accessToken'];
			$this->pay_appId = $settings_array['test_appId'];
			$this->pay_domain = 'https://sbx-pg.payme.vn/';
		}else{
			$this->pay_publishable_key = $settings_array['publishable_key'];
			$this->pay_private_key = $settings_array['private_key'];
			$this->pay_accessToken = $settings_array['accessToken'];
			$this->pay_appId = $settings_array['appId'];
			$this->pay_domain = 'https://pg.payme.vn/';
		}
	}
	public function get_link_payment_payme($order_id, $redirect_url){
		$this->get_config();

		// var_dump($this->pay_publishable_key);
		// var_dump($this->pay_private_key);
		// var_dump($this->pay_accessToken);
		// var_dump($this->pay_appId);
		// var_dump($this->pay_domain);
		// die('die hard');
		
		$order = wc_get_order( $order_id );
		
		$total = intval($order->get_total());
		
		$order_status  = $order->get_status(); // Get the order status (see the conditional method has_status() below)
		$currency      = $order->get_currency(); // Get the currency used  
		$payment_method = $order->get_payment_method(); // Get the payment method ID
		$payment_title = $order->get_payment_method_title(); // Get the payment method title
		// $ipn_url = site_url();
		$ipn_url = site_url('/');

		$partnerTransaction = time().rand(0,1000);
		
		// test info
		$payload = array(
			'amount'	=> $total,
			'desc'		=> "$order_id",
			'partnerTransaction'	=> "$partnerTransaction",
			'ipnUrl'		=> "$ipn_url",
			'redirectUrl'	=> "$redirect_url",
			'failedUrl'		=>  "$ipn_url",
			'payCode'		=> 'CREDIT',
		);
		
		//$json_payload = json_encode($payload);
		$api_path = '/v1/Payment/Generate';

		$apiService = new ApiService(true,  $this->pay_domain, $this->pay_appId, $this->pay_private_key, $this->pay_publishable_key, $this->pay_accessToken);
		$result = $apiService->PayMEApi($api_path, 'POST', $payload);
		// var_dump($result);die('die hard');
		$data = json_decode($result);
		
		return $data->{'data'}->{'url'};
	}

	public function payme_ipn()
	{
		$this->get_config();

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

		$header = apache_request_headers();
		$method = $_SERVER['REQUEST_METHOD'];
		
		$body = json_decode(file_get_contents('php://input'), true);
		$xAPIMessage = !empty($body['x-api-message']) ? $body['x-api-message'] : '';
		if ($xAPIMessage != ''){
			$accessToken = '';
			$xAPIKey = !empty($header['X-Api-Key']) ? $header['X-Api-Key'] : '';
			
			$xAPIValidate = !empty($header['X-Api-Validate']) ? $header['X-Api-Validate'] : '';
			
			$xApiAction = !empty($header['X-Api-Action']) ? $header['X-Api-Action'] : '';
			
			$objValidate = [
			'xApiAction' => $xApiAction,
			'method' => strtoupper($method),
			'accessToken' => $accessToken,
			'x-api-message' => $xAPIMessage
			];
			
			$apiService = new ApiService();
			try {
				$result = $apiService->decryptResponseIPN('POST', $xAPIKey, $xAPIMessage, $xAPIValidate, $accessToken, $this->pay_private_key, $xApiAction);
				$payment = json_decode($result);
				if($payment->{'state'} == 'SUCCEEDED'){
					global $woocommerce;
					$order = wc_get_order( $payment->{'desc'} );
					$order->payment_complete();
					//$order->reduce_order_stock();

					// some notes to customer (replace true with false to make it private)
					$order->add_order_note( 'Hey, your order is paid! Thank you!', true );

					// Empty cart
					$woocommerce->cart->empty_cart();
				}
			}
			catch (Exception $e) {
				write_log(print_r($e->getMessage(), TRUE));
			}
			catch (InvalidArgumentException $e) {
				write_log(print_r($e->getMessage(), TRUE));
			}
		}
	}

	function payme_woocommerce_redirect_after_checkout( $order_id ){
	
		// $order = wc_get_order( $order_id );
		
		// $order_id  = $order->get_id(); // Get the order ID
		// echo $order_id;
		// $parent_id = $order->get_parent_id(); // Get the parent order ID (for subscriptions…)

		// $user_id   = $order->get_user_id(); // Get the costumer ID
		// $user      = $order->get_user(); // Get the WP_User object

		// $order_status  = $order->get_status(); // Get the order status (see the conditional method has_status() below)
		// $currency      = $order->get_currency(); // Get the currency used  
		// $payment_method = $order->get_payment_method(); // Get the payment method ID
		// echo $payment_method;
		// $payment_title = $order->get_payment_method_title(); // Get the payment method title
		// echo $payment_title;
		// $date_created  = $order->get_date_created(); // Get date created (WC_DateTime object)
		// $date_modified = $order->get_date_modified(); // Get date modified (WC_DateTime object)

		// $billing_country = $order->get_billing_country(); // Customer billing country
	
		exit;
	}

    /**
	 * Maybe run the plugin.
	 */
	public function maybe_run() {
		register_activation_hook( $this->file, array( $this, 'activate' ) );
		add_filter( 'allowed_redirect_hosts', array( $this, 'whitelist_paypal_domains_for_redirect' ) );
		//add_action( 'woocommerce_thankyou', 'payme_woocommerce_redirect_after_checkout');
	}

}