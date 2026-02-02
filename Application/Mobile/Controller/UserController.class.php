<?php
namespace Mobile\Controller;

use Think\Log;

class UserController extends MobileController
{
	protected function _initialize()
	{
		parent::_initialize();
		$allow_action=array("index","login","nameauth","nameauth2","password","uppassword","paypassword","uppaypassword","ga","moble","upmoble","tpwdset","tpwdsetting","uptpwdsetting","bankadd","bank","upbank","delbank","qianbao","qianbaoadd","qianbao_coin_list","upqianbao","delqianbao","goods","upgoods","delgoods","log","safety","apiManager", "payTypeIntroduce");
		if(!in_array(ACTION_NAME,$allow_action)){
			$this->error("非法操作！");
		}
	}

	/* 币种列表页 */
	public function qianbao_coin_list()
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


	public function qianbaoadd($coin = NULL)
	{
		// 过滤非法字符----------------S
		if (checkstr($coin)) {
			$this->error('您输入的信息有误！');
		}
		// 过滤非法字符----------------E

		if (!userid()) {
			redirect("/Login/index");
		}

		$Coin = M('Coin')->where(array(
			'status' => 1,
			'name'   => array('neq', 'cny')
		))->select();

		if (!$coin) {
			$coin = $Coin[0]['name'];
		}
		$this->assign('xnb', $coin);

		foreach ($Coin as $k => $v) {
			$coin_list[$v['name']] = $v;
		}
		$this->assign('coin', $coin);
		$this->assign('coin_list', $coin_list);
		$userQianbaoList = M('UserQianbao')->where(array('userid' => userid(), 'status' => 1, 'coinname' => $coin))->order('id desc')->select();
		$this->assign('userQianbaoList', $userQianbaoList);
		$this->display();
	}

