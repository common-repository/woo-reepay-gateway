<?php
/**
* Plugin Name: WooCommerce Reepay Gateway
* Description: Take credit card payments on your store using Reepay.
* Version: 1.2.3
* Author: Codemakers
* Author URI: https://codemakers.dk
* WC tested up to: 3.4
* WC requires at least: 2.6
*/

if (!defined('ABSPATH')) {
    exit;
}

add_filter( 'woocommerce_payment_gateways', 'reepay_add_gateway_class' );
function reepay_add_gateway_class( $gateways ) {
	$gateways[] = 'WC_Reepay_Gateway';
	return $gateways;
}

function reepay_add_query_vars_filter( $vars ) {
  $vars[] = "id";
  $vars[] = "key";
  $vars[] = "customer";
  return $vars;
}
add_filter( 'query_vars', 'reepay_add_query_vars_filter' );

add_action( 'plugins_loaded', 'reepay_init_gateway_class' );
function reepay_init_gateway_class() {
 
	class WC_Reepay_Gateway extends WC_Payment_Gateway {
 		
		public function __construct() {
		
			$this->id = 'reepay';
			$this->has_fields = true;
			$this->method_title = 'Reepay Gateway';
			$this->method_description = 'Description of Reepay payment gateway';
			
			$this->supports = array(
			'products',
			'refunds',
			'subscriptions',
			'multiple_subscriptions',
			'subscription_cancellation', 
		   	'subscription_suspension', 
		   	'subscription_reactivation',
		   	'subscription_amount_changes',
		   	'subscription_date_changes',
			);
			$this->reepay_version = "wordpress/v1.2.3 (codemakers)";
			if($this->get_option( 'payments_cards_icons_dankort' ) == "yes"){
			$this->icon_array[] = plugins_url( 'images/dankort.png', __FILE__ );
			}
			if($this->get_option( 'payments_cards_icons_visa' ) == "yes"){
			$this->icon_array[] = plugins_url( 'images/visa.png', __FILE__ );
			}
			if($this->get_option( 'payments_cards_icons_mastercard' ) == "yes"){
			$this->icon_array[] = plugins_url( 'images/mastercard.png', __FILE__ );
			}
			if($this->get_option( 'payments_cards_icons_visa_electron' ) == "yes"){
			$this->icon_array[] = plugins_url( 'images/visa-electron.png', __FILE__ );
			}
			if($this->get_option( 'payments_cards_icons_maestro' ) == "yes"){
			$this->icon_array[] = plugins_url( 'images/maestro.png', __FILE__ );
			}
			if($this->get_option( 'payments_cards_icons_mobilepay_online' ) == "yes"){
			$this->icon_array[] = plugins_url( 'images/mobilepay.png', __FILE__ );
			}
			if($this->get_option( 'payments_cards_icons_viabill' ) == "yes"){
			$this->icon_array[] = plugins_url( 'images/viabill.png', __FILE__ );
			}
			if($this->get_option( 'payments_cards_icons_forbrugsforeningen' ) == "yes"){
			$this->icon_array[] = plugins_url( 'images/forbrugsforeningen.png', __FILE__ );
			}
			if($this->get_option( 'payments_cards_icons_american_express' ) == "yes"){
			$this->icon_array[] = plugins_url( 'images/american-express.png', __FILE__ );
			}
			if($this->get_option( 'payments_cards_icons_jcb' ) == "yes"){
			$this->icon_array[] = plugins_url( 'images/jcb.png', __FILE__ );
			}
			if($this->get_option( 'payments_cards_icons_diners_club_international' ) == "yes"){
			$this->icon_array[] = plugins_url( 'images/diners-club-international.png', __FILE__ );
			}
			if($this->get_option( 'payments_cards_icons_unionpay' ) == "yes"){
			$this->icon_array[] = plugins_url( 'images/unionpay.png', __FILE__ );
			}
			if($this->get_option( 'payments_cards_icons_discover' ) == "yes"){
			$this->icon_array[] = plugins_url( 'images/discover.png', __FILE__ );
			}
			
			$this->init_form_fields();
			$this->init_settings();
			$this->title = $this->get_option( 'title' );
			$this->description = $this->get_option( 'description' );
			$this->enabled = $this->get_option( 'enabled' );
			$this->reepay_private_key = $this->get_option( 'reepay_private_key' );
			$this->reepay_public_key = $this->get_option( 'reepay_public_key' );
			$this->test_enabled = $this->get_option( 'test_enabled' );
			$this->test_reepay_private_key = $this->get_option( 'test_reepay_private_key' );
			$this->max_icon_height = $this->get_option( 'max_icon_height' );
			$this->payment_window_display = $this->get_option( 'payment_window_display' );
			$this->capture_all_payments = $this->get_option( 'capture_all_payments' );
			$this->simple_product = $this->get_option( 'simple_product' );
			$this->virtual_product = $this->get_option( 'virtual_product' );
			
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
			add_action( 'woocommerce_thankyou', array( $this, 'reepay_thankyou' ));
			add_action( 'woocommerce_order_item_add_action_buttons', array( $this, 'add_reepay_charge_button' ) );
			add_filter( 'woocommerce_valid_order_statuses_for_cancel', array( $this, 'filter_valid_order_statuses_for_cancel'), 20, 2 );
			add_filter( 'woocommerce_valid_order_statuses_for_payment', array( $this, 'filter_valid_order_statuses_for_cancel'), 20, 2 );
			add_action( 'admin_notices', array( $this, 'sample_admin_notice_warning') );
			add_filter( 'woocommerce_gateway_icon', array( $this, 'add_payment_icon' ), 10, 2);
			add_action( 'woocommerce_after_checkout_form', array( $this, 'add_scripts_checkout' ));
			
			add_action( 'woocommerce_scheduled_subscription_payment_'.$this->id , array($this, 'scheduled_subscription_payment'), 10, 2 );
		}
		
		public function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {
			
			$order_id = $renewal_order->id;
			$data = json_decode($renewal_order, true);
			foreach($data as $key => $value){
				if($key == "billing"){
					foreach ($value as $keys => $entry) {
						if($keys == "email"){$billing_email = $entry;}
						if($keys == "address_1"){$billing_address_1 = $entry;}
						if($keys == "address_2"){$billing_address_2 = $entry;}
						if($keys == "city"){$billing_city = $entry;}
						if($keys == "country"){$billing_country = $entry;}
						if($keys == "phone"){$billing_phone = $entry;}
						if($keys == "company"){$billing_company = $entry;}
						if($keys == "postcode"){$billing_postcode = $entry;}
						if($keys == "first_name"){$billing_first_name = $entry;}
						if($keys == "last_name"){$billing_last_name = $entry;}
						if($keys == "state"){$billing_state = $entry;}
					}
				}
				if($key == "shipping"){
					foreach ($value as $keys => $entry) {
						if($keys == "first_name"){$shipping_first_name = $entry;}
						if($keys == "last_name"){$shipping_last_name = $entry;}
						if($keys == "company"){$shipping_company = $entry;}
						if($keys == "address_1"){$shipping_address_1 = $entry;}
						if($keys == "address_2"){$shipping_address_2 = $entry;}
						if($keys == "city"){$shipping_city = $entry;}
						if($keys == "state"){$shipping_state = $entry;}
						if($keys == "postcode"){$shipping_postcode = $entry;}
						if($keys == "country"){$shipping_country = $entry;}
					}
				}
			}
			if(get_option( 'woocommerce_prices_include_tax' ) == "yes"){
				$amount_incl_vat = "true";
			}else{
				$amount_incl_vat = "false";
			}
			if($this->test_enabled == 'yes'){
				$key = base64_encode($this->test_reepay_private_key);
			}else{
				$key = base64_encode($this->reepay_private_key);
			}
			$price = $amount_to_charge * 100;
			
			$login_user_id = $renewal_order->customer_id;
			$customer_id = get_user_meta($login_user_id, 'reepay_customer_id', true);
			
			if($renewal_order->prices_include_tax == '1'){
				$prices_include_tax	= true;
			}else{
				$prices_include_tax	= false;
			}
			
			global $woocommerce;
			$woocommerce_currency = get_option('woocommerce_currency');
			$order = wc_get_order( $order_id );
			$settle_all_product = $this->capture_all_payments;
			$virtual_product = $this->virtual_product;
			$simple_product = $this->simple_product;
			$simple_value = false;
			$virtual_value = false;
			$items = $order->get_items();
			$product_type = array();
			$is_trial = false;
			$order_list_item.='[';
			foreach ( $items as $item ) {
				$product = get_product($item->get_product_id());
				$item_price = $product->get_price() * 100;
				$tax_rates = WC_Tax::get_rates( $product->get_tax_class() );
				if (!empty($tax_rates)) {
					$tax_rate = reset($tax_rates);
					$taxrate = $tax_rate['rate'] / 100;
					$order_list_item.= '{"ordertext":"'.$product->get_title().'","amount":'.floatval($item_price).',"vat":'.$taxrate.',"quantity":'.$item['quantity'].',"amount_incl_vat":'.$amount_incl_vat.'},';
				}else{
					$order_list_item.= '{"ordertext":"'.$product->get_title().'","amount":'.floatval($item_price).',"quantity":'.$item['quantity'].',"amount_incl_vat":'.$amount_incl_vat.'},';
				}
				if(true == $product->is_virtual() || true == $product->is_downloadable()){
					$virtual_value = true;
				}else{
					$simple_value = true;
				}
				
				if ( class_exists( 'WC_Subscriptions_Product' ) && WC_Subscriptions_Product::get_trial_length( $item->get_product_id() ) > 0 ) {
					$is_trial = true;		
				}
			}
			$tax_rate = reset($tax_rates);
			$taxrate = $tax_rate['rate'] / 100; 
			if($tax_rate['shipping'] == "yes"){
				$amount_shipping_incl_vat = "false";
				$s_tax = $taxrate;
			}else{
				$amount_shipping_incl_vat = "true";
				$s_tax = "0";
			}
			$order_list_items .= substr($order_list_item, 0, -1);
			if(!empty($order->get_items( 'shipping' ))){
				foreach( $order->get_items( 'shipping' ) as $item_id => $shipping_item_obj ){
					$shipping_item_data = $shipping_item_obj->get_data();
					$shipping_data_name         = $shipping_item_data['name'];
					$shipping_data_total        = $shipping_item_data['total'] * 100;
					$order_list_items.= ',{"ordertext":"'.$shipping_data_name.'","amount":'.$shipping_data_total.',"vat":0,"quantity":1,"amount_incl_vat":'.$amount_shipping_incl_vat.'}';
				}
			}
			$order_list_items .=']';

			$order_list = json_decode($order_list_items);
			
			if(empty($shipping_country)){
				$shipping_country = '"country" => "'.$shipping_country.'"';
			}else{
				$shipping_country = '"country" => "'.$shipping_country.'"';
			}
			
			$url = 'https://api.reepay.com/v1/customer/'.$customer_id.'/payment_method';
			$arg = array(
				'headers' => array(
					'authorization' => 'Basic '.$key,
					'accept' => 'application/json',
					'content-type' => 'application/json',
					'User-Agent' => $this->reepay_version
				)
			);
			$responses = wp_remote_get( $url, $arg );
			$response_messages = wp_remote_retrieve_body( $responses );
			$data = json_decode( $response_messages );
			foreach( $data->cards as $card_value ) {
					$card = $card_value->masked_card;
					$card_id = $card_value->id;
			}
			
			$virtual_product = $this->virtual_product;
			$simple_product = $this->simple_product;
			$simple_value = false;
			$virtual_value = false;
			$settle = false;
			if($simple_value == true && $virtual_value == true){
				if($virtual_product == 'yes' && $simple_product == 'yes'){
					$settle = true;
				}else{
					if($settle_all_product == 'yes'){
						$settle = true;
					}else{
						$settle = false;
					}
				}
			}else if($simple_value == true && $virtual_value == false){
				if($simple_product == 'yes'){
					$settle = true;
				}else{
					if($settle_all_product == 'yes'){
						$settle = true;
					}else{
						$settle = false;
					}
				}
			}else if($simple_value == false && $virtual_value == true){
				if($virtual_product == 'yes'){
					$settle = true;
				}else{
					if($settle_all_product == 'yes'){
						$settle = true;
					}else{
						$settle = false;
					}
				}
			}else if($simple_value == false && $virtual_value == false){
				if($settle_all_product == 'yes'){
					$settle = true;
				}else{
					$settle = false;
				}
			}
			
			$urls = 'https://api.reepay.com/v1/charge';
			$data = array(
					'handle' => 'order-'.$order_id,
					'currency' => $woocommerce_currency,
					'customer' => array(
						'email' => $billing_email,
						'address' => $billing_address_1,
						'address2' => $billing_address_2,
						'city' => $billing_city,
						'country' => $billing_country,
						'phone' => $billing_phone,
						'company' => $billing_company,
						'postal_code' => $billing_postcode,
						'first_name' => $billing_first_name,
						'last_name' => $billing_last_name,
						'state_or_province' => $billing_state,
						'handle' => $customer_id,
					),
					'source' => $card_id,
					'settle' => true,
					'recurring' => true,
					'order_lines' => $order_list,
					'billing_address' => array(
						'email' => $billing_email,
						'address' => $billing_address_1,
						'address2' => $billing_address_2,
						'city' => $billing_city,
						'country' => $billing_country,
						'phone' => $billing_phone,
						'company' => $billing_company,
						'postal_code' => $billing_postcode,
						'first_name' => $billing_first_name,
						'last_name' => $billing_last_name,
						'state_or_province' => $billing_state,
					),
					'shipping_address' => array(
						'address' => $shipping_address_1,
						'address2' => $shipping_address_2,
						'city' => $shipping_city,
						$shipping_country,
						'company' => $shipping_company,
						'postal_code' => $shipping_postcode,
						'first_name' => $shipping_first_name,
						'last_name' => $shipping_last_name,
						'state_or_province' => $shipping_state,
					),
			);
			
			$data = json_encode($data);
			
			$args = array(
				'headers' => array(
					'authorization' => 'Basic '.$key,
					'accept' => 'application/json',
					'content-type' => 'application/json',
					'User-Agent' => $this->reepay_version
				),
				'body' => $data
			);
			
			$response = wp_remote_post( $urls, $args );
			$array = $response["response"];
			$result = json_decode($response["body"], true); 
			$response_message = wp_remote_retrieve_body( $response );
			if($array['code'] == 200){
				$order->update_status( 'processing' ); 
				update_post_meta($renewal_order->id, 'reepay_charge', '1');
				update_post_meta($renewal_order->id, 'invoice_id_customer_id', '');
			}else{
				reepay_logs($response_message);
			}

		}	
		
		public function add_scripts_checkout() {
			if($this->payment_window_display == "overlay"){
				global $woocommerce;
				$settle_all_product = $this->capture_all_payments;
				$virtual_product = $this->virtual_product;
				$simple_product = $this->simple_product;
				$simple_value = false;
				$virtual_value = false;
				$is_trial = false;
				$items = WC()->cart->get_cart();
				$product_type = array();
				$is_subscription = false;	
				if(get_option( 'woocommerce_prices_include_tax' ) == "yes"){
					$amount_incl_vat = "true";
				}else{
					$amount_incl_vat = "false";
				}
				$order_list_item.='[';
				foreach ( $items as $fetch ) {
					$item = $fetch['data']->post;
					//$product = $item;
					$item_price = get_post_meta($fetch['product_id'], '_price', true) * 100;
					$product = $fetch['data'];
					$tax_rates = WC_Tax::get_rates( $product->get_tax_class() ); 
					
					$freetrial = get_post_meta($fetch['product_id'], '_subscription_trial_length', true);
					if($freetrial == 0){
						$signupfee = get_post_meta($fetch['product_id'], '_subscription_sign_up_fee', true);
						if($signupfee > 0){
							$signupfee = $signupfee * 100;
							$item_price = $item_price + $signupfee;
						}
					}else{
						$signupfee = get_post_meta($fetch['product_id'], '_subscription_sign_up_fee', true);
						if($signupfee > 0){
							$signupfee = $signupfee * 100;
							$item_price = $signupfee;
						}
					}

					if (!empty($tax_rates)) {
						$tax_rate = reset($tax_rates); 
						$taxrate = $tax_rate['rate'] / 100;  
						$order_list_item.= '{\"ordertext\":\"'.$product->get_title().'\",\"amount\":'.floatval($item_price).',\"vat\":'.$taxrate.',\"quantity\":'.$fetch['quantity'].',\"amount_incl_vat\":'.$amount_incl_vat.'},';
						
					}else{
						$order_list_item.= '{\"ordertext\":\"'.$product->get_title().'\",\"amount\":'.floatval($item_price).',\"vat\":0,\"quantity\":'.$fetch['quantity'].',\"amount_incl_vat\":'.$amount_incl_vat.'},';
					
					}
					if(true == $product->is_virtual() || true == $product->is_downloadable()){
						$virtual_value = true;
					}else{
						$simple_value = true;
					}
					if( class_exists( 'WC_Subscriptions_Product' ) && WC_Subscriptions_Product::is_subscription( $product ) ) {
						$is_subscription = true;
					}
					if ( class_exists( 'WC_Subscriptions_Product' ) && WC_Subscriptions_Product::get_trial_length( $product->id ) > 0 ) {
						$signupfee = get_post_meta($fetch['product_id'], '_subscription_sign_up_fee', true);
						if($signupfee > 0){
							$is_trial = false;		
						}else{
							$is_trial = true;		
						}
						
					}
				}
				$tax_rate = reset($tax_rates); 
				$taxrate = $tax_rate['rate'] / 100;  
	
				if($tax_rate['shipping'] == "yes"){
					$amount_shipping_incl_vat = "false";
					$s_tax = $taxrate;
				}else{
					$amount_shipping_incl_vat = "true";
					$s_tax = "0";
				}
				

				$order_list_items .= substr($order_list_item, 0, -1);
			
				$packages = WC()->shipping->get_packages();
				foreach ($packages as $i => $package['rates']) {
					foreach($package["rates"]["rates"] as $ship){
						$ship_id = $ship->id;
						$ship_label = $ship->label;
						$ship_cost = $ship->cost * 100; 
						$ship_array[$ship_id] = ',{"ordertext":"'.$ship_label.'","amount":'.$ship_cost.',"vat":'.$s_tax.',"quantity":1,"amount_incl_vat":'.$amount_shipping_incl_vat.'}';
					}
				}

				$order_list = $order_list_items; 
				$settle = false;
				if($simple_value == true && $virtual_value == true){
					if($virtual_product == 'yes' && $simple_product == 'yes'){
						$settle = true;
					}else{
						if($settle_all_product == 'yes'){
							$settle = true;
						}else{
							$settle = false;
						}
					}
				}else if($simple_value == true && $virtual_value == false){
					if($simple_product == 'yes'){
						$settle = true;
					}else{
						if($settle_all_product == 'yes'){
							$settle = true;
						}else{
							$settle = false;
						}
					}
				}else if($simple_value == false && $virtual_value == true){
					if($virtual_product == 'yes'){
						$settle = true;
					}else{
						if($settle_all_product == 'yes'){
							$settle = true;
						}else{
							$settle = false;
						}
					}
				}else if($simple_value == false && $virtual_value == false){
					if($settle_all_product == 'yes'){
						$settle = true;
					}else{
						$settle = false;
					}
				}
				if($settle){
					$settle = 'true';
				}else{
					$settle = 'false';
				}
				
				if($this->test_enabled == 'yes'){
					$key = base64_encode($this->test_reepay_private_key);
				}else{
					$key = base64_encode($this->reepay_private_key);
				}
				$login_user_id = get_current_user_id();
				$reepay_user_id = get_user_meta($login_user_id, 'reepay_customer_id', true);
				if(!empty($reepay_user_id)){
					$handle = '\"handle\":\"'.$reepay_user_id.'\"';
				}else{
					$handle = '\"generate_handle\":true';
				}
				if($is_subscription){
					$is_recurring = ',\"recurring\":true';
				}else{
					$is_recurring = ',\"recurring\":false';
				}
			
				$woocommerce_currency = get_option('woocommerce_currency');
 				if($is_trial == 1){
					if ( is_user_logged_in() && !empty($reepay_user_id) ) {
						$datatrial = '{\"customer\":\"'.$reepay_user_id.'\"}';
					}else{
						$datatrial = '{\"create_customer\":{\"generate_handle\":true}}';
					}
					echo '
					<script>
					jQuery(document).ready(function(){
						jQuery("form.checkout").on("checkout_place_order", function(event) {
							event.preventDefault();
							if(jQuery("#token_id").val().length == 0 && jQuery(".payment_box.payment_method_reepay").css("display") == "block"){
								jQuery("form.checkout").append("<input type=\"hidden\" name=\"m_prevent_submit\" value=\"1\">");
							}
							return true;
						});
					});
					
					jQuery( document.body ).on( "checkout_error", function() {
						var counterror = 0;
						jQuery(".woocommerce-error li").each(function(){
							counterror++;
							var error_message = jQuery(this).text();
							if(error_message == "start_popup"){
								jQuery(this).hide();
							}
						});
						var error_text = jQuery(".woocommerce-error").find("li").first().text();
						if ( error_text=="start_popup" && counterror == 1) {
							jQuery(".woocommerce-error").hide();
						
							var ship_order = "";
							var arrayFromPHP = '.json_encode($ship_array).';
							var shipping = jQuery("input[name^=\"shipping_method\"]");
							var shipping_check = shipping+":checked";
							jQuery(shipping).each(function(){ 
								if(jQuery(this).attr("checked") == "checked"){
									var ship_val = jQuery(this).val();
									jQuery.each(arrayFromPHP, function (i, elem) {
										if(ship_val == i){
											ship_order = elem;	
										}
									});
								}
							});
							var random = Math.floor((Math.random() * 999) + 100);
							var new_data = '.date("mdGis").';
							var id_order = "order-"+new_data+""+random; 
							var data = "'.$datatrial.'";
							var xhr = new XMLHttpRequest();
							xhr.addEventListener("readystatechange", function () {
							  if (this.readyState === this.DONE) {
								  
								var obj = JSON.parse(this.responseText);
								var rp = new Reepay.ModalCheckout(obj.id);
								rp.addEventHandler(Reepay.Event.Accept, data => { 
									var datainfo = data.invoice +":"+data.customer;
									jQuery("#token_id").val(datainfo);
									jQuery("input[name=\"m_prevent_submit\"]").val("0");
									jQuery("#place_order").click();
								});
								
							  }
							});
							xhr.open("POST", "https://checkout-api.reepay.com/v1/session/recurring");
							xhr.setRequestHeader("accept", "application/json");
							xhr.setRequestHeader("content-type", "application/json");
							xhr.setRequestHeader("authorization", "Basic '.$key.'");
							xhr.send(data);
					
						}
					});


					</script>
					';
				}else{
					echo '
					<script>
					jQuery(document).ready(function(){
						jQuery("form.checkout").on("checkout_place_order", function(event) {
							event.preventDefault();
							if(jQuery("#token_id").val().length == 0 && jQuery(".payment_box.payment_method_reepay").css("display") == "block"){
								jQuery("form.checkout").append("<input type=\"hidden\" name=\"m_prevent_submit\" value=\"1\">");
							}
							return true;
						});
					});
					
					jQuery( document.body ).on( "checkout_error", function() {
						var counterror = 0;
						jQuery(".woocommerce-error li").each(function(){
							counterror++;
							var error_message = jQuery(this).text();
							if(error_message == "start_popup"){
								jQuery(this).hide();
							}
						});
						var error_text = jQuery(".woocommerce-error").find("li").first().text();
						if ( error_text=="start_popup" && counterror == 1) {
							jQuery(".woocommerce-error").hide();
						
							var ship_order = "";
							var arrayFromPHP = '.json_encode($ship_array).';
							var shipping = jQuery("input[name^=\"shipping_method\"]");
							var shipping_check = shipping+":checked";
							jQuery(shipping).each(function(){ 
								if(jQuery(this).attr("checked") == "checked"){
									var ship_val = jQuery(this).val();
									jQuery.each(arrayFromPHP, function (i, elem) {
										if(ship_val == i){
											ship_order = elem;	
										}
									});
								}
							});
							var random = Math.floor((Math.random() * 999) + 100);
							var new_data = '.date("mdGis").';
							var id_order = "order-"+new_data+""+random; 
							var data = "{\"settle\":'.$settle.',\"order\":{\"handle\":\""+id_order+"\",\"currency\":\"'.$woocommerce_currency.'\",\"customer\":{'.$handle.'},\"order_lines\":'.$order_list.'"+ship_order+"]}'.$is_recurring.'}";
							var xhr = new XMLHttpRequest();
							xhr.addEventListener("readystatechange", function () {
							  if (this.readyState === this.DONE) {
								  
								var obj = JSON.parse(this.responseText);
								var rp = new Reepay.ModalCheckout(obj.id);
								rp.addEventHandler(Reepay.Event.Accept, data => { 
									var datainfo = data.invoice +":"+data.customer;
									jQuery("#token_id").val(datainfo);
									jQuery("input[name=\"m_prevent_submit\"]").val("0");
									jQuery("#place_order").click();
								});
								
							  }
							});
							xhr.open("POST", "https://checkout-api.reepay.com/v1/session/charge");
							xhr.setRequestHeader("accept", "application/json");
							xhr.setRequestHeader("content-type", "application/json");
							xhr.setRequestHeader("authorization", "Basic '.$key.'");
							xhr.send(data);
					
						}
					});


					</script>
					';
				}
			}
		}

		public function add_payment_icon( $icons, $this_id ) {
			if($this_id == 'reepay'){
				$count_icon = count($this->icon_array);
				$show_icon = $count_icon - 3;
				foreach(array_reverse($this->icon_array) as $key => $image){
					if($key >= $show_icon){
						$icons .= '<img src="'.$image.'" style="padding: 3px" />';
					}
				}
			}
			return $icons;
		}
	
		public function sample_admin_notice_warning() {
			if($this->enabled == 'yes' && !is_ssl()){
				$message = __( 'Reepay is enabled, but a SSL certificate is not detected. Your checkout may not be secure! Please ensure your server has a valid', 'reepay' );
				$message_href = __( 'SSL certificate', 'reepay' );
				$url = 'https://en.wikipedia.org/wiki/Transport_Layer_Security';
				printf( '<div class="notice notice-warning is-dismissible"><p>%1$s <a href="%2$s" target="_blank">%3$s</a></p></div>', esc_html( $message ), esc_html( $url ), esc_html( $message_href ) ); 
			}
		}


		public function filter_valid_order_statuses_for_cancel( $statuses, $order = '' ){
			$order_id = $order->get_id();
			$key = base64_encode($this->reepay_private_key);
			$url = 'https://api.reepay.com//v1/charge/order-'.$order_id;
			$args = array(
				'headers' => array(
					'authorization' => 'Basic '.$key,
					'accept' => 'application/json',
					'content-type' => 'application/json',
					'User-Agent' => $this->reepay_version
				)
			);
			$response = wp_remote_get( $url, $args );
			$array = json_decode($response["body"], true);
			if($array['state'] == 'authorized'){
				$statuses  = array('processing', 'on-hold', 'failed' );
				return $statuses;
			}else{
				return $statuses;
			}
		}

		public function add_reepay_charge_button( $order ){
			$order_id = $order->get_order_number();
			$invoice_and_customer_ID = get_post_meta($order_id, 'invoice_id_customer_id', true);
			if(empty($invoice_and_customer_ID)){
				$order_full = 'order-'.$order_id;
			}else{
				$invoice_and_customer = explode(":", $invoice_and_customer_ID);
				$invoice = $invoice_and_customer[0];
				$order_full = $invoice;
			}
			$order_status = $order->get_status();  
			if($this->test_enabled == 'yes'){
				$key = base64_encode($this->test_reepay_private_key);
			}else{
				$key = base64_encode($this->reepay_private_key);
			}
			
			$url = 'https://api.reepay.com//v1/charge/'.$order_full;
			$args = array(
				'headers' => array(
					'authorization' => 'Basic '.$key,
					'accept' => 'application/json',
					'content-type' => 'application/json',
					'User-Agent' => $this->reepay_version
				)
			);
			$response = wp_remote_get( $url, $args );
			$array = json_decode($response["body"], true);
			$if_charge = get_post_meta($order_id, 'reepay_charge', true);
			if($order_status == 'processing'){
				if($order->is_editable() || $if_charge == "0"){
					echo '<button type="button" class="button button-primary charge-action">Capture Charge</button>';
				}
				
				if($order->is_editable() || $array["state"] == 'authorized' && $if_charge == "0"){
					echo '<button type="button" class="button button-primary cancel-invoice" style="margin-left: 5px;">Cancel invoice</button>';
				}
				echo '
				<script>
					jQuery(".charge-action").click(function(e){
						e.preventDefault();
						jQuery.ajax({
							type: "POST",
							url: "'.admin_url( "admin-ajax.php" ).'",
							data: {
								action: "ajax_capture_charge",
								order_id: "'.$order_id.'",
								order_full: "'.$order_full.'",
								key: "'.$key.'",
							},
							success: function(data, textStatus, XMLHttpRequest) {
								if(data.errorid != "200"){
									alert(data.errormessage);
								}
								location.reload();
							},
							error: function(MLHttpRequest, textStatus, errorThrown) {
								console.log(MLHttpRequest);
							}
						});
					});
					jQuery(".cancel-invoice").click(function(e){
						e.preventDefault();
						jQuery.ajax({
							type: "POST",
							url: "'.admin_url( "admin-ajax.php" ).'",
							data: {
								action: "ajax_cancel_invoice",
								order_id: "'.$order_id.'",
								order_full: "'.$order_full.'",
								key: "'.$key.'",
							},
							success: function(data, textStatus, XMLHttpRequest) {
								if(data.errorid != "200"){
									console.log(data.errormessage);
								}
								location.reload();
							},
							error: function(MLHttpRequest, textStatus, errorThrown) {
								console.log(MLHttpRequest);
							}
						});
					});
				</script>';
			}
		}
		 
		public function reepay_thankyou($order_id) {
			$paymentId = get_query_var('id');
			$key = get_query_var('key');
			$customer = get_query_var('customer');
			global $woocommerce;
			$order = new WC_Order( $order_id );
			$user_id = (int)$order->user_id;
			if(!empty($paymentId)){
				$order->add_order_note( sprintf( __( 'Reepay payment approved! Transaction ID: %s', 'reepay' ), $paymentId ) );
			}
			if(!empty($key)){
				update_post_meta($order_id, 'key', $key);
			}
			if(!empty($paymentId)){
				update_post_meta($order_id, 'id', $paymentId);
			}
			if(!empty($customer)){
				update_post_meta($order_id, 'customer', $customer);
			}
			if(empty($customer)){
				$invoice_and_customer_ID = get_post_meta($order_id, 'invoice_id_customer_id', true);
				$invoice_and_customer = explode(":", $invoice_and_customer_ID);
				$invoice_order = $invoice_and_customer[0];
				$customer = $invoice_and_customer[1];
			}
			update_user_meta($user_id, 'reepay_customer_id', $customer );
			if(!empty($invoice_order)){
				$order_full = $invoice_order;
			}else{
				$order_full = 'order-'.$order_id;	
			}
			if($this->test_enabled == 'yes'){
				$key = base64_encode($this->test_reepay_private_key);
			}else{
				$key = base64_encode($this->reepay_private_key);
			}
			$url = 'https://api.reepay.com//v1/charge/'.$order_full;
			$args = array(
				'headers' => array(
					'authorization' => 'Basic '.$key,
					'accept' => 'application/json',
					'content-type' => 'application/json',
					'User-Agent' => $this->reepay_version
				)
			);
			$response = wp_remote_get( $url, $args );
			$array = json_decode($response["body"], true);
			if($array['state'] == 'authorized'){
				$order->update_status( 'processing' ); 
				update_post_meta($order_id, 'reepay_charge', '0');
				$subscriptions = wcs_get_subscriptions_for_order( $order_id );
				foreach( $subscriptions as $subscription_id => $subscription ){
					$subscription->update_status( 'on-hold' );
				}
			}else{
				$order->update_status( 'processing' ); 
				update_post_meta($order_id, 'reepay_charge', '1');
				$subscriptions = wcs_get_subscriptions_for_order( $order_id );
				foreach( $subscriptions as $subscription_id => $subscription ){
					$subscription->update_status( 'active' );
				}
			}
		}

		public function admin_options()
        {
            echo '<h2>'. $this->title . '</h2>';
			echo '<p>'. $this->description .'</p>';
			$key = base64_encode($this->reepay_private_key);
			$url = "https://api.reepay.com/v1/account/pubkey";
			$args = array(
				'headers' => array(
					'authorization' => 'Basic '.$key,
					'accept' => 'application/json',
					'content-type' => 'application/json',
					'User-Agent' => $this->reepay_version
				)
			);
			$response = wp_remote_post( $url, $args );
			$array = $response["response"];

            if ($array["code"] == '200') {
                echo '<table class="form-table">';
				$this->generate_settings_html();
				echo '<script>
					jQuery(document).ready(function(){
						jQuery("#woocommerce_reepay_reepay_private_key").parent().prepend("<div style=\"position:absolute;margin-top:35px;color: green;\">Active</div>");
					});
				</script>';
                echo '</table>';
            }
            else {
                echo '<table class="form-table">';
				$this->generate_settings_html();
				echo '<script>
					jQuery(document).ready(function(){
						jQuery("#woocommerce_reepay_reepay_private_key").parent().prepend("<div style=\"position:absolute;margin-top:35px;color: red;\">Active</div>");
					});
				</script>';
                echo '</table>';
            }
			echo '<script>
				jQuery(document).ready(function(){
					jQuery(".payments_icon").each(function(){
						jQuery(this).closest(".forminp").css("padding", "0 10px");
					});
					jQuery(".payments_icon_first").closest(".forminp").css("padding", "15px 10px 5px 10px");
				});
			</script>';
        }
		
		
		public function process_refund($order_id, $amount = null, $reason = '')
		{ 
				if($this->test_enabled == 'yes'){
					$key = base64_encode($this->test_reepay_private_key);
				}else{
					$key = base64_encode($this->reepay_private_key);
				}
				$url = 'https://api.reepay.com//v1/refund';
			
				$data = array(
					'invoice' => 'order-'.$order_id,
					'amount' => $amount * 100,
				);
				$payload = json_encode($data);
				
				$args = array(
					'headers' => array(
						'authorization' => 'Basic '.$key,
						'accept' => 'application/json',
						'content-type' => 'application/json',
						'User-Agent' => $this->reepay_version
					),
					'body' => $payload
				);
				$response = wp_remote_post( $url, $args );
				$array = $response["response"];
				$response_message = wp_remote_retrieve_body( $response );
				if ($array["code"] == '200') {
					$order = wc_get_order($order_id);
					$order->add_order_note( __('The refund has been successful.', 'reepay') );
				} else {
					reepay_logs($response_message);
				}

				return true;
		}
		 
		 public function init_form_fields(){
 
			$this->form_fields = array(
				'enabled' => array(
					'title'       => __( 'Enable/Disable', 'reepay' ),
					'label'       => __( 'Enable Reepay Gateway', 'reepay' ),
					'type'        => 'checkbox',
					'default'     => 'no',
				),
				'api_integration' => array(
					'title'       => __( 'API – Integration', 'reepay' ),
					'type'        => 'title'
				),
				'reepay_private_key' => array(
					'title'       => __( 'Live private key', 'reepay' ),
					'type'        => 'text'
				),
				'reepay_public_key' => array(
					'title'       => __( 'Public key', 'reepay' ),
					'type'        => 'text'
				),
				'test_integration' => array(
					'title'       => __( 'Test mode', 'reepay' ),
					'type'        => 'title'
				),
				'test_enabled' => array(
					'title'       => __( 'Enable/Disable', 'reepay' ),
					'label'       => __( 'Enable sandbox (test) mode', 'reepay' ),
					'type'        => 'checkbox',
					'default'     => 'no',
				),
				'test_reepay_private_key' => array(
					'title'       => __( 'Test private key', 'reepay' ),
					'type'        => 'text'
				),
				'order_page_settings' => array(
					'title'       => __( 'Order page settings', 'reepay' ),
					'type'        => 'title'
				),
				'title' => array(
					'title'       => __( 'Title', 'reepay' ),
					'type'        => 'text'
				),
				'description' => array(
					'title'       => __( 'Description', 'reepay' ),
					'type'        => 'textarea'
				),
				'payments_cards_icons_dankort' => array(
					'title'       => __( 'Payments cards icons', 'reepay' ),
					'type'        => 'checkbox',
					'label' => __('Dankort', 'reepay'),
					'class' => 'payments_icon_first'
				),
				'payments_cards_icons_visa' => array(
					'title'       => '',
					'type'        => 'checkbox',
					'label' => __('Visa', 'reepay'),
					'class' => 'payments_icon'
				),
				'payments_cards_icons_mastercard' => array(
					'title'       => '',
					'type'        => 'checkbox',
					'label' => __('Mastercard', 'reepay'),
					'class' => 'payments_icon'
				),
				'payments_cards_icons_visa_electron' => array(
					'title'       => '',
					'type'        => 'checkbox',
					'label' => __('Visa Electron', 'reepay'),
					'class' => 'payments_icon'
				),
				'payments_cards_icons_maestro' => array(
					'title'       => '',
					'type'        => 'checkbox',
					'label' => __('Maestro', 'reepay'),
					'class' => 'payments_icon'
				),
				'payments_cards_icons_mobilepay_online' => array(
					'title'       => '',
					'type'        => 'checkbox',
					'label' => __('MobilePay Online', 'reepay'),
					'class' => 'payments_icon'
				),
				'payments_cards_icons_viabill' => array(
					'title'       => '',
					'type'        => 'checkbox',
					'label' => __('Viabill', 'reepay'),
					'class' => 'payments_icon'
				),
				'payments_cards_icons_unionpay' => array(
					'title'       => '',
					'type'        => 'checkbox',
					'label' => __('Unionpay', 'reepay'),
					'class' => 'payments_icon'
				),
				'payments_cards_icons_forbrugsforeningen' => array(
					'title'       => '',
					'type'        => 'checkbox',
					'label' => __('Forbrugsforeningen', 'reepay'),
					'class' => 'payments_icon'
				),
				'payments_cards_icons_discover' => array(
					'title'       => '',
					'type'        => 'checkbox',
					'label' => __('Discover', 'reepay'),
					'class' => 'payments_icon'
				),
				'payments_cards_icons_jcb' => array(
					'title'       => '',
					'type'        => 'checkbox',
					'label' => __('Jcb', 'reepay'),
					'class' => 'payments_icon'
				),
				'payments_cards_icons_diners_club_international' => array(
					'title'       => '',
					'type'        => 'checkbox',
					'label' => __('Diners club international', 'reepay'),
					'class' => 'payments_icon'
				),
				'payments_cards_icons_american_express' => array(
					'title'       => '',
					'type'        => 'checkbox',
					'label' => __('American express', 'reepay'),
					'class' => 'payments_icon'
				),
				'max_icon_height' => array(
					'title'       => __( 'Maximum icon height (in px)', 'reepay' ),
					'type'        => 'number'
				),
				'payment_window_settings' => array(
					'title'       => __( 'Payment window settings', 'reepay' ),
					'type'        => 'title'
				),
				'payment_window_display' => array(
					'title'       => __( 'Payment window display', 'reepay' ),
					'type'        => 'select',
					'options' => array(
						  'window' => __( 'Window', 'reepay' ),
						  'overlay'  => __( 'Overlay', 'reepay' )
					),
				),
				'autocapture_settings' => array(
					'title'       => __( 'Autocapture settings', 'reepay' ),
					'type'        => 'title'
				),
				'capture_all_payments' => array(
					'title'       => __( 'Capture all payments automatically (standard)', 'reepay' ),
					'type'        => 'checkbox',
					'label'       => __( 'Enable', 'reepay' ),
					'default'     => 'no'
				),
				'simple_product' => array(
					'title'       => __( 'Simple / Grouped / External / Variable products', 'reepay' ),
					'type'        => 'checkbox',
					'label'       => __( 'Enable', 'reepay' ),
					'description' => __( 'Capture payments automatically for these products only', 'reepay' ),
					'default'     => 'no'
				),
				'virtual_product' => array(
					'title'       => __( 'Virtual / Downloadable products', 'reepay' ),
					'type'        => 'checkbox',
					'label'       => __( 'Enable', 'reepay' ),
					'description' => __( 'Capture payments automatically for these products only', 'reepay' ),
					'default'     => 'no'
				)
			);
			
		}
		
		public function payment_fields() {
			if ( $this->description ) {
				echo wpautop( wp_kses_post( $this->description ) );
			}
			if($this->max_icon_height){
				$height = 'max-height:'.$this->max_icon_height.'px;';
			}
			foreach($this->icon_array as $key => $image){
				if($key > 2){
					echo '<img src="' . $image . '" style="'.$height.' float:none; display:inline-block; padding-right: 5px; padding-bottom: 5px;">';
				}
			}
		}
				
		public function payment_scripts() {
			wp_enqueue_script( 'reepay_js_token', 'https://token.reepay.com/token.js' );
			wp_enqueue_script( 'reepay_js_checkout', 'https://checkout.reepay.com/checkout.js' );
			//wp_enqueue_script( 'reepay_js_checkout', 'https://staging-checkout.reepay.com/checkout.js' );
			wp_enqueue_script( 'reepay_js_custom', '/wp-content/plugins/woo-reepay-gateway/script.js');
			wp_enqueue_script( 'reepay_js_reepay', 'https://js.reepay.com/v1/reepay.js');
		}
		
		public function validate_fields() {
			if ( $_POST['m_prevent_submit'] == 1 && wc_notice_count( 'error' ) == 0 ) {
				wc_add_notice( __( 'start_popup', 'reepay' ), 'error');
			} 
		}
		
		public function process_payment( $order_id ) {
		
			global $woocommerce;
			$woocommerce_currency = get_option('woocommerce_currency');
			$order = wc_get_order( $order_id );
			$settle_all_product = $this->capture_all_payments;
			$virtual_product = $this->virtual_product;
			$simple_product = $this->simple_product;
			$simple_value = false;
			$virtual_value = false;
			$items = $order->get_items();
			$product_type = array();
			$is_subscription = false;
			if(get_option( 'woocommerce_prices_include_tax' ) == "yes"){
				$amount_incl_vat = "true";
			}else{
				$amount_incl_vat = "false";
			}
			$is_trial = false;
			$order_list_item.='[';
			foreach ( $items as $item ) {
				$product = get_product($item->get_product_id());
				$item_price = $product->get_price() * 100;
				$freetrial = get_post_meta($item->get_product_id(), '_subscription_trial_length', true);
				if($freetrial == 0){
					$signupfee = get_post_meta($item->get_product_id(), '_subscription_sign_up_fee', true);
					if($signupfee > 0){
						$signupfee = $signupfee * 100;
						$item_price = $item_price + $signupfee;
					}
				}else{
					$signupfee = get_post_meta($item->get_product_id(), '_subscription_sign_up_fee', true);
					if($signupfee > 0){
						$signupfee = $signupfee * 100;
						$item_price = $signupfee;
					}
				}	
				$tax_rates = WC_Tax::get_rates( $product->get_tax_class() );
				if (!empty($tax_rates)) {
					$tax_rate = reset($tax_rates);
					$taxrate = $tax_rate['rate'] / 100;
					$order_list_item.= '{"ordertext":"'.$product->get_title().'","amount":'.floatval($item_price).',"vat":'.$taxrate.',"quantity":'.$item['quantity'].',"amount_incl_vat":'.$amount_incl_vat.'},';
					$stax = $taxrate;
				}else{
					$order_list_item.= '{"ordertext":"'.$product->get_title().'","amount":'.floatval($item_price).',"quantity":'.$item['quantity'].',"amount_incl_vat":'.$amount_incl_vat.'},';
					$stax = '0';
				}
				if(true == $product->is_virtual() || true == $product->is_downloadable()){
					$virtual_value = true;
				}else{
					$simple_value = true;
				}
				if( class_exists( 'WC_Subscriptions_Product' ) && WC_Subscriptions_Product::is_subscription( $product ) ) {
					$is_subscription = true;
				}
				if ( class_exists( 'WC_Subscriptions_Product' ) && WC_Subscriptions_Product::get_trial_length( $item->get_product_id() ) > 0 ) {
						$signupfee = get_post_meta($item->get_product_id(), '_subscription_sign_up_fee', true);
						if($signupfee > 0){
							$is_trial = false;		
						}else{
							$is_trial = true;		
						}	
				}
			}
			
			$tax_rate = reset($tax_rates);
			$taxrate = $tax_rate['rate'] / 100; 
			if($tax_rate['shipping'] == "yes"){
				$amount_shipping_incl_vat = "false";
				$s_tax = $taxrate;
			}else{
				$amount_shipping_incl_vat = "true";
				$s_tax = "0";
			}
			
			$order_list_items .= substr($order_list_item, 0, -1);
			if(!empty($order->get_items( 'shipping' ))){
				foreach( $order->get_items( 'shipping' ) as $item_id => $shipping_item_obj ){
					$shipping_item_data = $shipping_item_obj->get_data();
					$shipping_data_name         = $shipping_item_data['name'];
					$shipping_data_total        = $shipping_item_data['total'] * 100;
					$order_list_items.= ',{"ordertext":"'.$shipping_data_name.'","amount":'.$shipping_data_total.',"vat":'.$stax.',"quantity":1,"amount_incl_vat":'.$amount_shipping_incl_vat.'}';
				}
			}
			$order_list_items .=']';

			$order_list = json_decode($order_list_items);
		
			$settle = false;
			if($simple_value == true && $virtual_value == true){
				if($virtual_product == 'yes' && $simple_product == 'yes'){
					$settle = true;
				}else{
					if($settle_all_product == 'yes'){
						$settle = true;
					}else{
						$settle = false;
					}
				}
			}else if($simple_value == true && $virtual_value == false){
				if($simple_product == 'yes'){
					$settle = true;
				}else{
					if($settle_all_product == 'yes'){
						$settle = true;
					}else{
						$settle = false;
					}
				}
			}else if($simple_value == false && $virtual_value == true){
				if($virtual_product == 'yes'){
					$settle = true;
				}else{
					if($settle_all_product == 'yes'){
						$settle = true;
					}else{
						$settle = false;
					}
				}
			}else if($simple_value == false && $virtual_value == false){
				if($settle_all_product == 'yes'){
					$settle = true;
				}else{
					$settle = false;
				}
			}
			
			if($this->test_enabled == 'yes'){
				$key = base64_encode($this->test_reepay_private_key);
			}else{
				$key = base64_encode($this->reepay_private_key);
			}
			$price = $order->get_total() * 100;
			$login_user_id = get_current_user_id();
			$user_reepay_id = get_user_meta($login_user_id, 'reepay_customer_id', true );

			if($is_subscription){
				$is_recurring = "'recurring' => true,";
			}else{
				$is_recurring = "'recurring' => false,";
			}
				
			if(empty($shipping_country)){
				$shipping_country = '"country" => "'.$shipping_country.'"';
			}else{
				$shipping_country = '"country" => "'.$shipping_country.'"';
			}
			
			if($this->payment_window_display == "overlay"){
				$invoice_and_customer_ID = get_post_meta($order_id, 'invoice_id_customer_id', true);
				$invoice_and_customer = explode(":", $invoice_and_customer_ID);
				$url_customer = 'https://api.reepay.com/v1/customer/'.$invoice_and_customer[1];
				$data_customer = array(
					'email' => $order->billing_email,
					'address' => $order->billing_address_1,
					'city' => $order->billing_city,
					'country' => $order->billing_country,
					'phone' => $order->billing_phone,
					'company' => $order->billing_company,
					'postal_code' => $order->billing_postcode,
					'first_name' => $order->billing_first_name,
					'last_name' => $order->billing_last_name,
				);
				$data_customer = json_encode($data_customer);
				$args_customer = array(
					'method' => 'PUT',
					'headers' => array(
						'authorization' => 'Basic '.$key,
						'accept' => 'application/json',
						'content-type' => 'application/json',
						'User-Agent' => $this->reepay_version
					),
					'body' => $data_customer
				);
				$response_customer = wp_remote_post( $url_customer, $args_customer );
				
				$url_billing = 'https://api.reepay.com/v1/invoice/'.$invoice_and_customer[0].'/billing_address';
				$data_billing = array(
					'email' => $order->billing_email,
					'address' => $order->billing_address_1,
					'city' => $order->billing_city,
					'country' => $order->billing_country,
					'phone' => $order->billing_phone,
					'company' => $order->billing_company,
					'postal_code' => $order->billing_postcode,
					'first_name' => $order->billing_first_name,
					'last_name' => $order->billing_last_name,
				);
				$data_billing = json_encode($data_billing);
				$args_billing = array(
					'method' => 'PUT',
					'headers' => array(
						'authorization' => 'Basic '.$key,
						'accept' => 'application/json',
						'content-type' => 'application/json',
						'User-Agent' => $this->reepay_version
					),
					'body' => $data_billing
				);
				$response_billing = wp_remote_post( $url_billing, $args_billing );
				
				$url_shipping = 'https://api.reepay.com/v1/invoice/'.$invoice_and_customer[0].'/shipping_address';
				$data_shipping = array(
					'address' => $order->shipping_address_1,
					'city' => $order->shipping_city,
					$shipping_country,
					'company' => $order->shipping_company,
					'postal_code' => $order->shipping_postcode,
					'first_name' => $order->shipping_first_name,
					'last_name' => $order->shipping_last_name,
				);
				$data_shipping = json_encode($data_shipping);
				$args_shipping = array(
					'method' => 'PUT',
					'headers' => array(
						'authorization' => 'Basic '.$key,
						'accept' => 'application/json',
						'content-type' => 'application/json',
						'User-Agent' => $this->reepay_version
					),
					'body' => $data_shipping
				);
				$response_shipping = wp_remote_post( $url_shipping, $args_shipping );
	
				return array(
					'result' => 'success',
					'redirect' => $this->get_return_url( $order )
				);
			}else{
				if ( is_user_logged_in() && !empty($user_reepay_id) ) {
					if($is_trial == 1){
						$url = 'https://checkout-api.reepay.com/v1/session/recurring';
						if(empty($user_reepay_id)){
							$data = array(
								'create_customer' => array(
									'email' => $order->billing_email,
									'address' => $order->billing_address_1,
									'city' => $order->billing_city,
									'country' => $order->billing_country,
									'phone' => $order->billing_phone,
									'company' => $order->billing_company,
									'postal_code' => $order->billing_postcode,
									'first_name' => $order->billing_first_name,
									'last_name' => $order->billing_last_name,
									'generate_handle' => true,
								),
								'accept_url' => $this->get_return_url( $order ),
								'cancel_url'=> get_home_url().'/checkout/',
							);
						}else{
							$data = array(
								'customer' => $user_reepay_id,
								'accept_url' => $this->get_return_url( $order ),
								'cancel_url'=> get_home_url().'/checkout/',
							);
						}
					}else{
						$url = 'https://checkout-api.reepay.com/v1/session/charge';
						$data = array(
							'order' => array(
								'handle' => 'order-'.$order_id,
								'currency' => $woocommerce_currency,
								'customer' => array(
									'email' => $order->billing_email,
									'address' => $order->billing_address_1,
									'city' => $order->billing_city,
									'country' => $order->billing_country,
									'phone' => $order->billing_phone,
									'company' => $order->billing_company,
									'postal_code' => $order->billing_postcode,
									'first_name' => $order->billing_first_name,
									'last_name' => $order->billing_last_name,
									'handle' => $user_reepay_id,
								),
								'billing_address' => array(
									'email' => $order->billing_email,
									'address' => $order->billing_address_1,
									'city' => $order->billing_city,
									'country' => $order->billing_country,
									'phone' => $order->billing_phone,
									'company' => $order->billing_company,
									'postal_code' => $order->billing_postcode,
									'first_name' => $order->billing_first_name,
									'last_name' => $order->billing_last_name,
								),
								'shipping_address' => array(
									'address' => $order->shipping_address_1,
									'city' => $order->shipping_city,
									$shipping_country,
									'company' => $order->shipping_company,
									'postal_code' => $order->shipping_postcode,
									'first_name' => $order->shipping_first_name,
									'last_name' => $order->shipping_last_name,
								),
								'order_lines' => $order_list,
							),
							'settle' => $settle,
							$is_recurring,
							'accept_url' => $this->get_return_url( $order ),
							'cancel_url'=> get_home_url().'/checkout/',
						);
					}
					
					$data = json_encode($data);
					
					$args = array(
						'headers' => array(
							'authorization' => 'Basic '.$key,
							'accept' => 'application/json',
							'content-type' => 'application/json',
							'User-Agent' => $this->reepay_version
						),
						'body' => $data
					);
				}else{
					if($is_trial == 1){
						$url = 'https://checkout-api.reepay.com/v1/session/recurring';
						$data = array(
							'create_customer' => array(
								'email' => $order->billing_email,
								'address' => $order->billing_address_1,
								'city' => $order->billing_city,
								'country' => $order->billing_country,
								'phone' => $order->billing_phone,
								'company' => $order->billing_company,
								'postal_code' => $order->billing_postcode,
								'first_name' => $order->billing_first_name,
								'last_name' => $order->billing_last_name,
								'generate_handle' => true,
							),
							'accept_url' => $this->get_return_url( $order ),
							'cancel_url'=> get_home_url().'/checkout/',
						);
					}else{
						$url = 'https://checkout-api.reepay.com/v1/session/charge';
						$data = array(
							'order' => array(
								'handle' => 'order-'.$order_id,
								'currency' => $woocommerce_currency,
								'customer' => array(
									'email' => $order->billing_email,
									'address' => $order->billing_address_1,
									'city' => $order->billing_city,
									'country' => $order->billing_country,
									'phone' => $order->billing_phone,
									'company' => $order->billing_company,
									'postal_code' => $order->billing_postcode,
									'first_name' => $order->billing_first_name,
									'last_name' => $order->billing_last_name,
								),
								'billing_address' => array(
									'email' => $order->billing_email,
									'address' => $order->billing_address_1,
									'city' => $order->billing_city,
									'country' => $order->billing_country,
									'phone' => $order->billing_phone,
									'company' => $order->billing_company,
									'postal_code' => $order->billing_postcode,
									'first_name' => $order->billing_first_name,
									'last_name' => $order->billing_last_name,
								),
								'shipping_address' => array(
									'address' => $order->shipping_address_1,
									'city' => $order->shipping_city,
									$shipping_country,
									'company' => $order->shipping_company,
									'postal_code' => $order->shipping_postcode,
									'first_name' => $order->shipping_first_name,
									'last_name' => $order->shipping_last_name,
								),
								'order_lines' => $order_list,
							),
							'settle' => $settle,
							'recurring' => true,
							'accept_url' => $this->get_return_url( $order ),
							'cancel_url'=> get_home_url().'/checkout/',
						);
					}
					$data = json_encode($data);
					
					$args = array(
						'headers' => array(
							'authorization' => 'Basic '.$key,
							'accept' => 'application/json',
							'content-type' => 'application/json',
							'User-Agent' => $this->reepay_version
						),
						'body' => $data
					);
				}
				
				$response = wp_remote_post( $url, $args );
				$array = $response["response"];
				$result = json_decode($response["body"], true); 
				$response_message = wp_remote_retrieve_body( $response );
	
				if ($array["code"] == '200') {
					return array(
						'result' => 'success',
						'redirect' => $result['url']
					);
				} else {
					reepay_logs($response_message);
					wc_add_notice(  __( 'Connection error.', 'reepay' ), 'error' );
					return;
				}
			}	
		}
 	}
}
		
