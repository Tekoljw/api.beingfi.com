<?php
namespace Admin\Controller;
use Think\Log;

class ExchangeController extends AdminController
{
	public function index()
	{
		$this->display();
	}
	
	// C2C买入记录
	public function mycz($field = NULL, $name = NULL, $status = NULL, $starttime = NULL, $endtime = NULL)
	{
		$where = array();
		/* 用户名--条件 */
		if ($field && $name) {
			if ($field == 'username') {
				$where['userid'] = M('User')->where(array('username' => $name))->getField('id');
			} else {
				$where[$field] = $name;
			}
		}
		// 默认状态
		if ($status == "") {
			$where['_string'] = '(status = 1 OR status = 2)';
		}
		/* 状态--条件 */
		if ($status != '99') {
			if ($status) {
				$where['status'] = $status;
			}
		}

		// 时间--条件
		if (!empty($starttime) && empty($endtime)) {
			$starttime = strtotime($starttime);
			$where['addtime'] = array('EGT',$starttime);
		} else if (empty($starttime) && !empty($endtime)) {
			$endtime = strtotime($endtime);
			$where['addtime'] = array('ELT',$endtime);
		} else if (!empty($starttime) && !empty($endtime)) {
			$starttime = strtotime($starttime);
			$endtime = strtotime($endtime);
			$where['addtime'] =  array(array('EGT',$starttime),array('ELT',$endtime));
		}

		$where['otype'] = 1; // 订单类型

		//今日开始时间
		$today_start = strtotime(date("Y-m-d 00:00:00"));
		$now_time = time();
		
		//Log::record('mycz add函数 111111111', Log::INFO);

		// 订单统计
		//交易中
		$tongji['finance'] = D('ExchangeOrder')->where(array('otype'=>1,'status'=>0))->sum('mum') * 1;
		//Log::record('mycz add函数 2222222222', Log::INFO);
		//待处理
		$tongji['dcl'] = D('ExchangeOrder')->where(array('otype'=>1,'status'=>1))->sum('mum') * 1;
		//Log::record('mycz add函数 3333333333', Log::INFO);
		//处理中
		$tongji['financing'] = D('ExchangeOrder')->where(array('otype'=>1,'status'=>2))->sum('mum') * 1;
		//Log::record('mycz add函数 4444444444', Log::INFO);
		//已完成
		$tongji['ywc'] = D('ExchangeOrder')->where(array('otype'=>1,'status'=>3))->sum('mum') * 1;
		//Log::record('mycz add函数 555555555', Log::INFO);
		//撤销
		$tongji['cx'] = D('ExchangeOrder')->where(array('otype'=>1,'status'=>8))->sum('mum') * 1;
		//Log::record('mycz add函数 666666666', Log::INFO);
		//今日手续费
		$tongji['today_profit'] = D('ExchangeOrder')->where(array('otype'=>1,'status'=>3,'addtime'=>['between',[$today_start,$now_time]]))->sum('fee') * 1;
		//Log::record('mycz add函数 777777777', Log::INFO);
		//总手续费
		$tongji['total_profit'] = D('ExchangeOrder')->where(array('otype'=>1,'status'=>3))->sum('fee') * 1;
		//Log::record('mycz add函数 888888888', Log::INFO);
		//今日订单数量
		$tongji['today_order_count'] = D('ExchangeOrder')->where(array('otype'=>1,'addtime'=>['between',[$today_start,$now_time]]))->count();
		//Log::record('mycz add函数 999999999', Log::INFO);
		//今日成功订单数量
		$tongji['today_order_success'] = D('ExchangeOrder')->where(array('otype'=>1,'status'=>3,'addtime'=>['between',[$today_start,$now_time]]))->count();
		//Log::record('mycz add函数 10101010', Log::INFO);
		//今日订单金额
		$tongji['today_order_sum'] = D('ExchangeOrder')->where(array('otype'=>1,'addtime'=>['between',[$today_start,$now_time]]))->sum('mum')*1;
		//Log::record('mycz add函数 1212121212', Log::INFO);
		//今日成功金额
		$tongji['today_success_sum'] = D('ExchangeOrder')->where(array('otype'=>1,'status'=>3,'addtime'=>['between',[$today_start,$now_time]]))->sum('mum')*1;
		//Log::record('mycz add函数 1313131313', Log::INFO);
		//总订单数量
		$tongji['total_order_count'] = D('ExchangeOrder')->where(array('otype'=>1))->count();
		//Log::record('mycz add函数 1414141414', Log::INFO);
		//总成功订单数量
		$tongji['total_order_success'] = D('ExchangeOrder')->where(array('otype'=>1,'status'=>3))->count();
		//Log::record('mycz add函数 1515151515', Log::INFO);
		//符合条件的手续费
		$tongji['condition_profit'] = D('ExchangeOrder')->where($where)->sum('fee');
		//符合条件的订单数量
		$tongji['condition_order_count'] = D('ExchangeOrder')->where($where)->count();
		//符合条件的金额
		$tongji['condition_order_sum'] = D('ExchangeOrder')->where($where)->sum('mum')*1;
		$this->assign('tongji', $tongji);

		$count = D('ExchangeOrder')->where($where)->count();
		$Page = new \Think\Page($count, 15);
		$show = $Page->show();

		/** 检查设置条件 **/
        $exchange_config = M('exchange_config')->where(array('id' => 1))->find();
        $config_price = $exchange_config['mycz_uprice'];
		
		$list = D('ExchangeOrder')->where($where)->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
		
		foreach ($list as $k => $v) {
			$aids = '';
			$list[$k]['username'] = M('User')->where(array('id' => $v['userid']))->getField('username');
			$aids = M('exchange_agent')->where(array('id' => $v['aid']))->field("id,aid")->find();
			$list[$k]['agent'] = M('User')->where(array('id' => $aids['aid']))->getField('username');

			$sell_fee = $v["all_scale"] > 0 ? $v["all_scale"]+$v["fee"] : $v["scale_amount"]+$v["fee"];
			$list[$k]['sell_fee'] = $sell_fee;
			$obtain_num = ($v['mum']+$sell_fee)/$config_price;
			$list[$k]['obtain_num'] = $obtain_num < $v['num'] ? $obtain_num:$v['num'];
		}
		
		$this->assign('list', $list);
		$this->assign('page', $show);
		$this->display();
	}
	
