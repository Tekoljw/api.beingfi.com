<?php
namespace Mobile\Controller;

class FinanceController extends MobileController
{
	protected function _initialize()
	{
		parent::_initialize();
		$allow_action=array("index","financeInfo","myzr","myzr_coin_list","myzr_log","myzc","myzcadd","myzc_coin_list","upmyzc","mywt","mywt_coin_list","mycj","mycj_coin_list","mytj","mywd","myjp","myczlog","mycz_type_ajax","myzc_user","upmyzc_user","getShort" ,"coin_show");
		if(!in_array(ACTION_NAME,$allow_action)){
			$this->error(L("非法操作！"));
		}
	}
	
	public function index()
	{
		if (!userid()) {
			redirect('/Login/index.html');
		}

		$CoinList = M('Coin')->where(array('status' => 1))->select();
		$UserCoin = M('UserCoin')->where(array('userid' => userid()))->find();
		$Market = M('Market')->where(array('status' => 1))->select();

		foreach ($Market as $k => $v) {
			$Market[$v['name']] = $v;
		}

		$cny['zj'] = 0;

		foreach ($CoinList as $k => $v) {
			if ($v['name'] == 'cny') {
				$cny['ky'] = round($UserCoin[$v['name']], 2) * 1;
				$cny['dj'] = round($UserCoin[$v['name'] . 'd'], 2) * 1;
				$cny['zj'] = $cny['zj'] + $cny['ky'] + $cny['dj'];
			} else {
				if ($Market[$v['name'].'_'.Anchor_CNY]['new_price']) {
					$jia = $Market[$v['name'].'_'.Anchor_CNY]['new_price'];
				} else {
					$jia = 1;
				}

				$coinList[$v['name']] = array(
					'id' => $v['id'],
					'name' => $v['name'], 
					'img' => $v['img'], 
					'title' => $v['title'], 
					'xnb' => round($UserCoin[$v['name']], 6) * 1, 
					'xnbd' => round($UserCoin[$v['name'] . 'd'], 6) * 1, 
					'xnbz' => round($UserCoin[$v['name']] + $UserCoin[$v['name'] . 'd'], 6), 
					'jia' => $jia * 1, 
					'zhehe' => round(($UserCoin[$v['name']] + $UserCoin[$v['name'] . 'd']) * $jia, 2)
				);
				
				$coinList[$v['name']]['zhehe'] = sprintf("%.4f", $coinList[$v['name']]['zhehe']);
				$cny['zj'] = round($cny['zj'] + (($UserCoin[$v['name']] + $UserCoin[$v['name'] . 'd']) * $jia), 2) * 1;
				$coinList[$v['name']]['xnb'] = sprintf("%.4f", $coinList[$v['name']]['xnb']);
				$coinList[$v['name']]['xnbd'] = sprintf("%.4f", $coinList[$v['name']]['xnbd']);
				$coinList[$v['name']]['xnbz'] = sprintf("%.4f", $coinList[$v['name']]['xnbz']);
				$coinList[$v['name']]['zhehe'] = sprintf("%.2f", $coinList[$v['name']]['zhehe']);
				//$coinList[$v['name']]['zhehe'] = number_format($coinList[$v['name']]['zhehe'],2);//千分位显示
				
				$coinList[$v['name']]['token_type'] = $v['token_type'];
			}
		}

		$cny['dj'] = sprintf("%.2f", $cny['dj']);
		$cny['ky'] = sprintf("%.2f", $cny['ky']);
		$cny['zj'] = sprintf("%.2f", $cny['zj']);
		$cny['dj'] = number_format($cny['dj'],2);//千分位显示
		$cny['ky'] = number_format($cny['ky'],2);//千分位显示
		//$cny['zj'] = number_format($cny['zj'],2);//千分位显示

		$this->assign('cny', $cny);
		$this->assign('coinList', $coinList);
		$this->display();
	}

	//展示交易详情
	public function financeInfo(){

		$userid = userid();
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

		//今日成功的C2C交易金额
		$where = "(userid = {$userid} OR aid = {$userid}) AND addtime >= {$today_start} AND addtime <= {$curtime} AND status = 3";
		$exchange_sum_today_success = D('ExchangeOrder')->where($where)->sum('mum');
		$exchange_sum_today_success = isset($exchange_sum_today_success)?$exchange_sum_today_success:0;
		$exchange_sum_today_success = round($exchange_sum_today_success, 4);
		$this->assign('exchange_sum_today_success', $exchange_sum_today_success);

		//c2c交易总额
		$where = "(userid = {$userid} OR aid = {$userid})";
		$exchange_sum_total = D('ExchangeOrder')->where($where)->sum('mum');
		$exchange_sum_total = isset($exchange_sum_total)?$exchange_sum_total:0;
		$exchange_sum_total = round($exchange_sum_total, 4);
		$this->assign('exchange_sum_total', $exchange_sum_total);

		//c2c交易成功总额
		$where = "(userid = {$userid} OR aid = {$userid}) AND status = 3";
		$exchange_history_sum_total_success = M('exchange_order_history_sum')->where(['userid'=>$userid])->sum('mum_sum');
		$exchange_history_sum_total_success = isset($exchange_history_sum_total_success)?$exchange_history_sum_total_success:0;
		$exchange_sum_total_success = $exchange_history_sum_total_success + $exchange_sum_today_success;
		$exchange_sum_total_success = round($exchange_sum_total_success, 4);
		$this->assign('exchange_sum_total_success', $exchange_sum_total_success);

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

		$this->assign('list', $list);
		$this->display();
	}
	