add_action( 'wp_ajax_nopriv_ajax_capture_charge', 'ajax_capture_charge' );
add_action( 'wp_ajax_ajax_capture_charge', 'ajax_capture_charge' );

function ajax_capture_charge() {
	$order_id = $_POST['order_id'];
	$order_full = $_POST['order_full'];
	$key = $_POST['key'];
	$order = wc_get_order($order_id);
	$total_price = $order->get_total() * 100;
	$url = 'https://api.reepay.com//v1/charge/'.$order_full.'/settle';
	$args = array(
		'headers' => array(
			'authorization' => 'Basic '.$key,
			'accept' => 'application/json',
			'content-type' => 'application/json',
			'User-Agent' => 'wordpress/v1.2.3 (codemakers)'
		)
	);
	$response = wp_remote_post( $url, $args );
	$array = $response["response"];
	$response_message = wp_remote_retrieve_body( $response );
	if ($array["code"] == '200') {
		$subscriptions = wcs_get_subscriptions_for_order( $order_id );
		if(empty($subscriptions)){
			$subscription_id = get_post_meta($order_id, '_subscription_renewal', true);
			$subscription = wcs_get_subscription($subscription_id);
			if(!empty($subscription)){
				$subscription->update_status( 'active' );
			}
		}else{
			foreach( $subscriptions as $subscription_id => $subscription ){
				$subscription->update_status( 'active' );
			}
		}
		$order->add_order_note( __('The charge has been successfully captured.', 'reepay') );
		update_post_meta($order_id, 'reepay_charge', '1');
		$return = array(
			'message'  => __( 'The charge has been successfully captured.', 'reepay' ),
			'errormessage'	=> $array["message"],
			'errorid' => $array["code"],
		);
	}else{
		reepay_logs($response_message);
		$return = array(
			'message'  => __( 'Connection error.', 'reepay' ),
			'errormessage'	=> $array["message"],
			'errorid' => $array["code"],
		);
	}
	wp_send_json($return);
	die();
}

