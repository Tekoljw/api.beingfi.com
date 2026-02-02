<?php
namespace Admin\Controller;
use Think\Log;
class FinanceController extends AdminController
{
	protected function _initialize(){
		parent::_initialize();
		$allow_action=array("index","fianceExcel","amountlog","mycz","myczExcel","myczConfig","myczStatus","myczQueren","myczType","myczTypeEdit","myczTypeImage","myczTypeStatus","mytx","mytxStatus","mytxChuli","mytxChexiao","mytxQueren","mytxExcel","mytxConfig","myzr","myzc","myzcQueren","myzcCancel");
		if(!in_array(ACTION_NAME,$allow_action)){
			$this->error("页面不存在！");
		}
	}

	public function index($field = NULL, $name = NULL, $type = NULL, $starttime = NULL, $endtime = NULL)
	{
		$where = array();

		if ($field && $name) {
			if ($field == 'username') {
				$where['userid'] = M('User')->where(array('username' => $name))->getField('id');
			}
			else {
				$where[$field] = $name;
			}
		}

		//操作类型
		if(!empty($type) && $type != ''){
			$where['type'] = $type;
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
		}else{
			$starttime = strtotime(date('Y-m-d 00:00:00'));
			$endtime = time();
			$where['addtime'] =  array(array('EGT',$starttime),array('ELT',$endtime));
		}

		$tongji = array();
		//符合条件的费用
		$tongji['conditin_fee'] = M('Finance')->where($where)->sum('fee')*1;
		$tongji['conditin_count'] = M('Finance')->where($where)->count();
		$this->assign('tongji', $tongji);
		
		$count = M('Finance')->where($where)->count();
		$Page = new \Think\Page($count, 15);
		$show = $Page->show();
		$list = M('Finance')->where($where)->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();

		foreach ($list as $k => $v) {
			$list[$k]['username'] = M('User')->where(array('id' => $v['userid']))->getField('username');
			$list[$k]['num_a'] = Num($v['num_a']);
			$list[$k]['num_b'] = Num($v['num_b']);
			$list[$k]['num'] = Num($v['num']);
			$list[$k]['fee'] = Num($v['fee']);
			$list[$k]['type'] = ($v['type'] == 1 ? '收入' : '支出');
			$list[$k]['name'] = ($name_list[$v['name']] ? $name_list[$v['name']] : $v['name']);
			$list[$k]['mum_a'] = Num($v['mum_a']);
			$list[$k]['mum_b'] = Num($v['mum_b']);
			$list[$k]['mum'] = Num($v['mum']);
			$list[$k]['addtime'] = addtime($v['addtime']);
		}

		$this->assign('list', $list);
		$this->assign('page', $show);
		$this->display();
	}

	//导出资金变动记录
	public function fianceExcel(){
		$where = array();

		$endtime = time();
		$starttime = $endtime - 7*24*60*60;
		$where['addtime'] =  array(array('EGT',$starttime),array('ELT',$endtime));

		//资金变动
		$title = array('用户ID','用户名','操作币种','操作数量','操作类型','操作之前正常','操作之前冻结', '操作之前总计', '名称', '操作之后正常', '操作之后冻结','操作之后总计', '操作说明', '确认时间');
		$list = M('Finance')->where($where)->order('id desc')->select();
		foreach ($list as $k => $v) {
			$data[] = [
				'userid'	=> $v['userid'],
        		'username'	=> M('User')->where(array('id' => $v['userid']))->getField('username'),
        		'coinname'	=> $v['coinname'],
        		'fee'		=> Num($v['fee']),
        		'type'		=> ($v['type'] == 1 ? '收入' : '支出'),
        		'num_a'		=> Num($v['num_a']),
        		'num_b'		=> Num($v['num_b']),
        		'num'		=> Num($v['num']),
        		'name'		=> $v['name'],
        		'mum_a'		=> Num($v['mum_a']),
        		'mum_b'		=> Num($v['mum_b']),
        		'mum'		=> Num($v['mum']),
        		'addtime'	=> addtime($v['addtime']),
        	];
		}
        exportexcel($data, $title);
        // 将已经写到csv中的数据存储变量销毁，释放内存占用
        unset($data);
        //刷新缓冲区
        ob_flush();
        flush();
	}

