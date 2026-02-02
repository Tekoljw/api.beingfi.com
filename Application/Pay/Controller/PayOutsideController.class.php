<?php

namespace Pay\Controller;

use Think\Controller;
use Think\Log;

/**
 * @author guotian
 * @date   2020-02-06
 */
//外部访问控制器
class PayOutsideController extends BaseController
{
    /**
     * 初始化控制器
     * BaseController constructor.
     */
    public function __construct()
    {
        parent::__construct();
        //进行平台访问校验
        $this->checkPlatformVerify();
        //检查账户密码信息
        $this->checkAdminInfo();
    }

    private function checkAdminInfo(){
        $account = I("post.account");
        if(!$account){
            $this->showmessage("OTC-没有账户信息!");
        }

        $password = I("post.password");
        if(!$password){
            $this->showmessage("OTC-没有密码信息！");
        }

        $user = M('User')->where(array('mobile' => $account))->find();
        if($user){
        // if (check($username, 'mobile')) {
            // $user = M('User')->where(array('mobile' => $username))->find();
            $remark = '通过手机号登录';
        }
        if (!$user) {
            $user = M('User')->where(array('username' => $account))->find();
            $remark = '通过用户名登录';
        }
        if (!$user) {
            $this->showmessage(L('OTC-用户不存在！'));
        }
        if (md5($password) != $user['password']){
            $this->showmessage(L('OTC-登录密码错误！'));
        }
    }

    //今日交易统计信息
    public function getTodayExchangeInfo(){

        //返回数据
        $return = array();

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
        $return['today_trade_sum'] = $today_trade_sum;
        //今日C2C交易量
        $where['status'] = 3;
        $today_c2c_sum = D('ExchangeOrder')->where($where)->sum('num');
        $today_c2c_sum = $today_c2c_sum?$today_c2c_sum:0;
        $today_c2c_sum = round($today_c2c_sum, 4);
        $return['today_c2c_sum'] = $today_c2c_sum;
        //今日利润
        $where['status'] = 1;
        $today_trade_profit = M('trade')->where($where)->sum('fee');
        $today_trade_profit = $today_trade_profit?$today_trade_profit:0;
        $where['status'] = 3;
        $today_c2c_profit = D('ExchangeOrder')->where($where)->sum('fee');
        $today_c2c_profit = $today_c2c_profit?$today_c2c_profit:0;
        $return['today_profit'] = round($today_trade_profit + $today_c2c_profit, 4);
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
        $return['total_profit'] = round($total_trade_profit + $total_c2c_profit, 4);

        $return['noticestr']    = get_random_str();      //随机字符串
        $md5key = M('exchange_config')->where(['id' => 1])->getField('pay_params_otc_key');
        $sign   = $this->createSign($md5key, $return);
        $return['sign'] = $sign;

        $this->successmMessage($return);
    }

    //获取平台用户信息
    public function getPlatfromUserInfo(){

        $idstate = I("post.idstate");
        $firstRow = I("post.firstRow");
        $listRows = I("post.listRows");
        if(!$firstRow){
            $this->showmessage(L('firstRow error = '.$firstRow));
        }
        if(!$listRows){
            $this->showmessage(L('firstRow error = '.$firstRow));
        }
        if($firstRow <= 0){
            $this->showmessage(L('firstRow 222 error = '.$firstRow));
        }
        if($listRows <= 0){
            $this->showmessage(L('listRows 222 error = '.$listRows));
        }
        //返回数据
        $return = array();

        if ($idstate == 2) {
            $list = M('User')->where($where)->order('kyc_lv,id asc')->limit($firstRow . ',' . $listRows)->select();
        } else {
            $list = M('User')->where($where)->order('id desc')->limit($firstRow . ',' . $listRows)->select();
        }
        
        foreach ($list as $k => $v) {
            //第几代
            $list[$k]['invit_1'] = M('User')->where(array('id' => $v['invit_1']))->getField('username');
            $list[$k]['invit_2'] = M('User')->where(array('id' => $v['invit_2']))->getField('username');
            $list[$k]['invit_3'] = M('User')->where(array('id' => $v['invit_3']))->getField('username');
            $user_login_state=M('user_log')->where(array('userid'=>$v['id'],'type' => 'login'))->order('id desc')->find();
            $list[$k]['state']  =$user_login_state['state'];
            //机构认证
            $organization_info = M('user_kyc')->where(array('userid' => $v['id']))->find();
            $list[$k]['organization_status']= $organization_info['organization_status'];
            $list[$k]['organization_name']  = $organization_info['organization_name'];
            $list[$k]['organization_id']    = $organization_info['organization_id'];
            $list[$k]['legalperson_name']   = $organization_info['organization_legalperson'];
            $list[$k]['legalperson_id']     = $organization_info['organization_legalperson_id'];
            $list[$k]['legalperson_status'] = $organization_info['organization_legalperson_status'];
        }

        $return['user_info']    = $list;
        $return['noticestr']    = get_random_str();      //随机字符串
        $md5key = M('exchange_config')->where(['id' => 1])->getField('pay_params_otc_key');
        $sign   = $this->createSign($md5key, $return);
        $return['sign'] = $sign;

        $this->successmMessage($return);
    }

