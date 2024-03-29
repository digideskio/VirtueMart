<?php

defined ('_JEXEC') or die('Restricted access');

/**
 * @author Cardstream
 * @package VirtueMart
 * @subpackage payment
 * @copyright Copyright (c) 2015 Cardstream. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 */
if (!class_exists ('vmPSPlugin')) {
	require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
}

class plgVmPaymentCardstream extends vmPSPlugin {

	function __construct (& $subject, $config) {

		parent::__construct ($subject, $config);

		$this->_loggable = TRUE;
		$this->tableFields = array_keys ($this->getTableSQLFields ());
		$this->_tablepkey = 'id';
		$this->_tableId = 'id';
		$varsToPush = $this->getVarsToPush ();
		$this->setConfigParameterable ($this->_configTableFieldName, $varsToPush);

	}

	/**
	 * Create the table for this plugin if it does not yet exist.
	 *
	 */
	public function getVmPluginCreateTableSQL () {

		return $this->createTableSQL ('Cardstream Hosted Table');
	}

	/**
	 * Fields to create the payment table
	 *
	 * @return string SQL Fileds
	 */
	function getTableSQLFields () {
		error_log("Called: " . __METHOD__);
		$SQLfields = array(
			'id'                          => 'int(1) UNSIGNED NOT NULL AUTO_INCREMENT',
			'virtuemart_order_id'         => 'int(1) UNSIGNED',
			'order_number'                => 'char(64)',
			'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
			'payment_name'                => 'varchar(5000)',
			'payment_order_total'         => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\'',
			'payment_currency'            => 'char(3)',
			'email_currency'              => 'char(3)',
			'cost_per_transaction'        => 'decimal(10,2)',
			'cost_percent_total'          => 'decimal(10,2)',
			'tax_id'                      => 'smallint(1)', 
			'response_code'				  => 'int(1) UNSIGNED NULL DEFAULT NULL',
			'response_message' 			  => 'varchar(255) NULL DEFAULT NULL',
			'xref' 						  => 'varchar(255) NULL DEFAULT NULL',
			'response'					  => 'text',
		);

		return $SQLfields;
	}

