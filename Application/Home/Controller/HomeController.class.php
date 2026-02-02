<?php
namespace Home\Controller;

class HomeController extends \Think\Controller
{
	protected function _initialize()
	{
		//不需要检测的控制器
		$exclude_controller = array('AutoExchange', 'AutoRefreshData');

		if(in_array(CONTROLLER_NAME,$exclude_controller)){
			return;
		}else{

			//可以访问，但是需要检测的控制器
			$allow_controller=array("Index","Ajax","Api","Article","Finance","Login","Queue","Trade","Backstage","Exchange","Pay","User","Chart","Ptpbc","News","Reward","Financing","Issue","Vote");
			
			if(!in_array(CONTROLLER_NAME,$allow_controller)){
				$this->error("非法操作");
			}
			
			defined('APP_DEMO') || define('APP_DEMO', 0);
			
			// 链接审查（检查是否需要登录，检查是否开放访问）
			$data_url = (APP_DEBUG ? null : S('closeUrl'));
			if (!$data_url) {
				//$closeUrl = M('daohang')->where('status=1')->field('url')->select();
				$closeUrl = M('daohang')->where(array('url'=>$_SERVER['REQUEST_URI'], 'status'=>1))->find();
				S('closeUrl', $closeUrl);	 

				if (S('closeUrl')['get_login'] == 1) {
					$this->error(L('需要登录后浏览!'), U('Login/index'));exit;
				}
				if (S('closeUrl')['access'] == 1) {
					$this->error(L('禁止访问！'), U('/'));exit;
				}
			}

			if (!session('userId')) {
				session('userId', 0);
			} else if (CONTROLLER_NAME != 'Login' ) {
	/*			$user = D('user')->where('id = ' . session('userId'))->find();
				if (!$user['paypassword']) {
					//未设置交易密码
					redirect('/Login/register1');
				}*/
			}
		}
	}

