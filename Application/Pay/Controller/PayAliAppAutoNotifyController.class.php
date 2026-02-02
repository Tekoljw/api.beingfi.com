<?php
/**
 * Created by PhpStorm.
 * Date: 2018-10-30
 * Time: 12:00
 */
namespace Pay\Controller;

use Think\Controller;
use Think\Log;

//支付宝APP支付的自动通知代码
class PayAliAppAutoNotifyController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    //支付
    public function requestOrder($payParams, $postInfo)
    {

        if($payParams && $postInfo){
            //---------------------引入支付宝第三方类-----------------
            vendor('Alipay.aop.AopClient');
            vendor('Alipay.aop.SignData');
            vendor('Alipay.aop.request.AlipayTradeAppPayRequest');
            //组装系统参数
            $data = [
                'subject'           => $payParams['subject'],
                'out_trade_no'      => $payParams['orderid'],
                'total_amount'      => sprintf("%.2f", $postInfo['amount']),
                'product_code'      => "QUICK_MSECURITY_PAY",
            ];

            $notifyurl = $this->getPayNotifyUrl($payParams, 'PayAliAppAutoNotify');
            //支付地址
            $payurl = "https://openapi.alipay.com/gateway.do";

            $sysParams               = json_encode($data, JSON_UNESCAPED_UNICODE);
            $aop                     = new \AopClient();
            $aop->gatewayUrl         = $payurl;
            $aop->appId              = $payParams['appid'];
            $aop->rsaPrivateKey      = $payParams['appsecret'];
            $aop->alipayrsaPublicKey = $payParams['signkey'];
            $aop->signType           = 'RSA2';
            $aop->postCharset        = 'UTF-8';
            $aop->format             = 'json';
            $request                 = new \AlipayTradeAppPayRequest();
            $request->setBizContent($sysParams);
            $request->setNotifyUrl($notifyurl);
            $result = $aop->sdkExecute($request);

            //回调参数
            $return = [
                "mch_id"       => $payParams["mch_id"],       //商户号
                "signkey"      => $result,                    //支付宝APP的请求参数
                "domain_record"=> $payParams['domain_record'],//备案网址
                "subject"      => urlencode($payParams['subject']), //商品标题
                "truename"     => $payParams["truename"],    //账户名
            ];
            return $return;

        }else{

            return $this->returnErrorMsg('PayAliAppAutoNotify requestOrder 请求参数错误payParams');
        }
    }


    //同步通知
    public function callbackurl()
    {
        //第四个参数为1时，页面会跳转到订单信息里面的 pay_callbackurl
        //$this->EditMoney($_REQUEST["out_trade_no"], $_REQUEST["total_amount"], 'AliApp', 1); 
        exit("success");
    }

    //异步通知
    public function notifyurl()
    {
        $response  = $_POST;
        $orderid = $response['out_trade_no'];
        $order_info = D('ExchangeOrder')->where(array('orderid' => $orderid))->find();
        if(!empty($order_info)){
            //获取秘钥
            $publiKey = M('payparams_list')->where(['id'=>$order_info['payparams_id']])->getField('signkey');
            //---------------------引入支付宝第三方类-----------------
            vendor('Alipay.aop.AopClient');
            $aop                     = new \AopClient();
            $aop->alipayrsaPublicKey = $publiKey;
            $result = $aop->rsaCheckV1($response, '', 'RSA2');
            //Log::record('支付宝App代码 notifyurl  orderID = '.$response["out_trade_no"].' $response = '.json_encode($response).' $result = '.$result, Log::INFO);

            if ($result) {
                if ($response['trade_status'] == 'TRADE_SUCCESS' || $response['trade_status'] == 'TRADE_FINISHED') {

                    //Log::record('支付宝App代码 支付成功', Log::INFO);
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
            } else {
                Log::record('支付宝App代码 支付验签失败'.$result, Log::INFO);
                exit('error:check sign Fail!');
            }
        }else{
            Log::record('支付宝App代码 获取订单信息失败orderid='.$orderid, Log::INFO);
            exit('error:check sign Fail!');
        }
    }
}