	// C2C充值确认成功
	public function myczQueren()
	{
		$orderid = $_GET['orderid'];

		if (empty($orderid)) {
			$this->error('请选择要操作的数据!');
		}

		$mycz = D('ExchangeOrder')->where(array('orderid' => $orderid))->find();
		if ($mycz['status'] > 2) {
			$this->error('已经处理，禁止再次操作！');
		}

		if(!isset($mycz['aid']) || !$mycz['aid']){
			$this->error('订单没有匹配对应的卖出方，不能操作！');
		}
		
		$fp = fopen("lockcz.txt", "w+");
		if (flock($fp,LOCK_EX | LOCK_NB))
		{
			$rs = array();
			$mycz['op_userid'] = UID;
			$mycz['is_admin'] = true;
			$rs[] = R("Pay/PayExchange/confirmC2COrderWithOrderInfo", array($mycz));

			$mo = M();
			$mo->execute('set autocommit=0');
			$mo->execute('lock tables tw_user_coin write, tw_user read, tw_exchange_config read, tw_market read');
			
			$types = $mycz['type']; //充值类型
			$typed = $mycz['type'].'d'; //充值类型，冻结
			$nums = $mycz['num']; //充值数量
			$mums = $mycz['mum']; //实际充值数量
			
			// 获取用户信息
			$user_info = $mo->table('tw_user')->where(array('id' => $mycz['userid']))->find();
			// 首次充值赠送币
			$configs = M('exchange_config')->where(array('id' => 1))->find();

			if($configs['give_type'] <= 0){
				$mo->execute('commit');
				$mo->execute('unlock tables');
				$this->success('操作成功!');
			}

			$qbsong_num = $configs['xnb_mr_song_tiaojian']; // 充值条件，满足此金额奖励才能执行。
			$coin_name = $configs['xnb_mr_song']; // 赠送币种
			//推荐层级
			//一代
			$invit_1 = $datas['invit_1'];
			$song_num_1 = $configs['song_num_1'];
			//二代
			$invit_2 = $datas['invit_2'];
			$song_num_2 = $configs['song_num_2'];
			//三代
			$invit_3 = $datas['invit_3'];
			$song_num_3 = $configs['song_num_3'];

			// 查询市场最新成交价
			$markets = M('market')->where(array('name'=>$coin_name.'_'.Anchor_CNY))->field('new_price')->find();
			if ($markets['new_price']) {$new_price = $markets['new_price'];} else {$new_price = 1.00;}
			$user_coin_num = ($mums * ($configs['xnb_mr_song_num'] / 100)) / $new_price; //赠送数量（（充值金额*(赠送比例/100)）/ 赠送币种当前价格）
			
			if ($configs['give_type'] == 1 && $configs['xnb_mr_song_num'] > 0 && $nums >= $qbsong_num)
			{
				if (!(M('finance_log')->where(array('userid'=>$mycz['userid'],'description'=>array('like',"%首次充值赠送%")))->find())) {
					// 判断是否首次充值赠送
					if ($configs['grant_type'] == 1) { 
						/* 锁定发放奖励 */
					}elseif($configs['grant_type'] == 2){  //推荐人奖励

						// 赠送邀请人充值奖励
						if($song_num_1 > 0 && $invit_1 > 0){
							$coin_num_1 = $user_coin_num * $song_num_1 / 100;
							$rs[] = $mo->table('tw_invit')->add(array('userid' => $invit_1, 'invit' => $ids, 'name' => '一代首充赠送', 'type' => 0, 'num' => 0, 'mum' => 0, 'fee' => $coin_num_1, 'addtime' => time(), 'status' => 0,'coin'=>strtoupper($coin_name)));
						}
						if($song_num_2 > 0 && $invit_2 > 0){
							$coin_num_2 = $user_coin_num * $song_num_2 / 100;
							$rs[] = $mo->table('tw_invit')->add(array('userid' => $invit_2, 'invit' => $ids, 'name' => '二代首充赠送', 'type' => 0, 'num' => 0, 'mum' => 0, 'fee' => $coin_num_2, 'addtime' => time(), 'status' => 0,'coin'=>strtoupper($coin_name)));
						}
						if($song_num_3 > 0 && $invit_3 > 0){
							$coin_num_3 = $user_coin_num * $song_num_3 / 100;
							$rs[] = $mo->table('tw_invit')->add(array('userid' => $invit_3, 'invit' => $ids, 'name' => '三代首充赠送', 'type' => 0, 'num' => 0, 'mum' => 0, 'fee' => $coin_num_3, 'addtime' => time(), 'status' => 0,'coin'=>strtoupper($coin_name)));
						}

					} else {

						//旧的货币数量
						$old_user_coin = $mo->table('tw_user_coin')->where(array('userid' => $mycz['userid']))->find();
						/* 发放充值人奖励 */
						$rs[] = $mo->table('tw_user_coin')->where(array('userid' => $mycz['userid']))->setInc($coin_name, $user_coin_num);
						
						// 处理资金变更日志-----------------S
						$rs[] = $mo->table('tw_finance_log')->add(array('username' => $user_info['username'], 'adminname' => session('admin_username'), 'addtime' => time(), 'plusminus' => 1, 'amount' => $user_coin_num, 'description' => '首次充值赠送'.$coin_name, 'optype' => 28, 'cointype' => 3, 'old_amount' => $old_user_coin[$coin_name], 'new_amount' => $old_user_coin[$coin_name]+$user_coin_num, 'userid' => $user_info['id'], 'adminid' => session('admin_id'),'addip'=>get_client_ip()));
						// 处理资金变更日志-----------------E
					}
				}
			} else if ($configs['give_type'] == 2 && $configs['xnb_mr_song_num'] > 0 && $nums >= $qbsong_num) {
				// 判断是否每次充值赠送
				if ($configs['grant_type'] == 1) {
					/* 锁定发放奖励 */
				}elseif($configs['grant_type'] == 2){  //推荐人奖励

						// 赠送邀请人充值奖励
						if($song_num_1 > 0 && $invit_1 > 0){
							$coin_num_1 = $user_coin_num * $song_num_1 / 100;
							$rs[] = $mo->table('tw_invit')->add(array('userid' => $invit_1, 'invit' => $ids, 'name' => '一代充值赠送', 'type' => 1, 'num' => 0, 'mum' => 0, 'fee' => $coin_num_1, 'addtime' => time(), 'status' => 0,'coin'=>strtoupper($coin_name)));
						}
						if($song_num_2 > 0 && $invit_2 > 0){
							$coin_num_2 = $user_coin_num * $song_num_2 / 100;
							$rs[] = $mo->table('tw_invit')->add(array('userid' => $invit_2, 'invit' => $ids, 'name' => '二代充值赠送', 'type' => 1, 'num' => 0, 'mum' => 0, 'fee' => $coin_num_2, 'addtime' => time(), 'status' => 0,'coin'=>strtoupper($coin_name)));
						}
						if($song_num_3 > 0 && $invit_3 > 0){
							$coin_num_3 = $user_coin_num * $song_num_3 / 100;
							$rs[] = $mo->table('tw_invit')->add(array('userid' => $invit_3, 'invit' => $ids, 'name' => '三代充值赠送', 'type' => 1, 'num' => 0, 'mum' => 0, 'fee' => $coin_num_3, 'addtime' => time(), 'status' => 0,'coin'=>strtoupper($coin_name)));
						}
				} else {

					//旧的货币数量
					$old_user_coin = $mo->table('tw_user_coin')->where(array('userid' => $mycz['userid']))->find();
					/* 发放充值人奖励 */
					$rs[] = $mo->table('tw_user_coin')->where(array('userid' => $mycz['userid']))->setInc($coin_name, $user_coin_num);
					
					// 处理资金变更日志-----------------S
					$rs[] = $mo->table('tw_finance_log')->add(array('username' => $user_info['username'], 'adminname' => session('admin_username'), 'addtime' => time(), 'plusminus' => 1, 'amount' => $user_coin_num, 'description' => '充值赠送'.$coin_name, 'optype' => 28, 'cointype' => 3, 'old_amount' => $old_user_coin[$coin_name], 'new_amount' => $old_user_coin[$coin_name]+$user_coin_num, 'userid' => $user_info['id'], 'adminid' => session('admin_id'),'addip'=>get_client_ip()));
					// 处理资金变更日志-----------------E
				}
			}

			operation_log(UID, 1, "C2C订单确认成功orderid = ".$mycz['orderid']);
			if (check_arr($rs)) {
				$mo->execute('commit');
				$mo->execute('unlock tables');
				$message="操作成功";
				$res=1;
			} else {
				$mo->execute('rollback');
				$message="操作失败";
				$res=0;
			}
			flock($fp,LOCK_UN);
		} else {
			$message="请不要重复提交";
			$res=0;
		}
		fclose($fp);
		if ($res == 1) {
			$this->success($message);
		} else {
			$this->error($message);
		}
	}