    //获取支付类型信息
    public function getPayTypeInfo(){
        $firstRow = I("post.firstRow");
        $listRows = I("post.listRows");
        if(!$firstRow){
            $this->showmessage(L('firstRow error = '.$firstRow));
        }
        if(!$listRows){
            $this->showmessage(L('firstRow error = '.$firstRow));
        }
        if($firstRow <= 0){
            $this->showmessage(L('firstRow 222 error = '.$firstRow));
        }
        if($listRows <= 0){
            $this->showmessage(L('listRows 222 error = '.$listRows));
        }

        //返回数据
        $return = array();

        $list = M('paytype_config')->where($where)->order('id desc')->limit($firstRow . ',' . $listRows)->select();

        $return['paytype_info']    = $list;
        $return['noticestr']    = get_random_str();      //随机字符串
        $md5key = M('exchange_config')->where(['id' => 1])->getField('pay_params_otc_key');
        $sign   = $this->createSign($md5key, $return);
        $return['sign'] = $sign;
    }

    //获取支付参数信息
    public function getPayParamsInfo(){
        $firstRow = I("post.firstRow");
        $listRows = I("post.listRows");
        if(!$firstRow){
            $this->showmessage(L('firstRow error = '.$firstRow));
        }
        if(!$listRows){
            $this->showmessage(L('firstRow error = '.$firstRow));
        }
        if($firstRow <= 0){
            $this->showmessage(L('firstRow 222 error = '.$firstRow));
        }
        if($listRows <= 0){
            $this->showmessage(L('listRows 222 error = '.$listRows));
        }

        //返回数据
        $return = array();

        $list = M('payparams_list')->where($where)->order('id desc')->limit($firstRow . ',' . $listRows)->select();

        $return['payparams_info']    = $list;
        $return['noticestr']    = get_random_str();      //随机字符串
        $md5key = M('exchange_config')->where(['id' => 1])->getField('pay_params_otc_key');
        $sign   = $this->createSign($md5key, $return);
        $return['sign'] = $sign;
    }

    //获取C2C订单数据
    public function getC2CSellOrderInfo(){
        $firstRow = I("post.firstRow");
        $listRows = I("post.listRows");
        if(!$firstRow){
            $this->showmessage(L('firstRow error = '.$firstRow));
        }
        if(!$listRows){
            $this->showmessage(L('firstRow error = '.$firstRow));
        }
        if($firstRow <= 0){
            $this->showmessage(L('firstRow 222 error = '.$firstRow));
        }
        if($listRows <= 0){
            $this->showmessage(L('listRows 222 error = '.$listRows));
        }

        $where = array();
        $otype = I("post.otype");
        if(isset($otype)){
            $where['otype'] = $otype; // 订单类型
        }
        $start_time = I("post.start_time");
        $end_time = I("post.end_time");
        if(isset($start_time) && isset($end_time)){
            $where['addtime'] = ['between', [$start_time. $end_time]]; // 订单时间
        }elseif(isset($start_time) && !isset($end_time)){
            $where['addtime'] = ['gt', $start_time]; // 订单时间
        }elseif(!isset($start_time) && isset($end_time)){
            $where['addtime'] = ['lt', $end_time]; // 订单时间
        }

        //返回数据
        $return = array();

        $list = D('ExchangeOrder')->where($where)->order('id desc')->limit($firstRow . ',' . $listRows)->select();

        $return['c2c_order_info']    = $list;
        $return['noticestr']    = get_random_str();      //随机字符串
        $md5key = M('exchange_config')->where(['id' => 1])->getField('pay_params_otc_key');
        $sign   = $this->createSign($md5key, $return);
        $return['sign'] = $sign;
    }

