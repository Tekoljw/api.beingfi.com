<?php

namespace Pay\Controller;

use Think\Controller;
use Think\Log;

/**
 * @author guotian
 * @date   2020-02-06
 */
//外部访问控制器
class PayAutoPyController extends BaseController
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
    
    // 查询账户数据
    public function getAutoDataWithApp() {
        $info = M('payparams_list')->where(['status' => 1])->select();
        
        $return = [
            'account' => []
        ];
        try {
            $time = time();
            foreach ($info as $payparams) {
                if ($payparams['vmosid']) {
                    $appname = 'Wave';
                    if (strpos($payparams['channelid'], 301) !== false) {
                        $appname = 'Kbz';
                    }
                    $appname .= substr($payparams['mch_id'], -4);
                    
                    // 查询代付数据
                    // $dforder = D('ExchangeOrder')
                    $dforder = M('exchange_order_2026012')
                        ->where([
                            'otype' => 1,
                            'payparams_id' => $payparams['id'],
                            'status' => ['in', [0, 1, 2, 8]],
                            'addtime' => array('between', array($time - (60 * 60 * 24), $time)),
                        ])
                        ->field(['truename', 'bankcard', 'mum', 'orderid'])
                        ->order('id asc')
                        ->select();
                    foreach ($dforder as $key => $val) {
                        $dforder[$key]['payment_bank'] = $payparams['mch_id'];
                        $dforder[$key]['payment_app'] = $appname;
                    }
                    if (isset($return['account'][$payparams['vmosid']])) {
                        array_push($return['account'][$payparams['vmosid']]['payment'], ...$dforder);
                        array_push($return['account'][$payparams['vmosid']]['receive'], $appname);
                    } else {
                        
                        
                        $return['account'][$payparams['vmosid']] = [
                            'payment' => $dforder,
                            'receive' => [$appname]
                        ];
                    }
                }
            }
            $return['noticestr']    = get_random_str();      //随机字符串
            $md5key = M('exchange_config')->where(['id' => 1])->getField('pay_params_otc_key');
            $sign   = $this->createSign($md5key, $return);
            $return['sign'] = $sign;
            $this->successmMessage($return);
        } catch (Exception $e) {
            $this->showmessage($e->getMessage());
        }
    }
    
    // 代付
    public function paySuccessNotify() {
        $orderid = $_POST['orderid'];
        $status = $_POST['status'];
        $remark = $_POST['remark'];
        
        try {
            $order = D('ExchangeOrder')->where(['orderid' => $orderid])->find();
            if (!$order) {
                $this->showmessage('订单不存在');
            }
            
            if ($order['status'] >= 3) {
                $this->showmessage('订单状态异常');
            }
            
            $res = D('ExchangeOrder')->where(['orderid' => $orderid])->save(['status' => $status, 'remark' => $remark]);
            if (!$res) {
                $this->showmessage('系统繁忙');
            }
            $this->successmMessage($return);
        } catch (Exception $e) {
            $this->showmessage($e->getMessage());
        }
        
    }
    
}