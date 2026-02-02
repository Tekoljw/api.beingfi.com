<?php
namespace Api\Controller;

class MerchartController extends BaseController
{
    // 获取商户资产
    public function getUserAssets()
    {
        $userid = $this->user['userid'];
        $currencys = M('currencys')->order('id desc')->select();
        
        $result = [
            'available_balance' => 0,
            'freeze_balance' => 0,
            'user_amount' => []
        ];
        $userSettle = M('UserCoinSettle')->where(['userid' => $userid])->find();
	    // 币种及保证金
	    foreach ($currencys as $currency) {
	        $result['user_amount'][$currency['currency']] = [
	            'available' => isset($userSettle[strtolower($currency['currency'])]) ? $userSettle[strtolower($currency['currency'])] : 0,
	            'freeze' => isset($userSettle[strtolower($currency['currency'])."d"]) ? $userSettle[strtolower($currency['currency'])."d"] : 0,
            ];
	    }
	    
	    $this->successJson($result);
    }
    
    // 冻结记录
    public function freezeList()
    {
        $where = [];
	    $where['userid'] = $this->user['id'];
	    
	    $count = M('user_freeze_log')->where($where)->count();
	    $currentPage = $this->inputData['pageNum'] ? $this->inputData['pageNum'] : 1;
        $pageSize = $this->inputData['pageSize'] ? $this->inputData['pageSize'] : 15;
        $total = $count;
        $allPage = ceil($total / $pageSize);
        $offset = ($currentPage - 1) * $pageSize;
		$Page = new \Think\Page($count, $pageSize);
		$show = $Page->show();
		
		$list = M('user_freeze_log')->where($where)->order('id desc')->limit($offset . ',' . $pageSize)->select();
	    $this->successJson([
            'page' => [
                'total' => (int)$total,
                'all_page' => (int)$allPage,
                'current_page' => (int)$currentPage,
                'page_size' => (int)$pageSize,
            ],
            'list' => $list
        ]);
    }
    
    // 结算记录
	public function settleList() {
	    $where = [];
	    $where['is_team'] = 2;
	    $where['user_id'] = $this->user['id'];
	    
	    $count = M('Settles')->where($where)->count();
	    $currentPage = $this->inputData['pageNum'] ? $this->inputData['pageNum'] : 1;
        $pageSize = $this->inputData['pageSize'] ? $this->inputData['pageSize'] : 15;
        $total = $count;
        $allPage = ceil($total / $pageSize);
        $offset = ($currentPage - 1) * $pageSize;
		$Page = new \Think\Page($count, $pageSize);
		$show = $Page->show();
		
		$list = M('Settles')->where($where)->order('id desc')->limit($offset . ',' . $pageSize)->select();
		$result = [];
		foreach ($list as $key => $value) {
		    $result[$key] = $value;
		    $result[$key]['title'] = '结算';
		    
		    $peAdmin = M('PeAdmin')->where(array('id' => $value['user_id']))->find();
		    $result[$key]['merchart_name'] = $peAdmin['nickname'];
		}
		
	    $this->successJson([
            'page' => [
                'total' => (int)$total,
                'all_page' => (int)$allPage,
                'current_page' => (int)$currentPage,
                'page_size' => (int)$pageSize,
            ],
            'list' => $result
        ]);
	}
    
