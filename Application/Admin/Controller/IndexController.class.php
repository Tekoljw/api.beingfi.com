<?php
namespace Admin\Controller;

class IndexController extends AdminController
{
	protected function _initialize()
	{
		parent::_initialize();	
		$allow_action=array("index","coin","coinSet","market","marketSet","google","upGoogle","IndexMaxLimitTables");
		if(!in_array(ACTION_NAME,$allow_action)){
			$this->error("页面不存在！");
		}
	}

	public function index()
	{
		$arr = array();
		$arr['reg_sum'] = M('User')->count();
		$arr['cny_num'] = M('UserCoin')->sum('btc') + M('UserCoin')->sum('btcd');
		$arr['trance_mum'] = M('TradeLog')->sum('mum');

		if (100000000 < $arr['trance_mum']) {
			$arr['trance_mum'] = sprintf("%.2f", $arr['trance_mum']/100000000) . '亿';
		}else if (10000 < $arr['trance_mum']) {
			$arr['trance_mum'] = sprintf("%.2f", $arr['trance_mum']/10000) . '万';
		}
		//round($arr['trance_mum'] / 100000000)
		
		$arr['art_sum'] = M('Article')->count();
		//统计交易数据

		//今日开始时间
		$today_start = strtotime(date("Y-m-d 00:00:00"));
		$now_time = time();
		$where = array(
			'addtime' => ['between', [$today_start, $now_time]],
			'userid'  => ['gt', 0],
			'status'  => 1,
		);
		//今日币币交易量
		$today_trade_sum = M('trade')->where($where)->sum('mum');
		$today_trade_sum = $today_trade_sum?$today_trade_sum:0;
		$today_trade_sum = round($today_trade_sum, 4);
		$arr['today_trade_sum'] = $today_trade_sum;
		//今日C2C交易量
		$where['status'] = 3;
		$today_c2c_sum = D('ExchangeOrder')->where($where)->sum('num');
		$today_c2c_sum = $today_c2c_sum?$today_c2c_sum:0;
		$today_c2c_sum = round($today_c2c_sum, 4);
		$arr['today_c2c_sum'] = $today_c2c_sum;
		//今日利润
		$where['status'] = 1;
		$today_trade_profit = M('trade')->where($where)->sum('fee');
		$today_trade_profit = $today_trade_profit?$today_trade_profit:0;
		$where['status'] = 3;
		$today_c2c_profit = D('ExchangeOrder')->where($where)->sum('fee');
		$today_c2c_profit = $today_c2c_profit?$today_c2c_profit:0;
		$arr['today_profit'] = round($today_trade_profit + $today_c2c_profit, 4);
		//总利润
		unset($where['addtime']); //清除时间限制
		$where['status'] = 1;
		$total_trade_profit = M('trade')->where($where)->sum('fee');
		$total_trade_profit = $total_trade_profit?$total_trade_profit:0;
		//历史总费率
		$total_c2c_history_fee = M('exchange_order_history_sum')->sum('fee_sum');
		$total_c2c_history_fee = $total_c2c_history_fee?$total_c2c_history_fee:0;
		//历史总优惠
		$total_c2c_history_scale = M('exchange_order_history_sum')->sum('scale_amount_sum');
		$total_c2c_history_scale = $total_c2c_history_scale?$total_c2c_history_scale:0;
		$total_c2c_profit = $total_c2c_history_fee - $total_c2c_history_scale + $today_c2c_profit;
		$arr['total_profit'] = round($total_trade_profit + $total_c2c_profit, 4);

		$data = array();
		$time = mktime(0, 0, 0, date('m'), date('d'), date('Y')) - (29 * 24 * 60 * 60);
		$i = 0;

		for (; $i < 30; $i++) {
			$a = $time;
			$time = $time + (60 * 60 * 24);
			$date = addtime($time - (60 * 60), 'Y-m-d');
			$mycz = M('Myzr')->where(array(
				'status'  => array('neq', 0),
				'addtime' => array(
					array('gt', $a),
					array('lt', $time)
					)
				))->sum('num');
			$mytx = M('Myzc')->where(array(
				'status'  => 1,
				'addtime' => array(
					array('gt', $a),
					array('lt', $time)
					)
				))->sum('num');

			if ($mycz || $mytx) {
				$data['cztx'][] = array('date' => $date, 'charge' => $mycz, 'withdraw' => $mytx);
			}
		}

		$time = time() - (30 * 24 * 60 * 60);
		$i = 0;

		for (; $i < 60; $i++) {
			$a = $time;
			$time = $time + (60 * 60 * 24);
			$date = addtime($time, 'Y-m-d');
			$user = M('User')->where(array(
				'addtime' => array(
					array('gt', $a),
					array('lt', $time)
					)
				))->count();

			if ($user) {
				$data['reg'][] = array('date' => $date, 'sum' => $user);
			}
		}

		$this->assign('cztx', json_encode($data['cztx']));
		$this->assign('reg', json_encode($data['reg']));
		$this->assign('arr', $arr);

		$this->display();
	}

