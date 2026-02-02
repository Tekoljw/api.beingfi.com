<?php
namespace Home\Controller;

class FinanceController extends HomeController
{
	protected function _initialize()
	{
		parent::_initialize();
		$allow_action=array("index","financeInfo","excelC2COrderInfo","myzr","myzc","upmyzc","mywt","mycj","mytj","mywd","myjp","myyj","mydh","upmydh","invite","myfh","myfh_jywk","myfh_cbfh");
		if(!in_array(ACTION_NAME,$allow_action)){
			$this->error(L("非法操作！"));
		}
		
		//获取用户信息
		$backstage = M('User')->where(array('id' => userid()))->field('backstage')->find();
		$this->assign('backstage', $backstage['backstage']);
	}

	public function index()
	{
		if (!userid()) {
			redirect('/Login/index.html');
		}
		
		//获取用户信息
		$User = M('User')->where(array('id' => userid()))->find();
		$this->assign('user', $User);
		
		$UserCoin = M('UserCoin')->where(array('userid' => userid()))->find();
		$CoinList = M('Coin')->where(array('status' => 1))->order('sort asc')->select();
		
		$Market = M('Market')->where(array('status' => 1))->select();
		foreach ($Market as $k => $v) {
			$Market[$v['name']] = $v;
		}

		$cny['zj'] = 0;
		
		foreach ($CoinList as $k => $v) {
			if ($v['name'] == Anchor_CNY) {
				$cny['ky'] = round($UserCoin[$v['name']], 2) * 1;
				$cny['dj'] = round($UserCoin[$v['name'] . 'd'], 2) * 1;
				$cny['zj'] = $cny['zj'] + $cny['ky'] + $cny['dj'];
				
				if ($Market[$v['name'].'_'.Anchor_CNY]['new_price']) {
					$jia = $Market[$v['name'].'_'.Anchor_CNY]['new_price'];
				} else {
					$jia = 0;
				}

				$coinList[$v['name']] = array('name' => $v['name'], 'img' => $v['img'], 'title' => ' ' . strtoupper($v['name']) . ' ', 'xnb' => round($UserCoin[$v['name']], 6) * 1, 'xnbd' => round($UserCoin[$v['name'] . 'd'], 6) * 1, 'xnbz' => round($UserCoin[$v['name']] + $UserCoin[$v['name'] . 'd'], 6), 'jia' => $jia * 1, 'zhehe' => round(($UserCoin[$v['name']] + $UserCoin[$v['name'] . 'd']) * $jia, 2), 'type' => $v['type'], 'zr_jz' => $v['zr_jz'], 'zc_jz' => $v['zc_jz']);

				$coinList[$v['name']]['xnb'] = sprintf("%.2f", $coinList[$v['name']]['xnb']);
				$coinList[$v['name']]['xnbd'] = sprintf("%.2f", $coinList[$v['name']]['xnbd']);
				$coinList[$v['name']]['xnbz'] = sprintf("%.2f", $coinList[$v['name']]['xnbz']);
				$coinList[$v['name']]['zhehe'] = sprintf("%.2f", $coinList[$v['name']]['xnbz']);
				//$coinList[$v['name']]['zhehe'] = number_format($coinList[$v['name']]['zhehe'],2);//千分位显示

				//开启市场时才显示对应的币
				if(in_array($v['name'],C('coin_on'))){
					$coinList[$v['name']] = array('name' => $v['name'], 'img' => $v['img'], 'title' => $v['title'] . '(' . strtoupper($v['name']) . ')', 'xnb' => round($UserCoin[$v['name']], 6) * 1, 'xnbd' => round($UserCoin[$v['name'] . 'd'], 6) * 1, 'xnbz' => round($UserCoin[$v['name']] + $UserCoin[$v['name'] . 'd'], 6), 'jia' => $jia * 1, 'zhehe' => round(($UserCoin[$v['name']] + $UserCoin[$v['name'] . 'd']) * $jia, 2), 'type' => $v['type'], 'zr_jz' => $v['zr_jz'], 'zc_jz' => $v['zc_jz']);
				}
			} else {
				if ($Market[$v['name'].'_'.Anchor_CNY]['new_price']) {
					$jia = $Market[$v['name'].'_'.Anchor_CNY]['new_price'];
				} else {
					$jia = 0;
				}

				$coinList[$v['name']] = array('name' => $v['name'], 'img' => $v['img'], 'title' => ' ' . strtoupper($v['name']) . ' ', 'xnb' => round($UserCoin[$v['name']], 6) * 1, 'xnbd' => round($UserCoin[$v['name'] . 'd'], 6) * 1, 'xnbz' => round($UserCoin[$v['name']] + $UserCoin[$v['name'] . 'd'], 6), 'jia' => $jia * 1, 'zhehe' => round(($UserCoin[$v['name']] + $UserCoin[$v['name'] . 'd']) * $jia, 2), 'type' => $v['type'], 'zr_jz' => $v['zr_jz'], 'zc_jz' => $v['zc_jz']);

				$coinList[$v['name']]['xnb'] = sprintf("%.5f", $coinList[$v['name']]['xnb']);
				$coinList[$v['name']]['xnbd'] = sprintf("%.5f", $coinList[$v['name']]['xnbd']);
				$coinList[$v['name']]['xnbz'] = sprintf("%.5f", $coinList[$v['name']]['xnbz']);
				$coinList[$v['name']]['zhehe'] = sprintf("%.2f", $coinList[$v['name']]['zhehe']);
				//$coinList[$v['name']]['zhehe'] = number_format($coinList[$v['name']]['zhehe'],2);//千分位显示

				//开启市场时才显示对应的币
				if(in_array($v['name'],C('coin_on'))){
					$coinList[$v['name']] = array('name' => $v['name'], 'img' => $v['img'], 'title' => $v['title'] . '(' . strtoupper($v['name']) . ')', 'xnb' => round($UserCoin[$v['name']], 6) * 1, 'xnbd' => round($UserCoin[$v['name'] . 'd'], 6) * 1, 'xnbz' => round($UserCoin[$v['name']] + $UserCoin[$v['name'] . 'd'], 6), 'jia' => $jia * 1, 'zhehe' => round(($UserCoin[$v['name']] + $UserCoin[$v['name'] . 'd']) * $jia, 2), 'type' => $v['type'], 'zr_jz' => $v['zr_jz'], 'zc_jz' => $v['zc_jz']);
				}
				
				
				$cny['zj'] = round($cny['zj'] + (($UserCoin[$v['name']] + $UserCoin[$v['name'] . 'd']) * $jia), 2) * 1;
			}
		}

		$cny['dj'] = sprintf("%.2f", $cny['dj']);
		$cny['ky'] = sprintf("%.2f", $cny['ky']);
		$cny['zj'] = sprintf("%.2f", $cny['zj']);
		//$cny['dj'] = number_format($cny['dj'],2);//千分位显示
		//$cny['ky'] = number_format($cny['ky'],2);//千分位显示
		//$cny['zj'] = number_format($cny['zj'],2);//千分位显示
		
		$this->assign('cny', $cny);
		$this->assign('coinList', $coinList);
		$this->display();
	}