    // 发起商户结算
    public function doSettle()
    {
        if (!$this->inputData['currency'] || !$this->inputData['type'] || !$this->inputData['amount'] || !$this->inputData['address'] ) {
	        $this->errorJson('缺少参数！');
	    }
	    
	    if ($this->inputData['amount'] <= 0) {
	        $this->errorJson('金额错误！');
	    }
	    
	    $currency = M('currencys')->where(['currency' => $this->inputData['currency']])->find();
	    if (!$currency) {
	        $this->errorJson('币种不支持！');
	    }
	    
	    $received_currency = 'USDT';
	    $received_amount = 0;
	    $rate = 1;
	    
	    M()->startTrans();

        try {
            $res = M('Settles')->add([
    	        'is_team' => 2,
    	        'user_id' => $this->user['id'],
    	        'type' => $this->inputData['type'],
    	        'orderid' => date('YmdHis') . rand(100000, 999999),
    	        'currency' => $this->inputData['currency'],
    	        'amount' => $this->inputData['amount'],
    	        'address' => $this->inputData['address'],
    	        'received_currency' => $received_currency,
    	        'received_amount' => $received_amount,
    	        'rate' => $rate,
    	        'addtime' => time(),
            ]);
            
            $userSettle = M('UserCoinSettle')->where(['userid' => $this->user['userid']])->find();
            if (!isset($userSettle[strtolower($currency['currency'])]) || $userSettle[strtolower($currency['currency'])] < $this->inputData['amount']) {
                $this->errorJson('余额不足！');
            }
            $res2 = M('UserCoinSettle')->where(['userid' => $this->user['userid']])->save([
                strtolower($currency['currency']) => $userSettle[strtolower($currency['currency'])] - $this->inputData['amount'],
                strtolower($currency['currency'] . 'd') => $userSettle[strtolower($currency['currency'] . 'd')] + $this->inputData['amount'],
            ]);
            
            $res3 = M('user_freeze_log')->add([
                'userid' => $this->user['id'],
                'settleid' => $res,
                'currency' => $this->inputData['currency'],
                'amount' => $this->inputData['amount'],
                'status' => 1,
                'remark' => '结算申请',
                'addtime' => time()
            ]);
            if ($res && $res2 && $res3) {
                M()->commit();
                $this->successJson();
            } else {
                M()->rollback(); // 回滚事
                $this->errorJson('申请失败！');
            }
        } catch (Exception $e) {
            // 有任何失败，回滚事务
            M()->rollback();
            echo '操作失败: ' . $e->getMessage();
        }
    }
    
    // C2C卖出记录
	public function myApiOrderList()
	{
		$where = array();
        
        if ($this->inputData['otype']) {
            $where['otype'] = (int)$this->inputData['otype']; // 订单类型
        }
        
        // var_dump(D('ExchangeOrder')->where(array(
        //         'orderid' => ,
        //     ))->order('id asc')->find());exit;
            
        if ($this->inputData['orderid']) {
		    $where['orderid'] = $this->inputData['orderid'];
		}
		
		if ($this->inputData['out_order_id']) {
		    $where['out_order_id'] = $this->inputData['out_order_id'];
		}
		
		if ($this->inputData['payment_order_id']) {
		    $where['payment_order_id'] = $this->inputData['payment_order_id'];
		}
		
		if ($this->inputData['pay_channelid']) {
		    $where['pay_channelid'] = $this->inputData['pay_channelid'];
		}
		
	    $where['userid'] = $this->user['userid'];
		
		if (isset($this->inputData['status'])) {
            $where['status'] = (int)$this->inputData['status']; // 订单状态
        }
        
        // 币种
		$currency = isset($this->inputData['currency']) ? $this->inputData['currency'] : '';
		if ($currency) {
		    $currencyData = M('currencys')->where(['currency' => $currency])->find();
		    if (!$currencyData) {
		        $this->errorJson('币种不支持！');
		    }
		    
    		$where['currency'] = $currency;
		}
		
		if ($this->inputData['starttime'] && $this->inputData['endtime']) {
		    $where['addtime'] = ['between',[$this->inputData['starttime'],$this->inputData['endtime']]];
		}
		
		
		//今日开始时间
		$today_start = strtotime(date("Y-m-d 00:00:00"));
		$now_time = time();
		//Log::record('mycz add函数 111111111', Log::INFO);
		
        //今日订单数量
        $today_order_num = D('PayOrder')->where($where)->where(['addtime'=>['between',[$today_start,$now_time]]])->count();
        //待收款订单
        $pending_receive_order =  D('PayOrder')->where($where)->where(array('otype'=>2,'status'=>1))->count();
        //待付款订单
        $pending_payment_order =  D('PayOrder')->where($where)->where(array('otype'=>1,'status'=>1))->count();
        //超时订单
        $time_out_order = D('PayOrder')->where($where)->where(array('status'=>6))->count();
        
		$count = D('PayOrder')->where($where)->count();
	    $currentPage = $this->inputData['pageNum'] ? $this->inputData['pageNum'] : 1;
        $pageSize = $this->inputData['pageSize'] ? $this->inputData['pageSize'] : 15;
        $total = $count;
        $allPage = ceil($total / $pageSize);
        $offset = ($currentPage - 1) * $pageSize;
		$Page = new \Think\Page($count, $pageSize);
		$show = $Page->show();
		
		$list = D('PayOrder')->where($where)->order('id desc')->limit($offset . ',' . $pageSize)->select();

		/** 检查设置条件 **/
        $exchange_config = M('exchange_config')->where(array('id' => 1))->find();
        $config_price = $exchange_config['mytx_uprice'];

		foreach ($list as $k => $v) {
			$matchs ='';
			$aids = '';
			$peAdmin = M('PeAdmin')->where(array('userid' => $v['userid']))->find();
			$list[$k]['username'] = $peAdmin['nickname'];
        	$list[$k]['pay_bank'] = $v['bankcard'];
        	
			if (!isset($v['pay_proof'])) {
			    $list[$k]['pay_proof'] = '';
			}
			
			if (!isset($v['pay_channelid'])) {
			    $list[$k]['pay_channelid'] = 0;
			}
			
			$list[$k]['pay_channel_name'] = '通道';
		}
		
		$exchange_config = M('exchange_config')->where(array('id' => 1))->find();
		
		if ($this->user['role'] == 2) {
	        $user = M('User')->where(['id' => $this->user['userid']])->find();
	        $auto_sell_status = $user['auto_c2c_sell_status'];
	        $auto_buy_status = $user['auto_c2c_buy_status'];
	    } else {
	        $auto_sell_status = $this->user['auto_sell_status'];
	        $auto_buy_status = $this->user['auto_buy_status'];
	    }
		
		$this->successJson([
            'page' => [
                'total' => (int)$total,
                'all_page' => (int)$allPage,
                'current_page' => (int)$currentPage,
                'page_size' => (int)$pageSize,
            ],
            // 'report' => [
            //     'today_all_order' => $today_order_num,
            //     'pending_receive_order' => $pending_receive_order,
            //     'pending_payment_order' => $pending_payment_order,
            //     'time_out_order' => $time_out_order,
            //     'auto_sell_status' => $auto_sell_status,
            //     'auto_buy_status' => $auto_buy_status,
            // ],
            'list' => $list
        ]);
	}
	
