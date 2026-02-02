<?php

namespace Pay\Controller;

use Think\Controller;
use Think\Log;

/**
 * @author guotian
 * @date   2018-06-06
 */
//获取RM支付宝类型的自动通知控制器(文档：https://doc.revenuemonster.my/#payment-web-app-checkout)
class PayRmAlipayAutoNotifyController extends BaseController
{
    /**
     * 初始化控制器
     * BaseController constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    //支付
    public function requestOrder($payParams, $postInfo)
    {
        if($payParams && $postInfo){

            //订单已经存在的直接返回
            $return = $this->getExistOrderInfo($payParams);
            if($return){
                return $return;
            }

            $token = $this->requestToken($payParams);
            
            $MYR_RMB = $this->getMYRtoRMB();
            $cur_amount = $postInfo['amount']/$MYR_RMB * 100;
            $MYR = intval($cur_amount);
            if($cur_amount - $MYR >= 0.5){
                $MYR = $MYR + 1;
            }

            $order = [
                'title'         => $payParams['subject'],
                'detail'        => $payParams['subject'],
                'additionalData'=> $payParams['subject'],
                'amount'        => $MYR,
                'currencyType'  => 'MYR',
                'id'            => $payParams['orderid'],
            ];

            $redirectUrl = C('STAR_EXCHANGE_WEB_URL')."/Pay/PayRmAlipayAutoNotify/callbackurl.html?orderid={$payParams['orderid']}";
            $notifyurl = C('STAR_EXCHANGE_WEB_URL').'/Pay/PayRmAlipayAutoNotify/notifyurl.html';

            //请求数据
            $requestdata = [
                'order'         => $order,
                'method'        => ['ALIPAY_CN'], //支付宝
                'type'          => 'WEB_PAYMENT', //当前使用web形式
                'storeId'       => $payParams['mch_id'],
                'redirectUrl'   => $this->escape_url($redirectUrl),
                'notifyUrl'     => $this->escape_url($notifyurl),
                'layoutVersion' => 'v1',
            ];

            //var_dump($order['amount']);

            $url = 'https://open.revenuemonster.my/v3/payment/online';
            $notic_str = get_random_str();
            $time = time();

            $sign = $this->my_create_sign($requestdata, $payParams['appsecret'], $url, $time, $notic_str);
            
            $herder[] = "Content-Type: application/json";
            $herder[] = "Authorization: Bearer {$token}";
            $herder[] = "X-Nonce-Str: {$notic_str}";
            $herder[] = "X-Signature: sha256 {$sign}";
            $herder[] = "X-Timestamp: {$time}";

            //var_dump($herder);

            $result = httpRequestData($url,json_encode($requestdata),$herder);

            if($result && strpos($result, "httpcode") === false){
                $resultArray = json_decode($result, true);
                if($resultArray['code'] == 'SUCCESS'){
                    $item = $resultArray['item'];

                    //请求二维码的相关信息
                    return $this->requestQRInfo($payParams, $token, $item['checkoutId'], $item['url']);
                }else{
                    return $this->returnErrorMsg('PayRmAlipayAutoNotify requestOrder 请求参数错误code = '. $resultArray['code']);
                }
            }else{

                return $this->returnErrorMsg('PayRmAlipayAutoNotify requestOrder 请求参数错误result = '. $result);
            }
        }else{
            return $this->returnErrorMsg('PayRmAlipayAutoNotify requestOrder 请求参数无效');
        }

        return false;
    }

    //同步通知
    public function callbackurl()
    {
        $orderid = $_GET['orderid'];
        $status = $_GET['status'];
        if($orderid){
            $order_info = D('ExchangeOrder')->where(array('orderid' => $orderid))->find();
            if($status == 'SUCCESS'){
                $this->responseCallbackUrl($order_info, '00');
                echo "success";
            }else{

            }
        }
        echo $status;
    }

    //异步通知
    public function notifyurl()
    {
        $response  = $_POST;
        if(!$response){
            $response = file_get_contents("php://input");
        }
        if(is_array($response)){
            //Log::record("PayRmAlipayAutoNotify  notifyurl= ".json_encode($response), Log::INFO);
        }else{
            //Log::record("PayRmAlipayAutoNotify  notifyurl= ".$response, Log::INFO);
        }
        $responseArray = json_decode($response, true);
        if(isset($responseArray['eventType']) && $responseArray['eventType'] == 'PAYMENT_WEB_ONLINE'){
            $response_data = $responseArray['data'];
            if(isset($response_data['status']) && $response_data['status'] == "SUCCESS"){
                $order_data = $response_data['order'];
                $orderid = $order_data['id'];
                $order_info = D('ExchangeOrder')->where(array('orderid' => $orderid))->find();
                $order_info['is_admin'] = true;
                //Log::record("PayRmAlipayAutoNotify  order_info= ".json_encode($order_info), Log::INFO);
                $res = R("Pay/PayExchange/confirmC2COrderWithOrderInfo", array($order_info));
                if ($res === true) {
                    echo "success";
                } else {
                    echo "confirmC2COrder is failed";
                }
            }
        }else{
            echo "failed";
        }
    }

     //订单已经存在的处理
    private function getExistOrderInfo($payParams){

        $orderid = $payParams['orderid'];
        $orderInfo = D('ExchangeOrder')->where(['orderid'=>$orderid])->find();
        if($orderInfo && isset($orderInfo['payurl']) && $orderInfo['payurl'] && $orderInfo['payurl'] != ''){ //订单已经存在

            //回调参数
            $return = [
                "mch_id"       => $payParams["mch_id"],       //商户号
                "signkey"      => $orderInfo['payurl'],       //二维码地址
                "domain_record"=> $orderInfo['order_encode'], //二维码base64串
                "subject"      => urlencode($payParams['subject']), //商品标题
                "truename"     => $payParams["truename"],    //账户名
            ];
            return $return;
        }
        return false;
    }

     //请求对应二维码的信息
    private function requestQRInfo($payParams, $token, $checkout_id, $payurl){

        $url = "https://open.revenuemonster.my/v3/payment/online/qrcode?checkoutId={$checkout_id}&method=ALIPAY_CN";
        $notic_str = get_random_str();
        $time = time();

        $sign = $this->my_create_sign(null, $payParams['appsecret'], $url, $time, $notic_str, 'get');
        
        $herder[] = "Content-Type: application/json";
        $herder[] = "Authorization: Bearer {$token}";
        $herder[] = "X-Nonce-Str: {$notic_str}";
        $herder[] = "X-Signature: sha256 {$sign}";
        $herder[] = "X-Timestamp: {$time}";

        $result = httpRequestData($url,'',$herder, 'get');
        if($result && strpos($result, "httpcode") === false){
            $resultArray = json_decode($result, true);
            if($resultArray['code'] == 'SUCCESS'){
                $item = $resultArray['item'];
                
                $orderid = $payParams['orderid'];
                //回调参数
                $return = [
                    "mch_id"       => $payParams["mch_id"],       //商户号
                    "signkey"      => $payurl,                    //二维码地址
                    "domain_record"=> $item['qrCodeImageBase64'], //二维码base64串
                    "subject"      => urlencode($payParams['subject']), //商品标题
                    "truename"     => $payParams["truename"],    //账户名
                ];

                $res = D('ExchangeOrder')->where(['orderid'=>$orderid])->save(['payurl'=>$payurl,'order_encode'=>$item['qrCodeImageBase64']]);
                if($res){
                    return $return;
                }else{
                    return $this->returnErrorMsg('PayRmAlipayAutoNotify requestQRInfo order-modify信息错误error orderid = '.$orderid);
                }
            }else{
                return $this->returnErrorMsg('PayRmAlipayAutoNotify requestQRInfo 请求参数错误error result = '. $result);
            }
        }else{
            return $this->returnErrorMsg('PayRmAlipayAutoNotify requestQRInfo 请求参数无效invaild result = '.$result);
        }
    }

    //构造签名
    private function my_create_sign($requestdata, $pri_key, $url, $time, $notic_str, $request_method='post'){

        $signdata = array();
        if (is_array($requestdata)) {
            $data = '';
            if (!empty($requestdata)) {
                $this->array_ksort($requestdata);
                $jsonData = json_encode($requestdata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_APOS);
                $data = base64_encode($jsonData);
            }
            $signdata['data'] = $data;
        }

        $signdata['method']     = $request_method;
        $signdata['nonceStr']   = $notic_str;
        $signdata['requestUrl'] = $url;
        $signdata['signType']   = 'sha256';

        //var_dump($signdata);
        $signstr = '';
        foreach ($signdata as $key => $val) {
            if (!empty($val) && $key != 'sign') {
                $signstr = $signstr . $key . "=" . $val . "&";
            }
        }
        $signstr = $signstr . "timestamp=" . $time;

        //var_dump($signstr);
        //sha256签名
        $sign = $this->encrypt_private_SHA256($signstr, $pri_key);

        return $sign;
    }

     //RSA私钥加密
    private function encrypt_private_SHA256($str, $pri_key){

        $privateKey = "-----BEGIN RSA PRIVATE KEY-----\n" .
                wordwrap($pri_key, 64, "\n", true) .
                "\n-----END RSA PRIVATE KEY-----";

        $res = openssl_pkey_get_private($privateKey);
        //echo 'encrypt_private_RSA, pri_key = '.$pri_key;
        //echo 'encrypt_private_RSA, privateKey = '.$privateKey;
        //Log::record('encrypt_private_RSA, pri_key = '.$pri_key.' time ='.date('Y-m-d H:i:s'), Log::INFO);
        //Log::record('encrypt_private_RSA, privateKey = '.$privateKey.' time ='.date('Y-m-d H:i:s'), Log::INFO);
        $signature = '';
        openssl_sign($str, $signature, $res, OPENSSL_ALGO_SHA256);
        openssl_free_key($res);
        $signature = base64_encode($signature);
        return $signature;
    }

    //请求token
    private function requestToken($payParams){

         //请求token
        $tokenCaches = C('TokenCache');
        $TokenCache = $tokenCaches['RevenueMonster'];
        if($TokenCache){
            if(time() - intval($TokenCache['time']) <= 5400){ //小于1个半小时不请求
                //Log::record('OpenUnicomController requestToken', Log::INFO);
                return $TokenCache['token'];
            }
        }

        $url = 'https://oauth.revenuemonster.my/v1/token';

        $tokenParam = "{$payParams['appid']}:{$payParams['signkey']}";
        $AuthorizationParam = base64_encode($tokenParam);
        //echo "payParams:{$tokenParam} ";
        //var_dump($payParams);
        //传递参数
        $params = [
            'grantType' => 'client_credentials',
        ];

        $herder[] = "Content-Type: application/json";
        $herder[] = "Authorization: Basic {$AuthorizationParam}"; //密钥保护头
        $result = httpRequestData($url,json_encode($params),$herder);
        //echo "result:{$result}";
        $resultArray = json_decode($result, true);
        //echo "resultArray:";
        //var_dump($resultArray);
        if(isset($resultArray['accessToken'])){
            $token = $resultArray['accessToken'];
            updateTokenCache('RevenueMonster', $token);
            return $token;
        }
        return false;
    }

    //数组排序
    private function array_ksort(&$array)
    {
        if (count($array) > 0) {
            foreach ($array as $k => $v) {
                if (is_array($v)) {
                    $array[$k] = $this->array_ksort($v);
                } 
            }

            ksort($array);
        }
        return $array;
    }

    //处理url
    private function escape_url($url = '')
    {
        $url = parse_url($url);
        $fulluri = '';
        if (array_key_exists("scheme", $url)) {
            $fulluri = $fulluri.$url["scheme"]."://";
        }
        if (array_key_exists("host", $url)) {
            $fulluri = $fulluri.$url["host"];
        }
        if (array_key_exists("path", $url)) {
            $fulluri = $fulluri.$url["path"];
        }
        if (array_key_exists("query", $url)) {
            $query = urldecode($url["query"]);
            $fulluri = $fulluri."?".urlencode($query);
        }
        // if (array_key_exists("fragment", $url)) {
        //     $fulluri = $fulluri."#".urlencode($url["fragment"]);
        // }   

        return $fulluri;
    }

    //获取当前MYR（马币）的价格
    private function getMYRtoRMB(){

        $rate = $this->getCurrencyExchangeRate('MYR', 'CNY');
        if($rate){
            return $rate;
        }
        return 1.65;
    }
}