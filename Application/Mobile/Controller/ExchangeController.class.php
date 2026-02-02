<?php
/* 应用 - C2C交易 */
namespace Mobile\Controller;

class ExchangeController extends MobileController
{
	protected function _initialize(){
		parent::_initialize();

		$allow_action=array("index","upTrade","upMytx","orderQueren","addExchangeOrder","showOrderQueen","cancelOrder","bank","payinfo","bankadd","autoSellCNCstatus","autoBuyCNCstatus","payPersonalParams","payOrganizationParams","editPersonalParams","editOrganizationParams","editControl","upPayParams","upControlEdit","delPayParams","saveQrcode","addPersonalCode","upPersonalCode","setPayParamsStatus","checkPayParams","rushOrder","resetOrderQueen","checkNotice");

		if(!in_array(ACTION_NAME,$allow_action)){
			$this->error(L("非法操作！"));
		}
	}
	
	public function index($order_mode = 0)
	{
		if(checkstr($order_mode)){
			$this->error(L('您输入的信息有误！'));
		}

		if (!userid()) {
			$this->assign('logins', 0);
		} else {
			$this->assign('logins', 1);
			$this->assign('checkNotice', 1);
		}
		
		$userid = userid();

		// 搜索实名认证信息
		$user = M('user')->where(array('id' => $userid ))->find();
		if ($user['kyc_lv'] == 1) {
			if ($user['idstate'] == 2) {
				$this->assign('idcard', 1);
			} else {
				$this->assign('idcard', 0);
			}
		} else if ($user['kyc_lv'] == 2) {
			$this->assign('idcard', 1);
		}

		$this->assign('auto_c2c_sell_status', $user['auto_c2c_sell_status']);
		$this->assign('auto_c2c_buy_status', $user['auto_c2c_buy_status']);
		
		// 搜索银行卡信息
		$user_accounts = M('payparams_list')->where(['userid'=>$userid, 'status'=>1,'check_status'=>1,'is_manual_account'=>1])->find();
		if (!($user_accounts)) {
			$this->assign('user_accounts', 0);
		} else {
			$this->assign('user_accounts', 1);
		}
		
		// C2C配置信息
		$configs = M('exchange_config')->where(array('id' => 1))->find();
		$this->assign('configs', $configs);

		//订单的显示模式
		$this->assign('order_mode', $order_mode);
		switch ($order_mode) {
			case '0': //买入市场展示买入订单
				$where['otype']  = 1; //买入订单
				$where['status'] = 0; 
				break;
			case '1': //卖出市场展示卖出订单
				$where['otype']  = 2; //卖出订单
				$where['status'] = 0;
				break;
			case '2':
				$where = null;
				$where = "userid = {$userid} OR aid = {$userid}";
				break;
			default:
				$where['otype']  = 1; //买入订单
				$where['status'] = 0;
				break;
		}
		
		$count = D('ExchangeOrder')->where($where)->count();
		$Page = new \Think\Page($count, 10);
		$show = $Page->show();
		
		$list = array();
		if(is_array($where)){
			$where['rush_status'] = 1;//催单的订单
			$rush_count = D('ExchangeOrder')->where($where)->order('id desc')->count();
			if($rush_count >= $Page->firstRow+$Page->listRows){
				$list = D('ExchangeOrder')->where($where)->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
			}elseif($rush_count >= $Page->firstRow && $rush_count < $Page->firstRow+$Page->listRows){
				$find_count = $rush_count - $Page->firstRow;
				$list = D('ExchangeOrder')->where($where)->order('id desc')->limit($Page->firstRow . ',' . $find_count)->select();
				$where['rush_status'] = 0;//未催单的订单
				$find_count =  $Page->listRows-$find_count;
				$no_rush_list = D('ExchangeOrder')->where($where)->order('id desc')->limit(0 . ',' . $find_count)->select();
				if(is_array($no_rush_list)){
					$list = array_merge($list, $no_rush_list);
				}
			}else{
				$where['rush_status'] = 0;//未催单的订单
				$start = $Page->firstRow-$rush_count;
				$list = D('ExchangeOrder')->where($where)->order('id desc')->limit($start . ',' . $Page->listRows)->select();
			}
		}elseif(is_string($where)){
			//催单的订单
			$rush_where = $where . " AND rush_status = 1";
			//未催单的订单
			$no_rush_where = $where . " AND rush_status = 0";

			$rush_count = D('ExchangeOrder')->where($rush_where)->order('id desc')->count();
			if($rush_count >= $Page->firstRow+$Page->listRows){
				$list = D('ExchangeOrder')->where($rush_where)->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
			}elseif($rush_count >= $Page->firstRow && $rush_count < $Page->firstRow+$Page->listRows){
				$find_count = $rush_count - $Page->firstRow;
				$list = D('ExchangeOrder')->where($rush_where)->order('id desc')->limit($Page->firstRow . ',' . $find_count)->select();
				$find_count =  $Page->listRows-$find_count;
				$no_rush_list = D('ExchangeOrder')->where($no_rush_where)->order('id desc')->limit(0 . ',' . $find_count)->select();
				if(is_array($no_rush_list)){
					$list = array_merge($list, $no_rush_list);
				}
			}else{
				$start = $Page->firstRow-$rush_count;
				$list = D('ExchangeOrder')->where($no_rush_where)->order('id desc')->limit($start . ',' . $Page->listRows)->select();
			}
		}
		$this->assign('list', $list);
		$this->assign('page', $show);
		
		$UserCoin = M('user_coin')->where(array('userid' => $userid))->find();
		
		$cny['ky'] = round($UserCoin[Anchor_CNY], 2) * 1;
		$cny['ky'] = sprintf("%.2f", $cny['ky']);
		$this->assign('cny', $cny);
		$this->assign('userid', $userid);
		
		$this->display();
	}

	// 买入处理
	public function upTrade($price, $num, $otype, $orderid)
	{
		if (!userid()) {
			$this->error(L('请先登录！'));
		}
		if($otype != 1){
			$this->error(L('非法操作！'));
		}

		// 过滤非法字符----------------S
        if (checkstr($price) || checkstr($num) || checkstr($orderid) || checkstr($otype)) {
            $this->error('您输入的信息有误！');
        }
        // 过滤非法字符----------------E
		
		$userid = userid();
		$coin_type = $coin_type?$coin_type:Anchor_CNY; //，买入类型
		$cur_time  = time();
		
		if (D('ExchangeOrder')->where(array('userid' => $userid,'otype' => 1,'status' => 1))->count() > 1) {
			$this->error(L('您有超过2笔订单未完成，无法创建！'));
		}
		
		/** 检查设置条件 **/
		$configs = M('exchange_config')->where(array('id' => 1))->find();
		if($configs['mycz_status'] == 0){
			$this->error(L('网络繁忙，请稍后再试！'));	
		}

		$userinfo = M('user')->where(array('id' => $userid))->field('kyc_lv, idstate, auto_c2c_sell_status')->find();
		if($userinfo['idstate'] != 2){
			$this->error('审核通过的用户才能进行'.strtoupper(Anchor_CNY).'交易！');
		}
		if($userinfo['kyc_lv'] < 2){
			$this->error('通过高级认证的用户才能进行'.strtoupper(Anchor_CNY).'交易！');
		}
		if($userinfo['auto_c2c_sell_status']){
			$this->error(L('开启自动卖出状态下无法进行买入'));
		}

		//确定是接单买入，还是创建订单买入
		if($orderid){

			$buy_userid = $userid;
			//必须是卖出订单，才能接买入单
        	$order_info = D('ExchangeOrder')->where(['orderid'=>$orderid, 'otype'=>2])->find();
        	if($order_info){
        		$num = $order_info['num'];
        		$coin_type = $order_info['type'];

        		if($order_info['userid'] == $buy_userid){
	        		$this->error(L('不能接自己的订单！'));
	        	}

				/** 生成汇款备注 **/
				for (; true; ) {
					$tradeno = tradeno();
					if (!M('Mycz')->where(array('tradeno' => $tradeno))->find()) {
						break;
					}
				}

				$data = array(
					'orderid'		=> $order_info['orderid'],
					'aid'			=> $buy_userid,			//接单人id
					'status'		=> 1,						//已接单状态
					'remarks' 		=> $tradeno, 
					'updatetime'	=> $cur_time,
				);
				//更新交易时间
				M('user')->where(['id'=>$buy_userid])->save(['last_exchange_time'=>$cur_time]);

				$res = D('ExchangeOrder')->where(['orderid'=>$orderid])->save($data);
				if($res){
					$this->success(L('接单成功,前往我的订单查看！'));
				}else{
					$this->error(L('数据操作错误，接单失败！'));
				}
        	}else{
        		$this->error(L('订单状态异常，接单失败！'));
        	}
		}else{

			//交易cnc才读取配置
			if($coin_type == Anchor_CNY){
				$price = $configs['mycz_uprice'];
			}

			/** 实际到账金额 **/
			$mum = $num * $price;
		
			if ($mum < $configs['mycz_min']) {
				$this->error(L('每次买入金额不能小于') . $configs['mycz_min'] . L('元！'));
			}
			if ($configs['mycz_max'] < $mum) {
				$this->error(L('每次买入金额不能大于') . $configs['mycz_max'] . L('元！'));
			}

			/** 生成汇款备注 **/
			for (; true; ) {
				$tradeno = tradeno();
				if (!M('Mycz')->where(array('tradeno' => $tradeno))->find()) {
					break;
				}
			}

			$curExchangeOrderTableName = D('ExchangeOrder')->getCurExhcangeOrderTableName();
			if($curExchangeOrderTableName){
				$orderData = array(
					'otype' => $otype, 
					'userid' => $userid,
					'remarks' => $tradeno, 
					'uprice' => $price, 
					'num' => $num, 
					'mum' => $mum, 
					'type' => $coin_type,
				);
				$mycz = $this->addExchangeOrder(M(), $curExchangeOrderTableName, $orderData, $cur_time);
				
				if ($mycz) {

					$this->afterAddExchangeOrder($orderData);
					$this->success(L('订单创建成功！'));
				} else {
					$this->error(L('订单创建失败111！'));
				}
			}else{
				$this->error(L('订单创建失败22！'));
			}
		}
	}
	