	public function exportApiOrderList()
	{
		$where = array();
        
        if ($this->inputData['otype']) {
            $where['otype'] = (int)$this->inputData['otype']; // 订单类型
        }
        
        // var_dump(D('ExchangeOrder')->where(array(
        //         'orderid' => ,
        //     ))->order('id asc')->find());exit;
            
        if ($this->inputData['orderid']) {
		    $where['orderid'] = $this->inputData['orderid'];
		}
		
		if ($this->inputData['out_order_id']) {
		    $where['out_order_id'] = $this->inputData['out_order_id'];
		}
		
		if ($this->inputData['payment_order_id']) {
		    $where['payment_order_id'] = $this->inputData['payment_order_id'];
		}
		
		if ($this->inputData['pay_channelid']) {
		    $where['pay_channelid'] = $this->inputData['pay_channelid'];
		}
		
	    $where['userid'] = $this->user['userid'];
		
		if ($this->inputData['status']) {
            $where['status'] = (int)$this->inputData['status']; // 订单状态
        }
        
        // 币种
		$currency = isset($this->inputData['currency']) ? $this->inputData['currency'] : '';
		if ($currency) {
		    $currencyData = M('currencys')->where(['currency' => $currency])->find();
		    if (!$currencyData) {
		        $this->errorJson('币种不支持！');
		    }
		    
    		$where['currency'] = $currency;
		}
		
		if (!$this->inputData['starttime'] || !$this->inputData['endtime']) {
		    $this->errorJson('请选择时间范围！');
		}
		$where['addtime'] = ['between',[$this->inputData['starttime'],$this->inputData['endtime']]];
		
		//今日开始时间
		$today_start = strtotime(date("Y-m-d 00:00:00"));
		$now_time = time();
		//Log::record('mycz add函数 111111111', Log::INFO);
		
        //今日订单数量
        $today_order_num = D('PayOrder')->where($where)->where(['addtime'=>['between',[$today_start,$now_time]]])->count();
        //待收款订单
        $pending_receive_order =  D('PayOrder')->where($where)->where(array('otype'=>2,'status'=>1))->count();
        //待付款订单
        $pending_payment_order =  D('PayOrder')->where($where)->where(array('otype'=>1,'status'=>1))->count();
        //超时订单
        $time_out_order = D('PayOrder')->where($where)->where(array('status'=>6))->count();
        
		$count = D('PayOrder')->where($where)->count();
	    $currentPage = $this->inputData['pageNum'] ? $this->inputData['pageNum'] : 1;
        $pageSize = $this->inputData['pageSize'] ? $this->inputData['pageSize'] : 15;
        $total = $count;
        $allPage = ceil($total / $pageSize);
        $offset = ($currentPage - 1) * $pageSize;
		$Page = new \Think\Page($count, $pageSize);
		$show = $Page->show();
		
		$list = D('PayOrder')->where($where)->order('id desc')->select();

		/** 检查设置条件 **/
        $exchange_config = M('exchange_config')->where(array('id' => 1))->find();
        $config_price = $exchange_config['mytx_uprice'];

		foreach ($list as $k => $v) {
			$matchs ='';
			$aids = '';
			$peAdmin = M('PeAdmin')->where(array('userid' => $v['userid']))->find();
			$list[$k]['username'] = $peAdmin['nickname'];
        	$list[$k]['pay_bank'] = $v['bankcard'];
        	
			if (!isset($v['pay_proof'])) {
			    $list[$k]['pay_proof'] = '';
			}
			
			if (!isset($v['pay_channelid'])) {
			    $list[$k]['pay_channelid'] = 0;
			}
			
			$list[$k]['pay_channel_name'] = '通道';
		}
		
		$exchange_config = M('exchange_config')->where(array('id' => 1))->find();
		
		if ($this->user['role'] == 2) {
	        $user = M('User')->where(['id' => $this->user['userid']])->find();
	        $auto_sell_status = $user['auto_c2c_sell_status'];
	        $auto_buy_status = $user['auto_c2c_buy_status'];
	    } else {
	        $auto_sell_status = $this->user['auto_sell_status'];
	        $auto_buy_status = $this->user['auto_buy_status'];
	    }
		
		$this->successJson($list);
	}
	