	// C2C充值处理
	public function myczChuli()
	{
		$orderid = $_GET['orderid'];
		if (empty($orderid)) {
			$this->error('请选择要操作的数据!');
		}

		operation_log(UID, 1, "C2C充值确认开始处理orderid={$orderid}");
		if (D('ExchangeOrder')->where(array('orderid' => $orderid))->save(array('status' => 2,'endtime' => time()))) {
			$this->success('操作成功！');
		} else {
			$this->error('操作失败！');
		}
	}
	
	// C2C充值撤销
	public function myczChexiao()
	{
		$orderid = $_GET['orderid'];
		if (empty($orderid)) {
			$this->error('请选择要操作的数据!');
		}

		operation_log(UID, 1, "C2C充值确认撤销orderid={$orderid}");

		$order_info = D('ExchangeOrder')->where(array('orderid' => $orderid))->find();
		if($order_info['status'] > 2){
			$this->error('处理完成订单不能撤销！');
		}

		if($order_info['status'] > 1 && ($order_info['updatetime'] + 24*60*60) >= time()){
			$this->error('在处理订单24小时内不能撤销！');
		}
		$rs = R("Pay/PayExchange/cancelC2COrderWithOrderInfo", array($order_info));

		if ($rs === true) {
			$this->success('操作成功！');
		} else {
			$this->error($rs['msg']?$rs['msg']:'操作失败！');
		}
	}

