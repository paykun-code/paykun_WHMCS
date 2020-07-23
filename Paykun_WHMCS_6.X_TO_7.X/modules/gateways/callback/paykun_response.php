<?php



require_once __DIR__ . '/../../../init.php';

//require_once __DIR__ . '/../../../functions.php';

require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

//Add Payment file
require_once(dirname(__FILE__) . '/../paykun-sdk/Payment.php');
require_once(dirname(__FILE__) . '/../paykun-sdk/Errors/ValidationException.php');


$PK_gatewaymodule = "paykun";
$PK_GATEWAY_PARAM = getGatewayVariables($PK_gatewaymodule);

if (!$PK_GATEWAY_PARAM['type']) {
    die("Module Not Activated");
}

//Get query param
$response = $_REQUEST;

if(isset($response['payment-id'])) {

    //Get all configurations
    $PK_isLvie      = (strtolower($PK_GATEWAY_PARAM['pk_is_live']) == 'no') ? false : true;
    $PK_merchantId  = $PK_GATEWAY_PARAM['pk_merchant_id'];
    $PK_accessToken = $PK_GATEWAY_PARAM['pk_access_token'];

    $PK_transactionId = $response['payment-id'];
    $PK_responseData = getTransactionInfo($PK_transactionId, $PK_merchantId, $PK_accessToken, $PK_isLvie);
    $returnResponse = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http")."://$_SERVER[HTTP_HOST]$_SERVER[SCRIPT_NAME]";
    if(isset($PK_responseData['status']) && ($PK_responseData['status'] == "1" || $PK_responseData['status'] == 1 || $PK_responseData['status'] == true)) {

        $PK_payment_status = $PK_responseData['data']['transaction']['status'];
        $PK_order_id = $PK_responseData['data']['transaction']['custom_field_1'];
        $invoiceId = checkCbInvoiceID($PK_order_id, $PK_GATEWAY_PARAM['name']);
        //Add log for response
        logTransaction($PK_GATEWAY_PARAM['name'], $PK_responseData, $PK_payment_status);

        if($PK_payment_status === "Success") {
            $PK_resAmout = $response['data']['transaction']['order']['gross_amount'];
            $result = mysql_fetch_assoc(select_query('tblinvoices', 'total, userid', array("id" => $PK_order_id)));
            $amount = $result['total'];
            logTransaction($PK_GATEWAY_PARAM['name'], $PK_resAmout, $amount);

            addInvoicePayment(
                $invoiceId,
                $PK_transactionId,
                $amount,
                $PK_responseData['data']['transaction']['order']['gateway_fee'] + $PK_responseData['data']['transaction']['order']['tax'],
                $PK_GATEWAY_PARAM['name']
            );

            /*if(($amount	== $PK_resAmout)) {

            } else {
                logTransaction($PK_GATEWAY_PARAM['name'], $PK_responseData, 'Order Mismatched => '.'OrderAmount = '.$amount. ', responseAmount = '.$PK_resAmout);
            }*/

            $filename=str_replace('modules/gateways/callback/paykun_response.php','viewinvoice.php?id='.$PK_order_id, $returnResponse);
            header("Location: $filename");
        } else {
            //Transaction is failed
            logTransaction($PK_GATEWAY_PARAM['name'], $PK_responseData, $PK_payment_status);
            $filename = str_replace('modules/gateways/callback/paykun_response.php','viewinvoice.php?id='.$PK_order_id, $returnResponse);
            header("Location: $filename");
        }

        $filename = str_replace('modules/gateways/callback/paykun_response.php','viewinvoice.php?id='.$PK_order_id, $returnResponse);
        header("Location: $filename");
    }
}
else {

    logTransaction($PK_GATEWAY_PARAM['name'], $PK_responseData, $PK_payment_status);
    $location=str_replace('modules/gateways/callback/paykun_response.php','', $returnResponse);
    header("Location: $location");

}

/**
 * @param $paymentId
 * @param $mid
 * @param $atoken
 * @param $isLive
 * @return mixed|null
 * @throws \Paykun\Errors\ValidationException
 */
function getTransactionInfo($paymentId, $mid, $atoken, $isLive) {
    try {
        if($isLive == true) {
            $cUrl        = 'https://api.paykun.com/v1/merchant/transaction/' . $paymentId . '/';
        } else {
            $cUrl        = 'https://sandbox.paykun.com/api/v1/merchant/transaction/' . $paymentId . '/';
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $cUrl);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("MerchantId:$mid", "AccessToken:$atoken"));
        $response       = curl_exec($ch);
        $error_number   = curl_errno($ch);
        $error_message  = curl_error($ch);
        $res = json_decode($response, true);
        curl_close($ch);
        return ($error_message) ? null : $res;

    } catch (\Paykun\Errors\ValidationException $e) {

        throw new \Paykun\Errors\ValidationException("Server couldn't respond, ".$e->getMessage(), $e->getCode(), null);
        return null;

    }
}

?>