	//卖出处理
	public function upMytx($price, $num, $otype, $orderid)
	{
		if (!userid()) {
			$this->error(L('请先登录！'));
		}
		if($otype != 2){
			$this->error(L('非法操作！'));
		}

		// 过滤非法字符----------------S
        if (checkstr($price) || checkstr($num) || checkstr($otype) || checkstr($orderid)) {
            $this->error('您输入的信息有误！');
        }
        // 过滤非法字符----------------E

        $userid = userid();
        $coin_type = $coin_type?$coin_type:Anchor_CNY; //，卖出类型
        $cur_time  = time();
		
		/** 检查设置条件 **/
		$configs = M('exchange_config')->where(array('id' => 1))->find();
		
		if($configs['mytx_status'] == 0){
			$this->error(L('网络繁忙，请稍后再试！'));	
		}

		$userinfo = M('user')->where(array('id' => $userid))->field('kyc_lv, idstate, auto_c2c_sell_status')->find();
		if($userinfo['idstate'] != 2){
			$this->error('审核通过的用户才能进行'.strtoupper(Anchor_CNY).'交易！');
		}
		if($userinfo['kyc_lv'] < 2){
			$this->error('通过高级认证的用户才能进行'.strtoupper(Anchor_CNY).'交易！');
		}
		if($userinfo['auto_c2c_sell_status']){
			$this->error(L('开启自动卖出状态下无法进行手动卖出'));
		}

		//筛选支持该支付类型的用户的参数(随机账户)
        $account_info = M('payparams_list')->where(['userid'=>$userid,'status'=>1,'check_status'=>1,'is_manual_account'=>1])->order('rand()')->find();

        //不存在指定账户，使用混用账户
        if(empty($account_info)){
            $this->error(L('没有合适的手动交易账户，请核对后再试'));
        }

        //确定是接单卖出，还是创建订单卖出
        if($orderid){

        	$sell_userid = $userid;
        	//必须是买入订单，才能接卖出单
        	$order_info = D('ExchangeOrder')->where(['orderid'=>$orderid, 'otype'=>1])->find();
        	
        	if($order_info){
        		
        		$num = $order_info['num'];
        		$coin_type = $order_info['type'];
        		if($order_info['userid'] == $sell_userid){
	        		$this->error(L('不能接自己的订单！'));
	        	}

	        	if ($num <= 0) {
					$this->error(C('coin')[$coin_type]['title'] . L('交易金额不能为0！'));
				}

				/** 帐号金额 **/
				$user_coin = M('user_coin')->where(array('userid' => $userid))->find();
				if ($user_coin[$coin_type] < $num) {
					$this->error(C('coin')[$coin_type]['title'] . L('余额不足！'));
				}

				$data = array(
					'orderid'		=> $order_info['orderid'],
					'aid'			=> $sell_userid,			//接单人id
					'status'		=> 1,						 //已接单状态
					'pay_channelid' => $account_info['channelid'],//收款账户渠道id
                	'payparams_id'  => $account_info['id'],		 //收款账户id
					'truename' 		=> $account_info['truename'], //账户名
					'bank'     		=> $account_info['mch_id'], //类型名称
					'bankcard' 		=> $account_info['appid'],	//账户号
					'bankaddr' 		=> $account_info['signkey'], //附加信息
	                'bankprov' 		=> $account_info['appsecret'], 
	                'bankcity' 		=> $account_info['subject'],
	                'updatetime'	=> $cur_time,
				);
				$rs = array();
				//订单号对应的表名
        		$orderTableName = D('ExchangeOrder')->getExchangeOrderTableNameWithOrderID($orderid);
        		if($orderTableName){
        			$mo = M();
					$mo->execute('set autocommit=0');
					$mo->execute("lock tables {$orderTableName} write, tw_user_coin write, tw_user write, tw_finance write, tw_finance_log write");
					//更新订单信息
					$rs[] = $res_order = $mo->table($orderTableName)->where(['orderid'=>$orderid])->save($data);
					if($res_order){
						// 用户账户数据处理
		        		$rs[] = $mo->table('tw_user_coin')->where(array('userid' => $sell_userid))->setDec($coin_type,$num); // 修改金额
		        		//创建手动买入订单扣除cnc
		                $remarks = '创建mobile手动买入订单扣除cnc';
		                $rs[] = $this->addFinanceLog($order_info, $sell_userid, $remarks, 0, 1, false);
					}
					//更新交易时间
					$rs[] = $mo->table('tw_user')->where(['id'=>$sell_userid])->save(['last_exchange_time'=>time()]);
					if(check_arr($rs)){
						$mo->execute('commit');
						$mo->execute('unlock tables');
						$mo->execute('set autocommit=1');
						$this->success(L('接单成功,前往我的订单查看！'));
					}else{
						$mo->execute('rollback');
						$mo->execute('unlock tables');
						$mo->execute('set autocommit=1');
						$this->error(L('数据操作错误，接单失败！$rs ='.json_encode($rs)));
					}
        		}else{
        			$this->error(L('数据操作错误，接单失败111！'));
        		}
        	}else{
        		$this->error(L('订单状态异常，接单失败！'));
        	}

        }else{

        	/** 帐号金额 **/
			$user_coin = M('user_coin')->where(array('userid' => $userid))->find();
			if ($user_coin[$coin_type] < $num) {
				$this->error(C('coin')[$coin_type]['title'] . L('余额不足！'));
			}

        	if ($num < $configs['mytx_min']) {
				$this->error(L('每次卖出金额不能小于') . $configs['mytx_min'] . L('元！'));
			}

			if ($configs['mytx_max'] < $num) {
				$this->error(L('每次卖出金额不能大于') . $configs['mytx_max'] . L('元！'));
			}
			if ($configs['mytx_bei']) {
				if ($num % $configs['mytx_bei'] != 0) {
					$this->error(L('每次卖出金额必须是') . $configs['mytx_bei'] . L('的整倍数！'));
				}
			}

			//交易cnc才读取配置
			if($coin_type == Anchor_CNY){
				$price = $configs['mytx_uprice'];
			}

			//$paytype_config = M('paytype_config')->where(['channelid'=>$account_info['channelid']])->find();

        	//优惠
        	$sale_sell_rate = 0;//$paytype_config['sale_sell_rate'];
        	//单价
        	$price = $price * (1 + $sale_sell_rate);
			/** 实际到账金额 **/
			$mum = $num * $price;

			try{

				$curExchangeOrderTableName = D('ExchangeOrder')->getCurExhcangeOrderTableName();

				if($curExchangeOrderTableName){

					$mo = M();
					$mo->execute('set autocommit=0');
					$mo->execute("lock tables {$curExchangeOrderTableName} write, tw_user_coin write, tw_user write, tw_finance write, tw_finance_log write");
					
					$rs = array();

					$orderData = array(
						'otype' 		=> $otype,
						'userid' 		=> $userid,
						'orderid'       => build_exchange_order_no($cur_time),
						'remarks' 		=> $tradeno, 
						'uprice' 		=> $price, 
						'num' 			=> $num, 
						'mum' 			=> $mum, 
						'type'			=> $coin_type,
						'pay_channelid' => $account_info['channelid'],//收款账户渠道id
	                	'payparams_id'  => $account_info['id'],		 //收款账户id
						'truename' 		=> $account_info['truename'], //账户名
						'bank'     		=> $account_info['mch_id'], //类型名称
						'bankcard' 		=> $account_info['appid'],	//账户号
						'bankaddr' 		=> $account_info['signkey'], //附加信息
	                	'bankprov' 		=> $account_info['appsecret'], 
	                	'bankcity' 		=> $account_info['subject']
					);

					$rs[] = $otc_orderid = $this->addExchangeOrder($mo, $curExchangeOrderTableName, $orderData, $cur_time);

	                if($otc_orderid){
						// 用户账户数据处理
						$rs[] = $mo->table('tw_user_coin')->where(array('userid' => $userid))->setDec($coin_type,$num); // 修改金额
						//创建手动卖出订单扣除cnc
	                	$remarks = '创建moblie手动卖出订单扣除cnc';
	                	$rs[] = $this->addFinanceLog($orderData, $userid, $remarks, 0, 4, false);
	                	// 处理资金变更日志-----------------E
					}
					
					// 处理资金变更日志-----------------E

					if (check_arr($rs)) {
						session('mytx_verify', null);
						$mo->execute('commit');
						$mo->execute('unlock tables');
						$mo->execute('set autocommit=1');

						$this->afterAddExchangeOrder($orderData);
						$this->success(L('订单创建成功！'));
					} else {
						throw new \Think\Exception('订单创建失败！');
					}
				}
			}catch(\Think\Exception $e){
				$mo->execute('rollback');
				$mo->execute('unlock tables');
				$mo->execute('set autocommit=1');
				$this->error(L('订单创建失败！'));
			}
        }
	}