add_action( 'wp_ajax_nopriv_ajax_cancel_invoice', 'ajax_cancel_invoice' );
add_action( 'wp_ajax_ajax_cancel_invoice', 'ajax_cancel_invoice' );

function ajax_cancel_invoice() {
	$order_full = $_POST['order_full'];
	$order_id = $_POST['order_id'];
	$key = $_POST['key'];
	$order = wc_get_order($order_id);
	
	$url = 'https://api.reepay.com//v1/charge/'.$order_full.'/cancel';
			
	$args = array(
		'headers' => array(
			'authorization' => 'Basic '.$key,
			'accept' => 'application/json',
			'content-type' => 'application/json',
			'User-Agent' => 'wordpress/v1.2.3 (codemakers)'
		)
	);
	$response = wp_remote_post( $url, $args );
	$array = $response["response"];
	$response_message = wp_remote_retrieve_body( $response );
	if ($array["code"] != '200') {
		reepay_logs($response_message);
		$return = array(
			'errormessage'	=> $array['message'],
			'errorid' => $array["code"],
		);
	}else{
		$subscriptions = wcs_get_subscriptions_for_order( $order_id );
		if(empty($subscriptions)){
			$subscription_id = get_post_meta($order_id, '_subscription_renewal', true);
			$subscription = wcs_get_subscription($subscription_id);
			if(!empty($subscription)){
				$subscription->update_status( 'cancelled' );
			}
		}else{
			foreach( $subscriptions as $subscription_id => $subscription ){
				$subscription->update_status( 'cancelled' );
			}
		}
		$return = array(
			'errormessage'	=> $array['message'],
			'errorid' => $array["code"],
		);
		$order->update_status('cancelled');
		$order->add_order_note( __('The cancel has been successful.', 'reepay') );
	}
	wp_send_json($return);
	die();
	
}