	//展示交易详情
	public function financeInfo(){

		$userid = userid();
		if (!$userid) {
			redirect('/Login/index.html');
		}

		$curtime = time();
		$today_start = strtotime(date('Y-m-d 00:00:00'));
		//今日币币交易数
		$where = array(
			'userid' => $userid,
			'addtime'=>['between',[$today_start, $curtime]],
		);
		$trade_sum_today = M('trade')->where($where)->sum('mum');
		$trade_sum_today = isset($trade_sum_today)?$trade_sum_today:0;
		$trade_sum_today = round($trade_sum_today, 4);
		$this->assign('trade_sum_today', $trade_sum_today);

		//币币交易总数
		$where = array(
			'userid' => $userid,
		);
		$trade_sum_total = M('trade')->where($where)->sum('mum');
		$trade_sum_total = isset($trade_sum_total)?$trade_sum_total:0;
		$trade_sum_total = round($trade_sum_total, 4);
		$this->assign('trade_sum_total', $trade_sum_total);

		//今日c2c交易金额
		$where = "(userid = {$userid} OR aid = {$userid}) AND addtime >= {$today_start} AND addtime <= {$curtime}";
		$exchange_sum_today = D('ExchangeOrder')->where($where)->sum('mum');
		$exchange_sum_today = isset($exchange_sum_today)?$exchange_sum_today:0;
		$exchange_sum_today = round($exchange_sum_today, 4);
		$this->assign('exchange_sum_today', $exchange_sum_today);
		//今日总数量
		$exchange_totay_count = D('ExchangeOrder')->where($where)->count();

		//今日成功的C2C交易金额
		$where = "(userid = {$userid} OR aid = {$userid}) AND addtime >= {$today_start} AND addtime <= {$curtime} AND status = 3";
		$exchange_sum_today_success = D('ExchangeOrder')->where($where)->sum('mum');
		$exchange_sum_today_success = isset($exchange_sum_today_success)?$exchange_sum_today_success:0;
		$exchange_sum_today_success = round($exchange_sum_today_success, 4);
		$this->assign('exchange_sum_today_success', $exchange_sum_today_success);
		//今日成功数量
		$exchange_today_success_count = D('ExchangeOrder')->where($where)->count();
		//今日成功率
		if($exchange_totay_count > 0){
			$exchange_today_success_rate = round($exchange_today_success_count / $exchange_totay_count * 100, 2);
		}else{
			$exchange_today_success_rate = 0;
		}
		$this->assign('exchange_today_success_rate', $exchange_today_success_rate);

		//c2c交易总额
		$where = "(userid = {$userid} OR aid = {$userid})";
		$exchange_sum_total = D('ExchangeOrder')->where($where)->sum('mum');
		$exchange_sum_total = isset($exchange_sum_total)?$exchange_sum_total:0;
		$exchange_sum_total = round($exchange_sum_total, 4);
		$this->assign('exchange_sum_total', $exchange_sum_total);
		//总数量
		$exchange_total_count = D('ExchangeOrder')->where($where)->count();

		//c2c交易成功总额
		$where = "(userid = {$userid} OR aid = {$userid}) AND status = 3";
		$exchange_history_sum_total_success = M('exchange_order_history_sum')->where(['userid'=>$userid])->sum('mum_sum');
		$exchange_history_sum_total_success = isset($exchange_history_sum_total_success)?$exchange_history_sum_total_success:0;
		$exchange_sum_total_success = $exchange_history_sum_total_success + $exchange_sum_today_success;
		$exchange_sum_total_success = round($exchange_sum_total_success, 4);
		$this->assign('exchange_sum_total_success', $exchange_sum_total_success);
		//今日成功数量
		$exchange_todal_success_count = D('ExchangeOrder')->where($where)->count();
		//今日成功率
		if($exchange_total_count > 0){
			$exchange_total_success_rate = round($exchange_todal_success_count / $exchange_total_count * 100, 2);
		}else{
			$exchange_total_success_rate = 0;
		}
		//总成功率
		$this->assign('exchange_total_success_rate', $exchange_total_success_rate);

		//今日手续费
		$where = array(
			'userid' => $userid,
			'status' => 1,
			'addtime'=>['between',[$today_start, $curtime]],
		);
		$today_trade_poundage = M('trade')->where($where)->sum('fee');
		$today_trade_poundage = $today_trade_poundage?$today_trade_poundage:0;
		//今日c2c的手续费
		unset($where['userid']);
		$where['aid'] = $userid; //买家
		$where['otype'] = 2;
		$where['status'] = 3;
		$today_c2c_fee = D('ExchangeOrder')->where($where)->sum('fee');
		$today_c2c_fee = $today_c2c_fee?$today_c2c_fee:0;
		$today_c2c_scale = D('ExchangeOrder')->where($where)->sum('scale_amount');
		$today_c2c_scale = $today_c2c_scale?$today_c2c_scale:0;
		$today_poundage = $today_trade_poundage + $today_c2c_fee + $today_c2c_scale;
		$today_poundage = round($today_poundage, 4);
		$this->assign('today_poundage', $today_poundage);

		//总手续费
		$where = array(
			'userid' => $userid,
			'status' => 1,
		);
		$total_trade_poundage = M('trade')->where($where)->sum('fee');
		$total_trade_poundage = $total_trade_poundage?$total_trade_poundage:0;
		$total_c2c_fee = M('exchange_order_history_sum')->where(['userid'=>$userid])->sum('fee_sum');
		$total_c2c_fee = $total_c2c_fee?$total_c2c_fee:0;
		$total_poundage = $total_trade_poundage + $total_c2c_fee + $today_poundage;
		$total_poundage = round($total_poundage, 4);
		$this->assign('total_poundage', $total_poundage);

		//今日利润
		$where = "userid = {$userid} AND otype = 2 AND status = 3 AND addtime >= {$today_start} AND addtime <= {$curtime}"; //当前只有卖出订单才有利润
		$today_profit = D('ExchangeOrder')->where($where)->sum('scale_amount');
		$today_profit = isset($today_profit)?$today_profit:0;
		$today_profit = round($today_profit, 3);
		$this->assign('today_profit', $today_profit);

		//总利润
		$where = "userid = {$userid} AND otype = 2 AND status = 3"; //当前只有卖出订单才有利润
		$total_history_profit = M('exchange_order_history_sum')->where(['userid'=>$userid])->sum('scale_amount_sum');
		$total_history_profit = isset($total_history_profit)?$total_history_profit:0;
		$total_history_invit = M('exchange_order_history_sum')->where(['userid'=>$userid])->sum('invit_amount');
		$total_history_invit = isset($total_history_invit)?$total_history_invit:0;
		$total_profit = $total_history_profit + $total_history_invit + $today_profit;
		$total_profit = round($total_profit, 3);
		$this->assign('total_profit', $total_profit);

		//币币交易市场
		$list = array();
		$Market = M('Market')->where(array('status' => 1))->select();
		foreach ($Market as $key => $value) {
			$where = array(
				'userid' => $userid,
				'market' => $value['name'],
				'addtime'=>['between',[$today_start, time()]],
			);

			$data['market'] = $value['name'];

			$where['type'] = 1;
			$today_buy_sum = M('trade')->where($where)->sum('mum');
			$today_buy_sum = isset($today_buy_sum)?$today_buy_sum:0;
			$today_buy_sum = round($today_buy_sum, 4);
			$data['today_buy_sum'] = $today_buy_sum;
			$data['today_buy_num'] = M('trade')->where($where)->count();

			$where['type'] = 2;
			$today_sell_sum = M('trade')->where($where)->sum('mum');
			$today_sell_sum = isset($today_sell_sum)?$today_sell_sum:0;
			$today_sell_sum = round($today_sell_sum, 4);
			$data['today_sell_sum'] = $today_sell_sum;
			$data['today_sell_num'] = M('trade')->where($where)->count();

			unset($where['addtime']);
			$total_buy_sum = M('trade')->where($where)->sum('mum');
			$total_buy_sum = isset($total_buy_sum)?$total_buy_sum:0;
			$total_buy_sum = round($total_buy_sum, 4);
			$data['total_buy_sum'] = $total_buy_sum;
			$data['total_buy_num'] = M('trade')->where($where)->count();

			$total_sell_sum = M('trade')->where($where)->sum('mum');
			$total_sell_sum = isset($total_sell_sum)?$total_sell_sum:0;
			$total_sell_sum = round($total_sell_sum, 4);
			$data['total_sell_sum'] = $total_sell_sum;
			$data['total_sell_num'] = M('trade')->where($where)->count();
			$list[] = $data;
		}

		// c2c市场
		$data['market'] = 'C2C';
		$where = "((userid = {$userid} AND otype = 1)OR(aid = {$userid} AND otype = 2)) AND addtime >= {$today_start} AND addtime <= {$curtime}";
		$today_buy_sum= D('ExchangeOrder')->where($where)->sum('mum');
		$today_buy_sum = isset($today_buy_sum)?$today_buy_sum:0;
		$today_buy_sum = round($today_buy_sum, 4);
		$data['today_buy_sum'] = $today_buy_sum;
		$data['today_buy_num'] = D('ExchangeOrder')->where($where)->count();

		$where = "((userid = {$userid} AND otype = 2)OR(aid = {$userid} AND otype = 1)) AND addtime >= {$today_start} AND addtime <= {$curtime}";
		$today_sell_sum = D('ExchangeOrder')->where($where)->sum('mum');
		$today_sell_sum = isset($today_sell_sum)?$today_sell_sum:0;
		$today_sell_sum = round($today_sell_sum, 4);
		$data['today_sell_sum'] = $today_sell_sum;
		$data['today_sell_num'] = D('ExchangeOrder')->where($where)->count();

		$where = "((userid = {$userid} AND otype = 1)OR(aid = {$userid} AND otype = 2))";
		$total_buy_sum = D('ExchangeOrder')->where($where)->sum('mum');
		$total_buy_sum = isset($total_buy_sum)?$total_buy_sum:0;
		$total_buy_sum = round($total_buy_sum, 4);
		$data['total_buy_sum'] = $total_buy_sum;
		$data['total_buy_num'] = D('ExchangeOrder')->where($where)->count();

		$where = "((userid = {$userid} AND otype = 2)OR(aid = {$userid} AND otype = 1))";
		$total_sell_sum = D('ExchangeOrder')->where($where)->sum('mum');
		$total_sell_sum = isset($total_sell_sum)?$total_sell_sum:0;
		$total_sell_sum = round($total_sell_sum, 4);
		$data['total_sell_sum'] = $total_sell_sum;
		$data['total_sell_num'] = D('ExchangeOrder')->where($where)->count();
		$list[] = $data;

		//获取用户信息
		$User = M('User')->where(array('id' => $userid))->find();
		$this->assign('user', $User);

		$this->assign('list', $list);
		$this->display();
	}