	// C2C订单确认成功
	public function orderQueren($orderid, $paypassword)
	{
		if(checkstr($orderid) || checkstr($paypassword)){
			$this->error(L('您输入的信息有误！'));
		}
		if (empty($orderid)) {
			$this->error('请选择要操作的数据!');
		}

		if (!userid()) {
			$this->error('请先登录！');
		}

		if (!check($paypassword, 'password')) {
			$this->error(L('密码格式为6~16位，不含特殊符号！'));
		}
		$user_paypassword = M('User')->where(array('id' => userid()))->getField('paypassword');
		if (md5($paypassword) != $user_paypassword) {
			if(!empty($user_paypassword)){
				$this->error(L('交易密码错误！'));
			}else{
				$this->error(L('还未设置交易密码，请前往账户中心设置！'));
			}
		}

		$order_info = D('ExchangeOrder')->where(array('orderid' => $orderid))->find();
		$order_info['op_userid'] = userid();
		operation_log(userid(), 2, "C2C订单确认成功orderid = ".$order_info['orderid']);
		$res = R("Pay/PayExchange/confirmC2COrderWithOrderInfo", array($order_info));
		if ($res === true) {
			$this->success('操作成功！');
		} else {
			$this->error($res['msg']?$res['msg']:'操作失败！');
		}
	}

	//取消订单
	public function cancelOrder($orderid){
		if(checkstr($orderid)){
			$this->error(L('您输入的信息有误！'));
		}
		if (empty($orderid)) {
			$this->error('请选择要操作的数据!');
		}

		if (!userid()) {
			$this->error(L('请先登录！'),U('Login/index'));
		}

		$order_info = D('ExchangeOrder')->where(array('orderid' => $orderid))->find();
		
		if($order_info['status'] > 2){
			$this->error('已完成订单不能取消，如需取消联系平台!');
		}elseif(!isAutoC2COrder($order_info) && $order_info['status'] > 1){
			$this->error('已接订单不能取消，如需取消联系平台!');
		}elseif($order_info['status'] == 2 && isAutoC2COrder($order_info) && !isBuyUserOpreate($order_info, userid())){
			$this->error('自动订单非买方不能取消，如需取消联系平台!');
		}

		$rs = R("Pay/PayExchange/cancelC2COrderWithOrderInfo", array($order_info));

		if ($rs === true) {
			$this->success('操作成功！');
		} else {
			$this->error($rs['msg']?$rs['msg']:'操作失败！');
		}
	}

	//催单
	public function rushOrder($orderid){
		if (checkstr($orderid)) {
			$this->error(L('您输入的信息有误！'));
		}

		$userid = userid();
		if (!$userid) {
			$this->error(L('请先登录！'),U('Login/index'));
		}

        if($orderid){
        	$where = "(userid = {$userid} OR aid = {$userid}) AND status IN(2,8) AND rush_status = 1";
        	$rushOrderCount = D('ExchangeOrder')->where($where)->count();

        	if($rushOrderCount > 5){
        		$this->error(L('最多同时催5笔订单！'));
        	}else{
        		operation_log($userid, 2, "C2C订单催单orderid = ".$orderid);
        		$order_info = D('ExchangeOrder')->where(['orderid'=>$orderid])->find();
        		if(!empty($order_info)){
        			if($order_info['status'] != 3){
        				//3天内的时间
				        $dayTime = 7*24*60*60;
				        $time = time();
				        if($order_info['addtime'] >= $time - $dayTime){
				        	if($order_info['addtime'] < $time - 2*60){
			        			$data = array();
			        			$data['rush_status'] = 1;
			        			if($order_info['status'] == 8){
			        				$data['updatetime'] = $time;
			        			}
			        			$res = D('ExchangeOrder')->where(['orderid'=>$orderid])->save($data);
				        		if($res){
				        			$this->success('催单成功！');
				        		}else{
				        			$this->error($rs['msg']?$rs['msg']:'催单失败！');
				        		}
			        		}else{
			        			$this->error('订单2分钟后才能催单！');
			        		}
				        }else{
				        	$this->error('超过7天的订单，不能催单！');
				        }
        			}else{
		        		$this->error('订单已经完成，请刷新页面！');
		        	}
		        }else{
		        	$this->error('订单不存在！');
		        }
        	}
        }
	}

	// C2C订单重新确认成功
	public function resetOrderQueen($orderid, $paypassword)
	{
		if(checkstr($orderid) || checkstr($paypassword)){
			$this->error(L('您输入的信息有误！'));
		}
		if (empty($orderid)) {
			$this->error('请选择要操作的数据!');
		}

		$userid = userid();
		if (!$userid) {
			$this->error(L('请先登录！'),U('Login/index'));
		}

		if (!check($paypassword, 'password')) {
			$this->error(L('密码格式为6~16位，不含特殊符号！'));
		}
		$user_paypassword = M('User')->where(array('id' => $userid))->getField('paypassword');
		if (md5($paypassword) != $user_paypassword) {
			if(!empty($user_paypassword)){
				$this->error(L('交易密码错误！'));
			}else{
				$this->error(L('还未设置交易密码，请前往账户中心设置！'));
			}
		}

		$order_info = D('ExchangeOrder')->where(array('orderid' => $orderid))->find();
		operation_log($userid, 2, "C2C订单重置成功orderid = ".$order_info['orderid']);
		if(isBuyUserOpreate($order_info, $userid)){
			$this->error(L('买方不能重置订单成功！'));
		}
		$res = R("Pay/PayExchange/resetConfirmC2COrderWithOrderInfo", array($order_info));
		if ($res === true) {
			$this->success('操作成功！');
		} else {
			$this->error($res['msg']?$res['msg']:'操作失败！');
		}
	}