	// C2C买入惩罚
	public function myczPunishment()
	{
		$orderid = $_GET['orderid'];
		if (empty($orderid)) {
			$this->error('请选择要操作的数据!');
		}
		operation_log(UID, 1, "C2C订单惩罚orderid={$orderid}");

		$order_info = D('ExchangeOrder')->where(array('orderid' => $orderid))->find();
		if($order_info['status'] == 3 || $order_info['status'] == 8){
			if($order_info['punishment_level'] == 1){
				$this->error('订单已经惩罚！');
			}
			$buy_userid = $order_info['userid'];
			$coin_type = $order_info['type'];
			$rs = array();
			$scale_amount = isset($order_info['scale_amount'])?$order_info['scale_amount']:0;
			if($scale_amount > 0){
				//惩罚2倍优惠
				$reduce_num = 2*$scale_amount;
				$rs[] = M('user_coin')->where(array('userid' => $buy_userid))->setDec($coin_type, $reduce_num);
				$remarks = 'C2C市场'.$coin_type.'-买入订单的惩罚';
	            $rs[] = $this->addC2COrderFinanceLog($order_info, $reduce_num, $buy_userid, $remarks, 0, 32);
			}

			$rs[] = D('ExchangeOrder')->where(array('orderid' => $orderid))->save(['punishment_level'=>1]);
			if (check_arr($rs)) {
				$this->success('操作成功！');
			} else {
				$this->error($rs['msg']?$rs['msg']:'操作失败！');
			}
		}else{
			$this->error('只有完成和取消订单才能惩罚！');
		}
	}

