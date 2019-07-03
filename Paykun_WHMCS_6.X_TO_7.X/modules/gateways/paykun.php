<?php
require_once(dirname(__FILE__) . '/paykun-sdk/Payment.php');


/**
 * WHMCS PayKun Payment Gateway Module
 */
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function paykun_MetaData()
{
    return array(
        'DisplayName' => 'PayKun Payment Gateway Module',
        'APIVersion' => '1.1',
    );
}

/**
 * @return array
 * Gateway configuration options
 */
function paykun_config(){

    $configarray = array(
        "FriendlyName" => array("Type" => "System", "Value"=>"PayKun"),
        "pk_is_live" => array("FriendlyName" => "Is Live?", "Value"=>"yes", "Type" => "text", "Size" => "3", 'Description' => 'Type \'yes\' for live and \'no\' for sandbox'),
        "pk_merchant_id" => array("FriendlyName" => "Merchant ID", "Type" => "text", "Size" => "50"),
        "pk_access_token" => array("FriendlyName" => "Access Token", "Type" => "text", "Size" => "256"),
        "pk_enc_key" => array("FriendlyName" => "Encryption Key", "Type" => "text", "Size" => "256"),
    );
    return $configarray;
}


/**
 * @param $params
 * @return string
 * @throws \Paykun\Errors\ValidationException
 */
function paykun_link($params) {

    //Get all configurations
    $PK_isLvie      = (strtolower($params['pk_is_live']) == 'no') ? false : true;
    $PK_merchantId  = $params['pk_merchant_id'];
    $PK_accessToken = $params['pk_access_token'];
    $PK_encKey      = $params['pk_enc_key'];

    // Invoice Parameters
    $PK_orderId         = $params['invoiceid'];
    $PK_description     = $params["description"];
    $PK_amount          = $params['amount'];
    $PK_currencyCode    = $params['currency'];

    if($PK_currencyCode != "INR") {
        echo "Only INR Currency is allowed for now. please set your currency to INR";
        exit;
    }

    //Customer detail
    $PK_name    = $params['clientdetails']['firstname'].' '.$params['clientdetails']['lastname'];
    $PK_email   = $params['clientdetails']['email'];
    $PK_contact = $params['clientdetails']['phonenumber'];
    $PK_formattedOrderId = getOrderIdForPaykun($PK_orderId);

    try {

        $pk_obj = new \Paykun\Payment($PK_merchantId, $PK_accessToken, $PK_encKey, $PK_isLvie, true);
    } catch(Exception $e) {
        echo $e->getMessage();exit;
    }


    //Get callback url
    $callBackLink   = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http")."://$_SERVER[HTTP_HOST]$_SERVER[SCRIPT_NAME]";
    $callBackLink   = str_replace('cart.php', 'modules/gateways/callback/paykun_response.php', $callBackLink);
    $callBackLink   = str_replace('viewinvoice.php', 'modules/gateways/callback/paykun_response.php', $callBackLink);


    $pk_obj->initOrder($PK_formattedOrderId, $PK_description, $PK_amount, $callBackLink, $callBackLink);
    $pk_obj->addCustomer($PK_name, $PK_email, $PK_contact);
    $pk_obj->addBillingAddress('', '', '', '', '');
    $pk_obj->addShippingAddress('', '', '', '', '');
    $pk_obj->setCustomFields(['udf_1' => $PK_orderId]);

    //echo  $pk_obj->prepareCustomFormTemplate();exit;
    $pk_data =  $pk_obj->submit();
    return $pk_obj->prepareCustomFormTemplate($pk_data);
}

/**
 * @param $orderId
 * @return string
 */
function getOrderIdForPaykun($orderId) {

    $orderId = 'WHMCS_'.$orderId;
    $orderNumber = str_pad((string)$orderId, 10, '0', STR_PAD_LEFT);
    return $orderNumber;

}