	/**
     * 添加C2C交易订单
     * @param model  	 数据库操作对象
     * @param tableName  操作表名
     * @param orderData  订单数据
     * @param outOrderId 外部订单号
     * @return  id
     */
    private function addExchangeOrder($model, $tableName, $orderData, $addtime){

        if($model && $tableName && is_array($orderData)){
        	$addtime = $addtime?$addtime:time();
        	//更新用户的交易最后交易时间
        	if(isset($orderData['userid'])){
        		$model->table('tw_user')->where(['id'=>$orderData['userid']])->save(['last_exchange_time'=>$addtime]);
        	}
        	if(isset($orderData['aid'])){
                $model->table('tw_user')->where(['id'=>$orderData['aid']])->save(['last_exchange_time'=>$addtime]);
            }

            if(!isset($orderData['orderid'])){
                $orderData['orderid']   = build_exchange_order_no($addtime);
            }
            if(!isset($orderData['out_order_id'])){
                $orderData['out_order_id'] = $orderData['orderid'];
            }
            $orderData['addtime']   = $addtime;
            $orderData['updatetime']= $addtime;
            $orderData['status']    = 0;
            $res = $model->table($tableName)->add($orderData);
            if($res){
                return $orderData['orderid'];
            }else{
                return false;
            }
        }
        return false;
    }

    //添加C2C订单后的处理逻辑
    private function afterAddExchangeOrder($orderData){

        if(isset($orderData) && !empty($orderData)){
            $cur_time = time();
            if(isset($orderData['payparams_id'])){
                M('payparams_list')->where(['id'=>$orderData['payparams_id']])->save(['last_paying_time'=>$cur_time]);
            }
            if(isset($orderData['pay_channelid'])){
                M('paytype_config')->where(['channelid'=>$orderData['pay_channelid']])->save(['last_paying_time'=>$cur_time]);
            }
        }
    }

	public function showOrderQueen($orderid)
	{
		// 过滤非法字符----------------S
		if (checkstr($orderid)) {
			$this->error('您输入的信息有误！');
		}
		// 过滤非法字符----------------E
		
		if (!check($orderid, 'd')) {
			$this->error('参数错误');
		}

		if (!userid()) {
			$this->error(L('请先登录！'),U('Login/index'));
		}
		
		$order_info = D('ExchangeOrder')->where(array('orderid' => $orderid))->find();
		if(empty($order_info)){
			$this->error('订单信息不存在！');
		}
		
		$this->assign('orderid', $orderid);
		$this->display();
	}
	
	// 账户管理
	public function bank()
	{
		if (!userid()) {
			$this->error(L('请先登录！'),U('Login/index'));
		}
		
		// 搜索实名认证信息
		$user = D('user')->where(array('id' => userid()))->find();
		if ($user['kyc_lv'] == 1) {
			if ($user['idstate'] == 2) {
				$this->assign('idcard', 1);
			} else {
				$this->error(L('请先通过实名认证，再进行操作！'), U('User/index'));
			}
		} else if ($user['kyc_lv'] == 2) {
			$this->assign('idcard', 1);
		}

		$channelid_list = array();
		$select_channelid = $user['select_channelid'];
		if(!empty($select_channelid)){
			$channelid_list = explode(',', $select_channelid);
		}

		$where = array();
		$where['status'] = 1;
		$UserAccountTypes = M('paytype_config')->where($where)->order('id asc')->select();
		foreach ($UserAccountTypes as $key => $value) {
			//删除没有开通的通道
			if(!in_array($value['channelid'], $channelid_list)){
				unset($UserAccountTypes[$key]);
				continue;
			}
			$UserAccountTypes[$key]['channel_num'] = M('payparams_list')->where(['userid'=>userid(),'channelid'=>$value['channelid']])->count();
		}
		$this->assign('UserAccountTypes', $UserAccountTypes);
		$this->display();
	}
	
	public function payinfo($orderid,$type)
	{
		// 过滤非法字符----------------S
		if (checkstr($orderid) || checkstr($type)) {
			$this->error('您输入的信息有误！');
		}
		// 过滤非法字符----------------E
		
		if (!check($orderid, 'd')) {
			$this->error('参数错误1');
		}
		
		if (!check($type, 'd')) {
			$this->error('参数错误2');
		}

		if (!userid()) {
			$this->error(L('请先登录！'),U('Login/index'));
		}

		$orderInfo = D('ExchangeOrder')->where(array('orderid' => $orderid))->find();
		
		if($type == 1){  //卖方信息

			if($orderInfo['otype'] == 1){ //买入订单
				$userInfo = M('user')->where(['id' => $orderInfo['aid']])->find();
				if(isAutoC2COrder($orderInfo)){
					$accountInfo = array();
					$accountInfo['paytype']    	= 18;
					$accountInfo['truename']    = isset($orderInfo['truename'])?$orderInfo['truename']:'';
		            $accountInfo['mch_id']      = isset($orderInfo['bank'])?$orderInfo['bank']:'';
		            $accountInfo['appid']       = isset($orderInfo['bankcard'])?$orderInfo['bankcard']:'';
		            $accountInfo['signkey']     = isset($orderInfo['bankaddr'])?$orderInfo['bankaddr']:'';
		            $accountInfo['appsecret']   = isset($orderInfo['bankprov'])?$orderInfo['bankprov']:'';
		            $accountInfo['subject']     = isset($orderInfo['bankcity'])?$orderInfo['bankcity']:'';
				}else{
					$accountInfo = M('payparams_list')->where(['id' => $orderInfo['payparams_id']])->find();
				}
			}else{
				$userInfo = M('user')->where(['id' => $orderInfo['userid']])->find();
				$accountInfo = M('payparams_list')->where(['id' => $orderInfo['payparams_id']])->find();
			}
			
		}else{ //买方信息
			
			if($orderInfo['otype'] == 1){ //买入订单
				$userInfo = M('user')->where(['id' => $orderInfo['userid']])->find();
			}else{
				$userInfo = M('user')->where(['id' => $orderInfo['aid']])->find();
			}
		}

		if($orderInfo && $userInfo){
			$data['status'] = true;
			$data['order'] = $orderInfo;
			$data['userInfo'] = $userInfo;
			$data['accountInfo'] = $accountInfo;
		}elseif(!$orderInfo){
			$this->error('订单信息不存在');
		}elseif(!$userInfo){
			$this->error('用户信息不存在');
		}else{
			$this->error('未知错误');
		}

		$data['type'] = $type;
		$data['order'] = $orderInfo;
		$data['userInfo'] = $userInfo;
		$data['accountInfo'] = $accountInfo;
		
		$this->assign('data', $data);
		$this->display();
	}
	
	public function bankadd()
	{
		if (!userid()) {
			$this->error(L('请先登录！'),U('Login/index'));
		}
		
		// 搜索实名认证信息
		$user = D('user')->where(array('id' => userid()))->find();
		if ($user['kyc_lv'] == 1) {
			if ($user['idstate'] == 2) {
				$this->assign('idcard', 1);
			} else {
				$this->error(L('请先通过实名认证，再进行操作！'), U('User/index'));
			}
		} else if ($user['kyc_lv'] == 2) {
			$this->assign('idcard', 1);
		}

		$UserBankType = M('UserBankType')->where(array('status' => 1))->order('id desc')->select();
		$this->assign('UserBankType', $UserBankType);
		
		$truename = M('User')->where(array('id' => userid()))->getField('truename');
		$this->assign('truename', $truename);
		
		$UserBank = M('UserBank')->where(array('userid' => userid(), 'status' => 1))->order('id desc')->select();
		$this->assign('UserBank', $UserBank);

		$this->display();
	}

