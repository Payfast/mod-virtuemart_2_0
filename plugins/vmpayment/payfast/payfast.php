<?php
/**
 * payfast.php
 * 
 * Copyright (c) 2012 PayFast (Pty) Ltd
 *
 * LICENSE:
 * 
 * This payment module is free software; you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published
 * by the Free Software Foundation; either version 3 of the License, or (at
 * your option) any later version.
 * 
 * This payment module is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
 * or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Lesser General Public
 * License for more details.
 * 
 * Portions of this file contain code Copyright (C) 2004-2008 soeren - All rights reserved.
 * 
 * @author      Jonathan Page
 * @copyright   2012 PayFast (Pty) Ltd
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        http://www.payfast.co.za/help/virtuemart
 * @version     1.3.1
 */

defined('_JEXEC') or die('Direct Access to ' . basename(__FILE__) . ' is not allowed.');

if (!class_exists('vmPSPlugin'))
    require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');

define('SANDBOX_MERCHANT_ID', '10000100');
define('SANDBOX_MERCHANT_KEY', '46f0cd694581a');

class plgVMPaymentPayFast extends vmPSPlugin 
{
    // Instance of class
    public static $_this = false;

    function __construct(& $subject, $config) 
    {
    	parent::__construct($subject, $config);
    
    	$this->_loggable = true;
    	$this->tableFields = array_keys($this->getTableSQLFields());
    
    	$varsToPush = array(
            'payfast_merchant_key' => array('', 'char'),
            'payfast_merchant_id' => array('', 'char'),
    	    'payfast_verified_only' => array('', 'int'),
    	    'payment_currency' => array(0, 'int'),
    	    'sandbox' => array(0, 'int'),
    	    'sandbox_merchant_key' => array('', 'char'),
    	    'sandbox_merchant_id' => array('', 'char'),
    	    'payment_logos' => array('', 'char'),
    	    'debug' => array(0, 'int'),
    	    'status_pending' => array('', 'char'),
    	    'status_success' => array('', 'char'),
    	    'status_canceled' => array('', 'char'),
    	    'countries' => array(0, 'char'),
    	    'min_amount' => array(0, 'int'),
    	    'max_amount' => array(0, 'int'),
    	    'cost_per_transaction' => array(0, 'int'),
    	    'cost_percent_total' => array(0, 'int'),
    	    'tax_id' => array(0, 'int')
    	);
    
        	$this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
    }

    function _getPayfastDetails($method)
    {
    	if ($method->sandbox)
        {
            $payfastDetails = array(
                'merchant_id' => SANDBOX_MERCHANT_ID,
                'merchant_key' => SANDBOX_MERCHANT_KEY, 
                'url' => 'https://sandbox.payfast.co.za/eng/process'
            );
        }
        else 
        {
            $payfastDetails = array(
                'merchant_id' => $method->payfast_merchant_id,
                'merchant_key' => $method->payfast_merchant_key, 
                'url' => 'https://www.payfast.co.za/eng/process'
            );
        }
    
    	return $payfastDetails;
    }

    function _getPaymentResponseHtml($payfastData, $payment_name) 
    {
        $html = "";
    	/*vmdebug('payfast response', $payfastData);
    
    	$html = '<table>' . "\n";
    	$html .= $this->getHtmlRow('PAYFAST_PAYMENT_NAME', $payment_name);
    	$html .= $this->getHtmlRow('PAYFAST_ORDER_NUMBER', $payfastData['invoice']);
    	$html .= $this->getHtmlRow('PAYFAST_AMOUNT', $payfastData['mc_gross'] . " " . $payfastData['mc_currency']);
    
    	$html .= '</table>' . "\n";*/
    
    	return $html;
    }