	public function index()
	{
		if (!userid()) {
			redirect('/Login/index.html');
		}

		// 处理总财产--------------S
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
				if ($Market[$v['name'] . '_cny']['new_price']) {
					$jia = $Market[$v['name'] . '_cny']['new_price'];
				} else {
					$jia = 1;
				}

				$coinList[$v['name']] = array('name' => $v['name'], 'img' => $v['img'], 'title' => $v['title'] . '(' . strtoupper($v['name']) . ')', 'xnb' => round($UserCoin[$v['name']], 6) * 1, 'xnbd' => round($UserCoin[$v['name'] . 'd'], 6) * 1, 'xnbz' => round($UserCoin[$v['name']] + $UserCoin[$v['name'] . 'd'], 6), 'jia' => $jia * 1, 'zhehe' => round(($UserCoin[$v['name']] + $UserCoin[$v['name'] . 'd']) * $jia, 2));
				$cny['zj'] = round($cny['zj'] + (($UserCoin[$v['name']] + $UserCoin[$v['name'] . 'd']) * $jia), 2) * 1;
			}
		}

		//计算资金为艺库币
		$cny['zj'] = $UserCoin['acc']+$UserCoin['accd'];
		// 处理总财产--------------E

		$cny['dj'] = sprintf("%.4f", $cny['dj']);
		$cny['ky'] = sprintf("%.4f", $cny['ky']);
		$cny['zj'] = sprintf("%.4f", $cny['zj']);
		$cny['dj'] = number_format($cny['dj'],2);//千分位显示
		$cny['ky'] = number_format($cny['ky'],2);//千分位显示
		$cny['zj'] = number_format($cny['zj'],2);//千分位显示

		$this->assign('cny', $cny);
		$user = M('User')->where(array('id' => userid()))->find();
		$this->assign('user', $user);
		$this->display();
	}
	
	public function safety()
	{
		$user = M('User')->where(array('id' => userid()))->find();
		$this->assign('user', $user);
		$this->display();
	}

	public function login()
	{
		$link= M('Link')->where(array('status' => 1))->select();
		$this->assign('link', $link);
		$this->display();
	}

	//实名认证
	public function nameauth()
	{
		if (!userid()) {
			redirect("/Login/index.html");
		}

		$user = M('User')->where(array('id' => userid()))->find();
		if ($user['idcard']) {
			$user['idcard'] = substr_replace($user['idcard'], '********', 6, 8);
		}

		$this->assign('user', $user);
		$this->display();
	}

	//机构认证
	public function nameauth2()
	{
		if (!userid()) {
			redirect("/Login/index.html");
		}

		$user = M('User')->where(array('id' => userid()))->find();
		$user_kyc = M('user_kyc')->where(['userid'=>userid()])->find();

		//隐藏法人身份证完整信息
		if ($user_kyc['organization_id']) {
			$user_kyc['organization_id'] = substr_replace($user['organization_id'], '********', 6, 8);
		}

		//隐藏法人身份证完整信息
		if ($user_kyc['organization_legalperson_id']) {
			$user_kyc['organization_legalperson_id'] = substr_replace($user['organization_legalperson_id'], '********', 6, 8);
		}

		$this->assign('user', $user);
		$this->assign('user_kyc', $user_kyc);
		$this->display();
	}

	public function password()
	{
		if (!userid()) {
			redirect("/Login/index.html");
		}

		$mobile = M('User')->where(array('id' => userid()))->getField('mobile');
		if ($mobile) {
			$mobile = substr_replace($mobile, '****', 3, 4);
		} else {
			$this->error('请先认证手机！');
		}
		
		$this->assign('mobile', $mobile);
		$this->display();
	}

	public function uppassword($mobile_verify, $oldpassword, $newpassword, $repassword)
	{
		// 过滤非法字符----------------S
		if (checkstr($oldpassword) || checkstr($newpassword) || checkstr($repassword) || checkstr($mobile_verify)) {
			$this->error('您输入的信息有误！');
		}
		// 过滤非法字符----------------E

		if (!userid()) {
			$this->error('请先登录！');
		}

		if (!check($mobile_verify, 'd')) {
			$this->error('短信验证码格式错误！');
		}
		if ($mobile_verify != session('pass_verify')) {
			$this->error('短信验证码错误！');
		}
		if (!check($oldpassword, 'password')) {
			$this->error('密码格式为6~16位，不含特殊符号！');
		}
		if (strlen($newpassword) > 16 || strlen($newpassword) < 6) {
			$this->error('密码格式为6~16位，不含特殊符号！');
		}
		if (!check($newpassword, 'password')) {
			$this->error('密码格式为6~16位，不含特殊符号！');
		}
		if ($newpassword != $repassword) {
			$this->error('两次输入的密码不一致！');
		}

		$password = M('User')->where(array('id' => userid()))->getField('password');
		$paypasswords = M('User')->where(array('id' => userid()))->getField('paypassword');

		if (md5($oldpassword) != $password) {
			$this->error('旧登录密码错误！');
		}
		if (md5($newpassword) == $paypasswords) {
			$this->error('登录密码不能和交易密码相同！');
		}
		if (md5($newpassword) == $password) {
			$this->error('新登录密码跟原密码相同，修改失败！');
		}

		$rs = M('User')->where(array('id' => userid()))->save(array('password' => md5($newpassword)));
		if ($rs) {
			$this->success('修改成功');
		} else {
			$this->error('修改失败');
		}
	}

	public function paypassword()
	{
		if (!userid()) {
			redirect("/Login/index.html");
		}

		$mobile = M('User')->where(array('id' => userid()))->getField('mobile');
		if ($mobile) {
			$mobile = substr_replace($mobile, '****', 3, 4);
		} else {
			$this->error('请先认证手机！');
		}
		
		$this->assign('mobile', $mobile);
		$this->display();
	}

	public function uppaypassword($mobile_verify, $oldpaypassword, $newpaypassword, $repaypassword)
	{
		// 过滤非法字符----------------S
		if (checkstr($mobile_verify) || checkstr($oldpaypassword) || checkstr($newpaypassword) || checkstr($repaypassword)) {
			$this->error('您输入的信息有误！');
		}
		// 过滤非法字符----------------E

		if (!userid()) {
			$this->error('请先登录！');
		}

		if (!check($mobile_verify, 'd')) {
			$this->error('短信验证码格式错误！');
		}
		if ($mobile_verify != session('paypass_verify')) {
			$this->error('短信验证码错误！');
		}
/*		if (!check($oldpaypassword, 'password')) {
			$this->error('旧交易密码格式错误！');
		}
		if (!check($newpaypassword, 'password')) {
			$this->error('新交易密码格式错误！');
		}*/
		if (!check($oldpaypassword, 'password')) {
			$this->error('密码格式为6~16位，不含特殊符号！');
		}
		if (strlen($newpaypassword) > 16 || strlen($newpaypassword) < 6) {
			$this->error('密码格式为6~16位，不含特殊符号！');
		}
		if (!check($newpaypassword, 'password')) {
			$this->error('密码格式为6~16位，不含特殊符号！');
		}
		if ($newpaypassword != $repaypassword) {
			$this->error('两次输入的密码不一致！');
		}

		$user = M('User')->where(array('id' => userid()))->find();
		if (md5($oldpaypassword) != $user['paypassword']) {
			$this->error('旧交易密码错误！');
		}
		if (md5($newpaypassword) == $user['paypassword']) {
			$this->error('新交易密码跟原交易密码相同，修改失败！');
		}
		if (md5($newpaypassword) == $user['password']) {
			$this->error('交易密码不能和登录密码相同！');
		}

		$rs = M('User')->where(array('id' => userid()))->save(array('paypassword' => md5($newpaypassword)));
		if ($rs) {
			$this->success('修改成功');
		} else {
			$this->error('修改失败');
		}
	}

	public function ga()
	{
		if (empty($_POST)) {
			if (!userid()) {
				redirect("/Login/index.html");
			}

			$user = M('User')->where(array('id' => userid()))->find();
			$is_ga = ($user['ga'] ? 1 : 0);
			$this->assign('is_ga', $is_ga);

			if (!$is_ga) {
				$ga = new \Common\Ext\GoogleAuthenticator();
				$secret = $ga->createSecret();
				session('secret', $secret);
				$this->assign('Asecret', $secret);
				$zhanghu=C('google_prefix') . '-' . $user['username'];
				$this->assign('zhanghu', $zhanghu);
				$qrCodeUrl = $ga->getQRCodeGoogleUrl($zhanghu, $secret);
				$this->assign('qrCodeUrl', $qrCodeUrl);
				$this->display();
			} else {
				$arr = explode('|', $user['ga']);
				$this->assign('ga_login', $arr[1]);
				$this->assign('ga_transfer', $arr[2]);
				$this->display();
			}
		} else {
			if (!userid()) {
				$this->error('登录已经失效,请重新登录!');
			}

			foreach ($_POST as $k => $v) {
				// 过滤非法字符----------------S
				if (checkstr($v)) {
					$this->error('您输入的信息有误！');
				}
				// 过滤非法字符----------------E
			}

			$delete = '';
			$gacode = trim(I('ga'));
			$type = trim(I('type'));
			$ga_login = (I('ga_login') == false ? 0 : 1);
			$ga_transfer = (I('ga_transfer') == false ? 0 : 1);

			if (!$gacode) {
				$this->error('请输入验证码!');
			}

			if ($type == 'add') {
				$secret = session('secret');

				if (!$secret) {
					$this->error('验证码已经失效,请刷新网页!');
				}
			} else if (($type == 'updat') || ($type == 'delet')) {
				$user = M('User')->where('id = ' . userid())->find();

				if (!$user['ga']) {
					$this->error('还未设置谷歌验证码!');
				}

				$arr = explode('|', $user['ga']);
				$secret = $arr[0];
				$delete = ($type == 'delet' ? 1 : 0);
			} else {
				$this->error('操作未定义');
			}

			$ga = new \Common\Ext\GoogleAuthenticator();
			if ($ga->verifyCode($secret, $gacode, 1)) {
				$ga_val = ($delete == '' ? $secret . '|' . $ga_login . '|' . $ga_transfer : '');
				M('User')->save(array('id' => userid(), 'ga' => $ga_val));
				$this->success('操作成功');
			} else {
				$this->error('验证失败');
			}
		}
	}

	public function moble()
	{
		if (!userid()) {
			redirect("/Login/index.html");
		}

		$user = M('User')->where(array('id' => userid()))->find();
		if ($user['mobile']) {
			$user['mobile'] = substr_replace($user['mobile'], '****', 3, 4);
		}

		$this->assign('user', $user);
		$this->display();
	}

	public function upmoble($mobile, $mobile_verify)
	{
		// 过滤非法字符----------------S
		if (checkstr($mobile) || checkstr($mobile_verify)) {
			$this->error('您输入的信息有误！');
		}
		// 过滤非法字符----------------E

		if (!userid()) {
			$this->error('您没有登录请先登录！');
		}

		if (!check($mobile, 'mobile')) {
			$this->error('手机号码格式错误！');
		}
		if (!check($mobile_verify, 'd')) {
			$this->error('短信验证码格式错误！');
		}
		if ($mobile_verify != session('mobilebd_verify')) {
			$this->error('短信验证码错误！');
		}
		if (M('User')->where(array('mobile' => $mobile))->find()) {
			$this->error('手机号码已存在！');
		}

		$rs = M('User')->where(array('id' => userid()))->save(array('mobile' => $mobile, 'mobiletime' => time()));
		if ($rs) {
			$this->success('手机认证成功！');
		} else {
			$this->error('手机认证失败！');
		}
	}

	public function tpwdset()
	{
		if (!userid()) {
			redirect("/Login/index.html");
		}

		$user = M('User')->where(array('id' => userid()))->find();
		$this->assign('user', $user);
		$this->display();
	}

	public function tpwdsetting()
	{
		if (userid()) {
			$tpwdsetting = M('User')->where(array('id' => userid()))->getField('tpwdsetting');
			exit($tpwdsetting);
		}
	}

	public function uptpwdsetting($paypassword, $tpwdsetting)
	{
		// 过滤非法字符----------------S
		if (checkstr($paypassword) || checkstr($tpwdsetting)) {
			$this->error('您输入的信息有误！');
		}
		// 过滤非法字符----------------E

		if (!userid()) {
			$this->error('请先登录！');
		}

		if (!check($paypassword, 'password')) {
			$this->error('密码格式为6~16位，不含特殊符号！');
		}

		if (($tpwdsetting != 1) && ($tpwdsetting != 2) && ($tpwdsetting != 3)) {
			$this->error('选项错误！' . $tpwdsetting);
		}

		$user_paypassword = M('User')->where(array('id' => userid()))->getField('paypassword');
		if (md5($paypassword) != $user_paypassword) {
			if(!empty($user_paypassword)){
				$this->error(L('交易密码错误！'));
			}else{
				$this->error(L('还未设置交易密码，请前往账户中心设置！'));
			}
		}

		$rs = M('User')->where(array('id' => userid()))->save(array('tpwdsetting' => $tpwdsetting));
		if ($rs) {
			$this->success('成功！');
		} else {
			$this->error('失败！');
		}
	}

	public function bank()
	{
		if (!userid()) {
			redirect("/Login/index.html");
		}

		$UserBankType = M('UserBankType')->where(array('status' => 1))->order('id desc')->select();
		$this->assign('UserBankType', $UserBankType);
		$truename = M('User')->where(array('id' => userid()))->getField('truename');
		$this->assign('truename', $truename);
		$UserBank = M('UserBank')->where(array('userid' => userid(), 'status' => 1))->order('id desc')->select();
		$this->assign('UserBank', $UserBank);
		$this->display();
	}

	public function bankadd()
	{
		if (!userid()) {
			redirect('/Login/index');
		}

		$UserBankType = M('UserBankType')->where(array('status' => 1))->order('id desc')->select();
		$this->assign('UserBankType', $UserBankType);
		$truename = M('User')->where(array('id' => userid()))->getField('truename');
		$this->assign('truename', $truename);
		$UserBank = M('UserBank')->where(array('userid' => userid(), 'status' => 1))->order('id desc')->select();
		$this->assign('UserBank', $UserBank);
		$this->display();
	}

	public function upbank($name, $bank, $bankprov, $bankcity, $bankaddr, $bankcard, $paypassword)
	{
		// 过滤非法字符----------------S
		if (checkstr($name) || checkstr($bank) || checkstr($bankprov) || checkstr($bankcity) || checkstr($bankaddr) || checkstr($bankcard) || checkstr($paypassword)) {
			$this->error('您输入的信息有误！');
		}
		// 过滤非法字符----------------E

		if (!userid()) {
			redirect("/Login/index.html");
		}

		if (!check($name, 'a')) {
			$this->error('备注名称格式错误！');
		}
		if (!check($bank, 'a')) {
			$this->error('开户银行格式错误！');
		}
		if (!check($bankprov, 'c')) {
			$this->error('开户省市格式错误！');
		}
		if (!check($bankcity, 'c')) {
			$this->error('开户省市格式错误2！');
		}
		if (!check($bankaddr, 'a')) {
			$this->error('开户行地址格式错误！');
		}
		//if (!check($bankcard, 'd')) {
		//	$this->error('银行账号格式错误！');
		//}

		if (!check($paypassword, 'password')) {
			$this->error('密码格式为6~16位，不含特殊符号！');
		}

		$user_paypassword = M('User')->where(array('id' => userid()))->getField('paypassword');

		if (md5($paypassword) != $user_paypassword) {
			if(!empty($user_paypassword)){
				$this->error(L('交易密码错误！'));
			}else{
				$this->error(L('还未设置交易密码，请前往账户中心设置！'));
			}
		}

		if (!M('UserBankType')->where(array('title' => $bank))->find()) {
			$this->error('开户银行错误！');
		}

		$userBank = M('UserBank')->where(array('userid' => userid()))->select();
		foreach ($userBank as $k => $v) {
			if ($v['name'] == $name) {
				$this->error('请不要使用相同的备注名称！');
			}

			if ($v['bankcard'] == $bankcard) {
				$this->error('银行卡号已存在！');
			}
		}

		if (10 <= count($userBank)) {
			$this->error('每个用户最多只能添加10个地址！');
		}

		if (M('UserBank')->add(array('userid' => userid(), 'name' => $name, 'bank' => $bank, 'bankprov' => $bankprov, 'bankcity' => $bankcity, 'bankaddr' => $bankaddr, 'bankcard' => $bankcard, 'addtime' => time(), 'status' => 1))) {
			$this->success('银行添加成功！');
		} else {
			$this->error('银行添加失败！');
		}
	}

	public function delbank($id, $paypassword)
	{
		// 过滤非法字符----------------S
		if (checkstr($id) || checkstr($paypassword)) {
			$this->error('您输入的信息有误！');
		}
		// 过滤非法字符----------------E
		
		if (!userid()) {
			redirect("/Login/index.html");
		}

		if (!check($paypassword, 'password')) {
			$this->error('密码格式为6~16位，不含特殊符号！');
		}
		if (!check($id, 'd')) {
			$this->error('参数错误！');
		}

		$user_paypassword = M('User')->where(array('id' => userid()))->getField('paypassword');

		if (md5($paypassword) != $user_paypassword) {
			if(!empty($user_paypassword)){
				$this->error(L('交易密码错误！'));
			}else{
				$this->error(L('还未设置交易密码，请前往账户中心设置！'));
			}
		}

		if (!M('UserBank')->where(array('userid' => userid(), 'id' => $id))->find()) {
			$this->error('非法访问！');
		} else if (M('UserBank')->where(array('userid' => userid(), 'id' => $id))->delete()) {
			$this->success('删除成功！');
		} else {
			$this->error('删除失败！');
		}
	}

	public function qianbao($coin = NULL)
	{
		// 过滤非法字符----------------S
		if (checkstr($coin)) {
			$this->error('您输入的信息有误！');
		}
		// 过滤非法字符----------------E
		
		if (!userid()) {
			redirect("/Login/index.html");
		}

		$Coin = M('Coin')->where(array(
			'status' => 1,
			'name'   => array('neq', 'cny')
		))->select();

		if (!$coin) {
			$coin = $Coin[0]['name'];
		}

		$this->assign('xnb', $coin);
		foreach ($Coin as $k => $v) {
			$coin_list[$v['name']] = $v;
		}

		$this->assign('coin_list', $coin_list);
		$userQianbaoList = M('UserQianbao')->where(array('userid' => userid(), 'status' => 1, 'coinname' => $coin))->order('id desc')->select();
		$this->assign('userQianbaoList', $userQianbaoList);
		$this->display();
	}

	public function upqianbao($coin, $name, $addr, $paypassword)
	{
		// 过滤非法字符----------------S
		if (checkstr($coin) || checkstr($name)) {
			$this->error('您输入的信息有误！');
		}
		// 过滤非法字符----------------E

		if (!userid()) {
			redirect("/Login/index.html");
		}

		if (!check($name, 'a')) {
			$this->error('备注名称格式错误！');
		}
		if (!check($addr, 'dw')) {
			$this->error('钱包地址格式错误！');
		}
		if (!check($paypassword, 'password')) {
			$this->error('密码格式为6~16位，不含特殊符号！');
		}

		$user_paypassword = M('User')->where(array('id' => userid()))->getField('paypassword');
		if (md5($paypassword) != $user_paypassword) {
			$this->error('交易密码错误！');
		}
		if (!M('Coin')->where(array('name' => $coin))->find()) {
			$this->error('品种错误！');
		}

		$userQianbao = M('UserQianbao')->where(array('userid' => userid(), 'coinname' => $coin))->select();
		foreach ($userQianbao as $k => $v) {
			if ($v['name'] == $name) {
				$this->error('请不要使用相同的钱包标识！');
			}
			if ($v['addr'] == $addr) {
				$this->error('钱包地址已存在！');
			}
		}

		if (1 <= count($userQianbao)) {
			$this->error('每个人最多只能添加1个地址！');
		}

		if (M('UserQianbao')->add(array('userid' => userid(), 'name' => $name, 'addr' => $addr, 'coinname' => $coin, 'addtime' => time(), 'status' => 1))) {
			$this->success('添加成功！');
		} else {
			$this->error('添加失败！');
		}
	}

	public function delqianbao($id, $paypassword)
	{
		// 过滤非法字符----------------S
		if (checkstr($id) || checkstr($paypassword)) {
			$this->error('您输入的信息有误！');
		}
		// 过滤非法字符----------------E

		if (!userid()) {
			redirect("/Login/index.html");
		}

		if (!check($paypassword, 'password')) {
			$this->error('密码格式为6~16位，不含特殊符号！');
		}
		if (!check($id, 'd')) {
			$this->error('参数错误！');
		}

		$user_paypassword = M('User')->where(array('id' => userid()))->getField('paypassword');
		if (md5($paypassword) != $user_paypassword) {
			if(!empty($user_paypassword)){
				$this->error(L('交易密码错误！'));
			}else{
				$this->error(L('还未设置交易密码，请前往账户中心设置！'));
			}
		}

		if (!M('UserQianbao')->where(array('userid' => userid(), 'id' => $id))->find()) {
			$this->error('非法访问！');
		} else if (M('UserQianbao')->where(array('userid' => userid(), 'id' => $id))->delete()) {
			$this->success('删除成功！');
		} else {
			$this->error('删除失败！');
		}
	}

	public function goods()
	{
		if (!userid()) {
			redirect("/Login/index.html");
		}

		$userGoodsList = M('UserGoods')->where(array('userid' => userid(), 'status' => 1))->order('id desc')->select();
		foreach ($userGoodsList as $k => $v) {
			$userGoodsList[$k]['mobile'] = substr_replace($v['mobile'], '****', 3, 4);
			$userGoodsList[$k]['idcard'] = substr_replace($v['idcard'], '********', 6, 8);
		}

		$this->assign('userGoodsList', $userGoodsList);
		$this->display();
	}

	public function upgoods($name, $truename, $idcard, $mobile, $addr, $paypassword)
	{
		// 过滤非法字符----------------S
		if (checkstr($name) || checkstr($truename) || checkstr($idcard) || checkstr($mobile) || checkstr($paypassword)) {
			$this->error('您输入的信息有误！');
		}
		// 过滤非法字符----------------E

		if (!userid()) {
			redirect("/Login/index.html");
		}

		if (!check($name, 'a')) {
			$this->error('备注名称格式错误！');
		}
		if (!check($truename, 'truename')) {
			$this->error('联系姓名格式错误！');
		}
		if (!check($idcard, 'idcard')) {
			$this->error('身份证号格式错误！');
		}
		if (!check($mobile, 'mobile')) {
			$this->error('联系电话格式错误！');
		}
		if (!check($addr, 'a')) {
			$this->error('联系地址格式错误！');
		}

		$user_paypassword = M('User')->where(array('id' => userid()))->getField('paypassword');
		if (md5($paypassword) != $user_paypassword) {
			if(!empty($user_paypassword)){
				$this->error(L('交易密码错误！'));
			}else{
				$this->error(L('还未设置交易密码，请前往账户中心设置！'));
			}
		}

		$userGoods = M('UserGoods')->where(array('userid' => userid()))->select();
		foreach ($userGoods as $k => $v) {
			if ($v['name'] == $name) {
				$this->error('请不要使用相同的地址标识！');
			}
		}

		if (10 <= count($userGoods)) {
			$this->error('每个人最多只能添加10个地址！');
		}

		if (M('UserGoods')->add(array('userid' => userid(), 'name' => $name, 'addr' => $addr, 'idcard' => $idcard, 'truename' => $truename, 'mobile' => $mobile, 'addtime' => time(), 'status' => 1))) {
			$this->success('添加成功！');
		}
		else {
			$this->error('添加失败！');
		}
	}

	public function delgoods($id, $paypassword)
	{
		// 过滤非法字符----------------S
		if (checkstr($id) || checkstr($paypassword)) {
			$this->error('您输入的信息有误！');
		}
		// 过滤非法字符----------------E

		if (!userid()) {
			redirect("/Login/index.html");
		}

		if (!check($paypassword, 'password')) {
			$this->error('密码格式为6~16位，不含特殊符号！');
		}

		if (!check($id, 'd')) {
			$this->error('参数错误！');
		}

		$user_paypassword = M('User')->where(array('id' => userid()))->getField('paypassword');

		if (md5($paypassword) != $user_paypassword) {
			if(!empty($user_paypassword)){
				$this->error(L('交易密码错误！'));
			}else{
				$this->error(L('还未设置交易密码，请前往账户中心设置！'));
			}
		}

		if (!M('UserGoods')->where(array('userid' => userid(), 'id' => $id))->find()) {
			$this->error('非法访问！');
		} else if (M('UserGoods')->where(array('userid' => userid(), 'id' => $id))->delete()) {
			$this->success('删除成功！');
		} else {
			$this->error('删除失败！');
		}
	}

	public function log()
	{
		if (!userid()) {
			redirect("/Login/index.html");
		}

		$where['status'] = array('egt', 0);
		$where['userid'] = userid();
		$Model = M('UserLog');
		$count = $Model->db(1,'DB_Read')->where($where)->count();
		$Page = new \Think\Page($count, 10);
		$show = $Page->show();
		$list = $Model->db(1,'DB_Read')->where($where)->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
		$this->assign('list', $list);
		$this->assign('page', $show);
		$this->display();
	}

	//api的管理页面
	public function apiManager(){
		if (!userid()) {
			redirect('/Login/index');
		}

		$userinfo = M('User')->where(['id'=>userid()])->find();
		$this->assign('userinfo', $userinfo);
		$this->assign('gateway', 'http://'.MSCODE);
		$this->assign('onlineDocumentUrl', 'http://103.101.207.2:8085/Support/index/articles/cid/11/id/47.html');
		$this->assign('downloadDocumentUrl', 'http://103.101.207.2:8085/Support/index/articles/cid/11/id/47.html');
		$this->display();
	}

	//支付方式的参数说明
	public function payTypeIntroduce(){
		if (!userid()) {
			redirect('/Login/index');
		}

		$where = array();
		$where['status'] = 1;

		$list = M('paytype_config')->where($where)->order('id asc')->limit($Page->firstRow . ',' . $Page->listRows)->select();

		$count = M('paytype_config')->where($where)->count();
		$Page = new \Think\Page($count, 10);
		$show = $Page->show();
		$this->assign('list', $list);
		$this->assign('page', $show);
		$this->display();
	}
}

?>