function reepay_logs($message) { 
    $path = dirname(__FILE__) . '/log.txt';
    $agent = $_SERVER['HTTP_USER_AGENT'];
    if (($h = fopen($path, "a")) !== FALSE) {
        $mystring = date('Y-m-d h:i:s') ." : ". $message ."\n";
        fwrite( $h, $mystring );
        fclose($h);
    } 
}

add_action( 'personal_options_update','save_reepay_user_profile_fields' );
add_action( 'edit_user_profile_update', 'save_reepay_user_profile_fields' );

add_action( 'show_user_profile', 'reepay_user_profile_fields' );
add_action( 'edit_user_profile', 'reepay_user_profile_fields' );

function save_reepay_user_profile_fields( $user_id ) {
	if ( !current_user_can( 'edit_user', $user_id ) ) { 
		return false; 
	}
	update_user_meta( $user_id, 'reepay_customer_id', $_POST['reepay_customer_id'] );
}
		
function reepay_user_profile_fields( $user ) {
	echo '<h3>'. __("Reepay", "reepay") .'</h3>';
	echo  '<table class="form-table">
	<tr>
		<th><label for="reepay_customer_id">'. __("Customer id", "reepay") .'</label></th>
		<td>
			<input type="text" name="reepay_customer_id" id="reepay_customer_id" value="'.esc_attr( get_the_author_meta( 'reepay_customer_id', $user->ID ) ).'" class="regular-text" /><br />
		</td>
	</tr>
	</table>';
}