	public function coin($coinname = NULL)
	{
		if (!$coinname) {
			$coinname = C('xnb_mr');
		}

		if (empty($coinname)) {
			echo '请去设置--其他设置里面设置默认币种';
			exit();
		}

		if (!M('Coin')->where(array('name' => $coinname))->find()) {
			echo '币种不存在,请去设置里面添加币种，并清理缓存';
			exit();
		}

		$this->assign('coinname', $coinname);
		$data = array();
		$data['trance_b'] = M('UserCoin')->sum($coinname);
		$data['trance_s'] = M('UserCoin')->sum($coinname . 'd');
		$data['trance_num'] = $data['trance_b'] + $data['trance_s'];
		$data['trance_song'] = M('Myzr')->where(array('coinname' => $coinname))->sum('fee');
		$data['trance_fee'] = M('Myzc')->where(array('coinname' => $coinname))->sum('fee');

		if (C('coin')[$coinname]['type'] == 'qbb') {
			$dj_username = C('coin')[$coinname]['dj_yh'];
			$dj_password = C('coin')[$coinname]['dj_mm'];
			$dj_address = C('coin')[$coinname]['dj_zj'];
			$dj_port = C('coin')[$coinname]['dj_dk'];
			if($coinname=='eth'|| $coinname=='eos'|| $coinname=='etc'){
				$CoinClient = EthCommon($dj_address,$dj_port);
				if(!$CoinClient){
					$this->error('钱包对接失败！');
				}
				$numb = $CoinClient->eth_getBalance($dj_username,"latest");//获取主账号余额
				$data['trance_mum'] =  (hexdec($numb))/1000000000000000000;//转换成ether单位显示;
			}else{
				$CoinClient = CoinClient($dj_username, $dj_password, $dj_address, $dj_port, 5, array(), 1);
				$json = $CoinClient->getinfo();

				if (!isset($json['version']) || !$json['version']) {
					$this->error('钱包链接失败！');
				}

				$data['trance_mum'] = $json['balance'];
			}

		}
		else {
			$data['trance_mum'] = 0;
		}

		$this->assign('data', $data);
		$market_json = M('CoinJson')->where(array('name' => $coinname))->order('id desc')->find();

		if ($market_json) {
			$addtime = $market_json['addtime'] + 60;
		}
		else {
			$addtime = M('Myzr')->where(array('name' => $coinname))->order('id asc')->find()['addtime'];
		}

		if (!$addtime) {
			$addtime = time();
		}

		$t = $addtime;
		$start = mktime(0, 0, 0, date('m', $t), date('d', $t), date('Y', $t));
		$end = mktime(23, 59, 59, date('m', $t), date('d', $t), date('Y', $t));

		if ($addtime) {
			$trade_num = M('UserCoin')->where(array(
				'addtime' => array(
					array('egt', $start),
					array('elt', $end)
					)
				))->sum($coinname);
			$trade_mum = M('UserCoin')->where(array(
				'addtime' => array(
					array('egt', $start),
					array('elt', $end)
					)
				))->sum($coinname . 'd');
			$aa = $trade_num + $trade_mum;

			if (C($coinname)['type'] == 'qbb') {
				$bb = $json['balance'];
			}
			else {
				$bb = 0;
			}

			$trade_fee_buy = M('Myzr')->where(array(
				'name'    => $coinname,
				'addtime' => array(
					array('egt', $start),
					array('elt', $end)
					)
				))->sum('fee');
			$trade_fee_sell = M('Myzc')->where(array(
				'name'    => $coinname,
				'addtime' => array(
					array('egt', $start),
					array('elt', $end)
					)
				))->sum('fee');
			$d = array($aa, $bb, $trade_fee_buy, $trade_fee_sell);

			if (M('CoinJson')->where(array('name' => $coinname, 'addtime' => $end))->find()) {
				M('CoinJson')->where(array('name' => $coinname, 'addtime' => $end))->save(array('data' => json_encode($d)));
			}
			else {
				M('CoinJson')->add(array('name' => $coinname, 'data' => json_encode($d), 'addtime' => $end));
			}
		}

		$tradeJson = M('CoinJson')->where(array('name' => $coinname))->order('id asc')->limit(100)->select();

		foreach ($tradeJson as $k => $v) {
			if ((addtime($v['addtime']) != '---') && (14634049 < $v['addtime'])) {
				$date = addtime($v['addtime'], 'Y-m-d H:i:s');
				$json_data = json_decode($v['data'], true);
				$cztx[] = array('date' => $date, 'num' => $json_data[0], 'mum' => $json_data[1], 'fee_buy' => $json_data[2], 'fee_sell' => $json_data[3]);
			}
		}

		$this->assign('cztx', json_encode($cztx));
		$this->display();
	}