	// C2C买入订单取消并惩罚
	public function myczCXAndPunishment()
	{
		$orderid = $_POST['orderid'];
		if (empty($orderid)) {
			$this->error('请选择要操作的数据!');
		}
		$password = $_POST['password'];
		$admin = M('Admin')->where(array('id' => UID))->find();
		if ($admin['password'] != md5($password)) {
			$this->error('密码错误!');
		}

		operation_log(UID, 1, "C2C订单取消并惩罚orderid={$orderid}");

		$order_info = D('ExchangeOrder')->where(array('orderid' => $orderid))->find();
		if($order_info['status'] != 3){
			$this->error('只有完成的订单才能取消并惩罚！');
		}

		/** 检查设置条件 **/
        $exchange_config = M('exchange_config')->where(array('id' => 1))->find();
        if($exchange_config['mytx_status'] == 0){ //是否开启了CNC交易
            $this->error('C2C交易未开放！');
        }
        //单价
        $price = $exchange_config['mycz_uprice'];

		$buy_userid = $order_info['userid'];
		$coin_type = $order_info['type'];
		$all_scale  = isset($order_info['all_scale'])?$order_info['all_scale']:0;
        $fee        = isset($order_info['fee'])?$order_info['fee']:0;
        //总体手续费
        $total_fee  = $all_scale + $fee;
		$rs = array();
		$scale_amount = isset($order_info['scale_amount'])?$order_info['scale_amount']:0;
		$reduce_num = 0;
		if($scale_amount > 0){
	        //惩罚2倍优惠，同时并返回完成的金额
			$reduce_num = (2*$scale_amount + $order_info['mum']) / $price;
			$rs[] = M('user_coin')->where(array('userid' => $buy_userid))->setDec($coin_type, $reduce_num);
		}else{
	        //惩罚2倍优惠，同时并返回完成的金额
			$reduce_num = ($order_info['mum'] + $total_fee) / $price;
			$rs[] = M('user_coin')->where(array('userid' => $buy_userid))->setDec($coin_type, $reduce_num);
		}

		$remarks = 'C2C市场'.$coin_type.'-买入订单的取消并惩罚';
        $rs[] = $this->addC2COrderFinanceLog($order_info, $reduce_num, $buy_userid, $remarks, 0, 33);

		$rs[] = D('ExchangeOrder')->where(array('orderid' => $orderid))->save(['punishment_level'=>2]);
		//重新激活订单，并尝试更换买家
		$rs[] = $msg = R("Pay/PayExchange/autoResetAvtiveC2COrder", array($order_info));
		if (check_arr($rs)) {
			$this->success('操作成功！');
		} else {
			$this->error($msg['msg']?$msg['msg']:'操作失败！');
		}
	}

	// C2C买入重新指定买入用户
	public function myczResetBuyUser()
	{
		$orderid = $_POST['orderid'];
		if (empty($orderid)) {
			$this->error('请选择要操作的数据!');
		}
		$buy_userid = $_POST['buy_userid'];
		if (empty($buy_userid)) {
			$this->error('buy_userid未填写!');
		}
		operation_log(UID, 1, "C2C买入订单重新指定买入用户orderid={$orderid},buyuserid={$buy_userid}");

		$order_info = D('ExchangeOrder')->where(array('orderid' => $orderid))->find();
		$rs = R("Pay/PayExchange/resetC2COrderBuyUser", array($order_info, $buy_userid));
		if ($rs===true) {
			$this->success('操作成功！');
		} else {
			$this->error($rs['msg']?$rs['msg']:'操作失败！');
		}
	}
	