	// 资金变更日志
	public function amountlog($position = 'all', $plusminus = 'all', $name = NULL, $field = NULL, $cointype = NULL, $optype = NULL, $starttime = NULL, $endtime = NULL)
	{
		$where = array();
		if ($field && $name) {
			$where[$field] = $name;
		}
		if ($cointype) {
			$where['cointype'] = $cointype;
		}
		if ($optype) {
			$where['optype'] = $optype - 1;
		}
		if ($plusminus != 'all') {
			if ($plusminus == 'jia') {
				$where['plusminus'] = '1';
			} else if ($plusminus == 'jian') {
				$where['plusminus'] = '0';
			}
		}
		if ($position != 'all') {
			if ($position == 'hou') {
				$where['position'] = '0';
			} else if ($position == 'qian') {
				$where['position'] = '1';
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
		// else{
		// 	// 无时间查询，显示申请时间类型十天以内数据
		// 	$now_time = time() - 10*24*60*60;
		// 	$where['addtime'] =  array('EGT',$now_time);
		// }

		$count = M('FinanceLog')->where($where)->count();
		$Page = new \Think\Page($count, 15);
		$show = $Page->show();
		$list = M('FinanceLog')->where($where)->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
		// dump($where);
		foreach ($list as $k => $v) {
			$coin_info = M('Coin')->where(array('id'=>$v['cointype']))->find();
			$list[$k]['cointype'] =strtoupper($coin_info['name']);
			$list[$k]['optype'] = opstype($v['optype'],2);
			$list[$k]['old_amount'] = $v['old_amount']*1;
			$list[$k]['amount'] = $v['amount']*1;
			$list[$k]['new_amount'] = $v['new_amount']*1;
			if ($v['plusminus']) {
				$list[$k]['plusminus'] = '增加';
			} else {
				$list[$k]['plusminus'] = '减少';
			}
			if ($v['position']) {
				$list[$k]['position'] = '前台';
			} else {
				$list[$k]['position'] = '后台';
			}
		}

		$opstype = opstype('',88);
		$coinlists=M('coin')->where(array('name'=>array('neq','cny'),'status'=>1))->select();
		$this->assign('coins', $coinlists);
		$this->assign('opstype', $opstype);
		$this->assign('list', $list);
		$this->assign('page', $show);
		$this->display();
	}

	// 导出充值明细表
	public function myczExcel()
	{
		if (IS_POST) {
			$id = implode(',', $_POST['id']);
		}
		else {
			$id = $_GET['id'];
		}

		if (empty($id)) {
			$this->error('请选择要操作的数据!');
		}

		$where['id'] = array('in', $id);
		// 处理搜索的数据=================================================

		$list = M('Mycz')->where($where)->select();
		foreach ($list as $k => $v) {
			$list[$k]['userid'] = M('User')->where(array('id' => $v['userid']))->getField('username');
			$list[$k]['addtime'] = addtime($v['addtime']);
			$list[$k]['endtime'] = addtime($v['endtime']);

			if ($list[$k]['status'] == 0) {
				$list[$k]['status'] = '未付款';
			} else if ($list[$k]['status'] == 2) {
				$list[$k]['status'] = '人工到账';
			} else if ($list[$k]['status'] == 3) {
				$list[$k]['status'] = '处理中';
			} else if ($list[$k]['status'] == 1) {
				$list[$k]['status'] = '充值成功';
			} else {
				$list[$k]['status'] = '错误';
			}
		}

		$zd = M('Mycz')->getDbFields();
		array_splice($zd, 6, 2);
		array_splice($zd, 11, 1);
		$xlsName = 'cade';
		$xls = array();

		foreach ($zd as $k => $v) {
			$xls[$k][0] = $v;
			$xls[$k][1] = $v;
		}

		$xls[0][2] = '编号';
		$xls[1][2] = '用户名';
		$xls[2][2] = '充值金额';
		$xls[3][2] = '到账金额';
		$xls[4][2] = '充值方式';
		$xls[5][2] = '充值订单号';
		$xls[6][2] = '充值添加时间';
		$xls[7][2] = '充值结束时间';
		$xls[8][2] = '充值状态';
		$xls[9][2] = '真实姓名';
		$xls[10][2] = '银行账号';
		$xls[11][2] = '手续费';
		$xls[12][2] = '银行';

		operation_log(UID, 1, "导出充值明细表");
		$this->cz_exportExcel($xlsName, $xls, $list);
	}

	// 人民币买入配置
	public function myczConfig()
	{
		if (empty($_POST)) {
			$this->display();
		} else if (M('Config')->where(array('id' => 1))->save($_POST)) {
			operation_log(UID, 1, "人民币充值配置修改");
			$this->success('修改成功！');
		} else {
			$this->error('修改失败');
		}
	}

	public function myczStatus($id = NULL, $type = NULL, $mobile = 'Mycz')
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
				operation_log(UID, 1, "买入状态设置禁止");
				break;

			case 'resume':
				$data = array('status' => 1);
				operation_log(UID, 1, "买入状态设置开启");
				break;

			case 'repeal':
				$data = array('status' => 2, 'endtime' => time());
				operation_log(UID, 1, "买入状态设置废除");
				break;

			case 'delete':
				$data = array('status' => -1);
				operation_log(UID, 1, "买入状态设置删除");
				break;

			case 'del':
				operation_log(UID, 1, "买入删除");
				if (M($mobile)->where($where)->delete()) {
					$this->success('操作成功！');
				}
				else {
					$this->error('操作失败！');
				}

				break;

			default:
				$this->error('操作失败1！');
		}

		if (M($mobile)->where($where)->save($data)) {
			$this->success('操作成功！');
		} else {
			$this->error('操作失败2！');
		}
	}

