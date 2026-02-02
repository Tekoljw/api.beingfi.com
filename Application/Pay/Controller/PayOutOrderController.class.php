<?php

namespace Pay\Controller;

use Think\Controller;
use Think\Log;

/**
 * @author guotian
 * @date   2018-06-06
 */
//获取支付参数控制器
class PayOutOrderController extends BaseController
{
    /**
     * 初始化控制器
     * BaseController constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    //录入外部订单
    public function index()
    {
        $this->checkUserVerify();
        $otype = I("post.otype", 0);
        $userid = I("post.userid", 0);
        $out_order_id = I("post.payOrderId", '');
        $payment_order_id = I("post.mchOrderNo", '');
        $mch_no = I("post.mchNo", '');
        $appid = I("post.appId", '');
        $amount = I("post.amount", 0);
        $amount_actual = I("post.amountActual", 0);
        $mch_fee_amount = I("post.mchFeeAmount", 0);
        $currency = I("post.currency", '');
        $status = I("post.state", 0);
        $clientip = I("post.clientIp", '');
        $created_at = I("post.createdAt", 0);
        $success_time = I("post.successTime", 0);
        $req_time = I("post.reqTime", 0);
        $bank_name = I("post.bankName", '');
        $account_name = I("post.accountName", '');
        $account_no = I("post.accountNo", '');
        $is_otc_order = I("post.isOtcOrder", 0);
        
        // 如果是OTC订单，则不需要同步
        if ($is_otc_order == 1) {
            $return = [
                'mchNo' => $mch_no,
                'orderid' => 'otc order no sync',
                'payOrderId' => $out_order_id,
                'amount' => $amount,
                'status' => $status,
                'currency' => $currency,
                'noticestr' => get_random_str(),
            ];
            $md5key = M('PeAdmin')->where(['id' => $userid])->getField('apikey');
            $sign   = $this->createSign($md5key, $return);
            $return['sign'] = $sign;
            
            $this->successmMessage($return);
        }
        
        $peAdmin = M('PeAdmin')->where(['id' => $userid])->find();
        if (!$peAdmin) {
            $this->showmessage("OTC-商户号错误! amount is error");
        }
        
        // 验证订单类型
        if (!$otype || !in_array($otype, [1, 2])) {
            $this->showmessage("OTC-类型信息不能为空! otype is null");
        }
        
        // 验证币种
        if (!$currency) {
            $this->showmessage("OTC-币种信息不能为空! currency is null");
        }
        
        $applyCurrency = M('currencys')->where(['currency' => strtoupper($currency)])->find();
        if (!$applyCurrency) {
          $this->showmessage("OTC-币种不支持! currency is error");
        }
        
        // 验证金额
        if (!$amount || $amount <= 0) {
            $this->showmessage("OTC-币种金额不能为空! amount is null");
        }
        
        // 存在订单则更新
        $payorderinfo = D('PayOrder')->where(['out_order_id' => $out_order_id, 'mch_no' => $mch_no])->find();
        if ($payorderinfo) {
            if ($payorderinfo['status'] > 2) {
                $this->showmessage("OTC-订单状态不可同步! status is error");
            }
            
            if ($payorderinfo['status'] == 2 && $status != 5) {
                $this->showmessage("OTC-传入状态错误! status is error");
            }
            
            $res = D('PayOrder')
                ->where(['out_order_id' => $out_order_id, 'mch_no' => $mch_no])
                ->save([
                    'status' => $status,
                    'amount_actual' => $amount_actual,
                    'success_time'      => $success_time > 0 ? floor($success_time / 1000) : 0,
                    'req_time'          => $req_time > 0 ? floor($req_time / 1000) : 0,
                    'updatetime' => time()
                ]);
            if ($res) {
                $return = [
                    'mchNo' => $payorderinfo['mch_no'],
                    'orderid' => $payorderinfo['orderid'],
                    'payOrderId' => $payorderinfo['out_order_id'],
                    'amount' => $payorderinfo['amount'],
                    'state' => $status,
                    'currency' => $applyCurrency['currency'],
                    'noticestr' => get_random_str(),
                ];
                
                $md5key = M('PeAdmin')->where(['id' => $userid])->getField('apikey');
                $sign   = $this->createSign($md5key, $return);
                $return['sign'] = $sign;
                $this->successmMessage($return);
            } else {
                $this->showmessage("OTC-订单同步失败! async order is error");
            }
        }
        
        // 验证保证金
        $userCoinMargin = M('user_coin_margin')->where(['userid' => $peAdmin['userid']])->find();
        if (!$userCoinMargin && $otype == 2) {
            $this->showmessage("OTC-商户保证金不足! margin is error-1");
        }
        
        $userCoinSettle = M('user_coin_settle')->where(['userid' => $peAdmin['userid']])->find();
        $margin_money = $userCoinMargin[strtolower($applyCurrency['currency'])] ? $userCoinMargin[strtolower($applyCurrency['currency'])] : 0;
        $cur_money = $userCoinSettle[strtolower($applyCurrency['currency'])] ? $userCoinSettle[strtolower($applyCurrency['currency'])] : 0;
        
        if ($peAdmin['is_system'] != 1 && (($cur_money + $amount) > $margin_money) && $otype == 2) {
            $this->showmessage("OTC-商户保证金不足! margin is error-2");
        }
        
        $orderid = build_exchange_order_no(time());
        $add = [
            'otype'             => $otype,
            'userid'            => $userid,
            'orderid'           => $orderid,
            'out_order_id'      => $out_order_id,
            'payment_order_id'  => $payment_order_id,
            'mch_no'            => $mch_no,
            'appid'             => $appid,
            'amount'            => $amount,
            'amount_actual'     => $amount_actual,
            'mch_fee_amount'    => $mch_fee_amount,
            'currency'          => $applyCurrency['currency'],
            'status'            => $status,
            'remarks'           => '外部订单同步',
            'clientip'          => $clientip,
            'bank_name'         => $bank_name,
            'account_name'      => $account_name,
            'account_no'        => $account_no,
            'clientip'          => $clientip,
            'created_at'        => $created_at > 0 ? floor($created_at / 1000) : 0,
            'success_time'      => $success_time > 0 ? floor($success_time / 1000) : 0,
            'req_time'          => $req_time > 0 ? floor($req_time / 1000) : 0,
            'addtime'           => time(),
        ];
        
        $res = D('PayOrder')->add($add);
        // $sql = M('pay_order_2025111')->fetchSql(true)->add($add);
        // var_dump($sql);exit;
        if ($res) {
            $return = [
                'mchNo' => $mch_no,
                'orderid' => $orderid,
                'payOrderId' => $out_order_id,
                'amount' => $amount,
                'status' => $status,
                'currency' => $applyCurrency['currency'],
                'noticestr' => get_random_str(),
            ];
            
            $md5key = M('PeAdmin')->where(['id' => $userid])->getField('apikey');
            $sign   = $this->createSign($md5key, $return);
            $return['sign'] = $sign;
            $this->successmMessage($return);
        } else {
            $this->showmessage("OTC-订单同步失败! async order is error");
        }
    }
    
    //录入外部订单
    public function test_index()
    {
        $this->checkUserVerify();
        $otype = I("post.otype", 0);
        $userid = I("post.userid", 0);
        $out_order_id = I("post.payOrderId", '');
        $payment_order_id = I("post.mchOrderNo", '');
        $mch_no = I("post.mchNo", '');
        $appid = I("post.appId", '');
        $amount = I("post.amount", 0);
        $amount_actual = I("post.amountActual", 0);
        $mch_fee_amount = I("post.mchFeeAmount", 0);
        $currency = I("post.currency", '');
        $status = I("post.state", 0);
        $clientip = I("post.clientIp", '');
        $created_at = I("post.createdAt", 0);
        $success_time = I("post.successTime", 0);
        $req_time = I("post.reqTime", 0);
        
        
        $peAdmin = M('PeAdmin')->where(['id' => $userid])->find();
        if (!$peAdmin) {
            $this->showmessage("OTC-商户号错误! amount is error");
        }
        
        // 验证订单类型
        if (!$otype || !in_array($otype, [1, 2])) {
            $this->showmessage("OTC-类型信息不能为空! otype is null");
        }
        
        // 验证币种
        if (!$currency) {
            $this->showmessage("OTC-币种信息不能为空! currency is null");
        }
        
        $applyCurrency = M('currencys')->where(['currency' => strtoupper($currency)])->find();
        if (!$applyCurrency) {
          $this->showmessage("OTC-币种不支持! currency is error");
        }
        
        // 验证金额
        if (!$amount || $amount <= 0) {
            $this->showmessage("OTC-币种金额不能为空! amount is null");
        }
        
        // 存在订单则更新
        $payorderinfo = D('PayOrder')->where(['out_order_id' => $out_order_id, 'mch_no' => $mch_no])->find();
        if ($payorderinfo) {
            if ($payorderinfo['status'] > 2) {
                $this->showmessage("OTC-订单状态不可同步! status is error");
            }
            
            if ($payorderinfo['status'] == 2 && $status != 5) {
                $this->showmessage("OTC-传入状态错误! status is error");
            }
            
            $res = D('PayOrder')
                ->where(['out_order_id' => $out_order_id, 'mch_no' => $mch_no])
                ->save([
                    'status' => $status,
                    'amount_actual' => $amount_actual,
                    'updatetime' => time()
                ]);
            if ($res) {
                $return = [
                    'mchNo' => $payorderinfo['mch_no'],
                    'orderid' => $payorderinfo['orderid'],
                    'payOrderId' => $payorderinfo['out_order_id'],
                    'amount' => $payorderinfo['amount'],
                    'state' => $status,
                    'currency' => $applyCurrency['currency'],
                    'noticestr' => get_random_str(),
                ];
                
                $md5key = M('PeAdmin')->where(['id' => $userid])->getField('apikey');
                $sign   = $this->createSign($md5key, $return);
                $return['sign'] = $sign;
                $this->successmMessage($return);
            } else {
                $this->showmessage("OTC-订单同步失败! async order is error");
            }
        }
        
        // 验证保证金
        $userCoinMargin = M('user_coin_margin')->where(['userid' => $peAdmin['userid']])->find();
        if (!$userCoinMargin) {
            $this->showmessage("OTC-商户保证金不足! margin is error");
        }
        
        $userCoinSettle = M('user_coin_settle')->where(['userid' => $peAdmin['userid']])->find();
        $margin_money = $userCoinMargin[strtolower($applyCurrency['currency'])] ? $userCoinMargin[strtolower($applyCurrency['currency'])] : 0;
        $cur_money = $userCoinSettle[strtolower($applyCurrency['currency'])] ? $userCoinSettle[strtolower($applyCurrency['currency'])] : 0;
        
        if (($cur_money + $amount) > $margin_money) {
            $this->showmessage("OTC-商户保证金不足! margin is error");
        }
        
        $orderid = build_exchange_order_no(time());
        $add = [
            'otype'             => $otype,
            'userid'            => $userid,
            'orderid'           => $orderid,
            'out_order_id'      => $out_order_id,
            'payment_order_id'  => $payment_order_id,
            'mch_no'            => $mch_no,
            'appid'             => $appid,
            'amount'            => $amount,
            'amount_actual'     => $amount_actual,
            'mch_fee_amount'    => $mch_fee_amount,
            'currency'          => $applyCurrency['currency'],
            'status'            => $status,
            'remarks'           => '外部订单同步',
            'clientip'          => $clientip,
            'created_at'        => $created_at > 0 ? floor($created_at / 1000) : 0,
            'success_time'      => $success_time > 0 ? floor($success_time / 1000) : 0,
            'req_time'          => $req_time > 0 ? floor($req_time / 1000) : 0,
            'addtime'           => time(),
        ];
        
        $res = D('PayOrder')->add($add);
        // $sql = M('pay_order_2025111')->fetchSql(true)->add($add);
        // var_dump($sql);exit;
        if ($res) {
            $return = [
                'mchNo' => $mch_no,
                'orderid' => $orderid,
                'payOrderId' => $out_order_id,
                'amount' => $amount,
                'status' => $status,
                'currency' => $applyCurrency['currency'],
                'noticestr' => get_random_str(),
            ];
            
            $md5key = M('PeAdmin')->where(['id' => $userid])->getField('apikey');
            $sign   = $this->createSign($md5key, $return);
            $return['sign'] = $sign;
            $this->successmMessage($return);
        } else {
            $this->showmessage("OTC-订单同步失败! async order is error");
        }
    }

    
}