	// C2C卖出记录
	public function mytx($field = NULL, $name = NULL, $status = NULL, $starttime = 0, $endtime = 0)
	{
		$where = array();
		/* 用户名--条件 */
		if ($field && $name) {
			if ($field == 'username') {
				$where['userid'] = M('User')->where(array('username' => $name))->getField('id');
			} else {
				$where[$field] = $name;
			}
		}
		// 默认状态
		if ($status == "") {
			$where['_string'] = '(status = 1 OR status = 2)';
		}
		/* 状态--条件 */
		if ($status != '99') {
			if ($status) {
				$where['status'] = $status;
			}
		}

		// 时间--条件
		if (!empty($starttime) && empty($endtime)) {
			$starttime = strtotime($starttime);
			$where['addtime'] = array('EGT',$starttime);
		} else if (empty($starttime) && !empty($endtime)) {
			$endtime = strtotime($endtime);
			$where['addtime'] = array('ELT',$endtime);
		} else if (!empty($starttime) && !empty($endtime)) {
			$starttime = strtotime($starttime);
			$endtime = strtotime($endtime);
			$where['addtime'] =  array(array('EGT',$starttime),array('ELT',$endtime));
		}

		$where['otype'] = 2; // 订单类型

		//今日开始时间
		$today_start = strtotime(date("Y-m-d 00:00:00"));
		$now_time = time();
		
		//Log::record('mycz add函数 111111111', Log::INFO);

		// 订单统计
		$tongji['finance'] = D('ExchangeOrder')->where(array('otype'=>2,'status'=>0))->sum('mum') * 1;
		//Log::record('mycz add函数 2222222222', Log::INFO);
		$tongji['dcl'] = D('ExchangeOrder')->where(array('otype'=>2,'status'=>1))->sum('mum') * 1;
		//Log::record('mycz add函数 3333333333', Log::INFO);
		$tongji['financing'] = D('ExchangeOrder')->where(array('otype'=>2,'status'=>2))->sum('mum') * 1;
		//Log::record('mycz add函数 4444444444', Log::INFO);
		$tongji['ywc'] = D('ExchangeOrder')->where(array('otype'=>2,'status'=>3))->sum('mum') * 1;
		//Log::record('mycz add函数 5555555555', Log::INFO);
		$tongji['cx'] = D('ExchangeOrder')->where(array('otype'=>2,'status'=>8))->sum('mum') * 1;
		//Log::record('mycz add函数 6666666666', Log::INFO);
		//今日利润
		$tongji['today_profit'] = D('ExchangeOrder')->where(array('otype'=>2,'status'=>3,'addtime'=>['between',[$today_start,$now_time]]))->sum('fee') * 1;
		//Log::record('mycz add函数 7777777777', Log::INFO);
		//总手利润
		//历史总费率
		$total_c2c_history_fee = M('exchange_order_history_sum')->sum('fee_sum');
		$total_c2c_history_fee = $total_c2c_history_fee?$total_c2c_history_fee:0;
		//历史总优惠
		$total_c2c_history_scale = M('exchange_order_history_sum')->sum('scale_amount_sum');
		$total_c2c_history_scale = $total_c2c_history_scale?$total_c2c_history_scale:0;
		$tongji['total_profit'] = $total_c2c_history_fee - $total_c2c_history_scale + $tongji['today_profit'];
		
		//Log::record('mycz add函数 8888888888', Log::INFO);
		//今日订单数量
		$tongji['today_order_count'] = D('ExchangeOrder')->where(array('otype'=>2,'addtime'=>['between',[$today_start,$now_time]]))->count();
		//Log::record('mycz add函数 9999999999', Log::INFO);
		//今日成功订单数量
		$tongji['today_order_success'] = D('ExchangeOrder')->where(array('otype'=>2,'status'=>3,'addtime'=>['between',[$today_start,$now_time]]))->count();
		//Log::record('mycz add函数 1010101010', Log::INFO);
		//今日订单金额
		$tongji['today_order_sum'] = D('ExchangeOrder')->where(array('otype'=>2,'addtime'=>['between',[$today_start,$now_time]]))->sum('mum')*1;
		//Log::record('mycz add函数 1212121212', Log::INFO);
		//今日成功金额
		$tongji['today_success_sum'] = D('ExchangeOrder')->where(array('otype'=>2,'status'=>3,'addtime'=>['between',[$today_start,$now_time]]))->sum('mum')*1;
		//Log::record('mycz add函数 1313131313', Log::INFO);
		//总订单数量
		$tongji['total_order_count'] = D('ExchangeOrder')->where(array('otype'=>2))->count();
		//Log::record('mycz add函数 1414141414', Log::INFO);
		//总成功订单数量
		$tongji['total_order_success'] = D('ExchangeOrder')->where(array('otype'=>2,'status'=>3))->count();
		//Log::record('mycz add函数 1515151515', Log::INFO);
		//符合条件的手续费
		$tongji['condition_profit'] = D('ExchangeOrder')->where($where)->sum('fee');
		//符合条件的订单数量
		$tongji['condition_order_count'] = D('ExchangeOrder')->where($where)->count();
		//符合条件的金额
		$tongji['condition_order_sum'] = D('ExchangeOrder')->where($where)->sum('mum')*1;
		$this->assign('tongji', $tongji);

		$count = D('ExchangeOrder')->where($where)->count();
		$Page = new \Think\Page($count, 15);
		$show = $Page->show();
		
		$list = D('ExchangeOrder')->where($where)->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();

		/** 检查设置条件 **/
        $exchange_config = M('exchange_config')->where(array('id' => 1))->find();
        $config_price = $exchange_config['mytx_uprice'];

		foreach ($list as $k => $v) {
			$matchs ='';
			$aids = '';
			$list[$k]['username'] = M('User')->where(array('id' => $v['userid']))->getField('username');
			$aids = M('exchange_agent')->where(array('id' => $v['aid']))->field("id,aid")->find();
			$list[$k]['agent'] = M('User')->where(array('id' => $aids['aid']))->getField('username');

			if(strlen($v['bankcard']) > 10){
				preg_match('/([\d]{4})([\d]{4})([\d]{4})([\d]{4})([\d]{0,})?/',$v['bankcard'],$match);
				foreach ($match as $kb => $vo) { if($kb == 0){}else{$matchs .= $vo.' ';} }
			}else{
				$matchs = $v['bankcard'];
			}

			$list[$k]['bankname'] = '姓名：'.$v['truename'].'&nbsp； '.'银行名称：'.$v['bank'].'<br>'.'银行账号：<b style="font-size:15px;color:#3498db;">'.$matchs.'</b><br>'.'开户行：'.$v['bankprov'].' - '.$v['bankcity'].' - '.$v['bankaddr'];

			$buy_fee = $v["all_scale"] > 0 ? $v["all_scale"]+$v["fee"] : $v["scale_amount"]+$v["fee"];
			$list[$k]['buy_fee'] = $buy_fee;
			$obtain_num = ($v['mum']-$buy_fee)/$config_price;
			$list[$k]['obtain_num'] = $obtain_num < $v['num'] ? $obtain_num:$v['num'];

			switch ($v['punishment_level']) {
        		case '3':
        			$punishment_level = '延迟30分钟优惠0';
        			break;
        		case '2':
        			$punishment_level = '延迟10分钟优惠10%';
        			break;
        		case '1':
        			$punishment_level = '延迟5分钟优惠50%';
        			break;
        		default:
        			$punishment_level = '全额优惠';
        			break;
        	}
        	$list[$k]['punishment_level'] = $punishment_level;
		}
		
		$this->assign('list', $list);
		$this->assign('page', $show);
		$this->display();
	}
	
