<?php
namespace Api\Controller;

class IndexController extends BaseController
{
    // 修改密码
    public function editUserPass()
	{
		if (IS_POST) {
		    $oldPass = $this->inputData['oldPass'];
		    $newPass = $this->inputData['newPass'];
		    
			if ($this->user['password'] != md5($oldPass)) {
				$this->errorJson('原密码错误!');
			} else {
			    $res = M('PeAdmin')->save([
			        'id' => $this->user['id'],
				    'password' => md5($newPass),
				    'token' => ''
			    ]);
			    
			    if ($res) {
			        $this->successJson('操作成功');
			    } else {
			        $this->errorJson('操作失败!');
			    }
			}
		} else {
			$this->errorJson('请求拒绝！');
		}
	}
	
    // 收款账户
    public function payParams($name = NULL, $field = NULL, $status = NULL)
	{
		$where = array();
        if ($this->user['role'] == 2) {
	        $where['userid'] = $this->user['userid'];
	    }
	    
	    if ($this->user['role'] == 1) {
	        $child = M('PeAdmin')->where(['fid' => $this->user['id']])->field("userid")->select();
    	    $childID = array_column($child, 'userid');
    	    array_push($childID, $this->user['userid']);
    	    $where['userid'] = ['in', $childID];
	    }
		
		$where['channelid'] = ['like', "%{$this->inputData['channelid']}%"];
		$paytype_config = M('paytype_config')->where(['channelid'=>$this->inputData['channelid'], 'status'=>1])->find();
		if (!$paytype_config) {
		    $this->errorJson('支付方式不存在！');
		}
		
		if (isset($this->inputData['check_status'])) {
		    $where['check_status'] = $this->inputData['check_status'];
		}
		
		$currency = M('currencys')->where(['id' => $paytype_config['currencyid']])->find();
		
		$count = D('payparams_list')->where($where)->count();
	    $currentPage = $this->inputData['pageNum'] ? $this->inputData['pageNum'] : 1;
        $pageSize = $this->inputData['pageSize'] ? $this->inputData['pageSize'] : 15;
        $total = $count;
        $allPage = ceil($total / $pageSize);
        $offset = ($currentPage - 1) * $pageSize;
		$Page = new \Think\Page($count, $pageSize);
		$show = $Page->show();
		$list = M('payparams_list')->where($where)->order('id desc')->limit($offset . ',' . $pageSize)->select();

        $reuslt = [];
        foreach ($list as $key => $value) {
            $reuslt[$key] = $value;
            // if ($key > 2 && $key%2 == 0) {
            //     $reuslt[$key]['low_success_rate'] = '1';
            // } else {
            //     $reuslt[$key]['low_success_rate'] = '0';
            // }
            $reuslt[$key]['sales_name'] = $value['username'];
            $reuslt[$key]['currency'] = $currency['currency'] ? $currency['currency'] :'CNY';
            
            $peAdmin = M('PeAdmin')
    			->where(['userid' => $value['userid']])
    			->find();
            $reuslt[$key]['userid'] = $peAdmin['id'];
            
        }
        $this->successJson([
            'page' => [
                'total' => (int)$total,
                'all_page' => (int)$allPage,
                'current_page' => (int)$currentPage,
                'page_size' => (int)$pageSize,
            ],
            'list' => $reuslt
        ]);
	}
	
	//支付参数编辑
	public function payParamsEdit($id = NULL)
	{
	    if (!IS_POST) {
	        $this->errorJson('请求拒绝！');
	    }
	    
		if (empty($this->inputData)) {
			$this->errorJson('参数错误！');
		}
		$_POST = $this->inputData;
		
		// 过滤非法字符----------------S
		foreach ($_POST as $key => $value) {
			if (
			    $key!='signkey' 
			    && $key!='appsecret' 
			    && $key!='truename' 
			    && $key!='owner_photo' 
			    && $key!='owner_idcard_img1' 
			    && $key!='owner_idcard_img2' 
			    && checkstr($value)) {
				$this->errorJson(L('您输入的信息有误！'));
			}
		}
		// 过滤非法字符----------------E
        
        if($_POST['id']){
            if($_POST['userid']){
                $peAdmin = M('PeAdmin')
        			->where(['id' => $_POST['userid']])
        			->find();
        			
        		if(!$peAdmin){
        			$this->errorJson('该业务员ID的用户不存在！');
        		}
        		
    			$userinfo = M('User')->where(array('id'=>$peAdmin['userid']))->field('id,username,kyc_lv,idstate')->find();
        		if(!$userinfo){
        			$this->errorJson('该业务员ID的用户不存在！');
        		}
        		$_POST['username'] = $_POST['sales_name'] ? $_POST['sales_name'] : $userinfo['username'];
        		
        		if($userinfo['kyc_lv'] != 2 || $userinfo['idstate'] != 2){
        			$this->errorJson('通过高级实名认证的用户才能添加支付参数！');
        		}
    		}
        } else {
            if(!$_POST['userid']){
    			$this->errorJson('必须填写用户ID！');
    		}
    		
    		$peAdmin = M('PeAdmin')
    			->where(['id' => $_POST['userid']])
    			->find();
    			
    		if(!$peAdmin){
    			$this->errorJson('该业务员ID的用户不存在！');
    		}
    
    		if(!$_POST['channelid']){
    			$this->errorJson('必须选择账户类型！');
    		}
    
            if (!$_POST['appid'] || !$_POST['subject']) {
                $this->errorJson('必须填写账户参数！');
            }
            
    
    		$userinfo = M('User')->where(array('id'=>$peAdmin['userid']))->field('id,username,kyc_lv,idstate')->find();
    		if(!$userinfo){
    			$this->errorJson('该业务员ID的用户不存在！');
    		}
    		$_POST['username'] = $_POST['sales_name'] ? $_POST['sales_name'] : $userinfo['username'];
    		
    		if($userinfo['kyc_lv'] != 2 || $userinfo['idstate'] != 2){
    			$this->errorJson('通过高级实名认证的用户才能添加支付参数！');
    		}
    
    		//通过审核才能开启参数的判断
    		if(!$_POST['check_status'] && $_POST['status']){
    // 			$this->errorJson('审核通过,再设置为开启状态！');
    		}
    		
    		$_POST['low_money_count'] = 0;
    		$_POST['max_fail_count'] = 0;
    		$_POST['check_status'] = 0;
    		$_POST['status'] = 0;
    
    		//未指定的改为0
    		if(!$_POST['select_memberid'] || $_POST['select_memberid'] == ''){
    			$_POST['select_memberid'] = '0';
    		}
        }
        
		if ($_POST['appid']) {
		    $_POST['mch_id'] = $_POST['appid'];
            $_POST['signkey'] = $_POST['appid'];
            $_POST['mch_id'] = $_POST['appid'];
		}

		//获取是否为个人渠道
		$paytype_config = M('paytype_config')->where(['channelid'=>$_POST['channelid'], 'status'=>1])->find();
		if(!$paytype_config){
			$this->errorJson('未选择可用的账户类型！');
		}
		
		$_POST['is_personal'] = $paytype_config['is_personal'];
		$_POST['paytype'] = $paytype_config['paytype'];

		$_POST['addtime'] = time();

        // if ($_POST['status'] == 1) {
        //     $_POST['check_status'] = 1;
        // }else {
        //     $_POST['check_status'] = 0;
        // }
        
        if (isset($userinfo) && $userinfo) {
            $_POST['userid'] = $userinfo['id'];
        }
        
        // $_POST['is_defined'] = 1;
		if($_POST['id']){
			if (M('payparams_list')->save($_POST)) {
				$this->successJson('编辑成功！');
			} else {
				$this->errorJson('编辑失败！');
			}
		}else{
		    unset($_POST['token']);
            $_POST['appsecret'] = '';
			if (M('payparams_list')->add($_POST)) {
				$this->successJson('添加成功！');
			} else {
				$this->errorJson('添加失败！');
			}
		}
	}
	
	// 删除账户
	public function payParamsDel()
	{
	    if (!IS_POST) {
	        $this->errorJson('请求拒绝！');
	    }
	    
	    if ($this->inputData['id']) {
	        if (M('payparams_list')->where(['id' => $this->inputData['id']])->delete()) {
				$this->successJson('删除成功！');
			} else {
				$this->errorJson('删除失败！');
			}
	    } else {
	        $this->errorJson('删除失败！');
	    }
	}
	
	//支付类型列表
	public function payType()
	{
	    $currency = M('currencys')->where(['currency' => $this->inputData['currency']])->find();
        if (!$currency) {
           $this->errorJson('币种不存在！');
        }
        
		$where = array();
        $where['paytype'] = $this->inputData['paytype'];
        $where['currencyid'] = $currency['id'];
        
		if (isset($status)) {
			$where['status'] = $status;
		}
		
		if (isset($this->inputData['channel_type'])) {
			$where['channel_type'] = $this->inputData['channel_type'];
		}

		$count = M('paytype_config')->where($where)->count();
	    $currentPage = $this->inputData['pageNum'] ? $this->inputData['pageNum'] : 1;
        $pageSize = $this->inputData['pageSize'] ? $this->inputData['pageSize'] : 15;
        $total = $count;
        $allPage = ceil($total / $pageSize);
        $offset = ($currentPage - 1) * $pageSize;
		$Page = new \Think\Page($count, $pageSize);
		$show = $Page->show();
		$list = M('paytype_config')->where($where)->order('id desc')->limit($offset . ',' . $pageSize)->select();

        $payTypeList = C('PAYTYPES');
        $result = [];
        foreach ($list as $key => $value) {
            $result[$key] = $value;
            $currency = M('currencys')->where(['id' => $value['currencyid']])->find();
            $result[$key]['currency'] = $currency['currency'];
            foreach ($payTypeList as $person) {
                if ($person['id'] == $value['paytype']) {
                    $result[$key]['paytype_name'] = $person['name'];
                }
            }
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
	
	public function editPayType() {
        $currency = M('currencys')->where(['currency' => $this->inputData['currency']])->find();
        if (!$currency) {
           $this->errorJson('币种不存在！');
        }
	   
	   if ($this->inputData['channelid'] > 32767) {
	       $this->errorJson('请填写正确的ID！');
	   }
	    $this->inputData['currencyid'] = $currency['id'];
        if($this->inputData['id']){
        	if (M('paytype_config')->save($this->inputData)) {
        		$this->successJson('编辑成功！');
        	} else {
        		$this->errorJson('编辑失败！');
        	}
        }else{
            // $sql = M('paytype_config')->fetchSql(true)->add($this->inputData);
            // var_dump($sql);
        	if (M('paytype_config')->add($this->inputData)) {
        	    $channelid = $this->inputData['channelid'];
        	    $userSaveData = array(
                    'select_channelid' => array('exp', "CONCAT(IFNULL(select_channelid,''), ',{$channelid}')")
                );
        	    M('User')->where('1=1')->save($userSaveData);
        		$this->successJson('添加成功！');
        	} else {
        		$this->errorJson('添加失败！');
        	}
        }
	    $this->successJson();
	}
	
	public function paytypes() {
	    $list = C('PAYTYPES');
	    $result = [];
	    foreach ($list as $value) {
	        if ($value['currency'] == $this->inputData['currency']) {
	            $result[] = $value;
	        }
	    }
	    
	    $this->successJson($result);
	}
	
	public function payTypeList ()
	{
	    $where = array();
        $where['paytype'] = $this->inputData['paytype'];
		$list = M('paytype_config')->where($where)->order('id desc')->select();

        $payTypeList = C('PAYTYPES');
        $result = [];
        foreach ($list as $key => $value) {
            $result[$key] = $value;
            foreach ($payTypeList as $person) {
                if ($person['id'] == $value['paytype']) {
                    $result[$key]['paytype_name'] = $person['name'];
                    $result[$key]['currency'] = $person['currency'];
                }
            }
        }
		$this->successJson($result);
	}
	
	// C2C卖出记录
	public function exportOrderList()
	{
	    ini_set('memory_limit','10240M');
	    $time_type = isset($this->inputData['time_type']) ? $this->inputData['time_type'] : 1; // 1 根据订单创建时间 2 订单完成时间
	    $where = array();
	    
	    if ($this->inputData['otype']) {
            $where['otype'] = (int)$this->inputData['otype']; // 订单类型
        }
        
        if ($this->inputData['status']) {
            $where['status'] = (int)$this->inputData['status']; // 订单状态
        }
        
	    // 默认搜索用户
		if ($this->user['role'] == 2) {
	        $where['userid'] = $this->user['userid'];
	    }
	    
	    if ($this->user['role'] == 1) {
	        $child = M('PeAdmin')->where(['fid' => $this->user['id']])->field("userid")->select();
    	    $childID = array_column($child, 'userid');
    	    array_push($childID, $this->user['userid']);
    	    $where['userid'] = ['in', $childID];
	    }
        
        if (!$this->inputData['starttime'] || !$this->inputData['endtime']) {
            $this->errorJson('请选择时间范围！');
        }
        
        $start_time = strtotime($this->inputData['starttime']);
		$end_time = strtotime($this->inputData['endtime']);
		if ($time_type == 1) {
		    $where['addtime'] = ['between',[$start_time,$end_time]];
		} else {
		    $where['endtime'] = ['between',[$start_time,$end_time]];
		}
		
		// 币种
		$currency = isset($this->inputData['currency']) ? $this->inputData['currency'] : '';
		if ($currency) {
		    $currencyData = M('currencys')->where(['currency' => $currency])->find();
		    if (!$currencyData) {
		        $this->errorJson('币种不支持！');
		    }
		    
		    if ($this->inputData['otype'] == 1) {
		        $where['type'] = strtolower($currency);
		    } else {
		        $channelData = M('paytype_config')->where(['currencyid' => $currencyData['id']])->field("channelid")->select();
        		$channelids = array_column($channelData, 'channelid');
        		$where['pay_channelid'] = ['in', $channelids];
		    }
		    
		}
		$list = D('ExchangeOrder')->where($where)->select();
		
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
        	$list[$k]['currency'] = $v['type'];
        	$list[$k]['pay_bank'] = $v['bankcard'];
        	$list[$k]['channel_title'] = '';
        	if ($v['pay_channelid']) {
        	    $list[$k]['channel_title'] = M('paytype_config')->where(['channelid' => $v['pay_channelid']])->find()['channel_title'];
        	}
        	if ($v['pay_channelid']) {
        	    $paytype_config = M('paytype_config')->where(['channelid' => $v['pay_channelid']])->find();
        	    $list[$k]['channel_title'] = $paytype_config['channel_title'];
        	    $list[$k]['currency'] = M('currencys')->where(['id' => $paytype_config['currencyid']])->find()['currency'];
        	}
        	
        	if ($v['status'] == 99) {
        	    $list[$k]['orderid'] = '';
        	    $list[$k]['out_order_id'] = '';
        	}
		}
		$this->successJson($list);
		
	}
	
	// C2C卖出记录
	public function mytx()
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
		    $where['out_order_id'] = array('like', "%{$this->inputData['out_order_id']}%");
		}
		
		if ($this->inputData['return_order_id']) {
		    $where['return_order_id'] = array('like', "%{$this->inputData['return_order_id']}%");
		}
		