	//开启自动卖出CNC的状态
	public function autoSellCNCstatus(){

		// 过滤非法字符----------------E
		if (!userid()) {
			$this->error(L('请先登录！'),U('Login/index'));
		}
		
		//获取用户信息
		$userinfo = M('User')->where(array('id' => userid()))->find();
		if($userinfo['idstate'] != 2 || $userinfo['kyc_lv'] != 2){ //机构认证通过
			$this->error('通过高级实名的用户才能开启自动卖出！');
		}

		$payparams = M('payparams_list')->where(['userid'=>userid(), 'status'=>1])->find();
		if(empty($payparams)){
			$this->error('没有配置或开启用于自动卖出账户！');
		}

		if(($userinfo['auto_c2c_time']+60) >= time()){
			$this->error('1分钟后才能再次改变状态！');
		}
		$status = 0;
		if(!$userinfo['auto_c2c_sell_status']){
			$status = 1;
		}
		$data = [
			'auto_c2c_sell_status'	=> $status,
			'auto_c2c_time'			=> time(),
		];

		$res = M('User')->where(array('id' => userid()))->save($data);
		if($res){
			$this->assign('auto_c2c_sell_status', $status);
			$this->success('操作成功！');
		}else{
			$this->assign('auto_c2c_sell_status', $userinfo['auto_c2c_time']);
			$this->error('操作失败！');
		}
	}

	//开启自动买入CNC的状态
	public function autoBuyCNCstatus(){

		// 过滤非法字符----------------E
		if (!userid()) {
			$this->error(L('请先登录！'));
		}
		
		//获取用户信息
		$userinfo = M('User')->where(array('id' => userid()))->find();
		if($userinfo['idstate'] != 2 || $userinfo['kyc_lv'] != 2){ //机构认证通过
			$this->error('通过高级实名的用户才能开启自动买入！');
		}

		if($userinfo['cancal_c2c_level'] != 1){ //必须为完全信任才能开启自动买入
			$this->error('只有完全信任的用户才能开启自动买入！');
		}

		$payparams = M('payparams_list')->where(['userid'=>userid(), 'status'=>1])->find();
		if(empty($payparams)){
			$this->error('没有配置或开启用于自动买入账户！');
		}

		if(($userinfo['auto_c2c_time']+5) >= time()){
			$this->error('5秒钟后才能再次改变状态！');
		}
		$status = 0;
		if(!$userinfo['auto_c2c_buy_status']){
			$status = 1;
		}
		$data = [
			'auto_c2c_buy_status'	=> $status,
			'auto_c2c_time'		=> time(),
		];

		$res = M('User')->where(array('id' => userid()))->save($data);
		if($res){
			$this->assign('auto_c2c_buy_status', $status);
			$this->success('操作成功！');
		}else{
			$this->assign('auto_c2c_buy_status', $userinfo['auto_c2c_buy_status']);
			$this->error('操作失败！');
		}
	}

	//支付参数相关///////////////////
	///////////////////////////////

	//个人支付参数列表
	public function payPersonalParams($channelid=0)
	{
		// 过滤非法字符----------------E
		if (!userid()) {
			$this->error(L('请先登录！'),U('Login/index'));
		}

		if(checkstr($channelid)){
			$this->error(L('您输入的信息有误！'));
		}
		
		//获取用户信息
		$userinfo = M('User')->where(array('id' => userid()))->find();
		if($userinfo['idstate'] != 2 || $userinfo['kyc_lv'] != 2 ){ //高级认证通过
			$this->error('通过高级实名的用户才能添加个人支付参数！');
		}

		$this->assign('user', $userinfo);

		//取出支付参数类型
		$PayChannels = M('paytype_config')->where(['status'=>1,'is_personal'=>1])->order('id desc')->field('paytype, title, channelid, channel_title')->select();

		//整理出支付类型
		$PayTypes = array();
		foreach ($PayChannels as $key => $value) {
			$PayTypes[$value['paytype']] = $value;
		}

		$this->assign('PayTypes', $PayTypes);
		$this->assign('PayChannels', $PayChannels);


		$where['userid'] 	= userid();
		$where['is_personal'] = 1;
		if($channelid){
			$where['channelid'] = $channelid;
			//列表的标题
			$payTitle = M('paytype_config')->where(['channelid'=>$channelid])->order('id desc')->find();
			$this->assign('payTitle', $payTitle);
			$this->assign('channelid', $channelid);
		}
		$list = M('payparams_list')->where($where)->order('id desc')->select();

		foreach ($list as $key => $value) {
			
			if(strlen($value['mch_id']) > 12){
				$list[$key]['mch_id'] = substr($value['mch_id'], 0, 12) . '...';
			}

			if(strlen($value['signkey']) > 12){
				$list[$key]['signkey'] = substr($value['signkey'], 0, 12) . '...';
			}

			if(strlen($value['appid']) > 12){
				$list[$key]['appid'] = substr($value['appid'], 0, 12) . '...';
			}

			if(strlen($value['appsecret']) > 12){
				$list[$key]['appsecret'] = substr($value['appsecret'], 0, 12) . '...';
			}
			if($value['max_fail_count'] >= 15 && $value['status'] == 0){
				$list[$key]['max_fail_status'] = 1;
			}else{
				$list[$key]['max_fail_status'] = 0;
			}
		}

		$this->assign('list', $list);
		$this->display();
	}

	//机构支付参数列表
	public function payOrganizationParams($channelid=0)
	{
		// 过滤非法字符----------------E
		if (!userid()) {
			$this->error(L('请先登录！'),U('Login/index'));
		}

		if(checkstr($channelid)){
			$this->error(L('您输入的信息有误！'));
		}
		
		//获取用户信息
		$userinfo = M('User')->where(array('id' => userid()))->find();
		//机构认证状态
		$organization_status = M('user_kyc')->where(array('userid' => userid()))->getField('organization_status');
		if($userinfo['idstate'] != 2 || $userinfo['kyc_lv'] != 2 || $organization_status != 2){ //机构认证通过
			$this->error('通过机构认证的用户才能添加机构支付参数！');
		}

		$this->assign('user', $userinfo);

		//取出支付参数类型
		$PayChannels = M('paytype_config')->where(['status'=>1,'is_personal'=>0])->order('id desc')->field('paytype, title, channelid, channel_title')->select();

		//整理出支付类型
		$PayTypes = array();
		foreach ($PayChannels as $key => $value) {
			$PayTypes[$value['paytype']] = $value;
		}

		$this->assign('PayTypes', $PayTypes);
		$this->assign('PayChannels', $PayChannels);

		$where['userid'] = userid();
		$where['is_personal'] = 0;
		if($channelid){
			$where['channelid'] = $channelid;
			//列表的标题
			$payTitle = M('paytype_config')->where(['channelid'=>$channelid])->order('id desc')->find();
			$this->assign('payTitle', $payTitle);
			$this->assign('channelid', $channelid);
		}
		$list = M('payparams_list')->where($where)->order('id desc')->select();

		foreach ($list as $key => $value) {

			if(strlen($value['mch_id']) > 12){
				$list[$key]['mch_id'] = substr($value['mch_id'], 0, 12) . '...';
			}

			if(strlen($value['signkey']) > 12){
				$list[$key]['signkey'] = substr($value['signkey'], 0, 12) . '...';
			}

			if(strlen($value['appid']) > 12){
				$list[$key]['appid'] = substr($value['appid'], 0, 12) . '...';
			}

			if(strlen($value['appsecret']) > 12){
				$list[$key]['appsecret'] = substr($value['appsecret'], 0, 12) . '...';
			}
			if($value['max_fail_count'] >= 15 && $value['status'] == 0){
				$list[$key]['max_fail_status'] = 1;
			}else{
				$list[$key]['max_fail_status'] = 0;
			}
		}

		$this->assign('list', $list);
		$this->display();
	}

