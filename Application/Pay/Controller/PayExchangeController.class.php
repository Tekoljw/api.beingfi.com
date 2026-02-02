<?php

namespace Pay\Controller;

use Think\Controller;
use Think\Log;

/**
 * @author guotian
 * @date   2018-06-06
 */
//获取C2C-Exchange控制器
class PayExchangeController extends BaseController
{
    /**
     * 初始化控制器
     * BaseController constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    // C2C订单确认(APP监听接口操作)
    public function orderQuerenBk()
    {
        $this->checkPlatformVerify();
        $clientIP = get_client_ip();
        
        $ipList = explode(',', M('exchange_config')->where(['id' => 1])->getField('payment_submit_ip'));
        if (!in_array($clientIP, $ipList)) {
            $this->showmessage("禁止访问!");
        }
        
        $returnOrderID  = I("post.returnOrderID");
        $bankcard       = I("post.bankcard");
        $amount         = I("post.amount");
        $opUserID       = I("post.opUserID");
        $paypassword    = I("post.paypassword");
        $date           = I("post.date");
    	if(checkstr($returnOrderID) || checkstr($bankcard) || checkstr($paypassword) || checkstr($opUserID) || checkstr($amount)){
    		$this->showmessage("信息有误!");
    	}
    	
    	if (empty($date)) {
            $this->showmessage("信息有误!");
        }
    
    	if (!check($paypassword, 'password')) {
    	    $this->showmessage("密码格式为6~16位，不含特殊符号!");
    	}
    
    	$user_paypassword = M('User')->where(array('id' => $opUserID))->getField('paypassword');
    	if (md5($paypassword) != $user_paypassword) {
    		if(!empty($user_paypassword)){
    		    $this->showmessage("交易密码错误!");
    		}else{
    		    $this->showmessage("还未设置交易密码，请前往账户中心设置!");
    		}
    	}
    	
    	$paymentInfo = M('exchange_payment_record')->where([
    	    'return_order_id'   => $returnOrderID,
	        'real_amount'       => $amount,
	        'bankcard'          => $bankcard,
	       // 'date'              => $date,
	    ])->find();
	    
	    if ($paymentInfo && $date == $paymentInfo['date']) {
            $this->showmessage("请勿重复提交!");
	    }
	    
	    $time = time();
	    $res = M('exchange_payment_record')->add([
	        'userid'            => $opUserID,
	        'bankcard'          => $bankcard,
	        'real_amount'       => $amount,
	        'return_order_id'   => $returnOrderID,
	        'date'              => $date,
	        'ip'                => $clientIP,
	        'addtime'           => $time,
        ]);
        
        if ($res) {
            return $this->successmMessage();
        }
        
        $this->showmessage("系统繁忙!");
    }
    
    public function orderQueren()
    {
        $this->checkPlatformVerify();
        $clientIP = get_client_ip();
        
        $returnOrderID  = I("post.returnOrderID");
        $bankcard       = I("post.bankcard");
        $amount         = I("post.amount");
        $opUserID       = I("post.opUserID");
        $paypassword    = I("post.paypassword");
        $currency       = I("post.currency");
        $date           = I("post.date");
    	if(checkstr($returnOrderID) || checkstr($bankcard) || checkstr($paypassword) || checkstr($opUserID) || checkstr($amount)){
    		$this->showmessage("信息有误!");
    	}
    	
    	if (empty($date) || !strtotime($date)) {
            $this->showmessage("信息有误!");
        }
        
        if (!check($paypassword, 'password')) {
    	   // $this->showmessage("密码格式为6~16位，不含特殊符号!");
    	}
        
        $peAdmin = M('pe_admin')->where(['id' => $opUserID])->find();
        if (!$peAdmin) {
            $this->showmessage("业务员不存在!");
        }
        
        
        $fPeAdmin = M('pe_admin')->where(['id' => $peAdmin['fid']])->find();
        $ipList = explode(',', $fPeAdmin['monitor_ip']);
        if (!in_array($clientIP, $ipList)) {
            // $this->showmessage("禁止访问!您的IP为：" . $clientIP . ' - ' . $fPeAdmin['monitor_ip']);
            // $this->showmessage("禁止访问!您的IP为：" . $clientIP);
        }
        
        $opUserID = $peAdmin['userid'];
    
    	$user_paypassword = M('User')->where(array('id' => $opUserID))->getField('paypassword');
    // 	if (md5($paypassword) != $user_paypassword) {
    // 		if(!empty($user_paypassword)){
    // 		    $this->showmessage("交易密码错误!");
    // 		}else{
    // 		    $this->showmessage("还未设置交易密码，请前往账户中心设置!");
    // 		}
    // 	}
    	
    	$paymentInfo = M('exchange_payment_record')->where([
    	    'return_order_id'   => $returnOrderID,
	        'real_amount'       => $amount,
	        'bankcard'          => $bankcard,
	       // 'date'              => $date,
	    ])->find();
	    
	    if ($paymentInfo && $date == $paymentInfo['date']) {
            $this->showmessage("请勿重复提交!");
	    }
	    
	    $time = time();
	    $insert = [
	        'userid'            => $opUserID,
	        'bankcard'          => $bankcard,
	        'real_amount'       => $amount,
	        'return_order_id'   => $returnOrderID,
	        'date'              => $date,
	        'currency'          => "MMK",
	        'ip'                => $clientIP,
	        'addtime'           => $time,
        ];
        if ($currency) {
            switch ($currency) {
                case 'THB':
                    // 将UTC+7 转 UTC+8
                    $datetime = new \DateTime($date, new \DateTimeZone('Asia/Bangkok'));
                    $datetime->setTimezone(new \DateTimeZone('Asia/Shanghai'));
                    $insert['date'] = $datetime->format('Y-m-d H:i:s');
                    break;
            }
            $insert['currency'] = $currency;
        }
        
        Log::record("orderQueren  data= ".json_encode($insert) . 'currency = ' . $currency, Log::INFO);
	    $res = M('exchange_payment_record')->add($insert);
        
        if ($res) {
            return $this->successmMessage();
        }
        
        $this->showmessage("系统繁忙!");
    }
    
    // 处理已核对订单
    public function autoExchangePaymentNotify() {
        //删除掉已经核对超过1周的订单
        $time = time();
        $delRes = M('exchange_payment_record')->where([
	        'status' => 1,
	        'dealtime' => array('lt', $time - (86400 * 7))
        ])->delete();
        
        $paymentList = M('exchange_payment_record')
        // ->where('id=4120')
        ->where([
	        'status' => 0,
	        'transfer_status' => 0,
	       // 'addtime' => array('gt', $time - 86400)
        ])->select();
        
        $success = 0;
        $error = 0;
        $transfer = 0;
        foreach ($paymentList as $key => $value) {
            if ($value['status'] == 1) {
                $success++;
                continue;
            }
            
            if ($value['transfer_status'] == 1) {
                continue;
            }
            
            $currCurrency = $value['currency'] ? $value['currency'] : 'MMK';
            switch ($currCurrency) {
                case 'MMK':
                    $orderInfo = D('ExchangeOrder')->where(array(
                        'bankcard'          => $value['bankcard'],
                        'mum'               => $value['real_amount'],
                        'return_order_id'   => $value['return_order_id'],
                    ))->order('id asc')->find();
                    break;
                
                case 'THB':
                    $etime = strtotime($value['date']);
                    $stime = $etime - 300;
                    $where = [
                        'real_amount' => $value['real_amount'],
                        'type' => 'thb',
                        'addtime' => array('between', array($stime, $etime)),
                        '_string' => $this->buildBankcardCondition($value['bankcard'])
                    ];
                    
                    $orderInfo = D('ExchangeOrder')
                    // $orderInfo = M('exchange_order_2025104')
                        // ->fetchSql(true)
                        ->where($where)
                        ->order('id asc')
                        ->find();
                    break;
                default:
                    // code...
                    break;
            }
            // echo "<pre>";
            if ($orderInfo) {
                $orderid = $orderInfo['orderid'];
                // 订单已完成
                if ($orderInfo['status'] == 3) {
                    M('exchange_payment_record')->where([
        	            'id' => $value['id']
                    ])->save([
                        'status'    => 1,
                        'dealtime'  => $time,
                    ]);
                    
                    $success++;
                    continue;
                }
                
                // 订单已完成已取消
                if ($orderInfo['status'] == 8) {
                    $orderInfo['status'] = 2;
                    $this->modifyOrderInfo($orderid, array('status' => 2,'updatetime' => time()));
                    // $success++;
                    // continue;
                }
                
                $successOrderInfo = false;
                switch ($currCurrency) {
                    case 'MMK':
                        $successOrderInfo = D('ExchangeOrder')->where(array(
                            'bankcard'          => $value['bankcard'],
                            'mum'               => $value['real_amount'],
                            'return_order_id'   => $value['return_order_id'],
                            'status'            => 3,
                        ))->find();
                        break;
                    
                    case 'THB':
                        $etime = strtotime($value['date']);
                        $stime = $etime - 300;
                        $successwhere = [
                            'real_amount' => $value['real_amount'],
                            'type' => 'thb',
                            'status' => 3,
                            'addtime' => array('between', array($stime, $etime)),
                            '_string' => $this->buildBankcardCondition($value['bankcard'])
                        ];
                        
                        $successOrderInfo = D('ExchangeOrder')
                            ->where($successwhere)
                            ->order('id asc')
                            ->find();
                        break;
                }
                
                // 如果已经有人工处理过的订单，则不再处理
                if ($successOrderInfo) {
                    M('exchange_payment_record')->where([
        	            'id' => $value['id']
                    ])->save([
                        'status'    => 1,
                        'dealtime'  => $time,
                    ]);
                    
                    $success++;
                    continue;
                }
                
                // 先验证保证金 (代收需要验证保证金)
                if($orderInfo['otype'] == 2){
                    $sell_userid = $orderInfo['userid'];
                    $coin_type   = $orderInfo['type'];
                    $num         = isset($orderInfo['num'])?$orderInfo['num']:0;
                    $real_amount = (isset($orderInfo['real_amount']) && $order_info['real_amount'] > 0)?$orderInfo['real_amount']:$num;
                    
                    // 对应的后台账号
                    $peAdmin = M('PeAdmin')->where(array('userid' => $sell_userid))->find();
                    $fPeAdmin = M('PeAdmin')->where(['id' => $peAdmin['fid']])->find();
                    
                    if ($peAdmin) {
                        // 对应保证金
                        $user_coin_margin = M('user_coin_margin')->where(array('userid' => $sell_userid))->find();
                        $fuser_coin_margin = M('user_coin_margin')->where(array('userid' => $fPeAdmin['userid']))->find();
                        
                        $user_coin_settle = M('user_coin_settle')->where(array('userid' => $sell_userid))->find();
                        $fuser_coin_settle = M('user_coin_settle')->where(array('userid' => $fPeAdmin['userid']))->find();
                        
                        if ($user_coin_margin[$coin_type] < ($real_amount + $user_coin_settle[$coin_type])) {
                            // OTC-卖家用户保证金余额不足！
                            $error++;
                            continue;
                        }
                        
                        if ($fuser_coin_margin[$coin_type] < ($real_amount + $fuser_coin_settle[$coin_type])) {
                            // OTC-卖家用户上级代理保证金余额不足
                            $error++;
                            continue;
                        }
                    }
                }
                
                // 处理逻辑
                if($orderInfo['status'] < 2){
                    //改变为处理中
                    $this->modifyOrderInfo($orderid, array('status' => 2,'updatetime' => time()));
                }
                
                //设置支付平台的订单状态
                $res = $this->setPayPlatformOrderStatus($orderInfo);
                if($res === true ){
                    if($orderInfo['otype'] == 2){
                        $orderInfo['remarks'] = $orderInfo['remarks'].'-平台自动确认';
                        $orderInfo['status'] = 2;
                        $res = $this->confirmC2CSellOrder($orderInfo);
                        // var_dump($res);exit;
                        if ($res === true) {
                            M('exchange_payment_record')->where([
                	            'id' => $value['id']
                            ])->save([
                                'status'    => 1,
                                'dealtime'  => $time,
                            ]);
                            $success++;
                        } else {
                            $error++;
                        }
                    }else{
                        $res = $this->confirmC2CBuyOrder($orderInfo);
                        if ($res === true) {
                            M('exchange_payment_record')->where([
                	            'id' => $value['id']
                            ])->save([
                                'status'    => 1,
                                'dealtime'  => $time,
                            ]);
                            $success++;
                        } else {
                            $error++;
                        }
                    }
                }else{
                    $error++;
                }
            } else {
                if (($time - $value['addtime']) >= 180) { // 超出10分钟未处理的订单转移至人工处理
                    switch ($value['currency']) {
                        case 'MMK':
                            $payparamsInfo = M('payparams_list')->where([
                    	            'mch_id' => $value['bankcard']
                                ])->order('id desc')->find();
                                
                            $orderid = $value['return_order_id'];
                            $return_order_id = $value['return_order_id'];
                            $out_order_id = 'WT' . $return_order_id;
                            break;
                        
                        // 泰国
                        case 'THB':
                            $regex_pattern = '^' . str_replace('x', '.', $value['bankcard']) . '$';
                            $payparamsInfo = M('payparams_list')
                                ->where("mch_id REGEXP '%s'", $regex_pattern)
                                ->order('id desc')->find();
                                
                            $orderid = $value['bankcard'] . '-' . $value['real_amount'];
                            $return_order_id = $value['bankcard'] . '-' . $value['id'];
                            $out_order_id = 'WT' . $return_order_id;
                            M('exchange_payment_record')->where([
                	            'id' => $value['id']
                            ])->save([
                                'return_order_id'    => $return_order_id
                            ]);
                            break;
                    }
                    
                    $addData = [
                        'otype' => 2,
                        'orderid' => $orderid,
                        'out_order_id' => $out_order_id,
                        'remarks' => 'remark',
                        'userid' => $value['userid'],
                        'uprice' => '1',
                        'num' => $value['real_amount'],
                        'mum' => $value['real_amount'],
                        'real_amount' => $value['real_amount'],
                        'all_scale' => '0',
                        'scale_amount' => '0',
                        'type' => strtolower($value['currency']),
                        'aid' => $value['userid'],
                        'fee' => '0',
                        'truename' => $payparamsInfo['truename'],
                        'bank' => $payparamsInfo['truename'],
                        'bankprov' => $payparamsInfo['subject'],
                        'bankcity' => $payparamsInfo['subject'],
                        'bankaddr' => $payparamsInfo['subject'],
                        'bankcard' => $value['bankcard'],
                        'addtime' => $time,
                        'endtime' => '0',
                        'updatetime' => '0',
                        'overtime' => '0',
                        'status' => '99',
                        'notifyurl' => 'baidu.com',
                        'callbackurl' => 'baidu.com',
                        'payurl' => 'baidu.com',
                        'pay_channelid' => $payparamsInfo['channelid'],
                        'payparams_id' => $payparamsInfo['id'],
                        'repost_num' => '0',
                        'rush_status' => '0',
                        'punishment_level' => '0',
                        'order_encode' => '',
                        'return_order_id' => $return_order_id,
                        'pay_proof' => '',
                    ];
                    
                    $D = D('ExchangeOrder');
                    $table = $D->getCurExhcangeOrderTableName();
                    $addRes = M()->table($table)->add($addData);
                    if ($addRes) {
                        M('exchange_payment_record')->where([
            	            'id' => $value['id']
                        ])->save([
                            'transfer_status'    => 1,
                        ]);
                        
                        $transfer++;
                    }
                }
            }
        }
        
        echo '总查询待处理数据：' . count($paymentList) . '条，成功：' . $success . '条，失败：' . $error . '条，转至人为操作: ' . $transfer . '条';
    }
    
    //外部设置C2C订单的成功状态
    public function confirmC2COrderWithOutside(){
        //平台验证签名
        $this->checkPlatformVerify();

        //设置订单状态
        $res = $this->confirmC2COrderWithOrderInfo($orderInfo);

        if ($res === true) {
            $this->successmMessage('操作成功！');
        } else {
            $this->showmessage($res['msg']?$res['msg']:'操作失败！');
        }
    }
    
    // 外部设置C2C订单的成功状态(API调用)
    public function confirmC2COrderWithApiOrderInfo($orderInfo, $bankcard = '') {
        // 先验证保证金 (代收需要验证保证金)
        // if($orderInfo['otype'] == 2){
        //     $sell_userid = $orderInfo['userid'];
        //     $coin_type   = $orderInfo['type'];
        //     $num         = isset($orderInfo['num'])?$orderInfo['num']:0;
        //     $real_amount = (isset($orderInfo['real_amount']) && $order_info['real_amount'] > 0)?$orderInfo['real_amount']:$num;
            
        //     // 对应的后台账号
        //     $peAdmin = M('PeAdmin')->where(array('userid' => $sell_userid))->find();
        //     $fPeAdmin = M('PeAdmin')->where(['id' => $peAdmin['fid']])->find();
            
        //     if ($peAdmin) {
        //         // 对应保证金
        //         $user_coin_margin = M('user_coin_margin')->where(array('userid' => $sell_userid))->find();
        //         $fuser_coin_margin = M('user_coin_margin')->where(array('userid' => $fPeAdmin['userid']))->find();
                
        //         $user_coin_settle = M('user_coin_settle')->where(array('userid' => $sell_userid))->find();
        //         $fuser_coin_settle = M('user_coin_settle')->where(array('userid' => $fPeAdmin['userid']))->find();
                
        //         if ($user_coin_margin[$coin_type] < ($real_amount + $user_coin_settle[$coin_type])) {
        //             return $this->returnErrorMsg('OTC-卖家用户'. $coin_type . '保证金余额不足！ user amount is too little');
        //         }
                
        //         if ($fuser_coin_margin[$coin_type] < ($real_amount + $fuser_coin_settle[$coin_type])) {
        //             return $this->returnErrorMsg('OTC-卖家用户上级代理'. $coin_type . '保证金余额不足！ user amount is too little');
        //         }
        //     }
        // }
        //设置支付平台的订单状态
        $res = $this->setPayPlatformOrderStatus($orderInfo);
        if($res === true ){
            if($orderInfo['otype'] == 2){
                $orderInfo['remarks'] = $orderInfo['remarks'].'-手动确认';
                return $this->confirmC2CSellOrder($orderInfo);
            }else{
                $where = [
                    'userid' => $orderInfo['userid'],
                    'mch_id' => $bankcard,
                    'status' => 1,
                    'check_status' => 1,
                    '_string' => "FIND_IN_SET('{$orderInfo['pay_channelid']}', channelid) > 0",
                ];
                $payparams = M('payparams_list')->where($where)->find();
                
                if (!$payparams) {
                    return $this->returnErrorMsg('请输入该通道下的真实付款账户');
                }
                
                if (($payparams['amount'] - $orderInfo['mum']) < 0) {
                    return $this->returnErrorMsg('账户余额不足');
                }
                
                $res = $this->confirmC2CBuyOrder($orderInfo);
                
                if ($res === true) {
                    $res = M('payparams_list')->where(['id' => $payparams['id']])->setDec('amount', $orderInfo['mum']);
                    if ($res) {
                        D('ExchangeOrder')->where(array(
                            'orderid' => $orderInfo['orderid'],
                        ))->save(['payparams_id' => $payparams['id']]);
                        
                        return true;
                    } else {
                        return false;
                    }
                } else {
                    return $res;
                }
            }
        }else{
            return $this->returnErrorMsg('OTC-商户平台修改状态操作失败！msg ='.$res['msg']);
        }
    }

    //通过订单信息来确定订单
    public function confirmC2COrderWithOrderInfo($orderInfo){

        if(!empty($orderInfo)){
            //进行操作的用户id
            $op_userid = isset($orderInfo['op_userid'])?$orderInfo['op_userid']:0;
            //是否后台操作
            $is_admin = isset($orderInfo['is_admin'])?$orderInfo['is_admin']:0;
            $orderid = $orderInfo['orderid'];
            if($op_userid || $is_admin){

                if($orderInfo['status'] < 2){
                    //改变为处理中
                    $this->modifyOrderInfo($orderid, array('status' => 2,'updatetime' => time()));
                }

                if(!$is_admin && isBuyUserOpreate($orderInfo, $op_userid)){
                    //买入方确认，不能最终确认订单。添加记录后返回
                    if($orderInfo['otype'] == 1 && $orderInfo['userid'] == $op_userid){
                        if(!isAutoC2COrder($orderInfo)){ //自动买入订单，只要买入方确认付款，就直接成功
                            $sell_userid = $orderInfo['aid'];
                            return true; 
                        }
                    }
                    elseif($orderInfo['otype'] == 2 && $orderInfo['aid'] == $op_userid){
                        return true;
                    }
                }

                //设置支付平台的订单状态
                $res = $this->setPayPlatformOrderStatus($orderInfo);
                if($res === true ){
                    if($orderInfo['otype'] == 2){
                        $orderInfo['remarks'] = $orderInfo['remarks'].'-手动确认';
                        return $this->confirmC2CSellOrder($orderInfo);
                    }else{
                        return $this->confirmC2CBuyOrder($orderInfo);
                    }
                }else{
                    return $this->returnErrorMsg('OTC-商户平台修改状态操作失败！msg ='.$res['msg']);
                }
            }else{
                return $this->returnErrorMsg('OTC-没有传递操作userid,操作失败！');
            }
        }else{
            return $this->returnErrorMsg('OTC-没有传递订单信息orderinfo,操作失败！');
        }
    }

    //重新确认订单完成
    public function resetConfirmC2COrderWithOrderInfo($orderInfo){
        if($orderInfo){
            if($orderInfo['status'] == 8){
                if($orderInfo['otype'] == 2){
                    //设置支付平台的订单状态
                    $res = $this->setPayPlatformOrderStatus($orderInfo);
                    if($res === true ){
                        return $this->resetConfirmC2CSellOrder($orderInfo);
                    }else{
                        return $this->returnErrorMsg('OTC-重置商户平台修改状态操作失败！msg ='.$res['msg']);
                    }
                }else{
                    // 买入订单暂时不提供重置
                    return $this->returnErrorMsg('OTC-买入订单暂时不提供重置！');
                }
            }else{
                return $this->returnErrorMsg('OTC-该订单必须为取消订单,操作失败！');
            }
        }else{
            return $this->returnErrorMsg('OTC-没有传递订单数据,操作失败！');
        }
    }

    //通过订单信息来取消订单
    public function cancelC2COrderWithOrderInfo($orderInfo){

        if($orderInfo){
            //非超级后台账户操作需要不是完成订单，而超级后台账户可以直接操作
            if(($orderInfo['status'] < 3) || (isset($order_info['is_admin']) && $order_info['is_admin'])){
                if($orderInfo['otype'] == 2){
                    return $this->cancelC2CSellOrder($orderInfo);
                }else{
                    if(isAutoC2COrder($orderInfo)){
                        //设置支付平台的订单状态
                        $res = $this->setPayPlatformOrderStatus($orderInfo, 3);
                    }
                    return $this->cancelC2CBuyOrder($orderInfo);
                }
            }else{
                return $this->returnErrorMsg('OTC-该订单已经完成交易,操作失败！');
            }
        }else{
            return $this->returnErrorMsg('OTC-没有传递订单数据,操作失败！');
        }
    }

    //取消超时的C2C订单
    public function cancelOverTimeC2COrder($limit = 0){
        return $this->autoCancelC2CSellOrderOvertime(0, $limit);
    }

    //给C2C订单重新制定买家
    public function resetC2COrderBuyUser($orderInfo, $new_buy_userid){
        if(is_array($orderInfo) && isAutoC2COrder($orderInfo)){
            if(isset($new_buy_userid) && $new_buy_userid > 0){
                if($orderInfo['status'] == 1){
                    if($orderInfo['otype'] == 1){

                        $buy_userid = $orderInfo['userid'];
                        $sell_userid = $orderInfo['aid'];
                        if($new_buy_userid != $sell_userid){

                            if($new_buy_userid != $buy_userid){
                                $where = "id = {$new_buy_userid}  AND status = 1 AND kyc_lv = 2 AND idstate = 2 AND cancal_c2c_level = 1";
                                $buy_userid = M('user')->where($where)->getField('id');
                                if(isset($buy_userid) && $buy_userid > 0){
                                    $resetData = array();
                                    $resetData['status'] = 1;
                                    $resetData['punishment_level'] = 0;
                                    $resetData['updatetime'] = time();
                                    $resetData['userid'] = $buy_userid;
                                    if($this->modifyOrderInfo($orderInfo['orderid'], $resetData)){
                                        return true;
                                    }else{
                                        return $this->returnErrorMsg("OTC-修改订单数据失败！");
                                    }
                                }else{
                                    return $this->returnErrorMsg("OTC-本次指定的买家状态不符合要求,操作失败！");
                                }
                            }else{
                                return $this->returnErrorMsg("OTC-本次指定的买家和该订单上次买家相同buy_userid={$buy_userid},操作失败！");
                            }
                         }else{
                            return $this->returnErrorMsg("OTC-本次指定的买家和该订单的卖家相同sell_userid={$sell_userid},操作失败！");
                         }
                    }else{
                        return $this->returnErrorMsg('OTC-暂不提供卖出订单的重新指定买家,操作失败！');
                    }
                }else{
                    return $this->returnErrorMsg('OTC-只有接单但是没有确认的订单才能重新指定买家,操作失败！');
                }
            }else{
                return $this->returnErrorMsg('OTC-本次指定的买家ID不可用,操作失败！');
            }
        }else{
            return $this->returnErrorMsg('OTC-需要处理的订单信息不存在或为非自动,操作失败！');
        }
    }

    //自动重新激活C2C订单
    public function autoResetAvtiveC2COrder($orderInfo){
        if(is_array($orderInfo) && isAutoC2COrder($orderInfo)){
            if($orderInfo['status'] == 3){
                if($orderInfo['otype'] == 1){
                    $buy_userid = $orderInfo['userid'];
                    $sell_userid = $orderInfo['aid'];

                    $avtiveData = array();
                    $avtiveData['status'] = 1;
                    $avtiveData['updatetime'] = time();
                    //不能卖出用户和上一次匹配的买入用户
                    $where = "auto_c2c_buy_status = 1 AND status = 1 AND kyc_lv = 2 AND idstate = 2 AND cancal_c2c_level = 1 AND id != {$sell_userid} AND id != {$buy_userid}";
                    $buy_userid = M('user')->where($where)->order('rand()')->getField('id');
                    if(!$buy_userid){
                        $avtiveData['userid'] = $buy_userid;
                    }
                     return $this->modifyOrderInfo($orderInfo['orderid'], $avtiveData);
                }else{
                    return $this->returnErrorMsg('OTC-暂不提供卖出订单的重新激活,操作失败！');
                }
            }else{
                return $this->returnErrorMsg('OTC-只有完成状态的订单才能重新激活,操作失败！');
            }
        }else{
            return $this->returnErrorMsg('OTC-需要处理的订单信息不存在或为非自动,操作失败！');
        }
    }
    //自动切换订单的用户
    public function autoExchangeC2COrderUser($orderInfo){
        if(is_array($orderInfo) && isAutoC2COrder($orderInfo)){
            if($orderInfo['status'] < 2){
                if($orderInfo['status'] > 0){
                    if($orderInfo['otype'] == 1){
                        $limit_time = time() - 2*60*60;
                        //if($orderInfo['addtime'] > $limit_time){ //超过2小时设置为失败订单
                            $buy_userid = $orderInfo['userid'];
                            $sell_userid = $orderInfo['aid'];
                            //不能卖出用户和上一次匹配的买入用户
                            $where = "auto_c2c_buy_status = 1 AND status = 1 AND kyc_lv = 2 AND idstate = 2 AND cancal_c2c_level = 1 AND id != {$sell_userid} AND id != {$buy_userid}";
                            $buy_userid = M('user')->where($where)->order('rand()')->getField('id');
                            if(!$buy_userid){
                                return $this->returnErrorMsg('OTC-重新选择新的买入用户失败,没有其他符合的用户!');
                            }else{
                                $exchangeData = array();
                                $exchangeData['userid'] = $buy_userid;
                                $exchangeData['updatetime'] = time();
                                return $this->modifyOrderInfo($orderInfo['orderid'], $exchangeData);
                            }
                        // }else{
                        //     $this->cancelC2COrderWithOrderInfo($orderInfo);
                        // }
                    }else{
                        return $this->returnErrorMsg('OTC-暂不提供卖出订单的切换用户,操作失败！');
                    }
                }else{
                    return $this->returnErrorMsg('OTC-该订单为未接单状态不需重置,操作失败！');
                }
            }else{
                return $this->returnErrorMsg('OTC-该订单为完成状态,操作失败！');
            }
        }else{
            return $this->returnErrorMsg('OTC-需要处理的订单信息不存在或为非自动,操作失败！');
        }
    }

    //重置订单到市场
    public function resetC2COrderToMarket($orderInfo){
        if(is_array($orderInfo)){
            if($orderInfo['status'] < 3){
                if($orderInfo['status'] > 0){
                    if($orderInfo['otype'] == 1){
                        // $resetData = array();
                        // $resetData['aid'] = 0;
                        // $resetData['status'] = 0;
                        // $resetData['pay_channelid'] = 0;
                        // $resetData['payparams_id']  = 0;
                        // $resetData['truename']      = '';
                        // $resetData['bank']          = '';
                        // $resetData['bankcard']      = '';
                        // $resetData['bankaddr']      = '';
                        // $resetData['bankprov']      = '';
                        // $resetData['bankcity']      = '';
                        // $resetData['updatetime'] = time();
                        // D('ExchangeOrder')->where(array('orderid' => $orderid))->save($resetData);
                        return $this->returnErrorMsg('OTC-暂不提供买入订单的重回市场,操作失败！');
                    }else{
                        $resetData = array();
                        $resetData['aid'] = 0;
                        $resetData['status'] = 0;
                        $resetData['updatetime'] = time();
                        return $this->modifyOrderInfo($orderInfo['orderid'], $resetData);
                    }
                }else{
                    return $this->returnErrorMsg('OTC-该订单为未接单状态不需重置,操作失败！');
                }
            }else{
                return $this->returnErrorMsg('OTC-该订单为完成状态,操作失败！');
            }
        }else{
            return $this->returnErrorMsg('OTC-需要处理的订单信息不存在,操作失败！');
        }
    }


    //设置支付平台的订单状态(notify_status:2表示成功，3表示失败)
    public function setPayPlatformOrderStatus($order_info, $notify_status=2){

        //Log::record("setPayPlatformOrderStatus  userid= ".$order_info['userid'], Log::INFO);
        if(is_array($order_info)){
            
            $notifyurl = isset($order_info['notifyurl'])?$order_info['notifyurl']:false;
            //卖出订单才需要通知，且存在通知地址
            if($notifyurl && is_string($notifyurl)){

                $amount         = $order_info['mum'];
                $out_order_id   = $order_info['out_order_id'];
                $userid         = $order_info['aid']; //卖出订单通知买家
                $notify_status  = isset($notify_status)?$notify_status:0; //通知的支付订单状态

                if(!$out_order_id){
                     return $this->returnErrorMsg('OTC-订单没有设置out_order_id');
                }
                if(!$userid){
                    return $this->returnErrorMsg('OTC-订单没有设置userid');
                }
                if($notify_status){

                    if($notify_status == 2){ //成功状态
                        //设置风控状态
                        // $rs = $this->savePayTypeOfflineStatus($order_info);
                        // if(!$rs){
                        //     return $this->returnErrorMsg('设置账户类型风控状态失败');
                        // }
                        $rs = $this->savePayParamsOfflineStatus($order_info);
                        if(!$rs){
                            Log::record("OTC-setPayPlatformOrderStatus  orderid= ".$order_info['orderid'].'设置账户参数风控状态失败', Log::INFO);
                        }

                        $rs = $this->saveUserControlStatus($order_info);
                        if(!$rs){
                            Log::record("OTC-saveUserControlStatus  orderid= ".$order_info['orderid'].'设置用户风控状态失败', Log::INFO);
                        }
                    }

                    //发送请求通知商户的订单状态
                    $result = $this->requestMemberOrderStatus($order_info, $notify_status);
                    if($result){
                        return true;
                    }
                }
            }
            return true;
        }
        return false;
    }

    //通知商户平台的订单状态
    public function requestMemberOrderStatus($order_info, $notify_status){

        if($order_info){

            $amount         = (isset($order_info['real_amount']) && $order_info['real_amount'] > 0) ?$order_info['real_amount']:$order_info['mum'];
            $out_order_id   = $order_info['out_order_id'];
            $remarks        = $order_info['remarks'];
            $userid         = $order_info['aid'];
            $notifyurl      = $order_info['notifyurl'];
            $orderid        = $order_info['orderid'];
            
            if(!$notifyurl || !is_string($notifyurl)){
                return false;
            }
            // $Md5OtcKey = M('User')->where(['id' => $userid])->getField('apikey');
            $agentid = M('PeAdmin')->where(['userid' => $order_info['userid']])->getField('fid');
            $Md5OtcKey = M('PeAdmin')->where(['id' => $agentid])->getField('apikey');
            //发送数据
            $data = [
                'amount'    => $amount,
                'orderid'   => $out_order_id,
                'userid'    => $userid,
                'status'    => $notify_status, //通知的支付订单状态(1失败，2成功)
                'remarks'   => $remarks,        //订单备注
                'noticestr' => get_random_str(),
            ];
            $data['sign'] = $this->createSign($Md5OtcKey,$data);
            
            //发送请求
            $result = $this->requestNotifyUrl($notifyurl,$data);
            if(strpos(strtolower($result), "ok") !== false){//通知成功

                $this->modifyOrderInfo($order_info['orderid'], array('repost_num'=>0, 'real_amount'=>$amount));
                return true;
            }else{
                $repost_num = $order_info['repost_num'] + 1;
                $this->modifyOrderInfo($order_info['orderid'], array('repost_num'=>$repost_num, 'real_amount'=>$amount));
            }
        }
        return false;
    }

     //获取C2C订单的状态
    public function getOTCC2COrderStatus($userid, $orderid){
        $status = 0;
        if(isset($userid) && isset($orderid)){
            $where = "(userid = {$userid} OR aid = {$userid}) AND out_order_id = '{$orderid}'";
            $status = D('ExchangeOrder')->where($where)->getField('status');
            if(empty($status)){
                $status = -1;
            }elseif($status == 8){
                $status = 4;
            }
        }
        return $status;
    }

    //获取用户的货币数量
    public function getOTCUseridBalance($userid, $coin_type){
        $balance = 0;
        if(isset($userid) && isset($coin_type)){
            $balance = M('user_coin')->where(['userid'=>$userid])->getField($coin_type);
        }
        return $balance;
    }

    //通过外部订单号来确定订单
    protected function confirmCNCSellOrderWithOutOrderId($out_order_id){

        $orderInfo = D('ExchangeOrder')->where(array('out_order_id' => $out_order_id, 'otype'=>2))->find();

        return $this->confirmC2CSellOrder($orderInfo);
    }

    //通过外部订单号来取消订单
    protected function cancelCNCSellOrderWithOutOrderId($out_order_id){

        $orderInfo = D('ExchangeOrder')->where(array('out_order_id' => $out_order_id, 'otype'=>2))->find();

        return $this->cancelC2CSellOrder($orderInfo);
    }

    //修改支付类型风控状态
    protected function savePayTypeOfflineStatus($order_info)
    {
        if(!empty($order_info)) {
            $channelid  = $order_info['pay_channelid'];
            $amount     = $order_info['mum'];
            $addtime    = $order_info['addtime'];
            $info = M('paytype_config')->where(['channelid' => $channelid])->find();
            if ($info['offline_status'] && $info['control_status']) {
                $data = array();
                //通道是否开启风控和支付状态为上线
                $data['paying_money']     = bcadd($info['paying_money'], $amount, 2);

                if ($info['all_money'] > 0 && $data['paying_money'] >= $info['all_money']) {
                    $data['offline_status'] = 0;
                    $data['updatetime']     = time();
		            $this->SendTelegramPayTypeMsg($info);
                }
                //判断是否订单跨天了
                $now_date = date('Ymd');
                $addtime  = date('Ymd', $addtime);
                if($now_date > $addtime){
                    $data['paying_money']   = 0.00;
                    $data['offline_status'] = 1;
                }
                if(empty($data)){
                    return true;
                }
                return M('paytype_config')->where(['id' => $id])->save($data);
            }
        }
        return true;
    }

    //修改支付参数风控状态
    protected function savePayParamsOfflineStatus($order_info)
    {
        if(!empty($order_info)) {
            $id         = $order_info['payparams_id'];
            $amount     = $order_info['mum'];
            $addtime    = $order_info['addtime'];

            $info = M('payparams_list')->where(['id' => $id])->find();
            if(!empty($info) && $info['status'] && $amount > 0){

                $data = array();
                //最大失败次数修正
                $data['max_fail_count'] = 0;

                if ($info['offline_status'] && $info['control_status']) {
                    //通道是否开启风控和支付状态为上线
                    $data['paying_money']   = bcadd($info['paying_money'], $amount, 2);
                    $data['paying_num']     = $info['paying_num'] + 1;
                    if ($info['all_money'] > 0 && $data['paying_money'] >= $info['all_money']) {
                        $data['offline_status'] = 0;
                        $data['updatetime']     = time();
			            $this->SendTelegramPayParamsMsg($info);
                    }
                }

                //判断是否订单跨天了
                if($addtime > 0){
                    $now_date = date('Ymd');
                    $addtime  = date('Ymd', $addtime);
                    if($now_date > $addtime){
                        $paying_money   = $info['paying_money'];
                        $max_money      = $info['max_money'];
                        if($max_money > 0){
                            if($paying_money < $max_money){
                                $low_money_count = $info['low_money_count'] + 1;
                            }else{
                                $low_money_count = 0;
                            }
                        }
                        $data['paying_money']   = 0.00;
                        $data['paying_num']     = 0;
                        $data['offline_status'] = 1;
                        $data['low_money_count']= $low_money_count;
                    }
                }   
                $data['last_paying_time'] = time();
                if(empty($data)){
                    return true;
                }
                //Log::record("savePayParamsOfflineStatus  id = ".$id." data= ".json_encode($data), Log::INFO);
                return M('payparams_list')->where(['id' => $id])->save($data);
            }
        }
        return true;
    }

    //修改用户风控状态
    protected function saveUserControlStatus($order_info)
    {
        if(!empty($order_info)) {
            $amount = $order_info['mum'];
            $userid = $order_info['userid'];
            if($amount > 0){
                $user_info = M('user')->where(['id' => $userid])->find();
                if(!empty($user_info) && isset($user_info['paying_money'])) {
                    $data = array();
                    $data['paying_money'] = $user_info['paying_money'] + $amount;
                    return M('user')->where(['id' => $userid])->save($data);
                }
            }
        }
        return true;
    }

    //具体操作订单数据
    protected function modifyOrderInfo($orderid, $data){
        if(!empty($data)){
            return D('ExchangeOrder')->where(array('orderid' => $orderid))->save($data);
        }else{
            return false;
        }
    }

    //发送支付类型的tg信息
    protected function SendTelegramPayTypeMsg($payType_info){
        if(is_array($payType_info)){
            $telegramMsgText = '支付类型:'.$payType_info['channelid'].',风控导致下线'.',时间:'.date('Y-m-d H:i:s');
            $this->SendTelegramMsg($telegramMsgText);
        }else{
            Log::record("SendTelegramMsg = 支付参数信息错误，为空", Log::INFO);
        }
    }

    //发送支付参数的tg信息
    protected function SendTelegramPayParamsMsg($payparams_info){
        if(is_array($payparams_info)){
            $telegramMsgText = '支付参数:'.$payparams_info['id'].',风控导致下线,属于用户:'.$payparams_info['userid'].',渠道ID:'.$payparams_info['channelid'].',时间:'.date('Y-m-d H:i:s');
            $this->SendTelegramMsg($telegramMsgText);
        }else{
            Log::record("SendTelegramMsg = 支付参数信息错误，为空", Log::INFO);
        }
    }

    //发送信息到telegram
    protected function SendTelegramMsg($sendText){
	    $telegramBotToken = "6997846994:AAE0NDe6hJ8yakIT-Ws0-G3h7ui_KWR-fxI";
        $url = "https://api.telegram.org/bot".$telegramBotToken.'/sendMessage';
        $chatId = "-4094531931";
        $get_url =  $url.'?chat_id='.$chatId.'&text='.$sendText;
        //var_dump($get_url);
        $headers = array('Content-Type: application/x-www-form-urlencoded; charset=utf-8');
        $result = httpRequestData($get_url, '', $headers, 'get', 5);
        if($result){
            Log::record("SendTelegramMsg = 提交信息返回:". $result, Log::INFO);
        }else{
            //$this->showmessage('提交信息失败！');
            Log::record("SendTelegramMsg = 提交信息失败", Log::INFO);
        }
    }
    
    /**
     * 构建银行卡号查询条件
     * @param string $pattern 模式字符串，如 'xxx-x-x8692-x'
     * @return string SQL条件表达式
     */
    private function buildBankcardCondition($pattern) {
        $parts = explode('-', $pattern);
        $conditions = array();
        
        foreach ($parts as $index => $part) {
            $field = "SUBSTRING_INDEX(SUBSTRING_INDEX(bankcard, '-', " . ($index + 1) . "), '-', -1)";
            
            if ($part === 'xxx') {
                // 三位数字
                $conditions[] = "{$field} REGEXP '^[0-9]{3}$'";
            } elseif ($part === 'x') {
                // 一位数字
                $conditions[] = "{$field} REGEXP '^[0-9]{1}$'";
            } elseif (strpos($part, 'x') === 0 && strlen($part) > 1) {
                // 以x开头，如 x8692 - 表示以特定数字结尾
                $number = substr($part, 1);
                $conditions[] = "{$field} LIKE '%{$number}'";
            } elseif (strpos($part, 'x') !== false) {
                // 包含x，使用正则表达式
                $regex = '^' . str_replace('x', '[0-9]', $part) . '$';
                $conditions[] = "{$field} REGEXP '{$regex}'";
            } else {
                // 精确匹配
                $conditions[] = "{$field} = '{$part}'";
            }
        }
        
        return empty($conditions) ? '' : implode(' AND ', $conditions);
    }
}