	public function coinSet($coinname = NULL)
	{
		if (!$coinname) {
			$this->error('参数错误！');
		}

		if (M('CoinJson')->where(array('name' => $coinname))->delete()) {
			$this->success('操作成功！');
		}
		else {
			$this->error('操作失败！');
		}
	}

	public function market($market = NULL)
	{
		if (!$market) {
			$market = C('market_mr');
		}

		if (!$market) {
			echo '请去设置--其他设置里面设置默认市场';
			exit();
		}

		$market = trim($market);
		$xnb = explode('_', $market)[0];
		$rmb = explode('_', $market)[1];
		$this->assign('xnb', $xnb);
		$this->assign('rmb', $rmb);
		$this->assign('market', $market);
		$data = array();
		$data['trance_num'] = M('TradeLog')->where(array('market' => $market))->sum('num');
		$data['trance_buyfee'] = M('TradeLog')->where(array('market' => $market))->sum('fee_buy');
		$data['trance_sellfee'] = M('TradeLog')->where(array('market' => $market))->sum('fee_sell');
		$data['trance_fee'] = $data['trance_buyfee'] + $data['trance_sellfee'];
		$data['trance_mum'] = M('TradeLog')->where(array('market' => $market))->sum('mum');
		$data['trance_ci'] = M('TradeLog')->where(array('market' => $market))->count();
		$market_json = M('MarketJson')->where(array('name' => $market))->order('id desc')->find();

		if ($market_json) {
			$addtime = $market_json['addtime'] + 60;
		}
		else {
			$addtime = M('TradeLog')->where(array('market' => $market))->order('addtime asc')->find()['addtime'];
		}

		if (!$addtime) {
			$addtime = time();
		}

		if ($addtime) {
			if ($addtime < (time() + (60 * 60 * 24))) {
				$t = $addtime;
				$start = mktime(0, 0, 0, date('m', $t), date('d', $t), date('Y', $t));
				$end = mktime(23, 59, 59, date('m', $t), date('d', $t), date('Y', $t));
				$trade_num = M('TradeLog')->where(array(
					'market'  => $market,
					'addtime' => array(
						array('egt', $start),
						array('elt', $end)
						)
					))->sum('num');

				if ($trade_num) {
					$trade_mum = M('TradeLog')->where(array(
						'market'  => $market,
						'addtime' => array(
							array('egt', $start),
							array('elt', $end)
							)
						))->sum('mum');
					$trade_fee_buy = M('TradeLog')->where(array(
						'market'  => $market,
						'addtime' => array(
							array('egt', $start),
							array('elt', $end)
							)
						))->sum('fee_buy');
					$trade_fee_sell = M('TradeLog')->where(array(
						'market'  => $market,
						'addtime' => array(
							array('egt', $start),
							array('elt', $end)
							)
						))->sum('fee_sell');
					$d = array($trade_num, $trade_mum, $trade_fee_buy, $trade_fee_sell);

					if (M('MarketJson')->where(array('name' => $market, 'addtime' => $end))->find()) {
						M('MarketJson')->where(array('name' => $market, 'addtime' => $end))->save(array('data' => json_encode($d)));
					}
					else {
						M('MarketJson')->add(array('name' => $market, 'data' => json_encode($d), 'addtime' => $end));
					}
				}
				else {
					$d = null;

					if (M('MarketJson')->where(array('name' => $market, 'data' => ''))->find()) {
						M('MarketJson')->where(array('name' => $market, 'data' => ''))->save(array('addtime' => $end));
					}
					else {
						M('MarketJson')->add(array('name' => $market, 'data' => '', 'addtime' => $end));
					}
				}
			}
		}

		$tradeJson = M('MarketJson')->where(array('name' => $market))->order('id asc')->limit(100)->select();

		foreach ($tradeJson as $k => $v) {
			if ((addtime($v['addtime']) != '---') && (14634049 < $v['addtime'])) {
				$date = addtime($v['addtime'] - (60 * 60 * 24), 'Y-m-d H:i:s');
				$json_data = json_decode($v['data'], true);
				$cztx[] = array('date' => $date, 'num' => $json_data[0], 'mum' => $json_data[1], 'fee_buy' => $json_data[2], 'fee_sell' => $json_data[3]);
			}
		}

		$this->assign('cztx', json_encode($cztx));
		$this->assign('data', $data);
		$this->display();
	}

