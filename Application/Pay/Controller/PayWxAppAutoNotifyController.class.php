<?php

namespace Pay\Controller;

use Think\Controller;
use Think\Log;
use Org\Util\WxAppPay;

//微信APP支付的自动通知代码
class PayWxAppAutoNotifyController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function requestOrder($payParams, $postInfo)
    {

        if($payParams && $postInfo){

             Log::record('微信App代码：开始', Log::INFO);
            //通知地址
            $notifyurl = C('STAR_EXCHANGE_WEB_URL').'/Pay/PayWxAppAutoNotify/notifyurl.html';

            $get_url = $return['paygateway'].'/Pay_'.$return['code'].'?pay_orderid='.$return['orderid'];
            //var_dump($get_url);
            $headers = array('Content-Type: application/x-www-form-urlencoded; charset=utf-8');
            $result = httpRequestData($get_url, '', $headers, 'get', 5);
            if($result){
                $result = json_decode($result, true);
                if($result['status'] == 'success'){
                    //返回订单信息
                    $this->showReturnPayOrderInfo($result['data']);
                }else{
                    $this->showmessage($result['msg']);
                }
            }else{
                $this->showmessage('微信APP支付生成订单信息失败！');
            } 
        }  
    }
	
    public function callbackurl()
    {
        $this->EditMoney($_REQUEST['out_trade_no'], 0, 'WxApp', 1);
    }

    // 服务器点对点返回
    public function notifyurl()
    {

        $arrayData = $_REQUEST;
        //Log::record('微信H5代码 notifyurl：开始 arrayData = '.json_encode($arrayData), Log::INFO);

        if($arrayData['return_code'] != 'SUCCESS') exit('trade fail');

        $order_info = D('ExchangeOrder')->where(array('orderid' => $orderid))->find();
        if(empty($order_info)){
            $this->showmessage("订单不存在");
        }
        if($order_info['status'] > 2) {
            $this->showmessage("订单已支付");
        }  
        $publiKey = M('payparams_list')->where(['id'=>$order_info['payparams_id']])->getField('appsecret');
        if(!$appsecret){
            $this->showmessage("支付app密钥不存在");
        }
        //Log::record('微信H5 notifyurl：开始 orderID = '.$arrayData["out_trade_no"], Log::INFO);

        //去除sign
        $data_sign = $arrayData['sign'];
        unset($arrayData['sign']);

        $notifyurl = C('STAR_EXCHANGE_WEB_URL').'/Pay/PayWxAppAutoNotify/notifyurl.html';
        $wxwapPay = new WxAppPay($order["account"], $order["mch_id"], $notifyurl, $appsecret);
        $sign = $wxwapPay->MakeSign($arrayData);
        if(strtolower($sign) != strtolower($data_sign)){

            Log::record('微信App notifyurl：签名验证失败 sign= '.$sign.' arrayData-sign= '.$data_sign, Log::INFO);
            exit('签名验证失败');
        }else{

            $order_info['is_admin'] = true;
            //Log::record("PayRmAlipayAutoNotify  order_info= ".json_encode($order_info), Log::INFO);
            $res = R("Pay/PayExchange/confirmC2COrderWithOrderInfo", array($order_info));
            if ($res === true) {
                echo "success";
            } else {
                echo "confirmC2COrder is failed";
            }
            exit("success");
        }
    }
}

?>