	/**
     * 后台补偿订单导出
     */
    public function excelC2COrderInfo($export_mode)
    {
    	$userid = userid();
		if (!$userid) {
			redirect('/Login/index.html');
		}

		/** 检查设置条件 **/
        $exchange_config = M('exchange_config')->where(array('id' => 1))->find();
        $config_price = $exchange_config['mytx_uprice'];

		$curtime = time();
		$today_start = strtotime(date('Y-m-d 00:00:00'));
        $where  = "userid = {$userid} OR aid = {$userid}";
        switch ($export_mode) {
        	case 'today':
        		//今日订单
        		$where  = "(userid = {$userid} OR aid = {$userid}) AND addtime >= {$today_start} AND addtime < {$curtime}";
        		break;
        	case 'yesterday':
        		//昨日订单
        		$yesterday = strtotime(date('Y-m-d 00:00:00', strtotime("-1 day")));
        		$where  = "(userid = {$userid} OR aid = {$userid}) AND addtime >= {$yesterday} AND addtime < {$today_start}";
        		break;
        	case 'month':
        		//当月订单
        		$monthBegin = strtotime(date('Y-m-01 00:00:00'));
        		$where  = "(userid = {$userid} OR aid = {$userid}) AND addtime >= {$monthBegin} AND addtime < {$curtime}";
        		break;
        	default: //默认今日
        		$where  = "(userid = {$userid} OR aid = {$userid}) AND addtime >= {$today_start} AND addtime < {$curtime}";
        		break;
        }

        $title = array('发起人ID','买方ID','卖方ID','订单类型','操作类型','订单号', '外部订单号', '单价', '交易数量','实际数量', '总价', '自动卖出优惠', '自动买入手续费', '优惠情况', '货币类型', '账户名', '账户类型', '账户号', '订单时间', '状态', '订单标记');

        //资金变动类型
        $list = D("ExchangeOrder")->where($where)->select();
        foreach ($list as $key => $item) {
        	switch ($item['otype']) {
        		case '1':
        			if($item['userid'] == $userid){
        				$otype = '买入';
        			}else{
        				$otype = '卖出';
        			}
        			break;
        		case '2':
        			if($item['userid'] == $userid){
        				$otype = '卖出';
        			}else{
        				$otype = '买入';
        			}
        			break;
        		default:
        			$otype = '未知';
        			break;
        	}

        	switch ($item['status']) {
        		case '0':
        			$status = '未处理';
        			break;
        		case '1':
        			$status = '待处理';
        			break;
        		case '2':
        			$status = '处理中';
        			break;
        		case '3':
        			$status = '成功';
        			break;
        		case '8':
        			$status = '取消';
        			break;
        		default:
        			$status = '未知';
        			break;
        	}

        	switch ($item['status']) {
        		case '0':
        			$status = '未处理';
        			break;
        		case '1':
        			$status = '待处理';
        			break;
        		case '2':
        			$status = '处理中';
        			break;
        		case '3':
        			$status = '成功';
        			break;
        		case '8':
        			$status = '取消';
        			break;
        		default:
        			$status = '未知';
        			break;
        	}

        	switch ($item['punishment_level']) {
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

        	if(isBuyUserOpreate($item, $item['userid'])){
				$buy_userid  = $item['userid'];
				$sell_userid = $item['aid'];
			}else{
				$buy_userid  = $item['aid'];
				$sell_userid = $item['userid'];
			}

			if(isAutoC2COrder($item)){
				$is_auto = '自动生成';
			}else{
				$is_auto = '手动生成';
			}

        	$addtime = date('Y-m-d H:i:s', $item["addtime"]);

        	//手续费
        	$fee = $item["all_scale"] > 0 ? $item["all_scale"]+$item["fee"] : $item["scale_amount"]+$item["fee"];
        	$mum = round($item['mum'], 3);
        	//到账金额
        	if($item['otype'] == 1){
        		$obtain_num = ($mum+$fee)/$config_price;
        		$obtain_num = $obtain_num > $item['num'] ? $obtain_num:$item['num'];
        	}else{
        		$obtain_num = ($mum-$fee)/$config_price;
        		$obtain_num = $obtain_num < $item['num'] ? $obtain_num:$item['num'];
        	}
        	$data[] = [
        		'userid'			=> $item["userid"],
        		'buy_userid'		=> $buy_userid,
        		'sell_userid'		=> $sell_userid,
        		'otype'				=> $otype,
        		'is_auto'			=> $is_auto,
        		'orderid' 			=> $item["orderid"],
        		'out_order_id' 		=> $item["out_order_id"],
        		'uprice'			=> $item["uprice"],
        		'num'				=> $item["num"],
        		'obtain_num'		=> round($obtain_num, 3),
        		'mum'				=> $item["mum"],
        		'scale_amount'		=> $item["scale_amount"],
        		'fee'				=> round($fee, 3),
        		'punishment_level'	=> $punishment_level,
        		'type'				=> $item["type"],
        		'truename'			=> $item["truename"],
        		'channel_title'		=> getPayChannleTitle($item["pay_channelid"]),
        		'bankcard'			=> isset($item["bankcard"])?$item["bankcard"]:$item["bank"],
        		'addtime'			=> $addtime,
        		'status'			=> $status,
        		'remarks'			=> $item["remarks"],
        	];
        }
        $numberField = ['uprice','num', 'obtain_num', 'mum', 'scale_amount', 'fee'];
        
        exportexcel($data, $title);//, $numberField);
        // 将已经写到csv中的数据存储变量销毁，释放内存占用
        unset($data);
        //刷新缓冲区
        ob_flush();
        flush();
    }
	
	//生成二维码
	public function qrcode($url=NULL){
		Vendor('PHPQRcode.phpqrcode');
		//生成二维码图片
		$object = new \QRcode();
		$url = 'http://'.$_SERVER['HTTP_HOST'].'/Login/register?invit='.$url;//网址或者是文本内容
		$level = 3;
		$size = 4;
		$errorCorrectionLevel = intval($level) ;//容错级别
		$matrixPointSize = intval($size);//生成图片大小
		ob_clean();
		$object->png($url, false, $errorCorrectionLevel, $matrixPointSize, 2);
	}
	
	//生成海报
	public function haibao($url=NULL,$type=0)
	{
        $imageDefault = array(
            'left'=>430,
            'top'=>633,
            'right'=>0,
            'bottom'=>0,
            'width'=>120,
            'height'=>120,
            'opacity'=>100
        );
        $textDefault = array(
            'text'=>'',
            'left'=>0,
            'top'=>0,
            'fontSize'=>32,       //字号
            'fontColor'=>'255,255,255', //字体颜色
            'angle'=>0,
        );
		
        //海报最底层得背景
		if ($type == 2) {
			$imageDefault = array(
				'left'=>240,
				'top'=>629,
				'right'=>0,
				'bottom'=>0,
				'width'=>120,
				'height'=>120,
				'opacity'=>100
			);
			$background = 'Public/Home/rh_img/haibao2.png';
		} else {
			$background = 'Public/Home/rh_img/haibao.png';
		}
		
        $config['image'][]['url'] = 'http://'.$_SERVER['HTTP_HOST'].'/Home/Finance/qrcode/url/'.$url; //二维码
        $filename = ''; // 保存图片到服务器
		
        getbgqrcode($imageDefault,$textDefault,$background,'',$config);
    }
	
	public function mydh($coin = NULL,$jf_type =NULL)
	{
		// 过滤非法字符----------------S
		if (checkstr($coin)) {
			$this->error(L('您输入的信息有误！'));
		}
		// 过滤非法字符----------------E

		if (!userid()) {
			redirect('/Login/index.html');
		}
		
		if (C('coin')[$coin]) {
			$coin = trim($coin);
		} else {
			// $coin = C('xnb_mr');
			$coin=M('Coin')->where(array(
			'change' => array('neq',0),
			'status' => 1,
			'name'   => array('neq', Anchor_CNY)
			))->getField('name');
		}
		// var_dump($coin);
		$this->assign('xnb', $coin);

		$Coins = M('Coin')->where(array(
			'change' => array('neq',0),
			'status' => 1,
			'name'   => array('neq', Anchor_CNY)
		))->select();
		
		foreach ($Coins as $k => $v) {
			$coin_list[$v['name']] = $v;
		}
		$coin_info = M('Coin')->where(array('name' => $coin))->find();

		if($coin_info['change']==1){//固定汇率
			$coin_info['bili']=1/$coin_info['huilv']*$coin_info['amount'];//乘以最小交易数量
			$coin_info['bili2']=1/$coin_info['huilv'];
		}
		if($coin_info['change']==2){//浮动汇率
			//源币种行情比例
			$map1['name']=array('like',$coin.'%');
			$price1=M('market')->where($map1)->getField('new_price');
			//目标币种行情比例
			$map2['name']=array('like',$coin_info['changecoin'].'%');
			$price2=M('market')->where($map2)->getField('new_price');
			// $coin_info['bili']=$price1/$price2*$coin_info['amount'];//乘以最小交易数量
			$coin_info['bili']=round(($price1/$price2)*$coin_info['amount'],4);
			$coin_info['bili2']=round(($price1/$price2),7);
		}
		$this->assign('coin_info', $coin_info);

		if(!$coin_list){
			$this->error(L('币种不存在'));
		}
		// var_dump($coin_list);
		$this->assign('coin_list', $coin_list);

		$user_coin = M('UserCoin')->where(array('userid' => userid()))->find();
		$user_coin[$coin] = round($user_coin[$coin], 6);
		$user_coin[$coin] = sprintf("%.4f", $user_coin[$coin]);
		$this->assign('user_coin', $user_coin);
		$Coins = M('Coin')->where(array('name' => $coin))->find();

		$where['userid'] = userid();
		
		$Mobile = M('mydh');
		$count = $Mobile->where($where)->count();
		$Page = new \Think\Page($count, 10);
		$show = $Page->show();
		$list = $Mobile->where($where)->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
		
		foreach ($list as $key => $value) {
			$list[$key]['num']=sprintf("%.4f", $value['num']);
		}

		$this->assign('list', $list);
		$this->assign('page', $show);
		$this->display();
	}
	
	public function upmydh($coin, $num,  $paypassword)
	{
		// 过滤非法字符----------------S
		if (checkstr($coin) || checkstr($num) ) {
			$this->error(L('您输入的信息有误！'));
		}
		// 过滤非法字符----------------E
		
		if (!userid()) {
			// $this->error('您没有登录请先登录！');
			redirect('/Login/index.html');
		}
		
		$num = abs($num);
		if (!check($num, 'currency')) {
			$this->error(L('数量格式错误！'));
		}
		if (!check($paypassword, 'password')) {
			$this->error(L('密码格式为6~16位，不含特殊符号！'));
		}
		if (!check($coin, 'n')) {
			$this->error(L('币种格式错误！'));
		}
		if (!C('coin')[$coin]) {
			$this->error(L('币种错误！'));
		}
		$Coins = M('Coin')->where(array('name' => $coin))->find();
		if (!$Coins) {
			$this->error(L('币种错误！'));
		}
		if($Coins['change']==1){//固定汇率
			$cannum=1/$Coins['huilv']*$num;//乘以最小交易数量
		}
		if($Coins['change']==2){//浮动汇率
			//源币种行情比例
			$map1['name']=array('like',$coin.'%');
			$price1=M('market')->where($map1)->getField('new_price');
			//目标币种行情比例
			$map2['name']=array('like',$Coins['changecoin'].'%');
			$price2=M('market')->where($map2)->getField('new_price');
			$cannum=round(($price1/$price2)*$num,6);
		}

		$user = M('User')->where(array('id' => userid()))->find();
		$user_coin = M('UserCoin')->where(array('userid' => userid()))->find();
		// $this->error($user_coin[$coin] );
		$myzc_min = $Coins['amount'] ;//最小可交易数量
		// $myzc_min = ($Coins['zc_min'] ? abs($Coins['zc_min']) : 0.0001);
		$myzc_max = $user_coin[$coin];//账户余额
		// $myzc_max = ($Coins['zc_max'] ? abs($Coins['zc_max']) : 10000000);

		if ($num < $myzc_min) {
			$this->error(L('数量低于系统最小限额！'));
		}
		if ($myzc_max < $num) {
			$this->error(L('您的账户余额不足！'));
		}
		if (md5($paypassword) != $user['paypassword']) {
			if(!empty($user['paypassword'])){
				$this->error(L('交易密码错误！'));
			}else{
				$this->error(L('还未设置交易密码，请前往账户中心设置！'));
			}
		}
		if ($user_coin[$coin] < $num) {
			$this->error(L('可用余额不足'));
		}

		try{
			$mo = M();
			$mo->execute('set autocommit=0');
			$mo->execute('lock tables  tw_user_coin write  , tw_mydh write  , tw_finance_log write,tw_user read');

			$rs = array();
			$rs[] = $mo->table('tw_user_coin')->where(array('userid' => userid()))->setDec($coin, $num);
			$rs[] = $mo->table('tw_user_coin')->where(array('userid' => userid()))->setInc($Coins['changecoin'], $cannum);
			$rs[] = $mo->table('tw_mydh')->add(array('userid' => userid(), 'username' => $user['username'], 'coinname' => $coin, 'txid' => md5($user_coin[$coin . 'b'] . time()), 'num' => $num, 'amount' => $cannum, 'addtime' => time(), 'dbz' =>$Coins['changecoin']));
			// 处理资金变更日志-----------------S

			if (check_arr($rs)) {
				$mo->execute('commit');
				$mo->execute('unlock tables');
				// session('myzc_verify', null);
				$this->success(L('交易成功！'));
			} else {
				throw new \Think\Exception('交易失败,错误301！');
			}
		}catch(\Think\Exception $e){
			$mo->execute('rollback');
			$mo->execute('unlock tables');
			$this->error('交易失败,错误302!');
		}
	}

	// 钱包转入
	public function myzr($coin = NULL)
	{
		// 过滤非法字符----------------S
		if (checkstr($coin)) {
			$this->error(L('您输入的信息有误！'));
		}
		// 过滤非法字符----------------E

		if (!userid()) {
			redirect('/Login/index.html');
		}
		
		//获取用户信息
		$User = M('User')->where(array('id' => userid()))->find();
		$this->assign('user', $User);

/*		if (C('coin')[$coin]) {
			$coin = trim($coin);
		} else {
			$coin = C('xnb_mr');
		}*/
		
		$Coins = M('Coin')->where(array(
			'status' => 1,
			'type'   => array('neq', 'ptb'),
			'name'   => array('neq', Anchor_CNY),
		))->select();

		foreach ($Coins as $k => $v) {
			$coin_list[$v['name']] = $v;
		}
		
		if(!($coin)){
			$coin = $Coins[0]['name']; //拿出数组第一个
		}
		
		$this->assign('xnb', $coin);
		$this->assign('coin_list', $coin_list);
		
		$user_coin = M('UserCoin')->where(array('userid' => userid()))->find();
		$user_coin[$coin] = round($user_coin[$coin], 6);
		$user_coin[$coin] = sprintf("%.4f", $user_coin[$coin]);
		$user_coin[$coin.'d'] = round($user_coin[$coin.'d'], 6);
		$user_coin[$coin.'d'] = sprintf("%.4f", $user_coin[$coin.'d']);
		
		$this->assign('xnb_c', $user_coin[$coin]);
		$this->assign('xnbd_c', $user_coin[$coin.'d']);
		$this->assign('user_coin', $user_coin);
		
		$Coins = M('Coin')->where(array('name' => $coin))->find();
		$this->assign('zr_jz', $Coins['zr_jz']);
		// var_dump($user_coin[$qbdz]);
		
		$state_coin = 0;
		
		if (!$Coins['zr_jz']) {
			
			$qianbao = L('当前币种禁止转入！');
			$state_coin = 1;
			
		} else {
			
			//钱包地址
			$qbAddress = $coin.'b';
			//判断地址是否存在
			if (!$user_coin[$qbAddress]) {

				//内部币
				if ($Coins['type'] == 'rgb') {
					$qianbao = md5(username() . $coin);
					$rs = M('UserCoin')->where(array('userid' => userid()))->save(array($qbAddress => $qianbao));
					if (!$rs) {
						$qianbao = L('内部币生成钱包地址出错！');
						$state_coin = 1;
					}

				}elseif ($Coins['type'] == 'qbb') { //外部币
					
					$coin_config = M('Coin')->where(array('name' => $coin))->find();
					switch ($coin) {
					 	case 'eth': //token_type == 1 表示使用ERC20协议的代币，使用地址都是eth的地址
					 		$coin_select = M('Coin')->where(array('api_type' => 'eth','token_type' => 1))->select();
							$ethcoin = array('eth'); //ETH对接,FFF
							foreach ($coin_select as $k => $v) {
								$ethcoin[] = $v['name'];
							}

							foreach ($ethcoin as $k => $v) {
								// dump($v);
								if ($user_coin[$v.'b']) {
									$qianbao=$user_coin[$v.'b'];
									break;
								}
							}
							
							if (!$qianbao) {
								$CoinClient = EthCommon($dj_address, $dj_port);
								if (!$CoinClient) {
									$qianbao = L('钱包链接失败！');
									$state_coin = 1;
								} else {
									$qianbao = $CoinClient->personal_newAccount(username());//根据用户名生成账户
									if (!$qianbao || preg_match("/[\'.,:;*?~`!@#$%^&+=)(<>{}]|\]|\[|\/|\\\|\"|\|/",$qianbao)) {
		
										$qianbao = L('生成钱包地址出错！');
										$state_coin = 1;
									} else {
										foreach ($ethcoin as $k => $v) {
										$rs = M('UserCoin')->where(array('userid' => userid()))->save(array($v.'b' => $qianbao));
										}
									}
								}
							} else {
								foreach ($ethcoin as $k => $v) {
									$rs = M('UserCoin')->where(array('userid' => userid()))->save(array($v.'b' => $qianbao));
								}
							}
					 		break;
					 	case 'etc':
					 		$CoinClient = EthCommon($dj_address, $dj_port);
							$qianbao= $CoinClient->personal_newAccount(username());//根据用户名生成账户
							if (!$qianbao || preg_match("/[\'.,:;*?~`!@#$%^&+=)(<>{}]|\]|\[|\/|\\\|\"|\|/",$qianbao)) {
								$qianbao = L('生成钱包地址出错！');
								$state_coin = 1;
							}else{
								$rs = M('UserCoin')->where(array('userid' => userid()))->save(array('etcb' => $qianbao));
								// $rs = M('UserCoin')->where(array('userid' => userid()))->save(array('tatcb' => $qianbao));
							}
					 		break;
					 	case 'zec':
					 		$dj_username = $Coins['dj_yh'];
							$dj_password = $Coins['dj_mm'];
							$dj_address = $Coins['dj_zj'];
							$dj_port = $Coins['dj_dk'];
							$CoinClient = CoinClient($dj_username, $dj_password, $dj_address, $dj_port, 5, array(), 1);
							$json = $CoinClient->getinfo();

							if (!isset($json['version']) || !$json['version']) {
								$qianbao = L('钱包链接失败！');
								$state_coin = 1;
							} else {
								$qianbao = $CoinClient->getnewaddress();
								if (!$qianbao || preg_match("/[\'.,:;*?~`!@#$%^&+=)(<>{}]|\]|\[|\/|\\\|\"|\|/",$qianbao)) {
									$qianbao = L('生成钱包地址出错！');
									$state_coin = 1;
								}else{
									$rs = M('UserCoin')->where(array('userid' => userid()))->save(array($qbAddress => $qianbao));
								}
							}
					 		break;
					 	case 'usdt':
					 		$usdt_model = new \Common\Model\Coin\UsdtModel($Coins);
					 		$result 	= $usdt_model->getOldAddressByAccount(username());
							if (!$result || $result['status'] == 0) {
								$qianbao = $result['msg'];
							}else{
								$qianbao = $result['msg'];
								$rs = M('UserCoin')->where(array('userid' => userid()))->save(array($qbAddress => $qianbao));
								if (!$rs) {
									$this->error(L('钱包地址添加出错！'));
								}
							}
					 		break;
					 	default:
					 		$btc_model 	= new \Common\Model\Coin\BtcModel($Coins);
					 		$result 	= $btc_model->getOldAddressByAccount(username());
							if (!$result || $result['status'] == 0) {
								$qianbao = $result['msg'];
							}else{
								$qianbao = $result['msg'];
								$rs = M('UserCoin')->where(array('userid' => userid()))->save(array($qbAddress => $qianbao));
								if (!$rs) {
									$this->error(L('钱包地址添加出错！'));
								}
							}
					 		break;
					 } 
				}

			} else {
				$qianbao = $user_coin[$qbAddress];
			}
			// var_dump($qianbao);
		}

		$this->assign('qianbao', $qianbao);
		$where['userid'] = userid();
		$where['coinname'] = $coin;
		$where['from_user'] = '0';
		
		$Mobile = M('Myzr');
		$count = $Mobile->where($where)->count();
		$Page = new \Think\Page($count, 10);
		$show = $Page->show();
		$list = $Mobile->where($where)->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
		foreach ($list as $key => $value) {
			// $list[$key]['num']= $value['num'];
			// $list[$key]['mum']= $value['mum'];
			$list[$key]['num']=sprintf("%.4f", $value['num']);
			$list[$key]['mum']=sprintf("%.4f", $value['mum']);
			$list[$key]['fee']=sprintf("%.4f", $value['fee']);
		}
		
		$this->assign('state_coin', $state_coin);
		$this->assign('list', $list);
		$this->assign('page', $show);
		$this->display();
	}

	//今日是否能够转出金额
	private function isCanTodayMyzc($coin, $kyc_lv){
		if($coin){
			//机构认证状态
			$organization_status = M('user_kyc')->where(array('userid' => userid()))->getField('organization_status');
			if(!$organization_status){ //机构认证不通过则限制
				$todyNum = M('myzc')->where(['userid'=> userid(), 'coinname'=>$coin, 'addtime'=> ['gt', strtotime(date("Y-m-d 00:00:00"))]])->sum('num');

				$todyNum = $todyNum?$todyNum:0;

				$market = $coin.'_'.Anchor_CNY;
				$usdt_new_price = C('market')['usdt_cnc']['new_price'];
				$usdt_new_price = $usdt_new_price?$usdt_new_price:6.95;
				$new_price = C('market')[$market]['new_price'];

				switch ($kyc_lv) {
					case 1:
						if($new_price && ($todyNum*$new_price/$usdt_new_price) < C('TODAY_FIANCE_KYC_LIMIT'))
						{ //5000美金
							//$this->error(L('超过今日转出上限'.$new_price.' todyNum = '.$todyNum));
							return true;
						}
						break;
					case 2:
						if($new_price && ($todyNum*$new_price/$usdt_new_price) < C('TODAY_FIANCE_KYC_LIMIT')){ //5000美金
							return true;
							//$this->error(L('超过今日转出上限'.$new_price.' todyNum = '.$todyNum));
						}
						break;
					default:
						# code...
						break;
				}
			}else{
				return true;
			}
		}
		return false;
	}
	
	//钱包转出
	public function myzc($coin = NULL)
	{
		// 过滤非法字符----------------S
		if (checkstr($coin)) {
			$this->error(L('您输入的信息有误！'));
		}
		// 过滤非法字符----------------E
		
		if (!userid()) {
			redirect('/Login/index');
		}
		
		$Coins = M('Coin')->where(array(
			'status' => 1,
			'type'   => array('neq', 'ptb'),
			'name'   => array('neq', Anchor_CNY)
		))->select();

		foreach ($Coins as $k => $v) {
			$coin_list[$v['name']] = $v;
		}

		if(!($coin)){
			$coin = $Coins[0]['name']; //拿出数组第一个
		}
		
		$Coinx = M('Coin')->where(array('name' => $coin))->find();
		$myzc_min = ($Coinx['zc_min'] ? abs($Coinx['zc_min']) : 1);
		$myzc_max = ($Coinx['zc_max'] ? abs($Coinx['zc_max']) : 10000000);
		$this->assign('myzc_min', $myzc_min);
		$this->assign('myzc_max', $myzc_max);
		$this->assign('Coinx', $Coinx);
		
		$this->assign('xnb', $coin);
		$this->assign('coin_list', $coin_list);
		
		$user_coin = M('UserCoin')->where(array('userid' => userid()))->find();
		$user_coin[$coin] = round($user_coin[$coin], 6);
		$user_coin[$coin] = sprintf("%.4f", $user_coin[$coin]);
		$user_coin[$coin.'d'] = round($user_coin[$coin.'d'], 6);
		$user_coin[$coin.'d'] = sprintf("%.4f", $user_coin[$coin.'d']);
		
		$this->assign('xnb_c', $user_coin[$coin]);
		$this->assign('xnbd_c', $user_coin[$coin.'d']);
		$this->assign('user_coin', $user_coin);

		if (!$coin_list[$coin]['zc_jz']) {
			$this->assign('zc_jz', L('当前币种禁止转出！'));
		} else {
			$userQianbaoList = M('UserQianbao')->where(array('userid' => userid(), 'status' => 1, 'coinname' => $coin))->order('id desc')->select();
			$this->assign('userQianbaoList', $userQianbaoList);
			$mobile = M('User')->where(array('id' => userid()))->getField('mobile');

			if ($mobile) {
				$mobile = substr_replace($mobile, '****', 3, 4);
			}
			else {
				$this->error(L('不是手机注册用户，属于不正常账号，不能进行操作！'));
				exit();
			}

			$this->assign('mobile', $mobile);
		}

		$where['userid'] = userid();
		$where['coinname'] = $coin;
		$where['to_user'] = array('neq','1' );

		$Mobile = M('Myzc');
		$count = $Mobile->where($where)->count();
		$Page = new \Think\Page($count, 10);
		$show = $Page->show();
		
		$list = $Mobile->where($where)->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
		foreach ($list as $key => $value) {
			$list[$key]['num'] = sprintf("%.4f", $value['num']);
			$list[$key]['mum'] = sprintf("%.4f", $value['mum']);
			$list[$key]['fee'] = sprintf("%.4f", $value['fee']);
		}
		
		$user = M('user')->where(array('id'=>userid()))->find();
		$this->assign('user', $user);
		
		$this->assign('list', $list);
		$this->assign('page', $show);
		$this->display();
	}
	
	// 钱包转出处理
	public function upmyzc($coin, $num, $addr, $paypassword)
	{
		// 过滤非法字符----------------S
		if (checkstr($coin) || checkstr($num)) {
			$this->error(L('您输入的信息有误！'));
		}
		// 过滤非法字符----------------E
		
		if (!userid()) {
			$this->error(L('您没有登录请先登录！'));
		}
		
		$user_info = M('user')->where(array('id'=>userid()))->find();

/*		if ($user_info['mobile'] != session('chkmobile')) {
			$this->error(L('验证码错误！'));
		}
		if (!check($mobile_verify, 'd')) {
			$this->error(L('验证码错误！'));
		}
		if ($mobile_verify != session('myzc_verify')) {
			$this->error(L('验证码错误！'));
		}*/

		if (!check($coin, 'n')) {
			$this->error(L('币种格式错误！'));
		}
		if (!C('coin')[$coin]) {
			$this->error(L('币种错误！'));
		}
		
		$num = abs($num);
		if (!check($num, 'currency')) {
			$this->error(L('数量格式错误！'));
		}
		if (!check($addr, 'dw')) {
			$this->error(L('钱包地址格式错误！'));
		}
		if (!check($paypassword, 'password')) {
			$this->error(L('密码格式为6~16位，不含特殊符号！'));
		}
		if (md5($paypassword) != $user_info['paypassword']) {
			if(!empty($user_info['paypassword'])){
				$this->error(L('交易密码错误！'));
			}else{
				$this->error(L('还未设置交易密码，请前往账户中心设置！'));
			}
		}

		$Coins = M('Coin')->where(array('name' => $coin))->find();
		if (!$Coins) {
			$this->error(L('币种错误！'));
		}

		// 搜索实名认证信息
		if($user_info['kyc_lv'] > 1){ //初级认证已通过
			$this->assign('idcard', 1);
		}
		elseif ($user_info['kyc_lv'] == 1 && $user_info['idstate'] == 2) { //实名认证等级
			$this->assign('idcard', 1);
		} else {
			$this->error(L('请初级实名认证，再进行操作！'), U('User/index'));
		}
		//今天上限判断
		if(!$this->isCanTodayMyzc($coin, $user_info['kyc_lv'])){
			$this->error(L('超过今日转出上限'.C('TODAY_FIANCE_KYC_LIMIT').'美金，进行机构认证可以打开限制！'));
		}

		$myzc_min = ($Coins['zc_min'] ? abs($Coins['zc_min']) : 0.0001);
		$myzc_max = ($Coins['zc_max'] ? abs($Coins['zc_max']) : 10000000);
		if ($num < $myzc_min) {
			$this->error(L('转出数量不能低于').$myzc_min);
		}
		if ($myzc_max < $num) {
			$this->error(L('转出数量最高限制').$myzc_max);
		}

		$user_coin = M('UserCoin')->where(array('userid' => userid()))->find();
		if ($user_coin[$coin] < $num) {
			$this->error(L('可用余额不足'));
		}

		$qbdz = $coin . 'b';
		$fee_user = M('UserCoin')->where(array($qbdz => $Coins['zc_user']))->find();

		if ($fee_user) {
			debug(L('手续费地址: ') . $Coins['zc_user'] . L('存在,有手续费'));
			$fee = round(($num / 100) * $Coins['zc_fee'], 8);
			$mum = round($num - $fee, 8);

			if ($mum < 0) {
				$this->error(L('转出手续费错误！'));
			}
			if ($fee < 0) {
				$this->error(L('转出手续费设置错误！'));
			}
		} else {
			debug(L('手续费地址: ') . $Coins['zc_user'] . L('不存在,无手续费'));
			$fee = 0;
			$mum = $num;
		}

		switch ($Coins['type']) {

			case 'rgb': //认购币
				$this->myzc_rgb_to_in($Coins, $user_info, $user_coin, $num, $mum, $addr, $fee_user, $fee);
				break;
			case 'qbb': //钱包币
				if(M('user_coin')->where(array($qbdz => $addr))->find()) {
					//开始钱包币站外转出
					$this->myzc_qbb_to_in($Coins, $user_info, $user_coin, $num, $mum, $addr, $fee_user, $fee);
				} else {
					//开始钱包币站外转出
					$this->myzc_qbb_to_out($Coins, $user_info, $user_coin, $num, $mum, $addr, $fee_user, $fee);
				}
				break;
			default:
				# code...
				break;
		}
	}

	//开始内部认购币转出
	protected function myzc_rgb_to_in($Coins, $user_info, $user_coin, $num, $mum, $addr, $fee_user, $fee){

		//debug($Coins, L('开始转出'));
		$coin = $Coins['name'];
		$qbdz = $coin . 'b'; //币地址
		//接受人币信息
		$peerCoin = M('UserCoin')->where(array($qbdz => $addr))->find();

		if (!$peerCoin) {
			$this->error(L('转出地址不存在！'));
		}

		$mo = M();
		$mo->execute('set autocommit=0');
		$mo->execute('lock tables tw_user_coin write ,tw_myzc write ,tw_myzr write ,tw_myzc_fee write ,tw_finance_log write ,tw_user read');

		$rs = array();
		
		if ($fee) {
			if ($mo->table('tw_user_coin')->where(array($qbdz => $Coins['zc_user']))->find()) {
				$rs[] = $mo->table('tw_user_coin')->where(array($qbdz => $Coins['zc_user']))->setInc($coin, $fee);
				debug(array('msg' => L('转出收取手续费') . $fee), 'fee');
			} else {
				
				if(isset($Coins['zc_user']) && is_string($Coins['zc_user']) && trim($Coins['zc_user']) != '0'){
					//添加手续费账户
					$fee_account_data = array('userid' => 0,$qbdz => $Coins['zc_user'], $coin => $fee);
					$rs[] = $mo->table('tw_user_coin')->add($fee_account_data);
					debug(array('msg' => L('转出收取手续费') . $fee), 'fee');
				}else{
					$mo->execute('rollback');
					$this->error(L("平台未配置{$coin}手续费账户地址！"));
				}
			}
		}

		$rs[] = $mo->table('tw_user_coin')->where(array('userid' => userid()))->setDec($coin, $num);
		$rs[] = $mo->table('tw_user_coin')->where(array('userid' => $peerCoin['userid']))->setInc($coin, $mum);

		//转出
		$rs[] = $this->add_my_zc($mo, $addr, $user_coin, $coin, $num, $mum, $fee);
		//转入
		$rs[] = $this->add_my_zr($mo, $peerCoin, $user_coin, $coin, $num, $mum, $fee);

		if ($fee_user) {

			$rs[] = $this->add_my_zc_fee($mo, $addr, $fee_user, $user_coin, $coin, $num, $mum, $fee);

		}
		
		// 处理资金变更日志-----------------S
		
		// 转出人记录
		$user_zj_coin = $mo->table('tw_user_coin')->where(array('userid' => userid()))->find();
		$rs[] = $this->add_finance_log($mo, $user_info, $user_coin, $user_zj_coin, 0, $num, 6, 1, $Coins);
		// 接受人用户信息
		$peer_info = $mo->table('tw_user')->where(array('id' => $peerCoin['userid']))->find();
		$user_peer_coin = $mo->table('tw_user_coin')->where(array('userid' => $peerCoin['userid']))->find();
		// 接受人记录
		$rs[] = $this->add_finance_log($mo, $peer_info, $peerCoin, $user_peer_coin, 1, $num, 7, 1, $Coins);

		// 处理资金变更日志-----------------E

		if (check_arr($rs)) {
			$mo->execute('commit');
			$mo->execute('unlock tables');
			session('myzc_verify', null);
			$this->success(L('转账成功！'));
		} else {
			$mo->execute('rollback');
			$this->error(L('转账失败!'));
		}
	}

	//开始钱包币站内转出
	protected function myzc_qbb_to_in($Coins, $user_info, $user_coin, $num, $mum, $addr, $fee_user, $fee){

		$coin = $Coins['name'];
		$qbdz = $coin . 'b'; //币地址
		$peerCoin = M('UserCoin')->where(array($qbdz => $addr))->find();
		if (!$peerCoin) {
			$this->error(L('转出地址不存在！'));
		}
		try{
			$mo = M();
			$mo->execute('set autocommit=0');
			$mo->execute('lock tables  tw_user_coin write  , tw_myzc write  , tw_myzr write , tw_myzc_fee write,tw_finance_log write,tw_user read');

			$rs = array();
			
			if ($fee) {
				if ($mo->table('tw_user_coin')->where(array($qbdz => $Coins['zc_user']))->find()) {
					$rs[] = $mo->table('tw_user_coin')->where(array($qbdz => $Coins['zc_user']))->setInc($coin, $fee);
				} else {

					if(isset($Coins['zc_user']) && is_string($Coins['zc_user']) && trim($Coins['zc_user']) != '0'){
						//添加手续费账户
						$fee_account_data = array('userid' => 0,$qbdz => $Coins['zc_user'], $coin => $fee);
						$rs[] = $mo->table('tw_user_coin')->add($fee_account_data);
					}else{
						$mo->execute('rollback');
						$this->error(L("平台未配置{$coin}手续费账户地址！"));
					}
				}
			}

			$rs[] = $mo->table('tw_user_coin')->where(array('userid' => userid()))->setDec($coin, $num);
			$rs[] = $mo->table('tw_user_coin')->where(array('userid' => $peerCoin['userid']))->setInc($coin, $mum);

			//转出
			$rs[] = $this->add_my_zc($mo, $addr, $user_coin, $coin, $num, $mum, $fee);
			//转入
			$rs[] = $this->add_my_zr($mo, $peerCoin, $user_coin, $coin, $num, $mum, $fee);

			if ($fee_user) {

				$rs[] = $this->add_my_zc_fee($mo, $addr, $fee_user, $user_coin, $coin, $num, $mum, $fee);
			}
			
			// 处理资金变更日志-----------------S

			// 转出人记录
			$user_zj_coin = $mo->table('tw_user_coin')->where(array('userid' => userid()))->find();

			$rs[] = $this->add_finance_log($mo, $user_info, $user_coin, $user_zj_coin, 0, $num, 6, 1, $Coins);

			// 接受人用户信息
			$peer_info = $mo->table('tw_user')->where(array('id' => $peerCoin['userid']))->find();
			$user_peer_coin = $mo->table('tw_user_coin')->where(array('userid' => $peerCoin['userid']))->find();
			// 接受人记录
			$rs[] = $this->add_finance_log($mo, $peer_info, $peerCoin, $user_peer_coin, 1, $num, 7, 1, $Coins);

			// 处理资金变更日志-----------------E

			if (check_arr($rs)) {
				$mo->execute('commit');
				$mo->execute('unlock tables');
				session('myzc_verify', null);
				$this->success(L('转账成功！'));
			} else {
				throw new \Think\Exception(L('转账失败!'));
			}
		} catch (\Think\Exception $e){
			$mo->execute('rollback');
			$mo->execute('unlock tables');
			$this->error(L('转账失败'));
		}
	}

	//开始钱包币站外转出
	protected function myzc_qbb_to_out($Coins, $user_info, $user_coin, $num, $mum, $addr, $fee_user, $fee){

		$coin = $Coins['name'];
		$qbdz = $coin . 'b'; //币地址
		$dj_username = $Coins['dj_yh'];
		$dj_password = $Coins['dj_mm'];
		$dj_address = $Coins['dj_zj'];
		$dj_port = $Coins['dj_dk'];
		
		$coin_config = M('Coin')->where(array('name' => $coin))->find();
		if ($coin_config['api_type'] == 'eth'){  //ETH对接,FFF
			$auto_status = 0; //全部手动审核
			
		} elseif ($coin=='tatc') {
			$auto_status = 0; //全部手动审核
			
		} elseif ($coin_config['api_type'] == 'btc') { //比特系RPC调用
			$auto_status = 0; //全部手动审核

		} else {
			$auto_status = 0; //全部手动审核
		}

		try{
			$mo = M();
			$mo->execute('set autocommit=0');
			$mo->execute('lock tables tw_user_coin write ,tw_myzc write ,tw_myzr write ,tw_myzc_fee write ,tw_finance_log write ,tw_user read');

			$rs = array();
			if ($fee && $auto_status) {

				if ($mo->table('tw_user_coin')->where(array($qbdz => $Coins['zc_user']))->find()) {
					$rs[] = $r = $mo->table('tw_user_coin')->where(array($qbdz => $Coins['zc_user']))->setInc($coin, $fee);
					debug(array('res' => $r, 'lastsql' => $mo->table('tw_user_coin')->getLastSql()), '新增费用');
				} else {
					if(isset($Coins['zc_user']) && is_string($Coins['zc_user']) && trim($Coins['zc_user']) != '0'){
						//添加手续费账户
						$fee_account_data = array('userid' => 0, $qbdz => $Coins['zc_user'], $coin => $fee);
						$rs[] = $r = $mo->table('tw_user_coin')->add($fee_account_data);
					}else{
						$mo->execute('rollback');
						$this->error(L("平台未配置{$coin}手续费账户地址！"));
					}
				}

				$rs[] = $this->add_my_zc_fee($mo, $addr, $fee_user, $user_coin, $coin, $num, $mum, $fee, 2);
			}

			$rs[] = $r = $mo->table('tw_user_coin')->where(array('userid'=>userid()))->setDec($coin,$num);

			$rs[] = $aid = $this->add_my_zc($mo, $addr, $user_coin, $coin, $num, $mum, $fee, $auto_status);
			
			// 处理资金变更日志-----------------S
			
			// 转出人记录
			$user_zj_coin = $mo->table('tw_user_coin')->where(array('userid' => userid()))->find();

			$rs[] = $this->add_finance_log($mo, $user_info, $user_coin, $user_zj_coin, 0, $num, 6, 1, $Coins);

			// 处理资金变更日志-----------------E
					
			//$mum是扣除手续费后的金额
			if (check_arr($rs)) {
				if ($auto_status) { //自动钱包转出

					if ($coin=='eth' || $coin=='etc') { //以太坊20171110

						$EthClient = EthCommon($dj_address, $dj_port);
						$mum = $EthClient->toWei($mum);
						$sendrs = $EthClient->eth_sendTransaction($dj_username,$addr,$dj_password,$mum);

					} elseif ($coin='tatc') {

						$EthClient = EthCommon($dj_address, $dj_port);
						$mum = dechex ($mum*10000);//代币的位数10000
						$amounthex = sprintf("%064s",$mum);
						$addr2 = explode('0x',  $addr)[1];//接受地址
						$dataraw = '0xa9059cbb000000000000000000000000'.$addr2.$amounthex;//拼接data
						$constadd = '0x09a2fe80c940a39eee7b69e2b89af129cf5006bd';//合约地址
						$sendrs = $EthClient->eth_sendTransactionraw($dj_username,$constadd,$dj_password,$dataraw);
						//转出账户,合约地址,转出账户解锁密码,data值

					} else { //其他币20170922
						$sendrs = $CoinClient->sendtoaddress($addr, floatval($mum));
					}

					if ($sendrs) {
						$res = $mo->table('tw_myzc')->where(array('id'=>$aid))->save(array('txid'=>$sendrs));
						$mo->execute('commit');
						$mo->execute('unlock tables');
					} else{
						throw new \Think\Exception(L('转出失败!1'));
					}
				} else { //后台审核转出
					$mo->execute('commit');
					$mo->execute('unlock tables');
					session('myzc_verify', null);
					$this->success(L('转出申请成功,请等待审核！'));
				}
			} else {
				throw new \Think\Exception(L('转出失败!2'));
			}
		}catch(\Think\Exception $e){
			$mo->execute('rollback');
			$mo->execute('unlock tables');
			$this->error(L('转出失败!3'));
		}
		
		if (!$auto_status) { //手动转出

			$flag = 1;
		} else{ //自动转出

			if ($sendrs) { //自动转出成功

				$flag = 1;
				if ($coin=='eth' or $coin=='tatc') { //以太坊20170922
					if(!$sendrs){
						$flag = 0;
					}
				} else {
					$arr = json_decode($sendrs, true);
					if (isset($arr['status']) && ($arr['status'] == 0)) {
						$flag = 0;
					}
				}
			} else { //自动转出失败
				$flag = 0;
			}
		}

		if (!$flag) {
			$this->error(L('钱包服务器转出币种失败,请手动转出'));
		} else {
			$this->success(L('转出成功!'));
		}
	}
	
	// 委托管理
	public function mywt($market = NULL, $type = NULL, $status = NULL)
	{
		// 过滤非法字符----------------S
		if (checkstr($market) || checkstr($type) || checkstr($status)) {
			$this->error(L('您输入的信息有误！'));
		}
		// 过滤非法字符----------------E

		if (!userid()) {
			redirect('/Login/index');
		}
		
		//获取用户信息
		$User = M('User')->where(array('id' => userid()))->find();
		$this->assign('user', $User);

		check_server();
		$Coins = M('Coin')->where(array('status' => 1))->select();
		foreach ($Coins as $k => $v) {
			$coin_list[$v['name']] = $v;
		}

		$this->assign('coin_list', $coin_list);
		$Market = M('Market')->where(array('status' => 1))->select();
		foreach ($Market as $k => $v) {
			$v['xnb'] = explode('_', $v['name'])[0];
			$v['rmb'] = explode('_', $v['name'])[1];
			$market_list[$v['name']] = $v;
			// $market_list[$k]['nnn'] = strtoupper($v['xnb'].'/'.$v['rmb']);
		}
		$this->assign('market_list', $market_list);

		if (!$market_list[$market]) {
			$market = $Market[0]['name'];
		}

		$where['market'] = $market;
		if (($type == 1) || ($type == 2)) {
			$where['type'] = $type;
		}
		if (($status == 1) || ($status == 2) || ($status == 3)) {
			$where['status'] = $status - 1;
		}

		$where['userid'] = userid();
		$this->assign('market', $market);
		$this->assign('type', $type);
		$this->assign('status', $status);
		$Mobile = M('Trade');
		$count = $Mobile->where($where)->count();
		$Page = new \Think\Page($count, 10);
		$Page->parameter .= 'type=' . $type . '&status=' . $status . '&market=' . $market . '&';
		$show = $Page->show();
		$list = $Mobile->where($where)->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();

		foreach ($list as $k => $v) {
			$list[$k]['num'] = $v['num'] * 1;
			$list[$k]['price'] = $v['price'] * 1;
			$list[$k]['deal'] = $v['deal'] * 1;
		}

		$this->assign('list', $list);
		$this->assign('page', $show);
		$this->display();
	}

	public function mycj($market = NULL, $type = NULL)
	{
		// 过滤非法字符----------------S
		if (checkstr($market) || checkstr($type)) {
			$this->error(L('您输入的信息有误！'));
		}
		// 过滤非法字符----------------E

		if (!userid()) {
			redirect('/Login/index');
		}
		
		//获取用户信息
		$User = M('User')->where(array('id' => userid()))->find();
		$this->assign('user', $User);

		check_server();
		$Coins = M('Coin')->where(array('status' => 1))->select();

		foreach ($Coins as $k => $v) {
			$coin_list[$v['name']] = $v;
		}

		$this->assign('coin_list', $coin_list);
		$Market = M('Market')->where(array('status' => 1))->select();

		foreach ($Market as $k => $v) {
			$v['xnb'] = explode('_', $v['name'])[0];
			$v['rmb'] = explode('_', $v['name'])[1];
			$market_list[$v['name']] = $v;
		}

		$this->assign('market_list', $market_list);

		if (!$market_list[$market]) {
			$market = $Market[0]['name'];
		}

		if ($type == 1) {
			$where = 'userid=' . userid() . ' && market=\'' . $market . '\'';
		} else if ($type == 2) {
			$where = 'peerid=' . userid() . ' && market=\'' . $market . '\'';
		} else {
			$where = '((userid=' . userid() . ') || (peerid=' . userid() . ')) && market=\'' . $market . '\'';
		}

		$this->assign('market', $market);
		$this->assign('type', $type);
		$this->assign('userid', userid());
		
		$Mobile = M('TradeLog');
		$count = $Mobile->where($where)->count();
		$Page = new \Think\Page($count, 10);
		$Page->parameter .= 'type=' . $type . '&market=' . $market . '&';
		$show = $Page->show();
		$list = $Mobile->where($where)->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();

		foreach ($list as $k => $v) {
			$list[$k]['num'] = $v['num'] * 1;
			$list[$k]['price'] = $v['price'] * 1;
			$list[$k]['mum'] = $v['mum'] * 1;
			$list[$k]['fee_buy'] = $v['fee_buy'] * 1;
			$list[$k]['fee_sell'] = $v['fee_sell'] * 1;
		}

		$this->assign('list', $list);
		$this->assign('page', $show);
		$this->display();
	}
	
	// 推广邀请
	public function invite()
	{
		if (!userid()) {
			redirect('/Login/index');
		}
		
		$user = M('User')->where(array('id' => userid()))->find();
		
		if ($user['kyc_lv'] == 1) {
			if ($user['idstate'] == 2) {
				$this->assign('idcard', 1);
			} else {
				$this->error(L('请实名认证，再进行操作！'), U('User/index'));
			}
		} else if ($user['kyc_lv'] == 2) {
			$this->assign('idcard', 1);
		}

		$exchange_config = M('exchange_config')->where(['id'=>1])->find();
		$this->assign('exchange_config', $exchange_config);
		// check_server();
		
		$useracc= M('User')->where(array('id' => $user['invit_1']))->getField('username');
		if (!$user['invit']) {
			for (; true; ) {
				$tradeno = tradenoa();

				if (!M('User')->where(array('invit' => $tradeno))->find()) {
					break;
				}
			}

			M('User')->where(array('id' => userid()))->save(array('invit' => $tradeno));
			$user = M('User')->where(array('id' => userid()))->find();
		}

		$this->assign('user', $user);
		$this->assign('useracc', $useracc);
		$this->display();
	}

	public function mywd()
	{
		if (!userid()) {
			redirect('/Login/index');
		}
		
		// 统计
		$tongji['invit_1'] = M('User')->where(array('invit_1' => userid()))->count();
		$tongji['invit_2'] = M('User')->where(array('invit_2' => userid()))->count();
		$this->assign('tongji', $tongji);

		$where['invit_1'] = userid();
		$Model = M('User');
		$count = $Model->where($where)->count();
		$Page = new \Think\Page($count, 10);
		$show = $Page->show();
		$list = $Model->where($where)->order('id asc')->field('id,username,mobile,addtime,invit_1')->limit($Page->firstRow . ',' . $Page->listRows)->select(); //直推

		foreach ($list as $k => $v) {
			$list[$k]['invits'] = M('User')->where(array('invit_1' => $v['id']))->order('id asc')->field('id,username,mobile,addtime,invit_1')->select();
			$list[$k]['invitss'] = count($list[$k]['invits']); //累计二代

			foreach ($list[$k]['invits'] as $kk => $vv) {
				$list[$k]['invits'][$kk]['invits'] = M('User')->where(array('invit_1' => $vv['id']))->order('id asc')->field('id,username,mobile,addtime,invit_1')->select();
				$list[$k]['invits'][$kk]['invitss'] = count($list[$k]['invits'][$kk]['invits']); //累计三代
			}
		}

		$this->assign('list', $list);
		$this->assign('page', $show);
		$this->display();
	}

	// public function myjp()
	// 佣金记录
	public function myyj()
	{
		if (!userid()) {
			redirect('/Login/index');
		}
		
		// 统计
		$tongji['daozhang'] = M('invit')->where(array('id' => userid(),'status'=>1))->sum('fee');
		$tongji['weidaozhang'] = M('invit')->where(array('id' => userid(),'status'=>0))->sum('fee');
		$this->assign('tongji', $tongji);
		
		//获取用户信息
		$User = M('User')->where(array('id' => userid()))->find();
		$this->assign('user', $User);

		check_server();
		$where['userid'] = userid();
		$Model = M('Invit');
		$count = $Model->where($where)->count();
		$Page = new \Think\Page($count, 10);
		$show = $Page->show();
		$list = $Model->where($where)->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();

		foreach ($list as $k => $v) {
			$list[$k]['invit'] = M('User')->where(array('id' => $v['invit']))->getField('username');
		}

		$this->assign('list', $list);
		$this->assign('page', $show);
		$this->display();
	}
	
	// 我的分红
	public function myfh()
	{
		if (!userid()) {
			redirect('/Login/index');
		}
		
		//获取用户信息
		$User = M('User')->where(array('id' => userid()))->find();
		$this->assign('user', $User);
		
		$this->display();
	}
	
	// 持币分红
	public function myfh_cbfh()
	{
		if (!userid()) {
			redirect('/Login/index');
		}
		
		//获取用户信息
		$User = M('User')->where(array('id' => userid()))->find();
		$this->assign('user', $User);

		$where['userid'] = userid();
		$Model = M('FenhongLog');
		$count = $Model->where($where)->count();
		$Page = new \Think\Page($count, 15);
		$show = $Page->show();
		$list = $Model->where($where)->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
		$this->assign('list', $list);
		$this->assign('page', $show);
		$this->display();
	}
	
	// 交易挖矿
	public function myfh_jywk()
	{
		if (!userid()) {
			redirect('/Login/index');
		}
		
		//获取用户信息
		$User = M('User')->where(array('id' => userid()))->find();
		$this->assign('user', $User);

		$where['userid'] = userid();
		$Model = M('Mining');
		$count = $Model->where($where)->count();
		$Page = new \Think\Page($count, 15);
		$show = $Page->show();
		$list = $Model->where($where)->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
		$this->assign('list', $list);
		$this->assign('page', $show);
		$this->display();
	}
}

?>