    /**
     * Check if the payment conditions are fulfilled for this payment method
     * @author: Valerie Isaksen
     *
     * @param $cart_prices: cart prices
     * @param $payment
     * @return true: if the conditions are fulfilled, false otherwise
     *
     */
    protected function checkConditions($cart, $method, $cart_prices) {

	$address = (($cart->ST == 0) ? $cart->BT : $cart->ST);

	$amount = $cart_prices['salesPrice'];
	$amount_cond = ($amount >= $method->min_amount AND $amount <= $method->max_amount
		OR
		($method->min_amount <= $amount AND ($method->max_amount == 0) ));

	$countries = array();
	if (!empty($method->countries)) {
	    if (!is_array($method->countries)) {
		$countries[0] = $method->countries;
	    } else {
		$countries = $method->countries;
	    }
	}
	// probably did not gave his BT:ST address
	if (!is_array($address)) {
	    $address = array();
	    $address['virtuemart_country_id'] = 0;
	}

	if (!isset($address['virtuemart_country_id']))
	    $address['virtuemart_country_id'] = 0;
	if (in_array($address['virtuemart_country_id'], $countries) || count($countries) == 0) {
	    if ($amount_cond) {
		return true;
	    }
	}

	return false;
    }
    
    protected function getVmPluginCreateTableSQL() 
    {
	   return $this->createTableSQL('Payment PayFast Table');
    }

    function getTableSQLFields() 
    {
    	$SQLfields = array(
    	    'id' => 'int(11) UNSIGNED NOT NULL AUTO_INCREMENT',
    	    'virtuemart_order_id' => ' int(11) UNSIGNED DEFAULT NULL',
    	    'order_number' => ' char(32) DEFAULT NULL',
    	    'virtuemart_paymentmethod_id' => ' mediumint(1) UNSIGNED DEFAULT NULL',
    	    'payment_name' => ' char(255) NOT NULL DEFAULT \'\' ',
    	    'payment_order_total' => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\' ',
    	    'payment_currency' => 'char(3) ',
    	    'cost_per_transaction' => ' decimal(10,2) DEFAULT NULL ',
    	    'cost_percent_total' => ' decimal(10,2) DEFAULT NULL ',
    	    'tax_id' => ' smallint(1) DEFAULT NULL',
    	    'payfast_response' => ' varchar(255)  ',
    	    'payfast_response_payment_date' => ' char(28) DEFAULT NULL'
    	);
    	
        return $SQLfields;
    }

    function plgVmConfirmedOrder($cart, $order) 
    {
        if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) 
        {
    	    return null;
        }
	
        if (!$this->selectedThisElement($method->payment_element)) 
        {
	       return false;
	    }
	
        $session = JFactory::getSession();
    	$return_context = $session->getId();
    	$this->_debug = $method->debug;
    	$this->logInfo('plgVmConfirmedOrder order number: ' . $order['details']['BT']->order_number, 'message');
    