	public function __construct()
	{
		parent::__construct();

		if (isset($_GET['invit'])) {
			session('invit', $_GET['invit']);
		}

		$config = (APP_DEBUG ? null : S('home_config'));
		if (!$config) {
			$config = M('Config')->where(array('id' => 1))->find();
			S('home_config', $config);
		}
		
		// 检查是否关闭站点
		if (!session('web_close')) {
			if (!$config['web_close']) {
				$conf = array();
				
				if(LANG_SET == "zh-cn"){
					$conf['langs_close_cause'] = $config['web_close_cause'];
				} else {
					$conf['langs_close_cause'] = $config['web_close_cause_en'];
				}
				
				$this->assign('conf', $conf);
				//展示未开放网页
				$this->display('Index/maintain');
				exit;
			}
		}
		//客服联系QQ
		$this->assign('service_qq', $config['contact_service_qq']);
		//加载配置
		C($config);
/*		C('contact_qq', explode('|', C('contact_qq')));
		C('contact_qqun', explode('|', C('contact_qqun')));
		C('contact_bank', explode('|', C('contact_bank')));*/
		
		$coin = (APP_DEBUG ? null : S('home_coin'));
		if (!$coin) {
			$coin = M('Coin')->where(array('status' => 1))->select();
			S('home_coin', $coin);
		}

		$coinList = array();
		foreach ($coin as $k => $v) {
			$coinList['coin'][$v['name']] = $v;
			if ($v['name'] != Anchor_CNY) {
				$coinList['coin_list'][$v['name']] = $v;
			}
			if ($v['type'] == 'rmb') {
				$coinList['rmb_list'][$v['name']] = $v;
			} else {
				$coinList['xnb_list'][$v['name']] = $v;
			}
			if ($v['type'] == 'rgb') {
				$coinList['rgb_list'][$v['name']] = $v;
			}
			if ($v['type'] == 'qbb') {
				$coinList['qbb_list'][$v['name']] = $v;
			}
		}
		//加载币列表
		C($coinList);

		$market = (APP_DEBUG ? null : S('home_market'));
		if (!$market) {
			$market = M('Market')->where(array('status' => 1))->order('sort asc')->select();
			S('home_market', $market);
		}
		foreach ($market as $k => $v) {
			$v['new_price'] = round($v['new_price'], $v['round']);
			$v['buy_price'] = round($v['buy_price'], $v['round']);
			$v['sell_price'] = round($v['sell_price'], $v['round']);
			$v['min_price'] = round($v['min_price'], $v['round']);
			$v['max_price'] = round($v['max_price'], $v['round']);
			$v['xnb'] = explode('_', $v['name'])[0];
			$v['rmb'] = explode('_', $v['name'])[1];
			$v['xnbimg'] = C('coin')[$v['xnb']]['img'];
			$v['rmbimg'] = C('coin')[$v['rmb']]['img'];
			$v['volume'] = $v['volume'] * 1;
			$v['change'] = $v['change'] * 1;
			$v['title'] = C('coin')[$v['xnb']]['title'] . '(' . strtoupper($v['xnb']) . '/' . strtoupper($v['rmb']) . ')';

			$v['title_n'] = C('coin')[$v['xnb']]['title'];
			$v['title_ns'] = '(' . strtoupper($v['xnb']) . '/' . strtoupper($v['rmb']) . ')';
			$v['title_nsm'] = strtoupper($v['xnb']);

			$marketList['market'][$v['name']] = $v;
		}
		//加载市场列表
		C($marketList);
		
		$C = C();
		foreach ($C as $k => $v) {
			$C[strtolower($k)] = $v;
		}
		$this->assign('C', $C);
		
		// 顶部导航--------------------S
		if (!S('daohang_'.LANG_SET)) {
			$this->daohang = M('Daohang')->where(array('status' => 1,'lang'=>LANG_SET))->order('sort asc')->select();
			S('daohang_'.LANG_SET, $this->daohang);
			$this->assign('daohang', $this->daohang);
		} else {
			$this->daohang = S('daohang_'.LANG_SET);
			$this->assign('daohang', $this->daohang);
		}
		// 顶部导航--------------------E
		
		// 页脚导航--------------------S
		if (!S('footer_'.LANG_SET)) {
			$this->footer = M('footer')->where(array('status' => 1,'lang'=>LANG_SET))->order('sort asc')->select();
			S('footer_'.LANG_SET, $this->footer);
		} else {
			$this->footer = S('footer_'.LANG_SET);
		}
		// 页脚导航--------------------E
		
		$footerArticleType = (APP_DEBUG ? null : S('footer_indexArticleType'));
		if (!$footerArticleType || true) {
			$footerArticleType = M('ArticleType')->where(array('status' => 1, 'footer' => 1, 'shang' => array('like','help_%'),'lang'=>LANG_SET))->order('sort asc ,id desc')->limit(5)->select();
			S('footer_indexArticleType', $footerArticleType);
		}

		$this->assign('footerArticleType', $footerArticleType);
		$footerArticle = (APP_DEBUG ? null : S('footer_indexArticle'));
		if (!$footerArticle) {
			foreach ($footerArticleType as $k => $v) {
				 $second_class = M('ArticleType')->where(array('footer' => 1, 'status' => 1,'lang'=>LANG_SET))->order('id asc')->select();
				 if (!empty($second_class)) {
					 foreach ($second_class as $val){
						 $article_list = M('Article')->where(array('footer'=>1,'index'=>1,'status'=>1))->limit(5)->select();
						 if (!empty($article_list)) {
							 foreach ($article_list as $kk=>$vv) {
								 $footerArticle[$v['name']][] = $vv;
							 }
						 }
					 }
				 } else {
					 $article_list = M('Article')->where(array('footer'=>1,'index'=>1,'status'=>1,'type'=>$v['name']))->limit(5)->select();
					 if (!empty($article_list)) {
						 foreach ($article_list as $kk=>$vv) {
							 $footerArticle[$v['name']][] = $vv;
						 }
					 }
				 }
			}
			S('footer_indexArticle', $footerArticle);
		}
		$this->assign('footerArticle', $footerArticle);
		
		// 底部友情链接--------------------S
		$footerindexLink = (APP_DEBUG ? null : S('index_indexLink'));
		if (!$footerindexLink) {
			$footerindexLink = M('Link')->where(array('status' => 1,'look_type'=>1))->order('sort asc ,id desc')->select();
		}
		$this->assign('footerindexLink', $footerindexLink);
		// 底部友情链接--------------------E

		// 官方公告 ----------------------S
		$news_list1 = M('Article')->where(array('status'=>1))->order('sort,endtime desc')->limit(3)->select();
		$this->assign('notice_list', $news_list1);
		// 官方公告 ----------------------n
		
		// 交易币种列表--------------------S
		$data = array();
		foreach (C('market') as $k => $v) {
			$v['xnb'] = explode('_', $v['name'])[0];
			$v['rmb'] = explode('_', $v['name'])[1];
			$data[$k]['name'] = $v['name'];
			$data[$k]['img'] = $v['xnbimg'];
			$data[$k]['title'] = $v['title'];
		}
		$this->assign('market_ss', $data);
		// 交易币种列表--------------------E

		//注册协议
		//$this->assign('registerAgreement',((LANG_SET=='zh-cn')?'/Article/detail/id/54.html':'/Article/detail/id/150.html'));
		$this->assign('registerAgreement','/Support/index/articles/cid/7/id/18.html');
		
		// 踢出内容中的标签
		//$notice_info['content'] = strip_tags($notice_info['content']);
	}