	    // 用户搜索
	    if ($this->user['role'] == 1) { // 代理商
    	    $child = M('PeAdmin')->where(['fid' => $this->user['id']])->field("id,userid")->select();
    	    $childID = array_column($child, 'id');
    	    $childUserID = array_column($child, 'userid');
    	    array_push($childUserID, $this->user['userid']);
    	    
    	    if ($this->inputData['userid']) {
    	        if (in_array($this->inputData['userid'], $childID)) {
    	            $userid = M('PeAdmin')->where(['id' => $this->inputData['userid']])->field("userid")->find();
    		        $where['userid'] = $userid['userid'];
    	        } else {
    	            $where['userid'] = 0;
    	        }
	        } else { // 默认查下自己下所有业务员
	            $where['userid'] = ['in', $childUserID];
	        }
	    } else if ($this->user['role'] == 2) { // 业务员只能查看自己的订单
	        $where['userid'] = $this->user['userid'];
	    } else {
	        if ($this->inputData['userid']) {
	            $searchUser = M('PeAdmin')->where(['id' => $this->inputData['userid']])->find();
	            
	            if ($searchUser['role'] == 1) {
	                $child = M('PeAdmin')->where(['fid' => $searchUser['id']])->field("userid")->select();
            	    $childID = array_column($child, 'userid');
            	    array_push($childID, $this->user['userid']);
            	    
            	    $where['userid'] = ['in', $childID];
	            } else {
	                $where['userid'] = $searchUser['userid'];
	            }
	        }
	    }
	    
// 		if ($this->inputData['userid']) {
// 		    if ($this->user['role'] != 2 && in_array($this->inputData['userid'], $childID)) {
// 		        $userid = M('PeAdmin')->where(['id' => $this->inputData['userid']])->field("userid")->find();
// 		        $where['userid'] = $userid['userid'];
// 		    }
// 		}
		
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
		    
		    $channelData = M('paytype_config')->where(['currencyid' => $currencyData['id']])->field("channelid")->select();
    		$channelids = array_column($channelData, 'channelid');
    		$where['pay_channelid'] = ['in', $channelids];
		}
		
		//今日开始时间
		$today_start = strtotime(date("Y-m-d 00:00:00"));
		$now_time = time();
		//Log::record('mycz add函数 111111111', Log::INFO);
		
        //今日订单数量
        $today_order_num = D('ExchangeOrder')->where($where)->where(['addtime'=>['between',[$today_start,$now_time]]])->count();
        //待收款订单
        $pending_receive_order =  D('ExchangeOrder')->where($where)->where(array('otype'=>2,'status'=>1))->count();
        //待付款订单
        $pending_payment_order =  D('ExchangeOrder')->where($where)->where(array('otype'=>1,'status'=>1))->count();
        //超时订单
        $time_out_order = D('ExchangeOrder')->where($where)->where(array('status'=>8))->count();
        
		$count = D('ExchangeOrder')->where($where)->count();
	    $currentPage = $this->inputData['pageNum'] ? $this->inputData['pageNum'] : 1;
        $pageSize = $this->inputData['pageSize'] ? $this->inputData['pageSize'] : 15;
        $total = $count;
        $allPage = ceil($total / $pageSize);
        $offset = ($currentPage - 1) * $pageSize;
		$Page = new \Think\Page($count, $pageSize);
		$show = $Page->show();
		
		$list = D('ExchangeOrder')->where($where)->order('id desc')->limit($offset . ',' . $pageSize)->select();

		/** 检查设置条件 **/
        $exchange_config = M('exchange_config')->where(array('id' => 1))->find();
        $config_price = $exchange_config['mytx_uprice'];

		foreach ($list as $k => $v) {
			$matchs ='';
			$aids = '';
			$peAdmin = M('PeAdmin')->where(array('userid' => $v['userid']))->find();
			$list[$k]['username'] = $peAdmin['nickname'];
			$aids = M('exchange_agent')->where(array('id' => $v['aid']))->field("id,aid")->find();
			
			$list[$k]['agent'] = M('PeAdmin')->where(array('id' => $peAdmin['fid']))->getField('nickname');
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
        	$list[$k]['currency'] = 'MMK';
        	$list[$k]['pay_bank'] = $v['bankcard'];
        	$list[$k]['channel_title'] = '';
        	if ($v['pay_channelid']) {
        	    $paytype_config = M('paytype_config')->where(['channelid' => $v['pay_channelid']])->find();
        	    $list[$k]['channel_title'] = $paytype_config['channel_title'];
        	    $list[$k]['currency'] = M('currencys')->where(['id' => $paytype_config['currencyid']])->find()['currency'];
        	}
        	
        	if ($v['status'] == 99) {
        	    $list[$k]['orderid'] = '';
        	    $list[$k]['out_order_id'] = '';
        	}
        	
        	
			if (!isset($v['pay_proof'])) {
			    $list[$k]['pay_proof'] = '';
			}
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
            'report' => [
                'today_all_order' => $today_order_num,
                'pending_receive_order' => $pending_receive_order,
                'pending_payment_order' => $pending_payment_order,
                'time_out_order' => $time_out_order,
                'auto_sell_status' => $auto_sell_status,
                'auto_buy_status' => $auto_buy_status,
            ],
            'list' => $list
        ]);
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
		
		
	    // 用户搜索
	    if ($this->user['role'] == 1) { // 代理商
    	    $where['userid'] = $this->user['id'];
	    } else if ($this->user['role'] == 2) { // 业务员只能查看自己的订单
	        $where['userid'] = 0;
	    } else {
	        if ($this->inputData['userid']) {
	            $searchUser = M('PeAdmin')->where(['id' => $this->inputData['userid']])->find();
	            
	            if ($searchUser['role'] == 1) {
	               // $child = M('PeAdmin')->where(['fid' => $searchUser['id']])->field("userid")->select();
            	   // $childID = array_column($child, 'userid');
            	   // array_push($childID, $this->user['userid']);
            	    
            	    $where['userid'] = $searchUser['id'];
	            } else {
	                $where['userid'] = 0;
	            }
	        }
	    }
