<?php

include(dirname(__FILE__).'/../../config/config.inc.php');
include(dirname(__FILE__).'/../../header.php');
include(dirname(__FILE__).'/jeeb.php');
// error_log("Entered Jeeb-Notification");

// $handle = fopen('php://input','r');
// $postdata = fgets($handle);
$jeeb = new jeeb();
$postdata = file_get_contents("php://input");
$json = json_decode($postdata, true);
// fclose($handle);
if($json['signature']==Configuration::get('jeeb_APIKEY')){
  if($json['orderNo']){
    error_log("hey".$json['orderNo']);

    $orderNo = $json['orderNo'];

    $db = Db::getInstance();
    $result = array();
    $result = $db->ExecuteS('SELECT * FROM `' . _DB_PREFIX_ . 'order_bitcoin_jeeb` WHERE `id_order` = "' . $json['orderNo'] . '";');
    error_log("Db Result = ".print_r($result[0],true));
    error_log("Key =".$result[0]['key']);

    $cart_id = (int)$result[0]['cart_id'];
    $order_id = Order::getOrderByCartId($cart_id);
    $order = new Order($order_id);

    // error_log("Order -> ".print_r($order,true));

    // $row = $wpdb->get_results("SELECT * FROM {$table_name} WHERE `order_id` = '".$orderNo."'", ARRAY_A);
    // error_log("Buyer Email : ".$row[0]['buyer_email']);
    // $buyer_email = $row[0]['buyer_email'];

    // Call Jeeb
    if (Configuration::get('jeeb_TESTMODE') == "1")
    {
        $network_uri = "http://test.jeeb.io:9876/";
    }
    else
    {
        $network_uri = "https://jeeb.io/";
    }


    error_log("Entered Jeeb-Notification");
    if ( $json['stateId']== 2 ) {
      error_log('Order Id received = '.$json['orderNo'].' stateId = '.$json['stateId']);
      error_log('Object : '.print_r($json, true));

      $order_status = Configuration::get('JEEB_PENDING');

      error_log("Data : ".$order_status." cart ".$cart_id." amount ".$json['requestAmount']." key ".(string)$result[0]['key'] );

      $jeeb->validateOrder($cart_id, $order_status, $json['requestAmount'], "Jeeb", null, array(), null, false, (string)$result[0]['key']);

    }
    else if ( $json['stateId']== 3 ) {
      error_log('Order Id received = '.$json['orderNo'].' stateId = '.$json['stateId']);
      error_log('Object : '.print_r($json, true));

      $order_status = Configuration::get('JEEB_CONFIRMING');
      $db = Db::getInstance();
      $result = $db->Execute('UPDATE `'._DB_PREFIX_.'orders` SET current_state = '.$order_status.' WHERE `id_cart`='.$cart_id);

      $db = Db::getInstance();
      $result = array();
      $result = $db->ExecuteS('SELECT * FROM `' . _DB_PREFIX_ . 'orders` WHERE `id_cart` = ' . $cart_id . ';');
      error_log("Order Id of presta : ".$result[0]["id_order"]);

      $db = Db::getInstance();
      $result = $db->Execute('UPDATE `'._DB_PREFIX_.'order_history` SET id_order_state = '.$order_status.' WHERE `id_order`='.$result[0]["id_order"]);

    }
    else if ( $json['stateId']== 4 ) {
      error_log('Order Id received = '.$json['orderNo'].' stateId = '.$json['stateId']);
      $data = array(
        "token" => $json["token"]
      );

      $data_string = json_encode($data);
      $api_key = Configuration::get('jeeb_APIKEY');
      $url = $network_uri.'api/bitcoin/confirm/'.$api_key;
      error_log("Signature:".$api_key." Base-Url:".$network_uri." Url:".$url);

      $ch = curl_init($url);
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
      curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
          'Content-Type: application/json',
          'Content-Length: ' . strlen($data_string))
      );

      $result = curl_exec($ch);
      $data = json_decode( $result , true);
      error_log("data = ".var_export($data, TRUE));


      if($data['result']['isConfirmed']){
        error_log('Payment confirmed by jeeb');

        $db = Db::getInstance();
        $result = array();
        $result = $db->ExecuteS('SELECT * FROM `' . _DB_PREFIX_ . 'order_bitcoin_jeeb` WHERE `id_order` = "' . $json['orderNo'] . '";');
        error_log("Db Result = ".print_r($result[0],true));
        error_log("Key =".$result[0]['key']);

        $cart_id = (int)$result[0]['cart_id'];
        $order_status = Configuration::get('PS_OS_PAYMENT');

        $db = Db::getInstance();
        $result = $db->Execute('UPDATE `'._DB_PREFIX_.'orders` SET current_state = '.$order_status.' WHERE `id_cart`='.$cart_id);

        $db = Db::getInstance();
        $result = array();
        $result = $db->ExecuteS('SELECT * FROM `' . _DB_PREFIX_ . 'orders` WHERE `id_cart` = ' . $cart_id . ';');
        error_log("Order Id of presta : ".$result[0]["id_order"]);

        $db = Db::getInstance();
        $result = $db->Execute('UPDATE `'._DB_PREFIX_.'order_history` SET id_order_state = '.$order_status.' WHERE `id_order`='.$result[0]["id_order"]);



      }
      else {
        error_log('Payment confirmation rejected by jeeb');
      }
    }
    else if ( $json['stateId']== 5 ) {
      error_log('Order Id received = '.$json['orderNo'].' stateId = '.$json['stateId']);

      $order_status = Configuration::get('JEEB_EXPIRED');

      $db = Db::getInstance();
      $result = $db->Execute('UPDATE `'._DB_PREFIX_.'orders` SET current_state = '.$order_status.' WHERE `id_cart`='.$cart_id);

      $db = Db::getInstance();
      $result = array();
      $result = $db->ExecuteS('SELECT * FROM `' . _DB_PREFIX_ . 'orders` WHERE `id_cart` = ' . $cart_id . ';');
      error_log("Order Id of presta : ".$result[0]["id_order"]);

      $db = Db::getInstance();
      $result = $db->Execute('UPDATE `'._DB_PREFIX_.'order_history` SET id_order_state = '.$order_status.' WHERE `id_order`='.$result[0]["id_order"]);

    }
    else if ( $json['stateId']== 6 ) {
      error_log('Order Id received = '.$json['orderNo'].' stateId = '.$json['stateId']);

      $order_status = Configuration::get('PS_OS_CANCELED');

      $db = Db::getInstance();
      $result = $db->Execute('UPDATE `'._DB_PREFIX_.'orders` SET current_state = '.$order_status.' WHERE `id_cart`='.$cart_id);

      $db = Db::getInstance();
      $result = array();
      $result = $db->ExecuteS('SELECT * FROM `' . _DB_PREFIX_ . 'orders` WHERE `id_cart` = ' . $cart_id . ';');
      error_log("Order Id of presta : ".$result[0]["id_order"]);

      $db = Db::getInstance();
      $result = $db->Execute('UPDATE `'._DB_PREFIX_.'order_history` SET id_order_state = '.$order_status.' WHERE `id_order`='.$result[0]["id_order"]);

    }
    else if ( $json['stateId']== 7 ) {
      error_log('Order Id received = '.$json['orderNo'].' stateId = '.$json['stateId']);

      $order_status = Configuration::get('PS_OS_CANCELED');

      $db = Db::getInstance();
      $result = $db->Execute('UPDATE `'._DB_PREFIX_.'orders` SET current_state = '.$order_status.' WHERE `id_cart`='.$cart_id);

      $db = Db::getInstance();
      $result = array();
      $result = $db->ExecuteS('SELECT * FROM `' . _DB_PREFIX_ . 'orders` WHERE `id_cart` = ' . $cart_id . ';');
      error_log("Order Id of presta : ".$result[0]["id_order"]);

      $db = Db::getInstance();
      $result = $db->Execute('UPDATE `'._DB_PREFIX_.'order_history` SET id_order_state = '.$order_status.' WHERE `id_order`='.$result[0]["id_order"]);

    }
    else{
      error_log('Cannot read state id sent by Jeeb');
    }
}
}