	//编辑个人支付参数
	public function editPersonalParams($id, $channelid=0){

		if (!userid()) {
			$this->error(L('请先登录！'),U('Login/index'));
		}

		if(checkstr($channelid) || checkstr($id)){
			$this->error(L('您输入的信息有误！'));
		}
		
		//获取用户信息
		$userinfo = M('User')->where(array('id' => userid()))->find();
		if($userinfo['idstate'] != 2 || $userinfo['kyc_lv'] != 2 ){ //高级认证通过
			$this->error('通过高级实名的用户才能添加个人支付参数！');
		}

		$this->assign('user', $userinfo);

		//取出支付参数类型
		$PayChannels = M('paytype_config')->where(['status'=>1,'is_personal'=>1])->order('id desc')->field('paytype, title, channelid, channel_title')->select();

		//整理出支付类型
		$PayTypes = array();
		foreach ($PayChannels as $key => $value) {
			$PayTypes[$value['paytype']] = $value;
		}

		$this->assign('PayTypes', $PayTypes);
		$this->assign('PayChannels', $PayChannels);

		if($id){
			if (checkstr($id)){
				$this->error(L('信息有误！'));
			}

			$info = M('payparams_list')->where(['id'=>$id])->find();
			$this->assign('info', $info);
			//列表的标题
			$payTitle = M('paytype_config')->where(['channelid'=>$info['channelid']])->order('id desc')->find();
			$this->assign('payTitle', $payTitle);
			$this->assign('channelid', $info['channelid']);
		}elseif($channelid > 0){
			//列表的标题
			$payTitle = M('paytype_config')->where(['channelid'=>$channelid])->order('id desc')->find();
			$this->assign('payTitle', $payTitle);
			$this->assign('channelid', $channelid);
		}
		$this->display();
	}

	//编辑机构支付参数
	public function editOrganizationParams($id, $channelid=0){

		if (!userid()) {
			$this->error(L('请先登录！'),U('Login/index'));
		}

		if(checkstr($channelid) || checkstr($id)){
			$this->error(L('您输入的信息有误！'));
		}
	
		//获取用户信息
		$userinfo = M('User')->where(array('id' => userid()))->find();
		//机构认证状态
		$organization_status = M('user_kyc')->where(array('userid' => userid()))->getField('organization_status');
		if($userinfo['idstate'] != 2 || $userinfo['kyc_lv'] != 2 || $organization_status != 2){ //机构认证通过
			$this->error('通过机构认证的用户才能添加机构支付参数！');
		}

		$this->assign('user', $userinfo);

		//取出支付参数类型
		$PayChannels = M('paytype_config')->where(['status'=>1,'is_personal'=>0])->order('id desc')->field('paytype, title, channelid, channel_title')->select();

		//整理出支付类型
		$PayTypes = array();
		foreach ($PayChannels as $key => $value) {
			$PayTypes[$value['paytype']] = $value;
		}

		$this->assign('PayTypes', $PayTypes);
		$this->assign('PayChannels', $PayChannels);

		if($id){
			if (checkstr($id)){
				$this->error(L('信息有误！'));
			}

			$info = M('payparams_list')->where(['id'=>$id])->find();
			$this->assign('info', $info);
			//列表的标题
			$payTitle = M('paytype_config')->where(['channelid'=>$info['channelid']])->order('id desc')->find();
			$this->assign('payTitle', $payTitle);
			$this->assign('channelid', $info['channelid']);
		}elseif($channelid > 0){
			//列表的标题
			$payTitle = M('paytype_config')->where(['channelid'=>$channelid])->order('id desc')->find();
			$this->assign('payTitle', $payTitle);
			$this->assign('channelid', $channelid);
		}
		$this->display();
	}

	//编辑风控参数
	public function editControl($id){

		if (!userid()) {
			$this->error(L('请先登录！'),U('Login/index'));
		}

		if (checkstr($id)){
			$this->error(L('信息有误！'));
		}

		if(!$id){
			$this->error('id参数错误！');
		}

		$info = M('payparams_list')->where(['id'=>$id])->find();

		$paytype_config = M('paytype_config')->where(['channelid'=>$info['channelid']])->find();
		
		//获取用户信息
		$userinfo = M('User')->where(array('id' => userid()))->find();

		if($paytype_config['is_personal']){
			if($userinfo['idstate'] != 2 || $userinfo['kyc_lv'] != 2 ){ //高级认证通过
				$this->error('通过高级实名的用户才能添加个人支付参数！');
			}
		}else{
			if($userinfo['idstate'] != 2 || $userinfo['kyc_lv'] != 2 || $organization_status != 2){ //机构认证通过
				$this->error('通过机构认证的用户才能添加机构支付参数！');
			}
		}
		
		$this->assign('user', $userinfo);
		$this->assign('info', $info);
		$this->display();
	}

	//更新支付参数
	public function upPayParams($id, $channelid, $truename, $mch_id, $signkey, $appid, $appsecret, $domain_record, $subject, $is_manual_account, $is_defined, $status, $paypassword)
	{
		// 过滤非法字符----------------S
		if (checkstr($id)||checkstr($channelid)||checkstr($truename)||checkstr($mch_id)||checkstr($appid)||checkstr($is_manual_account)||checkstr($is_defined)||checkstr($status)||checkstr($domain_record)||checkstr($subject)||checkstr($paypassword)) {
			$this->error(L('输入的信息有误！'));
		}
		// 过滤非法字符----------------E

		if (!userid()) {
			$this->error(L('请先登录！'),U('Login/index'));
		}

		if (!check($paypassword, 'password')) {
			$this->error(L('密码格式为6~16位，不含特殊符号！'));
		}
		$userinfo = M('User')->where(array('id' => userid()))->field('paypassword, username, idstate, kyc_lv')->find();
		if (md5($paypassword) != $userinfo['paypassword']) {
			if(!empty($userinfo['paypassword'])){
				$this->error(L('交易密码错误！'));
			}else{
				$this->error(L('还未设置交易密码，请前往账户中心设置！'));
			}
		}

		//是否个人渠道
		$paytype_config = M('paytype_config')->where(['channelid'=>$channelid,'status'=>1])->find();
		if(!$paytype_config){
			$this->error('未选择可用的账户类型！');
		}
		$is_personal = $paytype_config['is_personal'];
		$paytype 	 = $paytype_config['paytype'];

		if(!$mch_id && !$signkey && !$appid && !$appsecret && !$subject && !$domain_record){
			$this->error('必须填写账户参数！');
		}

		if($userinfo['idstate'] != 2){
			$this->error('审核通过的用户才能操作账户参数！');
		}
		if($userinfo['kyc_lv'] < 2){
			$this->error('通过高级认证的用户才能操作账户参数！');
		}

		$data = array(
			'userid'  				=> userid(),
			'username'				=> $userinfo['username'],
			'paytype' 				=> $paytype,
			'channelid' 			=> $channelid,
			'truename'				=> $truename,
			'mch_id' 				=> $mch_id,
			'signkey' 				=> $signkey,
			'appid' 				=> $appid,
			'appsecret' 			=> $appsecret,
			'domain_record' 		=> $domain_record,
			'subject'				=> $subject,
			'addtime' 				=> time(),
			'is_personal'			=> $is_personal,
			'check_status' 			=> 0,
			'is_manual_account' 	=> $is_manual_account?$is_manual_account:0,
			'is_defined' 			=> $is_defined?$is_defined:0,
			'status' 				=> $status?$status:0,
			'low_money_count'		=> 0,
			'max_fail_count'		=> 0,
			'limit_amount_status'	=> 0,
		);

		if($id){
			unset($data['userid']);
			unset($data['username']);
			unset($data['addtime']);
			if (M('payparams_list')->where(['id'=>$id])->save($data)) {
				$this->success(L('编辑成功！'));
			} else {
				$this->error(L('编辑失败！'));
			}
		}else{
			//表示未指定
			$data['select_memberid'] = '0';
			if (M('payparams_list')->add($data)) {
				$this->success(L('添加成功！'));
			} else {
				$this->error(L('添加失败！'));
			}
		}
	}