	public function marketSet($market = NULL)
	{
		if (!$market) {
			$this->error('参数错误！');
		}

		if (M('MarketJson')->where(array('name' => $market))->delete()) {
			$this->success('操作成功！');
		}
		else {
			$this->error('操作失败！');
		}
	}


	//goole验证器绑定和解绑页面
	public function google(){

		$admin_id = session('admin_id');
		if($admin_id){
			
			$admin = M('Admin')->where(['id'=>$admin_id])->find();
			$is_ga = $admin['google_key'];
			if (!$is_ga) {
				$ga = new \Common\Ext\GoogleAuthenticator();
				$secret = $ga->createSecret();
				session('secret', $secret);
				$this->assign('Asecret', $secret);
				$this->assign('ga_transfer', 0);
				$qrCodeUrl = $ga->getQRCodeGoogleUrl(C('google_prefix') . '-' . $admin['username'], $secret);
				$this->assign('qrCodeUrl', $qrCodeUrl);
			}else{

				$this->assign('ga_transfer', 1);
			}
			$this->display();

		}else{
			$this->error(L('管理员请先登陆后台!'));
		}
	}

	// 更新谷歌验证码
	public function upGoogle($ga_verify, $type)
	{
		// 过滤非法字符----------------S
		if (checkstr($ga_verify) || checkstr($type)) {
			$this->error(L('您输入的信息有误！'));
		}
		// 过滤非法字符----------------E

		$admin_id = session('admin_id');

		if (!$admin_id) {
			$this->error(L('登录已经失效,请重新登录!'));
		}
		
		$admin = M('Admin')->where(array('id'=>$admin_id))->find();

		if (!$ga_verify) {
			$this->error(L('谷歌验证码错误！'));
		}
		
		if ($type == 'add') {
			$secret = session('secret');

			if (!$secret) {
				$this->error(L('验证码已经失效,请刷新网页!'));
			}
		} else if (($type == 'updat') || ($type == 'delet')) {	

			if (!$admin['google_key']) {
				$this->error(L('还未设置谷歌验证码!'));
			}

			$secret = $admin['google_key'];
			$delete = ($type == 'delet' ? 1 : 0);
		} else {
			$this->error(L('操作未定义'));
		}

		$ga = new \Common\Ext\GoogleAuthenticator();
		if ($ga->verifyCode($secret, $ga_verify, 1)) {
			$ga_val = ($delete == '' ? $secret:'');
			M('Admin')->where(['id' => $admin_id])->save(array('google_key' => $ga_val));
			$this->success(L('操作成功'));
		} else {
			$this->error(L('验证失败'));
		}
	}

	//获取索引ID超过限制的表名
    private function getIndexMaxLimitTables(){

      //需要检查的表
      $checkTables = array(
        ['table'=>'app_log','name'=>'app日志表'],
        ['table'=>'eth_log','name'=>'以太坊货币日志表'],
        ['table'=>'bazaar_log','name'=>'集市交易日志表'],
        ['table'=>'fenhong_log','name'=>'分红日志表'],
        ['table'=>'finance_log','name'=>'币币兑换日志表'],
        ['table'=>'issue_log','name'=>'认购日志表'],
        ['table'=>'money_dlog','name'=>'理财日志表d'],
        ['table'=>'money_log','name'=>'理财日志表'],
        ['table'=>'operation_log','name'=>'操作日志表'],
        ['table'=>'issue_log','name'=>'认购日志表'],
        ['table'=>'trade_log','name'=>'币币交易日志表'],
        ['table'=>'user_log','name'=>'用户日志表'],
      );
      //已经超过限制的表
      $limitTables = array();
      //最大提示索引数
      $maxLimitIndex = 15*10000*10000;//15亿
      foreach ($checkTables as $key => $value) {
        $maxId = M($value['table'])->order('id desc')->getField('id');
        if($maxId > $maxLimitIndex){
          $limitTables[$key] = ['id'=>$maxId, 'tablename'=>$value['name']];
        }
      }
      return $limitTables;
    }

    //展示索引ID超过限制的表
    public function IndexMaxLimitTables(){

      $list = $this->getIndexMaxLimitTables();
      //$list[1] = ['id'=>100, 'tablename'=>'红标'];
      $count = count($list);
      $size  = 30;
      $page = new \Think\Page($count, $size);
      $this->assign("list", $list);
      $this->assign('page', $page->show());
      $this->display();
    }
}

?>