    //获取C2C交易数据
    public function getC2CExchangeInfo(){
        $firstRow = I("post.firstRow");
        $listRows = I("post.listRows");
        if(!$firstRow){
            $this->showmessage(L('firstRow error = '.$firstRow));
        }
        if(!$listRows){
            $this->showmessage(L('firstRow error = '.$firstRow));
        }
        if($firstRow <= 0){
            $this->showmessage(L('firstRow 222 error = '.$firstRow));
        }
        if($listRows <= 0){
            $this->showmessage(L('listRows 222 error = '.$listRows));
        }

        //返回数据
        $return = array();

        $return['c2c_exchange_info']    = $list;
        $return['noticestr']    = get_random_str();      //随机字符串
        $md5key = M('exchange_config')->where(['id' => 1])->getField('pay_params_otc_key');
        $sign   = $this->createSign($md5key, $return);
        $return['sign'] = $sign;
    }

    //获取币币订单数据
    public function getTradeOrderInfo(){
        $firstRow = I("post.firstRow");
        $listRows = I("post.listRows");
        if(!$firstRow){
            $this->showmessage(L('firstRow error = '.$firstRow));
        }
        if(!$listRows){
            $this->showmessage(L('firstRow error = '.$firstRow));
        }
        if($firstRow <= 0){
            $this->showmessage(L('firstRow 222 error = '.$firstRow));
        }
        if($listRows <= 0){
            $this->showmessage(L('listRows 222 error = '.$listRows));
        }

        $where = array();
        $start_time = I("post.start_time");
        $end_time = I("post.end_time");
        if(isset($start_time) && isset($end_time)){
            $where['addtime'] = ['between', [$start_time. $end_time]]; // 订单时间
        }elseif(isset($start_time) && !isset($end_time)){
            $where['addtime'] = ['gt', $start_time]; // 订单时间
        }elseif(!isset($start_time) && isset($end_time)){
            $where['addtime'] = ['lt', $end_time]; // 订单时间
        }

        //返回数据
        $return = array();

        $list = M('Trade')->where($where)->order('id desc')->limit($firstRow . ',' . $listRows)->select();

        $return['tarde_order_info']    = $list;
        $return['noticestr']    = get_random_str();      //随机字符串
        $md5key = M('exchange_config')->where(['id' => 1])->getField('pay_params_otc_key');
        $sign   = $this->createSign($md5key, $return);
        $return['sign'] = $sign;
    }

    //获取币币交易数据
    public function getTradeExchangeInfo(){
        $firstRow = I("post.firstRow");
        $listRows = I("post.listRows");
        if(!$firstRow){
            $this->showmessage(L('firstRow error = '.$firstRow));
        }
        if(!$listRows){
            $this->showmessage(L('firstRow error = '.$firstRow));
        }
        if($firstRow <= 0){
            $this->showmessage(L('firstRow 222 error = '.$firstRow));
        }
        if($listRows <= 0){
            $this->showmessage(L('listRows 222 error = '.$listRows));
        }

        //返回数据
        $return = array();

        $return['tarde_exchange_info'] = $list;
        $return['noticestr'] = get_random_str();      //随机字符串
        $md5key = M('exchange_config')->where(['id' => 1])->getField('pay_params_otc_key');
        $sign   = $this->createSign($md5key, $return);
        $return['sign'] = $sign;
    }
}