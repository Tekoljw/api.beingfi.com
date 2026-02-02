<?php

namespace Pay\Controller;

use Think\Controller;
use Think\Log;

/**
 * @author guotian
 * @date   2024-07-24
 */
//BeingFi访问控制器
class PayBeingFiOrderController extends BaseController
{
    /**
     * 初始化控制器
     * BaseController constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    //正式生产环境
    //处理beingfi虚拟币订单逻辑（http://otc.beingfi.com/Pay/PayBeingFiOrder/productionCryptoChargeOrder.html）
    public function productionCryptoChargeOrder(){
        //正式beingfi平台分配的密钥key
        $BeingFiKey = "I6R7LtApE6UCcAosMjJEVEdQkHE3QQ5IFUH6opSDohuY8cYEu2MLD6YhfNEg9ETDfn3msjuf7pKtWg3ekCurUGyrLedes0CR6yJpWDBSS71uyfLE2C2NqTYT8R6zbVmr";
        $requestData = $this->getRequestParams();
        $sign = $requestData["sign"];
        //Log::record('productionCryptoChargeOrder, doCryptoChargeOrder = '.json_encode($_GET).' time ='.date('Y-m-d H:i:s'), Log::INFO);
        if(!$this->verify($requestData, $BeingFiKey, $sign)){
            $this->showmessage("OTC-平台验签失败!");
        }
        $this->doCryptoChargeOrder();
    }

    //测试环境
    //处理beingfi虚拟币订单逻辑（http://otc.beingfi.com/Pay/PayBeingFiOrder/testCryptoChargeOrder.html）
    public function testCryptoChargeOrder(){
        //测试beingfi平台分配的密钥key
        $BeingFiKey = "5aatkmuia2jytyn0lsnhhqer1n2exvl43jscozrg7jzdddfoh6znuvzr90skc5slftpzmnjd5rhkwj1y1qrcqn7rdg14k6bcezl5q01ib0s63sicje9hb7plnnz52t0t";
        $requestData = $this->getRequestParams();
        $sign = $requestData["sign"];
        //Log::record('testCryptoChargeOrder, doCryptoChargeOrder = '.json_encode($_GET).' time ='.date('Y-m-d H:i:s'), Log::INFO);
        if(!$this->verify($requestData, $BeingFiKey, $sign)){
            $this->showmessage("OTC-平台验签失败!");
        }
        $this->doCryptoChargeOrder();
    }

    //获取请求参数，兼容get和post两种方式
    protected function getRequestParams(){
        if(isset($_GET['sign'])){
            return $_GET;
        }else{
            return $_POST;
        }
    }

    //处理beingfi虚拟币订单逻辑
    protected function doCryptoChargeOrder(){
        $requestData = $this->getRequestParams();
        $mchUserAddress = $requestData["mchUserAddress"];
        $amount = $requestData["amount"];
        $user_info = M('User')->where(['crypto_charge_address' => $mchUserAddress])->find();
        $exchange_price = $this->getBinanceC2CHistoryOrderPrice($user_info);
        if($exchange_price > 0){
            $real_amount = $amount * $exchange_price;
            $userid = $user_info['id'];
            Log::record('doCryptoChargeOrder add amount, userid = '.$userid.',real_amount='.$real_amount .',exchange_price='.$exchange_price.',time ='.date('Y-m-d H:i:s'), Log::INFO);
            // 充值用户账户数据处理
            $result = M('user_coin')->where(array('userid' => $userid))->setInc(Anchor_CNY, $real_amount); // 修改金额
            if(!$result){
                $this->showmessage(L('add amount error coin_type = '.Anchor_CNY));
            }else{
                echo "SUCCESS";
                exit;
            }
        }else{
            $this->showmessage("OTC-获取市场的兑换价格失败!");
        }
    }

    // 获取币安的C2C历史订单价格
    protected function getBinanceC2CHistoryOrderPrice($user_info){

        $url = 'https://api.binance.com/sapi/v1/c2c/orderMatch/listUserOrderHistory';
        $parameters = [
          'tradeType' => 'BUY',
          'page' => 1,
          'rows' => 1,
          'timestamp' => time() * 1000 //时间需要是毫秒
        ];

        if(isset($user_info['third_part_json_params'])){
            $third_part_params_json = json_decode($user_info['third_part_json_params'], true);
            //Log::record('getBinanceC2CHistoryOrderPrice,userid ='.$user_info['id'].',third_part_json_params = '.$user_info['third_part_json_params'].'， time ='.date('Y-m-d H:i:s'), Log::INFO);
            $api_key = $third_part_params_json['api_key'];
            $secret_key = $third_part_params_json['secret_key'];
            $signature = $this->my_create_hmac_sign($parameters, $secret_key);
            $parameters['signature'] = $signature;
            $headers = [
              "X-MBX-APIKEY: {$api_key}"
            ];
            $qs = http_build_query($parameters); // query string encode the parameters
            $get_url = "{$url}?{$qs}"; // create the request URL

            //Log::record('getBinanceC2CHistoryOrderPrice, get_url = '.$get_url.' time ='.date('Y-m-d H:i:s'), Log::INFO);
            $price = $this->requestBinanceC2CHistoryOrderPrice($get_url, $headers);
            if($price){
                return $price;
            }else{
                //暂停1秒在继续执行
                sleep(1);
                //再次请求
                $price = $this->requestBinanceC2CHistoryOrderPrice($get_url, $headers);
            }
            return $price;
        }else{
            return 0;
        }
    }

    //请求币安的C2C历史订单价格
    private function requestBinanceC2CHistoryOrderPrice($get_url, $headers){
        $result = httpRequestData($get_url, '', $headers, 'get', 5);
        $resultArray = json_decode($result, true);
        Log::record('getBinanceC2CHistoryOrderPrice, resultArray = '.$result.' time ='.date('Y-m-d H:i:s'), Log::INFO);
        if($resultArray['message'] == "success"){
            $resultData = $resultArray['data'];
            foreach ($resultData as $key => $v) {
                if(strtoupper($v['fiat']) == strtoupper(Anchor_CNY) && strtoupper($v['orderStatus']) == 'COMPLETED'){
                    return $v['unitPrice'];
                }
            }
        }
        return 0;
    }

    //构造HMAC签名
    private function my_create_hmac_sign($requestdata, $secret_key){
        //ksort($requestdata);
        //var_dump($signdata);
        $signstr = http_build_query($requestdata);

        //var_dump($signstr);
        //hmac256签名
        return hash_hmac('SHA256',$signstr, $secret_key);
    }

    //构造RSA签名
    private function my_create_rsa256_sign($requestdata, $pri_key, $time){

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
        return urlencode($sign);
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

    // 获取币安授权的OAUTH访问token
    private function getBinanceAccessToken($user_info){

        if(isset($user_info['third_part_json_params'])){
            $third_part_json_params = json_decode($user_info['third_part_json_params']);
            $expire_time = $third_part_json_params['expire_time'];
            $refresh_token = $third_part_json_params['refresh_token'];
            $access_token = $third_part_json_params['access_token'];
            $client_id = $third_part_json_params['client_id'];
            $client_secret = $third_part_json_params['client_secret'];
            $cur_time = time();
            if($cur_time > $expire_time){
                $url = 'https://accounts.binance.com/oauth/token';

                $herder[] = "Content-Type: application/json";
                //请求数据
                $request_data = [
                    'client_id'         => $client_id,
                    'client_secret'     => $client_secret, //客户端密钥
                    'grant_type'        => 'refresh_token',
                    'refresh_token'     => $refresh_token //刷新的token
                ];

                //var_dump($herder);
                $result = httpRequestData($url,http_build_query($request_data),$herder);
                $resultArray = json_decode($result, true);
                //echo "resultArray:";
                //var_dump($resultArray);
                if(isset($resultArray['access_token'])){
                    $access_token = $resultArray['access_token'];
                    $third_part_json_params['access_token'] = $access_token;
                    $third_part_json_params['refresh_token'] = $resultArray['refresh_token'];
                    $third_part_json_params['expire_time'] = $cur_time + $resultArray['expires_in'] - 30; //提前30秒
                    $userid = $user_info['userid'];
                    //修改api参数
                    M('user_coin')->where(array('userid' => $userid))->setDec("third_part_json_params", json_encode($third_part_json_params));
                    return $access_token;
                }else{
                    return "";
                }
            }else{
                return $access_token;
            }
        }else{
            return "";
        }
    }
}