    	if (!class_exists('VirtueMartModelOrders'))
    	    require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );
    	if (!class_exists('VirtueMartModelCurrency'))
    	    require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'currency.php');
    
    	//$usr = & JFactory::getUser();
    	$new_status = '';
    
    	$usrBT = $order['details']['BT'];
    	$address = ((isset($order['details']['ST'])) ? $order['details']['ST'] : $order['details']['BT']);

    	$vendorModel = new VirtueMartModelVendor();
    	$vendorModel->setId(1);
    	$vendor = $vendorModel->getVendor();
    	$this->getPaymentCurrency($method);
    	$q = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="' . $method->payment_currency . '" ';
    	$db = &JFactory::getDBO();
    	$db->setQuery($q);
    	$currency_code_3 = $db->loadResult();
    
    	$paymentCurrency = CurrencyDisplay::getInstance($method->payment_currency);
    	$totalInPaymentCurrency = round($paymentCurrency->convertCurrencyTo($method->payment_currency, $order['details']['BT']->order_total,false), 2);
    	$cd = CurrencyDisplay::getInstance($cart->pricesCurrency);
    
    	$payfastDetails = $this->_getPayfastDetails($method);
    	
        if (empty($payfastDetails['merchant_id'])) 
        {
    	    vmInfo(JText::_('VMPAYMENT_PAYFAST_MERCHANT_ID_NOT_SET'));
    	    return false;
    	}
    
    	$testReq = $method->debug == 1 ? 'YES' : 'NO';
    	$post_variables = Array(
            // Merchant details
            'merchant_id' => $payfastDetails['merchant_id'],
            'merchant_key' => $payfastDetails['merchant_key'],
            'return_url' => JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id."&o_id={$order['details']['BT']->order_number}"),
            'cancel_url' => JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginUserPaymentCancel&on=' . $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id),
            'notify_url' => JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&tmpl=component&on=' . $order['details']['BT']->order_number .'&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id."&XDEBUG_SESSION_START=session_name"."&o_id={$order['details']['BT']->order_number}"),
    
            // Item details
        	'item_name' => JText::_('VMPAYMENT_payfast_ORDER_NUMBER') . ': ' . $order['details']['BT']->order_number,
        	'item_description' => "",
        	'amount' => number_format( sprintf( "%01.2f", $totalInPaymentCurrency ), 2, '.', '' ),
            'm_payment_id' => $order['details']['BT']->virtuemart_paymentmethod_id,
            'currency_code' => $currency_code_3,
            'custom_str1' => $order['details']['BT']->order_number,
            'custom_int1' => ""
            );
    
    	// Prepare data that should be stored in the database
    	$dbValues['order_number'] = $order['details']['BT']->order_number;
    	$dbValues['payment_name'] = $this->renderPluginName($method, $order);
    	$dbValues['virtuemart_paymentmethod_id'] = $cart->virtuemart_paymentmethod_id;
    	$dbValues['payfast_custom'] = $return_context;
    	$dbValues['cost_per_transaction'] = $method->cost_per_transaction;
    	$dbValues['cost_percent_total'] = $method->cost_percent_total;
    	$dbValues['payment_currency'] = $method->payment_currency;
    	$dbValues['payment_order_total'] = $totalInPaymentCurrency;
    	$dbValues['tax_id'] = $method->tax_id;
    	$this->storePSPluginInternalData($dbValues);
    
    	$html = '<form action="' . $payfastDetails['url'] .'" method="post" name="vm_payfast_form" >';
    	$html.= '<input type="image" name="submit" src="\images\stories\virtuemart\payment\payfast.png" alt="Click to pay with PayFast - it is fast, free and secure!" />';
    	foreach ($post_variables as $name => $value) 
        {
    	    $html.= '<input type="hidden" name="' . $name . '" value="' . htmlspecialchars($value) . '" />';
    	}
    	$html.= '</form>';
    
    	$html.= ' <script type="text/javascript">';
    	$html.= ' document.vm_payfast_form.submit();';
    	$html.= ' </script>';
    	// 	2 = don't delete the cart, don't send email and don't redirect
    	return $this->processConfirmedOrderPaymentResponse(2, $cart, $order, $html, $new_status);
    }

    function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId) 
    {
    	if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) 
        {
    	    return null; // Another method was selected, do nothing
    	}
    	if (!$this->selectedThisElement($method->payment_element)) 
        {
    	    return false;
    	}
   	    
        $this->getPaymentCurrency($method);
    	$paymentCurrencyId = $method->payment_currency;
    }

    function plgVmOnPaymentResponseReceived(  &$html) 
    {
        // the payment itself should send the parameter needed.
    	$virtuemart_paymentmethod_id = JRequest::getInt('pm', 0);
    
    	$vendorId = 0;
    	if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) 
        {
    	    return null; // Another method was selected, do nothing
    	}
    	
        if (!$this->selectedThisElement($method->payment_element)) 
        {
    	    return false;
    	}
    
    	$payment_data = JRequest::get('get');
    	vmdebug('plgVmOnPaymentResponseReceived', $payment_data);
    	$order_number = $payment_data['o_id'];

    	if (!class_exists('VirtueMartModelOrders'))
    	    require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );
    
    	$virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number);
    	$payment_name = $this->renderPluginName($method);
    	$html = $this->_getPaymentResponseHtml($payment_data, $payment_name);

	    if ($virtuemart_order_id) 
        {
    		if (!class_exists('VirtueMartCart'))
    		    require(JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');
    		
            // get the correct cart / session
    		$cart = VirtueMartCart::getCart();
    
    		// send the email ONLY if payment has been accepted
    		if (!class_exists('VirtueMartModelOrders'))
    		    require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );
    		
            $order = new VirtueMartModelOrders();
    		$orderitems = $order->getOrder($virtuemart_order_id);
    		//$cart->sentOrderConfirmedEmail($orderitems);
    		$cart->emptyCart();
	    }

        return true;
    }

    function plgVmOnUserPaymentCancel() 
    {
    	if (!class_exists('VirtueMartModelOrders'))
    	    require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );
    
    	$order_number = JRequest::getVar('on');
    	if (!$order_number)
    	    return false;
    	
        $db = JFactory::getDBO();
    	$query = 'SELECT ' . $this->_tablename . '.`virtuemart_order_id` FROM ' . $this->_tablename. " WHERE  `order_number`= '" . $order_number . "'";
    
    	$db->setQuery($query);
    	$virtuemart_order_id = $db->loadResult();
    
    	if (!$virtuemart_order_id) 
        {
    	    return null;
    	}
    	
        $this->handlePaymentUserCancel($virtuemart_order_id);
    	return true;
    }

    /*
     *   plgVmOnPaymentNotification() - This event is fired by Offline Payment. It can be used to validate the payment data as entered by the user.
     * Return:
     * Parameters:
     *  None
     *  @author Valerie Isaksen
     */
    function plgVmOnPaymentNotification() 
    {
        if (!class_exists('VirtueMartModelOrders'))
    	    require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );

        // Include PayFast Common File
        require_once("payfast_common.inc");

        // Variable Initialization
        $pfError = false;
        $pfErrMsg = '';
        $pfDone = false;
        $pfData = array();
        $pfOrderId = '';
        $pfParamString = '';

        //// Notify PayFast that information has been received
        if( !$pfError && !$pfDone )
        {
            header( 'HTTP/1.0 200 OK' );
            flush();
        }

        //// Get data sent by PayFast
        if( !$pfError && !$pfDone )
        {
            pflog( 'Get posted data' );
        
            // Posted variables from ITN
            $pfData = pfGetData();
            $payfast_data = $pfData;
        
            pflog( 'PayFast Data: '. print_r( $pfData, true ) );
        
            if( $pfData === false )
            {
                $pfError = true;
                $pfErrMsg = PF_ERR_BAD_ACCESS;
            }
        }
    
    	$order_number = $payfast_data['custom_str1'];
    	$virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($payfast_data['custom_str1']);
    	$this->logInfo('plgVmOnPaymentNotification: virtuemart_order_id  found ' . $virtuemart_order_id, 'message');
    
    	if (!$virtuemart_order_id) 
        {
    	    $this->_debug = true; // force debug here
    	    $this->logInfo('plgVmOnPaymentNotification: virtuemart_order_id not found ', 'ERROR');
    	    // send an email to admin, and ofc not update the order status: exit  is fine
    	    //$this->sendEmailToVendorAndAdmins(JText::_('VMPAYMENT_PAYFAST_ERROR_EMAIL_SUBJECT'), JText::_('VMPAYMENT_PAYFAST_UNKNOWN_ORDER_ID'));
    	    exit;
    	}
        
    	$vendorId = 0;
    	$payment = $this->getDataByOrderId($virtuemart_order_id);
    	$method = $this->getVmPluginMethod($payment->virtuemart_paymentmethod_id);
        $pfHost = ($method->sandbox ? 'sandbox' : 'www') . '.payfast.co.za';
    	
        if (!$this->selectedThisElement($method->payment_element)) 
        {
    	    return false;
    	}
    
    	$this->_debug = $method->debug;
    	if (!$payment) 
        {
    	    $this->logInfo('getDataByOrderId payment not found: exit ', 'ERROR');
    	    return null;
    	}
    	$this->logInfo('payfast_data ' . implode('   ', $payfast_data), 'message');

        
        pflog( 'PayFast ITN call received' );
        
        //// Verify security signature
        if( !$pfError && !$pfDone )
        {
            pflog( 'Verify security signature' );
        
            // If signature different, log for debugging
            if( !pfValidSignature( $pfData, $pfParamString ) )
            {
                $pfError = true;
                $pfErrMsg = PF_ERR_INVALID_SIGNATURE;
            }
        }
    
        //// Verify source IP (If not in debug mode)
        if( !$pfError && !$pfDone && !PF_DEBUG )
        {
            pflog( 'Verify source IP' );
        
            if( !pfValidIP( $_SERVER['REMOTE_ADDR'] ) )
            {
                $pfError = true;
                $pfErrMsg = PF_ERR_BAD_SOURCE_IP;
            }
        }
    
        //// Verify data received
        if( !$pfError )
        {
            pflog( 'Verify data received' );
        
            $pfValid = pfValidData( $pfHost, $pfParamString );
        
            if( !$pfValid )
            {
                $pfError = true;
                $pfErrMsg = PF_ERR_BAD_ACCESS;
            }
        }
            
        //// Check data against internal order
        if( !$pfError && !$pfDone )
        {
           // pflog( 'Check data against internal order' );
    
            // Check order amount
            if( !pfAmountsEqual( $pfData['amount_gross'], $payment->payment_order_total ) )
            {
                $pfError = true;
                $pfErrMsg = PF_ERR_AMOUNT_MISMATCH;
            }
        }
    
        //// Check status and update order
        if( !$pfError && !$pfDone )
        {
            pflog( 'Check status and update order' );
    
            $sessionid = $pfData['custom_str1'];
            $transaction_id = $pfData['pf_payment_id'];
    
    		switch( $pfData['payment_status'] )
            {
                case 'COMPLETE':
                    pflog( '- Complete' );
                    $new_status = $method->status_success;
                    break;
    
    			case 'FAILED':
                    pflog( '- Failed' );
            	    $new_status = $method->status_canceled;
        			break;
    
    			case 'PENDING':
                    pflog( '- Pending' );
    
                    // Need to wait for "Completed" before processing
        			break;
    
    			default:
                    // If unknown status, do nothing (safest course of action)
    			break;
            }
        }
    
    
        // If an error occurred
        if( $pfError )
        {
            pflog( 'Error occurred: '. $pfErrMsg );
        }
            	
    	// get all know columns of the table
    	$response_fields['virtuemart_order_id'] = $virtuemart_order_id;
    	$response_fields['order_number'] = $order_number;
        $response_fields['virtuemart_payment_method_id'] = $payment->virtuemart_paymentmethod_id;
        $response_fields['payment_name'] = $this->renderPluginName($method);
    	$response_fields['cost_per_transaction'] = $payment->cost_per_transaction;
    	$response_fields['cost_percent_total'] = $payment->cost_percent_total;
    	$response_fields['payment_currency'] = $payment->payment_currency;
    	$response_fields['payment_order_total'] = $totalInPaymentCurrency;
    	$response_fields['tax_id'] = $method->tax_id;
        $response_fields['payfast_response'] = $pfData['payment_status'];
        $response_fields['payfast_response_payment_date'] = date('Y-m-d H:i:s');

    	$this->storePSPluginInternalData($response_fields);
    
    	$this->logInfo('plgVmOnPaymentNotification return new_status:' . $new_status, 'message');
    
    	if ($virtuemart_order_id && $pfData['payment_status'] == 'COMPLETE') 
        {
    	    // send the email only if payment has been accepted
    	    if (!class_exists('VirtueMartModelOrders'))
                require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );
    	    
            $modelOrder = new VirtueMartModelOrders();
    	    $order['order_status'] = $new_status;
    	    $order['virtuemart_order_id'] = $virtuemart_order_id;
    	    $order['customer_notified'] = 1;
    	    $order['comments'] = JTExt::sprintf('VMPAYMENT_PAYFAST_PAYMENT_CONFIRMED', $order_number);
    	    $modelOrder->updateStatusForOneOrder($virtuemart_order_id, $order, true);
    	}
        
    	$this->emptyCart($return_context);
    
        // Close log
        pflog( '', true );
    	return true;
    }

    /**
     * Display stored payment data for an order
     * @see components/com_virtuemart/helpers/vmPSPlugin::plgVmOnShowOrderBEPayment()
     */
    function plgVmOnShowOrderBEPayment($virtuemart_order_id, $payment_method_id) 
    {
        if (!$this->selectedThisByMethodId($payment_method_id)) 
        {
            return null; // Another method was selected, do nothing
        }

    	$db = JFactory::getDBO();
    	$q = 'SELECT * FROM `' . $this->_tablename . '` '
    		. 'WHERE `virtuemart_order_id` = ' . $virtuemart_order_id;
    	$db->setQuery($q);

    	if (!($paymentTable = $db->loadObject())) 
        {
    	   // JError::raiseWarning(500, $db->getErrorMsg());
    	    return '';
    	}
    	
        $this->getPaymentCurrency($paymentTable);
    	$q = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="' . $paymentTable->payment_currency . '" ';
    	$db = &JFactory::getDBO();
    	$db->setQuery($q);
    	$currency_code_3 = $db->loadResult();
    	$html = '<table class="adminlist">' . "\n";
    	$html .=$this->getHtmlHeaderBE();
    	$html .= $this->getHtmlRowBE('payfast_PAYMENT_NAME', $paymentTable->payment_name);
    	//$html .= $this->getHtmlRowBE('payfast_PAYMENT_TOTAL_CURRENCY', $paymentTable->payment_order_total.' '.$currency_code_3);
    	$code = "payfast_response_";
    	foreach ($paymentTable as $key => $value) 
        {
    	    if (substr($key, 0, strlen($code)) == $code) 
            {
                $html .= $this->getHtmlRowBE($key, $value);
    	    }
    	}
    	$html .= '</table>' . "\n";
    	return $html;
    }

    /**
     * Create the table for this plugin if it does not yet exist.
     * This functions checks if the called plugin is active one.
     * When yes it is calling the standard method to create the tables
     * @author Valérie Isaksen
     *
     */
    function plgVmOnStoreInstallPaymentPluginTable($jplugin_id) 
    {
	   return $this->onStoreInstallPluginTable($jplugin_id);
    }

    /**
     * This event is fired after the payment method has been selected. It can be used to store
     * additional payment info in the cart.
     *
     * @author Max Milbers
     * @author Valérie isaksen
     *
     * @param VirtueMartCart $cart: the actual cart
     * @return null if the payment was not selected, true if the data is valid, error message if the data is not vlaid
     *
     */
    public function plgVmOnSelectCheckPayment(VirtueMartCart $cart) 
    {
	   return $this->OnSelectCheck($cart);
    }

    /**
     * plgVmDisplayListFEPayment
     * This event is fired to display the pluginmethods in the cart (edit shipment/payment) for exampel
     *
     * @param object $cart Cart object
     * @param integer $selected ID of the method selected
     * @return boolean True on succes, false on failures, null when this plugin was not selected.
     * On errors, JError::raiseWarning (or JError::raiseError) must be used to set a message.
     *
     * @author Valerie Isaksen
     * @author Max Milbers
     */
    public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn) 
    {
	   return $this->displayListFE($cart, $selected, $htmlIn);
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
    public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name) 
    {
	   return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
    }

    /**
     * plgVmOnCheckAutomaticSelectedPayment
     * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
     * The plugin must check first if it is the correct type
     * @author Valerie Isaksen
     * @param VirtueMartCart cart: the cart object
     * @return null if no plugin was found, 0 if more then one plugin was found,  virtuemart_xxx_id if only one plugin is found
     *
     */
    function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array()) 
    {
	   return $this->onCheckAutomaticSelected($cart, $cart_prices);
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
    public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name) 
    {
	   $this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
    }

    /**
     * This method is fired when showing when priting an Order
     * It displays the the payment method-specific data.
     *
     * @param integer $_virtuemart_order_id The order ID
     * @param integer $method_id  method used for this order
     * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
     * @author Valerie Isaksen
     */
    function plgVmonShowOrderPrintPayment($order_number, $method_id) 
    {
	   return $this->onShowOrderPrint($order_number, $method_id);
    }

    /**
     * This method is fired when showing the order details in the frontend, for every orderline.
     * It can be used to display line specific package codes, e.g. with a link to external tracking and
     * tracing systems
     *
     * @param integer $_orderId The order ID
     * @param integer $_lineId
     * @return mixed Null for method that aren't active, text (HTML) otherwise
     * @author Oscar van Eijk

      public function plgVmOnShowOrderLineFE(  $_orderId, $_lineId) {
      return null;
      }
     */
    function plgVmDeclarePluginParamsPayment($name, $id, &$data) 
    {
	   return $this->declarePluginParams('payment', $name, $id, $data);
    }

    function plgVmSetOnTablePluginParamsPayment($name, $id, &$table) 
    {
	   return $this->setOnTablePluginParams($name, $id, $table);
    }
}
