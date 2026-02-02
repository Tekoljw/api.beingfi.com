<?php
/**
 * Created by PhpStorm.
 * Date: 2020-2-30
 * Time: 12:00
 */
namespace Pay\Controller;

use Think\Controller;
use Think\Log;

//二维码扫码监控支付的自动通知代码
class PayQrcodeOutAutoNotifyController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    //支付
    public function requestOrder($payParams, $postInfo)
    {
        if($payParams && $postInfo){
            
            //回调参数
            $return = [
                "mch_id"       => $payParams["mch_id"],      //商户号
                "signkey"      => $payParams["signkey"],     //签名密钥
                "appid"        => $payParams["appid"],       //APPID
                "appsecret"    => $payParams["appsecret"],   //APPSECRET
                "domain_record"=> $payParams["domain_record"], //备案域名
                "subject"      => urlencode($payParams['subject']), //商品标题
                "truename"     => $payParams["truename"],    //账户名
            ];
            return $return;
        }else{
            return $this->returnErrorMsg('OTC-PayQrcodeOutAutoNotify requestOrder 请求参数错误payParams');
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
    public function notifyurl($order_info)
    {
        if(is_array($order_info)){

            $res = R("Pay/PayExchange/setPayPlatformOrderStatus", array($order_info));
            if($res === true ){
                $order_info['remarks'] = $order_info['remarks'].'-自动确认';
                $res = $this->confirmC2CSellOrder($order_info);
                if($res === true){
                    return true;
                }else{
                    $this->showmessage('OTC-PayQrcodeOutAutoNotify notifyurl 111 :'.$res['msg']);
                }
            }else{
                $this->showmessage('OTC-PayQrcodeOutAutoNotify notifyurl 2222 :'.$res['msg']);
            }
            
        }else{
            $this->showmessage("OTC-PayQrcodeOutAutoNotify 请求参数不存在！");
        }
    }
}
