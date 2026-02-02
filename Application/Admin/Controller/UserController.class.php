<?php
namespace Admin\Controller;

class UserController extends AdminController
{
	protected function _initialize()
	{
		parent::_initialize();	
		$allow_action=array("index","edit","edit2","status","admin","adminEdit","adminStatus","auth","authEdit","authStatus","authStart","authAccess","updateRules","authAccessUp","authUser","authUserAdd","authUserRemove","log","logEdit","logStatus","qianbao","qianbaoEdit","qianbaoStatus","bank","bankEdit","bankStatus","coin","coinEdit","coinFreeze","coinLog","goods","goodsEdit","goodsStatus","setpwd","userExcel","loginadmin","payType","payTypeEdit","payTypeControl","payTypeStatus","payParams","payParamsEdit","payParamsControl","payParamsStatus","payParamsCheck","editOrganizationKyc","exchangeApiKey","openChannelId","saveOpenChannelId");
		if(!in_array(ACTION_NAME,$allow_action)){
			$this->error("页面不存在！");
		}
	}

	public function index($name=NULL, $field=NULL, $status=NULL, $idstate=NULL)
	{
		$where = array();
		if ($field && $name) {
			$where[$field] = $name;
		}
		if ($status) {
			$where['status'] = $status - 1;
		}
		/* 状态--条件 */
		if ($idstate) {
			$where['idstate'] = $idstate - 1;
		}
		
		// 统计
		$tongji['dsh'] = M('User')->where(array('idstate'=>1))->count();
		$this->assign('tongji', $tongji);
		
		$count = M('User')->where($where)->count();
		$Page = new \Think\Page($count, 15);
		$show = $Page->show();
		
		if ($idstate == 2) {
			$list = M('User')->where($where)->order('kyc_lv,id asc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
		} else {
			$list = M('User')->where($where)->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
		}
		
		foreach ($list as $k => $v) {
			//第几代
			$list[$k]['invit_1'] = M('User')->where(array('id' => $v['invit_1']))->getField('username');
			$list[$k]['invit_2'] = M('User')->where(array('id' => $v['invit_2']))->getField('username');
			$list[$k]['invit_3'] = M('User')->where(array('id' => $v['invit_3']))->getField('username');
			$user_login_state=M('user_log')->where(array('userid'=>$v['id'],'type' => 'login'))->order('id desc')->find();
			$list[$k]['state']	=$user_login_state['state'];
			//机构认证
			$organization_info = M('user_kyc')->where(array('userid' => $v['id']))->find();
			$list[$k]['organization_status']= $organization_info['organization_status'];
			$list[$k]['organization_name'] 	= $organization_info['organization_name'];
			$list[$k]['organization_id'] 	= $organization_info['organization_id'];
			$list[$k]['legalperson_name'] 	= $organization_info['organization_legalperson'];
			$list[$k]['legalperson_id'] 	= $organization_info['organization_legalperson_id'];
			$list[$k]['legalperson_status'] = $organization_info['organization_legalperson_status'];
		}

		$this->assign('list', $list);
		$this->assign('page', $show);
		$this->display();
	}

	//个人实名初级认证
	public function edit($id = NULL)
	{
		if (empty($_POST)) {
			if (empty($id)) {
				$this->data = array('is_generalize'=>1);
			} else {
				$this->data = M('User')->where(array('id' => trim($id)))->find();
			}

            $areas = M('area')->select();
            $this->assign('areas',$areas);
			$this->display();
		} else {
			if (APP_DEMO) {
				$this->error('测试站暂时不能修改！');
			}

			if ($_POST['password']) {
				$_POST['password'] = md5($_POST['password']);
			} else {
				unset($_POST['password']);
			}
			if ($_POST['paypassword']) {
				$_POST['paypassword'] = md5($_POST['paypassword']);
			} else {
				unset($_POST['paypassword']);
			}
			
			$_POST['mobiletime'] = strtotime($_POST['mobiletime']);

			$cancal_c2c_level = isset($_POST['cancal_c2c_level']) ? $_POST['cancal_c2c_level'] : 0;
			if(!is_numeric($cancal_c2c_level)){
				$this->error('自动取消时间等级选择错误！');
			}

			$all_money = isset($_POST['all_money']) ? $_POST['all_money'] : 0;
			if(!is_numeric($all_money)){
				$this->error('可交易总额需为整数！');
			}

			if($cancal_c2c_level < 0 && $cancal_c2c_level > 2){
				$this->error('自动取消时间选择的信任等级错误！');
			}

			$result = M('User')->where(array('username'=>$_POST['username']))->find();

			if (empty($result)) {
				$_POST['addtime'] = time();
				$mo = M();
				$mo->execute('set autocommit=0');
				$mo->execute('lock tables tw_user write , tw_user_coin write ');
				$rs = array();
				$rs[] = $mo->table('tw_user')->add($_POST);
				$rs[] = $mo->table('tw_user_coin')->add(array('userid' => $rs[0]));

				operation_log(UID, 1, "用户个人实名初级认证添加");
				if(check_arr($rs)){
					$mo->execute('commit');
					$mo->execute('unlock tables');
					$this->success('编辑成功！',U('User/index'));
				} else {
					$this->error('编辑失败！');
				}
			} else {
				operation_log(UID, 1, "用户个人实名初级认证编辑");
				if (M('User')->save($_POST)) {
					$this->success('编辑成功！',U('User/index'));
				} else {
					$this->error('编辑失败！');
				}
			}
		}
	}
	
	//个人实名高级认证
	public function edit2($id = NULL)
	{
		if (empty($_POST)) {
			if (empty($id)) {
				$this->data = array('is_generalize'=>1);
			} else {
				$this->data = M('User')->where(array('id' => trim($id)))->find();
			}

            $areas = M('area')->select();
            $this->assign('areas',$areas);
			$this->display();
		} else {
			if (APP_DEMO) {
				$this->error('测试站暂时不能修改！');
			}

			$_POST['mobiletime'] = strtotime($_POST['mobiletime']);
			
			$_POST['endtime'] = time();

			operation_log(UID, 1, "用户个人实名高级认证添加");
			if (M('User')->save($_POST)) {
				$this->success('编辑成功！',U('User/index'));
			} else {
				$this->error('编辑失败！');
			}
		}
	}

	//机构认证
	public function editOrganizationKyc($id = NULL)
	{
		if (empty($_POST)) {
			$userinfo = M('User')->where(array('id' => trim($id)))->field('kyc_lv,idstate')->find();
			if($userinfo['kyc_lv'] == 2 && $userinfo['idstate'] == 2){
				//获取机构认证数据
				$data = M('user_kyc')->where(array('userid' => trim($id)))->find();
				$this->assign('data', $data);
				$this->display();
			}else{
				$this->error('需通过高级认证后才能编辑！');
			}	
		} else {
			if (APP_DEMO) {
				$this->error('测试站暂时不能修改！');
			}

			$_POST['kyc_organization_time'] = time();
			if($_POST['userid']){
				operation_log(UID, 1, "用户机构认证编辑");
				$res = M('user_kyc')->where(['userid'=>$_POST['userid']])->save($_POST);
			}else{
				$this->error('用户还未上传相关信息，不能编辑！');
			}
			if ($res) {
				//设置审核状态为非通过的时候，关闭自动卖出功能
				if($_POST['organization_status'] != 2){
					M('User')->where(array('id' => $_POST['userid']))->setField('auto_c2c_sell_status', 0);
				}
				$this->success('编辑成功！', U('User/index'));
			} else {
				$this->error('编辑失败！');
			}
		}
	}

	public function status($id = NULL, $type = NULL, $mobile = 'User')
	{
		if (APP_DEMO) {
			$this->error('测试站暂时不能修改！');
		}

		if (empty($id)) {
			$this->error('参数错误！');
		}
		if (empty($type)) {
			$this->error('参数错误1！');
		}
		
		if (strpos(',', $id)) {
			$id = implode(',', $id);
		}

		$where['id'] = array('in', $id);
		$where1['userid'] = array('in', $id);
		$mobile_coin = $mobile.'_coin';
		switch (strtolower($type)) {
		case 'forbid': //冻结
			$data = array('status' => 0);
			operation_log(UID, 1, "用户个人实名认证冻结");
			break;

		case 'resume': //初级认证
			$data = array('status' => 1);
			operation_log(UID, 1, "用户个人实名初级认证");
			break;

		case 'repeal': //实名认证
			$data = array('status' => 2, 'endtime' => time());
			operation_log(UID, 1, "用户个人实名认证");
			break;
				
		case 'idauth': //认证通过
			$data = array('idstate' => 2, 'idcardinfo' => '', 'endtime' => time());
			operation_log(UID, 1, "用户个人实名认证通过");
			break;

		case 'notidauth': //认证失败
			$data = array('idstate' => 8, 'endtime' => time());
			operation_log(UID, 1, "用户个人实名认证失败");
			break;

		case 'delete':
			$data = array('status' => -1);
			operation_log(UID, 1, "用户个人实名认证删除");
			break;

		case 'del':
			if (M($mobile)->where($where)->delete()&&M($mobile_coin)->where($where1)->delete()) {
				$this->success('操作成功！');
			} else {
				$this->error('操作失败！');
			}
			break;

		default:
			$this->error('操作失败！');
		}
		
		if ($type == 'idauth') { //认证通过发奖
			// 注册奖励模块
			$datas = M('User')->where($where)->find();
			$configs = M('config')->where(array('id' => 1))->find();
			
			$ids = $datas['id'];
			$invit_1 = $datas['invit_1'];
			$invit_2 = $datas['invit_2'];
			$invit_3 = $datas['invit_3'];
			
			if($datas['idstate'] == 8){}
			else
			{
				if($datas['kyc_lv'] == 2 || $datas['idstate'] == 2){}
				else if($datas['kyc_lv'] == 0 || $datas['kyc_lv'] == 1)
				{
					//注册赠送币
					if ($configs['give_type'] == 1) {

						$mo = M();
						$mo->execute('set autocommit=0');
						$mo->execute('lock tables tw_user write, tw_user_coin write, tw_invit write, tw_finance_log write');

						$rs = array();

						// 数据未处理时的查询（原数据）
						$finance_num_user_coin = $mo->table('tw_user_coin')->where(array('userid' => $ids))->find();
						// 用户账户数据处理
						$coin_name = $configs['xnb_mr_song']; //赠送币种
						$song_num =  $configs['xnb_mr_song_num']; //赠送数量
						$rs[] = $mo->table('tw_user_coin')->where($where1)->setInc($coin_name, $song_num); // 修改金额
						// 数据处理完的查询（新数据）
						$finance_mum_user_coin = $mo->table('tw_user_coin')->where(array('userid' => $ids))->find();

						// optype=1 充值类型 'cointype' => 1人民币类型 'plusminus' => 1增加类型
						$rs[] = $mo->table('tw_finance_log')->add(array('username' => $datas['username'], 'adminname' => session('admin_username'), 'addtime' => time(), 'plusminus' => 1, 'amount' => $song_num, 'description' => '注册赠送', 'optype' => 27, 'cointype' => 3, 'old_amount' => $finance_num_user_coin[$coin_name], 'new_amount' => $finance_mum_user_coin[$coin_name], 'userid' => $datas['id'], 'adminid' => session('admin_id'),'addip'=>get_client_ip()));


						// 赠送邀请人邀请奖励
						if($configs['song_num_1'] > 0 && $invit_1 > 0){
							$coin_num_1 = $configs['song_num_1'];
							$rs[] = $mo->table('tw_invit')->add(array('userid' => $invit_1, 'invit' => $ids, 'name' => '一代注册赠送', 'type' => 2, 'num' => 0, 'mum' => 0, 'fee' => $coin_num_1, 'addtime' => time(), 'status' => 0,'coin'=>strtoupper($coin_name)));
						}
						if($configs['song_num_2'] > 0 && $invit_2 > 0){
							$coin_num_2 = $configs['song_num_2'];
							$rs[] = $mo->table('tw_invit')->add(array('userid' => $invit_2, 'invit' => $ids, 'name' => '二代注册赠送', 'type' => 2, 'num' => 0, 'mum' => 0, 'fee' => $coin_num_2, 'addtime' => time(), 'status' => 0,'coin'=>strtoupper($coin_name)));
						}
						if($configs['song_num_3'] > 0 && $invit_3 > 0){
							$coin_num_3 = $configs['song_num_3'];
							$rs[] = $mo->table('tw_invit')->add(array('userid' => $invit_3, 'invit' => $ids, 'name' => '三代注册赠送', 'type' => 2, 'num' => 0, 'mum' => 0, 'fee' => $coin_num_3, 'addtime' => time(), 'status' => 0,'coin'=>strtoupper($coin_name)));
						}

						$rs[] = $mo->table('tw_user')->where($where)->save($data);

						if (check_arr($rs)) {
							$mo->execute('commit');
							$mo->execute('unlock tables');
							return $this->success('操作成功！');
						} else {
							$mo->execute('rollback');
							return $this->error('操作失败！');
						}
					}
				}
			}
		}

		if (M($mobile)->where($where)->save($data)) {
			$this->success('操作成功！');
		} else {
			$this->error('操作失败！');
		}
	}

	public function admin($name = NULL, $field = NULL, $status = NULL)
	{
		$DbFields = M('Admin')->getDbFields();

		if (!in_array('email', $DbFields)) {
			M()->execute('ALTER TABLE `tw_admin` ADD COLUMN `email` VARCHAR(200)  NOT NULL   COMMENT \'\' AFTER `id`;');
		}

		$where = array();

		if ($field && $name) {
			if ($field == 'username') {
				$where['userid'] = M('User')->where(array('username' => $name))->getField('id');
			} else {
				$where[$field] = $name;
			}
		}

		if ($status) {
			$where['status'] = $status - 1;
		}

		$count = M('Admin')->where($where)->count();
		$Page = new \Think\Page($count, 15);
		$show = $Page->show();
		$list = M('Admin')->where($where)->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
		foreach ($list as $k => $v) {
			$aga = 0;
			$aga = M('AuthGroupAccess')->where(array('uid'=>$v['id']))->find();
			$ag = M('AuthGroup')->where(array('id'=>$aga['group_id']))->find();
			if (!$aga) {
				$list[$k]['quanxianzu'] = '<a href="'.U('User/auth').'">未绑定权限</a>';
			} else {
				$list[$k]['quanxianzu'] = '<span title="'.$ag['description'].'">'.$ag['title'].'</span>';
			}
		}
		
		$this->assign('list', $list);
		$this->assign('page', $show);
		$this->display();
	}

	public function adminEdit()
	{
		if (empty($_POST)) {
			if (empty($_GET['id'])) {
				$this->data = null;
			} else {
				$this->data = M('Admin')->where(array('id' => trim($_GET['id'])))->find();
			}

			$this->display();
		} else {
			if (APP_DEMO) {
				$this->error('测试站暂时不能修改！');
			}

			$input = I('post.');

			if (!check($input['username'], 'username')) {
				//$this->error('用户名格式错误！');
			}
			if ($input['nickname'] && !check($input['nickname'], 'A')) {
				$this->error('昵称格式错误！');
			}
			if ($input['password'] && !check($input['password'], 'password')) {
				$this->error('登录密码格式错误！');
			}
			if ($input['mobile'] && !check($input['mobile'], 'mobile')) {
				$this->error('手机号码格式错误！');
			}
			if ($input['email'] && !check($input['email'], 'email')) {
				$this->error('邮箱格式错误！');
			}

			if ($input['password']) {
				$input['password'] = md5($input['password']);
			} else {
				unset($input['password']);
			}
			
			if ($_POST['id']) {
				operation_log(UID, 1, "后台管理员账户编辑");
				$rs = M('Admin')->save($input);
			} else {
				$_POST['addtime'] = time();
				operation_log(UID, 1, "后台管理员账户添加");
				$rs = M('Admin')->add($input);
			}

			if ($rs) {
				$this->success('编辑成功！');
			} else {
				$this->error('编辑失败！');
			}
		}
	}

	public function adminStatus($id = NULL, $type = NULL, $mobile = 'Admin')
	{
		if (APP_DEMO) {
			$this->error('测试站暂时不能修改！');
		}

		if (empty($id)) {
			$this->error('参数错误！');
		}
		if (empty($type)) {
			$this->error('参数错误1！');
		}

		if (strpos(',', $id)) {
			$id = implode(',', $id);
		}

		$where['id'] = array('in', $id);

		switch (strtolower($type)) {
		case 'forbid':
			$data = array('status' => 0);
			operation_log(UID, 1, "后台管理员账户状态设置禁止");
			break;

		case 'resume':
			$data = array('status' => 1);
			operation_log(UID, 1, "后台管理员账户状态设置开启");
			break;

		case 'repeal':
			$data = array('status' => 2, 'endtime' => time());
			operation_log(UID, 1, "后台管理员账户状态设置废除");
			break;

		case 'delete':
			$data = array('status' => -1);
			operation_log(UID, 1, "后台管理员账户状态设置删除");
			break;

		case 'del':
			operation_log(UID, 1, "后台管理员账户删除");
			if (M($mobile)->where($where)->delete()) {
				$this->success('操作成功！');
			} else {
				$this->error('操作失败！');
			}
			break;

		default:
			$this->error('操作失败！');
		}

		if (M($mobile)->where($where)->save($data)) {
			$this->success('操作成功！');
		} else {
			$this->error('操作失败！');
		}
	}

	public function auth()
	{
		$this->meta_title = '权限管理';
		
		$list = $this->lists('AuthGroup', array('module' => 'admin'), 'id asc');
		$list = int_to_string($list);
		foreach ($list as $k => $v) {
			$count = M('AuthGroupAccess')->where(array('group_id'=>$v['id']))->count();
			if ($count == 0) {
				$list[$k]['count'] = '';
			} else {
				$list[$k]['count'] = $count;
			}
		}
		
		$this->assign('_list', $list);
		$this->assign('_use_tip', true);
		$this->display();
	}

	public function authEdit()
	{
		if (empty($_POST)) {
			if (empty($_GET['id'])) {
				$this->data = null;
			} else {
				$this->data = M('AuthGroup')->where(array('module' => 'admin', 'type' => \Common\Model\AuthGroupModel::TYPE_ADMIN))->find((int) $_GET['id']);
			}

			$this->display();
		} else {
			if (APP_DEMO) {
				$this->error('测试站暂时不能修改！');
			}

			if (isset($_POST['rules'])) {
				sort($_POST['rules']);
				$_POST['rules'] = implode(',', array_unique($_POST['rules']));
			}

			$_POST['module'] = 'admin';
			$_POST['type'] = \Common\Model\AuthGroupModel::TYPE_ADMIN;
			$AuthGroup = D('AuthGroup');
			$data = $AuthGroup->create();

			if ($data) {
				if (empty($data['id'])) {
					operation_log(UID, 1, "后台管理员账户权限编辑");
					$r = $AuthGroup->add();
				} else {
					operation_log(UID, 1, "后台管理员账户权限添加");
					$r = $AuthGroup->save();
				}

				if ($r === false) {
					$this->error('操作失败' . $AuthGroup->getError());
				} else {
					$this->success('操作成功!');
				}
			} else {
				$this->error('操作失败' . $AuthGroup->getError());
			}
		}
	}

	public function authStatus($id = NULL, $type = NULL, $mobile = 'AuthGroup')
	{
		if (APP_DEMO) {
			$this->error('测试站暂时不能修改！');
		}

		if (empty($id)) {
			$this->error('参数错误！');
		}
		if (empty($type)) {
			$this->error('参数错误1！');
		}

		if (strpos(',', $id)) {
			$id = implode(',', $id);
		}

		$where['id'] = array('in', $id);

		switch (strtolower($type)) {
		case 'forbid':
			$data = array('status' => 0);
			operation_log(UID, 1, "后台管理员账户权限状态设置禁止");
			break;

		case 'resume':
			$data = array('status' => 1);
			operation_log(UID, 1, "后台管理员账户权限状态设置开启");
			break;

		case 'repeal':
			$data = array('status' => 2, 'endtime' => time());
			operation_log(UID, 1, "后台管理员账户权限状态设置废除");
			break;

		case 'delete':
			$data = array('status' => -1);
			operation_log(UID, 1, "后台管理员账户权限状态设置删除");
			break;

		case 'del':	
			operation_log(UID, 1, "后台管理员账户权限删除");
			if (M($mobile)->where($where)->delete()) {
				$this->success('操作成功！');
			} else {
				$this->error('操作失败！');
			}

			break;

		default:
			$this->error('操作失败！');
		}

		if (M($mobile)->where($where)->save($data)) {
			$this->success('操作成功！');
		} else {
			$this->error('操作失败！');
		}
	}

	public function authStart()
	{
		if (M('AuthRule')->where(array('status' => 1))->delete()) {
			$this->success('操作成功！');
		} else {
			$this->error('操作失败！');
		}
	}

	public function authAccess()
	{
		$this->updateRules();
		$auth_group = M('AuthGroup')->where(array(
			'status' => array('egt', '0'),
			'module' => 'admin',
			'type'   => \Common\Model\AuthGroupModel::TYPE_ADMIN
		))->getfield('id,id,title,rules');
		
		$node_list = $this->returnNodes();
		$map = array('module' => 'admin', 'type' => \Common\Model\AuthRuleModel::RULE_MAIN, 'status' => 1);
		$main_rules = M('AuthRule')->where($map)->getField('name,id');
		$map = array('module' => 'admin', 'type' => \Common\Model\AuthRuleModel::RULE_URL, 'status' => 1);
		$child_rules = M('AuthRule')->where($map)->getField('name,id');
		$this->assign('main_rules', $main_rules);
		$this->assign('auth_rules', $child_rules);
		$this->assign('node_list', $node_list);
		$this->assign('auth_group', $auth_group);
		$this->assign('this_group', $auth_group[(int) $_GET['group_id']]);
		$this->meta_title = '访问授权';
		$this->display();
	}

	protected function updateRules()
	{
		$nodes = $this->returnNodes(false);
		$AuthRule = M('AuthRule');
		$map = array(
			'module' => 'admin',
			'type'   => array('in', '1,2')
		);
		$rules = $AuthRule->where($map)->order('name')->select();
		$data = array();

		foreach ($nodes as $value) {
			$temp['name'] = $value['url'];
			$temp['title'] = $value['title'];
			$temp['module'] = 'admin';

			if (0 < $value['pid']) {
				$temp['type'] = \Common\Model\AuthRuleModel::RULE_URL;
			} else {
				$temp['type'] = \Common\Model\AuthRuleModel::RULE_MAIN;
			}

			$temp['status'] = 1;
			$data[strtolower($temp['name'] . $temp['module'] . $temp['type'])] = $temp;
		}

		$update = array();
		$ids = array();

		foreach ($rules as $index => $rule) {
			$key = strtolower($rule['name'] . $rule['module'] . $rule['type']);
			if (isset($data[$key])) {
				$data[$key]['id'] = $rule['id'];
				$update[] = $data[$key];
				unset($data[$key]);
				unset($rules[$index]);
				unset($rule['condition']);
				$diff[$rule['id']] = $rule;
			} else if ($rule['status'] == 1) {
				$ids[] = $rule['id'];
			}
		}

		if (count($update)) {
			foreach ($update as $k => $row) {
				if ($row != $diff[$row['id']]) {
					$AuthRule->where(array('id' => $row['id']))->save($row);
				}
			}
		}

		if (count($ids)) {
			$AuthRule->where(array(
				'id' => array('IN', implode(',', $ids))
			))->save(array('status' => -1));
		}

		if (count($data)) {
			$AuthRule->addAll(array_values($data));
		}

		if ($AuthRule->getDbError()) {
			trace('[' . 'Admin\\Controller\\UserController::updateRules' . ']:' . $AuthRule->getDbError());
			return false;
		} else {
			return true;
		}
	}

	public function authAccessUp()
	{
		if (isset($_POST['rules'])) {
			sort($_POST['rules']);
			$_POST['rules'] = implode(',', array_unique($_POST['rules']));
		}

		$_POST['module'] = 'admin';
		$_POST['type'] = \Common\Model\AuthGroupModel::TYPE_ADMIN;
		$AuthGroup = D('AuthGroup');
		$data = $AuthGroup->create();

		if ($data) {
			if (empty($data['id'])) {
				$r = $AuthGroup->add();
			} else {
				$r = $AuthGroup->save();
			}
			if ($r === false) {
				$this->error('操作失败' . $AuthGroup->getError());
			} else {
				$this->success('操作成功!');
			}
		} else {
			$this->error('操作失败' . $AuthGroup->getError());
		}
	}

	public function authUser($group_id)
	{
		if (empty($group_id)) {
			$this->error('参数错误');
		}

		$auth_group = M('AuthGroup')->where(array(
			'status' => array('egt', '0'),
			'module' => 'admin',
			'type'   => \Common\Model\AuthGroupModel::TYPE_ADMIN
		))->getfield('id,id,title,rules');
		$prefix = C('DB_PREFIX');
		$l_table = $prefix . \Common\Model\AuthGroupModel::MEMBER;
		$r_table = $prefix . \Common\Model\AuthGroupModel::AUTH_GROUP_ACCESS;
		$model = M()->table($l_table . ' m')->join($r_table . ' a ON m.id=a.uid');
		$_REQUEST = array();
		$list = $this->lists($model, array(
			'a.group_id' => $group_id,
			'm.status'   => array('egt', 0)
			), 'm.id asc', null, 'm.id,m.username,m.nickname,m.last_login_time,m.last_login_ip,m.status');
		int_to_string($list);
		$this->assign('_list', $list);
		$this->assign('auth_group', $auth_group);
		$this->assign('this_group', $auth_group[(int) $_GET['group_id']]);
		$this->meta_title = '成员授权';
		$this->display();
	}

	public function authUserAdd()
	{
		$uid = I('uid');

		if (empty($uid)) {
			$this->error('请输入后台成员信息');
		}

		if (!check($uid, 'd')) {
			$user = M('Admin')->where(array('username' => $uid))->find();
			if (!$user) {
				$user = M('Admin')->where(array('nickname' => $uid))->find();
			}
			if (!$user) {
				$user = M('Admin')->where(array('mobile' => $uid))->find();
			}
			if (!$user) {
				$this->error('用户不存在(id 用户名 昵称 手机号均可)');
			}
			$uid = $user['id'];
		}

		$gid = I('group_id');

		if ($res = M('AuthGroupAccess')->where(array('uid' => $uid))->find()) {
			if ($res['group_id'] == $gid) {
				$this->error('已经存在,请勿重复添加');
			} else {
				$res = M('AuthGroup')->where(array('id' => $gid))->find();
				if (!$res) {
					$this->error('当前组不存在');
				}
				$this->error('已经存在[' . $res['title'] . ']组,不可重复添加');
			}
		}

		$AuthGroup = D('AuthGroup');

		if (is_numeric($uid)) {
			if (is_administrator($uid)) {
				$this->error('该用户为超级管理员');
			}
			if (!M('Admin')->where(array('id' => $uid))->find()) {
				$this->error('管理员用户不存在');
			}
		}

		if ($gid && !$AuthGroup->checkGroupId($gid)) {
			$this->error($AuthGroup->error);
		}
		if ($AuthGroup->addToGroup($uid, $gid)) {
			$this->success('操作成功');
		} else {
			$this->error($AuthGroup->getError());
		}
	}

	public function authUserRemove()
	{
		$uid = I('uid');
		$gid = I('group_id');

		if ($uid == UID) {
			$this->error('不允许解除自身授权');
		}
		if (empty($uid) || empty($gid)) {
			$this->error('参数有误');
		}

		$AuthGroup = D('AuthGroup');
		if (!$AuthGroup->find($gid)) {
			$this->error('用户组不存在');
		}

		if ($AuthGroup->removeFromGroup($uid, $gid)) {
			$this->success('操作成功');
		} else {
			$this->error('操作失败');
		}
	}

	public function log($name = NULL, $field = NULL, $status = NULL)
	{
		$where = array();
		if ($field && $name) {
			if ($field == 'username') {
				$where['userid'] = M('User')->where(array('username' => $name))->getField('id');
			} else {
				$where[$field] = $name;
			}
		}

		if ($status) {
			$where['status'] = $status - 1;
		}

		$count = M('UserLog')->where($where)->count();
		$Page = new \Think\Page($count, 15);
		$show = $Page->show();
		$list = M('UserLog')->where($where)->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();

		foreach ($list as $k => $v) {
			$list[$k]['username'] = M('User')->where(array('id' => $v['userid']))->getField('username');
		}

		$this->assign('list', $list);
		$this->assign('page', $show);
		$this->display();
	}

	public function logEdit($id = NULL)
	{
		if (empty($_POST)) {
			if (empty($id)) {
				$this->data = null;
			} else {
				$this->data = M('UserLog')->where(array('id' => trim($id)))->find();
			}

			$this->display();
		} else {
			if (APP_DEMO) {
				$this->error('测试站暂时不能修改！');
			}

			$_POST['addtime'] = strtotime($_POST['addtime']);

			if (M('UserLog')->save($_POST)) {
				$this->success('编辑成功！');
			} else {
				$this->error('编辑失败！');
			}
		}
	}

	public function logStatus($id = NULL, $type = NULL, $mobile = 'UserLog')
	{
		if (APP_DEMO) {
			$this->error('测试站暂时不能修改！');
		}

		if (empty($id)) {
			$this->error('参数错误！');
		}
		if (empty($type)) {
			$this->error('参数错误1！');
		}

		if (strpos(',', $id)) {
			$id = implode(',', $id);
		}

		$where['id'] = array('in', $id);

		switch (strtolower($type)) {
		case 'forbid':
			$data = array('status' => 0);
			break;

		case 'resume':
			$data = array('status' => 1);
			break;

		case 'repeal':
			$data = array('status' => 2, 'endtime' => time());
			break;

		case 'delete':
			$data = array('status' => -1);
			break;

		case 'del':
			if (M($mobile)->where($where)->delete()) {
				$this->success('操作成功！');
			}
			else {
				$this->error('操作失败！');
			}

			break;

		default:
			$this->error('操作失败！');
		}

		if (M($mobile)->where($where)->save($data)) {
			$this->success('操作成功！');
		} else {
			$this->error('操作失败！');
		}
	}

	public function qianbao($name = NULL, $field = NULL, $coinname = NULL, $status = NULL)
	{
		$where = array();

		if ($field && $name) {
			if ($field == 'username') {
				$where['userid'] = M('User')->where(array('username' => $name))->getField('id');
			} else {
				$where[$field] = $name;
			}
		}

		if ($status) {
			$where['status'] = $status - 1;
		}
		if ($coinname) {
			$where['coinname'] = trim($coinname);
		}

		$count = M('UserQianbao')->where($where)->count();
		$Page = new \Think\Page($count, 15);
		$show = $Page->show();
		$list = M('UserQianbao')->where($where)->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();

		foreach ($list as $k => $v) {
			$list[$k]['username'] = M('User')->where(array('id' => $v['userid']))->getField('username');
		}

		$this->assign('list', $list);
		$this->assign('page', $show);
		$this->display();
	}

	public function qianbaoEdit($id = NULL)
	{
		if (empty($_POST)) {
			if (empty($id)) {
				$this->data = null;
			} else {
				$this->data = M('UserQianbao')->where(array('id' => trim($id)))->find();
			}

			$this->display();
		} else {
			if (APP_DEMO) {
				$this->error('测试站暂时不能修改！');
			}

			operation_log(UID, 1, "用户钱包地址编辑");
			$_POST['addtime'] = strtotime($_POST['addtime']);

			if (M('UserQianbao')->save($_POST)) {
				$this->success('编辑成功！');
			} else {
				$this->error('编辑失败！');
			}
		}
	}

	public function qianbaoStatus($id = NULL, $type = NULL, $mobile = 'UserQianbao')
	{
		if (APP_DEMO) {
			$this->error('测试站暂时不能修改！');
		}

		if (empty($id)) {
			$this->error('参数错误！');
		}
		if (empty($type)) {
			$this->error('参数错误1！');
		}

		if (strpos(',', $id)) {
			$id = implode(',', $id);
		}

		$where['id'] = array('in', $id);

		switch (strtolower($type)) {
		case 'forbid':
			$data = array('status' => 0);
			operation_log(UID, 1, "用户钱包地址状态设置禁止");
			break;

		case 'resume':
			$data = array('status' => 1);
			operation_log(UID, 1, "用户钱包地址状态设置开启");
			break;

		case 'repeal':
			$data = array('status' => 2, 'endtime' => time());
			operation_log(UID, 1, "用户钱包地址状态设置废除");
			break;

		case 'delete':
			$data = array('status' => -1);
			operation_log(UID, 1, "用户钱包地址状态设置删除");
			break;

		case 'del':
			operation_log(UID, 1, "用户钱包地址删除");
			if (M($mobile)->where($where)->delete()) {
				$this->success('操作成功！');
			} else {
				$this->error('操作失败！');
			}

			break;

		default:
			$this->error('操作失败！');
		}

		if (M($mobile)->where($where)->save($data)) {
			$this->success('操作成功！');
		} else {
			$this->error('操作失败！');
		}
	}

	//提现地址
	public function bank($name = NULL, $field = NULL, $status = NULL)
	{
		$where = array();

		if ($field && $name) {
			if ($field == 'username') {
				$where['userid'] = M('User')->where(array('username' => $name))->getField('id');
			} else {
				$where[$field] = $name;
			}
		}

		if ($status) {
			$where['status'] = $status - 1;
		}

		$count = M('UserBank')->where($where)->count();
		$Page = new \Think\Page($count, 15);
		$show = $Page->show();
		$list = M('UserBank')->where($where)->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();

		foreach ($list as $k => $v) {
			$list[$k]['username'] = M('User')->where(array('id' => $v['userid']))->getField('username');
		}

		$this->assign('list', $list);
		$this->assign('page', $show);
		$this->display();
	}

	//提现地址编辑
	public function bankEdit($id = NULL)
	{
		if (empty($_POST)) {
			if (empty($id)) {
				$this->data = null;
			} else {
				$this->data = M('UserBank')->where(array('id' => trim($id)))->find();
			}

			$this->display();
		} else {
			if (APP_DEMO) {
				$this->error('测试站暂时不能修改！');
			}

			$_POST['addtime'] = strtotime($_POST['addtime']);
			operation_log(UID, 1, "用户银行卡管理编辑");
			if (M('UserBank')->save($_POST)) {
				$this->success('编辑成功！');
			} else {
				$this->error('编辑失败！');
			}
		}
	}

	//提现地址状态设置
	public function bankStatus($id = NULL, $type = NULL, $mobile = 'UserBank')
	{
		if (APP_DEMO) {
			$this->error('测试站暂时不能修改！');
		}

		if (empty($id)) {
			$this->error('参数错误！');
		}
		if (empty($type)) {
			$this->error('参数错误1！');
		}

		if (strpos(',', $id)) {
			$id = implode(',', $id);
		}

		$where['id'] = array('in', $id);

		switch (strtolower($type)) {
		case 'forbid':
			$data = array('status' => 0);
			operation_log(UID, 1, "用户银行卡状态设置禁止");
			break;

		case 'resume':
			$data = array('status' => 1);
			operation_log(UID, 1, "用户银行卡状态设置开启");
			break;

		case 'repeal':
			$data = array('status' => 2, 'endtime' => time());
			operation_log(UID, 1, "用户银行卡状态设置废除");
			break;

		case 'delete':
			$data = array('status' => -1);
			operation_log(UID, 1, "用户银行卡状态设置删除");
			break;

		case 'del':
			operation_log(UID, 1, "用户银行卡删除");
			if (M($mobile)->where($where)->delete()) {
				$this->success('操作成功！');
			} else {
				$this->error('操作失败！');
			}

			break;

		default:
			$this->error('操作失败！');
		}

		if (M($mobile)->where($where)->save($data)) {
			$this->success('操作成功！');
		} else {
			$this->error('操作失败！');
		}
	}

	//支付类型列表
	public function payType($name = NULL, $field = NULL, $status = NULL)
	{
		$where = array();

		if ($status) {
			$where['status'] = $status - 1;
		}

		$count = M('paytype_config')->where($where)->count();
		$Page = new \Think\Page($count, 15);
		$show = $Page->show();
		$list = M('paytype_config')->where($where)->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();

		$this->assign('list', $list);
		$this->assign('page', $show);
		$this->display();
	}

	//账户类型编辑
	public function payTypeEdit($id = NULL)
	{
		if (empty($_POST)) {
			if (empty($id)) {
				$this->data = null; 
			} else {
				$this->data = M('paytype_config')->where(array('id' => trim($id)))->order('id desc')->find();
			}

			$this->assign('PayTypes', C('PAYTYPES'));

			$this->display();
		} else {
			if (APP_DEMO) {
				$this->error('测试站暂时不能修改！');
			}

			$payType = $_POST['paytype'] - 1;
			$_POST['title'] = C('PAYTYPES')[$payType]['name'];

			//费率
			$_POST['auto_buy_rate'] = $_POST['auto_buy_rate'] ? $_POST['auto_buy_rate'] : 0;
	        //优惠
	        $_POST['sale_sell_rate'] = $_POST['sale_sell_rate'] ? $_POST['sale_sell_rate'] : 0;
	        if(!is_numeric($_POST['auto_buy_rate']) || !is_numeric($_POST['sale_sell_rate'])){
	        	 $this->error('填写错误，费率,优惠需为数字！');
	        }
	        if($_POST['auto_buy_rate'] < $_POST['sale_sell_rate']){
	            $this->error('填写错误，自动费率必须大于优惠！');
	        }

			if($_POST['id']){
				operation_log(UID, 1, "用户自动卖出CNC支付类型编辑");

				if ($_POST['addtime']) {
					if (addtime(strtotime($_POST['addtime'])) == '---') {
						$this->error('添加时间格式错误');
					} else {
						$_POST['addtime'] = strtotime($_POST['addtime']);
					}
				} else {
					$_POST['addtime'] = time();
				}
				if (M('paytype_config')->save($_POST)) {
					$this->success('编辑成功！');
				} else {
					$this->error('编辑失败！');
				}
			}else{
				//查询条件
				$where = array('channelid' =>$_POST['channelid']);
				$payType = M('paytype_config')->where($where)->find();
				if($payType){
					$this->error('该账户类型已存在！');
				}
				operation_log(UID, 1, "用户自动卖出CNC支付类型添加");
				if ($_POST['addtime']) {
					if (addtime(strtotime($_POST['addtime'])) == '---') {
						$this->error('添加时间格式错误');
					} else {
						$_POST['addtime'] = strtotime($_POST['addtime']);
					}
				} else {
					$_POST['addtime'] = time();
				}
				if (M('paytype_config')->add($_POST)) {
					$this->success('添加成功！');
				} else {
					$this->error('添加失败！');
				}
			}
		}
	}

	//支付参数风控编辑
	public function payTypeControl($id = NULL)
	{
		if (empty($_POST)) {
			if (empty($id)) {
				$this->error('选择的支付参数id不能为空！');
			} else {
				$this->data = M('paytype_config')->where(array('id' => trim($id)))->find();
			}

			$this->display();
		} else {
			if (APP_DEMO) {
				$this->error('测试站暂时不能修改！');
			}

			// 过滤非法字符----------------S
			foreach ($_POST as $key => $value) {
				
				if (checkstr($value)) {
					$this->error(L('您输入的信息有误！'));
				}
			}
			// 过滤非法字符----------------E

			//时间判断
			if($_POST['end_time'] > 0 && $_POST['start_time'] > $_POST['end_time']){
				$this->error(L('开始时间需要大于结束时间！'));
			}

			//金额判断
			if($_POST['max_money'] > 0 && $_POST['min_money'] > $_POST['max_money']){
				$this->error(L('订单最大金额需要大于最小金额！'));
			}

			$_POST['control_status'] = $_POST['control_status']?$_POST['control_status']:0;
			$_POST['offline_status'] = $_POST['offline_status']?$_POST['offline_status']:0;

			if($_POST['id']){
				operation_log(UID, 1, "支付类型风控编辑编辑id=".$_POST['id']);
				if (M('paytype_config')->save($_POST)) {
					$this->success('编辑成功！');
				} else {
					$this->error('编辑失败！');
				}
			}else{
				$this->error('支付参数不存在！');
			}
		}
	}

	//提现地址状态设置
	public function payTypeStatus($id = NULL, $type = NULL, $Table = 'paytype_config')
	{
		if (APP_DEMO) {
			$this->error('测试站暂时不能修改！');
		}

		if (empty($id)) {
			$this->error('参数错误！');
		}
		if (empty($type)) {
			$this->error('参数错误1！');
		}

		if (strpos(',', $id)) {
			$id = implode(',', $id);
		}

		$where['id'] = array('in', $id);

		switch (strtolower($type)) {
		case 'forbid':
			$data = array('status' => 0);
			operation_log(UID, 1, "用户自动卖出CNC支付类型状态设置禁止");
			break;

		case 'resume':
			$data = array('status' => 1);
			operation_log(UID, 1, "用户自动卖出CNC支付类型状态设置开启");
			break;

		case 'repeal':
			$data = array('status' => 2, 'endtime' => time());
			operation_log(UID, 1, "用户自动卖出CNC支付类型状态设置废除");
			break;

		case 'delete':
			$data = array('status' => -1);
			operation_log(UID, 1, "用户自动卖出CNC支付类型状态设置删除");
			break;

		case 'del':
			operation_log(UID, 1, "用户自动卖出CNC支付类型删除");
			if (M($Table)->where($where)->delete()) {
				$this->success('操作成功！');
			} else {
				$this->error('操作失败！');
			}

			break;

		default:
			$this->error('操作失败！');
		}

		if (M($Table)->where($where)->save($data)) {
			$this->success('操作成功！');
		} else {
			$this->error('操作失败！');
		}
	}

	//支付参数列表
	public function payParams($name = NULL, $field = NULL, $status = NULL)
	{
		$where = array();

		if ($field && $name) {
			if ($field == 'username') {
				$where['userid'] = M('User')->where(array('username' => $name))->getField('id');
			} else {
				$where[$field] = $name;
			}
		}

		if ($status) {
			$where['status'] = $status - 1;
		}

		$count = M('payparams_list')->where($where)->count();
		$Page = new \Think\Page($count, 15);
		$show = $Page->show();
		$list = M('payparams_list')->where($where)->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();

		$this->assign('list', $list);
		$this->assign('page', $show);
		$this->display();
	}

	//支付参数编辑
	public function payParamsEdit($id = NULL)
	{
		if (empty($_POST)) {
			if (empty($id)) {
				$this->data = null;
			} else {
				$this->data = M('payparams_list')->where(array('id' => trim($id)))->find();
			}

			$PayChannels = M('paytype_config')->where(['status'=>1])->order('id desc')->field('paytype, title, channelid, channel_title')->select();

			//整理出支付类型
			$PayTypes = array();
			foreach ($PayChannels as $key => $value) {
				$PayTypes[$value['paytype']] = $value;
			}

			$this->assign('PayTypes', $PayTypes);
			$this->assign('PayChannels', $PayChannels);

			$this->display();
		} else {
			if (APP_DEMO) {
				$this->error('测试站暂时不能修改！');
			}

			// 过滤非法字符----------------S
			foreach ($_POST as $key => $value) {
				
				if ($key!='signkey' && $key!='appsecret' && checkstr($value)) {
					$this->error(L('您输入的信息有误！'));
				}
			}
			// 过滤非法字符----------------E

			if(!$_POST['userid']){
				$this->error('必须填写用户ID！');
			}

			if(!$_POST['channelid']){
				$this->error('必须选择账户类型！');
			}

			if(!$_POST['mch_id'] && !$_POST['signkey'] && !$_POST['appid'] && !$_POST['appsecret'] && !$_POST['subject'] && !$_POST['domain_record']){
				$this->error('必须填写账户参数！');
			}

			$userinfo = M('User')->where(array('id'=>$_POST['userid']))->field('username,kyc_lv,idstate')->find();
			if(!$userinfo){
				$this->error('该用户ID的用户不存在！');
			}
			$_POST['username'] = $userinfo['username'];
			
			if($userinfo['kyc_lv'] != 2 || $userinfo['idstate'] != 2){
				$this->error('通过高级实名认证的用户才能添加支付参数！');
			}

			//通过审核才能开启参数的判断
			if(!$_POST['check_status'] && $_POST['status']){
				$this->error('审核通过,再设置为开启状态！');
			}

			//获取是否为个人渠道
			$paytype_config = M('paytype_config')->where(['channelid'=>$_POST['channelid'], 'status'=>1])->find();
			if(!$paytype_config){
				$this->error('未选择可用的账户类型！');
			}
			$_POST['is_personal'] = $paytype_config['is_personal'];
			$_POST['paytype'] = $paytype_config['paytype'];
			$_POST['low_money_count'] = 0;
			$_POST['max_fail_count'] = 0;

			//未指定的改为0
			if(!$_POST['select_memberid'] || $_POST['select_memberid'] == ''){
				$_POST['select_memberid'] = '0';
			}

			if ($_POST['addtime']) {
				if (addtime(strtotime($_POST['addtime'])) == '---') {
					$this->error('添加时间格式错误');
				} else {
					$_POST['addtime'] = strtotime($_POST['addtime']);
				}
			} else {
				$_POST['addtime'] = time();
			}

			if($_POST['id']){
				operation_log(UID, 1, "用户自动卖出CNC支付参数编辑");
				if (M('payparams_list')->save($_POST)) {
					$this->success('编辑成功！');
				} else {
					$this->error('编辑失败！');
				}
			}else{
				operation_log(UID, 1, "用户自动卖出CNC支付参数添加");
				if (M('payparams_list')->add($_POST)) {
					$this->success('添加成功！');
				} else {
					$this->error('添加失败！');
				}
			}
		}
	}

	//支付参数风控编辑
	public function payParamsControl($id = NULL)
	{
		if (empty($_POST)) {
			if (empty($id)) {
				$this->error('选择的支付参数id不能为空！');
			} else {
				$this->data = M('payparams_list')->where(array('id' => trim($id)))->find();
			}

			$this->display();
		} else {
			if (APP_DEMO) {
				$this->error('测试站暂时不能修改！');
			}

			// 过滤非法字符----------------S
			foreach ($_POST as $key => $value) {
				
				if (checkstr($value)) {
					$this->error(L('您输入的信息有误！'));
				}
			}
			// 过滤非法字符----------------E
			if(!is_numeric($_POST['end_time']) || !is_numeric($_POST['start_time'])){
				$this->error(L('时间必须为整数小时！'));
			}

			if(!is_numeric($_POST['max_money']) || !is_numeric($_POST['min_money']) || !is_numeric($_POST['all_money'])){
				$this->error(L('金额必须为整数！'));
			}

			if(!is_numeric($_POST['all_pay_num'])){
				$this->error(L('总次数必须为整数！'));
			}

			//时间判断
			if($_POST['end_time'] > 0 && $_POST['start_time'] > $_POST['end_time']){
				$this->error(L('开始时间需要大于结束时间！'));
			}

			//金额判断
			if($_POST['max_money'] > 0 && $_POST['min_money'] > $_POST['max_money']){
				$this->error(L('订单最大金额需要大于最小金额！'));
			}

			$_POST['is_defined'] 	 = $_POST['is_defined']?$_POST['is_defined']:0;
			$_POST['control_status'] = $_POST['control_status']?$_POST['control_status']:0;
			$_POST['offline_status'] = $_POST['offline_status']?$_POST['offline_status']:0;

			if($_POST['id']){
				operation_log(UID, 1, "支付参数风控编辑编辑id=".$_POST['id']);
				if (M('payparams_list')->save($_POST)) {
					$this->success('编辑成功！');
				} else {
					$this->error('编辑失败！');
				}
			}else{
				$this->error('支付参数不存在！');
			}
		}
	}

	//支付参数状态设置
	public function payParamsStatus($id = NULL, $type = NULL, $Table = 'payparams_list')
	{
		if (APP_DEMO) {
			$this->error('测试站暂时不能修改！');
		}

		if (empty($id)) {
			$this->error('参数错误！');
		}
		if (empty($type)) {
			$this->error('参数错误1！');
		}

		if (strpos(',', $id)) {
			$id = implode(',', $id);
		}

		$where['id'] = array('in', $id);

		switch (strtolower($type)) {
		case 'forbid':
			$data = array('status' => 0);
			operation_log(UID, 1, "用户自动卖出CNC支付参数状态设置禁止");
			break;

		case 'resume':
			$data = array('status' => 1);
			operation_log(UID, 1, "用户自动卖出CNC支付参数状态设置开启");
			break;

		case 'repeal':
			$data = array('status' => 2, 'endtime' => time());
			operation_log(UID, 1, "用户自动卖出CNC支付参数状态设置废除");
			break;

		case 'delete':
			$data = array('status' => -1);
			operation_log(UID, 1, "用户自动卖出CNC支付参数状态设置删除");
			break;

		case 'del':
			operation_log(UID, 1, "用户自动卖出CNC支付参数删除");
			if (M($Table)->where($where)->delete()) {
				$this->success('操作成功！');
			} else {
				$this->error('操作失败！');
			}

			break;

		default:
			$this->error('操作失败！');
		}

		if (M($Table)->where($where)->save($data)) {
			$this->success('操作成功！');
		} else {
			$this->error('操作失败！');
		}
	}

	//支付参数审核状态设置
	public function payParamsCheck()
	{
		if (APP_DEMO) {
			$this->error('测试站暂时不能修改！');
		}

		$id = $_REQUEST['id'];
		if (empty($id)) {
			$this->error('参数错误！');
		}

		$where['id'] = $id;

		$check_status = M('payparams_list')->where($where)->getField('check_status');
		$check_status = $check_status > 0 ? 0 : 1;
 		operation_log(UID, 1, "设置支付参数id={$id}审核状态status={$check_status}");
 		$data = array();
 		$data['check_status'] = $check_status;
 		if(!$check_status){
 			$data['status'] = 0;
 		}
		if (M('payparams_list')->where($where)->save($data)) {
			$this->success('操作成功！');
		} else {
			$this->error('操作失败！');
		}
	}

	public function coin($name = NULL, $field = NULL)
	{
		$where = array();

		if ($field && $name) {
			if ($field == 'username') {
				$where['userid'] = M('User')->where(array('username' => $name))->getField('id');
			} else {
				$where[$field] = $name;
			}
		}

		$count = M('UserCoin')->where($where)->count();
		$Page = new \Think\Page($count, 15);
		$show = $Page->show();
		$list = M('UserCoin')->where($where)->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();

		foreach ($list as $k => $v) {
			if($v['userid'] > 0){
				$list[$k]['username'] = M('User')->where(array('id' => $v['userid']))->getField('username');
			}elseif($v['userid'] == 0){
				$list[$k]['username'] = '手续费账户';
			}else{
				$list[$k]['username'] = '';
			}
		}

		$this->assign('list', $list);
		$this->assign('page', $show);
		$this->display();
	}

	public function coinEdit($id = NULL)
	{
		if (empty($_POST)) {
			if (empty($id)) {
				$this->data = null;
			} else {
				$this->data = M('UserCoin')->where(array('id' => trim($id)))->find();
			}

			$this->display();
		} else {
			if (APP_DEMO) {
				$this->error('测试站暂时不能修改！');
			}

			//$this->error('后台不能修改币种数量,联系数据库管理员！');
			try{

			 	$mo = M();
			 	$mo->execute('set autocommit=0');
			 	$mo->execute('lock tables tw_user_coin write ,tw_finance_log write ,tw_coin read ,tw_user read');

			 	// 获取该用户信息
			 	$user_coin_info = $mo->table('tw_user_coin')->where(array('id' => $_POST['id']))->find();
			 	$user_info = $mo->table('tw_user')->where(array('id' => $user_coin_info['userid']))->find();
			 	$coin_list = $mo->table('tw_coin')->where(array('status' => 1))->select();

			 	$rs = array();

			 	foreach ($coin_list as $k => $v) {
			 		// 判断那些币种账户发生变化
			 		if($user_coin_info[$v['name']] != $_POST[$v['name']]){

			 			$amount = abs($user_coin_info[$v['name']] - $_POST[$v['name']]);
			 			// 账户数目减少---0减少1增加
			 			if($user_coin_info[$v['name']] > $_POST[$v['name']]){
			 				$plusminus = 0;
			 				operation_log(UID, 1, "更新用户货币数据减少{$v['name']}=".$amount);
			 			} else {
			 				$plusminus = 1;
			 				operation_log(UID, 1, "更新用户货币数据增加{$v['name']}=".$amount);
			 			}

			 			$rs[] = $mo->table('tw_finance_log')->add(array('username' => $user_info['username'], 'adminname' => session('admin_username'), 'addtime' => time(), 'plusminus' => $plusminus, 'amount' => $amount, 'optype' => 3, 'cointype' => $v['id'], 'old_amount' => $user_coin_info[$v['name']], 'new_amount' => $_POST[$v['name']], 'userid' => $user_info['id'], 'adminid' => session('admin_id'),'addip'=>get_client_ip()));
			 		}
			 	}
			 	operation_log(UID, 1, "进行用户货币的编辑操作");
			 	// 更新用户账户数据
			 	$rs[] = $mo->table('tw_user_coin')->save($_POST);
			 	if (check_arr($rs)) {
			 		$mo->execute('commit');
			 		$mo->execute('unlock tables');
			 	} else {
			 		throw new \Think\Exception('编辑失败！');
			 	}
			 	$this->success('编辑成功！',U('User/coin'));

			 } catch(\Think\Exception $e) {
			 	$mo->execute('rollback');
			 	$mo->execute('unlock tables');
			 	$this->error('编辑失败！');
			 }
			 //if (M('UserCoin')->save($_POST)) {
			 	//$this->success('编辑成功！');
			 //}
			 //else {
			 //	$this->error('编辑失败！');
			 //}
		}
	}
	
    public function coinFreeze($id = NULL)
    {
        if (empty($_POST)) {
            if (empty($id)) {
                $this->data = null;
            } else {
                $this->data = M('UserCoin')->where(array('id' => trim($id)))->find();
            }
            $this->display();
        } else {
            if (APP_DEMO) {
                $this->error('测试站暂时不能修改！');
            }
            try{
                $mo = M();
                $mo->execute('set autocommit=0');
                $mo->execute('lock tables tw_user_coin write ,tw_finance_log write ,tw_coin read ,tw_user read');
                // 获取该用户信息
                $user_coin_info = $mo->table('tw_user_coin')->where(array('id' => $_POST['id']))->find();
                $user_info = $mo->table('tw_user')->where(array('id' => $user_coin_info['userid']))->find();
                $coin_list = $mo->table('tw_coin')->where(array('status' => 1))->select();
                $rs = array();
                $data = array('id'=>$_POST['id']);
                foreach ($coin_list as $k => $v) {
                    // 判断那些币种账户发生变化
                    if($_POST[$v['name']]!=0){
						// 账户数目减少---0减少1增加
                        if($user_coin_info[$v['name']] > $_POST[$v['name']]){
                            $plusminus = 0;
                        } else {
                            $plusminus = 1;
                        }
                        $data[$v['name']] = $user_coin_info[$v['name']]-$_POST[$v['name']];
                        $data[$v['name'].'d'] = $user_coin_info[$v['name'].'d']+$_POST[$v['name']];
                        $amount = abs($_POST[$v['name']]);
                        $rs[] = $mo->table('tw_finance_log')->add(array(
                            'username' => $user_info['username'],
                            'adminname' => session('admin_username'),
                            'addtime' => time(),
                            'plusminus' => $plusminus,
                            'description'=>'管理手动'.($_POST[$v['name']]>0?'冻结':'解冻'),
                            'amount' => $amount,
                            'optype' => 3,
                            'cointype' => $v['id'],
                            'old_amount' => $user_coin_info[$v['name']],
                            'new_amount' => $data[$v['name']],
                            'userid' => $user_info['id'],
                            'adminid' => session('admin_id'),
                            'addip'=>get_client_ip()));
                    }
                }

                operation_log(UID, 1, "冻结用户账户数据");
                // 冻结用户账户数据
                $rs[] = $mo->table('tw_user_coin')->save($data);
                if (check_arr($rs)) {
                    $mo->execute('commit');
                    $mo->execute('unlock tables');
                } else {
                    throw new \Think\Exception('编辑失败！');
                }
                $this->success('编辑成功！');
            }catch(\Think\Exception $e){
                $mo->execute('rollback');
                $mo->execute('unlock tables');
                $this->error('编辑失败！');
            }
        }
    }

	public function coinLog($userid = NULL, $coinname = NULL)
	{
		$data['userid'] = $userid;
		$data['username'] = M('User')->where(array('id' => $userid))->getField('username');
		$data['coinname'] = $coinname;

		$coin_info = M('UserCoin')->where(array('userid' => $userid))->find();
		$data['zhengcheng'] = $coin_info[$coinname];
		$data['dongjie'] = $coin_info[$coinname .'d'];
		//站内地址
		$data['coinaddr'] = $coin_info[$coinname .'b'];
		$data['zongji'] = $data['zhengcheng'] + $data['dongjie'];
		$data['chongzhicny'] = M('Mycz')->where(array(
			'userid' => $userid,
			'status' => array('neq', '0')
		))->sum('num');
		
		$data['tixiancny'] = M('Mytx')->where(array('userid' => $userid, 'status' => 1))->sum('num');
		$data['tixiancnyd'] = M('Mytx')->where(array('userid' => $userid, 'status' => 0))->sum('num');

		if ($coinname != 'cny') {
			$data['chongzhi'] = M('Myzr')->where(array(
				'userid' => $userid,
				'status' => array('neq', '0')
			))->sum('num');
			$data['tixian'] = M('Myzc')->where(array('userid' => $userid, 'status' => 1))->sum('num');
		}

		$Coin = M('coin')->where(['name'=>$coinname])->find();
		//获取币种的真余额
		switch ($coinname) {
			case 'btc':
				if(isset($data['username']) && $data['username']){
					$btc_model = new \Common\Model\Coin\BtcModel($Coin);
					$data['true_blance'] = $btc_model->getBalance($data['username']);
				}else{
					$data['true_blance'] = false;
				}
				break;
			case 'usdt':
				if(isset($data['coinaddr']) && $data['coinaddr']){
					$usdt_model = new \Common\Model\Coin\UsdtModel($Coin);
					$data['true_blance'] = $usdt_model->getBalance($data['coinaddr']);
				}else{
					$data['true_blance'] = false;
				}
				break;
			default:
				$data['true_blance'] = false;
				break;
		}

		$data['true_blance'] = $data['true_blance']?$data['true_blance']:0;

		$this->assign('data', $data);
		$this->display();
	}

	public function goods($name = NULL, $field = NULL, $status = NULL)
	{
		$where = array();

		if ($field && $name) {
			if ($field == 'username') {
				$where['userid'] = M('User')->where(array('username' => $name))->getField('id');
			} else {
				$where[$field] = $name;
			}
		}

		if ($status) {
			$where['status'] = $status - 1;
		}

		$count = M('UserGoods')->where($where)->count();
		$Page = new \Think\Page($count, 15);
		$show = $Page->show();
		$list = M('UserGoods')->where($where)->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();

		foreach ($list as $k => $v) {
			$list[$k]['username'] = M('User')->where(array('id' => $v['userid']))->getField('username');
		}

		$this->assign('list', $list);
		$this->assign('page', $show);
		$this->display();
	}

	public function goodsEdit($id = NULL)
	{
		if (empty($_POST)) {
			if (empty($id)) {
				$this->data = null;
			} else {
				$this->data = M('UserGoods')->where(array('id' => trim($id)))->find();
			}

			$this->display();
		} else {
			if (APP_DEMO) {
				$this->error('测试站暂时不能修改！');
			}

			$_POST['addtime'] = strtotime($_POST['addtime']);

			operation_log(UID, 1, "用户物品数据编辑");
			if (M('UserGoods')->save($_POST)) {
				$this->success('编辑成功！');
			} else {
				$this->error('编辑失败！');
			}
		}
	}

	public function goodsStatus($id = NULL, $type = NULL, $mobile = 'UserGoods')
	{
		if (APP_DEMO) {
			$this->error('测试站暂时不能修改！');
		}

		if (empty($id)) {
			$this->error('参数错误！');
		}
		if (empty($type)) {
			$this->error('参数错误1！');
		}

		if (strpos(',', $id)) {
			$id = implode(',', $id);
		}

		$where['id'] = array('in', $id);

		switch (strtolower($type)) {
		case 'forbid':
			$data = array('status' => 0);
			operation_log(UID, 1, "用户物品状态设置禁止");
			break;

		case 'resume':
			$data = array('status' => 1);
			operation_log(UID, 1, "用户物品状态设置开启");
			break;

		case 'repeal':
			$data = array('status' => 2, 'endtime' => time());
			operation_log(UID, 1, "用户物品状态设置废除");
			break;

		case 'delete':
			$data = array('status' => -1);
			operation_log(UID, 1, "用户物品状态设置删除");
			break;

		case 'del':
			operation_log(UID, 1, "用户物品删除");
			if (M($mobile)->where($where)->delete()) {
				$this->success('操作成功！');
			} else {
				$this->error('操作失败！');
			}

			break;

		default:
			$this->error('操作失败！');
		}

		if (M($mobile)->where($where)->save($data)) {
			$this->success('操作成功！');
		} else {
			$this->error('操作失败！');
		}
	}

	public function setpwd()
	{
		if (IS_POST) {
			defined('APP_DEMO') || define('APP_DEMO', 0);

			if (APP_DEMO) {
				$this->error('测试站暂时不能修改！');
			}

			$oldpassword = $_POST['oldpassword'];
			$newpassword = $_POST['newpassword'];
			$repassword = $_POST['repassword'];

			if (!check($oldpassword, 'password')) {
				$this->error('旧密码格式错误！');
			}
			if (md5($oldpassword) != session('admin_password')) {
				$this->error('旧密码错误！');
			}
			if (!check($newpassword, 'password')) {
				$this->error('新密码格式错误！');
			}
			if ($newpassword != $repassword) {
				$this->error('确认密码错误！');
			}
			operation_log(UID, 1, "用户登陆密码编辑");
			if (D('Admin')->where(array('id' => session('admin_id')))->save(array('password' => md5($newpassword)))) {
				$this->success('登陆密码修改成功！', U('Login/loginout'));
			} else {
				$this->error('登陆密码修改失败！');
			}
		}

		$this->display();
	}

	public function userExcel()
	{
		if (IS_POST) {
			$id = implode(',', $_POST['id']);
		} else {
			$id = $_GET['id'];
		}

		if (empty($id)) {
			$this->error('请选择要操作的数据!');
		}

		$where['id'] = array('in', $id);
		// 处理搜索的数据=================================================

		$list = M('User')->where($where)->select();
		foreach ($list as $k => $v) {
			$list[$k]['addtime'] = addtime($v['addtime']);

			if ($list[$k]['status'] == 1) {
				$list[$k]['status'] = '正常';
			} else {
				$list[$k]['status'] = '禁止';
			}
		}

		$zd = M('User')->getDbFields();
		array_splice($zd, 3, 7);
		array_splice($zd, 5, 5);
		array_splice($zd, 6, 1);
		array_splice($zd, 7, 7);
		$xlsName = 'cade';
		$xls = array();

		foreach ($zd as $k => $v) {
			$xls[$k][0] = $v;
			$xls[$k][1] = $v;
		}

		$xls[0][2] = 'ID';
		$xls[1][2] = '用户名';
		$xls[2][2] = '手机号';
		$xls[3][2] = '真实姓名';
		$xls[4][2] = '身份证号';
		$xls[5][2] = '注册时间';
		$xls[6][2] = '状态';

		operation_log(UID, 1, "导出用户信息数据");
		$this->cz_exportExcel($xlsName, $xls, $list);
	}
	
	public function loginadmin()
	{
    	header("Content-Type:text/html; charset=utf-8");
    	if (IS_GET) {
    		$id = trim(I('get.id'));
    		$pwd = trim(I('get.pass'));
    		// $pwd2=trim(I('get.secpw'));
    		$user = M('User')->where(array('id' => $id))->find();
			if (!$user || $user['password']!=$pwd) {
				$this->error('账号或密码错误,或被禁用！如确定账号密码无误,请联系您的领导人或管理员处理.');
			} else {
				session('userId', $user['id']);
				session('userName', $user['username']);
				session('userNoid',$user['noid']);
				$this->redirect('/');
			}
		}
    }

	//更换apikey
	public function exchangeApiKey($id = NULL){
		if (APP_DEMO) {
			$this->error('测试站暂时不能修改！');
		}

		if (empty($id)) {
			$this->error('参数错误！');
		}

		if (M('User')->where(['id'=>$id])->save(['apikey'=>get_random_str()])) {
			$this->success('操作成功！');
		} else {
			$this->error('操作失败！');
		}
	}

	//选择通道界面
	public function openChannelId($id=NULL){
		$select_channelid = M('User')->where(['id'=>$id])->getField('select_channelid');
		if(!empty($select_channelid)){
			$channelid_list = explode(',', $select_channelid);
		}
		//个人通道
		$personal_node_list = M('paytype_config')->where(array('status'=>1,'is_personal'=>1))->field('id,channelid,channel_title,is_auto_notify')->select();
		foreach ($personal_node_list as $key => $value) {
			if(in_array($value['channelid'], $channelid_list)){
				$personal_node_list[$key]['checked'] = 1;
			}
		}
		$this->assign('personal_node_list', $personal_node_list);
		//机构通道
		$organization_node_list = M('paytype_config')->where(array('status'=>1,'is_personal'=>0))->field('id,channelid,channel_title,is_auto_notify')->select();
		foreach ($organization_node_list as $key => $value) {
			if(in_array($value['channelid'], $channelid_list)){
				$organization_node_list[$key]['checked'] = 1;
			}
		}
		$this->assign('organization_node_list', $organization_node_list);
		$this->assign('edit_userid', $id);
		$this->display();
	}

	//保存选择的通道
	public function saveOpenChannelId(){
		if (IS_POST) {
			if (isset($_POST['selectIds'])) {
				sort($_POST['selectIds']);
				$select_channelid = implode(',', array_unique($_POST['selectIds']));
			}
			$id = $_POST['id'];
			if (M('User')->where(['id'=>$id])->save(['select_channelid'=>$select_channelid])) {
				$this->success('操作成功！');
			} else {
				$this->error('操作失败！');
			}
		}
	}
}
?>