	//更新风控参数
	public function upControlEdit($id, $all_money, $all_pay_num, $start_time, $end_time, $min_money, $max_money,$control_status, $offline_status, $paypassword)
	{
		// 过滤非法字符----------------S
		if (checkstr($id)||checkstr($all_money)||checkstr($all_pay_num)||checkstr($start_time)||checkstr($end_time)||checkstr($min_money)||checkstr($max_money)||checkstr($control_status)||checkstr($offline_status)||checkstr($paypassword)) {
			$this->error(L('您输入的信息有误！'));
		}
		// 过滤非法字符----------------E

		if (!userid()) {
			$this->error(L('请先登录！'),U('Login/index'));
		}

		if (!check($paypassword, 'password')) {
			$this->error(L('密码格式为6~16位，不含特殊符号！'));
		}
		$userinfo = M('User')->where(array('id' => userid()))->field('paypassword, username, idstate, kyc_lv')->find();
		if (md5($paypassword) != $userinfo['paypassword']) {
			if(!empty($userinfo['paypassword'])){
				$this->error(L('交易密码错误！'));
			}else{
				$this->error(L('还未设置交易密码，请前往账户中心设置！'));
			}
		}

		if(!$all_money){
			$all_money = 0;
		}

		if(!$all_pay_num){
			$all_pay_num = 0;
		}

		if(!$start_time){
			$$all_money = 0;
		}

		if(!$end_time){
			$end_time = 0;
		}

		if(!$min_money){
			$min_money = 0;
		}

		if(!$max_money){
			$max_money = 0;
		}

		if(!is_numeric($all_money) || !is_numeric($min_money) || !is_numeric($max_money)){
			$this->error('请检查金额，必须是数字');
		}

		if(!is_numeric($all_pay_num)){
			$this->error('请检查总次数，必须是数字');
		}

		if($all_pay_num < 0){
			$this->error('总次数不能小于0的整数');
		}

		//限制金额
		$limit_money = 100*10000;
		if($min_money >= $limit_money || $max_money >= $limit_money){
			$this->error('最大和最小金额必须小于100万');
		}

		if($max_money > 0 && $min_money > $max_money){
			$this->error('最大金额需大于最小金额');
		}

		//时间判断
		if(!is_numeric($start_time) || !is_numeric($end_time)){
			$this->error('时间格式为整型数字0-24');
		}

		$start_time = intval($start_time);
		$end_time 	= intval($end_time);

		if($start_time < 0 || $start_time > 24 || $end_time < 0 || $end_time > 24){
			$this->error('时间格式为整型数字0-24');
		}

		if($end_time > 0 && $start_time > $end_time){
			$this->error('结束时间需大于开始时间！');
		}

		if($userinfo['idstate'] != 2){
			$this->error('审核通过的用户才能操作支付参数！');
		}
		if($userinfo['kyc_lv'] < 2){
			$this->error('通过高级认证的用户才能操作支付参数！');
		}

		$data = array(
			'all_money'		=> $all_money,
			'all_pay_num'	=> $all_pay_num,
			'start_time' 	=> $start_time,
			'end_time' 		=> $end_time,
			'min_money' 	=> $min_money,
			'max_money' 	=> $max_money,
			'control_status'=> $control_status?$control_status:0,
			'offline_status'=> $offline_status?$offline_status:0,
		);

		if($id){
			if (M('payparams_list')->where(['id'=>$id])->save($data)) {
				$this->success(L('编辑成功！'));
			} else {
				$this->error(L('编辑失败！'));
			}
		}else{
			
			$this->error(L('编辑风控的参数不存在！'));
		}
	}

	//删除支付参数
	public function delPayParams($id, $paypassword)
	{
		// 过滤非法字符----------------S
		if (checkstr($id) || checkstr($paypassword)) {
			$this->error(L('您输入的信息有误！'));
		}
		// 过滤非法字符----------------E

		if (!userid()) {
			$this->error(L('请先登录！'),U('Login/index'));
		}

		if (!check($paypassword, 'password')) {
			$this->error(L('密码格式为6~16位，不含特殊符号！'));
		}

		if (!check($id, 'd')) {
			$this->error(L('参数错误！'));
		}

		$user_paypassword = M('User')->where(array('id' => userid()))->getField('paypassword');
		if (md5($paypassword) != $user_paypassword) {
			if(!empty($user_paypassword)){
				$this->error(L('交易密码错误！'));
			}else{
				$this->error(L('还未设置交易密码，请前往账户中心设置！'));
			}
		}

		if (!M('payparams_list')->where(array('userid' => userid(), 'id' => $id))->find()) {
			$this->error(L('非法访问！'));
		} else if (M('payparams_list')->where(array('userid' => userid(), 'id' => $id))->delete()) {
			$this->success(L('删除成功！'));
		} else {
			$this->error(L('删除失败！'));
		}
	}

	//保存二维码图片
	public function saveQrcode()
	{
		$upload = new \Think\Upload();
		$upload->maxSize = 3145728;
		$upload->exts = array('jpg', 'gif', 'png', 'jpeg');
		$upload->rootPath = './Upload/qrcode/personal/';
		$upload->autoSub = false;
		
		$info = $upload->upload();
		foreach ($info as $k => $v) {
			$path = $v['savepath'] . $v['savename'];
			echo $path;
			exit();
		}
	}

	//增加个人二维码的页面
	public function addPersonalCode($channelid=0){

		if (!userid()) {
			$this->error(L('请先登录！'),U('Login/index'));
		}

		if(checkstr($channelid)){
			$this->error(L('您输入的信息有误！'));
		}

		$this->assign('channelid', $channelid);
		//取出支付参数类型
		$PayChannels = M('paytype_config')->where(['status'=>1,'is_personal'=>1])->order('id desc')->field('paytype, title, channelid, channel_title')->select();
		$this->assign('PayChannels', $PayChannels);
		$this->display();
	}

	//上传个人支付宝二维码的参数
	public function upPersonalCode($qrcode, $mch_id, $channelid, $truename, $paypassword){
		// 过滤非法字符----------------S
		if (checkstr($mch_id)||checkstr($truename)||checkstr($channelid)||checkstr($paypassword)) {
			$this->error(L('输入的信息有误！'));
		}
		// 过滤非法字符----------------E

		if (!userid()) {
			$this->error(L('请先登录！'),U('Login/index'));
		}

		if (!check($paypassword, 'password')) {
			$this->error(L('密码格式为6~16位，不含特殊符号！'));
		}
		$userinfo = M('User')->where(array('id' => userid()))->field('paypassword, username, idstate, kyc_lv')->find();
		if (md5($paypassword) != $userinfo['paypassword']) {
			if(!empty($userinfo['paypassword'])){
				$this->error(L('交易密码错误！'));
			}else{
				$this->error(L('还未设置交易密码，请前往账户中心设置！'));
			}
		}

		if(!$qrcode){
			$this->error('上传二维码图片!');
		}

		$mch_id = trim($mch_id);
		if(!$mch_id){
			$this->error('填写正确的账号!');
		}

		$truename = trim($truename);
		if(!$truename){
			$this->error('填写对应账户的拥有人姓名!');
		}

		if($userinfo['idstate'] != 2){
			$this->error('审核通过的用户才能操作支付参数！');
		}
		if($userinfo['kyc_lv'] < 2){
			$this->error('通过高级认证的用户才能操作支付参数！');
		}
		// //加载需要的类
		// vendor('QRcodeReaderMaster/lib/QrReader');
		// //二维码图片的地址
		// $qrcodePath = APP_REALPATH.'/Upload/qrcode/personal/'.$qrcode;
		// //$this->error($qrcodePath);
		// //解析二维码得到链接
		// $qrcodeReader = new \QrReader($qrcodePath);
		// $signkey = $qrcodeReader->text();

		$signkey = getQRcodeToUrl($qrcode);
		if(!$signkey || $signkey == ''){
			$this->error('无法解析正确的二维码链接，请重试或者手动解析链接后填写！');
		}
		switch ($channelid) {
			case '264':
				$appid  	= 'alipay';
				break;
			case '265':
				$appid  	= 'wx';
				break;
			default:
				$appid  	= 'qrcode';
				break;
		}

		$paytype_config = M('paytype_config')->where(['channelid'=>$channelid,'status'=>1])->find();
		if(!$paytype_config){
			$this->error('未选择可用的账户类型！');
		}
		$paytype = $paytype_config['paytype'];

		$where = array();
		$where['channelid'] = $channelid;
		$where['signkey'] 	= $signkey;

		$pay_params = M('payparams_list')->where($where)->find();
		if($pay_params){
			$this->error('该二维码已经上传，不能重复！');
		}

		$data = array(
			'userid'  		=> userid(),
			'username'		=> $userinfo['username'],
			'paytype' 		=> $paytype,		
			'channelid' 	=> $channelid,
			'truename'		=> $truename,		
			'mch_id' 		=> $mch_id,	
			'signkey' 		=> $signkey,
			'appid' 		=> $appid,	
			'appsecret' 	=> $appsecret,
			'domain_record' => "无",
			'subject'		=> "无",
			'addtime' 		=> time(),
			'is_personal'	=> 1,
			'check_status' 	=> 0,
			'status' 		=> 0,
		);

		//表示未指定
		$data['select_memberid'] = '0';
		if (M('payparams_list')->add($data)) {
			$this->success(L('添加成功！'));
		} else {
			$this->error(L('添加失败！'));
		}
	}