	//添加资金变动记录
	protected function add_finance_log($model, $user_info, $old_user_coin, $new_user_coin, $plusminus, $amount, $optype, $position, $Coins ){

		if($model){
			$coin = $Coins['name'];
			$data = array(
				'username' => $user_info['username'], 
				'adminname' => $user_info['username'], 
				'addtime' => time(), 
				'plusminus' => $plusminus, 
				'amount' => $amount, 
				'optype' => $optype, 
				'position' => $position, 
				'cointype' => $Coins['id'], 
				'old_amount' => $old_user_coin[$coin], 
				'new_amount' => $new_user_coin[$coin], 
				'userid' => $user_info['id'], 
				'adminid' => userid(),
				'addip'=>get_client_ip()
			);
			return $model->table('tw_finance_log')->add($data);
		}
		return false;
	}

	//添加转入订单
    protected function add_my_zr($model, $peer_coin, $user_coin, $coin, $num, $mum, $fee, $status = 1, $from_user = 0){
    	if($model){
    		//币地址
    		$coin_addr = $coin . 'b';

    		$data = array(
    			'userid' => $peer_coin['userid'], 
    			'username' => $user_coin[$coin_addr], 
    			'coinname' => $coin, 
    			'txid' => md5($peer_coin[$coin_addr] . $user_coin[$coin_addr] . time()),
    			'num' => $num, 
    			'fee' => $fee, 
    			'mum' => $mum, 
    			'addtime' => time(), 
    			'status' => $status
    		);
    		if($from_user){
    			$data['from_user'] = $from_user;
    		}
        	return $model->table('tw_myzr')->add($data );
    	}
    	return false;
    }

	//添加转出订单
    protected function add_my_zc($model, $to_addr, $user_coin, $coin, $num, $mum, $fee, $status = 1, $to_user = 0){
    	if($model){
    		//币地址
    		$coin_addr = $coin . 'b';

    		$data = array(
    			'userid' => $user_coin['userid'], 
    			'username' => $to_addr, 
    			'coinname' => $coin, 
    			'txid' => md5($to_addr . $user_coin[$coin_addr] . time()), 
    			'num' => $num, 
    			'fee' => $fee, 
    			'mum' => $mum, 
    			'addtime' => time(),
    			'status' => $status
    		);
    		if($to_user){
    			$data['to_user'] = $to_user;
    		}
        	return $model->table('tw_myzc')->add($data );
    	}
    	return false;
    }

	//添加转出手续费
    protected function add_my_zc_fee($model, $to_addr, $fee_user, $user_coin, $coin, $num, $mum, $fee, $type = 1){
    	if($model){
    		//币地址
    		$coin_addr = $coin . 'b';

    		$data = array(
    			'userid' => $fee_user['userid'], 
    			'username' => $user_coin[$coin_addr], 
    			'coinname' => $coin, 
    			'txid' => md5($to_addr . $user_coin[$coin_addr] . time()), 
    			'num' => $num, 
    			'fee' => $fee, 
    			'type' => $type, 
    			'mum' => $mum, 
    			'addtime' => time(), 
    			'status' => 1
    		);
        	return $model->table('tw_myzc_fee')->add($data );
    	}
    	return false;
    }
}
?>