// 		if ($this->inputData['userid']) {
// 		    if ($this->user['role'] != 2 && in_array($this->inputData['userid'], $childID)) {
// 		        $userid = M('PeAdmin')->where(['id' => $this->inputData['userid']])->field("userid")->find();
// 		        $where['userid'] = $userid['userid'];
// 		    }
// 		}
		
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
            'report' => [
                'today_all_order' => $today_order_num,
                'pending_receive_order' => $pending_receive_order,
                'pending_payment_order' => $pending_payment_order,
                'time_out_order' => $time_out_order,
                'auto_sell_status' => $auto_sell_status,
                'auto_buy_status' => $auto_buy_status,
            ],
            'list' => $list
        ]);
	}
	
	// 手动匹配订单
	public function paymentMatchOrder()
	{
	    $orderInfo = D('ExchangeOrder')->where(array(
                'orderid' => $this->inputData['orderid'],
            ))->order('id asc')->find();
	    
	    if (!$orderInfo) {
	        $this->errorJson('订单不存在！');
	    }
	    
	    if ($orderInfo['status'] == 3) {
	        $this->errorJson('订单已完成！');
	    }
	    
	    $returnOrderInfo = D('ExchangeOrder')->where(array(
                'out_order_id' => 'WT' . $this->inputData['return_order_id'],
            ))->order('id asc')->find();
	    if (!$returnOrderInfo) {
	        $this->errorJson('唯一标识错误！');
	    }
	    
        $paymentRecord = M('exchange_payment_record')->where([
            'return_order_id' => $this->inputData['return_order_id']
        ])->order('id asc')->find();
        
        // 判断订单时间，订单时间必须在支付时间之前
        if ($orderInfo['addtime'] >= $paymentRecord['addtime']) {
            $this->errorJson('订单时间与支付时间不匹配！');
        }
	    
	    // 如果订单金额 != 实际支付金额，则修改订单金额为实际支付金额
	    if ($orderInfo['real_amount'] != $paymentRecord['real_amount']) { 
	       // $this->errorJson('订单金额大于实际支付金额！');
	       $rs = D('ExchangeOrder')->where(array(
                'orderid' => $this->inputData['orderid'],
            ))->save([
                'userid' => $paymentRecord['userid'],
                'num' => $paymentRecord['real_amount'],
                'mum' => $paymentRecord['real_amount'],
                'real_amount' => $paymentRecord['real_amount'],
            ]);
            
            if (!$rs) {
                $this->errorJson('操作金额失败！');
            }
            
            $orderInfo['userid'] = $paymentRecord['userid'];
            $orderInfo['num'] = $paymentRecord['real_amount'];
            $orderInfo['mum'] = $paymentRecord['real_amount'];
            $orderInfo['real_amount'] = $paymentRecord['real_amount'];
	    }
	    
	    if ($orderInfo['pay_channelid'] != $returnOrderInfo['pay_channelid']) {
	        $this->errorJson('通道不匹配！');
	    }
	    
	    $payExchangeController = A('Pay/PayExchange');
	    //设置支付平台的订单状态
	    $orderInfo['status'] = 2;
        $res = $payExchangeController->confirmC2COrderWithApiOrderInfo($orderInfo);
        if($res === true ){
            // 确认成功则删除无用订单记录
            D('ExchangeOrder')->where(array(
                'out_order_id' => 'WT' . $this->inputData['return_order_id'],
            ))->order('id asc')->delete();
            
            M('exchange_payment_record')->where([
	            'return_order_id' => $this->inputData['return_order_id']
            ])->save([
                'status'    => 1,
                'dealtime'  => time(),
            ]);
            
            $this->successJson();
        }else{
            $this->errorJson('操作失败！', $res);
        }
	}
	
	// 驳回代付订单
	public function rejectOrder()
	{
	    $orderInfo = D('ExchangeOrder')->where(array(
                'orderid' => $this->inputData['orderid'],
            ))->order('id asc')->find();
	    
	    if (!$orderInfo) {
	        $this->errorJson('订单不存在！');
	    }
	    
	    if ($orderInfo['status'] == 2 || $orderInfo['status'] ==3){
	        $this->errorJson('订单不可操作！');
	    }
	    
	    $payExchangeController = A('Pay/PayExchange');
	    $transOrder = $orderInfo;
	    $transOrder['remarks'] = $this->inputData['content'];
        $res = $payExchangeController->setPayPlatformOrderStatus($transOrder, 3);
        if($res === true ){
            D('ExchangeOrder')->where(array(
                'orderid' => $this->inputData['orderid'],
            ))->order('id asc')->save([
                'status' => 9,
                'remarks' => $orderInfo['remarks'] . ' - 驳回。' . '原因：' . $this->inputData['content']
            ]);
            $this->successJson();
        }else{
            $this->errorJson('操作失败！' . $res['msg'], $res);
        }
	}
	
	// 操作订单接口
	public function orderConfirm()
	{
	    $orderInfo = D('ExchangeOrder')->where(array(
                'orderid' => $this->inputData['orderid'],
            ))->order('id asc')->find();
	    
	    if (!$orderInfo) {
	        $this->errorJson('订单不存在！');
	    }
	    
	    $return_order_id = $this->inputData['return_order_id'];
	    if ($return_order_id) { // 判断是否为业务员
	        if ($this->user['role'] == 2){
	            $this->errorJson('无权限操作！');
	        }
	        $orderInfo['return_order_id'] = $return_order_id;
	        D('ExchangeOrder')->where(array(
                'orderid' => $this->inputData['orderid'],
            ))->order('id asc')->save(['return_order_id' => $return_order_id]);
	    }
	    
	    $noReturnOrderidArr = ['thb'];
	    if (!in_array($orderInfo['type'], $noReturnOrderidArr)) {
	        if ($orderInfo['otype'] == 2 && !$orderInfo['return_order_id']) {
    	        $this->errorJson('订单不存在唯一标识！');
    	    }
	    }
	    
	    
	    $payExchangeController = A('Pay/PayExchange');
	    //设置支付平台的订单状态
	    $orderInfo['status'] = 2;
        $res = $payExchangeController->confirmC2COrderWithApiOrderInfo($orderInfo, $this->inputData['bankcard']);
        if($res === true ){
            if (!in_array($orderInfo['type'], $noReturnOrderidArr)) {
                M('exchange_payment_record')->where([
    	            'return_order_id' => $orderInfo['return_order_id']
                ])->save([
                    'status'    => 1,
                    'dealtime'  => time(),
                ]);
            }
            
            
    	    if ($this->inputData['pay_proof']) {
    	        D('ExchangeOrder')->where(array(
                    'orderid' => $this->inputData['orderid'],
                ))->order('id asc')->save(['pay_proof' => $this->inputData['pay_proof']]);
    	    }
            
            $this->successJson();
        }else{
            $this->errorJson('操作失败！' . $res['msg'], $res);
        }
	}
	
	// 操作订单接口
	public function orderSupplyConfirm()
	{
	     $orderInfo = D('ExchangeOrder')->where(array(
                'orderid' => $this->inputData['orderid'],
            ))->order('id asc')->find();
	    
	    if (!$orderInfo) {
	        $this->errorJson('订单不存在！');
	    }
	    
	    if ($orderInfo['status'] < 3) {
	        $this->errorJson('订单状态不允许补发！');
	    }
	    
	    $payExchangeController = A('Pay/PayExchange');
	    if ($orderInfo['status'] == 3) {
	        $res = $payExchangeController->setPayPlatformOrderStatus($orderInfo, 2);
	    } else if ($orderInfo['status'] == 9) {
	        $res = $payExchangeController->setPayPlatformOrderStatus($orderInfo, 3);
	    } else {
	        $this->errorJson('订单状态不允许补发！');
	    }
	    
	    if($res === true ){
            $this->successJson();
        }else{
            $this->errorJson('操作失败！' . $res['msg'], $res);
        }
	}
	
	public function editOrderConfig()
	{
	    if ($this->inputData['type'] != 1 && $this->inputData['type'] != 2) {
	        $this->errorJson('未知操作！');
	    }
	    
        $saveData = [];
        $saveUser = [];
        
	    $saveData['id'] = $this->user['id'];
	    $saveUser['id'] = $this->user['userid'];
	    if ($this->inputData['type'] == 2) {
	        $saveData['auto_sell_status'] = $this->inputData['status'];
	        $saveUser['auto_c2c_sell_status'] = $this->inputData['status'];
	    } else {
	        $saveData['auto_buy_status'] = $this->inputData['status'];
	        $saveUser['auto_c2c_buy_status'] = $this->inputData['status'];
	    }
	    
	    // 业务员修改对应的用户数据
	    if ($this->user['role'] == 2) {
	        if (M('user')->save($saveUser)) {
				$this->successJson();
			} else {
				$this->errorJson('操作失败！');
			}
	    } else {
	        // 代理直接修改数据
	        if (M('PeAdmin')->save($saveData)) {
				$this->successJson();
			} else {
				$this->errorJson('操作失败！');
			}
	    }
	    
	}
	
	// 查询自动结算设置
	public function autoSettleInfo()
	{
	    $peAdmin = M('PeAdmin')->where(['id' => $this->user['id']])->find();
	    $result = [
	        'is_auto_settle' => $peAdmin['is_auto_settle'],
	        'settle_address' => $peAdmin['settle_address'] ? $peAdmin['settle_address'] : '',
	        'settle_rate_loss' => $peAdmin['settle_rate_loss'],
        ];
	    $this->successJson($result);
	}
	
	// 保存自动结算设置
	public function saveAutoSettleInfo()
	{
	    $data = [
	        'is_auto_settle' => $this->inputData['is_auto_settle'],
	        'settle_address' => $this->inputData['settle_address'],
	        'settle_rate_loss' => $this->inputData['settle_rate_loss'],
        ];
        
	    $res = M('PeAdmin')->where(['id' => $this->user['id']])->save($data);
	    if (!$res) {
	        $this->errorJson('操作失败！');
	    }
	    
	    $this->successJson();
	}
	
	// 结算列表（供应商平台 商户结算）
	public function merchartSettles() {
	    $where = [];
	    $where['is_team'] = 2;
	    $allChild =  $this->getAllSubordinates($this->user['id']);
	    if ($this->inputData['currency']) {
	        $where['currency'] = $this->inputData['currency'];
	    }
	    
	    if ($this->inputData['user_id']) {
	        if (in_array($this->inputData['user_id'], $allChild)) {
	            $where['user_id'] = $this->inputData['user_id'];
	        } else {
	            $where['user_id'] = '';
	        }
	        
	    } else {
	        $where['user_id'] = ['in', array_column($allChild, 'id')];
	    }
	    
	    if ($this->inputData['status']) {
	        $where['status'] = $this->inputData['status'];
	    }
	    
	    if ($this->inputData['starttime'] && $this->inputData['endtime']) {
	        $where['addtime'] =['between', [$this->inputData['starttime'],$this->inputData['endtime']]];
	    }
	    
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
	
	// 确认商户结算
	public function editMerchartSettles () {
	    if (!$this->inputData['id'] || !$this->inputData['status']) {
	        $this->errorJson('缺少参数！');
	    }
	    
	    $settle = M('Settles')->where(['id' => $this->inputData['id']])->find();
	    if (!$settle) {
	        $this->errorJson('未知操作！');
	    }
	    
	    if ($this->inputData['status'] == 4 && (!$this->inputData['actual_rate'])) {
	        $this->errorJson('缺少参数！');
	    }
	    
        if ($this->inputData['status'] == 4 && $this->inputData['actual_rate'] <= 0) {
           $this->errorJson('请输入正确的汇率！');
        }
	    
	    $peAdmin = M('PeAdmin')->where(['id' => $settle['user_id']])->find();
        $userid = $peAdmin['userid'];
        $userCoinData = M('UserCoinSettle')->where(['userid' => $userid])->find();
	    if ($this->inputData['status'] == 4) {
	        if ($settle['type'] == 3) {  // 结算
                $rs_coin = M('UserCoin')->save([
                    'id' => $userCoinData['id'],
                    strtolower($settle['currency']) . 'd' => $userCoinData[strtolower($settle['currency']) . 'd'] - $settle['amount']
                ]);
                if (!$rs_coin) {
                    $this->errorJson('操作失败！');
                }
            }
	    } else if ($this->inputData['status'] == 3) {
	        $rs_coin = M('UserCoinSettle')->save([
                'id' => $userCoinData['id'],
                strtolower($settle['currency']) . 'd' => $userCoinData[strtolower($settle['currency']) . 'd'] - $settle['amount'],
                strtolower($settle['currency']) => $userCoinData[strtolower($settle['currency'])] + $settle['amount'],
            ]);
	    }
	    
	    $saveData = $this->inputData;
	    
	    $res = M('Settles')->save($saveData);
	    $res2 = M('user_freeze_log')->where(['settleid' => $this->inputData['id']])->save(['status' => 2]);
	    
	    if ($res && $res2) {
	        $this->successJson();
	    } else {
	        $this->errorJson('操作失败！');
	    }
	}
	
	// 结算列表（管理员 平台结算）
	public function adminSettles() {
	    $where = [];
	    $where['is_team'] = 0;
	    if ($this->inputData['currency']) {
	        $where['currency'] = $this->inputData['currency'];
	    }
	    
	    if ($this->inputData['user_id']) {
	        $where['user_id'] = $this->inputData['user_id'];
	    }
	    
	    if ($this->inputData['status']) {
	        $where['status'] = $this->inputData['status'];
	    }
	    
	    if ($this->inputData['type']) {
	        $where['type'] = $this->inputData['type'];
	    }
	    
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
		    if ($value['type'] == 1) {
		        $result[$key]['title'] = '增加保证金';
		    } else if ($value['type'] == 2) {
		        $result[$key]['title'] = '减少保证金';
		    } else if ($value['type'] == 3) {
		        $result[$key]['title'] = '结算';
		    } else if ($value['type'] == 5) {
		        $result[$key]['title'] = '手动罚款';
		    } else if ($value['type'] == 6) {
		        $result[$key]['title'] = '自动罚款';
		    } else if ($value['type'] == 7) {
		        $result[$key]['title'] = '底薪发放';
		    } else if ($value['type'] == 8) {
		        $result[$key]['title'] = '保底系统费';
		    }
		}
		
		$pending_settles = M('Settles')->where($where)->where(['status' => 1, 'is_team' => 0, 'type' => 3])->count();
		$pending_add_margin_settles = M('Settles')->where($where)->where(['status' => 1, 'is_team' => 0, 'type' => 1])->count();
		$pending_reduce_margin_settles = M('Settles')->where($where)->where(['status' => 1, 'is_team' => 0, 'type' => 2])->count();
	    $this->successJson([
            'page' => [
                'total' => (int)$total,
                'all_page' => (int)$allPage,
                'current_page' => (int)$currentPage,
                'page_size' => (int)$pageSize,
            ],
            'report' => [
                "pending_settles" => $pending_settles,
                "pending_add_margin_settles" => $pending_add_margin_settles,
                "pending_reduce_margin_settles" => $pending_reduce_margin_settles
            ],
            'list' => $result
        ]);
	}
	
	// 管理员 / 代理商 操作结算（平台结算）
	public function editSettles () {
	    if (!$this->inputData['id'] || !$this->inputData['status']) {
	        $this->errorJson('缺少参数！');
	    }
	    
	    $userCoin = M('UserCoinSettle')->where(['usderid' => $this->user['userid']])->find();
	    
	    $settle = M('Settles')->where(['id' => $this->inputData['id']])->find();
	    if (!$settle) {
	        $this->errorJson('未知操作！');
	    }
	    
	    if ($this->inputData['status'] == 4 && (!$this->inputData['actual_rate'])) {
	        $this->errorJson('缺少参数！');
	    }
	    
        if ($this->inputData['actual_rate'] <= 0) {
           $this->errorJson('请输入正确的汇率！');
        }
	    
	    
	    if ($this->inputData['status'] == 4) {
	        $peAdmin = M('PeAdmin')->where(['id' => $settle['user_id']])->find();
	        $userid = $peAdmin['userid'];
	        $userCoin = M('UserCoinSettle')->where(['userid' => $userid])->find();
	        $userCoinMargin = M('UserCoinMargin')->where(['userid' => $userid])->find();
	        $userCoinData = M('UserCoin')->where(['userid' => $userid])->find();
	        if ($settle['type'] == 1) { // 增加保证金
                $rs = M('UserCoinMargin')->save([
                    'id' => $userCoinMargin['id'],
                    strtolower($settle['currency']) => $userCoinMargin[strtolower($settle['currency'])] + $settle['amount']
                ]);
                $rs_coin = M('UserCoin')->save([
                    'id' => $userCoinData['id'],
                    strtolower($settle['currency']) => $userCoinData[strtolower($settle['currency'])] + $settle['amount']
                ]);
                if (!$rs) {
                    $this->errorJson('操作失败！');
                }
            } else if ($settle['type'] == 2) { // 减少保证金
                $rs = M('UserCoinMargin')->save([
                    'id' => $userCoinMargin['id'],
                    strtolower($settle['currency']) => $userCoinMargin[strtolower($settle['currency'])] - $settle['amount']
                ]);
                if (!$rs) {
                    $this->errorJson('操作失败！');
                }
            } else if ($settle['type'] == 3) {  // 结算
                $rs = M('UserCoinSettle')->save([
                    'id' => $userCoin['id'],
                    strtolower($settle['currency']) => $userCoin[strtolower($settle['currency'])] - $settle['amount']
                ]);
                $rs_coin = M('UserCoin')->save([
                    'id' => $userCoinData['id'],
                    strtolower($settle['currency']) => $userCoinData[strtolower($settle['currency'])] + $settle['amount']
                ]);
                if (!$rs) {
                    $this->errorJson('操作失败！');
                }
            }
            
            // 如果是团队普通结算，需要把金额添加给代理
	       // if ($settle['is_team'] == 1 && $settle['type'] == 3) {
	       //     $fPeAdmin = M('PeAdmin')->where(['id' => $peAdmin['fid']])->find();
	            
	       //     $fUserCoin = M('UserCoinSettle')->where(['userid' => $fPeAdmin['userid']])->find();
	       //     $rs = M('UserCoinSettle')->save([
        //             'id' => $fUserCoin['id'],
        //             strtolower($settle['currency']) => $fUserCoin[strtolower($settle['currency'])] + $settle['amount']
        //         ]);
        //         if (!$rs) {
        //             $this->errorJson('操作失败！');
        //         }
	       // }
	    }
	    
	    $saveData = $this->inputData;
	    $saveData['loss_amount'] = ($settle['amount'] / $this->inputData['actual_rate']) - $settle['received_amount'];
	    
	    $res = M('Settles')->save($saveData);
	    
	    if ($res) {
	        $this->successJson();
	    } else {
	        $this->errorJson('操作失败！');
	    }
	}
	
	
	// 业务员结算列表 （团队结算）
	public function saleSettles () {
	    $where = [];
	    $where['is_team'] = 1;
	    $where['user_id'] = $this->user['id'];
	    if ($this->inputData['currency']) {
	        $where['currency'] = $this->inputData['currency'];
	    }
	    
	    if ($this->inputData['status']) {
	        $where['status'] = $this->inputData['status'];
	    }
	    
	    if ($this->inputData['type']) {
	        $where['type'] = $this->inputData['type'];
	    }
	    
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
		    if ($value['type'] == 1) {
		        $result[$key]['title'] = '增加保证金';
		    } else if ($value['type'] == 2) {
		        $result[$key]['title'] = '减少保证金';
		    } else if ($value['type'] == 3) {
		        $result[$key]['title'] = '结算';
		    } else if ($value['type'] == 5) {
		        $result[$key]['title'] = '手动罚款';
		    } else if ($value['type'] == 6) {
		        $result[$key]['title'] = '自动罚款';
		    } else if ($value['type'] == 7) {
		        $result[$key]['title'] = '底薪发放';
		    } else if ($value['type'] == 8) {
		        $result[$key]['title'] = '保底系统费';
		    }
		}
		
		// 数据
		$currencyList = M('currencys')->select();
		$userCoin = M('UserCoinSettle')->where(['userid' => $this->user['userid']])->find();
		$userCoinMargin = M('UserCoinMargin')->where(['userid' => $this->user['userid']])->find();
		$reportData = [];
		foreach ($currencyList as $key => $value) {
		    $reportData[$value['currency']] = [
		        'amount' => $userCoin[strtolower($value['currency'])],
                'margin_amount' => $userCoinMargin[strtolower($value['currency'])],
	        ];
		}
		
		$walletData = [
		    'amount' => 0,
		    'address' => [],
	    ];
	    
	    $walletResult = $this->getWalletInfo($this->user['wallet_id']);
	    if ($walletResult) {
	        $walletData = $walletResult;
	    }
		
	    $this->successJson([
            'page' => [
                'total' => (int)$total,
                'all_page' => (int)$allPage,
                'current_page' => (int)$currentPage,
                'page_size' => (int)$pageSize,
            ],
            'wallet' => $walletData,
            'report' => $reportData,
            'list' => $result
        ]);
	}
	
	// 业务员申请结算（团队结算）
	public function addTeamSettles () {
	    if (!$this->inputData['currency'] || !$this->inputData['type'] || !$this->inputData['amount']) {
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
	    if ($this->inputData['is_currency'] == 1) {
	        $received_currency = $this->inputData['currency'];
	        $received_amount = $this->inputData['amount'];
	    } else {
	        $received_currency = 'USDT';
	        $received_amount = $this->inputData['received_amount'] ? $this->inputData['received_amount'] : 0;
	        if ($received_amount > 0) {
	            $rate = $this->inputData['amount'] / $received_amount;
	        }
	        
	        $fPeAdmin = M('PeAdmin')->where(['id' => $this->user['fid']])->find();
    	    $walletResult = $this->walletTransfer($this->user['wallet_id'], $fPeAdmin['wallet_id'], $received_amount, $this->inputData['pin']);
    	    if ($walletResult !== true) {
    	        $this->errorJson($walletResult);
    	    }
	    }
	    
	    $res = M('Settles')->add([
	        'is_team' => 1,
	        'user_id' => $this->user['id'],
	        'type' => $this->inputData['type'],
	        'orderid' => date('YmdHis') . rand(100000, 999999),
	        'currency' => $this->inputData['currency'],
	        'amount' => $this->inputData['amount'],
	        'received_currency' => $received_currency,
	        'received_amount' => $received_amount,
	        'rate' => $rate,
	        'addtime' => time(),
        ]);
        
        if ($res) {
            $this->successJson();
        } else {
            $this->errorJson('申请失败！');
        }
	}
	
	
	// 代理商平台结算列表 （平台结算）
	public function agentSettles () {
	    $where = [];
	    $where['is_team'] = 0;
	    $where['user_id'] = $this->user['id'];
	    if ($this->inputData['currency']) {
	        $where['currency'] = $this->inputData['currency'];
	    }
	    
	    if ($this->inputData['status']) {
	        $where['status'] = $this->inputData['status'];
	    }
	    
	    if ($this->inputData['type']) {
	        $where['type'] = $this->inputData['type'];
	    }
	    
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
		    if ($value['type'] == 1) {
		        $result[$key]['title'] = '增加保证金';
		    } else if ($value['type'] == 2) {
		        $result[$key]['title'] = '减少保证金';
		    } else if ($value['type'] == 3) {
		        $result[$key]['title'] = '结算';
		    } else if ($value['type'] == 5) {
		        $result[$key]['title'] = '手动罚款';
		    } else if ($value['type'] == 6) {
		        $result[$key]['title'] = '自动罚款';
		    } else if ($value['type'] == 7) {
		        $result[$key]['title'] = '底薪发放';
		    } else if ($value['type'] == 8) {
		        $result[$key]['title'] = '保底系统费';
		    }
		}
		
		// 数据
		$currencyList = M('currencys')->select();
		$userCoin = M('UserCoinSettle')->where(['userid' => $this->user['userid']])->find();
		$userCoinMargin = M('UserCoinMargin')->where(['userid' => $this->user['userid']])->find();
		$reportData = [];
		foreach ($currencyList as $key => $value) {
		    $reportData[$value['currency']] = [
		        'amount' => $userCoin[strtolower($value['currency'])],
                'margin_amount' => $userCoinMargin[strtolower($value['currency'])],
	        ];
		}
		
		$walletData = [
		    'amount' => 0,
		    'address' => [],
	    ];
	    
	    $walletResult = $this->getWalletInfo($this->user['wallet_id']);
	    if ($walletResult) {
	        $walletData = $walletResult;
	    }
		
	    $this->successJson([
            'page' => [
                'total' => (int)$total,
                'all_page' => (int)$allPage,
                'current_page' => (int)$currentPage,
                'page_size' => (int)$pageSize,
            ],
            'wallet' => $walletData,
            'report' => $reportData,
            'list' => $result
        ]);
	}
	
	// 代理尚申请结算 （平台）
	public function addSettles () {
	    if (!$this->inputData['currency'] || !$this->inputData['type'] || !$this->inputData['amount']) {
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
	    if ($this->inputData['is_currency'] == 1) {
	        $received_currency = $this->inputData['currency'];
	        $received_amount = $this->inputData['amount'];
	    } else {
	        $received_currency = 'USDT';
	        $received_amount = $this->inputData['received_amount'] ? $this->inputData['received_amount'] : 0;
	        if ($received_amount > 0) {
	            $rate = $this->inputData['amount'] / $received_amount;
	        }
	        
	        $fPeAdmin = M('PeAdmin')->where(['id' => $this->adminid])->find();
    	    $walletResult = $this->walletTransfer($this->user['wallet_id'], $fPeAdmin['wallet_id'], $received_amount, $this->inputData['pin']);
    	    if ($walletResult !== true) {
    	        $this->errorJson($walletResult);
    	    }
	    }
	    
	    $res = M('Settles')->add([
	        'is_team' => 0,
	        'user_id' => $this->user['id'],
	        'type' => $this->inputData['type'],
	        'orderid' => date('YmdHis') . rand(100000, 999999),
	        'currency' => $this->inputData['currency'],
	        'amount' => $this->inputData['amount'],
	        'received_currency' => $received_currency,
	        'received_amount' => $received_amount,
	        'rate' => $rate,
	        'addtime' => time(),
        ]);
        
        if ($res) {
            $this->successJson();
        } else {
            $this->errorJson('申请失败！');
        }
	    
	}
	
	// 代理商团队结算列表
	public function agentTeamSettles() {
	    $where = [];
	    $where['is_team'] = 1;
	    $child = M('PeAdmin')->where(['fid' => $this->user['id']])->field("id")->select();
	    $childID = array_column($child, 'id');
	    $where['user_id'] = ['in', $childID];
	    if ($this->inputData['currency']) {
	        $where['currency'] = $this->inputData['currency'];
	    }
	    
	    if ($this->inputData['status']) {
	        $where['status'] = $this->inputData['status'];
	    }
	    
	    if ($this->inputData['type']) {
	        $where['type'] = $this->inputData['type'];
	    }
	    
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
		    if ($value['type'] == 1) {
		        $result[$key]['title'] = '增加保证金';
		    } else if ($value['type'] == 2) {
		        $result[$key]['title'] = '减少保证金';
		    } else if ($value['type'] == 3) {
		        $result[$key]['title'] = '结算';
		    } else if ($value['type'] == 5) {
		        $result[$key]['title'] = '手动罚款';
		    } else if ($value['type'] == 6) {
		        $result[$key]['title'] = '自动罚款';
		    } else if ($value['type'] == 7) {
		        $result[$key]['title'] = '底薪发放';
		    } else if ($value['type'] == 8) {
		        $result[$key]['title'] = '保底系统费';
		    }
		}
		
		$pending_settles = M('Settles')->where($where)->where(['status' => 1, 'type' => 3])->count();
		$pending_add_margin_settles = M('Settles')->where($where)->where(['status' => 1, 'type' => 1])->count();
		$pending_reduce_margin_settles = M('Settles')->where($where)->where(['status' => 1, 'type' => 2])->count();
	    $this->successJson([
            'page' => [
                'total' => (int)$total,
                'all_page' => (int)$allPage,
                'current_page' => (int)$currentPage,
                'page_size' => (int)$pageSize,
            ],
            'report' => [
                'pending_settles' => $pending_settles,
                'pending_add_margin_settles' => $pending_add_margin_settles,
                'pending_reduce_margin_settles' => $pending_reduce_margin_settles,
            ],
            'list' => $result
        ]);
	}
	
	// 账户列表
	public function accountList () {
	    $id = $this->inputData['id'];
	    $peAdmin = M('PeAdmin')->where(['id' => $id])->find();
	    
	    if (!$peAdmin) {
	        $this->errorJson('未知用户！');
	    }
	    
	    $where = [];
	    // 默认搜索用户
		if ($peAdmin['role'] == 2) {
	        $where['userid'] = $peAdmin['userid'];
	    }
	    
	    if ($peAdmin['role'] == 1) {
	        $child = M('PeAdmin')->where(['fid' => $peAdmin['id']])->field("userid")->select();
    	    $childID = array_column($child, 'userid');
    	    array_push($childID, $peAdmin['userid']);
    	    $where['userid'] = ['in', $childID];
	    }
	    
	    $list = M('payparams_list')->where($where)->order('id desc')->select();
	    foreach ($list as $key => $value) {
	        $paytype_config = M('paytype_config')->where(['channelid' => $value['channelid']])->find();
    	    $list[$key]['channel_title'] = $paytype_config['channel_title'];
	    }
	    
	    $this->successJson($list);
	}
	
	// 结算列表
	public function users() {
	    $where['role'] = 2;
	    $where['fid'] = $this->user['id'];
		if (isset($this->inputData['wallet_id'])) {
		    $where['_complex'] = [
		        'id' => $this->inputData['wallet_id'],
		        'wallet_id' => $this->inputData['wallet_id'],
		        '_logic' => 'or'
	        ];
		}
		
	    $count = M('PeAdmin')->where($where)->count();
	    $total = $count;
        $currentPage = $this->inputData['pageNum'] ? $this->inputData['pageNum'] : 1;
        $pageSize = $this->inputData['pageSize'] ? $this->inputData['pageSize'] : 15;
        $total = $count;
        $allPage = ceil($total / $pageSize);
        $offset = ($currentPage - 1) * $pageSize;
		$Page = new \Think\Page($count, $pageSize);
		$show = $Page->show();
		
		$list = M('PeAdmin')->where($where)->order('id desc')->field(['id', 'userid', 'nickname', 'username', 'last_login_time', 'status', 'tg_account', 'receive_commission', 'payment_commission', 'punish_commission', 'extraction_commission', 'receive_fee', 'payment_fee', 'wallet_id', 'channel_fees'])->limit($offset . ',' . $pageSize)->select();
		
		
		$result = [];
		$currencys = M('currencys')->order('id desc')->select();
		foreach ($list as $key => $value) {
		    $user = M('User')->where(['id' => $value['userid']])->find();
		    $value['auto_c2c_sell_status'] = 0;
		    $value['auto_c2c_buy_status'] = 0;
		    
		    if ($user) {
		        $value['auto_c2c_sell_status'] = $user['auto_c2c_sell_status'];
		        $value['auto_c2c_buy_status'] = $user['auto_c2c_buy_status'];
		    }
		    
		    $result[$key] = $value;
		    if ($result[$key]['channel_fees']) {
		        $result[$key]['channel_fees'] = unserialize($result[$key]['channel_fees']);
		    }
		    
		    $userAmount = [];
		    $userSettle = M('UserCoinSettle')->where(['userid' => $value['userid']])->find();
		    $userMargin = M('UserCoinMargin')->where(['userid' => $value['userid']])->find();
		    // 币种及保证金
		    foreach ($currencys as $currency) {
		        $userAmount[$currency['currency']] = [
		            'settle' => isset($userSettle[strtolower($currency['currency'])]) ? $userSettle[strtolower($currency['currency'])] : 0,
		            'margin' => isset($userMargin[strtolower($currency['currency'])]) ? $userMargin[strtolower($currency['currency'])] : 0,
	            ];
		    }
		    $result[$key]['amount'] = $userAmount;
		    
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
		
// 		$list = [
// 		    [
// 		        'id'                            => 1,                           // 
// 		        'username'                      => 'feng001',                   // 用户名
// 		        'nickname'                      => '风1',                       // 昵称
// 	            'tg_account'                    => 1,                           // TG
// 	            'receive_commission'            => '1',                         // 代收佣金
// 	            'payment_commission'            => '3',                         // 代付佣金
// 	            'punish_commission'             => '5',                         // 处罚佣金
// 	            'extraction_commission'         => '2',                         // 抽取佣金
// 	            'last_login_time'               => '1746562625',                // 最后登陆时间
// 	            'status'                        => 1,                           // 状态 1 正常 2 冻结
// 	            'user_id'                       => 1,                           // 外部userid
// 	        ]
// 	    ];
	    
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
	
	public function addUser () {
	    if ($this->inputData['wallet_id']) {
	        if (M('PeAdmin')->where(array('wallet_id' => $this->inputData['wallet_id']))->find()) {
    	        $this->errorJson('代理商已存在！');
    	    }
	    } else {
	        if (M('PeAdmin')->where(array('username' => $this->inputData['username']))->find()) {
    	        $this->errorJson('代理商已存在！');
    	    }
	    }
	    
	    if ($this->inputData['password']) {
            $this->inputData['password'] = md5($this->inputData['password']);
        } else {
            $this->inputData['password'] = md5(123456);
        }
        $this->inputData['username'] = $this->inputData['username'] ? $this->inputData['username'] : '';
        $this->inputData['moble'] = '';
        $this->inputData['sort'] = 0;
        $this->inputData['addtime'] = time();
        $this->inputData['last_login_time'] = 0;
        $this->inputData['last_login_ip'] = '';
        $this->inputData['endtime'] = 0;
        $this->inputData['status'] = 1;
        $this->inputData['google_key'] = '';
        $this->inputData['role'] = 2;
        $this->inputData['token'] = '';
        $this->inputData['fid'] = $this->user['id'];
        
        $paytype_config = M('paytype_config')->field(['id', 'channelid'])->select();
        $channelids = array_column($paytype_config, 'channelid');
        $addUserData = [];
        $addUserData['username'] = $this->inputData['username'] ? $this->inputData['username'] : $this->inputData['nickname'];
        $addUserData['enname'] = $this->inputData['username'] ? $this->inputData['username'] : $this->inputData['nickname'];
        $addUserData['qz'] = '86';
        $addUserData['mobile'] = $this->inputData['tg_account'] ? $this->inputData['tg_account'] : $this->inputData['wallet_id'];
        $addUserData['mobiletime'] = time();
        $addUserData['password'] = md5($this->inputData['password']);
        $addUserData['tpwdsetting'] = '1';
        $addUserData['paypassword'] = md5($this->inputData['password']);
        $addUserData['invit_1'] = '0';
        $addUserData['invit_2'] = '0';
        $addUserData['invit_3'] = '0';
        $addUserData['kyc_lv'] = 2;
        $addUserData['truename'] = $this->inputData['nickname'];
        $addUserData['idcard'] = $this->inputData['tg_account'] ? $this->inputData['tg_account'] : $this->inputData['wallet_id'];
        $addUserData['idtype'] = 1;
        $addUserData['idnationality'] = get_city_ip();
        $addUserData['idstate'] = 2;
        $addUserData['idcardinfo'] = '';
        $addUserData['idimg1'] = '5d011c7b89c3b.jpg';
        $addUserData['idimg2'] = '5d011c7f4176b.jpg';
        $addUserData['idimg3'] = '5d011cb2dca03.jpg';
        $addUserData['addip'] = get_client_ip();
        $addUserData['addr'] = get_city_ip();
        $addUserData['addtime'] = time();
        $addUserData['status'] = 1;
        $addUserData['invit'] = tradenoa();
        $addUserData['otcuser'] = $this->inputData['username'] ? $this->inputData['username'] : $this->inputData['nickname'];
        $addUserData['apikey'] = get_random_str();
        // $addUserData['lv'] = 0;
        // $addUserData['backstage'] = 0;
        $addUserData['auto_c2c_sell_status'] = $this->inputData['auto_c2c_sell_status'];
        $addUserData['auto_c2c_buy_status'] = $this->inputData['auto_c2c_buy_status'];
        $addUserData['cancal_c2c_level'] = '1';
        $addUserData['select_channelid'] = implode(',', $channelids);
        // $addUserData['auto_c2c_time'] = '';
        // $addUserData['last_exchange_time'] = '';
        // $addUserData['all_money'] = '';
        // $addUserData[''] = '';
        // $addUserData[''] = '';
        // $addUserData[''] = '';
        // $addUserData[''] = '';
        
		$userID = M('User')->add($addUserData);
// 		var_dump($userM->getDbError());
		$this->inputData['userid'] = $userID;
		$rsAdmin = M('PeAdmin')->add($this->inputData);
		$user_coin = array('userid' => $userID);
		// 创建用户数字资产档案
		$rsCoin = M('UserCoin')->add($user_coin);
		$rsMarginCoin = M('UserCoinMargin')->add($user_coin);
		$rsSettleCoin = M('UserCoinSettle')->add($user_coin);
		if ($userID && $rsAdmin && $rsCoin && $rsMarginCoin && $rsSettleCoin) {
			$this->successJson('添加成功！');
		} else {
			$this->errorJson('添加失败！');
		}
	}
	
	// 批量添加成功
	public function addBatchUser () {
	    $addList = $this->inputData['addList'] ? $this->inputData['addList'] : [];
	    
	    if ($addList && count($addList) > 0) {
	        $paytype_config = M('paytype_config')->field(['id', 'channelid'])->select();
            $channelids = array_column($paytype_config, 'channelid');
        
	        foreach ($addList as $key => $value) {
	            if ($value['wallet_id']) {
	                if (M('PeAdmin')->where(array('wallet_id' => $value['wallet_id']))->find()) {
            	        $this->errorJson($value['wallet_id'] . '代理商已存在！');
            	    }
	            } else {
	                if (M('PeAdmin')->where(array('username' => $value['username']))->find()) {
            	        $this->errorJson($value['username'] . '代理商已存在！');
            	    }
	            }
	            
        	    
        	    $addData = [];
        	    $addData['username'] = $value['username'] ? $value['username'] : '';
        	    $addData['nickname'] = $value['nickname'] ? $value['nickname'] : '';
                $addData['tg_account'] = $value['tg_account'];
                $addData['wallet_id'] = $value['wallet_id'];
        	    $addData['password'] = md5(123456);
        	    $addData['moble'] = '';
                $addData['sort'] = 0;
                $addData['addtime'] = time();
                $addData['last_login_time'] = 0;
                $addData['last_login_ip'] = '';
                $addData['endtime'] = 0;
                $addData['status'] = 1;
                $addData['google_key'] = '';
                $addData['role'] = 2;
                $addData['token'] = '';
                $addData['fid'] = $this->user['id'];
                
                $addUserData = [];
                $addUserData['username'] = $value['username'] ? $value['username'] : $value['nickname'];
                $addUserData['enname'] = $value['username'] ? $value['username'] : $value['nickname'];
                $addUserData['qz'] = '86';
                $addUserData['mobile'] = $value['tg_account'] ? $value['tg_account'] : $value['wallet_id'];
                $addUserData['mobiletime'] = time();
                $addUserData['password'] = md5(123456);
                $addUserData['tpwdsetting'] = '1';
                $addUserData['paypassword'] = md5(123456);
                $addUserData['invit_1'] = '0';
                $addUserData['invit_2'] = '0';
                $addUserData['invit_3'] = '0';
                $addUserData['kyc_lv'] = 2;
                $addUserData['truename'] = $value['username'] ? $value['username'] : $value['nickname'];
                $addUserData['idcard'] = $value['tg_account'] ? $value['tg_account'] : $value['wallet_id'];
                $addUserData['idtype'] = 1;
                $addUserData['idnationality'] = get_city_ip();
                $addUserData['idstate'] = 2;
                $addUserData['idcardinfo'] = '';
                $addUserData['idimg1'] = '5d011c7b89c3b.jpg';
                $addUserData['idimg2'] = '5d011c7f4176b.jpg';
                $addUserData['idimg3'] = '5d011cb2dca03.jpg';
                $addUserData['addip'] = get_client_ip();
                $addUserData['addr'] = get_city_ip();
                $addUserData['addtime'] = time();
                $addUserData['status'] = 1;
                $addUserData['invit'] = tradenoa();
                $addUserData['otcuser'] = $value['username'] ? $value['username'] : $value['nickname'];
                $addUserData['apikey'] = get_random_str();
                $addUserData['auto_c2c_sell_status'] = '0';
                $addUserData['auto_c2c_buy_status'] = '0';
                $addUserData['cancal_c2c_level'] = '1';
                $addUserData['select_channelid'] = implode(',', $channelids);
                
                $userID = M('User')->add($addUserData);
                $addData['userid'] = $userID;
        		$rsAdmin = M('PeAdmin')->add($addData);
        		$user_coin = array('userid' => $userID);
        		// 创建用户数字资产档案
        		$rsCoin = M('UserCoin')->add($user_coin);
        		$rsMarginCoin = M('UserCoinMargin')->add($user_coin);
        		$rsSettleCoin = M('UserCoinSettle')->add($user_coin);
        // 		var_dump($rsAdmin);exit;
        		if (!$userID || !$rsAdmin || !$rsCoin || !$rsMarginCoin || !$rsSettleCoin) {
        			$this->errorJson('添加失败！');
        		}
	        }
	        
	        $this->successJson('添加成功！');
	    } else {
	        $this->errorJson('请填写数据！');
	    }
	}
	
	public function editUser () {
	    if($this->inputData['id']){
	        if ($this->inputData['password']) {
	            $this->inputData['password'] = md5($this->inputData['password']);
	        }
	        $peAdmin = M('PeAdmin')->where(['id' => $this->inputData['id']])->find();
	        if (!$peAdmin) {
	            $this->errorJson('账号不存在！');
	        }
	        $saveUser = [
	            'id' => $peAdmin['userid'],
	            'auto_c2c_sell_status' => $this->inputData['auto_c2c_sell_status'],
	            'auto_c2c_buy_status' => $this->inputData['auto_c2c_buy_status'],
            ];
            
            if ($this->inputData['channel_fees']) {
	            $this->inputData['channel_fees'] = serialize($this->inputData['channel_fees']);
	        }
            unset($this->inputData['token']);
            unset($this->inputData['auto_c2c_sell_status']);
            unset($this->inputData['auto_c2c_buy_status']);
			if (M('PeAdmin')->save($this->inputData) || M('user')->save($saveUser)) {
				$this->successJson('编辑成功！');
			} else {
				$this->errorJson('编辑失败！');
			}
		}else{
		    $this->errorJson('编辑失败！');
		}
	}
	
	public function config () {
	    $user = M('User')->where(['id' => $this->user['userid']])->find();
	    $list = [
	        'api' => [
	            'key' => $this->user['apikey'],
	            'test-key' => $this->user['apikey'],
            ],
            'ip' => [
                'callback' => $this->user['ip'] ? $this->user['ip'] : '',
                'payment' =>  $this->user['monitor_ip'] ? $this->user['monitor_ip'] : '',
            ],
	        
	    ];
	    
	   // if ($this->user['role'] == 3) {
	   //     $list['ip']['payment'] = M('PeAdmin')->where(['id' => 1])->find()['payment_submit_ip'];
	   // }
	    
	    $currency = $this->inputData['currency'];
	    if ($currency) {
	        $where = array();
	        $currencyInfo = M('currencys')->where(['currency' => $currency])->find();
            $where['currencyid'] = $currencyInfo['id'];
            
	        $count = M('paytype_config')->where($where)->count();
    	    $currentPage = $this->inputData['pageNum'] ? $this->inputData['pageNum'] : 1;
            $pageSize = $this->inputData['pageSize'] ? $this->inputData['pageSize'] : 15;
            $total = $count;
            $allPage = ceil($total / $pageSize);
            $offset = ($currentPage - 1) * $pageSize;
    		$Page = new \Think\Page($count, $pageSize);
    		$show = $Page->show();
    		$payparams = M('paytype_config')->where($where)->order('id desc')->limit($offset . ',' . $pageSize)->select();
    		
    		$result = [];
    		foreach ($payparams as $key => $value) {
    		    $result[$key] = [
    		        'channelid' => $value['channelid'],
    		        'channel_name' => $value['channel_title'],
    		        'receive_commission'            => $this->user['receive_commission'],                         // 代收佣金
    	            'payment_commission'            => $this->user['payment_commission'],                         // 代付佣金
    	            'punish_commission'             => $this->user['punish_commission'],                         // 处罚佣金
	            ];
    		}
	        $list['fee'] = [
	            $currency => [
	                'page' => [
                        'total' => (int)$total,
                        'all_page' => (int)$allPage,
                        'current_page' => (int)$currentPage,
                        'page_size' => (int)$pageSize,
                    ],
	                'list' => $result
	                
                ]
	        ];
	    }
		
	    $this->successJson($list);
	}
	
	// 编辑IP
	public function editIP () {
	    if ($this->inputData['type'] == 2) { // 监控IP
	       // if (M('ExchangeConfig')->save([
    	   //     'id' => 1,
    	   //     'payment_submit_ip' => $this->inputData['ip'],
        //     ])) {
        //         $this->successJson();
        //     } else {
        //         $this->errorJson();
        //     }
            if (M('PeAdmin')->save([
    	        'id' => $this->user['id'],
    	        'monitor_ip' => $this->inputData['ip'],
            ])) {
                $this->successJson();
            } else {
                $this->errorJson();
            }
	    } else {
	        if (M('PeAdmin')->save([
    	        'id' => $this->user['id'],
    	        'ip' => $this->inputData['ip'],
            ])) {
                $this->successJson();
            } else {
                $this->errorJson();
            }
	    }
	}
	
	public function dashboard () {
	    $today_start = strtotime(date("Y-m-d 00:00:00"));
	    $yesterday = date("Y-m-d", strtotime("-1 day"));
	    $yes_start = strtotime($yesterday . ' 00:00:00');
	    $yes_end = strtotime($yesterday . ' 23:59:59');
	    $month_start = strtotime(date("Y-m-d H:i:s",mktime(0, 0 , 0,date("m"),1,date("Y"))));
	    $lastmonth_start = strtotime(date("Y-m-d H:i:s",mktime(0, 0 , 0,date("m")-1,1,date("Y"))));
        $lastmonth_end = strtotime(date("Y-m-d H:i:s",mktime(23,59,59,date("m") ,0,date("Y"))));
		$now_time = time();
	    $before24 = $now_time - 86400;
	    
		$orderWhere = [];
		$peAdmWhere = [];
		$settleWhere = [];
		$payparamsWhere = [];
		$payparamsWhere['status'] = 1;
		// 默认搜索用户
		if ($this->user['role'] == 2) {
	        $orderWhere['userid'] = $this->user['userid'];
	        $payparamsWhere['userid'] = $this->user['userid'];
	        $settleWhere['userid'] = $this->user['id'];
	    }
	    
	    if ($this->user['role'] == 1) {
	        $child = M('PeAdmin')->where(['fid' => $this->user['id']])->field("userid")->select();
    	    $childID = array_column($child, 'userid');
    	    $childPID = array_column($child, 'id');
    	    array_push($childID, $this->user['userid']);
    	    array_push($childPID, $this->user['id']);
    	    $orderWhere['userid'] = ['in', $childID];
    	    
	        $peAdmWhere['fid'] = $this->user['id'];
	        
	        $settleWhere['userid'] = ['in', $childPID];
	        $payparamsWhere['userid'] = ['in', $childID];
	    }
	    
	    // 币种搜索
	    $currency = M('currencys')->where(['currency' => $this->inputData['currency']])->find();
	    $paytype_config = M('paytype_config')->where(['currencyid' => $currency['id']])->select();
        $channelids = array_column($paytype_config, 'channelid');
	    $orderWhere['pay_channelid'] = ['in', $channelids];
	    
	    $order_num = D('ExchangeOrder')->where($orderWhere)->where(['addtime'=>['between',[$today_start,$now_time]]])->count();
	    $order_success_num = D('ExchangeOrder')->where($orderWhere)->where(['status' => 3, 'addtime'=>['between',[$today_start,$now_time]]])->count();
	    $order_rate = 0;
	    $order_rate_growth = 0;
	    if ($order_num > 0) {
	        $order_rate = round(($order_success_num / $order_num), 2);
	        
	        $order_num_yes = D('ExchangeOrder')->where($orderWhere)->where(['addtime'=>['between',[$yes_start,$yes_end]]])->count();
	        $order_success_num_yes = D('ExchangeOrder')->where($orderWhere)->where(['status' => 3, 'addtime'=>['between',[$yes_start,$yes_end]]])->count();
	        
	        $order_rate_yes = round(($order_success_num_yes / $order_num_yes), 2);
	        $order_rate_growth = $order_rate - $order_rate_yes;
	    }
	    
	    $active_user = 0;
	    $active_user_yes = 0;
	    foreach ($paytype_config as $key => $value) {
	        if ($value['channel_type'] == 2) {
	            $channelid = $value['channelid'];
	            $payparamsWhere['_string'] = "FIND_IN_SET('{$channelid}', channelid) > 0";
    	        $active_user += M('payparams_list')->where($payparamsWhere)->count();
    	        $active_user_yes += M('payparams_list')->where($payparamsWhere)->where(['addtime'=>['between',[$lastmonth_start,$lastmonth_end]]])->count();
	        }
	        
	    }
	    $active_user_change = $active_user /  - $active_user_yes;
	    
	    $all_user = M('PeAdmin')->where($peAdmWhere)->count();
	    $all_user_yes = M('PeAdmin')->where($peAdmWhere)->where(['addtime'=>['between',[$lastmonth_start,$lastmonth_end]]])->count();
	    $all_user_change = $all_user - $all_user_yes;
	    
	    $receive_amount = D('ExchangeOrder')->where($orderWhere)->where(array('otype'=>2,'status'=>3))->where(['addtime'=>['between',[$month_start,$now_time]]])->sum('mum') * 1;
	    $receive_amount_yes = D('ExchangeOrder')->where($orderWhere)->where(array('otype'=>2,'status'=>3))->where(['addtime'=>['between',[$lastmonth_start,$lastmonth_end]]])->sum('mum') * 1;
	    $receive_amount_change = round(($receive_amount - $receive_amount_yes) / $receive_amount);
	    $payment_amount = D('ExchangeOrder')->where($orderWhere)->where(array('otype'=>1,'status'=>3))->where(['addtime'=>['between',[$month_start,$now_time]]])->sum('mum') * 1;
	    $payment_amount_yes = D('ExchangeOrder')->where($orderWhere)->where(array('otype'=>1,'status'=>3))->where(['addtime'=>['between',[$lastmonth_start,$lastmonth_end]]])->sum('mum') * 1;
	    $payment_amount_change = round(($payment_amount - $payment_amount_yes) / $payment_amount);
	    
	    $unsettled_amount = M('UserCoinSettle')->where(['userid' => $this->user['userid']])->find();
	    
	    $last_login_time = $this->user['last_login_time'];
	    $last_operate_time = $this->user['last_login_time'];
	    
	    if ($this->user['is_system'] == 1) {
	        $fee_amount = 0;
	    } else {
	        $fee_amount = M('exchange_report')->where(['userid' => $this->user['userid'], 'currency' => $this->inputData['currency']])->sum('receive_amount') + 
	            M('exchange_report')->where(['userid' => $this->user['userid'], 'currency' => $this->inputData['currency']])->sum('payment_amount') - 
	            M('exchange_report')->where(['userid' => $this->user['userid'], 'currency' => $this->inputData['currency']])->sum('punish_amount');
	    }
	    
	    $agent_user = M('PeAdmin')->where(['role' => 1])->count();
	    $agent_change = M('PeAdmin')->where(['role' => 1, 'addtime'=>['between',[$month_start,$now_time]]])->count();
	    
	    $agent_settled_pending = M('Settles')->where($settleWhere)->where(['status' => 1, 'addtime'=>['between',[$today_start,$now_time]]])->count();
	    $agent_settled_pending_yes = M('Settles')->where($settleWhere)->where(['status' => 1, 'addtime'=>['between',[$yes_start,$yes_end]]])->count();
	    $last_agent_settled_pending = $agent_settled_pending - $agent_settled_pending_yes;
	    
		$list = [
            'order_num'                     => $order_success_num,                     // 实时成单数量
            'order_rate'                    => $order_rate,                             // 实时成单率
            'order_rate_growth'             => $order_rate_growth,                     // 实时成单率增长比
            
            'active_user'                   => $active_user,                        // 活跃账户
            'active_user_change'            => $active_user_change,                        // 活跃账户变化(月)
            
		    'all_user'                      => $all_user,                       // 总账户（客户总数）
		    'all_user_change'               => $all_user_change,                        // 总账户（客户总数）变化
		    
            'receive_amount'                => $receive_amount,                      // 代收金额
            'receive_amount_change'         => $receive_amount_change,                      // 代收金额(增加百分比)
            'payment_amount'                => $payment_amount,                       // 代付金额
            'payment_amount_change'         => $payment_amount_change,                      // 代付金额(增加百分比)
            
            'unsettled_amount'              => $unsettled_amount[strtolower($this->inputData['currency'])],                       // 未结算金额
            
            'last_login_time'               => $this->user['last_login_time'],                // 上次登陆时间
            'last_operate_time'             => $this->user['last_login_time'],                // 上次操作时间
            
            'fee_amount'                    => $fee_amount,                     // 手续费金额 / 佣金
            
            
            'agent_user'                    => $agent_user,                 // 代理商用户
            'agent_change'                  => $agent_change,                         // 代理商用户变化（月）
            
            
            'agent_settled_pending'         => $agent_settled_pending,                        // 代理商结算待处理
            'last_agent_settled_pending'    => $last_agent_settled_pending,                        // 上次代理商结算待处理
            
            'today_amount'                  => '1000',                      // 今日收益
            'today_amount_change'           => '16.5',                       // 今日收益变化
            
            
            
            
            
            'last_active_user'              => '25',                        // 上次对比活跃账户
            'team_user'                     => '30',                        // 团队账户
            'settled_time'                  => '1746562625',                // 上次结算时间
            'settled_amount'                => '200',                       // 上次结算金额
            
            
	    ];
	    $this->successJson($list);
	}
	
	public function dashboard_reposts ()
	{
	    $today_start = strtotime(date("Y-m-d 00:00:00"));
	    $yesterday = date("Y-m-d", strtotime("-1 day"));
	    $yes_start = strtotime($yesterday . ' 00:00:00');
	    $yes_end = strtotime($yesterday . ' 23:59:59');
	    $qd_start = strtotime(date("Y-m-d", strtotime("-2 day")) . ' 00:00:00');
	    $qd_end = strtotime(date("Y-m-d", strtotime("-2 day")) . ' 23:59:59');
	    $month_start = strtotime(date("Y-m-d H:i:s",mktime(0, 0 , 0,date("m"),1,date("Y"))));
	    $lastmonth_start = strtotime(date("Y-m-d H:i:s",mktime(0, 0 , 0,date("m")-1,1,date("Y"))));
        $lastmonth_end = strtotime(date("Y-m-d H:i:s",mktime(23,59,59,date("m") ,0,date("Y"))));
		$now_time = time();
	    $before24 = $now_time - 86400;
	    
        $where = [];
        // 默认搜索用户
		if ($this->user['role'] == 2) {
	        $where['userid'] = $this->user['userid'];
	    }
	    
	    if ($this->user['role'] == 1) {
	        $child = M('PeAdmin')->where(['fid' => $this->user['id']])->field("userid")->select();
    	    $childID = array_column($child, 'userid');
    	    array_push($childID, $this->user['userid']);
    	    $where['userid'] = ['in', $childID];
	    }
	    $currencys = M('currencys')->order('id desc')->select();
	    $result = [];
	    foreach ($currencys as $key => $value) {
	        $paytype_config = M('paytype_config')->where(['currencyid' => $value['id']])->select();
	        $channelids = array_column($paytype_config, 'channelid');
    	    $where['pay_channelid'] = ['in', $channelids];
    	    
	        // 今天
	        $todayReceive = D('ExchangeOrder')->where($where)->where(array('otype'=>2,'status'=>3))->where(['addtime'=>['between',[$today_start,$now_time]]])->sum('mum') * 1;
	        $todayReceive_yes = D('ExchangeOrder')->where($where)->where(array('otype'=>2,'status'=>3))->where(['addtime'=>['between',[$yes_start,$yes_end]]])->sum('mum') * 1;
	        $today_export = M('exchange_report')->where(['userid' => $this->user['userid'], 'currency' => $value['currency'], ['addtime'=>['between',[$today_start,$now_time]]]])->find();
	        $today_receive_status = 0;
	        if ($todayReceive_yes > $todayReceive) {
	            $today_receive_status = -1;
	        } else {
	            $today_receive_status = 1;
	        }
	        
	        $todayPayment = D('ExchangeOrder')->where($where)->where(array('otype'=>1,'status'=>3))->where(['addtime'=>['between',[$today_start,$now_time]]])->sum('mum') * 1;
	        $todayPayment_yes = D('ExchangeOrder')->where($where)->where(array('otype'=>1,'status'=>3))->where(['addtime'=>['between',[$yes_start,$yes_end]]])->sum('mum') * 1;
	        $today_payment_status = 0;
	        if ($todayPayment_yes > $todayPayment) {
	            $today_payment_status = -1;
	        } else {
	            $today_payment_status = 1;
	        }
	        
	        
	        // 昨天
	        $yesReceive = D('ExchangeOrder')->where($where)->where(array('otype'=>2,'status'=>3))->where(['addtime'=>['between',[$yes_start,$yes_end]]])->sum('mum') * 1;
	        $yesReceive_yes = D('ExchangeOrder')->where($where)->where(array('otype'=>2,'status'=>3))->where(['addtime'=>['between',[$qd_start,$qd_end]]])->sum('mum') * 1;
	        $yes_export = M('exchange_report')->where(['userid' => $this->user['userid'], 'currency' => $value['currency'], ['addtime'=>['between',[$yes_start,$yes_end]]]])->find();
	        $yes_receive_status = 0;
	        if ($yesReceive_yes > $yesReceive) {
	            $yes_receive_status = -1;
	        } else {
	            $yes_receive_status = 1;
	        }
	        
	        $yesPayment = D('ExchangeOrder')->where($where)->where(array('otype'=>1,'status'=>3))->where(['addtime'=>['between',[$yes_start,$yes_end]]])->sum('mum') * 1;
	        $yesPayment_yes = D('ExchangeOrder')->where($where)->where(array('otype'=>1,'status'=>3))->where(['addtime'=>['between',[$qd_start,$qd_end]]])->sum('mum') * 1;
	        $yes_payment_status = 0;
	        if ($yesPayment_yes > $yesPayment) {
	            $yes_payment_status = -1;
	        } else {
	            $yes_payment_status = 1;
	        }
	        
	        
	        // 本月
	        $monthReceive = D('ExchangeOrder')->where($where)->where(array('otype'=>2,'status'=>3))->where(['addtime'=>['between',[$month_start,$now_time]]])->sum('mum') * 1;
	        $monthReceive_yes = D('ExchangeOrder')->where($where)->where(array('otype'=>2,'status'=>3))->where(['addtime'=>['between',[$lastmonth_start,$lastmonth_end]]])->sum('mum') * 1;
	        $month_receive_status = 0;
	        if ($monthReceive_yes > $monthReceive) {
	            $month_receive_status = -1;
	        } else {
	            $month_receive_status = 1;
	        }
	        
	        $monthPayment = D('ExchangeOrder')->where($where)->where(array('otype'=>1,'status'=>3))->where(['addtime'=>['between',[$month_start,$now_time]]])->sum('mum') * 1;
	        $monthPayment_yes = D('ExchangeOrder')->where($where)->where(array('otype'=>1,'status'=>3))->where(['addtime'=>['between',[$lastmonth_start,$lastmonth_end]]])->sum('mum') * 1;
	        $month_payment_status = 0;
	        if ($monthPayment_yes > $monthPayment) {
	            $month_payment_status = -1;
	        } else {
	            $month_payment_status = 1;
	        }
	        
	        
	        // 总计
	        $allReceive = D('ExchangeOrder')->where($where)->where(array('otype'=>2,'status'=>3))->sum('mum') * 1;
	        $allReceive_yes = D('ExchangeOrder')->where($where)->where(array('otype'=>2,'status'=>3))->sum('mum') * 1;
	        $all_receive_status = 0;
	        if ($allReceive_yes > $allReceive) {
	            $all_receive_status = -1;
	        } else {
	            $all_receive_status = 1;
	        }
	        
	        $allPayment = D('ExchangeOrder')->where($where)->where(array('otype'=>1,'status'=>3))->sum('mum') * 1;
	        $allPayment_yes = D('ExchangeOrder')->where($where)->where(array('otype'=>1,'status'=>3))->sum('mum') * 1;
	        $all_payment_status = 0;
	        if ($allPayment_yes > $allPayment) {
	            $all_payment_status = -1;
	        } else {
	            $all_payment_status = 1;
	        }
	        $result[$value['currency']] = [
		        'today_receive_amount'          => $todayReceive, //今日代收金额
		        'today_receive_fee'             => $today_export['receive_amount'], //今日代收手续费
		        'today_receive_status'          => $today_receive_status,     //今日代收情况（1 增长，0 持平， -1 减少）
		        'today_paymeny_amount'          => $todayPayment, //今日代付金额
		        'today_paymeny_fee'             => $today_export['payment_amount'], //今日代付手续费
		        'today_paymeny_status'          => $today_payment_status,     //今日代付情况（1 增长，0 持平， -1 减少）
		        
		        'yesterday_receive_amount'          => $yesReceive, //今日代收金额
		        'yesterday_receive_fee'             => $yes_export['receive_amount'], //今日代收手续费
		        'yesterday_receive_status'          => $yes_receive_status,     //今日代收情况（1 增长，0 持平， -1 减少）
		        'yesterday_paymeny_amount'          => '100', //今日代付金额
		        'yesterday_paymeny_fee'             => $yes_export['payment_amount'], //今日代付手续费
		        'yesterday_paymeny_status'          => $yes_payment_status,     //今日代付情况（1 增长，0 持平， -1 减少）
		        
		        'month_receive_amount'          => $monthReceive, //今日代收金额
		        'month_receive_fee'             => M('exchange_report')->where(['userid' => $this->user['userid'], 'currency' => $value['currency'], ['addtime'=>['between',[$month_start,$now_time]]]])->sum('receive_amount'), //今日代收手续费
		        'month_receive_status'          => $month_receive_status,     //今日代收情况（1 增长，0 持平， -1 减少）
		        'month_paymeny_amount'          => $monthPayment, //今日代付金额
		        'month_paymeny_fee'             => M('exchange_report')->where(['userid' => $this->user['userid'], 'currency' => $value['currency'], ['addtime'=>['between',[$month_start,$now_time]]]])->sum('payment_amount'), //今日代付手续费
		        'month_paymeny_status'          => $month_payment_status,     //今日代付情况（1 增长，0 持平， -1 减少）
		        
		        'all_receive_amount'          => $allReceive, //今日代收金额
		        'all_receive_fee'             => M('exchange_report')->where(['userid' => $this->user['userid'], 'currency' => $value['currency']])->sum('receive_amount'), //今日代收手续费
		        'all_receive_status'          => 0,     //今日代收情况（1 增长，0 持平， -1 减少）
		        'all_paymeny_amount'          => $allPayment, //今日代付金额
		        'all_paymeny_fee'             => M('exchange_report')->where(['userid' => $this->user['userid'], 'currency' => $value['currency']])->sum('payment_amount'), //今日代付手续费
		        'all_paymeny_status'          => 0,     //今日代付情况（1 增长，0 持平， -1 减少）
	        ];
	    }
	    $this->successJson($result);
	}
	
	public function reposts ()
	{
	    $month_start = strtotime(date("Y-m-d H:i:s",mktime(0, 0 , 0,date("m"),1,date("Y"))));
	    $lastmonth_start = strtotime(date("Y-m-d H:i:s",mktime(0, 0 , 0,date("m")-1,1,date("Y"))));
        $lastmonth_end = strtotime(date("Y-m-d H:i:s",mktime(23,59,59,date("m") ,0,date("Y"))));
        $year_start = strtotime(date('Y-01-01', time()));
		$now_time = time();
		
	    $where = array();
	    $childWhere = array();
	    $where['currency'] = $this->inputData['currency'];
        if ($this->user['role'] == 2 || $this->user['role'] == 1) {
	        $where['userid'] = $this->user['userid'];
	    }
	    
	    if ($this->user['role'] == 1) {
	        $child = M('PeAdmin')->where(['fid' => $this->user['id']])->field("userid")->select();
    	    $childID = array_column($child, 'userid');
    	    $childWhere['userid'] = ['in', $childID];
	    }
	    
	    if ($this->inputData['time'] == 'month') {
	        $where['addtime'] = ['between',[$month_start,$now_time]];
	        $childWhere['addtime'] = ['between',[$month_start,$now_time]];
	    } else if ($this->inputData['time'] == 'last_month') {
	        $where['addtime'] = ['between',[$lastmonth_start,$lastmonth_end]];
	        $childWhere['addtime'] = ['between',[$lastmonth_start,$lastmonth_end]];
	    } else if ($this->inputData['time'] == 'year') {
	        $where['addtime'] = ['between',[$year_start,$now_time]];
	        $childWhere['addtime'] = ['between',[$year_start,$now_time]];
	    }
	    $count = M('exchange_report')->where($where)->count();
	    $currentPage = $this->inputData['pageNum'] ? $this->inputData['pageNum'] : 1;
        $pageSize = $this->inputData['pageSize'] ? $this->inputData['pageSize'] : 15;
        $total = $count;
        $allPage = ceil($total / $pageSize);
        $offset = ($currentPage - 1) * $pageSize;
		$Page = new \Think\Page($count, $pageSize);
		$show = $Page->show();
		$list = M('exchange_report')->where($where)->order('id desc')->limit($offset . ',' . $pageSize)->select();
		
		$result = [];
		foreach ($list as $key => $value) {
		    $result[$key] = $value;
		    $result[$key]['team_receive_amount'] = 0;
		    $result[$key]['team_payment_amount'] = 0;
		    $result[$key]['team_punish_amount'] = 0;
		    
		    if ($this->user['role'] == 1) {
		        $result[$key]['team_receive_amount'] = M('exchange_report')->where($childWhere)->sum('receive_fee');
		        $result[$key]['team_payment_amount'] = M('exchange_report')->where($childWhere)->sum('payment_fee');
		        $result[$key]['team_punish_amount'] = M('exchange_report')->where($childWhere)->sum('punish_amount');
		    }
		    
		    // 利润 = 手续费-佣金-自己罚款+团队罚款
		    $result[$key]['income_amount'] = ($value['receive_fee'] + $value['payment_fee']) - ($result[$key]['team_receive_amount'] + $result[$key]['team_payment_amount']) - $value['punish_amount'] + $result[$key]['team_punish_amount'];
		    $result[$key]['system_fee'] = 0;
		    
		  //  [
	   //         'date' => '2025-05-01',
    //             'receive_amount'            => '100',                           // 代收金额
    //             'receive_fee'               => '1',                             // 代收手续费
    //             'team_receive_amount'       => '2',                             // 团队代收手续费
    //             'payment_amount'            => '300',                           // 代付佣金
    //             'payment_fee'               => '3',                             // 代付手续费
    //             'team_payment_amount'       => '2',                             // 团队代付手续费
    //             'punish_amount'             => '30',                            // 处罚佣金
    //             'income_amount'             => '60',                            // 利润
    //             'system_fee'                => '20',                         // 系统手续费
    //         ]
		}
        
        
        $this->successJson([
            'page' => [
                'total' => (int)$total,
                'all_page' => (int)$allPage,
                'current_page' => (int)$currentPage,
                'page_size' => (int)$pageSize,
            ],
            'list' => $result,
        ]);
	}
	
	// 币种列表
	public function currencys ()
	{
	    $list = M('currencys')->order('id desc')->select();
	    $this->successJson($list);
	}
	
	public function addCurrency ()
	{
	    if (!$this->inputData['currency']) {
	        $this->errorJson('缺少参数！');
	    }
	    
	    $currency = M('currencys')->where(['currency' => $this->inputData['currency']])->find();
	    if ($currency) {
	        $this->errorJson('已存在币种！');
	    }
	    
	    if (M('currencys')->add([
	        'currency' => $this->inputData['currency'],
	        'desc' => $this->inputData['desc'] ? $this->inputData['currency'] : $this->inputData['currency'],
	        'symbol' => $this->inputData['symbol'] ? $this->inputData['symbol'] : '',
	        'addtime' => time(),
        ])) {
            $this->successJson();
        } else {
            $this->errorJson('添加失败！');
        }
	    
	}
	
	public function editCurrency ()
	{
	    if (!$this->inputData['id'] ) {
	        $this->errorJson('缺少参数！');
	    }
	    
	    $currency = M('currencys')->where(['currency' => $this->inputData['currency']])->find();
	    if ($currency) {
	        $this->errorJson('已存在币种！');
	    }
	    
	    if (M('currencys')->save([
	        'id' => $this->inputData['id'],
	        'currency' => $this->inputData['currency'],
	        'desc' => $this->inputData['desc'],
	        'symbol' => $this->inputData['symbol']
        ])) {
            $this->successJson();
        } else {
            $this->errorJson();
        }
	    $this->successJson();
	}
	
	public function delCurrency ()
	{
	    $this->successJson();
	}
	
	public function apiLogs ()
	{
	    $count = D('ExchangeOrder')->where($where)->count();
	    $currentPage = $this->inputData['pageNum'] ? $this->inputData['pageNum'] : 1;
        $pageSize = $this->inputData['pageSize'] ? $this->inputData['pageSize'] : 15;
        $total = $count;
        $allPage = ceil($total / $pageSize);
        $offset = ($currentPage - 1) * $pageSize;
		$Page = new \Think\Page($count, $pageSize);
		$show = $Page->show();
		
		$list = [
		    [
		        'id'                            => 1,
		        'endpoint'                      => '/Api/Index/currencys',      // 请求地址
		        'method'                        => 'POST',                      // 请求方式
	            'request_header'                => "{content-type:'application/json'}",// 请求头
                'request_body'                  => "{username:'admin', password:'123456'}",// 请求参数
	            'response_status'               => '200',                       // 响应状态
	            'response_data'                 => '{"code": 0,"msg": "success","data": []}', // 响应结果
	            'addtime'                       => '1746562625',                // 调用时间
	            'response_time'                 => '125',                       // 响应时间
	        ]
	    ];
	    
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
	
	// 代理商
	public function agents()
	{
	    $where['role'] = 1;
	    if (isset($this->inputData['wallet_id'])) {
		    $where['_complex'] = [
		        'id' => $this->inputData['wallet_id'],
		        'wallet_id' => $this->inputData['wallet_id'],
		        '_logic' => 'or'
	        ];
		}
	    $count = M('PeAdmin')->where($where)->count();
	    $currentPage = $this->inputData['pageNum'] ? $this->inputData['pageNum'] : 1;
        $pageSize = $this->inputData['pageSize'] ? $this->inputData['pageSize'] : 15;
        $total = $count;
        $allPage = ceil($total / $pageSize);
        $offset = ($currentPage - 1) * $pageSize;
		$Page = new \Think\Page($count, $pageSize);
		$show = $Page->show();
		
		$list = M('PeAdmin')
		    ->where($where)
		    ->order('id desc')
		  //  ->field(['id', 'userid', 'nickname', 'username', 'last_login_time', 'status', 'tg_account', 'receive_commission', 'payment_commission', 'punish_commission', 'receive_fee', 'payment_fee', 'wallet_id', 'is_system', 'currency_fees', 'channel_fees'])
		    ->limit($offset . ',' . $pageSize)
		    ->select();
		
		$currencys = M('currencys')->order('id desc')->select();
		foreach ($list as $key => $value) {
		    if ($value['channel_fees']) {
		        $list[$key]['channel_fees'] = unserialize($value['channel_fees']);
		    }
		    
		    if ($value['currency_fees']) {
		        $list[$key]['currency_fees'] = unserialize($value['currency_fees']);
		    }
		    
		    $userAmount = [];
		    $userSettle = M('UserCoinSettle')->where(['userid' => $value['userid']])->find();
		    $userMargin = M('UserCoinMargin')->where(['userid' => $value['userid']])->find();
		    // 币种及保证金
		    foreach ($currencys as $currency) {
		        $userAmount[$currency['currency']] = [
		            'settle' => isset($userSettle[strtolower($currency['currency'])]) ? $userSettle[strtolower($currency['currency'])] : 0,
		            'margin' => isset($userMargin[strtolower($currency['currency'])]) ? $userMargin[strtolower($currency['currency'])] : 0,
	            ];
		    }
		    $list[$key]['amount'] = $userAmount;
		}
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
	
	public function agentsEdit()
	{
	    if($this->inputData['id']){
	        if ($this->inputData['password']) {
	            $this->inputData['password'] = md5($this->inputData['password']);
	        }
	        if ($this->inputData['channel_fees']) {
	            $this->inputData['channel_fees'] = serialize($this->inputData['channel_fees']);
	        }
	        if ($this->inputData['currency_fees']) {
	            $this->inputData['currency_fees'] = serialize($this->inputData['currency_fees']);
	        }
	        
	        
	        unset($this->inputData['token']);
	        unset($this->inputData['is_system']);
	       // $sql = M('PeAdmin')->fetchSql(true)->save($this->inputData);
	       // var_dump($sql);exit;
			if (M('PeAdmin')->save($this->inputData)) {
				$this->successJson('编辑成功！');
			} else {
				$this->errorJson('编辑失败！');
			}
		}else{
		    if ($this->inputData['wallet_id']) {
		        if (M('PeAdmin')->where(array('wallet_id' => $this->inputData['wallet_id']))->find()) {
    		        $this->errorJson('代理商已存在！');
    		    }
		    } else {
		        if (M('PeAdmin')->where(array('username' => $this->inputData['username']))->find()) {
    		        $this->errorJson('代理商已存在！');
    		    }
		    }
		    
		    if ($this->inputData['password']) {
	            $this->inputData['password'] = md5($this->inputData['password']);
	        } else {
	            $this->inputData['password'] = md5(123456);
	        }
	        $this->inputData['username'] = $this->inputData['username'] ? $this->inputData['username'] : '';
	        $this->inputData['moble'] = '';
	        $this->inputData['sort'] = 0;
	        $this->inputData['addtime'] = time();
	        $this->inputData['last_login_time'] = 0;
	        $this->inputData['last_login_ip'] = '';
	        $this->inputData['endtime'] = 0;
	        $this->inputData['status'] = 1;
	        $this->inputData['google_key'] = '';
	        $this->inputData['role'] = 1;
	        $this->inputData['is_system'] = $this->inputData['is_system'];
	        $this->inputData['token'] = '';
	        $this->inputData['apikey'] = get_random_str();
    		$this->inputData['payment_commission'] = $this->inputData['payment_commission'] ? $this->inputData['payment_commission'] : 0;
    		$this->inputData['payment_fee'] = $this->inputData['payment_fee'] ? $this->inputData['payment_fee'] : 0;
    		$this->inputData['punish_commission'] = $this->inputData['punish_commission'] ? $this->inputData['punish_commission'] : 0;
    		$this->inputData['receive_commission'] = $this->inputData['receive_commission'] ? $this->inputData['receive_commission'] : 0;
    		$this->inputData['receive_fee'] = $this->inputData['receive_fee'] ? $this->inputData['receive_fee'] : 0;
    		$this->inputData['currency_fees'] = $this->inputData['currency_fees'] ? serialize($this->inputData['currency_fees']) : '';
    		$this->inputData['channel_fees'] = $this->inputData['channel_fees'] ? serialize($this->inputData['channel_fees']) : '';
	        
	        $addUserData = [];
            $addUserData['username'] = $this->inputData['username'] ? $this->inputData['username'] : $this->inputData['nickname'];
            $addUserData['enname'] = $this->inputData['username'] ? $this->inputData['username'] : $this->inputData['nickname'];
            $addUserData['qz'] = '86';
            $addUserData['mobile'] = $this->inputData['tg_account'] ? $this->inputData['tg_account'] : $this->inputData['wallet_id'];
            $addUserData['mobiletime'] = time();
            $addUserData['password'] = md5($this->inputData['password']);
            $addUserData['tpwdsetting'] = '1';
            $addUserData['paypassword'] = md5($this->inputData['password']);
            $addUserData['invit_1'] = '0';
            $addUserData['invit_2'] = '0';
            $addUserData['invit_3'] = '0';
            $addUserData['kyc_lv'] = 2;
            $addUserData['truename'] = $this->inputData['nickname'];
            $addUserData['idcard'] = $this->inputData['tg_account'] ? $this->inputData['tg_account'] : $this->inputData['wallet_id'];
            $addUserData['idtype'] = 1;
            $addUserData['idnationality'] = get_city_ip();
            $addUserData['idstate'] = 2;
            $addUserData['idcardinfo'] = '';
            $addUserData['idimg1'] = '5d011c7b89c3b.jpg';
            $addUserData['idimg2'] = '5d011c7f4176b.jpg';
            $addUserData['idimg3'] = '5d011cb2dca03.jpg';
            $addUserData['addip'] = get_client_ip();
            $addUserData['addr'] = get_city_ip();
            $addUserData['addtime'] = time();
            $addUserData['status'] = 1;
            $addUserData['invit'] = tradenoa();
            $addUserData['otcuser'] = $this->inputData['username'] ? $this->inputData['username'] : $this->inputData['nickname'];
            $addUserData['apikey'] = get_random_str();
            // $addUserData['lv'] = 0;
            // $addUserData['backstage'] = 0;
            $addUserData['auto_c2c_sell_status'] = 0;
            $addUserData['auto_c2c_buy_status'] = 0;
            // $addUserData['auto_c2c_time'] = '';
            // $addUserData['last_exchange_time'] = '';
            // $addUserData['all_money'] = '';
            // $addUserData[''] = '';
            // $addUserData[''] = '';
            // $addUserData[''] = '';
            // $addUserData[''] = '';
    		$userID = M('User')->add($addUserData);
    // 		var_dump($userM->getDbError());
    		$this->inputData['userid'] = $userID;
    		unset($this->inputData['token']);
    		$rsAdmin = M('PeAdmin')->add($this->inputData);
    		$user_coin = array('userid' => $userID);
    		// 创建用户数字资产档案
    		$rsCoin = M('UserCoin')->add($user_coin);
    		$rsMarginCoin = M('UserCoinMargin')->add($user_coin);
    		$rsSettleCoin = M('UserCoinSettle')->add($user_coin);
			if ($rsAdmin && $rsCoin && $userID && $rsMarginCoin && $rsSettleCoin) {
				$this->successJson('添加成功！');
			} else {
				$this->errorJson('添加失败！');
			}
		}
	}
	
	public function agentsDel () {
	    if (!IS_POST) {
	        $this->errorJson('请求拒绝！');
	    }
	    
	    
	    if ($this->inputData['id']) {
	        if ($this->inputData['id'] == 1) {
	            $this->errorJson('禁止删除！');
	        }
	        
	        if (M('PeAdmin')->where(['id' => $this->inputData['id']])->delete()) {
				$this->successJson('删除成功！');
			} else {
				$this->errorJson('删除失败！');
			}
	    } else {
	        $this->errorJson('删除失败！');
	    }
	}
	
	// 上传图片
// 	public function uploadImage()
// 	{
// 		$file = $_FILES['file']; // 获取上传的文件信息
//         if (empty($file['name'])) {
//             $this->errorJson('请选择文件！');
//         }
		
// 		$upload = new \Think\Upload();
// 		$upload->maxSize = 3145728;
// 		$upload->exts = array('jpg', 'gif', 'png', 'jpeg');
// 		$upload->rootPath = './Upload/public/';
// 		$upload->autoSub = false;
// 		$info = $upload->upload();
// 		foreach ($info as $k => $v) {
// 			$path = $v['savepath'] . $v['savename'];
// 		}
		
// 		$this->successJson(['path' => '/Upload/public/' . $path]);
// 	}
	
	public function uploadImage()
	{
	   // require 'vendor/autoload.php';
	    $file = $_FILES['file'];
	    $uploader = new \Common\Service\SimpleS3Uploader();
        $result = $uploader->uploadFormFile($file, 'uploads');
        
        if ($result['status']) {
            $this->successJson(['path' => $result['url']]);
        } else {
            $this->errorJson('上传失败！' . $result['message']);
        }
	}
	
	public function getLangConfig()
	{
	    $config = json_decode(file_get_contents($this->langConfig), true);
	    $this->successJson(['config' => $config]);
	}
	
	public function saveLangConfig() {
	    $config = $this->inputData['config'];
	    
	    if ($config) {
	        file_put_contents($this->langConfig, $config);
	    }
	    $this->successJson(['config' => $config]);
	}
	
	// 管理员手动罚款
	public function doAgentFine () {
	    if (!IS_POST) {
	        $this->errorJson('请求拒绝！');
	    }
	    
	    if (!$this->inputData['currency'] || !$this->inputData['type'] || !$this->inputData['amount'] || !$this->inputData['userid']) {
	        $this->errorJson('缺少参数！');
	    }
	    
	    if ($this->inputData['amount'] <= 0) {
	        $this->errorJson('金额错误！');
	    }
	    
	    $currency = M('currencys')->where(['currency' => $this->inputData['currency']])->find();
	    if (!$currency) {
	        $this->errorJson('币种不支持！');
	    }
	    
	    $peAdmin = M('PeAdmin')->where(['id' => $this->inputData['userid']])->find();
        if (!$peAdmin) {
            $this->errorJson('供应商不存在！');
        }
        
        $is_team = 0;
        if ($peAdmin['role'] == 2) {
            $is_team = 1;
        }
	    
	    $rate = 1;
	    $received_currency = $this->inputData['currency'];
        $received_amount = $this->inputData['amount'];
	    
	    $res = M('Settles')->add([
	        'is_team' => 0,
	        'user_id' => $this->inputData['userid'],
	        'type' => $this->inputData['type'],
	        'orderid' => date('YmdHis') . rand(100000, 999999),
	        'currency' => $this->inputData['currency'],
	        'amount' => $this->inputData['amount'],
	        'remark' => $this->inputData['remark'],
	        'received_currency' => $received_currency,
	        'received_amount' => $received_amount,
	        'rate' => $rate,
	        'status' => 4,
	        'addtime' => time(),
        ]);
        
        // 增加待结算金额
        $userCoin = M('UserCoinSettle')->where(['userid' => $peAdmin['userid']])->find();
        $userCoinData = M('UserCoin')->where(['userid' => $peAdmin['userid']])->find();
        $rs = M('UserCoinSettle')->save([
            'id' => $userCoin['id'],
            strtolower($this->inputData['currency']) => $userCoin[strtolower($this->inputData['currency'])] + $this->inputData['amount']
        ]);
        $rs_coin = M('UserCoin')->save([
            'id' => $userCoinData['id'],
            strtolower($this->inputData['currency']) => $userCoinData[strtolower($this->inputData['currency'])] + $this->inputData['amount']
        ]);
        
        if ($res && $rs && $rs_coin) {
            $this->successJson();
        } else {
            $this->errorJson('操作失败！');
        }
	}
	
	// 获取用户风控设置
	public function getUserPaytypeControl ()
	{
	    if (!IS_POST) {
	        $this->errorJson('请求拒绝！');
	    }
	    
	    $where['userid'] = $this->user['id'];
	    $where['payparams_id'] = $this->inputData['payparams_id'];
	    
	    $data = M('user_paytype_control')->where($where)->find();
	    $this->successJson($data ? $data : []);
	}
	
	// 修改用户风控设置
	public function setUserPaytypeControl ()
	{
	    if (!IS_POST) {
	        $this->errorJson('请求拒绝！');
	    }
	    
	    $where['userid'] = $this->user['id'];
	    $where['payparams_id'] = $this->inputData['payparams_id'];
	    $info = M('user_paytype_control')->where($where)->find();
	    if ($info) {
	        $this->inputData['id'] = $info['id'];
	        $res = M('user_paytype_control')->save($this->inputData);
	    } else {
	        $this->inputData['userid'] = $this->user['id'];
	        $res = M('user_paytype_control')->add($this->inputData);
	    }
	    
	    if ($res) {
	        $this->successJson('操作成功');
	    } else {
	        $this->errorJson('操作失败!');
	    }
	}
	
	// API接口管理
	public function getApiProviderList ()
	{
	    if (!IS_POST) {
	        $this->errorJson('请求拒绝！');
	    }
	    
	    $this->doSyncProvider();
	    
	    if ($this->user['role'] == 1) { // 代理商
    	    $where['userid'] = $this->user['id'];
	    } else if ($this->user['role'] == 2) { // 业务员只能查看自己的订单
	        $where['userid'] = 0;
	    } else {
	        if ($this->inputData['userid']) {
	            $searchUser = M('PeAdmin')->where(['id' => $this->inputData['userid']])->find();
	            
	            if ($searchUser['role'] == 1) {
            	    $where['userid'] = $searchUser['id'];
	            } else {
	                $where['userid'] = 0;
	            }
	        }
	    }
	    
	    // 币种
		$currency = isset($this->inputData['currency']) ? $this->inputData['currency'] : '';
		if ($currency) {
		    $currencyData = M('currencys')->where(['currency' => $currency])->find();
		    if (!$currencyData) {
		        $this->errorJson('币种不支持！');
		    }
		    
    		$where['currency'] = ['exp', "FIND_IN_SET('$currency', currency) > 0"];
		}
	    
	    
	    $count = M('user_interface')->where($where)->count();
	    $currentPage = $this->inputData['pageNum'] ? $this->inputData['pageNum'] : 1;
        $pageSize = $this->inputData['pageSize'] ? $this->inputData['pageSize'] : 15;
        $total = $count;
        $allPage = ceil($total / $pageSize);
        $offset = ($currentPage - 1) * $pageSize;
		$Page = new \Think\Page($count, $pageSize);
		$show = $Page->show();
		$list = M('user_interface')->where($where)->order('id desc')->limit($offset . ',' . $pageSize)->select();

        foreach ($list as $key => $value) {
            if ($list[$key]['channel']) {
		        $list[$key]['channel'] = unserialize($list[$key]['channel']);
		    } else {
		        $list[$key]['channel'] = [
                    ["channel_name" => "通道名称【H5】", "channel_id" => "通道ID / KEY", "channel_type" => "1"],
                    ["channel_name" => "通道名称【APP】", "channel_id" => "通道ID / KEY", "channel_type" => "2"]
                ];
		    }
            
        }
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
	
	// 修改接口状态
	public function setApiProviderStatus ()
	{
	    if (!IS_POST) {
	        $this->errorJson('请求拒绝！');
	    }
	    
	    $list = M('user_interface')->where(['id' => $this->inputData['id']])->find();
	    if (!$list) {
	        $this->errorJson('数据不存在！');
	    }
	    
	    $res = M('user_interface')->save([
	        'id' => $this->inputData['id'],
		    'status' => $this->inputData['status']
	    ]);
	    
	    if ($res) {
	        $this->successJson('操作成功');
	    } else {
	        $this->errorJson('操作失败!');
	    }
	}
	
	// 修改接口配置参数
	public function setApiProviderData ()
	{
	    if (!IS_POST) {
	        $this->errorJson('请求拒绝！');
	    }
	    
	    $list = M('user_interface')->where(['id' => $this->inputData['id']])->find();
	    if (!$list) {
	        $this->errorJson('数据不存在！');
	    }
	    
	    $res = M('user_interface')->save([
	        'id' => $this->inputData['id'],
		    'payinfo' => $this->inputData['payinfo']
	    ]);
	    
	    if ($res) {
	        $this->doSyncEditProvider($list['userid'], $list['key'], $this->inputData['payinfo']);
	        $this->successJson('操作成功');
	    } else {
	        $this->errorJson('操作失败!');
	    }
	}
	
	// 同步接口数据
	public function doSyncProvider ()
	{
        
        $sendHeader = [];
        $sendData = [];
        $sendData['sign'] = $this->createSign($this->bepayKey, $sendData);
        $verifyResult = httpRequestData($this->syncProvidersUrl . '?' . http_build_query($sendData), $sendData, $sendHeader, 'GET');
        if ($verifyResult) {
            $verifyResultArr = json_decode($verifyResult, true);
            if ($verifyResultArr['code'] == 0) {
                $resultData = $verifyResultArr['data'];
                
                foreach ($resultData as $key => $value) {
                    $peAdmin = M('PeAdmin')->where(['id' => $key])->find();
                    if ($peAdmin) {
                        foreach ($value as $item) {
                            $userInterface = M('user_interface')->where(['userid' => $key, 'key' => $item['key']])->find();
                            if (!$userInterface) {
                                $res = M('user_interface')->add([
                                    'userid' => $key,
                                    'currency' => implode(',', $item['currency']),
                                    'key' => $item['key'],
                                    'name' => $item['name'],
                                    'payinfo' => $item['payinfo'],
                                    'channel' => serialize($item['channel']),
                                    'status' => 1,
                                    'addtime' => time()
                                ]);
                            } else {
                                M('user_interface')->save([
                                    'id' => $userInterface['id'],
                                    'currency' => implode(',', $item['currency']),
                                    'name' => $item['name'],
                                    'payinfo' => $item['payinfo'],
                                    'channel' => serialize($item['channel']),
                                ]);
                            }
                        }
                    }
                }
                
                return true;
            }
        }
        return false;
	}
	
	// 同步修改接口数据
	public function doSyncEditProvider ($agentid, $streamid, $payInfo)
	{
        $sendHeader = ['Content-Type: application/json;'];
        $sendData = ['agentid' => $agentid, 'streamid' => $streamid, 'payInfo' => $payInfo];
        $sendData['sign'] = $this->createSign($this->bepayKey, $sendData);
        $verifyResult = httpRequestData($this->syncEditProviderDataUrl, json_encode($sendData), $sendHeader, 'POST');
        if ($verifyResult) {
            $verifyResultArr = json_decode($verifyResult, true);
            if ($verifyResultArr['code'] == 0) {
                return true;
            }
        }
        return false;
	}
	
	// 获取钱包数据
	public function getWalletInfo ($id)
	{
        $sendHeader = ['Content-Type: multipart/form-data;'];
        $sendData = ['appid' => $this->appid, 'id' => $id];
        $sendData['sign'] = $this->createSign($this->bepayKey, $sendData);
        $verifyResult = httpRequestData($this->getWalletInfoUrl, $sendData, $sendHeader, 'POST');
        if ($verifyResult) {
            $verifyResultArr = json_decode($verifyResult, true);
            if ($verifyResultArr['code'] == 1) {
                return $verifyResultArr['data'];
            }
        }
        return false;
	}
	
	// 内部转账
    public function walletTransfer ($id, $to_id, $amount, $pin)
    {
        $sendHeader = ['Content-Type: multipart/form-data;'];
        $sendData = ['appid' => $this->appid, 'id' => $id, 'to_id' => $to_id, 'amount' => $amount, 'pin' => $pin];
        $sendData['sign'] = $this->createSign($this->bepayKey, $sendData);
        $verifyResult = httpRequestData($this->walletTransferUrl, $sendData, $sendHeader, 'POST');
        if ($verifyResult) {
            $verifyResultArr = json_decode($verifyResult, true);
            if ($verifyResultArr['code'] == 1) {
                return true;
            } else {
                return $verifyResultArr['msg'];
            }
        }
        return false;
    }
    
    // 商户列表
    public function mercharts () {
	    $where['is_merchart'] = 1;
	    $where['role'] = 5;
	    $allChild =  $this->getAllSubordinates($this->user['id']);
	    $where['id'] = ['in', array_column($allChild, 'id')];
		if (isset($this->inputData['nickname'])) {
		    $where['_complex'] = [
		        'id' => $this->inputData['nickname'],
		        'nickname' => $this->inputData['nickname'],
		        '_logic' => 'or'
	        ];
		}
		
	    $count = M('PeAdmin')->where($where)->count();
	    $total = $count;
        $currentPage = $this->inputData['pageNum'] ? $this->inputData['pageNum'] : 1;
        $pageSize = $this->inputData['pageSize'] ? $this->inputData['pageSize'] : 15;
        $total = $count;
        $allPage = ceil($total / $pageSize);
        $offset = ($currentPage - 1) * $pageSize;
		$Page = new \Think\Page($count, $pageSize);
		$show = $Page->show();
		
		$list = M('PeAdmin')->where($where)->order('id desc')->field(['id', 'userid', 'nickname', 'username', 'last_login_time', 'status', 'tg_account', 'receive_commission', 'payment_commission', 'punish_commission', 'extraction_commission', 'receive_fee', 'payment_fee', 'wallet_id', 'channel_fees', 'addtime'])->limit($offset . ',' . $pageSize)->select();
		
		
		$result = [];
		$currencys = M('currencys')->order('id desc')->select();
		foreach ($list as $key => $value) {
		    $user = M('User')->where(['id' => $value['userid']])->find();
		    $value['auto_c2c_sell_status'] = 0;
		    $value['auto_c2c_buy_status'] = 0;
		    
		    if ($user) {
		        $value['auto_c2c_sell_status'] = $user['auto_c2c_sell_status'];
		        $value['auto_c2c_buy_status'] = $user['auto_c2c_buy_status'];
		    }
		    
		    $result[$key] = $value;
		    if ($result[$key]['channel_fees']) {
		        $result[$key]['channel_fees'] = unserialize($result[$key]['channel_fees']);
		    }
		    
		    $userAmount = [];
		    $userSettle = M('UserCoinSettle')->where(['userid' => $value['userid']])->find();
		    // 币种及保证金
		    foreach ($currencys as $currency) {
		        $userAmount[$currency['currency']] = [
		            'available' => isset($userSettle[strtolower($currency['currency'])]) ? $userSettle[strtolower($currency['currency'])] : 0,
		            'freeze' => isset($userSettle[strtolower($currency['currency'])."d"]) ? $userSettle[strtolower($currency['currency'])."d"] : 0,
	            ];
		    }
		    $result[$key]['amount'] = $userAmount;
		    $result[$key]['balance'] = 0;
		    
		    $result[$key]['order_num'] = D('PayOrder')->where(array('userid'=>$result[$key]['userid']))->count();
		    
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
		
// 		$list = [
// 		    [
// 		        'id'                            => 1,                           // 
// 		        'username'                      => 'feng001',                   // 用户名
// 		        'nickname'                      => '风1',                       // 昵称
// 	            'tg_account'                    => 1,                           // TG
// 	            'receive_commission'            => '1',                         // 代收佣金
// 	            'payment_commission'            => '3',                         // 代付佣金
// 	            'punish_commission'             => '5',                         // 处罚佣金
// 	            'extraction_commission'         => '2',                         // 抽取佣金
// 	            'last_login_time'               => '1746562625',                // 最后登陆时间
// 	            'status'                        => 1,                           // 状态 1 正常 2 冻结
// 	            'user_id'                       => 1,                           // 外部userid
// 	        ]
// 	    ];
	    
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
    
    // 添加商户
    public function addMerchart () {
        $this->inputData['is_merchart'] = 1;
	    if ($this->inputData['wallet_id']) {
	        if (M('PeAdmin')->where(array(
	                'wallet_id' => $this->inputData['wallet_id'],
	                'is_merchart' => 1,
            ))->find()) {
    	        $this->errorJson('商户已存在！');
    	    }
	    } else {
	        if (M('PeAdmin')->where(array('username' => $this->inputData['username']))->find()) {
    	        $this->errorJson('代理商已存在！');
    	    }
	    }
	    
	    if ($this->inputData['password']) {
            $this->inputData['password'] = md5($this->inputData['password']);
        } else {
            $this->inputData['password'] = md5(123456);
        }
        $this->inputData['username'] = $this->inputData['username'] ? $this->inputData['username'] : '';
        $this->inputData['moble'] = '';
        $this->inputData['sort'] = 0;
        $this->inputData['addtime'] = time();
        $this->inputData['last_login_time'] = 0;
        $this->inputData['last_login_ip'] = '';
        $this->inputData['endtime'] = 0;
        $this->inputData['status'] = 1;
        $this->inputData['google_key'] = '';
        $this->inputData['role'] = 5;
        $this->inputData['token'] = '';
        $this->inputData['fid'] = $this->user['id'];
        
        $paytype_config = M('paytype_config')->field(['id', 'channelid'])->select();
        $channelids = array_column($paytype_config, 'channelid');
        $addUserData = [];
        $addUserData['username'] = $this->inputData['username'] ? $this->inputData['username'] : $this->inputData['nickname'];
        $addUserData['enname'] = $this->inputData['username'] ? $this->inputData['username'] : $this->inputData['nickname'];
        $addUserData['qz'] = '86';
        $addUserData['mobile'] = $this->inputData['tg_account'] ? $this->inputData['tg_account'] : $this->inputData['wallet_id'];
        $addUserData['mobiletime'] = time();
        $addUserData['password'] = md5($this->inputData['password']);
        $addUserData['tpwdsetting'] = '1';
        $addUserData['paypassword'] = md5($this->inputData['password']);
        $addUserData['invit_1'] = '0';
        $addUserData['invit_2'] = '0';
        $addUserData['invit_3'] = '0';
        $addUserData['kyc_lv'] = 2;
        $addUserData['truename'] = $this->inputData['nickname'];
        $addUserData['idcard'] = $this->inputData['tg_account'] ? $this->inputData['tg_account'] : $this->inputData['wallet_id'];
        $addUserData['idtype'] = 1;
        $addUserData['idnationality'] = get_city_ip();
        $addUserData['idstate'] = 2;
        $addUserData['idcardinfo'] = '';
        $addUserData['idimg1'] = '5d011c7b89c3b.jpg';
        $addUserData['idimg2'] = '5d011c7f4176b.jpg';
        $addUserData['idimg3'] = '5d011cb2dca03.jpg';
        $addUserData['addip'] = get_client_ip();
        $addUserData['addr'] = get_city_ip();
        $addUserData['addtime'] = time();
        $addUserData['status'] = 1;
        $addUserData['invit'] = tradenoa();
        $addUserData['otcuser'] = $this->inputData['username'] ? $this->inputData['username'] : $this->inputData['nickname'];
        $addUserData['apikey'] = get_random_str();
        // $addUserData['lv'] = 0;
        // $addUserData['backstage'] = 0;
        $addUserData['auto_c2c_sell_status'] = $this->inputData['auto_c2c_sell_status'] ? $this->inputData['auto_c2c_sell_status'] : 0;
        $addUserData['auto_c2c_buy_status'] = $this->inputData['auto_c2c_buy_status'] ? $this->inputData['auto_c2c_buy_status'] : 0;
        $addUserData['cancal_c2c_level'] = '1';
        $addUserData['select_channelid'] = implode(',', $channelids);
        // $addUserData['auto_c2c_time'] = '';
        // $addUserData['last_exchange_time'] = '';
        // $addUserData['all_money'] = '';
        // $addUserData[''] = '';
        // $addUserData[''] = '';
        // $addUserData[''] = '';
        // $addUserData[''] = '';
        
		$userID = M('User')->add($addUserData);
// 		var_dump($userM->getDbError());
		$this->inputData['userid'] = $userID;
		$rsAdmin = M('PeAdmin')->add($this->inputData);
		$user_coin = array('userid' => $userID);
		// 创建用户数字资产档案
		$rsCoin = M('UserCoin')->add($user_coin);
		$rsMarginCoin = M('UserCoinMargin')->add($user_coin);
		$rsSettleCoin = M('UserCoinSettle')->add($user_coin);
		if ($userID && $rsAdmin && $rsCoin && $rsMarginCoin && $rsSettleCoin) {
			$this->successJson('添加成功！');
		} else {
			$this->errorJson('添加失败！');
		}
	}
	
	public function editMerchart () {
	    if($this->inputData['id']){
	        if ($this->inputData['password']) {
	            $this->inputData['password'] = md5($this->inputData['password']);
	        }
	        $peAdmin = M('PeAdmin')->where(['id' => $this->inputData['id']])->find();
	        if (!$peAdmin) {
	            $this->errorJson('账号不存在！');
	        }
            
            if ($this->inputData['channel_fees']) {
	            $this->inputData['channel_fees'] = serialize($this->inputData['channel_fees']);
	        }
            unset($this->inputData['token']);
            unset($this->inputData['auto_c2c_sell_status']);
            unset($this->inputData['auto_c2c_buy_status']);
			if (M('PeAdmin')->save($this->inputData)) {
				$this->successJson('编辑成功！');
			} else {
				$this->errorJson('编辑失败！');
			}
		}else{
		    $this->errorJson('编辑失败！');
		}
	}
	
	// 获取品牌配置
	public function getTgbotConfig ()
	{
	    $where['userid'] = $this->user['id'];
	    $result = [
	        'name' => '',
	        'bot_token' => '',
	        'domain' => '',
	        'support_link' => '',
        ];
        
        $config = M('tg_config')->where($where)->find();
        if ($config) {
            $result = $config;
        }
        
        $this->successJson($result);
	}
	
	// 设置品牌配置
	public function setTgbotConfig ()
	{
	    $where['userid'] = $this->user['id'];
        $config = M('tg_config')->where($where)->find();
        if (!$config) {
    	    $saveData = [
    	        'userid' => $this->user['id'],
    	        'name' => $this->inputData['name'] ? $this->inputData['name'] : '',
    	        'bot_token' => $this->inputData['bot_token'] ? $this->inputData['bot_token'] : '',
    	        'domain' => $this->inputData['domain'] ? $this->inputData['domain'] : '',
    	        'support_link' => $this->inputData['support_link'] ? $this->inputData['support_link'] : '',
    	        'addtime' => time()
            ];
            $res = M('tg_config')->add($saveData);
        } else {
            $res = M('tg_config')->where($where)->save($this->inputData);
        }
        
        if ($res) {
			$this->successJson('编辑成功！');
		} else {
			$this->errorJson('编辑失败！');
		}
	}
	
	public function testAdbInfo () {
        //   $signer = new \Common\Service\VmosAPISigner(
        //     "02nytsN365w0IxX0184L1cSBNFvTgP5q",  // Access Key ID
        //     "VzWEXKboTvIfS7GRAZOkOODw"  // Secret Access Key
        //   );
        
        //   $baseURL = "https://api.vmoscloud.com";
        
        //   // Example GET request
        //   $getPath = "/vcpcloud/api/padApi/getProxys";
        //   $getParams = ["page" => 1, "rows" => 10];
        //   $getHeaders = $signer->signRequest("GET", $getPath, $getParams);
        
        //   $getUrl = $baseURL . $getPath . '?' . http_build_query($getParams);
        //   $getCurl = curl_init($getUrl);
        //   curl_setopt($getCurl, CURLOPT_RETURNTRANSFER, true);
        //   curl_setopt($getCurl, CURLOPT_HTTPHEADER, $getHeaders);
        //   curl_setopt($getCurl, CURLOPT_SSL_VERIFYPEER, false);
        //   curl_setopt($getCurl, CURLOPT_SSL_VERIFYHOST, false);
        
        //   $getResponse = curl_exec($getCurl);
        //   curl_close($getCurl);
        //   echo "GET Response:\n" . $getResponse . "\n";
        
        //   // Example POST request
        //   $postPath = "/vcpcloud/api/padApi/adb";
        //   $postData = ["padCode" => "APP5BK4M0W8JFCI0", "enable" => true];
        //   $postHeaders = $signer->signRequest("POST", $postPath, [], $postData);
        
        //   $postCurl = curl_init($baseURL . $postPath);
        //   curl_setopt($postCurl, CURLOPT_RETURNTRANSFER, true);
        //   curl_setopt($postCurl, CURLOPT_POST, true);
        //   curl_setopt($postCurl, CURLOPT_HTTPHEADER, $postHeaders);
        //   curl_setopt($postCurl, CURLOPT_POSTFIELDS, json_encode($postData, JSON_UNESCAPED_UNICODE));
        //   curl_setopt($postCurl, CURLOPT_SSL_VERIFYPEER, false);
        //   curl_setopt($postCurl, CURLOPT_SSL_VERIFYHOST, false);
        
        //   $postResponse = curl_exec($postCurl);
        //   if (curl_errno($postCurl)) {
        //     echo "cURL Error: \n" . curl_error($postCurl) . "\n";
        //   } else {
        //     $httpCode = curl_getinfo($postCurl, CURLINFO_HTTP_CODE);
        //     echo "POST Response HTTP Status: $httpCode\n";
        //     echo "POST Response:\n$postResponse\n";
        //     echo "<pre>";
        //     var_dump(json_decode($postResponse, true));
        //   }
        //   curl_close($postCurl);
        echo "<pre>";
        var_dump($_SERVER);
        var_dump(get_client_ip());
        $ip = '';
    
        // 优先级从高到低检查各种可能的IP来源
        $sources = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP', 
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($sources as $source) {
            if (!empty($_SERVER[$source])) {
                $ip = $_SERVER[$source];
                break;
            }
        }
        
        // 处理多个IP的情况（如X-Forwarded-For可能包含多个IP）
        if (strpos($ip, ',') !== false) {
            $ips = explode(',', $ip);
            $ip = trim($ips[0]); // 取第一个IP（最原始的客户端IP）
        }
        
        // 验证IP地址格式（IPv4）
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ip = $_SERVER['REMOTE_ADDR'] ? $_SERVER['REMOTE_ADDR'] : ''; // 回退到REMOTE_ADDR
        }
        
        // 再次验证最终的IP
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ip = ''; // 如果仍然不是有效的IPv4，返回空字符串
        }
        
        echo $ip;
    }
	
	
	

}
?>