	//修改支付参数状态
	public function setPayParamsStatus($id, $paypassword)
	{
		// 过滤非法字符----------------S
		if (checkstr($id) || checkstr($paypassword)) {
			$this->error(L('您输入的信息有误！'));
		}
		// 过滤非法字符----------------E

		if (!userid()) {
			$this->error(L('请先登录！'),U('Login/index'));
		}

		if (!check($paypassword, 'password')) {
			$this->error(L('密码格式为6~16位，不含特殊符号！'));
		}

		if (!check($id, 'd')) {
			$this->error(L('参数错误！'));
		}

		$user_paypassword = M('User')->where(array('id' => userid()))->getField('paypassword');
		if (md5($paypassword) != $user_paypassword) {
			if(!empty($user_paypassword)){
				$this->error(L('交易密码错误！'));
			}else{
				$this->error(L('还未设置交易密码，请前往账户中心设置！'));
			}
		}

		$payParams = M('payparams_list')->where(array('userid' => userid(), 'id' => $id))->find();
		if(!$payParams){
			$this->error(L('非法访问！'));
		}else{
			if(!$payParams['check_status']){
				M('payparams_list')->where(array('userid' => userid(), 'id' => $id))->setField('status', 0);
				$this->error(L('该参数未通过审核，请联系平台审核！'));
			}

			$data = array();
			if($payParams['status']){
				$data['status'] = 0;
				$data['low_money_count'] = 0;
				$data['limit_amount_status'] = 0;
				$res = M('payparams_list')->where(array('userid' => userid(), 'id' => $id))->save($data);
			}else{
				$data['status'] = 1;
				$data['max_fail_count']  = 0;
				$res = M('payparams_list')->where(array('userid' => userid(), 'id' => $id))->save($data);
			}
			if ($res) {
				$this->success(L('操作成功！'));
			} else {
				$this->error(L('操作失败！'));
			}
		}
	}

	//检查参数
	public function checkPayParams($channelid){
		// 过滤非法字符----------------S
		if (checkstr($channelid)) {
			$this->error(L('您输入的信息有误！'));
		}

		if (!userid()) {
			$this->error(L('请先登录！'),U('Login/index'));
		}

		if($channelid != 264){
			$this->error(L('暂时不提供该种类型参数的检查！'));
		}

		$list = array();

		$payParams = M('payparams_list')->where(array('userid' => userid(), 'channelid' => $channelid))->select();
		foreach ($payParams as $key => $value) {
			$list['title'] = $value['mch_id'];
			$list['link'] = 'alipays://platformapi/startapp?appId=20000123&actionType=scan&biz_data={"s":"转账","u":"'.$payParams['appid'].'","a":"10","m":"进行转账-success"}';
		}

		$this->assign('list', $list);
		$this->display();
	}

	/////////////////////////////////////
	//// END

	//语音通知
	public function checkNotice(){
		$userid = userid();
		$start_time = time() - 20*60;
		//2天内的订单
		$where = array(
			'userid' => $userid,
			'otype'	 => 2,
			'status' => ['in', '2,8'],
			'rush_status'=> 1,
			'updatetime' => ['gt', $start_time],
		);
		//催单的卖单
        $orderRushSellCount = D('ExchangeOrder')->where($where)->count();
        unset($where['userid']);
        $where['aid'] = $userid;
        $where['otype'] = 1;
        //催单的卖单
        $orderRushBuyCount = D('ExchangeOrder')->where($where)->count();
        //普通订单
        // $start_time = time() - 10*60;
        // unset($where['aid']);
        // unset($where['updatetime']);
        // $where['otype']  = 2;
        // $where['status'] = 2;
        // $where['userid'] = $userid;
        // $where['userid'] = $userid;
        // $where['addtime'] = ['gt', $start_time];
        $orderNormalCount = 0;//D('ExchangeOrder')->where($where)->count();
        //订单数量
        $orderCount = $orderRushSellCount + $orderRushBuyCount + $orderNormalCount;
        //Log::record("checkNotice orderCount= ".$orderCount, Log::INFO);
        $this->ajaxReturn(['status' => 0, 'num' => $orderCount]);
	}

	//添加资金变动日志
    protected function addFinanceLog($orderInfo, $userid, $remark, $plusminus = 0, $optype = 0, $blog=true){

        //添加订单日志
        $mo = M();

        $coin_type      = $orderInfo['type']; //提现类型
        $coin_type_d    = $orderInfo['type'].'d'; //提现类型，冻结
        $num            = $orderInfo['num'];
        $finance_nameid = $orderInfo['orderid'];

        $rs = array();
        $finance = $mo->table('tw_finance')->where(array('userid' => $userid))->order('id desc')->find();
        // 数据处理完的查询（新数据）
        $finance_mum_user_coin = $mo->table('tw_user_coin')->where(array('userid' => $userid))->find();
        $new_coin_num = $finance_mum_user_coin[$coin_type];
        $new_coin_num_d = $finance_mum_user_coin[$coin_type_d]; //冻结金额

        // 数据未处理时的查询（原数据）
        if($plusminus == 1){ //（0减少，1增加） plusminus
            $old_coin_num = $new_coin_num - $num;
            $old_coin_num_d = $new_coin_num_d; //冻结金额
        }else{
            $old_coin_num = $new_coin_num + $num;
            $old_coin_num_d = $new_coin_num_d; //冻结金额
        }

        //变动hash值
        $finance_hash = md5($userid . $old_coin_num . $old_coin_num_d . $num . $new_coin_num . $new_coin_num_d . MSCODE . 'tp3.net.cn');
        
        $finance_num = $old_coin_num + $old_coin_num_d;
        if ($finance['mum'] < $finance_num) {
            $finance_status = (1 < ($finance_num - $finance['mum']) ? 0 : 1);
        } else {
            $finance_status = (1 < ($finance['mum'] - $finance_num) ? 0 : 1);
        }
        
        // 处理资金变更日志-----------------S
        
        $rs[] = $mo->table('tw_finance')->add(array('userid' => $userid, 'coinname' => $coin_type, 'num_a' => $old_coin_num, 'num_b' => $old_coin_num_d , 'num' => $old_coin_num + $old_coin_num_d, 'fee' => $num, 'type' => $plusminus, 'name' => 'mytx_c2c', 'nameid' => $finance_nameid, 'remark' => $remark, 'mum_a' => $new_coin_num, 'mum_b' => $new_coin_num_d, 'mum' => $new_coin_num + $new_coin_num_d, 'move' => $finance_hash, 'addtime' => time(), 'status' => $finance_status));

        //是否记录日志
        if($blog){
            /*
             * 操作位置（0后台，1前台） position
             * 动作类型（参考function.php） optype
             * 资金类型（1人民币） cointype
             * 类型（0减少，1增加） plusminus
             * 操作数据 amount
             */
            $username = $mo->table('tw_user')->where(array('id' => $userid))->getField("username");

            $rs[] = $mo->table('tw_finance_log')->add(array('username' => $username, 'adminname' => $username, 'addtime' => time(), 'plusminus' => $plusminus, 'amount' => $num, 'optype' => $optype, 'position' => 1, 'cointype' => 1, 'old_amount' =>  $old_coin_num, 'new_amount' => $new_coin_num, 'userid' => $userid, 'adminid' => $userid,'addip'=>get_client_ip()));
        }
         // 处理资金变更日志-----------------E

        if(check_arr($rs)){
            return true;
        }else{
            return false;
        }
        return false;
    }
}
?>