	public function coin_show($coin)
	{
		// 过滤非法字符----------------S
		if (checkstr($coin)) {
			$this->error(L('您输入的信息有误！'));
		}
		// 过滤非法字符----------------E
		
		if (!userid()) {
			redirect('/Login/index.html');
		}
		
		$Coin = M('Coin')->where(array('id' => $coin,'status' => 1))->find();
		$CoinInfo = $Coin;
		$CoinInfo['name'] = strtoupper($Coin['name']);
		
		$this->assign('coin_info', $CoinInfo);
		

		$Market = M('Market')->where(array('status' => 1))->select();
		foreach ($Market as $k => $v) {
			$Market[$v['name']] = $v;
		}
		if ($Market[$Coin['name'].'_'.Anchor_CNY]['new_price']) {
			$jia = $Market[$Coin['name'].'_'.Anchor_CNY]['new_price'];
		} else {
			$jia = 1;
		}
		
		$user_coin = M('UserCoin')->where(array('userid' => userid()))->find();
		$user_coin['xnb'] = sprintf("%.6f", $user_coin[$Coin['name']]);
		$user_coin['xnbd'] = sprintf("%.6f", $user_coin[$Coin['name'].'d']);
		$user_coin['zhehe'] = round(($user_coin[$Coin['name']] + $user_coin[$Coin['name'] . 'd']) * $jia, 2);
		$this->assign('user_coin', $user_coin);
		
		$this->assign('coin', $Coin['name']);
		$this->display();
	}
	
	public function CURLQueryString($url)
	{
        //设置附加HTTP头
        $addHead=array("Content-type: application/json");
        //初始化curl
        $curl_obj=curl_init();
        //设置网址
        curl_setopt($curl_obj,CURLOPT_URL,$url);
        //附加Head内容
        curl_setopt($curl_obj,CURLOPT_HTTPHEADER,$addHead);
        //是否输出返回头信息
        curl_setopt($curl_obj,CURLOPT_HEADER,0);
        //将curl_exec的结果返回
        curl_setopt($curl_obj,CURLOPT_RETURNTRANSFER,1);
        //设置超时时间
        curl_setopt($curl_obj,CURLOPT_TIMEOUT,8);
        //执行
        $result=curl_exec($curl_obj);
        //关闭curl回话
        curl_close($curl_obj);
        return $result;
    }
	
	public function mytj()
	{
		if (!userid()) {
			redirect('/#login');
		}
		$user = M('User')->where(array('id' => userid()))->find();
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
		$user_url="http://".$_SERVER['HTTP_HOST']."/Login/register/invit/".$user['invit'];
		$this->assign('user', $user);
		$this->assign('user_url', $user_url);
		$this->assign('useracc', $useracc);
		$this->display();
	}

	public function mywd()
	{
		if (!userid()) {
			redirect('/#login');
		}

		$where['invit_1'] = userid();
		$Model = M('User');
		$count = $Model->where($where)->count();
		$Page = new \Think\Page($count, 10);
		$show = $Page->show();
		$list = $Model->where($where)->order('id asc')->field('id,username,mobile,addtime,invit_1')->limit($Page->firstRow . ',' . $Page->listRows)->select();

		foreach ($list as $k => $v) {
			$list[$k]['invits'] = M('User')->where(array('invit_1' => $v['id']))->order('id asc')->field('id,username,mobile,addtime,invit_1')->select();
			$list[$k]['invitss'] = count($list[$k]['invits']);

			foreach ($list[$k]['invits'] as $kk => $vv) {
				$list[$k]['invits'][$kk]['invits'] = M('User')->where(array('invit_1' => $vv['id']))->order('id asc')->field('id,username,mobile,addtime,invit_1')->select();
				$list[$k]['invits'][$kk]['invitss'] = count($list[$k]['invits'][$kk]['invits']);
			}
		}

		$this->assign('list', $list);
		$this->assign('page', $show);
		$this->display();
	}
	
