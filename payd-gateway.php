<?php
/*
 * Plugin Name: WooCommerce Payd Payment Gateway
 * Plugin URI: https://www.payd.ae/
 * Description: payd payment gateway
 * Author: Ankit Prajapati
 * Author URI: 
 * Version: 1.0.1
 *
 */
 
 /*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter( 'woocommerce_payment_gateways', 'payd_add_gateway_class' );
function payd_add_gateway_class( $gateways ) {
	$gateways[] = 'WC_Payd_Gateway'; // your class name is here
	return $gateways;
}
 
add_action( 'woocommerce_thankyou', 'payd_add_content_thankyou' );
  
function payd_add_content_thankyou($order_id) {
  
    $order = wc_get_order( $order_id );
    global $wpdb; 
    $result = $wpdb->get_results("SELECT * FROM wp_payd_transactions WHERE orderid =$order_id ");
    //print_r($result);
    if(count($result) > 0){
     
        $referenceid = $result[0]->referenceid;
        $mode = $result[0]->mode; 

        $curl = curl_init();

        $secret = 'your secret key';

        curl_setopt_array($curl, array(
        CURLOPT_URL => "https://www.payd.ae/pg/public/api/paymentdetails/$referenceid/$mode",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => array(
            "cache-control: no-cache",
            "content-type: text/plain",
            "secretkey: $secret"
        ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
        //echo "cURL Error #:" . $err;
            //payment status not verified
            wc_add_notice( 'Payment error: Payment Status Not verified ', 'error' );
            $order->add_order_note(  'Payment error: Payment Status Not verified ' );

        } else {
        //echo $response;
        
        $datas = json_decode($response);

        $remarks = $datas->remarks;
        $status = $datas->data;
        $paymentstatus = $status->payment_status;

        if($datas->message == "success")
        {
            wc_add_notice( 'Payment success: Payment Status verified by Payd', 'success' );
            $order->add_order_note(  'Payment success: Payment Status verified by Payd ' );

            global $wpdb;  
            $wpdb->query("UPDATE `wp_payd_transactions` SET `paymentstatus`='$paymentstatus',`remarks`='$remarks' WHERE orderid =$order_id");  

            
            $order->payment_complete();
            //$order->wc_reduce_stock_levels();
            wc_reduce_stock_levels( $order_id ); 
            //$order->reduce_order_stock();
        }    
        }
        
    }


}

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action( 'plugins_loaded', 'payd_init_gateway_class' );
function payd_init_gateway_class() {
 
	class WC_Payd_Gateway extends WC_Payment_Gateway {
 
 		/**
 		 * Class constructor, more about it in Step 3
 		 */
          public function __construct() {
            
            $this->id = 'payd_gateway'; // payment gateway plugin ID
            $this->icon = apply_filters( 'woocommerce_noob_icon', plugins_url('/icon.png', __FILE__ ) );; // URL of the icon that will be displayed on checkout page near your gateway name
            $this->has_fields = false; // in case you need a custom credit card form
            $this->method_title = 'Payd Gateway';
            $this->method_description = 'Description of payd payment gateway'; // will be displayed on the options page
        
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
            //$this->publishable_key = $this->testmode ? $this->get_option( 'test_publishable_key' ) : $this->get_option( 'publishable_key' );
        
            // This action hook saves the settings
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        
            // We need custom JavaScript to obtain a token
            //add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );

        }
 
		/**
 		 * Plugin options, we deal with it in Step 3 too
 		 */
 		public function init_form_fields(){

            $this->form_fields = array(
                'enabled' => array(
                    'title'       => 'Enable/Disable',
                    'label'       => 'Enable Payd Gateway',
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ),
                'title' => array(
                    'title'       => 'Title',
                    'type'        => 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => 'Description',
                    'type'        => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default'     => 'Pay using Payd Payment Gateway.',
                ),
                'testmode' => array(
                    'title'       => 'Test mode',
                    'label'       => 'Enable Test Mode',
                    'type'        => 'checkbox',
                    'description' => 'Place the payment gateway in test mode using test API keys.',
                    'default'     => 'yes',
                    'desc_tip'    => true,
                ),
                'test_private_key' => array(
                    'title'       => 'Test Private Key',
                    'type'        => 'password',
                ),
                'private_key' => array(
                    'title'       => 'Live Private Key',
                    'type'        => 'password'
                )
            );
 
	
 
	 	}
 
		/**
		 * You will need it if you want your custom credit card form, Step 4 is about it
		 */
		public function payment_fields() {
 
	
 
		}
 
		/*
		 * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
		 */
	 	public function payment_scripts() {


 
	
 
	 	}
 
		/*
 		 * Fields validation, more in Step 5
		 */
		public function validate_fields() {

            if( empty( $_POST[ 'billing_first_name' ]) ) {
                wc_add_notice(  'First name is required!', 'error' );
                return false;
            }
            return true;
            
            if( empty( $_POST[ 'billing_last_name' ]) ) {
                wc_add_notice(  'Last name is required!', 'error' );
                return false;
            }
            return true;

            if( empty( $_POST[ 'billing_address_1' ]) ) {
                wc_add_notice(  'Billing Address is required!', 'error' );
                return false;
            }
            return true;

            if( empty( $_POST[ 'billing_city' ]) ) {
                wc_add_notice(  'Billing City is required!', 'error' );
                return false;
            }
            return true;

            if( empty( $_POST[ 'billing_postcode' ]) ) {
                wc_add_notice(  'Billing Postcode is required!', 'error' );
                return false;
            }
            return true;

            if( empty( $_POST[ 'billing_phone' ]) ) {
                wc_add_notice(  'Billing Phone is required!', 'error' );
                return false;
            }
            return true;

            if( empty( $_POST[ 'billing_email' ]) ) {
                wc_add_notice(  'Billing Email is required!', 'error' );
                return false;
            }
            return true;
	
 
		}
 
		/*
		 * We're processing the payments here, everything about it is in Step 5
		 */
		public function process_payment( $order_id ) {
 
            global $woocommerce;
 
            // we need it to get any order detailes
            $order = wc_get_order( $order_id );

            $secret = $this->private_key;

            $order->update_status( 'on-hold', 'Awaiting Payd Payment' );

            $customer_id = $order->get_user_id();
            $customer_user =  $order->get_user_id();
            $customer_email = ($a = get_userdata($order->get_user_id() )) ? $a->user_email : '';
            $billing_company = $order->get_billing_company();
            $billing_email = $order->get_billing_email();
            $billing_phone = $order->get_billing_phone();
            $billing_postcode = $order->get_billing_postcode();
            
            $ordernumber = $order->get_order_number();
            
            $map = $order->get_shipping_address_map_url();
            $billing_fullname = $order->get_formatted_billing_full_name();
            $shipping_fullname = $order->get_formatted_shipping_full_name();
            
            $currency = $order->get_currency();
            $totalitems = $order->get_item_count();
            $order_total = $order->get_total();

            $i = 0;

            // Get and Loop Over Order Items
            foreach ( $order->get_items() as $item_id => $item ) {
                // $product_id = $item->get_product_id();
                // $variation_id = $item->get_variation_id();
                // $product = $item->get_product();
                // $name = $item->get_name();
                // $quantity = $item->get_quantity();
                // $subtotal = $item->get_subtotal();
                // $total = $item->get_total();
                // $tax = $item->get_subtotal_tax();
                // $taxclass = $item->get_tax_class();
                // $taxstat = $item->get_tax_status();
                // $allmeta = $item->get_meta_data();
                // $somemeta = $item->get_meta( '_whatever', true );
                // $type = $item->get_type();

                $data['products'][$i]['title'] =  $item->get_name() ;
                $data['products'][$i]['details'] = $item->get_name() ;
                $data['products'][$i]['amount'] =  $item->get_total();
                $data['products'][$i]['vat'] = "0" ;
                $data['products'][$i]['total'] =  $item->get_total();
                
                $i++;

            }
            $curl = curl_init();

            $data['name'] = $billing_fullname;
            $data['amount'] = $order_total;
            $data['remarks'] = "Ipsot Product Purchased";
            $data['email'] = $billing_email;
            $data['phone'] =  $billing_phone;
            $data['redirecturl'] = $this->get_return_url( $order );

            $data['product'] = json_encode( $data['products']);
            curl_setopt_array($curl, array(
                CURLOPT_URL => "https://www.payd.ae/pg/public/api/generateTransactionId",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => "name= ".$data['name']."&amount=".$data['amount']."&remarks=".$data['remarks']."&phone=".$data['phone']."&email=".$data['email']."&redirecturl=".$data['redirecturl']."&product=".$data['product']."",
                CURLOPT_HTTPHEADER => array(
                  "cache-control: no-cache",
                  "content-type: application/x-www-form-urlencoded",
                  "secretkey: $secret"
                ),
              ));

            $response = curl_exec($curl);
            $err = curl_error($curl);

            curl_close($curl);

            $result = json_decode($response);

            if ( $result->message === "Success") {

                $url = $result->data;
                $parts = parse_url($url);
                parse_str($parts['query'], $query);
                $uniqueid = $query['uniqueid'];
                $mode =  $query['mode'];

                global $wpdb;  
                $wpdb->query("INSERT INTO `wp_payd_transactions`( `orderid`,`referenceid`, `total`, `mode`, `userid`,`paymentstatus`,`remarks`) VALUES ($order_id,$uniqueid,$order_total,$mode, $customer_id,'pending','waiting for confirmation')");  
               
                $redirect = $result->data; // example: https://mybank.com/payment/...

                return array(
                    'result' => 'success',
                    'redirect' => $redirect // ???
                );

            } else {
                wc_add_notice( 'Payment error: ' . $result->message , 'error' );
                $order->add_order_note(  'Payment error: ' . $result->message  );
                return;
            }
         
	 	}
 
		/*
		 * In case you need a webhook, like PayPal IPN etc
		 */
		public function webhook() {
 
		
	 	}
 	}
}
 
 
