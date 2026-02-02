<?php

namespace Pay\Controller;

use Think\Controller;
use Think\Log;

/**
 * @author guotian
 * @date   2018-06-06
 */
//获取支付参数控制器
class TestController extends BaseController
{
    /**
     * 初始化控制器
     * BaseController constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    //基本的支付需求信息
    public function testPayOrder()
    {

        //exit('test close');

        $userid = 4;

        $md5key = M('User')->where(['id' => $userid])->getField('apikey');

        //POST参数
        $requestarray = array(
            'amount'      => '1',
            'userid'      => $userid,
            'channelid'   => '264',
            'orderid'     => '2152357856881357122577',
            'notifyurl'   => 'http://192.168.2.9:8085/Pay/Test/notifyurl',
            'noticestr'   => get_random_str(),
        );
        $md5keysignstr = $this->createSign($md5key, $requestarray);
        $requestarray['sign'] = $md5keysignstr;

        var_dump($requestarray);

        $herder[] = 'Content-Type: application/x-www-form-urlencoded; charset=utf-8';
        $curl_result = httpRequestData('http://192.168.2.9:8085/Pay/PayParams.html', http_build_query($requestarray), $herder);

        echo json_encode($curl_result);
    }

    //获取OTC是否可以进行交易
    public function TestGetOTCPayStatus(){

        exit('test close');

        $userid = 4;

        $md5key = M('User')->where(['id' => $userid])->getField('apikey');

        //POST参数
        $requestarray = array(
            'amount'      => '1',
            'userid'      => $userid,
            'channelid'   => '264',
            'noticestr'   => get_random_str(),
        );
        $md5keysignstr = $this->createSign($md5key, $requestarray);
        $requestarray['sign'] = $md5keysignstr;

        var_dump($requestarray);

        $herder[] = 'Content-Type: application/x-www-form-urlencoded; charset=utf-8';
        $curl_result = httpRequestData('http://192.168.2.9:8085/Pay/PayParams/getOTCPayStatus.html', http_build_query($requestarray), $herder);

        echo json_encode($curl_result);
    }

    //自动进行C3C卖出的接口测试
    public function TestAutoSellC2COrder(){

        //exit('test close');

        $userid = 4;

        $md5key = M('User')->where(['id' => $userid])->getField('apikey');

        //POST参数
        $requestarray = array(
            'amount'      => '1',
            'userid'      => $userid,
            'fee'         => 11,
            'orderid'     => '123456',
            'bank'        => '1353',
            'bankcard'    => '1353',
            'truename'    => '1353',
            'notifyurl'   => 'http://192.168.2.9:8085/Pay/Test/notifyurl',
            'noticestr'   => get_random_str(),
        );
        $md5keysignstr = $this->createSign($md5key, $requestarray);
        $requestarray['sign'] = $md5keysignstr;

        var_dump($requestarray);

        $herder[] = 'Content-Type: application/x-www-form-urlencoded; charset=utf-8';
        $curl_result = httpRequestData('http://192.168.2.9:8085/Pay/PayParams/autoSellC2COrder.html', http_build_query($requestarray), $herder);

        echo json_encode($curl_result);
    }


    //获取C2C订单的状态
    public function TestGetOTCC2COrderStatus(){

        exit('test close');

        $userid = 4;

        $md5key = M('User')->where(['id' => $userid])->getField('apikey');

        //POST参数
        $requestarray = array(
            'userid'      => $userid,
            'orderid'     => 'cnc',
            'noticestr'   => get_random_str(),
        );
        $md5keysignstr = $this->createSign($md5key, $requestarray);
        $requestarray['sign'] = $md5keysignstr;

        var_dump($requestarray);

        $herder[] = 'Content-Type: application/x-www-form-urlencoded; charset=utf-8';
        $curl_result = httpRequestData('http://192.168.2.9:8085/Pay/PayParams/getOTCC2COrderStatus.html', http_build_query($requestarray), $herder);

        echo json_encode($curl_result);
    }

    //测试获取用户余额的接口
    public function TestGetOTCUserBalance(){

        exit('test close');

        $userid = 4;

        $md5key = M('User')->where(['id' => $userid])->getField('apikey');

        //POST参数
        $requestarray = array(
            'userid'      => $userid,
            'coin_type'   => 'cnc',
            'noticestr'   => get_random_str(),
        );
        $md5keysignstr = $this->createSign($md5key, $requestarray);
        $requestarray['sign'] = $md5keysignstr;

        var_dump($requestarray);

        $herder[] = 'Content-Type: application/x-www-form-urlencoded; charset=utf-8';
        $curl_result = httpRequestData('http://192.168.2.9:8085/Pay/PayParams/getOTCUserBalance.html', http_build_query($requestarray), $herder);

        echo json_encode($curl_result);
    }
    
    //测试外部信息确认订单接口
    public function TestOTCOrderStatusWithBankSms(){

        exit('test close');

        $userid = 4;

        $md5key = M('User')->where(['id' => $userid])->getField('apikey');

        //POST参数
        $requestarray = array(
            'userid'      => $userid,
            'amount'      => '1',
            'time'        => time(),
            'bankcard'    => '111',
            'noticestr'   => get_random_str(),
        );
        $md5keysignstr = $this->createSign($md5key, $requestarray);
        $requestarray['sign'] = $md5keysignstr;

        //var_dump($requestarray);

        $herder[] = 'Content-Type: application/x-www-form-urlencoded; charset=utf-8';
        $curl_result = httpRequestData('http://192.168.2.9:8085/Pay/PayParams/setOTCOrderStatusWithBankSms.html', http_build_query($requestarray), $herder);

        echo json_encode($curl_result);
    }

    public function notifyurl(){
        echo "notify is OK";
    }
    
    //请求pay数据
    public function requestPayWebData(){

        exit('interface close');
        //$username = 'shijing';
        //$password = 'shijing123';
        //$md5key = 'htcHeKDYGG1358=CDKcung5287widu=';
        //$reques_demain = 'www.starpaycenter.com';
        $DATA_AUTH_KEY = 'b5MV=IsXUK652fbc@u]"l(hdpg$?o0E1NGr4;BP76Wf';
        //POST参数
        $requestarray = array(
            'username'    => $username,
            //'password'    => $password,
            'password'    => md5($password.$DATA_AUTH_KEY),
            'noticestr'   => get_random_str(),
        );
        $md5keysignstr = $this->createSign($md5key, $requestarray);
        $requestarray['sign'] = $md5keysignstr;
        //var_dump($requestarray);

        $herder[] = 'Content-Type: application/x-www-form-urlencoded; charset=utf-8';
        $curl_result = httpRequestData('http://'.$reques_demain.'/Pay_PayParams_getWebNewTradeData.html', http_build_query($requestarray), $herder);

        echo $curl_result;
    }
    
    //请求exchange数据
    public function requestExchangeWebData(){

        exit('interface close');

        $account = '';
        $password = '';
        $md5key = '';
        $reques_demain = '';
        //POST参数
        $requestarray = array(
            'account'     => $account,
            //'password'    => $password,
            'password'    => md5($password),
            'noticestr'   => get_random_str(),
        );
        $md5keysignstr = $this->createSign($md5key, $requestarray);
        $requestarray['sign'] = $md5keysignstr;
        //var_dump($requestarray);

        $herder[] = 'Content-Type: application/x-www-form-urlencoded; charset=utf-8';
        $curl_result = httpRequestData('http://'.$reques_demain.'/Pay/PayOutside/getTodayExchangeInfo.html', http_build_query($requestarray), $herder);

        echo $curl_result;
    }
        
    //展示跳转页面
     public function singleH5QRcode(){
        
        $pay_params['amount'] = '1';
        $pay_params['orderid'] = '122345679';
        
        $mch_id = '2088531358751239';
        $amount = '1';
        
        $pay_url = array();
        $pay_url[0] = 'alipays://platformapi/startapp?appId=20000123&actionType=scan&biz_data={"s":"转账","u":"'.$mch_id.'","a":"'.$amount.'","m":"进行转账"}';
        //$pay_url[0] = 'alipays://platformapi/startapp?appId=20000123&actionType=scan&biz_data={"s":"转账","u":"2088531358751239","a":"10","m":"进行转账-success"}';
        //$pay_url[0] = 'alipays://platformapi/startapp?appId=09999988&actionType=toAccount&goBack=NO&amount='.$amount.'&userId='.$mch_id.'&memo=不要修改金额';
        //$pay_url[0] = 'alipays://platformapi/startapp?appId=09999988&actionType=toAccount&goBack=NO&amount=10&userId=2088531358751239&memo=不要修改金额';
        
        if($pay_url){
            if(is_array($pay_url)){
                $this->assign('pay_url', $pay_url[0]);
                if(count($pay_url) > 1){ //有预备地址
                    $this->assign('pay_url_ready', $pay_url[1]);
                }
            }else{
                $this->assign('pay_url', $pay_url);
            }
        }
        if(!isset($pay_params['receiver'])){
            $pay_params['receiver'] = '星辰支付';
        }
        $this->assign('pay_params', $pay_params);
        $this->assign('orderid', $pay_params['orderid']);
        $this->display('singleH5');

    }
}