	// C2C提现确认成功
	public function orderQueren()
	{
		$orderid = $_GET['orderid'];
		if (empty($orderid)) {
			$this->error('请选择要操作的数据!');
		}

		$order_info = D('ExchangeOrder')->where(array('orderid' => $orderid))->find();
		$order_info['op_userid'] = UID;
		$order_info['is_admin'] = true;
		operation_log(UID, 1, "C2C订单确认成功orderid = ".$order_info['orderid']);
		$res = R("Pay/PayExchange/confirmC2COrderWithOrderInfo", array($order_info));
		if ($res === true) {
			$this->success('操作成功！');
		} else {
			$this->error($res['msg']?$res['msg']:'操作失败！');
		}
	}
	
	// C2C提现处理
	public function mytxChuli()
	{
		$orderid = $_GET['orderid'];
		if (empty($orderid)) {
			$this->error('请选择要操作的数据!');
		}

		operation_log(UID, 1, "C2C提现确认开始处理orderid={$orderid}");
		if (D('ExchangeOrder')->where(array('orderid' => $orderid))->save(array('status' => 2,'endtime' => time()))) {
			$this->success('操作成功！');
		} else {
			$this->error('操作失败！');
		}
	}
	
	// C2C提现撤销
	public function mytxChexiao()
	{
		$orderid = $_GET['orderid'];
		if (empty($orderid)) {
			$this->error('请选择要操作的数据!');
		}

		operation_log(UID, 1, "C2C提现撤销order={$orderid}");
		$order_info = D('ExchangeOrder')->where(array('orderid' => $orderid))->find();
		if($order_info['status'] > 2){
			$this->error('处理完成订单不能撤销！');
		}

		if($order_info['status'] > 1 && ($order_info['updatetime'] + 24*60*60) >= time()){
			$this->error('在处理订单24小时内不能撤销！');
		}
		$order_info['is_admin'] = true;
		$rs = R("Pay/PayExchange/cancelC2COrderWithOrderInfo", array($order_info));

		if ($rs === true) {
			$this->success('操作成功！');
		} else {
			$this->error($rs['msg']?$rs['msg']:'操作失败！');
		}
	}
	
	// C2C订单重置为成功
	public function mytxResetQueenOrder($orderid, $password)
	{
		if (empty($orderid) || empty($password)) {
			$this->error('请选择要操作的数据!');
		}

		$admin = M('Admin')->where(array('id' => UID))->find();
		if ($admin['password'] != md5($password)) {
			$this->error('密码错误!');
		}

		$order_info = D('ExchangeOrder')->where(array('orderid' => $orderid))->find();
		operation_log(UID, 1, "C2C重置为成功orderid=".$order_info['orderid']);

		$rs = R("Pay/PayExchange/resetConfirmC2COrderWithOrderInfo", array($order_info));
		if ($rs === true) {
			$this->success('操作成功！');
		} else {
			$this->error($rs['msg']?$rs['msg']:'操作失败！');
		}
	}
	
	// C2C配置
	public function config()
	{
		$this->data = M('exchange_config')->where(array('id' => 1))->find();
		
		$this->display();
	}
	
	public function configedit()
	{
		if (APP_DEMO) {
			$this->error('测试站暂时不能修改！');
		}
		
		header('Content-Type:text/html;charset=UTF-8');
        //$data = I('post.');
		$_POST['mycz_prompt'] = htmlspecialchars($_POST['mycz_prompt']);
		$_POST['mytx_prompt'] = htmlspecialchars($_POST['mytx_prompt']);
		
		if($_POST['auto_df_buy_min_rate'] > 1){
			$this->error('自动买入的手续费率比例不能高于1！');
		}
		if($_POST['auto_df_platfrom_rate'] > 1){
			$this->error('平台分成比例不能高于1！');
		}	

		operation_log(UID, 1, "C2C交易配置编辑");
		if (M('exchange_config')->where(array('id' => 1))->save($_POST)) {
			$this->success('修改成功！');
		} else {
			$this->error('修改失败');
		}
	}
	
