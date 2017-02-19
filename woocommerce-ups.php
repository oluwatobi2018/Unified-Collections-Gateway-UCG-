<?php
class WC_OO_Unified_Payment_System extends WC_Payment_Gateway
{
	// Setup Gateway's id, description and other values
	function __construct()
	{
	
		// The global ID for this Payment method
		$this->id = 'upsl';
		
		// credit card logo
		$this->icon = plugins_url( 'assets/images/creditcard.png' , __FILE__ );

		// The Title shown on the top of the Payment Gateways Page next to all the other Payment Gateways
		$this->method_title = __( 'Unified Payment Services Limited', 'oo-ups' );

		// The description for this Payment Gateway, shown on the actual Payment options page on the backend
		$this->method_description = __( 'Unified Payment Services Limited Payment Gateway Plug-in for WooCommerce', 'oo-ups' );

		// The title to be used for the vertical tabs that can be ordered top to bottom
		$this->title = __( 'Unified Payment Services Limited', 'oo-ups' );

		// If you want to show an image next to the gateway's name on the frontend, enter a URL to an image.
		// $this->icon = apply_filters('woocommerce_ups_icon', plugins_url( 'assets/images/ups.png' , __FILE__ ) );

		// Callback url
		// $this->redirect_url = WC()->api_request_url( 'WC_OO_Unified_Payment_System' );

		// Bool. Can be set to true if you want payment fields to show on the checkout
		$this->has_fields = false;

		// This basically defines your settings which are then loaded with init_settings()
		$this->init_form_fields();

		// After init_settings() is called, you can get the settings and load them into variables, e.g:
		// $this->title = $this->get_option( 'title' );
		$this->init_settings();
	
		// Turn these settings into variables we can use
		foreach ( $this->settings as $setting_key => $value ) {
			$this->$setting_key = $value;
		}
		
		// Lets check for SSL
		add_action( 'admin_notices', array( $this,	'do_ssl_check' ) );

		// Check is gateway is in sandbox
		add_action( 'admin_notices', array( $this,	'check_test_mode' ) );

		// Payment listener/API hook
		// add_action( 'woocommerce_api_unified_payment_system_gateway', array( $this, 'check_response' ) );

		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'check_response'  ));
		
		// Save settings
		if ( is_admin() ) {
			// Versions over 2.0
			// Save our administration options. Since we are not going to be doing anything special
			// we have not defined 'process_admin_options' in this class so the method in the parent
			// class will be used instead
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		}
	} // End __construct()

	// Build the administration fields for this specific Gateway
	public function init_form_fields() 
	{
		$this->form_fields = array(
			'enabled' => array(
				'title'		=> __( 'Enable / Disable', 'oo-ups' ),
				'label'		=> __( 'Enable this payment gateway', 'oo-ups' ),
				'type'		=> 'checkbox',
				'default'	=> 'no',
			),
			'title' => array(
				'title'		=> __( 'Title', 'oo-ups' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'Payment title the customer will see during the checkout process.', 'oo-ups' ),
				'default'	=> __( 'Credit card', 'oo-ups' ),
			),
			'description' => array(
				'title'		=> __( 'Description', 'oo-ups' ),
				'type'		=> 'textarea',
				'desc_tip'	=> __( 'Payment description the customer will see during the checkout process.', 'oo-ups' ),
				'default'	=> __( 'Service Provided by Unified Payment Services Limited.', 'oo-ups' ),
				'css'		=> 'max-width:350px;',
			),
			'currenct_code' => array(
                'title'     => __('Currency Code'),
                'type'      => 'text',
                'desc_tip'  => __('Payment gateway currency code'),
            ),
			'merchant_id' => array(
                'title'     => __('Merchant ID'),
                'type'      => 'text',
                'desc_tip'  => __('Merchant ID is case sensitive'),
            ),
            'cert_info' => array(
                'title'     => __('.CRT File Name'),
                'type'      => 'text',
                'desc_tip'  => __('Certificate Information File Name,  you may leave this field empty if not sure'),
                'default'	=> __( 'CAcert.crt', 'oo-ups' ),
            ),
            'ssl_cert' => array(
                'title'    => __('.PEM File Name'),
                'type'     => 'text',
                'desc_tip' => __('SSL Certificate File Name,  you may leave this field empty if not sure'),
                // 'default'  => __( $this->merchant_id.'.pem', 'oo-ups' ),
            ),
            'ssl_key' => array(
                'title'    => __('.KEY File Name'),
                'type'     => 'text',
                'desc_tip' => __('SSL Key File Name, you may leave this field empty if not sure'),
                // 'default'  => __( $this->merchant_id.'.key', 'oo-ups' ),
            ),            
			'environment' => array(
				'title'		=> __( 'Gateway Status', 'oo-ups' ),
				'label'		=> __( 'Enable Test Mode', 'oo-ups' ),
				'type'		=> 'checkbox',
				'description' => __( 'Check to put the payment gateway in test mode.', 'oo-ups' ),
				'default'	=> 'yes',
			),
		);		
	}

	// Submit payment and handle response
    public function process_payment( $order_id )
    {
	    $response = $this->create_payment_request( $order_id );

	    if( 'success' == $response['result'] )
	    {
	    	// Redirect to gateway payment page
	    	return array(
	    		'result' 	=> 'success',
	    		'redirect'	=> $response['redirect']
	    	);
		}
		else
		{
			// Failed attempt
			// uncomment for debug purpose only
			// WP debug must be enabled
			// print_r(curl_error($response['connection']));
			
			throw new Exception( __( 'We are currently experiencing problems trying to connect to this payment gateway. Sorry for the inconvenience.', 'oo-ups' ) );

			return array(
				'result' 	=> 'fail',
				'redirect'	=> ''
		    );
		}
    }

	// Submit payment and handle response
	public function create_payment_request($order_id)
	{
		global $woocommerce;
	
		// Get this Order's information so that we know
		// who to charge and how much
		$customer_order = new WC_Order( $order_id );
		
		// Are we testing right now or is it a real transaction
		// $environment = ( $this->environment == 'yes' ) ? TRUE : FALSE;

		// Decide which URL to post to
		// ($environment_url == 'yes' ? TRUE : FALSE)
		$environment_url = ( $this->environment == 'yes' ) 
						   ? 'https://196.46.20.36:5444/Exec'  // Test server
						   : 'https://196.46.20.33:5444/exec';  // Live server

		$total_amount = $customer_order->order_total * 100;

		// $success_order   = $customer_order->get_checkout_order_received_url();
		$success_order   = $this->get_return_url( $customer_order );
		$cancelled_order = $customer_order->get_cancel_order_url();
		$declined_order  = $this->get_return_url( $customer_order );

		// This is where the fun stuff begins
		$xml = "<?xml version='1.0' encoding='UTF-8'?>
			<TKKPG>
			<Request>
			<Operation>CreateOrder</Operation>
			<Language>EN</Language>
			<Order>
			<Merchant>".$this->merchant_id."</Merchant>
			<Amount>".$total_amount."</Amount>
			<Currency>".$this->currency_code."</Currency>
			<Description>Payment for Order ID:". $order_id ." on ".get_bloginfo('name')."</Description>
			<ApproveURL>". $success_order ."</ApproveURL>
			<CancelURL>". $cancelled_order ."</CancelURL>
			<DeclineURL>". $declined_order ."</DeclineURL>
			</Order>
			</Request>
			</TKKPG>";		

		// Get cURL resource
		$ch = curl_init();
		// Set some options
		curl_setopt_array($ch, array(
			CURLOPT_URL             => $environment_url,
			CURLOPT_RETURNTRANSFER  => 1,
			CURLOPT_CONNECTTIMEOUT  => 0,
			CURLOPT_TIMEOUT         => 5000,
			CURLOPT_SSL_VERIFYHOST  => 0,
			CURLOPT_SSL_VERIFYPEER  => TRUE,
			CURLOPT_SSLVERSION 		=> 6,
			CURLOPT_CAINFO          => dirname(__FILE__).DIRECTORY_SEPARATOR."cert".DIRECTORY_SEPARATOR.$this->cert_info,
			CURLOPT_SSLCERT         => dirname(__FILE__).DIRECTORY_SEPARATOR."cert".DIRECTORY_SEPARATOR.$this->ssl_cert,
			CURLOPT_SSLKEY          => dirname(__FILE__).DIRECTORY_SEPARATOR."cert".DIRECTORY_SEPARATOR.$this->ssl_key,
			CURLOPT_HTTPHEADER      => array('Content-Type: text/xml'),
			CURLOPT_POSTFIELDS      => $xml,
			)
		);

		//Send the request & save response to $response 
		$response = curl_exec($ch);

		if ( !$response  )
		{
			return array(
		    	'result'	=> 'fail',
				'connection'=> $ch,
		    	'redirect'	=> '',
			);
		}
		else
		{	
			// Retrieve the body's resopnse if no errors found
			//convert the XML result into array
		    $array_data = json_decode(json_encode(simplexml_load_string($response)), true);
		    foreach ($array_data as $data) {

		    	$orderid   = $data['Order']['OrderID'];
		    	$sessionid = $data['Order']['SessionID'];
		    	$order_url = $data['Order']['URL'];
		    	$gateway_url = $order_url."?ORDERID=".$orderid."&SESSIONID=".$sessionid;

		    	$gateway_transaction_query_details = $orderid."_".$sessionid."_".$order_id;

		    	WC()->session->set( 'oo_wc_ups_order_details', $gateway_transaction_query_details );

		    	// Redirect to gateway payment page
				return array(
					'result'    => 'success',
					'orderid'   => $orderid,
					'sessionid' => $sessionid,
					'redirect'  => $gateway_url,
				);

	    	}
	    }
	    // Close request to clear up some resources
		curl_close($ch);
	}

	// Check Unified Payment Services Limited response
	public function query_ups_transaction( $orderid, $sessionid )
	{
		// Are we testing right now or is it a real transaction
		$environment = ( $this->environment == 'yes' ) ? 'TRUE' : 'FALSE';

		// Decide which URL to post to
		$environment_url = ( 'FALSE' == $environment ) 
						   ? 'https://196.46.20.33:5444/exec'  // Live server
						   : 'https://196.46.20.36:5444/Exec'; // Test server


		$checkResp = "<?xml version='1.0' encoding='UTF-8'?>
			<TKKPG>
			<Request>
			<Operation>GetOrderStatus</Operation>
			<Language>EN</Language>
			<Order>
			<Merchant>$this->merchant_id</Merchant>
			<OrderID>$orderid</OrderID>
			</Order>
			<SessionID>$sessionid</SessionID>
			</Request>
			</TKKPG>";

		// Get cURL resource
		$curl = curl_init();
		// Set some options
		curl_setopt_array($curl, array(
			CURLOPT_URL             => $environment_url,
			CURLOPT_RETURNTRANSFER  => 1,
			CURLOPT_CONNECTTIMEOUT  => 0,
			CURLOPT_TIMEOUT         => 5000,
			CURLOPT_SSL_VERIFYHOST  => 0,
			CURLOPT_SSL_VERIFYPEER  => TRUE,
			CURLOPT_SSLVERSION 		=> 6,
			CURLOPT_CAINFO          => dirname(__FILE__).DIRECTORY_SEPARATOR."cert".DIRECTORY_SEPARATOR.$this->cert_info,
			CURLOPT_SSLCERT         => dirname(__FILE__).DIRECTORY_SEPARATOR."cert".DIRECTORY_SEPARATOR.$this->ssl_cert,
			CURLOPT_SSLKEY          => dirname(__FILE__).DIRECTORY_SEPARATOR."cert".DIRECTORY_SEPARATOR.$this->ssl_key,
			CURLOPT_HTTPHEADER      => array('Content-Type: text/xml'),
			CURLOPT_POSTFIELDS      => $checkResp,
			)
		);
			//Send the request & save response to $response 
			$resp = curl_exec($curl);

			if (!$resp)
			{
				$gatewayreply = array(
			    	'result'	=> 'fail',
			    	'redirect'	=> '',
				);
			}
			else
			{
				//convert the XML result into array
		        $array_data = json_decode(json_encode(simplexml_load_string($resp)), true);
		        foreach ($array_data as $chdata) {

		        	$gateway_response_id        = $chdata['Order']['OrderID'];
		        	$gateway_response_sessionid = $chdata['Order']['OrderStatus'];

		        	$gatewayreply = array(
		        		'orderid'	    => $gateway_response_id,
		        		'orderstatus'	=> $gateway_response_sessionid,
			    	);
			    	return $gatewayreply;
		        }
			}
		// Close request to clear up some resources
		curl_close($curl);
	}

	// Verify a successful payment
	public function check_response()
	{
		global $woocommerce;

		$details = WC()->session->get( 'oo_wc_ups_order_details' );

		if ( $details ) {
			$order_details = explode("_",$details);
			$orderid   = $order_details[0];
			$sessionid = $order_details[1];
			$order_id = $order_details[2];

			$order = new WC_Order($order_id);

			$total_amount = $order->get_total() * 100;

			$gateway_response = $this->query_ups_transaction( $orderid , $sessionid );

			// Get response from post data
			$upsResponse = json_decode( json_encode( simplexml_load_string( stripslashes($_POST['xmlmsg']) ) ) );

			$OrderID             = $upsResponse->OrderID;
			$PAN                 = $upsResponse->PAN;
			$PurchaseAmount      = $upsResponse->PurchaseAmount;
			$PurchaseAmountScr   = $upsResponse->PurchaseAmountScr;
			$currency            = $upsResponse->CurrencyScr;
			$TranDateTime        = $upsResponse->TranDateTime;
			$ResponseCode        = $upsResponse->ResponseCode;
			$ResponseDescription = $upsResponse->ResponseDescription;
			$Brand               = $upsResponse->Brand;
			$OrderStatus         = $upsResponse->OrderStatus;
			$ApprovalCodeScr     = $upsResponse->ApprovalCodeScr;
			$MerchantTranID      = $upsResponse->MerchantTranID;
			$Name                = $upsResponse->Name;

			// $PurchaseAmount = $PurchaseAmount / 100;

			if ('001' == $ResponseCode) 
			{
				// successful transaction
				// Add Customer Order Note
               	$order->add_order_note( 
               		'Your transaction was successful, payment was received. <br />
					Payment Reference: '.$orderid.'<br/>
					Order Status: '.$OrderStatus.'<br />
					Approval Code: '.$ApprovalCodeScr.'<br />
					Card Holder: '.$Name.'<br />
					Card Number: '.$PAN.'<br />
					Card Type: '.$Brand.'<br />
					Transaction done at: '.$TranDateTime, 1 
				);

                // Add Admin Order Note
                $order->add_order_note('XML DUMP: <pre>'.$_POST['xmlmsg'].'</pre>');
          		$order->add_order_note( 
               		'Payment was received. <br />
					Payment Reference: '.$orderid.'<br/>
					Order Status: '.$OrderStatus.'<br />
					Approval Code: '.$ApprovalCodeScr.'<br />
					Card Holder: '.$Name.'<br />
					Card Number: '.$PAN.'<br />
					Card Type: '.$Brand.'<br />
					Transaction done at: '.$TranDateTime
				);

				// Notification message
                $display_message = 
				'<p class="">Thank you for your order. Your transaction was successful.</p>
				<ul class="woocommerce-thankyou-order-details order_details">
					<li class="order">Payment Reference: <strong>'.$orderid.'</strong></li>
					<li class="date">Order Status: <strong style="color:#008000">'.$OrderStatus.'</strong></li>
					<li class="order">Order Status: <strong>'.$ApprovalCodeScr.'</strong></li>
					<li class="date">Card Holder: <strong>'.$Name.'</strong></li>
					<li class="order">Card Number: <strong>'.$PAN.'</strong></li>
					<li class="date">Card Type: <strong>'.$Brand.'</strong></li>
				</ul>
				';

				// Mark order as Paid
				$order->payment_complete();

				// Empty the cart (Very important step)
				WC()->cart->empty_cart();

				// Update the order status
				$order->update_status('completed');

				// Unset session
				WC()->session->__unset( 'oo_wc_ups_order_details' );

				echo $display_message;
			}

			elseif( '001' == $ResponseCode &&  $total_amount != $PurchaseAmount )
			{
				// Successful transaction but amount not the same
				// Add Customer Order Note
                $order->add_order_note(
					'Your payment transaction was successful, but the amount paid is not the same as the total order amount.<br />
					Your order is currently on-hold.<br />
					Amount Paid was &#8358; '.$PurchaseAmountScr.' while the Total Order Amount is &#8358; '.$order->get_total().'<br />
					Kindly contact us for more information regarding your order and payment status.<br />
					Payment Reference: '.$orderid.'<br/>
					Order Status: '.$OrderStatus.'<br />
					Aproval Code: '.$ApprovalCodeScr.'<br />
					Card Holder: '.$Name.'<br />
					Card Number: '.$PAN.'<br />
					Card Type: '.$Brand.'<br />
					Transaction done at: '.$TranDateTime, 1
				);

                // Add Admin Order Note
                $order->add_order_note('XML DUMP: <pre>'.$_POST['xmlmsg'].'</pre>');
                $order->add_order_note(
                	'Look into this order.<br />
                	This order is currently on hold.<br />
                	Reason: Amount paid is less than the total order amount.<br />
                	Amount Paid was &#8358; '.$PurchaseAmountScr.' while the Total Order Amount is &#8358; '.$order->get_total().'<br />
                	Payment Reference: '.$orderid.'<br/>
                	Order Status: '.$OrderStatus.'<br />
					Aproval Code: '.$ApprovalCodeScr.'<br />
					Card Holder: '.$Name.'<br />
					Card Number: '.$PAN.'<br />
					Card Type: '.$Brand.'<br />
					Transaction done at: '.$TranDateTime
                );

                // Notification message
				$display_message = 
				'<p class="">Thank you for your order. However, the amount paid is not the same as the total order amount.<br />
				Your order is currently <strong>ON-HOLD</strong></p>
				<ul class="woocommerce-thankyou-order-details order_details">
					<li class="order">Payment Reference: <strong>'.$orderid.'</strong></li>
					<li class="date">Order Status: <strong style="color:#008000">'.$OrderStatus.'</strong></li>
					<li class="order">Aproval Code: <strong>'.$ApprovalCodeScr.'</strong></li>
					<li class="date">Card Holder: <strong>'.$Name.'</strong></li>
					<li class="order">Card Number: <strong>'.$PAN.'</strong></li>
					<li class="date">Card Type: <strong>'.$Brand.'</strong></li>
					<li class="total">Amount Paid: <strong><span class="amount">&#8358;'.number_format($order->get_total(), 2).'</span></strong></li>
				</ul>
				';

                // Mark order as Paid
				$order->payment_complete();

				// Empty the cart (Very important step)
				WC()->cart->empty_cart();

				// Update the order status
				$order->update_status('on-hold');

				// Unset session
				WC()->session->__unset( 'oo_wc_ups_order_details' );

				echo $display_message;
			}
			else
			{
				// process a failed transaction
				// Add Customer Order Note
               	$order->add_order_note( 
               		'Thank you for your order. <br />
	    			However, the transaction wasn\'t successful, payment wasn\'t received.<br />
	    			Reason: '. $ResponseDescription.'<br />
					Payment Reference: '.$orderid.'<br/>
					Order Status: '.$OrderStatus.'<br />
					Card Holder: '.$Name.'<br />
					Card Number: '.$PAN.'<br />
					Card Type: '.$Brand.'<br />
					Transaction done at: '.$TranDateTime, 1 
				);

                // Add Admin Order Note
                $order->add_order_note('XML DUMP: <pre>'.$_POST['xmlmsg'].'</pre>');
          		$order->add_order_note( 
               		'Thank you for your order. <br />
	    			However, the transaction was not successful. Reason: '. $ResponseDescription.'<br />
					Payment Reference: '.$orderid.'<br/>
					Order Status: '.$OrderStatus.'<br />
					Card Holder: '.$Name.'<br />
					Card Number: '.$PAN.'<br />
					Card Type: '.$Brand.'<br />
					Transaction done at: '.$TranDateTime
				);

              	// Notification message
                $display_message = 
				'<p class="">Thank you for your order. However, the transaction was not successful.<br />
				Reason: <strong>'.$ResponseDescription.'</strong></p>
				<ul class="woocommerce-thankyou-order-details order_details">
					<li class="order">Payment Reference: <strong>'.$orderid.'</strong></li>
					<li class="date">Order Status: <strong style="color:red">'.$OrderStatus.'</strong></li>
					<li class="date">Card Holder: <strong>'.$Name.'</strong></li>
					<li class="order">Card Number: <strong>'.$PAN.'</strong></li>
					<li class="date">Card Type: <strong>'.$Brand.'</strong></li>
				</ul>
				';

				// Update the order status
				$order->update_status('failed');

                echo $display_message;
			}
		}
	}

	// Check if we are forcing SSL on checkout pages
	// Custom function not required by the Gateway
	public function do_ssl_check() 
	{
		if( $this->enabled == 'yes' ) 
		{
			if( get_option( 'woocommerce_force_ssl_checkout' ) == 'no' ) 
			{
				echo "<div class=\"update-nag\"><p>". sprintf( __( "<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>" ), $this->method_title, admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) ."</p></div>";	
			}
		}		
	}
	// Check if we are using the gateway in sandbox
	// Custom function not required by the Gateway
	public function check_test_mode() 
	{
		if( $this->enabled == 'yes' ) 
		{

			if( $this->environment == 'yes' ) 
			{
				echo "<div class=\"update-nag\"><p>". sprintf( __( "<strong>%s</strong> is in test mode, you cannot accept live payment <a href=\"%s\">enable live mode when you are ready to accept live payment.</a>" ), $this->method_title, admin_url( 'admin.php?page=wc-settings&tab=checkout&section=upsl' ) ) ."</p></div>";	
			}
		}
	}
}