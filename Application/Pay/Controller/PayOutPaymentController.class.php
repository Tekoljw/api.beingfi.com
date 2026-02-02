<?php

namespace Pay\Controller;

use Think\Controller;
use Think\Log;

/**
 * @author guotian
 * @date   2020-02-06
 */
//外部访问控制器
class PayOutPaymentController extends BaseController
{
    /**
     * 初始化控制器
     * BaseController constructor.
     */
    public function __construct()
    {
        parent::__construct();
        
        // $this->checkUserVerify();
    }
    
    // 代收接口
    public function orderadd() {
        $userid = $_POST['mchid'];
        $orderid = $_POST['orderid'];
        $pay_channelid = $_POST['channelid'];
        $amount = $_POST['amount'];
        
        $info = M('Channel')->where(['id' => $pay_channelid, 'status' => 1])->find();
        
        $filePath = APP_PATH . '/' . MODULE_NAME . '/PayController/' . $info['code'] . 'PayController.class.php';
        //是否存在通道文件
        if (!is_file($filePath)) {
            $this->showmessage('支付通道不存在'.$info['code']);
        }
        
        try {
            $config = json_decode($info['payinfo'], true);
            $res = R($info['code'] . '/pay', [$config],'PayController');
            if ($res === false) {
                $this->showmessage('服务器维护中,请稍后再试...');
            }
            
            if ($res['status'] != 1) {
                $this->showmessage($res['msg']);
            }
            $return = [
                'bankName' => $res['data']['bankName'],
                'accountName' => $res['data']['accountName'],
                'bankCard' => $res['data']['bankCard'],
            ];
            $return['noticestr']    = get_random_str();      //随机字符串
            // $md5key = M('User')->where(['id' => $userid])->getField('apikey');
            $md5key = M('PeAdmin')->where(['id' => $userid])->getField('apikey');
            $sign   = $this->createSign($md5key, $return);
            $return['sign'] = $sign;
            $this->successmMessage($return);
        } catch (Exception $e) {
            $this->showmessage($e->getMessage());
        }
    }
    
    // 代付
    public function orderPayment() {
        $userid = $_POST['mchid'];
        $orderid = $_POST['orderid'];
        $amount = $_POST['amount'];
        $pay_channelid = $_POST['pay_channelid'];
        
        $info = M('Channel')->where(['id' => $pay_channelid, 'status' => 1])->find();
        
        $filePath = APP_PATH . '/' . MODULE_NAME . '/PayController/' . $info['code'] . 'PayController.class.php';
        //是否存在通道文件
        if (!is_file($filePath)) {
            $this->showmessage('支付通道不存在'.$info['code'], ['channel' => $_POST['pay_channelid']]);
        }
        
        try {
            $config = json_decode($info['payinfo'], true);
            $res = R($info['code'] . '/payment', [$config],'PayController');
            if ($res === false) {
                $this->showmessage('服务器维护中,请稍后再试...');
            }
            
            if ($res['status'] != 1) {
                $this->showmessage($res['msg']);
            }
            
            $return = array();
            $return['out_order_id'] = $res['data']['orderid'];              //otc平台的对应订单号
            $return['noticestr']    = get_random_str();      //随机字符串
            // $md5key = M('User')->where(['id' => $userid])->getField('apikey');
            $md5key = M('PeAdmin')->where(['id' => $userid])->getField('apikey');
            $sign   = $this->createSign($md5key, $return);
            $return['sign'] = $sign;
            $this->successmMessage($return);
        } catch (Exception $e) {
            $this->showmessage($e->getMessage());
        }
        
    }
    
}