	public function myczType()
	{
		$where = array();
		$count = M('MyczType')->where($where)->count();
		$Page = new \Think\Page($count, 15);
		$show = $Page->show();
		$list = M('MyczType')->where($where)->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
		$this->assign('list', $list);
		$this->assign('page', $show);
		$this->display();
	}

	public function myczTypeEdit($id = NULL)
	{
		if (empty($_POST)) {
			if ($id) {
				$this->data = M('MyczType')->where(array('id' => trim($id)))->find();
			} else {
				$this->data = null;
			}

			$this->display();
		} else {
			if (APP_DEMO) {
				$this->error('测试站暂时不能修改！');
			}

			if ($_POST['id']) {
				operation_log(UID, 1, "人民币充值方式编辑");
				$rs = M('MyczType')->save($_POST);
			} else {
				operation_log(UID, 1, "人民币充值方式添加");
				$rs = M('MyczType')->add($_POST);
			}

			if ($rs) {
				$this->success('操作成功！');
			} else {
				$this->error('操作失败！');
			}
		}
	}

	public function myczTypeImage()
	{
		$upload = new \Think\Upload();
		$upload->maxSize = 3145728;
		$upload->exts = array('jpg', 'gif', 'png', 'jpeg');
		$upload->rootPath = './Upload/public/';
		$upload->autoSub = false;
		$info = $upload->upload();

		foreach ($info as $k => $v) {
			$path = $v['savepath'] . $v['savename'];
			echo $path;
			exit();
		}
	}

	public function myczTypeStatus($id = NULL, $type = NULL, $mobile = 'MyczType')
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
				operation_log(UID, 1, "人民币充值方式状态设置禁止");
				break;

			case 'resume':
				$data = array('status' => 1);
				operation_log(UID, 1, "人民币充值方式状态设置开启");
				break;

			case 'repeal':
				$data = array('status' => 2, 'endtime' => time());
				operation_log(UID, 1, "人民币充值方式状态设置废除");
				break;

			case 'delete':
				$data = array('status' => -1);
				operation_log(UID, 1, "人民币充值方式状态设置删除");
				break;

			case 'del':
				operation_log(UID, 1, "人民币充值方式删除");
				if (M($mobile)->where($where)->delete()) {
					$this->success('操作成功！');
				} else {
					$this->error('操作失败！');
				}
				break;