	public function commissionList () {
	    $where = [];
	    $time = $this->inputData['time'];
        
        $curtime = time();
	    if ($time == 'day') {
	        $starttime = strtotime(date('Y-m-d'));
	        $where['addtime'] = ['between', [$starttime, $curtime]];
	    } else if ($time == 'week') {
	        $starttime = strtotime('monday this week');
	        $where['addtime'] = ['between', [$starttime, $curtime]];
	    } else if ($time == 'month') {
	        $starttime = strtotime(date('Y-m-01'));
	        $where['addtime'] = ['between', [$starttime, $curtime]];
	    }
	    $where['user_id'] = $this->user['id'];
	    
	    $count = M('user_commission_log')->where($where)->count();
	    $currentPage = $this->inputData['pageNum'] ? $this->inputData['pageNum'] : 1;
        $pageSize = $this->inputData['pageSize'] ? $this->inputData['pageSize'] : 15;
        $total = $count;
        $allPage = ceil($total / $pageSize);
        $offset = ($currentPage - 1) * $pageSize;
		$Page = new \Think\Page($count, $pageSize);
		$show = $Page->show();
		
		$list = M('user_commission_log')->where($where)->order('id desc')->limit($offset . ',' . $pageSize)->select();
		$result = [];
		foreach ($list as $key => $value) {
		    $result[$key] = $value;
		    $orderInfo = D('PayOrder')->where(['orderid' => $value['orderid']])->find();
		    
		    $result[$key]['merchart_name'] = '';
		    $result[$key]['order_amount'] = '';
		    if ($orderInfo) {
		        $result[$key]['merchart_name'] = M('PeAdmin')->where(['userid' => $orderInfo['userid']])->find()['nickname'];
		        $result[$key]['order_amount'] = $orderInfo['amount'];
		    }
		}
		
	    $this->successJson([
            'page' => [
                'total' => (int)$total,
                'all_page' => (int)$allPage,
                'current_page' => (int)$currentPage,
                'page_size' => (int)$pageSize,
            ],
            'report' => [
                'total' => 0,
                'settle' => 0,
                'settle_pending' => 0,
            ],
            'list' => $result
        ]);
	}

}
?>