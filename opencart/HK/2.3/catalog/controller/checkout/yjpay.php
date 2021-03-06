<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/8/23
 * Time: 11:26
 */
class ControllerCheckoutYjpay extends Controller
{
    public function index(){

        $getData = $this->request->get;
        unset($getData['route']);
        $getString = '&';
        foreach ($getData as $key => $value) {
          $getString .= $key.'='.$value.'&';
        }
        $getString = rtrim($getString,'&');

        if(isset($getData['sign'])){
            $sign = $getData['sign'];
            unset($getData['sign']);
            if( $this->getSign($getData) == $sign ){
                $db = $this->db;
                $order = $this->yjpay_order($db,$getData['merchOrderNo']);
                if($order){
                    if($order['order_status_id'] != $this->config->get('yjpay_success_status')){
                        switch ($getData['status']){
                            case 'success':
                                $this->yjpay_order_update($db,$getData['merchOrderNo'],$this->config->get('yjpay_success_status'));
                                $this->yjpay_order_history($db,$getData['merchOrderNo'],$this->config->get('yjpay_success_status'),'<strong>YJPay:<strong>'.$getData['description']);
                                break;
                            case 'authorizing':
                                $this->yjpay_order_update($db,$getData['merchOrderNo'],$this->config->get('yjpay_authorizing_status'));
                                $this->yjpay_order_history($db,$getData['merchOrderNo'],$this->config->get('yjpay_authorizing_status'),'<strong>YJPay:<strong>'.$getData['description']);
                                break;
                            case 'processing':
                                $this->yjpay_order_update($db,$getData['merchOrderNo'],$this->config->get('yjpay_start_status'));
                                $this->yjpay_order_history($db,$getData['merchOrderNo'],$this->config->get('yjpay_start_status'),'<strong>YJPay:<strong>'.$getData['description']);
                                break;
                            default:
                                $this->yjpay_order_update($db,$getData['merchOrderNo'],$this->config->get('yjpay_fail_status'));
                                $this->yjpay_order_history($db,$getData['merchOrderNo'],$this->config->get('yjpay_fail_status'),'<strong>YJPay:<strong>'.$getData['description']);
                                break;
                        }
                    }

                }else{
                    $failure_url = $this->url->link('checkout/failure', '', 'SSL');
                    $this->response->redirect($failure_url.$getString);
                }

                if($getData['success'] == 'false') {
                    $failure_url = $this->url->link('checkout/failure', '', 'SSL');
                    $this->response->redirect($failure_url.$getString);
                }else{
                    $success_url = $this->url->link('checkout/success', '', 'SSL');
                    $this->response->redirect($success_url.$getString);
                }

            }else{
                echo 'the requset has error !';
            }
        }else{
            echo 'the requset has error !';
        }

}

    private function getSign($data){
        foreach ( $data as $k => $v ){
            if($v == ''){
                unset($data[$k]);
            }
        }
        ksort($data);
        $waitSign = '';
        foreach ($data as $k => $v ){
            $waitSign .= '&'.$k.'='.$v;
        }
        $waitSign = trim($waitSign,'&').trim($this->config->get('yjpay_secretKey'));

        return md5($waitSign);
    }

    private function yjpay_order($db, $orderID) {
        $result = $db->query('SELECT * FROM `' . DB_PREFIX . 'order` WHERE `payment_code` = "yjpay" AND `order_id`=' . intval($orderID));
        return $result->rows ? $result->rows[0] : false;
    }

    private function yjpay_order_update($db, $orderID, $status) {
        $db->query("UPDATE `" . DB_PREFIX . "order` SET order_status_id = " . $status . " WHERE order_id = " . $orderID);
    }

    private function yjpay_order_history($db, $orderID, $statusID, $message) {
        $message = $db->escape($message);
        $date    = date('Y-m-d H:i:s', time());

        $db->query("INSERT INTO `" . DB_PREFIX . "order_history`(`order_id`,`order_status_id`,`notify`,`comment`,`date_added`) VALUES({$orderID},{$statusID},0,'{$message}','{$date}')");
    }

    private function yjpay_setting($db) {
        $setting = array();
        $result  = $db->query("SELECT `key`,`value` FROM `" . DB_PREFIX . "setting` WHERE `key` like 'yjpay_%'");

        foreach ($result->rows as $row) {
            $setting[$row['key']] = $row['value'];
        }

        return $setting;
    }
}