			default:
				$this->error('操作失败1！');
		}

		if (M($mobile)->where($where)->save($data)) {
			$this->success('操作成功！');
		} else {
			$this->error('操作失败2！');
		}
	}

	public function mytxStatus($id = NULL, $type = NULL, $mobile = 'Mytx')
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
				operation_log(UID, 1, "人民币提现状态设置禁止");
				break;

			case 'resume':
				$data = array('status' => 1);
				operation_log(UID, 1, "人民币提现状态设置开启");
				break;

			case 'repeal':
				$data = array('status' => 2, 'endtime' => time());
				operation_log(UID, 1, "人民币提现状态设置废除");
				break;

			case 'delete':
				$data = array('status' => -1);
				operation_log(UID, 1, "人民币提现状态设置删除");
				break;

			case 'del':
				operation_log(UID, 1, "人民币提现删除");
				if (M($mobile)->where($where)->delete()) {
					$this->success('操作成功！');
				} else {
					$this->error('操作失败！');
				}

				break;

			default:
				$this->error('操作失败1！');
		}

		if (M($mobile)->where($where)->save($data)) {
			$this->success('操作成功！');
		} else {
			$this->error('操作失败2！');
		}
	}

	public function mytxChuli()
	{
		$id = $_GET['id'];
		if (empty($id)) {
			$this->error('请选择要操作的数据!');
		}

		operation_log(UID, 1, "人民币提现确认处理中");
		if (M('Mytx')->where(array('id' => $id))->save(array('status' => 3,'endtime' => time()))) {
			$this->success('操作成功！');
		} else {
			$this->error('操作失败！');
		}
	}

	public function mytxQueren()
	{
		$id = $_GET['id'];
		if (empty($id)) {
			$this->error('请选择要操作的数据!');
		}

		operation_log(UID, 1, "人民币提现确认处理成功");
		if (M('Mytx')->where(array('id' => $id))->save(array('status' => 1))) {
			$this->success('操作成功！');
		} else {
			$this->error('操作失败！');
		}
	}

	// 导出提现明细表
	public function mytxExcel()
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
		$list = M('Mytx')->where($where)->field('id,userid,num,fee,mum,truename,name,bank,bankprov,bankcity,bankaddr,bankcard,addtime,endtime,status')->select();

		foreach ($list as $k => $v) {
			$list[$k]['userid'] = M('User')->where(array('id' => $v['userid']))->getField('username');
			$list[$k]['addtime'] = addtime($v['addtime']);

			if ($list[$k]['status'] == 0) {
				$list[$k]['status'] = '未处理';
			} else if ($list[$k]['status'] == 1) {
				$list[$k]['status'] = '已划款';
			} else if ($list[$k]['status'] == 2) {
				$list[$k]['status'] = '已撤销';
			} else if ($list[$k]['status'] == 3) {
				$list[$k]['status'] = '正在处理';
			} else {
				$list[$k]['status'] = '错误';
			}

			$list[$k]['bankcard'] = ' '.$v['bankcard'].' ';
		}

		$zd = M('Mytx')->getDbFields();
		array_splice($zd, 12, 1);
		$xlsName = 'cade';
		$xls = array();
		foreach ($zd as $k => $v) {
			$xls[$k][0] = $v;
			$xls[$k][1] = $v;
		}

		$xls[0][2] = '编号';
		$xls[1][2] = '用户名';
		$xls[2][2] = '提现金额';
		$xls[3][2] = '手续费';
		$xls[4][2] = '到账金额';
		$xls[5][2] = '姓名';
		$xls[6][2] = '银行备注';
		$xls[7][2] = '银行名称';
		$xls[8][2] = '开户省份';
		$xls[9][2] = '开户城市';
		$xls[10][2] = '开户地址';
		$xls[11][2] = '银行卡号';
		$xls[12][2] = '提现时间';
		$xls[13][2] = '导出时间';
		$xls[14][2] = '提现状态';

		operation_log(UID, 1, "人民币提现记录导出");

		$this->exportExcel($xlsName, $xls, $list);
	}

	public function mytxConfig()
	{
		if (empty($_POST)) {
			$this->display();
		} else if (M('Config')->where(array('id' => 1))->save($_POST)) {
			operation_log(UID, 1, "人民币提现配置编辑");
			$this->success('修改成功！');
		} else {
			$this->error('修改失败');
		}
	}
	
	// 虚拟币转入
	public function myzr($field = NULL, $name = NULL, $coinname = NULL, $time_type = 'addtime', $starttime = NULL, $endtime = NULL, $num_start = NULL, $num_stop = NULL)
	{
		$where = array();

		if ($field && $name) {
			if ($field == 'username') {
				$where['userid'] = M('User')->where(array('username' => $name))->getField('id');
			} else {
				$where[$field] = $name;
			}
		}

		if ($coinname) {
			$where['coinname'] = $coinname;
		}

		// 转入数量--条件
		if (is_numeric($num_start) && !is_numeric($num_stop)) {
			$where['num'] = array('EGT',$num_start);
		} else if (!is_numeric($num_start) && is_numeric($num_stop)) {
			$where['num'] = array('ELT',$num_stop);
		} else if (is_numeric($num_start) && is_numeric($num_stop)) {
			$where['num'] = array(array('EGT',$num_start),array('ELT',$num_stop));
		}

		// 时间--条件
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

		$count = M('Myzr')->where($where)->count();
		$Page = new \Think\Page($count, 15);
		$show = $Page->show();
		
		$list = M('Myzr')->where($where)->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
		foreach ($list as $k => $v) {
			$list[$k]['usernamea'] = M('User')->where(array('id' => $v['userid']))->getField('username');
		}

		$this->assign('list', $list);
		$this->assign('page', $show);
		$this->display();
	}
	
	// 虚拟币转出
	public function myzc($field = NULL, $name = NULL, $coinname = NULL, $time_type = 'addtime', $starttime = NULL, $endtime = NULL, $num_start = NULL, $num_stop = NULL)
	{
		$where = array();

		if ($field && $name) {
			if ($field == 'username') {
				$where['userid'] = M('User')->where(array('username' => $name))->getField('id');
			} else {
				$where[$field] = $name;
			}
		}

		if ($coinname) {
			$where['coinname'] = $coinname;
		}

		// 转入数量--条件
		if(is_numeric($num_start) && !is_numeric($num_stop)){
			$where['num'] = array('EGT',$num_start);
		} else if (!is_numeric($num_start) && is_numeric($num_stop)) {
			$where['num'] = array('ELT',$num_stop);
		} else if (is_numeric($num_start) && is_numeric($num_stop)) {
			$where['num'] = array(array('EGT',$num_start),array('ELT',$num_stop));
		}

		// 时间--条件
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
		} else {
			// 无时间查询，显示申请时间类型十天以内数据
			$now_time = time() - 1000*24*60*60;
			$where['addtime'] =  array('EGT',$now_time);
		}

		$count = M('Myzc')->where($where)->count();
		$Page = new \Think\Page($count, 15);
		$show = $Page->show();
		
		$list = M('Myzc')->where($where)->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
		foreach ($list as $k => $v) {
			$list[$k]['usernamea'] = M('User')->where(array('id' => $v['userid']))->getField('username');
		}

		$this->assign('list', $list);
		$this->assign('page', $show);
		$this->display();
	}

	//转出审核
	public function myzcQueren($id = NULL)
	{
		if (APP_DEMO) {
			$this->error('测试站暂时不能修改！');
		}

		$myzc = M('Myzc')->where(array('id' => trim($id)))->find();
		if (!$myzc) {
			$this->error('转出错误！');
		}
		if ($myzc['status']) {
			$this->error('已经处理过！');
		}

		$coin = $myzc['coinname'];
		//该币种的配置
		$coin_config = C('coin')[$coin];

		$dj_username = C('coin')[$coin]['dj_yh'];
		$dj_password = C('coin')[$coin]['dj_mm'];
		$dj_address = C('coin')[$coin]['dj_zj'];
		$dj_port = C('coin')[$coin]['dj_dk'];
		//官方转出钱包
		$official_zcdz = C('coin')[$coin]['zc_user'];
		if(!$official_zcdz || $official_zcdz == '0'){
			$this->error(L($coin.'未配置官方手续费收取钱包地址！'));
		}
		$qbdz = $coin . 'b'; //币地址 

		//官方收取手续费的用户
		$official_user = M('UserCoin')->where(array($qbdz => $official_zcdz))->find();
		//转出用户的货币信息
		$user_coin = M('UserCoin')->where(array('userid' => $myzc['userid']))->find();
		//站内转账
		$zhannei = M('UserCoin')->where(array($qbdz => $myzc['username']))->find();

		M()->startTrans();
		$rs = array();

		if ($zhannei) {
			$rs[] = M('myzr')->add(array('userid' => $zhannei['userid'], 'username' => $myzc['username'], 'coinname' => $coin, 'txid' => md5($myzc['username'] . $user_coin[$qbdz] . time()), 'num' => $myzc['num'], 'fee' => $myzc['fee'], 'mum' => $myzc['mum'], 'addtime' => time(), 'status' => 1));
			$rs[] = $r = M('user_coin')->where(array('userid' => $zhannei['userid']))->setInc($coin, $myzc['mum']);
		}

		if (!$official_user['userid']) {
			$official_user['userid'] = 0;
		}

		if (0 < $myzc['fee']) {

			$rs[] = $this->add_my_zc_fee($myzc, $official_user, $user_coin, $coin, 2);

			if (M('user_coin')->where(array($qbdz => $official_zcdz))->find()) {
				$rs[] = M('user_coin')->where(array($qbdz => $official_zcdz))->setInc($coin, $myzc['fee']);
				//debug(array('lastsql' => M('user_coin')->getLastSql()), '新增费用');
			} else {
				$rs[] = M('user_coin')->add(array($qbdz => $official_zcdz, $coin => $myzc['fee']));
			}
		}

		if (check_arr($rs)) {
			if (strtolower($coin) == 'eth' || strtolower($coin) =='etc') {
				//转出 ETH、ETC
				$CoinClient = EthCommon($dj_address, $dj_port);
		        if (!$CoinClient) {
					$this->error(L('钱包链接失败！'));
				}
				$mum = $CoinClient->toWei($myzc['mum']);
				$sendrs = $CoinClient->eth_sendTransaction($dj_username,$myzc['username'],$dj_password,$mum);
				
			} elseif ($coin_config['token_type'] == 1) { //ETH对接,FFF
				//转出 ERC20代币
				$CoinClient = EthCommon($dj_address, $dj_port);
	            if (!$CoinClient) {
					$this->error(L('钱包链接失败！'));
				}
				//Token合约设置
				$addr = $coin_config['dj_hydz']; //ERC20合约地址
				$wei = 1e18; //手续费
				$methodid = '0xa9059cbb';
				
				if($coin=='zil'){
					$addr = '0x05f4a42e251f2d52b8ed15e9fedaacfcef1fad27';
					$wei = 1e12;
				}
				if($coin=='trx'){
					$addr = '0xf230b790e05390fc8295f4d3f60332c93bed42e2';
					$wei = 1e6;
				}
/*				if($coin=='fff'){
					$addr = '0xe045e994f17c404691b238b9b154c0998fa28aef';
				}*/
				
				if(!$addr){
					$mo->rollback();
					$this->error('ERC20合约地址不存在');
				}
				
				$url = 'https://api.etherscan.io/api?module=account&action=tokenbalance&contractaddress='.$addr.'&address='.$dj_username.'&tag=latest&apikey=ERXIYCNF6PP3ZNQAWICHJ6N5W7P212AHZI';
				//contractaddress=合约地址,address=持有代币的地址
				$fanhui = file_get_contents($url);
				$fanhui = json_decode($fanhui,true);
				if ($fanhui['message'] == 'OK') {
					$numb = $fanhui['result']/$wei;//18位小数
				}
				if ($numb < $myzc['mum']) {
					$mo->rollback();
					$this->error('钱包余额不足');
				}
				$sendnum = NumToStr($myzc['mum']*$wei);
				$mum = bnumber($sendnum,10,16);
				$amounthex = sprintf("%064s",$mum);
				$addr2 = explode('0x',  $myzc['username'])[1];//接受地址
				$dataraw = $methodid.'000000000000000000000000'.$addr2.$amounthex;//拼接data
				$constadd = $addr;//合约地址
				$sendrs = $CoinClient->eth_sendTransactionraw($dj_username,$constadd,$dj_password,$dataraw);//转出账户,合约地址,转出账户解锁密码,data值
			} elseif (strtolower($coin)=='usdt') {
				//转出 USDT
				$usdt_model = new \Common\Model\Coin\UsdtModel($coin_config);
				$mycoin_addr = $user_coin[$qbdz]; 
		 		$result = $usdt_model->sendToCoin($mycoin_addr, $myzc['username'], $myzc['mum'], $official_zcdz, $myzc['fee']);
				if (!$result || $result['status'] == 0) {
					$mo->rollback();
					$this->error($result['msg']);
				}else{
					$sendrs = $result['msg'];
				}
			} else {
				//转出 BTC
				$btc_model  = new \Common\Model\Coin\BtcModel($coin_config);
				$mycoin_addr = $user_coin[$qbdz]; 
		 		$result 	= $btc_model->sendToCoin($mycoin_addr, $myzc['username'], $myzc['mum'], $official_zcdz, $myzc['fee']);
				if (!$result || $result['status'] == 0) {
					$mo->rollback();
					$this->error($result['msg']);
				}else{
					$sendrs = $result['msg'];
				}
			}

			if ($sendrs) {
				$flag = 1;
				$arr = json_decode($sendrs, true);

				if (isset($arr['status']) && ($arr['status'] == 0)) {
					M('myzc')->where(array('id'=>trim($id)))->save(array('txid'=>$arr['data']));
					$flag = 0;
				}else{
					if(is_array($sendrs)){
						M('myzc')->where(array('id'=>trim($id)))->save(array('txid'=>$sendrs['my_txid']));
					}else{
						M('myzc')->where(array('id'=>trim($id)))->save(array('txid'=>$sendrs));
					}
				}
			} else {
				$flag = 0;
			}

			operation_log(UID, 1, "userid={$myzc['userid']}虚拟币{$coin}转出{$myzc['num']}审核");

			if (!$flag) {
				M()->rollback();
				M('myzc')->where(array('id' => trim($id)))->save(array('status' => 2,'endtime'=>time()));
				$this->error('钱包服务器转出币失败!');
			} else {
				M('myzc')->where(array('id' => trim($id)))->save(array('status' => 1,'endtime'=>time()));
				M()->commit();
				$this->success('转账成功！');
			}
		} else {
			M()->rollback();
			$this->error('转出失败!' . implode('|', $rs) . $myzc['fee']);
		}
	}

	//转出撤销
	public function myzcCancel($id = NULL, $password)
	{
		if (APP_DEMO) {
			$this->error('测试站暂时不能修改！');
		}

		$admin = M('Admin')->where(array('id' => UID))->find();
		if ($admin['password'] != md5($password)) {
			$this->error('密码错误!');
		}

		$myzc = M('Myzc')->where(array('id' => trim($id)))->find();
		if (!$myzc) {
			$this->error('撤销错误！');
		}
		//失败的订单可以撤销
		if ($myzc['status'] && $myzc['status'] != 2 && $myzc['txid'] != '') {
			$this->error('已经处理过！');
		}

		M()->startTrans();
		$rs = array();

		//返还币
		$rs[] = M('user_coin')->where(array('userid'=>$myzc['userid']))->setInc($myzc['coinname'],$myzc['num']);
		//返回手续费
		$coin = $myzc['coinname'];
		$official_zcdz = C('coin')[$coin]['zc_user'];
		if($myzc['txid'] && $myzc['fee'] > 0 && $official_zcdz && $official_zcdz != '0'){
			$fee_info = M('myzc_fee')->where(array('txid' => $myzc['txid']))->find();
			if($fee_info){
				$rs[] = M('user_coin')->where(array($coin . 'b' => $official_zcdz))->setDec($coin, $myzc['fee']);
			}
		}

		//转出订单设置为撤销
		$rs[] = M('Myzc')->where(array('id' => trim($id)))->save(array('status' => 3,'endtime'=>time()));

		operation_log(UID, 1, "虚拟币转出撤销id=".$id);

		if (check_arr($rs)) {
			M()->commit();
			$this->success('撤销成功！');
		}else{
			M()->rollback();
			$this->error('撤销失败!');
		}
	}
}
?>