add_action( 'woocommerce_after_order_notes', 'reepay_token_id_checkout_hidden_field', 10, 1 );
function reepay_token_id_checkout_hidden_field( $checkout ) {
    echo '<div id="token_id_field">
            <input type="hidden" class="input-hidden" name="token_id" id="token_id" value="">
    </div>';
}

add_action( 'woocommerce_checkout_update_order_meta', 'save_reepay_toke_id_checkout_hidden_field', 10, 1 );
function save_reepay_toke_id_checkout_hidden_field( $order_id ) {
	update_post_meta( $order_id, 'invoice_id_customer_id', sanitize_text_field( $_POST['token_id'] ) );
}

add_filter( 'manage_edit-shop_order_columns', 'reepay_add_new_order_admin_list_column' );
function reepay_add_new_order_admin_list_column( $columns ) {
	$columns['reepay_charge'] = 'Reepay charge';
	return $columns;
}
 
add_action( 'manage_shop_order_posts_custom_column', 'reepay_add_new_order_admin_list_column_content' );
function reepay_add_new_order_admin_list_column_content( $column ) {
	global $post;
	if ( 'reepay_charge' === $column ) {
		$charge = get_post_meta(get_the_ID(), 'reepay_charge', true);
		if($charge == 1){
			echo '<img width="20" src="'.plugins_url( 'images/check.png', __FILE__ ).'">';
		}
	}
}