	/**
	 *
	 *
	 */
	function plgVmConfirmedOrder ($cart, $order) {
		error_log("Called: " . __METHOD__);
		
		if (!($method = $this->getVmPluginMethod ($order['details']['BT']->virtuemart_paymentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement ($method->payment_element)) {
			return FALSE;
		}

		VmConfig::loadJLang('com_virtuemart',true);
		VmConfig::loadJLang('com_virtuemart_orders', TRUE);

		if (!class_exists ('VirtueMartModelOrders')) {
			require(VMPATH_ADMIN . DS . 'models' . DS . 'orders.php');
		}

		$this->getPaymentCurrency($method);
		$currency_code_3 = shopFunctions::getCurrencyByID($method->payment_currency, 'currency_code_3');
		$email_currency = $this->getEmailCurrency($method);

		$totalInPaymentCurrency = vmPSPlugin::getAmountInCurrency($order['details']['BT']->order_total,$method->payment_currency);

		$dbValues['payment_name'] = $this->renderPluginName ($method);
		$dbValues['order_number'] = $order['details']['BT']->order_number;
		$dbValues['virtuemart_paymentmethod_id'] = $order['details']['BT']->virtuemart_paymentmethod_id;
		$dbValues['cost_per_transaction'] = $method->cost_per_transaction;
		$dbValues['cost_percent_total'] = $method->cost_percent_total;
		$dbValues['payment_currency'] = $currency_code_3;
		$dbValues['email_currency'] = $email_currency;
		$dbValues['payment_order_total'] = $totalInPaymentCurrency['value'];
		$dbValues['tax_id'] = $method->tax_id;
		$dbValues['response_code'] = 9999999;
		$dbValues['response_message'] = NULL;
		$dbValues['xref'] = NULL;
		$dbValues['response'] = NULL;
		$this->storePSPluginInternalData ($dbValues);
		
		
		if (!class_exists ('VirtueMartModelCurrency')) {
			require(VMPATH_ADMIN . DS . 'models' . DS . 'currency.php');
		}
		$currency = CurrencyDisplay::getInstance ('', $order['details']['BT']->virtuemart_vendor_id);
		$format = number_format($totalInPaymentCurrency['value'],2);
                $amount = preg_replace("/,/", "", $format);
		
		$fields = array(	
			'merchantID' => $this->_vmpCtable->merchant_id, 
			'currencyCode' => $currency_code_3, 
			'countryCode' => 826, 
			'action' => 'SALE',
			'type' => 1,
			'orderRef' => $order['details']['BT']->order_number,
			'transactionUnique' => uniqid(),
			'amount' => $amount,
			'redirectURL' => JURI::root() . 'index.php?option=com_virtuemart&view=vmplg&task=pluginresponsereceived&on=' . $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id . '&Itemid=' . vRequest::getInt('Itemid'),
			'customerName' => $order['details']['BT']->first_name . ' ' . $order['details']['BT']->last_name,
			'customerAddress' => $order['details']['BT']->address_1 . "\n " . $order['details']['BT']->address_2 . "\n " . $order['details']['BT']->city,
			'customerPostcode' => $order['details']['BT']->zip,
			'customerEmail' => $order['details']['BT']->email,
		);			
			
/*
		$html = $this->renderByLayout('post_payment', array(
			'order_number' =>$order['details']['BT']->order_number,
			'order_pass' =>$order['details']['BT']->order_pass,
			'payment_name' => $dbValues['payment_name'],
			'displayTotalInPaymentCurrency' => $totalInPaymentCurrency['display']
		));
		$modelOrder = VmModel::getModel ('orders');
		$order['order_status'] = $this->getNewStatus ($method);
		$order['customer_notified'] = 1;
		$order['comments'] = '';
		$modelOrder->updateStatusForOneOrder ($order['details']['BT']->virtuemart_order_id, $order, TRUE);

		//We delete the old stuff
		$cart->emptyCart ();
*/

		if (vmconfig::get('css')) {
			$msg = vmText::_('VMPAYMENT_CARDSTREAM_REDIRECT_MESSAGE', true);
		} else {
			$msg='';
		}
		
		vmJsApi::addJScript('vm.paymentFormAutoSubmit', '
  			jQuery(document).ready(function($){
   				jQuery("body").addClass("vmLoading");
  				var msg="'.$msg.'";
   				jQuery("body").append("<div class=\"vmLoadingDiv\"><div class=\"vmLoadingDivMsg\">"+msg+"</div></div>");
    			jQuery("#vmCardstreamPaymentForm").submit();
			})
		');

		$html = '<form id="vmCardstreamPaymentForm" action="https://gateway.cardstream.com/hosted/" method="post">';
	
		foreach ($fields as $key => $value) {
			$html .= '<input type="hidden" name="' . $key . '" value="' . $value . '">';		
		}
	
		if (isset($this->_vmpCtable->signature_key) && !empty($this->_vmpCtable->signature_key)) {
			$html .= '<input type="hidden" name="signature" value="' . $this->createSignature($fields, $this->_vmpCtable->signature_key) . '" />';
		}
		
		$html .= '<input type="submit" value="' . vmText::_('VMPAYMENT_CARDSTREAM_REDIRECT_MESSAGE') . '"></form>';
		
		$cart->_confirmDone = FALSE;
		$cart->_dataValidated = FALSE;
		$cart->setCartIntoSession();

		vRequest::setVar ('html', $html);
		return TRUE;
	}



	/*
	 * Generate a Cardstream Signature
	 */
	function createSignature(array $data, $key = null, array $fields = null) {
	
		$pairs = ($fields ? array_intersect_key($data, array_flip($fields)) : $data);
			
		ksort($pairs);
	
		// Create the URL encoded signature string
		$ret = http_build_query($pairs, '', '&');
	
		// Normalise all line endings (CRNL|NLCR|NL|CR) to just NL (%0A)
		$ret = preg_replace('/%0D%0A|%0A%0D|%0A|%0D/i', '%0A', $ret);
	
		// Hash the signature string and the key together
		$ret = hash('SHA512', $ret . $key);
		
		if (!empty($fields)) {
			$x = '';
			foreach ($fields as $field) {
				$x .= ",{$field}";		
			}
			$x = ltrim($x, ",");
			
			$ret = "{$ret}|{$x}";
		}
	
		return $ret;	
		
	}



	/*
		 * Keep backwards compatibility
		 * a new parameter has been added in the xml file
		 */
	function getNewStatus ($method) {
	error_log("Called: " . __METHOD__);
		if (isset($method->status_pending) and $method->status_pending!="") {
			return $method->status_pending;
		} else {
			return 'P';
		}
	}

	/**
	 * Display stored payment data for an order
	 *
	 */
	function plgVmOnShowOrderBEPayment ($virtuemart_order_id, $virtuemart_payment_id) {
	error_log("Called: " . __METHOD__);
		if (!$this->selectedThisByMethodId ($virtuemart_payment_id)) {
			return NULL; // Another method was selected, do nothing
		}

		if (!($paymentTable = $this->getDataByOrderId ($virtuemart_order_id))) {
			return NULL;
		}
		VmConfig::loadJLang('com_virtuemart');

		$html = '<table class="adminlist table">' . "\n";
		$html .= $this->getHtmlHeaderBE ();
		$html .= $this->getHtmlRowBE ('COM_VIRTUEMART_PAYMENT_NAME', $paymentTable->payment_name);
		$html .= $this->getHtmlRowBE ('STANDARD_PAYMENT_TOTAL_CURRENCY', $paymentTable->payment_order_total . ' ' . $paymentTable->payment_currency);
		if ($paymentTable->email_currency) {
			$html .= $this->getHtmlRowBE ('STANDARD_EMAIL_CURRENCY', $paymentTable->email_currency );
		}
		$html .= '</table>' . "\n";
		return $html;
	}

	/*	function getCosts (VirtueMartCart $cart, $method, $cart_prices) {

			if (preg_match ('/%$/', $method->cost_percent_total)) {
				$cost_percent_total = substr ($method->cost_percent_total, 0, -1);
			} else {
				$cost_percent_total = $method->cost_percent_total;
			}
			return ($method->cost_per_transaction + ($cart_prices['salesPrice'] * $cost_percent_total * 0.01));
		}
	*/
	/**
	 * Check if the payment conditions are fulfilled for this payment method
	 *
	 * @author: Valerie Isaksen
	 *
	 * @param $cart_prices: cart prices
	 * @param $payment
	 * @return true: if the conditions are fulfilled, false otherwise
	 *
	 */
	protected function checkConditions ($cart, $method, $cart_prices) {
	error_log("Called: " . __METHOD__);
		$this->convert_condition_amount($method);
		$amount = $this->getCartAmount($cart_prices);
		$address = (($cart->ST == 0) ? $cart->BT : $cart->ST);


		//vmdebug('standard checkConditions',  $amount, $cart_prices['salesPrice'],  $cart_prices['salesPriceCoupon']);
		$amount_cond = ($amount >= $method->min_amount AND $amount <= $method->max_amount
			OR
			($method->min_amount <= $amount AND ($method->max_amount == 0)));
		if (!$amount_cond) {
			return FALSE;
		}
		$countries = array();
		if (!empty($method->countries)) {
			if (!is_array ($method->countries)) {
				$countries[0] = $method->countries;
			} else {
				$countries = $method->countries;
			}
		}

		// probably did not gave his BT:ST address
		if (!is_array ($address)) {
			$address = array();
			$address['virtuemart_country_id'] = 0;
		}

		if (!isset($address['virtuemart_country_id'])) {
			$address['virtuemart_country_id'] = 0;
		}
		if (count ($countries) == 0 || in_array ($address['virtuemart_country_id'], $countries) ) {
			return TRUE;
		}

		return FALSE;
	}


	/*
* We must reimplement this triggers for joomla 1.7
*/

	/**
	 * Create the table for this plugin if it does not yet exist.
	 * This functions checks if the called plugin is active one.
	 * When yes it is calling the standard method to create the tables
	 *
	 *
	 */
	function plgVmOnStoreInstallPaymentPluginTable ($jplugin_id) {
	error_log("Called: " . __METHOD__);
		return $this->onStoreInstallPluginTable ($jplugin_id);
	}

	/**
	 * This event is fired after the payment method has been selected. It can be used to store
	 * additional payment info in the cart.
	 *
	 * @author Max Milbers
	 *
	 * @param VirtueMartCart $cart: the actual cart
	 * @return null if the payment was not selected, true if the data is valid, error message if the data is not vlaid
	 *
	 */
	public function plgVmOnSelectCheckPayment (VirtueMartCart $cart, &$msg) {
	error_log("Called: " . __METHOD__);
		return $this->OnSelectCheck ($cart);
	}

	/**
	 * plgVmDisplayListFEPayment
	 * This event is fired to display the pluginmethods in the cart (edit shipment/payment) for exampel
	 *
	 * @param object  $cart Cart object
	 * @param integer $selected ID of the method selected
	 * @return boolean True on succes, false on failures, null when this plugin was not selected.
	 * On errors, JError::raiseWarning (or JError::raiseError) must be used to set a message.
	 *
	 * @author Valerie Isaksen
	 * @author Max Milbers
	 */
	public function plgVmDisplayListFEPayment (VirtueMartCart $cart, $selected = 0, &$htmlIn) {
	error_log("Called: " . __METHOD__);
		return $this->displayListFE ($cart, $selected, $htmlIn);
	}

	/*
* plgVmonSelectedCalculatePricePayment
* Calculate the price (value, tax_id) of the selected method
* It is called by the calculator
* This function does NOT to be reimplemented. If not reimplemented, then the default values from this function are taken.
* @author Valerie Isaksen
* @cart: VirtueMartCart the current cart
* @cart_prices: array the new cart prices
* @return null if the method was not selected, false if the shiiping rate is not valid any more, true otherwise
*
*
*/

	public function plgVmonSelectedCalculatePricePayment (VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name) {
	error_log("Called: " . __METHOD__);
		return $this->onSelectedCalculatePrice ($cart, $cart_prices, $cart_prices_name);
	}

	function plgVmgetPaymentCurrency ($virtuemart_paymentmethod_id, &$paymentCurrencyId) {
	error_log("Called: " . __METHOD__);
		if (!($method = $this->getVmPluginMethod ($virtuemart_paymentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement ($method->payment_element)) {
			return FALSE;
		}
		$this->getPaymentCurrency ($method);

		$paymentCurrencyId = $method->payment_currency;
		return;
	}

	/**
	 * plgVmOnCheckAutomaticSelectedPayment
	 * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
	 * The plugin must check first if it is the correct type
	 *
	 * @author Valerie Isaksen
	 * @param VirtueMartCart cart: the cart object
	 * @return null if no plugin was found, 0 if more then one plugin was found,  virtuemart_xxx_id if only one plugin is found
	 *
	 */
	function plgVmOnCheckAutomaticSelectedPayment (VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter) {
	error_log("Called: " . __METHOD__);
		return $this->onCheckAutomaticSelected ($cart, $cart_prices, $paymentCounter);
	}

	/**
	 * This method is fired when showing the order details in the frontend.
	 * It displays the method-specific data.
	 *
	 * @param integer $order_id The order ID
	 * @return mixed Null for methods that aren't active, text (HTML) otherwise
	 * @author Max Milbers
	 * @author Valerie Isaksen
	 */
	public function plgVmOnShowOrderFEPayment ($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name) {
	error_log("Called: " . __METHOD__);
		$this->onShowOrderFE ($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
	}
	/**
	 * @param $orderDetails
	 * @param $data
	 * @return null
	 */

	function plgVmOnUserInvoice ($orderDetails, &$data) {
	error_log("Called: " . __METHOD__);
		if (!($method = $this->getVmPluginMethod ($orderDetails['virtuemart_paymentmethod_id']))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement ($method->payment_element)) {
			return NULL;
		}
		//vmdebug('plgVmOnUserInvoice',$orderDetails, $method);

		if (!isset($method->send_invoice_on_order_null) or $method->send_invoice_on_order_null==1 or $orderDetails['order_total'] > 0.00){
			return NULL;
		}

		if ($orderDetails['order_salesPrice']==0.00) {
			$data['invoice_number'] = 'reservedByPayment_' . $orderDetails['order_number']; // Nerver send the invoice via email
		}

	}
	/**
	 * @param $virtuemart_paymentmethod_id
	 * @param $paymentCurrencyId
	 * @return bool|null
	 */
	function plgVmgetEmailCurrency($virtuemart_paymentmethod_id, $virtuemart_order_id, &$emailCurrencyId) {
	error_log("Called: " . __METHOD__);
		if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($method->payment_element)) {
			return FALSE;
		}
		if (!($payments = $this->getDatasByOrderId($virtuemart_order_id))) {
			// JError::raiseWarning(500, $db->getErrorMsg());
			return '';
		}
		if (empty($payments[0]->email_currency)) {
			$vendorId = 1; //VirtueMartModelVendor::getLoggedVendor();
			$db = JFactory::getDBO();
			$q = 'SELECT   `vendor_currency` FROM `#__virtuemart_vendors` WHERE `virtuemart_vendor_id`=' . $vendorId;
			$db->setQuery($q);
			$emailCurrencyId = $db->loadResult();
		} else {
			$emailCurrencyId = $payments[0]->email_currency;
		}

	}
	/**
	 * This event is fired during the checkout process. It can be used to validate the
	 * method data as entered by the user.
	 *
	 * @return boolean True when the data was valid, false otherwise. If the plugin is not activated, it should return null.
	 * @author Max Milbers

	public function plgVmOnCheckoutCheckDataPayment(  VirtueMartCart $cart) {
	return null;
	}
	 */

	/**
	 * This method is fired when showing when priting an Order
	 * It displays the the payment method-specific data.
	 *
	 * @param integer $_virtuemart_order_id The order ID
	 * @param integer $method_id  method used for this order
	 * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
	 * @author Valerie Isaksen
	 */
	function plgVmonShowOrderPrintPayment ($order_number, $method_id) {
	error_log("Called: " . __METHOD__);
		return $this->onShowOrderPrint ($order_number, $method_id);
	}

	function plgVmDeclarePluginParamsPaymentVM3( &$data) {
	error_log("Called: " . __METHOD__);
		return $this->declarePluginParams('payment', $data);
	}
	function plgVmSetOnTablePluginParamsPayment ($name, $id, &$table) {
	error_log("Called: " . __METHOD__);
		return $this->setOnTablePluginParams ($name, $id, $table);
	}

	//Notice: We only need to add the events, which should work for the specific plugin, when an event is doing nothing, it should not be added

	/**
	 * Save updated order data to the method specific table
	 *
	 * @param array   $_formData Form data
	 * @return mixed, True on success, false on failures (the rest of the save-process will be
	 * skipped!), or null when this method is not actived.
	 *
	public function plgVmOnUpdateOrderPayment(  $_formData) {
	return null;
	}

	/**
	 * Save updated orderline data to the method specific table
	 *
	 * @param array   $_formData Form data
	 * @return mixed, True on success, false on failures (the rest of the save-process will be
	 * skipped!), or null when this method is not actived.
	 *
	public function plgVmOnUpdateOrderLine(  $_formData) {
	return null;
	}

	/**
	 * plgVmOnEditOrderLineBE
	 * This method is fired when editing the order line details in the backend.
	 * It can be used to add line specific package codes
	 *
	 * @param integer $_orderId The order ID
	 * @param integer $_lineId
	 * @return mixed Null for method that aren't active, text (HTML) otherwise
	 *
	public function plgVmOnEditOrderLineBEPayment(  $_orderId, $_lineId) {
	return null;
	}

	/**
	 * This method is fired when showing the order details in the frontend, for every orderline.
	 * It can be used to display line specific package codes, e.g. with a link to external tracking and
	 * tracing systems
	 *
	 * @param integer $_orderId The order ID
	 * @param integer $_lineId
	 * @return mixed Null for method that aren't active, text (HTML) otherwise
	 *
	public function plgVmOnShowOrderLineFE(  $_orderId, $_lineId) {
	return null;
	}

	/**
	 * This event is fired when the  method notifies you when an event occurs that affects the order.
	 * Typically,  the events  represents for payment authorizations, Fraud Management Filter actions and other actions,
	 * such as refunds, disputes, and chargebacks.
	 *
	 * NOTE for Plugin developers:
	 *  If the plugin is NOT actually executed (not the selected payment method), this method must return NULL
	 *
	 * @param         $return_context: it was given and sent in the payment form. The notification should return it back.
	 * Used to know which cart should be emptied, in case it is still in the session.
	 * @param int     $virtuemart_order_id : payment  order id
	 * @param char    $new_status : new_status for this order id.
	 * @return mixed Null when this method was not selected, otherwise the true or false
	 *
	 * @author Valerie Isaksen
	 *
	 *
	public function plgVmOnPaymentNotification() {
	return null;
	}

	/**
	 *
	 *
	*/ 
	function plgVmOnPaymentResponseReceived(&$html) {

		if (!class_exists('VirtueMartCart')) {
			require(VMPATH_SITE . DS . 'helpers' . DS . 'cart.php');
		}
		if (!class_exists('shopFunctionsF')) {
			require(VMPATH_SITE . DS . 'helpers' . DS . 'shopfunctionsf.php');
		}
		if (!class_exists('VirtueMartModelOrders')) {
			require(VMPATH_ADMIN . DS . 'models' . DS . 'orders.php');
		}
		VmConfig::loadJLang('com_virtuemart_orders', TRUE);

				
		$rc = vRequest::getInt('responseCode', '');
		$virtuemart_paymentmethod_id = vRequest::getInt('pm', '');
		$order_number = vRequest::getString('on', 0);
		
		if (!($this->_currentMethod = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}
		
		if (!$this->selectedThisElement($this->_currentMethod->payment_element)) {
			return NULL;
		}

		if (!($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number))) {
			return NULL;
		}
		
		if (!($payments = $this->getDatasByOrderNumber($order_number))) {
			return '';
		}
				
		$payment_name = $this->renderPluginName($this->_currentMethod);
		$payment = end($payments);
					
		$dbValues['id'] = $payment->id;
		$dbValues['response_code'] = $rc;
		$dbValues['response_message'] = vRequest::getString('responseMessage', '');
		$dbValues['xref'] = vRequest::getString('xref', '');;
		$dbValues['response'] = serialize(vRequest::getPost());
		$dbValues['virtuemart_order_id'] = $virtuemart_order_id;
		$dbValues['order_number'] = $order_number;
		$dbValues['virtuemart_paymentmethod_id'] = $virtuemart_paymentmethod_id;
		
		$this->storePluginInternalData ($dbValues);	
		
		VmConfig::loadJLang('com_virtuemart');
		$orderModel = VmModel::getModel('orders');
		$order = $orderModel->getOrder($virtuemart_order_id);

		
		if (!class_exists('CurrencyDisplay')) {
			require(VMPATH_ADMIN . DS . 'helpers' . DS . 'currencydisplay.php');
		}
		$currency = CurrencyDisplay::getInstance('', $order['details']['BT']->order_currency);
		
		$to_sign = $_POST;
		unset($to_sign['signature']);

		if ($rc === "0" && ($this->createSignature($to_sign, $this->_currentMethod->signature_key) ==  vRequest::getString('signature'))) {
			$returnValue = 1;
			$new_status = $this->_currentMethod->status_success;
			
		} else {
			$success = false;
			$returnValue = 2;
			$new_status = $this->_currentMethod->status_pending;
		}

		$html = $this->renderByLayout('post_payment', array(
			"success" => $returnValue,
			"payment_name" => $payment_name,
			"order" => $order,
			"currency" => $currency,
			"response_code" => $dbValues['response_code'],
			"response_message" => $dbValues['response_message'],
		));

		$this->processConfirmedOrderPaymentResponse($returnValue, VirtueMartCart::getCart(), $order, $html, $payment_name, $new_status);

		return TRUE;

	}
	

}

// No closing tag