	// C2C代理商
	public function agent()
	{
		$where = array();
		/* 用户名--条件 */
		if ($field && $name) {
			if ($field == 'username') {
				$where['userid'] = M('User')->where(array('username' => $name))->getField('id');
			} else {
				$where[$field] = $name;
			}
		}
		/* 状态--条件 */
		if ($status) {
			$where['status'] = $status - 1;
		}
		/* 时间--条件 */
		if (!empty($starttime) && empty($endtime)) {
			$starttime = strtotime($starttime);
			$where[$time_type] = array('EGT',$starttime);

		} else if (empty($starttime) && !empty($endtime)) {
			$endtime = strtotime($endtime);
			$where[$time_type] = array('ELT',$endtime);

		} else if (!empty($starttime) && !empty($endtime)) {
			$starttime = strtotime($starttime);
			$endtime = strtotime($endtime);
			$where[$time_type] =  array(array('EGT',$starttime),array('ELT',$endtime));
		}


		$count = M('exchange_agent')->where($where)->count();
		$Page = new \Think\Page($count, 15);
		$show = $Page->show();
		$list = M('exchange_agent')->where($where)->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
		
		$i=0;
		foreach ($list as $k => $v) {
			$i++;
			$list[$k]['username'] = M('User')->where(array('id' => $v['aid']))->getField('username');
			preg_match('/([\d]{4})([\d]{4})([\d]{4})([\d]{4})([\d]{0,})?/',$v['bankcard'],$match);
			foreach ($match as $kb => $vo) { if($kb == 0){}else{$matchs[$i] .= $vo.' ';} }
			
			$list[$k]['bankinfo'] = '银行名称：'.$v['bank'].'<br>'.'银行账号：<b style="font-size:15px;color:#3498db;">'.$matchs[$i].'</b><br>'.'开户行：'.$v['bankprov'].' - '.$v['bankcity'].' - '.$v['bankaddr'];
		}

		$this->assign('list', $list);
		$this->assign('page', $show);
		
		$this->display();
	}
	
	// C2C代理商 - 新增
	public function agentEdit($id = NULL)
	{
		if (empty($_POST)) {
			$liste = '';
			
			if ($id) {
				$this->data = M('exchange_agent')->where(array('id' => trim($id)))->find();
			} else {
				$this->data = null;
			}
			
			$this->display();
		} else {
			if (APP_DEMO) {
				$this->error('测试站暂时不能修改！');
			}

			if ($_POST['id']) {
				operation_log(UID, 1, "C2C交易代理商编辑");
				$rs = M('exchange_agent')->save($_POST);
			} else {
				$_POST['addtime'] = time();
				operation_log(UID, 1, "C2C交易代理商添加");
				$rs = M('exchange_agent')->add($_POST);
			}

			if ($rs) {
				$this->success('编辑成功！');
			} else {
				$this->error('编辑失败！');
			}
		}	
	}

	public function agentStatus($id = NULL, $type = NULL, $mobile = 'exchange_agent')
	{
		if (APP_DEMO) {
			$this->error('测试站暂时不能修改！');
		}

		if (empty($id)) {
			$this->error('参数错误！');
		}
		if (empty($type)) {
			$this->error('参数错误type！');
		}

		if (strpos(',', $id)) {
			$id = implode(',', $id);
		}
		
		$where['id'] = array('in', $id);

		switch (strtolower($type)) {
			case 'forbid':
				$data = array('status' => 0);
				operation_log(UID, 1, "C2C交易代理商状态设置禁止");
				break;
			case 'resume':
				$data = array('status' => 1);
				operation_log(UID, 1, "C2C交易代理商状态设置开启");
				break;
			case 'repeal':
				$data = array('status' => 2, 'endtime' => time());
				operation_log(UID, 1, "C2C交易代理商状态设置废除");
				break;
			// case 'del':
			// 	$data = array('status' => -1);
			// 	operation_log(UID, 1, "C2C交易代理商状态设置删除");
			// 	break;
			case 'del':
				operation_log(UID, 1, "C2C交易代理商删除");
				if (M($mobile)->where($where)->delete()) {
					$this->success('操作成功！');
				} else {
					$this->error('操作失败！');
				}
				break;

			default:
				$this->error('非法参数！');
		}

		if (M($mobile)->where($where)->save($data)) {
			$this->success('操作成功！');
		} else {
			$this->error('操作失败！');
		}
	}
	
	// 监控信息列表（wave & kbz）
	public function paymentRecord($field = NULL, $name = NULL, $status = NULL)
	{
		$where = array();
		if ($field && $name) {
			$where[$field] = $name;
		}
		/* 状态--条件 */
		if ($status != '99' && $status !== NULL) {
			$where['status'] = $status;
		}

		$count = M('exchange_payment_record')->where($where)->count();
		$Page = new \Think\Page($count, 15);
		$show = $Page->show();
		
		$list = M('exchange_payment_record')->where($where)->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
		
		$this->assign('list', $list);
		$this->assign('page', $show);
		$this->display();
	}
	
	// 监控信息删除
	public function paymentRecordEdit($id = NULL, $name = NULL, $status = NULL)
	{
	    if (empty($id)) {
			$this->error('参数错误！');
		}
		
		$paymentInfo = M('exchange_payment_record')->where(['id' => $id])->find();
		if ($paymentInfo['status'] == 1) {
		    $this->error('请勿重复操作！');
		}
		
		operation_log(UID, 1, "监控信息修改状态");
		if (M('exchange_payment_record')->where(['id' => $id])->save(['status' => 1, 'dealtime' => time()])) {
			$this->success('操作成功！');
		} else {
			$this->error('操作失败！');
		}
		
	    
	}
}
?>