	public function myjp()
	{
		if (!userid()) {
			redirect('/#login');
		}
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
	
	// 充值记录
	public function myczlog()
	{
		if (!userid()) {
			redirect("/Login/index");
		}

		$where = array();

		$where['userid'] = userid();
		$count = M('Mycz')->where($where)->count();
		$Page = new \Think\Page($count, 15);
		$show = $Page->show();
		$list = M('Mycz')->where($where)->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();

		foreach ($list as $k => $v) {
			$list[$k]['type'] = M('MyczType')->where(array('name' => $v['type']))->getField('title');
			$list[$k]['num'] = (Num($v['num']) ? Num($v['num']) : '');
			$list[$k]['mum'] = (Num($v['mum']) ? Num($v['mum']) : '');
			$list[$k]['num'] = sprintf("%.2f", $list[$k]['num']);
			$list[$k]['mum'] = sprintf("%.2f", $list[$k]['mum']);
		}

		$this->assign('list', $list);
		$this->assign('page', $show);
		$this->display();
	}


	// 充值方式ajax处理
	public function mycz_type_ajax($pp)
	{
		// 过滤非法字符----------------S
		if (checkstr($pp)) {
			$this->error(L('您输入的信息有误！'));
		}
		// 过滤非法字符----------------E

		if($pp){
			$my = M('MyczType')->select();
			if($my){
				foreach ($my as $k => $v) {
					if($v['name'] == $pp){
						if($v['min']){
							echo $v['min'];die();
						}else{
							echo 0;die();
						}
					}
				}
				echo 0;
			}else{
				echo 0;
			}
		}else{
			echo 0;
		}
	}

	// 转入虚拟币记录
	public function myzr_log($coin = null)
	{
		// 过滤非法字符----------------S
		if (checkstr($coin)) {
			$this->error(L('您输入的信息有误！'));
		}
		// 过滤非法字符----------------E

		$Coin = M('Coin')->where(array('name' => $coin))->find();
		$this->assign('coin_info', $Coin);

		$where['userid'] = userid();
		$where['coinname'] = $coin;
		$where['from_user'] = '0';
		$Moble = M('Myzr');
		$count = $Moble->where($where)->count();
		$Page = new \Think\Page1($count, 10);
		$show = $Page->show();
		$list = $Moble->where($where)->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
		foreach ($list as $key => $value) {
			$list[$key]['num']=sprintf("%.4f", $value['num']);
			$list[$key]['mum']=sprintf("%.4f", $value['mum']);
			$list[$key]['fee']=sprintf("%.4f", $value['fee']);
		}
		$this->assign('list', $list);
		$this->assign('page', $show);

		$this->display();

	}

	public function mycj_coin_list()
	{
		// // 获取币种列表信息------S
		// $map = array();
		// $map['name'] = array('NEQ','cny');
		// $map['status'] = 1;
		// $coin_list = M('Coin')->where($map)->order('id desc')->select();

		$coin_list=M('market')->where('status=1')->select();
		foreach ($coin_list as $key => $v) {
			$xnb = explode('_', $v['name'])[0];
			$rmb = explode('_', $v['name'])[1];
			$coinxx=M('coin')->where(array('name'=>$xnb))->find();
			$coin_list[$key]['img']=$coinxx['img'];
			$coin_list[$key]['title']=strtoupper($xnb).'/'.strtoupper($rmb);
			# code...
		}

		$this->assign('coin_list', $coin_list);
		// 获取币种列表信息------E

		$this->display();
	}

	/* 币种列表页 */
	public function myzr_coin_list()
	{
		// 获取币种列表信息------S
		$map = array();
		$map['name'] = array('NEQ','cny');
		$map['status'] = 1;

		$coin_list = M('Coin')->where($map)->order('id desc')->select();

		$this->assign('coin_list', $coin_list);
		// 获取币种列表信息------E

		$this->display();
	}

	public function myzc_coin_list()
	{
		// 获取币种列表信息------S
		$map = array();
		$map['name'] = array('NEQ','cny');
		$map['status'] = 1;

		$coin_list = M('Coin')->where($map)->order('id desc')->select();

		$this->assign('coin_list', $coin_list);
		// 获取币种列表信息------E

		$this->display();
	}


	public function myuser_coin_list()
	{
		// 获取币种列表信息------S
		$map = array();
		$map['name'] = array('NEQ','cny');
		$map['status'] = 1;

		$coin_list = M('Coin')->where($map)->order('id desc')->select();

		$this->assign('coin_list', $coin_list);
		// 获取币种列表信息------E

		$this->display();
	}

	/* 币种列表页 */
	public function mywt_coin_list()
	{
		// 获取币种列表信息------S
		$map = array();
		// $map['name'] = array('NEQ','cny');
		$map['status'] = 1;

		$coin_list=M('market')->where('status=1')->select();
		foreach ($coin_list as $key => $v) {
			$xnb = explode('_', $v['name'])[0];
			$rmb = explode('_', $v['name'])[1];
			$coinxx=M('coin')->where(array('name'=>$xnb))->find();
			$coin_list[$key]['img']=$coinxx['img'];
			$coin_list[$key]['title']=strtoupper($xnb).'/'.strtoupper($rmb);
			# code...
		}

		$this->assign('coin_list', $coin_list);
		// 获取币种列表信息------E

		$this->display();
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
				}

				//外部币
				if ($Coins['type'] == 'qbb') {

					$dj_username = $Coins['dj_yh'];
					$dj_password = $Coins['dj_mm'];
					$dj_address = $Coins['dj_zj'];
					$dj_port = $Coins['dj_dk'];
					$CoinClient = CoinClient($dj_username, $dj_password, $dj_address, $dj_port, 5, array(), 1);
					$json = $CoinClient->getinfo();
					
					$coin_config = M('Coin')->where(array('name' => $coin))->find();
					if ($coin=='eth' || $coin=='eos' || $coin_config['token_type'] == 1)  //ETH对接,FFF
					{
						$coin_select = M('Coin')->where(array('api_type' => 'eth','token_type' => 1))->select();
						$ethcoin = array('eth'); //ETH对接,FFF
						foreach ($coin_select as $k => $v) {
							$ethcoin[] = $v['name'];
						}
						/*$ethcoin = array('eth','tip','eos','grav','fff');*/

						foreach ($ethcoin as $k => $v) {
							// dump($v);
							if ($user_coin[$v.'b']) {
								$qianbao=$user_coin[$v.'b'];
								break;
							}
						}
						
						if (!$qianbao) {

							$EthClient = EthCommon($dj_address, $dj_port);
							if (!$EthClient) {
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
						
					} elseif ($coin=='etc') {
						
						$CoinClient = EthCommon($dj_address, $dj_port);
						$qianbao= $CoinClient->personal_newAccount(username());//根据用户名生成账户
						if (!$qianbao || preg_match("/[\'.,:;*?~`!@#$%^&+=)(<>{}]|\]|\[|\/|\\\|\"|\|/",$qianbao)) {
							$qianbao = L('生成钱包地址出错！');
							$state_coin = 1;
						}else{
							$rs = M('UserCoin')->where(array('userid' => userid()))->save(array('etcb' => $qianbao));
							// $rs = M('UserCoin')->where(array('userid' => userid()))->save(array('tatcb' => $qianbao));
						}
					
					} elseif ($coin=='zec') {

						if (!isset($json['version']) || !$json['version']) {
							$qianbao = L('钱包链接失败！');
							$state_coin = 1;
						} else {
							$qianbao = $CoinClient->getnewaddress();
							if (!$qianbao || preg_match("/[\'.,:;*?~`!@#$%^&+=)(<>{}]|\]|\[|\/|\\\|\"|\|/",$qianbao)) {
								$qianbao = L('生成钱包地址出错！');
								$state_coin = 1;
							} else {
								$rs = M('UserCoin')->where(array('userid' => userid()))->save(array($qbAddress => $qianbao));
							}
						}

					}else{
						
						if (!isset($json['version']) || !$json['version']) {
							$qianbao = L('钱包链接失败！');
							$state_coin = 1;
						} else {
						
							$qianbao_addr = $CoinClient->getaddressesbylabel(username());
							$qianbao_addr = json_decode($qianbao_addr, true);
							//$qianbao_addr = $qianbao_addr['address'];
							if (!is_array($qianbao_addr)) {
								$qianbao_ad = $CoinClient->getnewaddress(username());
								if (!$qianbao_ad) {
									$qianbao = L('生成钱包地址出错getnewaddress！');
									$state_coin = 1;
								} else {
									$qianbao = $qianbao_ad;
								}
							} else {
								$qianbao = $qianbao_addr['address'];
							}

							if (!$qianbao || preg_match("/[\'.,:;*?~`!@#$%^&+=)(<>{}]|\]|\[|\/|\\\|\"|\|/",$qianbao)) {
								$qianbao = L('生成钱包地址出错 地址不配置 qianbao ='.$qianbao);
								$state_coin = 1;
							}

							if($state_coin == 0){
								$rs = M('UserCoin')->where(array('userid' => userid()))->save(array($qbAddress => $qianbao));
								if (!$rs) {
									$this->error(L('钱包地址添加出错！'));
								}
							}
						}
					}
				}

			}else{

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

	public function myzrold($coin = NULL)
	{
		// 过滤非法字符----------------S
		if (checkstr($coin)) {
			$this->error(L('您输入的信息有误！'));
		}
		// 过滤非法字符----------------E

		if (!userid()) {
			redirect('/Login/index.html');
		}

		// 获取币种信息
		$coin_info = M('Coin')->where(array('name' => $coin))->find();
		if(!$coin_info){
			$this->error(L('币种不存在'));
		}

		$this->assign('coin_info', $coin_info);

		$user_coin = M('UserCoin')->where(array('userid' => userid()))->find();
		$user_coin[$coin] = sprintf("%.4f", $user_coin[$coin]);
		$this->assign('user_coin', $user_coin);


		if (!$coin_info['zr_jz']) {
			$qianbao = '当前币种禁止转入！';
		} else {
			//钱包地址
			$qbdz = $coin . 'b';

			if (!$user_coin[$qbdz]) {
				if ($coin_info['type'] == 'rgb') {
					$qianbao = md5(username() . $coin);
					$rs = M('UserCoin')->where(array('userid' => userid()))->save(array($qbdz => $qianbao));

					if (!$rs) {
						$this->error(L('生成钱包地址出错！'));
					}
				}

				if ($coin_info['type'] == 'qbb') {
					$dj_username = $coin_info['dj_yh'];
					$dj_password = $coin_info['dj_mm'];
					$dj_address = $coin_info['dj_zj'];
					$dj_port = $coin_info['dj_dk'];
					$CoinClient = CoinClient($dj_username, $dj_password, $dj_address, $dj_port, 5, array(), 1);
					$json = $CoinClient->getinfo();

					if (!isset($json['version']) || !$json['version']) {
						$this->error(L('钱包链接失败！'));
					}

					$qianbao_addr = $CoinClient->getaddressesbyaccount(username());

					if (!is_array($qianbao_addr)) {
						$qianbao_ad = $CoinClient->getnewaddress(username());

						if (!$qianbao_ad) {
							$this->error(L('生成钱包地址出错！'));
						} else {
							$qianbao = $qianbao_ad;
						}
					} else {
						$qianbao = $qianbao_addr[0];
					}

					if (!$qianbao) {
						$this->error(L('生成钱包地址出错！'));
					}

					$rs = M('UserCoin')->where(array('userid' => userid()))->save(array($qbdz => $qianbao));
					if (!$rs) {
						$this->error(L('钱包地址添加出错！'));
					}
				}
			} else {
				$qianbao = $user_coin[$coin . 'b'];
			}
		}

		$this->assign('qianbao', $qianbao);
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

	//虚拟币转出申请
	public function myzc($coin = NULL)
	{
		// 过滤非法字符----------------S
		if (checkstr($coin)) {
			$this->error(L('您输入的信息有误！'));
		}
		// 过滤非法字符----------------E

		if (!userid()) {
			redirect('/Login/index.html');
		}

		$coin_info = M('Coin')->where(array('name' => $coin))->find();
		if(!$coin_info){
			$this->error(L('币种不存在'));
		}

		$this->assign('coin_info', $coin_info);

		$where = array();

		$where['userid'] = userid();
		$where['coinname'] = $coin;
		$where['to_user'] = array('neq','1' );
		$Mobile = M('Myzc');
		$count = $Mobile->where($where)->count();
		$Page = new \Think\Page1($count, 10);
		$show = $Page->show();
		$list = $Mobile->where($where)->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
		foreach ($list as $key => $value) {
			$list[$key]['num']=sprintf("%.4f", $value['num']);
			$list[$key]['mum']=sprintf("%.4f", $value['mum']);
			$list[$key]['fee']=sprintf("%.4f", $value['fee']);
		}
		$this->assign('list', $list);
		$this->assign('page', $show);
		$this->display();
	}

	/*
		转出虚拟币操作页面
	*/
	public function myzcadd($coin = NULL)
	{
		// 过滤非法字符----------------S
		if (checkstr($coin)) {
			$this->error(L('您输入的信息有误！'));
		}
		// 过滤非法字符----------------E

		if (!userid()) {
			redirect("/Login/index.html");
		}

		if (C('coin')[$coin]) {
			$coin = trim($coin);
		} else {
			$coin = C('xnb_mr');
		}

		$this->assign('xnb', $coin);
		$Coin = M('Coin')->where(array(
			'status' => 1,
			'name'   => array('neq', 'cny')
			))->select();

		foreach ($Coin as $k => $v) {
			$coin_list[$v['name']] = $v;
		}

		$this->assign('coin_list', $coin_list);
		$user_coin = M('UserCoin')->where(array('userid' => userid()))->find();
		$user_coin[$coin] = round($user_coin[$coin], 6);
		$this->assign('user_coin', $user_coin);

		if (!$coin_list[$coin]['zc_jz']) {
			$this->assign('zc_jz', L('当前币种禁止转出！'));
		} else {

			$userQianbaoList = M('UserQianbao')->where(array('userid' => userid(), 'status' => 1, 'coinname' => $coin))->order('id desc')->select();
			$this->assign('userQianbaoList', $userQianbaoList);
			$moble = M('User')->where(array('id' => userid()))->getField('mobile');

			if ($moble) {
				$moble = substr_replace($moble, '****', 3, 4);
			} else {

				redirect(U('/User/mobile'));
				exit();
			}

			$this->assign('moble', $moble);
		}

		$where['userid'] = userid();
		$where['coinname'] = $coin;
		$Moble = M('Myzc');
		$count = $Moble->where($where)->count();
		$Page = new \Think\Page1($count, 10);
		$show = $Page->show();

		$list = $Moble->where($where)->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();

		// 处理总计================================
		$lis = $Moble->where($where)->select();
		$fees = 0;
		$nums = 0;
		$mums = 0;
		foreach ($lis as $k => $v) {
			$fees += $v['fee'];
			$nums += $v['num'];
			$mums += $v['mum'];
		}
		$this->assign('fees', $fees);
		$this->assign('nums', $nums);
		$this->assign('mums', $mums);
		// 处理总计================================
		$user=M('user')->where(array('id'=>userid()))->find();
		$this->assign('user', $user);
		$this->assign('coin', $coin);
		$this->assign('list', $list);
		$this->assign('page', $show);
		$this->display();
	}

	//虚拟币转出确认
	public function upmyzc($coin, $num, $addr, $paypassword, $mobile_verify)
	{
		// 过滤非法字符----------------S
		if (checkstr($coin) || checkstr($num) || checkstr($mobile_verify)) {
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

		$num = abs($num);

		if (!check($num, 'currency')) {
			$this->error(L('数量格式错误！'));
		}
		if ($coin=='tatc') {
			if ($num <100) {
				$this->error(L('数量不能低于100！'));
			}
		} else {
			if ($num <0.1) {
				$this->error(L('数量不能低于0.1！'));
			}
		}

		if (!check($addr, 'dw')) {
			$this->error(L('钱包地址格式错误！'));
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

		$myzc_min = ($Coins['zc_min'] ? abs($Coins['zc_min']) : 0.0001);
		$myzc_max = ($Coins['zc_max'] ? abs($Coins['zc_max']) : 10000000);
		if ($num < $myzc_min) {
			$this->error(L('转出数量超过系统最小限制！'));
		}
		if ($myzc_max < $num) {
			$this->error(L('转出数量超过系统最大限制！'));
		}

		$user = M('User')->where(array('id' => userid()))->find();
		if (md5($paypassword) != $user['paypassword']) {
			if(!empty($user['paypassword'])){
				$this->error(L('交易密码错误！'));
			}else{
				$this->error(L('还未设置交易密码，请前往账户中心设置！'));
			}
		}

		// 搜索实名认证信息
		if($user['kyc_lv'] > 1){ //初级认证已通过
			$this->assign('idcard', 1);
		}
		elseif ($user['kyc_lv'] == 1 && $user['idstate'] == 2) { //实名认证等级
			$this->assign('idcard', 1);
		} else {
			$this->error(L('请初级实名认证，再进行操作！'), U('User/index'));
		}
		//今天上限判断
		if(!$this->isCanTodayMyzc($coin, $user['kyc_lv'])){
			$this->error(L('超过今日转出上限'.C('TODAY_FIANCE_KYC_LIMIT').'美金，进行机构认证可以打开限制！'));
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

		if ($Coins['type'] == 'rgb') { //认购币
			debug($Coins, L('开始转出'));
			$peer = M('UserCoin')->where(array($qbdz => $addr))->find();

			if (!$peer) {
				$this->error(L('转出地址不存在！'));
			}

			$mo = M();
			$mo->execute('set autocommit=0');
			// $mo->execute('lock tables  tw_user_coin write  , tw_myzc write  , tw_myzr write , tw_myzc_fee write');
			$mo->execute('lock tables  tw_user_coin write  , tw_myzc write  , tw_myzr write , tw_myzc_fee write,tw_finance_log write,tw_user read');

			$rs = array();
			if ($fee) {
				if ($mo->table('tw_user_coin')->where(array($qbdz => $Coins['zc_user']))->find()) {
					$rs[] = $mo->table('tw_user_coin')->where(array($qbdz => $Coins['zc_user']))->setInc($coin, $fee);
					debug(array('msg' => L('转出收取手续费') . $fee), 'fee');
				} else {
					if(isset($Coins['zc_user']) && is_string($Coins['zc_user'])){
						//添加手续费账户
						$fee_account_data = array('userid' => 0, $qbdz => $Coins['zc_user'], $coin => $fee);
						$rs[] = $mo->table('tw_user_coin')->add($fee_account_data);
						debug(array('msg' => L('转出收取手续费') . $fee), 'fee');
					}else{
						$mo->execute('rollback');
						$this->error(L("平台未配置{$coin}手续费账户地址！"));
					}
				}
			}

			$rs[] = $mo->table('tw_user_coin')->where(array('userid' => userid()))->setDec($coin, $num);
			$rs[] = $mo->table('tw_user_coin')->where(array('userid' => $peer['userid']))->setInc($coin, $mum);

			$rs[] = $mo->table('tw_myzc')->add(array('userid' => userid(), 'username' => $addr, 'coinname' => $coin, 'txid' => md5($addr . $user_coin[$coin . 'b'] . time()), 'num' => $num, 'fee' => $fee, 'mum' => $mum, 'addtime' => time(), 'status' => 1));
			$rs[] = $mo->table('tw_myzr')->add(array('userid' => $peer['userid'], 'username' => $user_coin[$coin . 'b'], 'coinname' => $coin, 'txid' => md5($user_coin[$coin . 'b'] . $addr . time()), 'num' => $num, 'fee' => $fee, 'mum' => $mum, 'addtime' => time(), 'status' => 1));

			if ($fee_user) {
				$rs[] = $mo->table('tw_myzc_fee')->add(array('userid' => $fee_user['userid'], 'username' => $Coins['zc_user'], 'coinname' => $coin, 'txid' => md5($user_coin[$coin . 'b'] . $Coins['zc_user'] . time()), 'num' => $num, 'fee' => $fee, 'type' => 1, 'mum' => $mum, 'addtime' => time(), 'status' => 1));
			}

			// 处理资金变更日志-----------------S

			// 转出人记录
			$user_zj_coin = $mo->table('tw_user_coin')->where(array('userid' => userid()))->find();
			$rs[] = $mo->table('tw_finance_log')->add(array('username' => $user['username'], 'adminname' => $user['username'], 'addtime' => time(), 'plusminus' => 0, 'amount' => $num, 'optype' => 6, 'position' => 1, 'cointype' => $Coins['id'], 'old_amount' => $user_coin[$coin], 'new_amount' => $user_zj_coin[$coin], 'userid' => userid(), 'adminid' => userid(),'addip'=>get_client_ip()));

			// 获取用户信息
			$user_info = $mo->table('tw_user')->where(array('id' => $peer['userid']))->find();
			$user_peer_coin = $mo->table('tw_user_coin')->where(array('userid' => $peer['userid']))->find();

			// 接受人记录
			$rs[] = $mo->table('tw_finance_log')->add(array('username' => $user_info['username'], 'adminname' => $user['username'], 'addtime' => time(), 'plusminus' => 1, 'amount' => $mum, 'optype' => 7, 'position' => 1, 'cointype' => $Coins['id'], 'old_amount' => $peer[$coin], 'new_amount' => $user_peer_coin[$coin], 'userid' => $peer['userid'], 'adminid' => userid(),'addip'=>get_client_ip()));

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

		if ($Coins['type'] == 'qbb') { //钱包币
			$mo = M();
			if ($mo->table('tw_user_coin')->where(array($qbdz => $addr))->find()) {
				debug($Coin, "开始钱包币站内转出");
				$peer = M('UserCoin')->where(array($qbdz => $addr))->find();
				if (!$peer) {
					$this->error(L('转出地址不存在！'));
				}
				try{
					$mo = M();
					$mo->execute('set autocommit=0');
					// $mo->execute('lock tables  tw_user_coin write  , tw_myzc write  , tw_myzr write , tw_myzc_fee write');
					$mo->execute('lock tables  tw_user_coin write  , tw_myzc write  , tw_myzr write , tw_myzc_fee write,tw_finance_log write,tw_user read');

					$rs = array();
					if ($fee) {
						if ($mo->table('tw_user_coin')->where(array($qbdz => $Coins['zc_user']))->find()) {
							$rs[] = $mo->table('tw_user_coin')->where(array($qbdz => $Coins['zc_user']))->setInc($coin, $fee);
						} else {
							
							if(isset($Coins['zc_user']) && is_string($Coins['zc_user'])){
								//添加手续费账户
								$fee_account_data = array('userid' => 0, $qbdz => $Coins['zc_user'], $coin => $fee);
								$rs[] = $mo->table('tw_user_coin')->add($fee_account_data);
							}else{
								$mo->execute('rollback');
								$this->error(L("平台未配置{$coin}手续费账户地址！"));
							}
						}
					}

					$rs[] = $mo->table('tw_user_coin')->where(array('userid' => userid()))->setDec($coin, $num);
					$rs[] = $mo->table('tw_user_coin')->where(array('userid' => $peer['userid']))->setInc($coin, $mum);

					$rs[] = $mo->table('tw_myzc')->add(array('userid' => userid(), 'username' => $addr, 'coinname' => $coin, 'txid' => md5($addr . $user_coin[$coin . 'b'] . time()), 'num' => $num, 'fee' => $fee, 'mum' => $mum, 'addtime' => time(), 'status' => 1));
					$rs[] = $mo->table('tw_myzr')->add(array('userid' => $peer['userid'], 'username' => $user_coin[$coin . 'b'], 'coinname' => $coin, 'txid' => md5($user_coin[$coin . 'b'] . $addr . time()), 'num' => $num, 'fee' => $fee, 'mum' => $mum, 'addtime' => time(), 'status' => 1));

					if ($fee_user) {
						$rs[] = $mo->table('tw_myzc_fee')->add(array('userid' => $fee_user['userid'], 'username' => $Coins['zc_user'], 'coinname' => $coin, 'txid' => md5($user_coin[$coin . 'b'] . $Coins['zc_user'] . time()), 'num' => $num, 'fee' => $fee, 'type' => 1, 'mum' => $mum, 'addtime' => time(), 'status' => 1));
					}

					// 处理资金变更日志-----------------S

					// 转出人记录
					$user_zj_coin = $mo->table('tw_user_coin')->where(array('userid' => userid()))->find();
					$rs[] = $mo->table('tw_finance_log')->add(array('username' => $user['username'], 'adminname' => $user['username'], 'addtime' => time(), 'plusminus' => 0, 'amount' => $num, 'optype' => 6, 'position' => 1, 'cointype' => $Coins['id'], 'old_amount' => $user_coin[$coin], 'new_amount' => $user_zj_coin[$coin], 'userid' => userid(), 'adminid' => userid(),'addip'=>get_client_ip()));

					// 获取用户信息
					$user_info = $mo->table('tw_user')->where(array('id' => $peer['userid']))->find();
					$user_peer_coin = $mo->table('tw_user_coin')->where(array('userid' => $peer['userid']))->find();

					// 接受人记录
					$rs[] = $mo->table('tw_finance_log')->add(array('username' => $user_info['username'], 'adminname' => $user['username'], 'addtime' => time(), 'plusminus' => 1, 'amount' => $mum, 'optype' => 7, 'position' => 1, 'cointype' => $Coins['id'], 'old_amount' => $peer[$coin], 'new_amount' => $user_peer_coin[$coin], 'userid' => $peer['userid'], 'adminid' => userid(),'addip'=>get_client_ip()));

					// 处理资金变更日志-----------------E

					if (check_arr($rs)) {
						$mo->execute('commit');
						$mo->execute('unlock tables');
						session('myzc_verify', null);
						$this->success(L('转账成功！'));
					} else {
						throw new \Think\Exception(L('转账失败!'));
					}
				}catch(\Think\Exception $e){
					$mo->execute('rollback');
					$mo->execute('unlock tables');
					$this->error(L('转账失败!'));
				}
			} else {
				debug($Coin, "开始钱包币站外转出");
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
							if(isset($Coins['zc_user']) && is_string($Coins['zc_user'])){
								//添加手续费账户
								$fee_account_data = array('userid' => 0, $qbdz => $Coins['zc_user'], $coin => $fee);
								$rs[] = $r = $mo->table('tw_user_coin')->add($fee_account_data);
							}else{
								$mo->execute('rollback');
								$this->error(L("平台未配置{$coin}手续费账户地址！"));
							}
						}

						$rs[] = $mo->table('tw_myzc_fee')->add(array('userid' => $fee_user['userid'], 'username' => $Coins['zc_user'], 'coinname' => $coin, 'num' => $num, 'fee' => $fee, 'mum' => $mum, 'type' => 2, 'addtime' => time(), 'status' => 1));
					}

					$rs[] = $r = $mo->table('tw_user_coin')->where(array('userid' => userid()))->setDec($coin, $num);
					$rs[] = $aid = $mo->table('tw_myzc')->add(array('userid' => userid(), 'username' => $addr, 'coinname' => $coin, 'num' => $num, 'fee' => $fee, 'mum' => $mum, 'addtime' => time(), 'status' => $auto_status));

					// 处理资金变更日志-----------------S

					// 转出人记录
					$user_zj_coin = $mo->table('tw_user_coin')->where(array('userid' => userid()))->find();
					$rs[] = $mo->table('tw_finance_log')->add(array('username' => $user['username'], 'adminname' => $user['username'], 'addtime' => time(), 'plusminus' => 0, 'amount' => $num, 'optype' => 6, 'position' => 1, 'cointype' => $Coins['id'], 'old_amount' => $user_coin[$coin], 'new_amount' => $user_zj_coin[$coin], 'userid' => userid(), 'adminid' => userid(),'addip'=>get_client_ip()));

					// 处理资金变更日志-----------------E
					
					//$mum是扣除手续费后的金额
					if (check_arr($rs)) {
						if ($auto_status) {
							if ($coin=='eth' || $coin=='etc') {//以太坊20171110
/*								$mo->execute('commit');
								$mo->execute('unlock tables');
								session('myzc_verify', null);
								$this->success(L('转出申请成功,请等待审核！'));*/

								$EthClient = EthCommon($dj_address, $dj_port);
								$mum = $EthClient->toWei($mum);
								$sendrs = $EthClient->eth_sendTransaction($dj_username,$addr,$dj_password,$mum);

							} elseif($coin='tatc') {
/*								$mo->execute('commit');
								$mo->execute('unlock tables');
								session('myzc_verify', null);
								$this->success(L('转出申请成功,请等待审核！'));*/

								$EthClient = EthCommon($dj_address, $dj_port);
								$mum = dechex ($mum*10000);//代币的位数10000
								$amounthex = sprintf("%064s",$mum);
								$addr2 = explode('0x',  $addr)[1];//接受地址
								$dataraw = '0xa9059cbb000000000000000000000000'.$addr2.$amounthex;//拼接data
								$constadd = '0x09a2fe80c940a39eee7b69e2b89af129cf5006bd';//合约地址
								$sendrs = $EthClient->eth_sendTransactionraw($dj_username,$constadd,$dj_password,$dataraw);
								//转出账户,合约地址,转出账户解锁密码,data值

							} else {//其他币20170922
								$sendrs = $CoinClient->sendtoaddress($addr, floatval($mum));
							}

							if ($sendrs) {
								$res = $mo->table('tw_myzc')->where(array('id'=>$aid))->save(array('txid'=>$sendrs));
								$mo->execute('commit');
								$mo->execute('unlock tables');
							} else {
								throw new \Think\Exception(L('转出失败!1'));
							}
						} else {
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
				
				if (!$auto_status) {
					$flag = 1;
				} else if ($auto_status && $sendrs) {
					$flag = 1;
					if ($coin=='eth' or $coin=='tatc') {//以太坊20170922
						if (!$sendrs) {
							$flag = 0;
						}
					} else {
						$arr = json_decode($sendrs, true);
						if (isset($arr['status']) && ($arr['status'] == 0)) {
							$flag = 0;
						}
					}

				} else {
					$flag = 0;
				}

				if (!$flag) {
					$this->error(L('钱包服务器转出币种失败,请手动转出'));
				} else {
					$this->success(L('转出成功!'));
				}
			}
		}
	}

	//虚拟币会员转出///////////////////////////////////////////////////////

	//虚拟币会员转出申请
	public function myzc_user($coin = NULL,$jf_type =NULL)
	{
		// 过滤非法字符----------------S
		if (checkstr($coin)) {
			$this->error(L('您输入的信息有误！'));
		}
		// 过滤非法字符----------------E


		if (!userid()) {
			redirect('/Login/index.html');
		}

		$coin_info = M('Coin')->where(array('name' => $coin))->find();
		if (!$coin_info) {
			$this->error(L('币种不存在'));
		}

		$this->assign('coin_info', $coin_info);

		if ($jf_type == 'jf_zr') {
			//$where['username'] = session('userName');
			$where['userid'] = userid();
			$where['coinname'] = $coin;
			$where['from_user'] = '1';
			$Mobile = M('Myzr');
			$count = $Mobile->where($where)->count();
			$Page = new \Think\Page($count, 10);
			$show = $Page->show();
			$list = $Mobile->where($where)->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
			// foreach ($list as $k => $v) {
			// 	// $users_n = M('User')->where(array('id' => $v['userid']))->getField('username');
			// 	// $list[$k]['username'] = $users_n;
			// }
			foreach ($list as $key => $value) {
				$list[$key]['num']=sprintf("%.4f", $value['num']);
				$list[$key]['mum']=sprintf("%.4f", $value['mum']);
				$list[$key]['fee']=sprintf("%.4f", $value['fee']);
			}
		} else {
			$where['userid'] = userid();
			$where['coinname'] = $coin;
			$where['to_user'] = '1';
			$Mobile = M('Myzc');
			$count = $Mobile->where($where)->count();
			$Page = new \Think\Page($count, 10);
			$show = $Page->show();
			$list = $Mobile->where($where)->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
			foreach ($list as $key => $value) {
				$list[$key]['num']=sprintf("%.4f", $value['num']);
				$list[$key]['mum']=sprintf("%.4f", $value['mum']);
				$list[$key]['fee']=sprintf("%.4f", $value['fee']);
			}

		}

		$this->assign('list', $list);
		$this->assign('page', $show);
		$this->display();
	}

	public function mywt($market = NULL, $type = NULL, $status = NULL)
	{
		// 过滤非法字符----------------S
		if (checkstr($market) || checkstr($type) || checkstr($status)) {
			$this->error(L('您输入的信息有误！'));
		}
		// 过滤非法字符----------------E

		if (!userid()) {
			redirect('/Login/index.html');
		}

		// 获取币种信息
		$coin_info = M('market')->where(array('name' => $market))->find();
		if(!$coin_info){
			$this->error(L('币种不存在'));
		}
		$this->assign('coin_info', $coin_info);

		if (($type == 1) || ($type == 2)) {
			$where['type'] = $type;
		}
		if (($status == 1) || ($status == 2) || ($status == 3)) {
			$where['status'] = $status - 1;
		}

		$this->assign('market', $market);
		$this->assign('type', $type);
		$this->assign('status', $status);

		// 筛选条件
		$where['userid'] = userid();
		$where['market'] = $market;

		$Mobile = M('Trade');
		$count = $Mobile->db(1,'DB_Read')->where($where)->count();
		$Page = new \Think\Page1($count, 15);
		$show = $Page->show();

		$list = $Mobile->db(1,'DB_Read')->where($where)->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
		foreach ($list as $k => $v) {
			$list[$k]['num'] = $v['num'] * 1;
			$list[$k]['price'] = $v['price'] * 1;
			$list[$k]['deal'] = $v['deal'] * 1;
			if ($v['deal'] <= 0) {
				$list[$k]['demark'] = '未成交';
			} else if ($v['deal'] < $v['num']) {
				$list[$k]['demark'] = '部分成交';
			} else if ($v['deal'] >= $v['num']) {
				$list[$k]['demark'] = '已完成';
			}
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
			redirect('/#login');
		}

		// 获取币种信息
		$coin_info = M('market')->where(array('name' => $market))->find();
		if(!$coin_info){
			$this->error(L('币种不存在'));
		}
		$this->assign('coin_info', $coin_info);

		// $market = $market.'_cny';

		if ($type == 1) {
			$where = 'userid=' . userid() . ' && market=\'' . $market . '\'';
		} else if ($type == 2) {
			$where = 'peerid=' . userid() . ' && market=\'' . $market . '\'';
		} else {
			$where = '((userid=' . userid() . ') || (peerid=' . userid() . ')) && market=\'' . $market . '\'';
		}

		// 按时间筛选条件================================================
		$info = array();
		if (isset($_GET['time1'])) {
			$time1 = $_GET['time1'];
			$info['time1'] = $time1;
		} else {
			$time1 = null;
		}
		if (isset($_GET['time2'])) {
			$time2 = $_GET['time2'];
			$info['time2'] = $time2;
		} else {
			$time2 = null;
		}

		if($time1 && $time2){
			$time1 = strtotime($time1);
			$time2 = strtotime($time2);
			if($time1 < $time2){
				// $where['addtime'] = array(array('egt',$time1),array('elt',$time2));
				$where .= ' && addtime>=' . $time1 . ' && addtime<=\'' . $time2 . '\'';
			}else if($time1 == $time2){
				// $where['addtime'] = array('eq',$time1);
				$where .= ' && addtime=\'' . $time2 . '\'';
			}else if($time1 > $time2){
				// $where['addtime'] = array('egt',$time1);
				$where .= ' && addtime>=\'' . $time1 . '\'';
			}

		}else if($time1 && !$time2){
			$time1 = strtotime($time1);
			// $where['addtime'] = array('egt',$time1);
			$where .= ' && addtime>=\'' . $time1 . '\'';
		}else if(!$time1 && $time2){
			$time2 = strtotime($time2);
			// $where['addtime'] = array('elt',$time2);
			$where .= ' && addtime<=\'' . $time2 . '\'';
		}
		// 按时间筛选条件=====结束===========================================

		$this->assign('market', $market);
		$this->assign('type', $type);
		$this->assign('userid', userid());
		$Mobile = M('TradeLog');
		$count = $Mobile->db(1,'DB_Read')->where($where)->count();
		$Page = new \Think\Page1($count, 15);
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
}
?>