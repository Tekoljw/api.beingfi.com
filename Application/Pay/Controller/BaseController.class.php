<?php

namespace Pay\Controller;

use \think\Controller;
use Think\Log;

/**
 * @author guotian
 * @date   2018-06-06
 */
class BaseController extends Controller
{
    /**
     * 初始化控制器
     * BaseController constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    //检测商户用户签名
    protected function checkUserVerify(){

        $sign       = I("post.sign");
        if(!$sign){
            $this->showmessage("OTC-没有签名信息!");
        }

        $noticestr  = I("post.noticestr");

        if(!$noticestr){
            $this->showmessage("OTC-noticestr1随机字符串参数不能为空！");
        }

        $userid = I("post.userid");
        if(!$userid){
            $this->showmessage("OTC-userid获取的商户ID不能为空!");
        }
        // $md5key = M('User')->where(['id' => $userid])->getField('apikey');
        $md5key = M('PeAdmin')->where(['id' => $userid])->getField('apikey');

        if(!$this->verify($_POST, $md5key, $sign)){
            $this->showmessage("OTC-验签失败!");
        }
    }

    //检测平台签名
    protected function checkPlatformVerify(){

        $sign       = I("post.sign");
        if(!$sign){
            $this->showmessage("OTC-没有平台签名信息!");
        }

        $noticestr  = I("post.noticestr");

        if(!$noticestr){
            $this->showmessage("OTC-noticestr1随机字符串参数不能为空！");
        }

        $md5key = M('exchange_config')->where(['id' => 1])->getField('pay_params_otc_key');

        if(!$this->verify($_POST, $md5key, $sign)){
            $this->showmessage("OTC-平台验签失败!");
        }
    }

     /**
     *  验证签名
     * @return bool
     */
    protected function verify($param, $md5key, $md5sign)
    {
        $md5keysignstr = $this->createSign($md5key, $param);
        // var_dump($md5keysignstr);exit;
        if ($md5sign == $md5keysignstr) {
            return true;
        } else {
            Log::record("验证签名 sign= ".json_encode($param), Log::INFO);
            return false;
        }
    }

    /**
     * 创建签名
     * @param $Md5key
     * @param $list
     * @return string
     */
    protected function createSign($Md5key, $list)
    {
        ksort($list);
        reset($list); //内部指针指向数组中的第一个元素
        $md5str = "";
        foreach ($list as $key => $val) {
            if (($val !== '' && $val !== []) && $key != 'sign') {
                if(is_array($val)){
                    $md5str = $md5str . $key . "=" . json_encode($val) . "&";
                }else{
                    $md5str = $md5str . $key . "=" . $val . "&";
                }
            }
        }
        $md5str = $md5str . "key=" . $Md5key;
        // var_dump($md5str);exit;
        $sign = strtoupper(md5($md5str));
        //Log::record("创建签名 md5str= ".$md5str.", sign=".$sign, Log::INFO);
        return $sign;
    }
    
    function isNotEmpty($value) {
        return $value !== '' && $value !== null && $value !== false && $value !== [];
    }

    /**
     * 错误返回
     * @param string $msg
     * @param array $fields
     */
    protected function showmessage($msg = '', $fields = array())
    {
        header('Content-Type:application/json; charset=utf-8');
        $data = array('status' => 'error', 'msg' => $msg, 'data' => $fields);
        echo json_encode($data, 320);
        exit;
    }

      /**
     * 成功返回
     * @param string $msg
     * @param array $fields
     */
    protected function successmMessage($fields = array())
    {
        header('Content-Type:application/json; charset=utf-8');
        $data = array('status' => 'success', 'msg' => '成功', 'data' => $fields);
        echo json_encode($data);
        exit;
    }

    //跳转页面
    protected function setHtml($tjurl, $arraystr)
    {
        $str = '<form id="Form1" name="Form1" method="post" action="' . $tjurl . '">';
        foreach ($arraystr as $key => $val) {
            $str .= '<input type="hidden" name="' . $key . '" value="' . $val . '">';
        }
        $str .= '</form>';
        $str .= '<script>';
        $str .= 'document.Form1.submit();';
        $str .= '</script>';
        exit($str);
    }

    //错误返回形式
    protected function returnErrorMsg($msg){

        return array('status'=>0, 'msg'=>$msg);
    }

    //mysql数据库提交
    protected function mysqlCommit($model){
        if(isset($model)){
            $model->execute('commit');
            $model->execute('unlock tables');
            $model->execute('set autocommit=1');
        }
        return true;
    }

    //mysql数据库回滚
    protected function mysqlRollback($model){
        if(isset($model)){
            $model->execute('rollback');
            $model->execute('unlock tables');
            $model->execute('set autocommit=1');
        }
        return false;
    }

     //判断存在的外部订单号的账户参数
    protected function getExistExchangeOrderPayParams($userid, $out_order_id){

        if($userid && $out_order_id){
            $where = "(userid = {$userid} OR aid = {$userid}) AND out_order_id = '{$out_order_id}'";
            $orderInfo = D('ExchangeOrder')->where($where)->find();
            if($orderInfo){
                if($orderInfo['status'] < 3){
                    $payParams = array();
                    if(isset($orderInfo['payparams_id']) && $orderInfo['payparams_id'] > 0){
                        //获取支付参数
                        $payParams = M('payparams_list')->where(['id'=>$orderInfo['payparams_id']])->find();
                    }
                    
                    $otc_orderid = $orderInfo['orderid'];
                    if(isset($otc_orderid)){
                        $payParams['orderid'] = $otc_orderid;
                        // 赋值真实支付金额
                        $payParams['real_amount'] = $orderInfo['real_amount'];
                    }else{
                        $payParams['orderid'] = '';
                    }
                    return $payParams;
                }else{
                    return $this->returnErrorMsg(L('OTC-该订单已经是完成状态-completed, status = '. $orderInfo['status']));
                }
            }
        }
        return false;
    }

    //自动取消一个超时的订单
    protected function autoCancelC2CSellOrderOvertime($sell_userid = 0, $limit = 0){
        if(isset($limit) && is_numeric($limit)){

            $max_time   = 120*60;//最大订单时间
            $max_limit  = 20;    //每个商户最大的获取订单数量
            $all_max_limit = 50; //总体的订单数量
            //7天内的时间
            $dayTime = 7*24*60*60;
            $cur_time = time();
            $start_time = $cur_time-$dayTime;

            $order_where = array();
            $order_where['otype']   = 2; //卖出
            $order_where['status']  = 2; //处理中
            $order_where['notifyurl'] = ['exp','is not null'];
            $order_where['addtime'] = array('between', [$start_time ,$cur_time]);
            $order_where['overtime'] = array('lt', $cur_time);

            //超过最大时间的直接处理
            $limit = $limit < $max_limit ? $limit : $max_limit;

            $order_list = array();
            if($sell_userid > 0){
                //当前用户
                $order_where['userid'] = $sell_userid;
                if($limit > 0){
                    $order_list = D('ExchangeOrder')->where($order_where)->order('id asc')->limit($limit)->select();
                }else{
                    $order_list = D('ExchangeOrder')->where($order_where)->order('id asc')->select();
                }
            }else{

                if($limit > 0){
                    $order_count = 0;
                    //最大时间之内有交易
                    $limit_time = $cur_time - $max_time;
                    $user_where = array(
                        'status'            => 1, //正常账户
                        'idstate'           => 2, //认证通过
                        'kyc_lv'            => ['egt', 2], //通过高级认证
                        'last_exchange_time'=> ['gt', $limit_time],
                    );
                    
                    $user_list = M('user')->where($user_where)->field('id')->select();
                    if(!empty($user_list)){

                        foreach ($user_list as $key => $user) {
                            //当前用户
                            $order_where['userid'] = $user['id'];
                            $temp_order_list = D('ExchangeOrder')->where($order_where)->order('id asc')->limit($limit)->select();

                            if(!empty($temp_order_list)){
                                if($order_count < $all_max_limit){
                                    $order_count = $order_count + count($temp_order_list);
                                    $order_list = array_merge($order_list, $temp_order_list);
                                }else{
                                    break;
                                }
                            }
                        }
                    }
                }else{
                    //所有的超时订单一起处理掉
                    $order_list = D('ExchangeOrder')->where($order_where)->order('id asc')->select();
                }
            }

            //处理过期时间已经到的订单
            if(isset($order_list) && !empty($order_list)) {
                foreach ($order_list as $key => $orderInfo) {
                    $this->cancelC2CSellOrder($orderInfo);
                }
            }

            if(!isset($sell_userid) || !$sell_userid){
                //处理超过最大时间的订单
                unset($order_where['overtime']);
                unset($order_where['userid']);
                $order_where['addtime'] = array('between', [$start_time ,$cur_time-$max_time]);
                if($limit > 0){
                    $max_overtime_order_list = D('ExchangeOrder')->where($order_where)->order('id asc')->limit($limit)->select();
                }else{
                    $max_overtime_order_list = D('ExchangeOrder')->where($order_where)->order('id asc')->select();
                }

                if(isset($max_overtime_order_list) && !empty($max_overtime_order_list)){
                    foreach ($max_overtime_order_list as $key => $orderInfo) {
                        $this->cancelC2CSellOrder($orderInfo);
                    }
                }
            }

            return true;
        }
        return false;
    }

    //获取符合要求用户的支付参数
    protected function getCanUsePayParams($postInfo){
        $amount     = $postInfo['amount'];
        $userid     = $postInfo['userid'];
        $channelid  = $postInfo['channelid'];
        $orderid    = $postInfo['orderid'];
        $agentid    = $postInfo['agentid'] ? $postInfo['agentid'] : $postInfo['userid'];
        $coin_type  = Anchor_CNY; //提现类型
        
        // 获取指定代理下的用户
        $childids = [];
        if ($agentid) {
            $webAdmin = M('PeAdmin')->where(array('id' => 1))->find();
            $peAdmin = M('PeAdmin')->where(array('id' => $agentid))->find();
            if ($peAdmin && $peAdmin['role'] == 1) {
                // 判断是否开启收款
                if ($peAdmin['auto_sell_status'] != 1) {
                    return $this->returnErrorMsg('OTC-商户暂停收款! status is error');
                }
                // 判断供应商保证金
                $agentCoinMargin = M('user_coin_margin')->where(['userid' => $peAdmin['userid']])->find();
                $agentCoinSettle = M('user_coin_settle')->where(['userid' => $peAdmin['userid']])->find();
                if (!$agentCoinMargin) {
                    return $this->returnErrorMsg('OTC-商户保证金不足! margin is error');
                }
                
                $agent_margin_money = $agentCoinMargin[$coin_type] ? $agentCoinMargin[$coin_type] : 0; // 保证金
                $agent_cur_money = $agentCoinSettle[$coin_type] ? $agentCoinSettle[$coin_type] : 0; // 当前余额
                
                // 增加之后的金额
                if ($peAdmin['is_system'] == 1) {
                    $agentFees = $this->getTransFees($peAdmin, $channelid, $amount, 2);
                    $agentReceiveAllFee = $agentFees['commission_fee'] + $agentFees['fixed_fee'];
                    $agent_cur_money += $agentReceiveAllFee;
                } else {
                    $agent_cur_money += $amount;
                }
                
                // 保证金剩余
                $agentCoinLast = round(($agent_cur_money / $agent_margin_money) * 100);
                if ($agentCoinLast >= 80 && $agentCoinLast < 90) {
                    $this->sendMsg($webAdmin['wallet_id'], 'admin-err-101', [$peAdmin['nickname']]); // 通知管理员
                    $this->sendMsg($peAdmin['wallet_id'], 'agent-err-103', []); // 通知供应商
                }
                if ($agentCoinLast >= 90) {
                    $this->sendMsg($webAdmin['wallet_id'], 'admin-err-102', [$peAdmin['nickname']]); // 通知管理员
                    $this->sendMsg($peAdmin['wallet_id'], 'agent-err-104', []); // 通知供应商
                }
                
                if ($agent_cur_money >= floatval($agent_margin_money)) {
                    return $this->returnErrorMsg("OTC-商户保证金不足! margin is error.");
                }
                // if ($peAdmin['is_system'] == 1) {
                //     $agentFees = $this->getTransFees($peAdmin, $channelid, $amount, 2);
                //     $agentReceiveAllFee = $agentFees['commission_fee'] + $agentFees['fixed_fee'];
                //     if (($agent_cur_money + $agentReceiveAllFee) > $agent_margin_money) {
                //         $this->returnErrorMsg("OTC-商户保证金不足! margin is error.");
                //     }
                // } else {
                //     if (($agent_cur_money + $amount) > $agent_margin_money) {
                //         $this->returnErrorMsg("OTC-商户保证金不足! margin is error.");
                //     }
                // }
                
                
                // 检查下级保证金
                $child = M('PeAdmin')->where(['fid' => $peAdmin['id']])->field("userid")->select();
                $allChildids = array_column($child, 'userid');
                
                foreach ($allChildids as $childid) {
                    $curPeAdmim = M('PeAdmin')->where(['userid' => $childid])->find();
                    $userCoinMargin = M('user_coin_margin')->where(['userid' => $childid])->find();
                    $userCoinSettle = M('user_coin_settle')->where(['userid' => $childid])->find();
                    $margin_money = $userCoinMargin[$coin_type] ? $userCoinMargin[$coin_type] : 0; // 保证金
                    $cur_money = $userCoinSettle[$coin_type] ? $userCoinSettle[$coin_type] : 0; // 当前余额
                    
                    if (($cur_money + $amount) <= $margin_money) {
                        array_push($childids, $childid);
                        
                        $userCoinLast = round((($cur_money + $amount) / $margin_money) * 100);
                        if ($userCoinLast >= 80 && $userCoinLast < 90) {
                            $this->sendMsg($peAdmin['wallet_id'], 'agent-err-101', [$curPeAdmim['nickname']]); // 通知供应商
                            $this->sendMsg($curPeAdmim['wallet_id'], 'sale-err-101', []); // 通知业务员
                        }
                        if ($userCoinLast >= 90) {
                            $this->sendMsg($peAdmin['wallet_id'], 'agent-err-102', [$curPeAdmim['nickname']]); // 通知供应商
                            $this->sendMsg($curPeAdmim['wallet_id'], 'sale-err-102', []); // 通知业务员
                        }
                    }
                }
                
                array_push($childids, $peAdmin['userid']);
            }
        }

        //取消过时的C2C卖出订单
        $this->autoCancelC2CSellOrderOvertime(0, 1);
        //判断该通道是否开启
        $open_channel = $this->checkChannelIsOpen($userid, $channelid);
        if($open_channel !== true){
            return $open_channel;
        }
        //选择符合要求的用户
        $user_pay_params = $this->selectC2CUserPayParams($amount, $userid, $channelid, $childids, $peAdmin);
        if(is_array($user_pay_params) && isset($user_pay_params['status']) && $user_pay_params['status'] === 0){
            return $user_pay_params;
        }

        //自动卖出商户CNC
        $orderInfo = $this->autoCreateC2CSellOrder($user_pay_params, $postInfo);
        if(is_array($orderInfo) && isset($orderInfo['status']) && $orderInfo['status'] === 0){
            return $orderInfo;
        }elseif(is_string($orderInfo)){
            $otc_orderid = $orderInfo;
        }else{
            return $this->returnErrorMsg(L('OTC-API创建自动卖出订单失败，请稍后再试！'));
        }

        //取消随机到的商户的过期订单
        $this->autoCancelC2CSellOrderOvertime($user_pay_params['userid'], 1);
        
        //赋值订单号
        if(isset($otc_orderid)){
            $user_pay_params['orderid'] = $otc_orderid;
            $user_pay_params['real_amount'] = D('ExchangeOrder')->where("orderid = {$otc_orderid}")->find()['real_amount'];
        }else{
            $user_pay_params['orderid'] = '';
        }

        return $user_pay_params;
    }

    //选择符合要求的用户账户参数
    protected function selectC2CUserPayParams($amount, $userid, $channelid, $childids=[], $agentInfo = []){

        //支付类型是否打开
        $user_pay_params = [];
        $paytype_config = M('paytype_config')->where(['channelid'=> $channelid])->find();
        $payStatus = $paytype_config['status'];
        if($payStatus){

            //通道风控暂时关闭
            // $l_PayTypeRiskcontrol = new \Pay\Logic\PayTypeRiskcontrolLogic($amount);
            // $l_PayTypeRiskcontrol->setConfigInfo($paytype_config); //设置配置属性
            // $error_msg = $l_PayTypeRiskcontrol->monitoringData();
            // if ($error_msg !== true) {
            //     return $this->returnErrorMsg($error_msg);
            // }

            /** 检查设置条件 **/
            $exchange_config = M('exchange_config')->where(array('id' => 1))->find();
            
            if($exchange_config['mytx_status'] == 0){
                return $this->returnErrorMsg(L('OTC-网络繁忙，请稍后再试！'));
            }
            //单价
            $price = $exchange_config['mytx_uprice'];
            $coin_num = round($amount / $price, 4);
            //Log::record("selectCNCUser coin_num= ".$coin_num.' price = '.$price, Log::INFO);

            $where = [];
            if ($childids) {
                $where['tw_user.id'] = ['in', $childids];
            }
            //筛选货币满足要求的用户
            $user_price_list = M('user')
                    ->join('LEFT JOIN __USER_COIN__ ON __USER__.id = __USER_COIN__.userid')
                    ->where($where)
                    ->where(array('tw_user.auto_c2c_sell_status'=>1, 'tw_user.status'=>1, 'tw_user.kyc_lv'=>2, 'tw_user.idstate'=>2, 'tw_user_coin.'.Anchor_CNY => ['egt', $coin_num]))
                    ->field('tw_user.id,tw_user.last_exchange_time,tw_user.paying_money,tw_user.all_money,tw_user.select_channelid')
                    ->select();
                    
            
// var_dump('tw_user_coin.'.Anchor_CNY);exit;
            // Log::record("user_price_list = ".json_encode($user_price_list), Log::INFO);
            //从数据库获取符合要求的账户信息
            $payparams_list = $this->getMatchAllPayParamsFormDb($userid, $channelid, $paytype_config);
            // Log::record("payparams_list = ".json_encode($payparams_list), Log::INFO);

            if(!empty($payparams_list) && !empty($user_price_list)){

                //子账户单商户用户风控
                $l_PayParamsRiskcontrol= new \Pay\Logic\PayParamsRiskcontrolLogic($amount);
                //筛选出不合适的参数
                foreach ($payparams_list as $key => $v) {

                    //判断是自定义还是继承渠道的风控
                    $temp_info               = $v['is_defined'] ? $v : $paytype_config;
                    $temp_info['params_id']  = $v['id']; //用于子账号风控类继承渠道风控机制时修改数据的id

                    //子账号风控
                    $l_PayParamsRiskcontrol->setConfigInfo($temp_info);

                    $error_msg = $l_PayParamsRiskcontrol->monitoringData();

                    if ($error_msg !== true) {
                        unset($payparams_list[$key]);
                    }
                    
                    // 账户风控
                    if ($v['min_money'] != 0 && $v['max_money'] != 0) {
                        if ($amount < $v['min_money'] || $amount > $v['max_money']) { // 最大最小金额
                            unset($payparams_list[$key]);
                        }
                    }
                    
                    // 当日总金额
                    if ($v['all_money'] != 0) {
                        if (($amount + $v['paying_money']) > $v['all_money']) { // 每日最大金额
                            unset($payparams_list[$key]);
                        }
                    }
                    
                    
                }

                if(empty($payparams_list)){
                    $this->sendMsg(
                        $agentInfo['wallet_id'],
                        'agent-err-301',
                        [Anchor_CNY, $paytype_config['channel_title'], $coin_num, Anchor_CNY, $paytype_config['channel_title']]
                    ); // 通知供应商
                    // return $this->returnErrorMsg($error_msg);
                    return $this->returnErrorMsg('OTC-params match payparms is null, channelid = '. $channelid .' ,userid = '. $userid);
                }
                
                //用户风控
                $l_UserRiskcontrol = new \Pay\Logic\UserRiskcontrolLogic($amount, $channelid);
                //组合只有userid的array
                $userid_price_list = [];
                foreach ($user_price_list as $key => $userinfo) {

                    $l_UserRiskcontrol->setConfigInfo($userinfo);
                    $error_msg = $l_UserRiskcontrol->monitoringData();
                    if ($error_msg === true) {
                        $userid_price_list[] = $userinfo['id'];
                    } else {
                        Log::record("UserRiskcontrolLogic = ".json_encode($userinfo).', error_msg = '.$error_msg, Log::INFO);
                    }
                }

                if(empty($userid_price_list)){
                    $this->sendMsg(
                        $agentInfo['wallet_id'],
                        'agent-err-301',
                        [Anchor_CNY, $paytype_config['channel_title'], $coin_num, Anchor_CNY, $paytype_config['channel_title']]
                    ); // 通知供应商
                    
                    return $this->returnErrorMsg($error_msg);
                }
                
                
                
                //筛选符合要求的支付参数
                $match_payparams_list = [];
                foreach ($payparams_list as $key => $payparams) {
                    if(in_array($payparams['userid'], $userid_price_list)){
                        $match_payparams_list[] = $payparams;
                    }
                }
                
                //根据时间筛选支付账号
                $match_payparams_list = $this->autoSelectPayParamsWithTime($match_payparams_list);

                if(!empty($match_payparams_list)){
                    //根据权重获取账户
                    $user_pay_params = $this->getMatchSuccessRatePayParams($match_payparams_list, $amount);
                }   
            }
        }

        if(empty($user_pay_params)){
            if(empty($user_price_list)){
                $this->sendMsg(
                    $agentInfo['wallet_id'],
                    'agent-err-301',
                    [Anchor_CNY, $paytype_config['channel_title'], $coin_num, Anchor_CNY, $paytype_config['channel_title']]
                ); // 通知供应商
                
                return $this->returnErrorMsg('OTC-user amount or auto_c2c_sell_status is error, channelid = '. $channelid .' ,userid = '. $userid);
                //return $this->returnErrorMsg('OTC-user amount or auto_c2c_sell_status is error, channelid = '. $channelid .' ,userid = '. $userid. ' user_price_list = '.json_encode($user_price_list));
            }elseif(empty($payparams_list)){
                $this->sendMsg(
                    $agentInfo['wallet_id'],
                    'agent-err-301',
                    [Anchor_CNY, $paytype_config['channel_title'], $coin_num, Anchor_CNY, $paytype_config['channel_title']]
                ); // 通知供应商
                
                return $this->returnErrorMsg('OTC-params match payparms is null, channelid = '. $channelid .' ,userid = '. $userid);
                //return $this->returnErrorMsg('OTC-params match payparms is null, channelid = '. $channelid .' ,userid = '. $userid. ' user_price_list = '.json_encode($user_price_list));
            }else{
                $this->sendMsg(
                    $agentInfo['wallet_id'],
                    'agent-err-301',
                    [Anchor_CNY, $paytype_config['channel_title'], $coin_num, Anchor_CNY, $paytype_config['channel_title']]
                ); // 通知供应商
                
                return $this->returnErrorMsg('OTC-have not match user info, channelid = '. $channelid .' ,userid = '. $userid);
                //return $this->returnErrorMsg('OTC-have not match user info, channelid = '. $channelid .' ,userid = '. $userid.' user_price_list = '. json_encode($user_price_list) . ' payparams_list = '. json_encode($payparams_list));
            }
        }else{
            if($user_pay_params['weight'] != $user_pay_params['before_weight']){
                //更新惩罚时间
                R('Pay/PayUpdate/updatePunishmentTimeAndStatus', array($user_pay_params));
            }
        }

        return $user_pay_params;
    }

    //判断用户是否开启该通道
    protected function checkChannelIsOpen($userid, $channelid){
        //判断用户该通道是否可开启
        $channelid_list = array();
        $select_channelid = M('user')->where(array('id' => $userid))->getField('select_channelid');
        if(!empty($select_channelid)){
            $channelid_list = explode(',', $select_channelid);
        }
        if(!in_array($channelid, $channelid_list)){
            return $this->returnErrorMsg(L('OTC-买家用户未开通该通道, user is not open this channel! channelid = '.$channelid));
        }
        return true;
    }

    //从数据库Db获取符合要求的用户账户参数
    protected function getMatchAllPayParamsFormDb($userid, $channelid, $paytype_config){

        $payparams_list = array();
        if(isset($userid) && isset($channelid)){

            $payParamsWhere = array(
                // 'channelid'         => $channelid,
                // 'channelid'         => ['like', "%{$channelid}%"],
                'status'            => 1,
                'check_status'      => 1,
                'receive_status'    => 1,
                'is_manual_account' => 0, // 不是手动交易账户
                'userid'            => ['neq', $userid], //自己不能购买自己的订单
                '_string'           => "paying_num < all_pay_num AND FIND_IN_SET('{$channelid}', channelid) > 0", // 还可以继续调用的账户
            );

            //不是自动通知的账户类型在10秒内不能有重复订单
            // if(!isset($paytype_config['is_auto_notify']) || !$paytype_config['is_auto_notify']){
            //     $payParamsWhere['last_paying_time'] = ['lt', time()-10]; //10秒
            // }
            //指定账户里面选择
            $payParamsWhere['select_memberid'] = $userid;
            //筛选支持该支付类型的用户的参数
            $payparams_list = M('payparams_list')->where($payParamsWhere)->select();

            if(empty($payparams_list)){
                //不存在指定账户，使用混用账户
                $payParamsWhere['select_memberid'] = '0';
                //权重高的账户
                $payParamsWhere['weight']          = ['gt', 10];
                $high_payparams_list = M('payparams_list')->where($payParamsWhere)->limit(60)->order('rand()')->select();

                if(!empty($high_payparams_list)){
                     //普通账户
                    $payParamsWhere['weight']          = ['elt', 10];
                    $low_payparams_list = M('payparams_list')->where($payParamsWhere)->limit(40)->order('rand()')->select();

                    if(!empty($low_payparams_list)){
                        $payparams_list = array_merge($high_payparams_list, $low_payparams_list);
                    }else{
                        $payparams_list = $high_payparams_list;
                    }
                }else{
                    unset($payParamsWhere['weight']);
                    $payparams_list = M('payparams_list')->where($payParamsWhere)->limit(100)->order('rand()')->select();
                }
            }
        }
        return $payparams_list;
    }

    // 获取匹配成功率的账户
    protected function getMatchSuccessRatePayParams($payparams_list, $amount){
        
        if(count($payparams_list) >= 3){
            //先进行权重随机
            $weight_payparams = array();
            for ($i=0; $i < 3; $i++) { 
                $pay_params = getWeight($payparams_list);
                array_push($weight_payparams, $pay_params);
            }
            
            //成功率最大的索引
            $max_success_index = 0;
            $max_success_rate = 0;        
            foreach ($weight_payparams as $key => $pay_params) {
                if(isset($payparams['success_rate_list'])){
                    $successRateList = json_decode($payparams['success_rate_list'], true);
                    $index = 0;
                    if(!empty($successRateList)){
                        foreach ($successRateList as $key => $successRate) {
                            if($successRate[0] < $amount && $successRate[1] >= $amount){
                                if($successRate[2] > $max_success_rate){
                                    $max_success_rate = $successRate[2];
                                    $max_success_index = $index;
                                }
                            }
                            $index++;
                        }
                    }
                }
            }
            return isset($weight_payparams[$max_success_index]) ? $weight_payparams[$max_success_index] : [];
        }
        return getWeight($payparams_list);
    }

    //判断支付参数当前是否可用
    protected function autoSelectPayParamsWithTime($payparams_list){
        //长时间没有订单的账户列表
        $longTimeNoOrderPayParams = array(); 
        $cur_time = time();
        
        if(!empty($payparams_list)){
            $long_time = 20 * 60; //20分钟算长时间
            shuffle($payparams_list); //数组打乱
            foreach ($payparams_list as $key => $payParams) {
                
                if($payParams['last_paying_time'] < $cur_time-$long_time && $payParams['weight'] > 1){ //超过20分钟无订单

                    array_push($longTimeNoOrderPayParams, $payparams_list[$key]);
                    break;
                }
            }
        }
        if(empty($longTimeNoOrderPayParams)){
            return $payparams_list;
        }else{
            return $longTimeNoOrderPayParams;
        }
    }

    //获取过期时间
    protected function getC2COrderOverTime($userinfo){
        $overtime = 30*60;
        if(isset($userinfo) && is_array($userinfo)){

            switch ($userinfo['cancal_c2c_level']) {
                case '1':
                    $overtime = 10*60;
                    break;
                case '2':
                    $overtime = 120*60;
                    break;
                default:
                    $overtime = 30*60;
                    break;
            }
        }
        return $overtime;
    }

    //自动卖出商户C2C订单
    protected function autoCreateC2CSellOrder($user_pay_params, $postInfo){

        $buy_userid     = $postInfo['userid'];
        $channelid      = $postInfo['channelid']; 
        $amount         = $postInfo['amount']; 
        $out_order_id   = $postInfo['orderid'];
        $notifyurl      = $postInfo['notifyurl'];
        $callbackurl    = isset($postInfo['callbackurl'])?$postInfo['callbackurl']:null;
        if(!$user_pay_params || !is_array($user_pay_params) || !$amount || !$out_order_id){
            return $this->returnErrorMsg('OTC-生成卖出订单所需要的信息不完整');
        }

        if(!$notifyurl){
            return $this->returnErrorMsg('OTC-请求卖出订单未上传notifyurl');
        }

        //卖出的用户id
        $sell_userid = $user_pay_params['userid'];

        $coin_type  = Anchor_CNY; //提现类型
        $otype      = 2; //卖出（平台商为卖，api用户为买）
        $cur_time   = time();
        
        /** 检查设置条件 **/
        $exchange_config = M('exchange_config')->where(array('id' => 1))->find();
        if($exchange_config['mytx_status'] == 0){ //是否开启了CNC交易
            return $this->returnErrorMsg(L('OTC-网络繁忙，请稍后再试！'));
        }

        $userinfo = M('user')->where(array('id' => $sell_userid))->find();
        if(!$userinfo){
            return $this->returnErrorMsg(L('OTC-卖家用户信息不存在！'));
        }
        //订单过期时间
        $overtime = $this->getC2COrderOverTime($userinfo);
        $overtime = $overtime > 0 ? ($cur_time+$overtime) : ($cur_time+30*60);

        $paytype_config = M('paytype_config')->where(['channelid'=>$channelid])->find();
        if(empty($paytype_config)){
            return $this->returnErrorMsg(L("OTC-创建订单的账户类型不存在channelid = {$channelid}！"));
        }
        //净费率
        $auto_buy_rate = $paytype_config['auto_buy_rate'];
        //优惠
        $sale_sell_rate = $paytype_config['sale_sell_rate'];
        if($auto_buy_rate < $sale_sell_rate){
            return $this->returnErrorMsg(L('OTC-费率填写错误，费率必须大于优惠！ error:buy_rate < sell_rate, channelid = ' . $channelid));
        }

        //配置金额
        $config_price = $exchange_config['mytx_uprice'];
        if(!$config_price){
            return $this->returnErrorMsg(L('OTC-配置的卖出金额异常！'));
        }
        //单价
        $price = $config_price * ($sale_sell_rate + 1);
        //数量
        $num = $amount/$price;
        /** 实际到账金额 **/
        $mum = $amount;
        //优惠折扣金额
        $all_scale = $amount - ($config_price * $num);
        $all_scale = $all_scale > 0?$all_scale:0;
        //计算实际的净费率
        $fee = ($amount * $auto_buy_rate) - $all_scale;
        $fee = $fee > 0?$fee:0;
        //防止篡改
        $mum        = abs($mum);
        $num        = abs($num);
        $fee        = abs($fee);
        $all_scale  = abs($all_scale);

        /** 帐号金额 **/
        $user_coin = M('user_coin')->where(array('userid' => $sell_userid))->find();
        if ($user_coin[$coin_type] < $num) {
            return $this->returnErrorMsg('OTC-'. $coin_type . L('余额不足！ user amount is too little'));
        }

        //缩短显示信息
        foreach ($user_pay_params as $key => $value) {
            if(is_string($value) && strlen($value) > 25){
                $user_pay_params[$key] = substr($value, 0, 24).'...';
            }
        }

        try{

            $curExchangeOrderTableName = D('ExchangeOrder')->getCurExhcangeOrderTableName();

            if($curExchangeOrderTableName){

                //处理上级的分成逻辑
                $invit_amount = $this->addInvitProfits($all_scale, $userinfo, $coin_type, $exchange_config, false);

                $mo = M();
                $mo->execute('set autocommit=0');
                $mo->execute("lock tables {$curExchangeOrderTableName} write, tw_user write, tw_user_coin write, tw_finance write, tw_finance_log write, tw_payparams_list write");
                
                $rs = array();

                $orderData = array(
                    'otype'         => $otype,
                    'userid'        => $sell_userid,
                    'orderid'       => build_exchange_order_no($cur_time),
                    'out_order_id'  => $out_order_id,
                    'remarks'       => '自动卖出订单', 
                    'notifyurl'     => $notifyurl,
                    'callbackurl'   => $callbackurl,
                    'uprice'        => $price, 
                    'num'           => $num, 
                    'mum'           => $mum, 
                    'fee'           => $fee,
                    'all_scale'     => $all_scale,
                    'scale_amount'  => $all_scale-$invit_amount,
                    'type'          => $coin_type, 
                    'aid'           => $buy_userid, 
                    'pay_channelid' => $channelid,
                    'payparams_id'  => $user_pay_params['id'],
                    'truename'      => $user_pay_params['truename'], //账户名
                    'bank'          => $user_pay_params['mch_id'], //类型名称
                    'bankcard'      => $user_pay_params['appid'],  //账户号
                    'bankaddr'      => $user_pay_params['signkey'], //附加信息
                    'bankprov'      => $user_pay_params['appsecret'], 
                    'bankcity'      => $user_pay_params['subject'],
                    'overtime'      => $overtime, //过期时间
                    'status'        => 2,
                );

                $check_res = $this->checkC2CSellOrderAmount($paytype_config, $exchange_config, $otype, $orderData['num'], $orderData['mum'], $orderData['all_scale'], $orderData['fee']);
                if($check_res !== true){
                    $this->mysqlRollback($mo);
                    return $check_res;
                }

                $rs[] = $otc_orderid = $this->addExchangeOrder($mo, $curExchangeOrderTableName, $orderData, $cur_time, $paytype_config);
                
                // // 如果开启了随机金额
                // if ($paytype_config['is_random_money'] == 1) {
                //     $rs[] = $this->addOrderRandomAmount([
                //         'orderid' => $otc_orderid,
                //         'addtime' => $cur_time,
                //         'bankcard' => $user_pay_params['appid'],
                //         'mum' => $mum,
                //         'paytype_config' => $paytype_config
                //     ]);
                // }
                
                // 调用成功增加一次调用次数
                $rs[] = $mo->table('tw_payparams_list')->where(array('id' => $user_pay_params['id']))->setInc('paying_num', 1);
                if($otc_orderid){
                    // （暂时不扣除资金，改为成功后扣除资金）
                    // 用户账户数据处理
                    //$rs[] = $mo->table('tw_user_coin')->where(array('userid' => $sell_userid))->setDec($coin_type, $num); // 修改金额
                    //创建自动卖出订单扣除cnc
                    //$remarks = '创建自动卖出订单扣除cnc';
                    //$rs[] = $this->addFinanceLog($orderData, $sell_userid, $remarks, 0, 4, false);
                } else {
                    $this->mysqlRollback($mo);
                    return $this->returnErrorMsg(L('OTC-订单已达上线'));
                }

                if(check_arr($rs)) {

                    $this->mysqlCommit($mo);
                    $this->afterAddExchangeOrder($orderData, $user_pay_params);
                    return $otc_orderid;
                } else {

                    $this->mysqlRollback($mo);
                    return $this->returnErrorMsg(L('OTC-卖出订单创建失败11'));
                }
            }else{
                $this->mysqlRollback($mo);
                return $this->returnErrorMsg(L('OTC-卖出订单创建失败22'));
            }
        }catch(\Think\Exception $e){

            $this->mysqlRollback($mo);
            return $this->returnErrorMsg(L('OTC-卖出订单创建失败33'));
        }
    }

    //通过api自动买入订单
    protected function autoCreateC2CBuyOrderWithApi($postInfo){

        if(isset($postInfo) && is_array($postInfo)){
            
            $sell_userid    = $postInfo['userid'];
            $channelid      = $postInfo['channelid'];
            $amount         = $postInfo['amount'];
            $agentid        = $postInfo['agentid'] ? $postInfo['agentid'] : $postInfo['userid'];
            $coin_type      = Anchor_CNY; //提现类型
            $user_pay_params = array();
            $user_pay_params['truename']    = $postInfo['truename'];
            $user_pay_params['mch_id']      = $postInfo['bank'];
            $user_pay_params['appid']       = $postInfo['bankcard'];
            $user_pay_params['signkey']     = $postInfo['bankaddr'];  //附加信息
            $user_pay_params['appsecret']   = $postInfo['bankprov'];  //省
            $user_pay_params['subject']     = $postInfo['bankcity'];  //城市
            
            //筛选满足要求的用户(只有为完全信任的用户才能接取自动买入订单【代付】)
            $where = array('auto_c2c_buy_status'=>1, 'status'=>1, 'kyc_lv'=>2, 'idstate'=>2,'cancal_c2c_level'=>1, 'id'=>['neq', $sell_userid]);
            if(!$agentid){
                return $this->returnErrorMsg('OTC-未知的商户买入。agent error');
            }
            
            if (!$channelid) {
                return $this->returnErrorMsg('OTC-未知通道。channel error');
            }
            // $buy_userid = M('user')->where($where)->order('rand()')->getField('id');
            
            // 获取指定代理下的用户
            if ($agentid) {
                $webAdmin = M('PeAdmin')->where(array('id' => 1))->find();
                $peAdmin = M('PeAdmin')->where(array('id' => $agentid))->find();
                if ($peAdmin && $peAdmin['role'] == 1) {
                    
                    // 判断是否开启出款
                    if ($peAdmin['auto_buy_status'] != 1) {
                        return $this->returnErrorMsg('OTC-商户暂停付款! status is error');
                    }
                    
                    $agentCoinMargin = M('user_coin_margin')->where(['userid' => $peAdmin['userid']])->find();
                    $agentCoinSettle = M('user_coin_settle')->where(['userid' => $peAdmin['userid']])->find();
                    if (!$agentCoinMargin) {
                        return $this->returnErrorMsg('OTC-商户保证金不足! margin is error');
                    }
                    
                    $agent_margin_money = $agentCoinMargin[$coin_type] ? $agentCoinMargin[$coin_type] : 0; // 保证金
                    $agent_cur_money = $agentCoinSettle[$coin_type] ? $agentCoinSettle[$coin_type] : 0; // 当前余额
                    
                    // 系统服务费模式需要验证保证金
                    if ($peAdmin['is_system'] == 1) {
                        $agentFees = $this->getTransFees($peAdmin, $channelid, $amount, 2);
                        $agentReceiveAllFee = $agentFees['commission_fee'] + $agentFees['fixed_fee'];
                        
                        
                        // 保证金剩余
                        $agentCoinLast = round(($agent_cur_money + $agentReceiveAllFee) / $agent_margin_money * 100);
                        if ($agentCoinLast >= 80 && $agentCoinLast < 90) {
                            $this->sendMsg($webAdmin['wallet_id'], 'admin-err-101', [$peAdmin['nickname']]); // 通知管理员
                            $this->sendMsg($peAdmin['wallet_id'], 'agent-err-103', []); // 通知供应商
                        }
                        if ($agentCoinLast >= 90) {
                            $this->sendMsg($webAdmin['wallet_id'], 'admin-err-102', [$peAdmin['nickname']]); // 通知管理员
                            $this->sendMsg($peAdmin['wallet_id'], 'agent-err-104', []); // 通知供应商
                        }
                        
                        if (($agent_cur_money + $agentReceiveAllFee) > $agent_margin_money) {
                            return $this->returnErrorMsg("OTC-商户保证金不足! margin is error.");
                        }
                    }
                    
                    $child = M('PeAdmin')->where(['fid' => $peAdmin['id']])->field("userid")->select();
                    $childids = array_column($child, 'userid');
                    array_push($childids, $peAdmin['userid']);
                    // 获取所有可用的付款账户
                    $payparams = $this->getChildMatchBuyPayParams($childids, $postInfo);
                    
                    if (isset($payparams['status']) && $payparams['status'] == 0) {
                        // 未匹配到账户则随机分配给一个用户
                        $where['id'] = ['in', $childids];
                        
                        $buy_userid = M('user')->where($where)->order('rand()')->getField('id');
                        // return $this->returnErrorMsg('OTC-未匹配到付款账户');
                    } else {
                        $allPayparamsid = array_column($payparams, 'id');
                        // 随机分配一个账户
                        $selectParams = M('payparams_list')->where(['id' => ['in', $allPayparamsid]])->order('rand()')->find();
                        $buy_userid = $selectParams['userid'];
                        $user_pay_params['id'] = $selectParams['id'];
                    }
                    
                    
                } else {
                    return $this->returnErrorMsg('OTC-未知的商户买入。agent error222');
                }
            }
            
            if(!$buy_userid){
                return $this->returnErrorMsg('OTC-生成自动代付订单，缺少对应的商户买入。match is fail, buy_userid is null1');
            }
            $user_pay_params['userid'] = $buy_userid;
            $user_pay_params['channelid'] = $channelid;

            return $this->autoCreateC2CBuyOrder($user_pay_params, $postInfo);
        }else{
            return $this->returnErrorMsg('OTC-请求参数不存在。post params is null');
        }
    }
    
    // 查询可分配的用户ID
    protected function getChildMatchBuyPayParams ($childids = [], $postInfo)
    {
        $amount         = $postInfo['amount'];
        $sell_userid    = $postInfo['userid'];
        $channelid      = $postInfo['channelid'];
        $allocable = [];
        
        if (count($childids) == 0) {
            return $this->returnErrorMsg('OTC-未匹配到付款账户');
        }
        
        // 查询所有可以付款的账户
        $where = [];
        $where['tw_payparams_list.userid'] = array('in', $childids);
        $where['tw_payparams_list.min_money'] = array('elt', $amount);
        $where['tw_payparams_list.max_money'] = array('egt', $amount);
        $where['tw_payparams_list.status'] = 1;
        $where['tw_payparams_list.check_status'] = 1;
        $where['tw_payparams_list.payment_status'] = 1;
        // $where['tw_payparams_list.channelid'] = $channelid;
        // $where['tw_payparams_list.channelid'] = ['like', "%{$channelid}%"];
        $where['_string'] = "tw_payparams_list.paying_num < tw_payparams_list.all_pay_num AND tw_payparams_list.amount > {$amount} AND FIND_IN_SET('{$channelid}', channelid) > 0";
        $payparams = M('payparams_list')
            ->join('LEFT JOIN __USER__ ON __USER__.id = __PAYPARAMS_LIST__.userid')
            ->where(array('tw_user.auto_c2c_buy_status'=>1, 'tw_user.status'=>1, 'tw_user.kyc_lv'=>2, 'tw_user.idstate'=>2,'tw_user.cancal_c2c_level'=>1, 'tw_user.id'=>['neq', $sell_userid]))
            ->where($where)
            ->field('tw_payparams_list.*')
            ->select();
            
        if (!$payparams) {
            return $this->returnErrorMsg('OTC-未匹配到付款账户');
        }
        
        return $payparams;
    }

    //自动创建购买c2c的订单
    protected function autoCreateC2CBuyOrder($user_pay_params, $postInfo)
    {
        $channelid      = isset($user_pay_params['channelid'])? $user_pay_params['channelid']:0; 
        //支付参数ID
        $payParams_id   = isset($user_pay_params['id'])? $user_pay_params['id']:0;
        $amount         = $postInfo['amount'];
        $total_fee      = $postInfo['fee'];
        $sell_userid    = $postInfo['userid'];
        $out_order_id   = $postInfo['orderid'];
        $notifyurl      = $postInfo['notifyurl'];
        $callbackurl    = isset($postInfo['callbackurl'])?$postInfo['callbackurl']:null;

        if(!$user_pay_params || !is_array($user_pay_params) || !$amount || !$out_order_id){
            return $this->returnErrorMsg('OTC-生成买入订单所需要的信息不完整');
        }

        //买入的用户id
        $buy_userid = $user_pay_params['userid'];
        $coin_type  = Anchor_CNY; //提现类型
        $otype      = 1; //买入（平台商为买，api用户为卖）
        $cur_time   = time();

        /** 检查设置条件 **/
        $exchange_config = M('exchange_config')->where(array('id' => 1))->find();
        if($exchange_config['mytx_status'] == 0){ //是否开启了CNC交易
            return $this->returnErrorMsg(L('OTC-exchange_config 网络繁忙，请稍后再试！'));
        }

        //自动代付的平台固定金额手续费
        $auto_df_buy_min_amount = isset($exchange_config['auto_df_buy_min_amount'])?$exchange_config['auto_df_buy_min_amount']:0;
        //自动代付的买入费率
        $auto_df_buy_min_rate   = isset($exchange_config['auto_df_buy_min_rate'])?$exchange_config['auto_df_buy_min_rate']:0;
        //自动代付的平台手续费
        $auto_df_platfrom_rate = isset($exchange_config['auto_df_platfrom_rate'])?$exchange_config['auto_df_platfrom_rate']:0;

        if(!$auto_df_buy_min_rate && !$auto_df_buy_min_amount){
            return $this->returnErrorMsg(L('OTC-平台没有配置最低手续费比例- have not buy fee rate！'));
        }

        if(!$auto_df_platfrom_rate){
            return $this->returnErrorMsg(L('OTC-平台没有配置平台手续费比例- have not platfrom fee rate！'));
        }

        if($auto_df_platfrom_rate >= 1){
            return $this->returnErrorMsg(L('OTC-平台手续费过高，超过100%- platfrom fee rate is too high，beyond 100%！'));
        }

        //最低手续费
        $low_fee = $amount * $auto_df_buy_min_rate+$auto_df_buy_min_amount;
        if($total_fee < $low_fee){
           return $this->returnErrorMsg("OTC-手续费不能低于最低手续费={$low_fee},当前为{$total_fee}!");
        }

        //单价
        $config_price = $exchange_config['mycz_uprice'];
        if(!$config_price){
            return $this->returnErrorMsg(L('OTC-配置的买入金额异常！'));
        }
        //数量
        $num = $amount/$config_price;
         //手续费的数量
        $total_fee_num   = $total_fee/$config_price;
        //平台手续费
        $fee = $total_fee * $auto_df_platfrom_rate;
        $fee = $fee > 0?$fee:0;
        //优惠金额
        $all_scale = $total_fee - $fee;
        $all_scale = $all_scale > 0?$all_scale:0;
        /** 实际到账金额 **/
        $mum = $amount;

        //防止篡改
        $mum        = abs($mum);
        $num        = abs($num);
        $fee        = abs($fee);
        $all_scale  = abs($all_scale);

        $userinfo = M('user')->where(array('id' => $buy_userid))->find();
        if(!$userinfo){
            return $this->returnErrorMsg(L('OTC-买家用户信息不存在！'));
        }
        //订单过期时间
        $overtime = $this->getC2COrderOverTime($userinfo);
        $overtime = $overtime > 0 ? ($cur_time+$overtime) : ($cur_time+30*60);

        //整个减少的货币数量
        $total_coin = $num+$total_fee_num;
        /** 帐号金额 **/
        $user_coin = M('user_coin')->where(array('userid' => $sell_userid))->find();
        if ($user_coin[$coin_type] < $total_coin) {
            return $this->returnErrorMsg('OTC-'. $coin_type . '余额不足！ user amount is too little');
        }

        try{

            $curExchangeOrderTableName = D('ExchangeOrder')->getCurExhcangeOrderTableName();

            if($curExchangeOrderTableName){
                $mo = M();
                $mo->execute('set autocommit=0');
                $mo->execute("lock tables {$curExchangeOrderTableName} write, tw_user write, tw_user_coin write, tw_finance write, tw_finance_log write");
                $rs = array();

                //处理上级的分成逻辑
                $invit_amount = $this->addInvitProfits($all_scale, $userinfo, $coin_type, $exchange_config, false);

                $orderData = array(
                    'otype'         => $otype, 
                    'userid'        => $buy_userid,
                    'orderid'       => build_exchange_order_no($cur_time),
                    'out_order_id'  => $out_order_id,
                    'remarks'       => '自动买入订单', 
                    'notifyurl'     => $notifyurl,
                    'callbackurl'   => $callbackurl,
                    'uprice'        => $config_price, 
                    'num'           => $num, 
                    'mum'           => $mum, 
                    'fee'           => $fee,
                    'all_scale'     => $all_scale,
                    'scale_amount'  => $all_scale-$invit_amount,
                    'type'          => $coin_type, 
                    'aid'           => $sell_userid,
                    'pay_channelid' => $channelid,
                    'payparams_id'  => $payParams_id,
                    'truename'      => $user_pay_params['truename'], //账户名
                    'bank'          => $user_pay_params['mch_id'],  //类型名称
                    'bankcard'      => $user_pay_params['appid'],   //账户号
                    'bankaddr'      => $user_pay_params['signkey'], //附加信息
                    'bankprov'      => $user_pay_params['appsecret'], 
                    'bankcity'      => $user_pay_params['subject'],
                    'overtime'      => $overtime, //过期时间
                    'status'        => 1,
                );

                $rs[] = $otc_orderid = $this->addExchangeOrder($mo, $curExchangeOrderTableName, $orderData, $cur_time);
                
                if($otc_orderid){
                    // 用户账户数据处理
                    $rs[] = $res_coin = $mo->table('tw_user_coin')->where(array('userid' => $sell_userid))->setDec($coin_type, $total_coin); // 修改金额
                    //创建自动买入订单扣除cnc
                    $remarks = '创建自动买入订单扣除cnc手续费';
                    $rs[] = $this->addFinanceLog($orderData, $sell_userid, $remarks, 0, 4, false);
                }

                if (check_arr($rs)) {

                    $this->mysqlCommit($mo);
                    $this->afterAddExchangeOrder($orderData);
                    return $otc_orderid;
                } else {

                    $this->mysqlRollback($mo);
                    //if($res_coin)M('user_coin')->where(array('userid' => $sell_userid))->setInc($coin_type, $total_coin); // 修改金额
                    return $this->returnErrorMsg(L('OTC-自动买入订单创建失败！'));
                }
            }else{
                $this->mysqlRollback($mo);
                return $this->returnErrorMsg(L('OTC-自动买入订单创建失败222！'));
            }
        }catch(\Think\Exception $e){

            $this->mysqlRollback($mo);
            return $this->returnErrorMsg(L('OTC-自动买入订单创建失败333！'));
        }
    }

    /**
     * 添加C2C交易订单
     * @param model      数据库操作对象
     * @param tableName  操作表名
     * @param orderData  订单数据
     * @return  orderid
     */
    private function addExchangeOrder($model, $tableName, $orderData, $addtime, $paytype_config = false){

        if($model && $tableName && is_array($orderData)){
            $addtime = $addtime?$addtime:time();
            //更新用户的交易最后交易时间
            if(isset($orderData['userid'])){
                $model->table('tw_user')->where(['id'=>$orderData['userid']])->save(['last_exchange_time'=>$addtime]);
            }
            if(isset($orderData['aid'])){
                $model->table('tw_user')->where(['id'=>$orderData['aid']])->save(['last_exchange_time'=>$addtime]);
            }
            if(isset($orderData['aid']))

            if(!isset($orderData['orderid'])){
                $orderData['orderid']   = build_exchange_order_no($addtime);
            }
            if(!isset($orderData['out_order_id'])){
                $orderData['out_order_id'] = $orderData['orderid'];
            }
            $orderData['addtime']   = $addtime;
            $orderData['updatetime']= $addtime;
            
            // 如果开启了随机金额
            if ($paytype_config['is_random_money'] == 1) {
                $real_amount = $this->addOrderRandomAmount([
                    'orderid' => $orderData['orderid'],
                    'addtime' => $addtime,
                    'bankcard' => $orderData['bankcard'],
                    'mum' => $orderData['mum'],
                    'paytype_config' => $paytype_config
                ]);
                
                if (!$real_amount) {
                    return false;
                    
                }
                $orderData['real_amount'] = $real_amount;
            }
            $res = $model->table($tableName)->add($orderData);
            if($res){
                return $orderData['orderid'];
            }else{
                return false;
            }
            
        }
        return false;
    }

    //添加C2C订单后的处理逻辑
    protected function afterAddExchangeOrder($orderData, $payparams=null){

        if(!empty($orderData)){
            $cur_time = time();
            //账户参数的判断
            if(isset($orderData['payparams_id']) && $orderData['payparams_id'] > 0){
                $payparams_id = $orderData['payparams_id'];
                $data = array();
                if(isset($payparams)){
                    //最大失败次数增加
                    // $data['max_fail_count'] = $payparams['max_fail_count'] + 1;
                    // if($data['max_fail_count'] > 10){ //最大10次失败
                    //     $data['status'] = 0;
                    // }

                    //订单金额情况
                    $start_time = $cur_time - 6*60*60;
                    $where = array();
                    $where['otype']     = 2;
                    $where['payparams_id'] = $payparams_id;
                    $where['status']    = 3;
                    $where['notifyurl'] = ['exp','is not null'];
                    $where['addtime']   = ['between', [$start_time, $cur_time]];
                    $max_money = D('ExchangeOrder')->where($where)->limit(20)->order('id desc')->max("mum");
                    if(isset($max_money) && $max_money > 0){
                        $where['status'] = 8;
                        $where['mum']    = ['gt', $max_money];
                        $fail_count = D('ExchangeOrder')->where($where)->limit(50)->order('id desc')->count();
                        if($fail_count > 10){
                            $data['limit_amount_status'] = 1;
                        }
                    }

                    //支付过的金额保存
                    $minute_index = intval(date('i') / 5);
                    $amount = $orderData['mum'];
                    if($payparams['pay_amount_list'] != 'null'){
                        $pay_amount_list = json_decode($payparams['pay_amount_list'], true);
                        if(empty($pay_amount_list)){
                            $pay_amount_list = json_encode(array($minute_index, $amount));
                            $data['pay_amount_list'] = $pay_amount_list;
                        }else{

                            if(is_numeric($pay_amount_list[0])){
                                $index = intval($pay_amount_list[0]);
                                if($index == $minute_index){
                                    array_push($pay_amount_list, $amount);
                                    $pay_amount_list = json_encode($pay_amount_list);
                                    $data['pay_amount_list'] = $pay_amount_list;
                                }else{
                                    $pay_amount_list = json_encode(array($minute_index, $amount));
                                    $data['pay_amount_list'] = $pay_amount_list;
                                }
                            }else{
                                $pay_amount_list = json_encode(array($minute_index, $amount));
                                $data['pay_amount_list'] = $pay_amount_list;
                            }
                        }
                    }else{
                        $pay_amount_list = json_encode(array($minute_index, $amount));
                        $data['pay_amount_list'] = $pay_amount_list;
                    }
                }
                $data['last_paying_time'] = $cur_time;
                $res = M('payparams_list')->where(['id'=>$payparams_id])->save($data);
                //Log::record("afterAddExchangeOrder 00000000 res = ".$res.' payparams_id = '.$payparams_id.' orderData = '.json_encode($orderData), Log::INFO);
            }
            //账户类型的判断
            if(isset($orderData['pay_channelid']) && $orderData['pay_channelid'] > 0){
                M('paytype_config')->where(['channelid'=>$orderData['pay_channelid']])->save(['last_paying_time'=>$cur_time]);
            }
            //Log::record("afterAddExchangeOrder  11111111111 ", Log::INFO);
        }
        //Log::record("afterAddExchangeOrder  2222222222", Log::INFO);
    }

    //CNC卖出订单确认成功
    protected function confirmC2CSellOrder($orderInfo){
        $today_date = date('Y-m-d');
        if (empty($orderInfo)) {
            return $this->returnErrorMsg('OTC-卖出订单没有要确认操作的数据!');
        }

        //非卖出订单
        if ($orderInfo['otype'] != 2) {
            return $this->returnErrorMsg('OTC-该订单不是卖出订单!');
        }

        //已经完成的订单
        if($orderInfo['status'] > 2){
            return $this->returnErrorMsg('OTC-该订单已经处于完成状态，不能再次确认!');
        }

        /** 检查设置条件 **/
        $exchange_config = M('exchange_config')->where(array('id' => 1))->find();
        if($exchange_config['mytx_status'] == 0){ //是否开启了CNC交易
            return $this->returnErrorMsg(L('OTC-CNC交易关闭，请稍后再试！'));
        }

        $id          = $orderInfo['id'];
        $otype       = $orderInfo['otype'];
        $sell_userid = $orderInfo['userid'];
        $buy_userid  = $orderInfo['aid'];
        $orderid     = $orderInfo['orderid'];
        $out_order_id = $orderInfo['out_order_id'];
        $coin_type   = $orderInfo['type'];
        $channelid   = $orderInfo['pay_channelid'];
        $num         = isset($orderInfo['num'])?$orderInfo['num']:0;
        $mum         = isset($orderInfo['mum'])?$orderInfo['mum']:0;
        $fee         = isset($orderInfo['fee'])?$orderInfo['fee']:0;
        $all_scale   = isset($orderInfo['all_scale'])?$orderInfo['all_scale']:0;
        $real_amount = (isset($orderInfo['real_amount']) && $order_info['real_amount'] > 0)?$orderInfo['real_amount']:$num;
        $cur_time    = time();

        $userinfo = M('user')->where(array('id' => $sell_userid))->find();
        if(!$userinfo){
            return $this->returnErrorMsg(L('OTC-卖家用户信息不存在！'));
        }

        /** 帐号金额 **/
        $user_coin = M('user_coin')->where(array('userid' => $sell_userid))->find();
        if ($user_coin[$coin_type] < $real_amount) {
            return $this->returnErrorMsg('OTC-卖家用户'. $coin_type . '余额不足！ user amount is too little');
        }

        $paytype_config = M('paytype_config')->where(['channelid'=>$channelid])->find();
        if(empty($paytype_config)){
            return $this->returnErrorMsg(L("OTC-确认卖出订单的账户类型不存在channelid = {$channelid}！"));
        }

        $check_res = $this->checkC2CSellOrderAmount($paytype_config, $exchange_config, $otype, $num, $mum, $all_scale, $fee, $orderInfo);
        if($check_res !== true){
            return $check_res;
        }
        //订单号对应的表名
        $orderTableName = D('ExchangeOrder')->getExchangeOrderTableNameWithOrderID($orderid);
        
        // 对应的后台账号
        $peAdmin = M('PeAdmin')->where(array('userid' => $sell_userid))->find();
        $fPeAdmin = M('PeAdmin')->where(['id' => $peAdmin['fid']])->find();
        
        if (!$peAdmin) {
            return $this->returnErrorMsg('OTC-卖家用户查询失败！');
        }
        // 获取用户手续费
        $userFees = $this->getTransFees($peAdmin, $channelid, $real_amount, 2);
        $agentFees = $this->getTransFees($fPeAdmin, $channelid, $real_amount, 2);
        
        // 手续费
        $userReceiveAllFee = $userFees['commission_fee'] + $userFees['fixed_fee'];
        $agentReceiveAllFee = $agentFees['commission_fee'] + $agentFees['fixed_fee'];
        
        // 对应保证金
        $user_coin_margin = M('user_coin_margin')->where(array('userid' => $sell_userid))->find();
        $fuser_coin_margin = M('user_coin_margin')->where(array('userid' => $fPeAdmin['userid']))->find();
        
        $user_coin_settle = M('user_coin_settle')->where(array('userid' => $sell_userid))->find();
        $fuser_coin_settle = M('user_coin_settle')->where(array('userid' => $fPeAdmin['userid']))->find();
        
        if ($user_coin_margin[$coin_type] < ($real_amount + $user_coin_settle[$coin_type])) {
            return $this->returnErrorMsg('OTC-卖家用户'. $coin_type . '保证金余额不足！ user amount is too little');
        }
        
        if ($fPeAdmin['is_system'] == 1) {
            if ($fuser_coin_margin[$coin_type] < ($agentReceiveAllFee + $fuser_coin_settle[$coin_type])) {
                return $this->returnErrorMsg('OTC-卖家用户上级代理'. $coin_type . '保证金余额不足！ user amount is too little');
            }
        } else {
            if ($fuser_coin_margin[$coin_type] < ($real_amount + $fuser_coin_settle[$coin_type])) {
                return $this->returnErrorMsg('OTC-卖家用户上级代理'. $coin_type . '保证金余额不足！ user amount is too little');
            }
        }
        // 计算账户成功率
        $payparamsInfo = M('payparams_list')->where(['id' => $orderInfo['payparams_id']])->find();
        $minRate = M('paytype_config')->where(['channelid' => $channelid])->getField('min_success_rate');
        $start_time = $cur_time - 6*60*60;
        $where = array();
        $where['otype']     = 2;
        $where['payparams_id'] = $orderInfo['payparams_id'];
        $where['notifyurl'] = ['exp','is not null'];
        $where['addtime']   = ['between', [$start_time, $cur_time]];
        $where['status']    = ['in', '3,8,9'];
        $payparamsAllOrderCount = D('ExchangeOrder')->where($where)->limit(50)->order('id desc')->count(); // 6小时内的50笔订单
        $where['status']    = 3;
        $payparamsSuccessOrderCount = D('ExchangeOrder')->where($where)->limit(50)->order('id desc')->count();  // 6小时内的50笔成功订单
        $successRate = round(($payparamsSuccessOrderCount / $payparamsAllOrderCount) * 100);

        if($orderTableName){
            $mo = M();
            $mo->execute('set autocommit=0');
            $mo->execute("lock tables {$orderTableName} write, tw_user write, tw_user_coin write, tw_user_coin_settle write, tw_exchange_report write, tw_exchange_order_history_sum write, tw_payparams_list write, tw_finance write, tw_finance_log write");
            
            $orderRealData = $mo->table($orderTableName)->where(array('orderid'=>$orderid))->find();
            //防止已经处理的订单再次处理，所以先设置状态
            if ($mo->table($orderTableName)->where(array('orderid'=>$orderid))->save(array('status'=>3,'rush_status'=>0))) {

                try{
                    //防止篡改
                    $num        = abs($num);
                    $fee        = abs($fee);
                    $all_scale  = abs($all_scale);
                    //配置金额
                    $config_price = $exchange_config['mytx_uprice'];
                    $fee = $fee / $config_price;

                    // 卖出方用户账户数据处理
                    $rs[] = $mo->table('tw_user_coin')->where(array('userid' => $sell_userid))->setDec($coin_type, $real_amount); // 修改金额
                    
                    // 操作新后台数据
                    if ($peAdmin) {
                        // // 获取用户手续费
                        // $userFees = $this->getTransFees($peAdmin, $channelid, $real_amount, 2);
                        // $agentFees = $this->getTransFees($fPeAdmin, $channelid, $real_amount, 2);
                        
                        // // 手续费
                        // $userReceiveAllFee = $userFees['commission_fee'] + $userFees['fixed_fee'];
                        // $agentReceiveAllFee = $agentFees['commission_fee'] + $agentFees['fixed_fee'];
                        
                        // 超时罚款
                        $userPunishAmount = 0;
                        $agnetPunishAmount = 0;
                        if ($orderRealData['status'] == 8) {
                            $userPunishAmount = $userFees['punish_fee'];
                            $agnetPunishAmount = $agentFees['punish_fee'];
                        }
                        
                        // 待结算金额 = 到账金额 - 单笔手续费 - 固定手续费 + 超时罚款
                        $userSettleAmount = $real_amount - $userReceiveAllFee + $userPunishAmount;
                        $rs[] = $mo->table('tw_user_coin_settle')->where(array('userid' => $sell_userid))->setInc($coin_type, $userSettleAmount); // 修改业务员金额
                        
                        // 判断代理是否是系统模式
                        if ($fPeAdmin['is_system'] == 1) {
                            // 计算系统模式下的手续费
                            $agentSystemFeeAmount = $agentReceiveAllFee + $agnetPunishAmount; // 服务费 + 超时费
                            $rs[] = $mo->table('tw_user_coin_settle')->where(array('userid' => $fPeAdmin['userid']))->setInc($coin_type, $agentSystemFeeAmount); // 修改上级代理金额
                        } else {
                            $agentSettleAmount = $real_amount - $agentReceiveAllFee + $agnetPunishAmount;
                            $rs[] = $mo->table('tw_user_coin_settle')->where(array('userid' => $fPeAdmin['userid']))->setInc($coin_type, $agentSettleAmount); // 修改上级代理金额
                        }
                        
                        // 添加记录
                        $userExportData = $mo->table('tw_exchange_report')
                            ->where(array(
                                'userid' => $sell_userid,
                                'currency' => strtoupper($coin_type),
                                'date' => $today_date,
                            ))->find();
                        if ($userExportData) {
                            $rs[] = $mo->table('tw_exchange_report')->where(array('id' => $userExportData['id']))->save([
                                'receive_amount' => $userExportData['receive_amount'] + $real_amount,
                                'receive_fee' => $userExportData['receive_fee'] + $userReceiveAllFee,
                                'punish_amount' => $userExportData['punish_amount'] + $userPunishAmount,
                            ]); // 修改业务员报表数据
                        } else {
                            $rs[] = $mo->table('tw_exchange_report')->add([
                                'userid' => $sell_userid,
                                'currency' => strtoupper($coin_type),
                                'date' => $today_date,
                                'receive_amount' => $real_amount,
                                'receive_fee' => $userReceiveAllFee,
                                'payment_amount' => 0,
                                'payment_fee' => 0,
                                'punish_amount' => $userPunishAmount,
                                'addtime' => time(),
                            ]);
                        }
                        
                        $agentExportData = $mo->table('tw_exchange_report')
                            ->where(array(
                                'userid' => $fPeAdmin['userid'],
                                'currency' => strtoupper($coin_type),
                                'date' => $today_date,
                            ))->find();
                        if ($agentExportData) {
                            $rs[] = $mo->table('tw_exchange_report')->where(array('id' => $agentExportData['id']))->save([
                                'receive_amount' => $agentExportData['receive_amount'] + $real_amount,
                                'receive_fee' => $agentExportData['receive_fee'] + $agentReceiveAllFee,
                                'punish_amount' => $agentExportData['punish_amount'] + $agnetPunishAmount,
                            ]); // 修改代理报表数据
                        } else {
                            $rs[] = $mo->table('tw_exchange_report')->add([
                                'userid' => $fPeAdmin['userid'],
                                'currency' => strtoupper($coin_type),
                                'date' => $today_date,
                                'receive_amount' => $real_amount,
                                'receive_fee' => $agentReceiveAllFee,
                                'payment_amount' => 0,
                                'payment_fee' => 0,
                                'punish_amount' => $agnetPunishAmount,
                                'addtime' => time(),
                            ]);
                        }
                    }
                    
                    //创建自动卖出订单扣除cnc
                    $remarks = '创建自动卖出订单扣除cnc';
                    $rs[] = $this->addFinanceLog($orderInfo, $sell_userid, $remarks, 0, 4, false);

                    //修改买方数据
                    $rs[] = $mo->table('tw_user_coin')->where(array('userid' => $buy_userid))->setInc($coin_type, $real_amount-$fee);
                    
                    // 修改账户数据
                    $rs[] = $mo->table('tw_payparams_list')->where(array('id' => $orderInfo['payparams_id']))->setInc('amount', $real_amount-$fee);
                    $rs[] = $mo->table('tw_payparams_list')->where(array('id' => $orderInfo['payparams_id']))->setInc('paying_money', $real_amount);
                    $payparamsSaveData = [];
                    if ($payparamsAllOrderCount >= 50 ){
                        $payparamsSaveData['success_rate'] = $successRate;
                    }
                    
                    // 如果成功率小于15%需要通知
                    if ($payparamsAllOrderCount >= 50 && $successRate < 15) {
                        $this->sendMsg($fPeAdmin['wallet_id'], 'agent-err-201', [$peAdmin['nickname'], $payparamsInfo['appid']]); // 通知供应商
                        $this->sendMsg($peAdmin['wallet_id'], 'sale-err-201', [$payparamsInfo['appid']]); // 通知业务员
                    }
                    // 成功率小于最低成功率 最近50笔订单
                    if ($payparamsAllOrderCount >= 50 && $successRate < $minRate) {
                        $payparamsSaveData['receive_status'] = 0;
                        
                        $this->sendMsg($fPeAdmin['wallet_id'], 'agent-err-202', [$peAdmin['nickname'], $payparamsInfo['appid']]); // 通知供应商
                        $this->sendMsg($peAdmin['wallet_id'], 'sale-err-202', [$payparamsInfo['appid']]); // 通知业务员
                    }
                    
                    // 如果余额小于10%需要通知
                    $beforAmount = $payparamsInfo['amount'] + $real_amount - $fee;
                    $remainingAmount = round($beforAmount / $payparamsInfo['max_amount'] * 100);
                    if ($remainingAmount >= 90) {
                        $this->sendMsg($fPeAdmin['wallet_id'], 'agent-err-203', [$peAdmin['nickname'], $payparamsInfo['appid']]); // 通知供应商
                        $this->sendMsg($peAdmin['wallet_id'], 'sale-err-203', [$payparamsInfo['appid']]); // 通知业务员
                    }
                    // 收款余额已达最大值
                    if ($beforAmount > $payparamsInfo['max_amount']) {
                        $payparamsSaveData['receive_status'] = 0;
                        
                        $this->sendMsg($fPeAdmin['wallet_id'], 'agent-err-204', [$peAdmin['nickname'], $payparamsInfo['appid']]); // 通知供应商
                        $this->sendMsg($peAdmin['wallet_id'], 'sale-err-204', [$payparamsInfo['appid']]); // 通知业务员
                    }
                    
                    if ($payparamsSaveData) {
                        $rs[] = $mo->table('tw_payparams_list')->where(array('id' => $orderInfo['payparams_id']))->save($payparamsSaveData);
                    }

                    //是否记录日志
                    $bFinanceLog = true;
                    if(isAutoC2COrder($orderInfo)){
                        $bFinanceLog = false;
                        $bEndOrder = true;
                        //计算延迟惩罚
                        $cur_scale = $this->countDelayTimePunishment($orderInfo, $bEndOrder);
                        $del_scale = $all_scale-$cur_scale;
                        //处理上级的分成逻辑
                        $invit_amount = $this->addInvitProfits($cur_scale, $userinfo, $coin_type, $exchange_config);
                        if($invit_amount > 0){ //上级分成金额
                            // 修改金额
                           $rs[] = $mo->table('tw_user_coin')->where(array('userid' => $sell_userid))->setDec($coin_type,$del_scale+$invit_amount); 
                        }
                    }
                    if(!isset($bEndOrder)){
                        $remarks = $orderInfo['remarks'];
                        $rs[] = $mo->table($orderTableName)->where(array('orderid'=>$orderid))->save(array('endtime' => time(),'remarks'=>$remarks));
                    }

                    //记录买方变动日志
                    $remarks = 'C2C市场'.$coin_type.'-卖出订单的买方确认';
                    $rs[] = $this->addFinanceLog($orderInfo, $buy_userid, $remarks, 1, 1, $bFinanceLog);

                    if (check_arr($rs)) {
                        $this->mysqlCommit($mo);
                        return true;
                    } else {
                        $this->mysqlRollback($mo);
                        return $this->returnErrorMsg('OTC-卖出订单确认操作失败111！');
                    }
                }catch(\Think\Exception $e){
                    $this->mysqlRollback($mo);
                    return $this->returnErrorMsg('OTC-卖出订单确认操作失败222！');
                }
            } else {
                $this->mysqlRollback($mo);
                return $this->returnErrorMsg('OTC-卖出订单确认操作失败333！');
            }
        }else{
            return $this->returnErrorMsg('OTC-卖出订单确认操作,对应订单表不存在！');
        }
    }

    //C2C卖出订单重置为成功
    protected function resetConfirmC2CSellOrder($orderInfo){

        if (empty($orderInfo)) {
            return $this->returnErrorMsg('OTC-卖出订单没有要重新确认操作的数据!');
        }

        if($orderInfo['otype'] != 2){
            return $this->returnErrorMsg(L('OTC-只有卖出订单才能重置！'));
        }

        if($orderInfo['status'] != 8){
            return $this->returnErrorMsg(L('OTC-不为撤销订单，不能重置成功！'));
        }

        /** 检查设置条件 **/
        $exchange_config = M('exchange_config')->where(array('id' => 1))->find();
        if($exchange_config['mytx_status'] == 0){ //是否开启了CNC交易
            return $this->returnErrorMsg(L('OTC-CNC交易关闭，请稍后再试！'));
        }

        $otype       = $orderInfo['otype'];
        $orderid     = $orderInfo['orderid'];
        $sell_userid = $orderInfo['userid'];
        $buy_userid  = $orderInfo['aid'];
        $coin_type   = $orderInfo['type'];
        $channelid   = $orderInfo['pay_channelid'];
        $num         = isset($orderInfo['num'])?$orderInfo['num']:0;
        $mum         = isset($orderInfo['mum'])?$orderInfo['mum']:0;
        $fee         = isset($orderInfo['fee'])?$orderInfo['fee']:0;
        $all_scale   = isset($orderInfo['all_scale'])?$orderInfo['all_scale']:0;
        $real_amount = (isset($orderInfo['real_amount']) && $order_info['real_amount'] > 0)?$orderInfo['real_amount']:$num;

        $paytype_config = M('paytype_config')->where(['channelid'=>$channelid])->find();
        if(empty($paytype_config)){
            return $this->returnErrorMsg(L("OTC-重新确认卖出订单的账户类型不存在channelid = {$channelid}！"));
        }

        $check_res = $this->checkC2CSellOrderAmount($paytype_config, $exchange_config, $otype, $num, $mum, $all_scale, $fee, $orderInfo);
        if($check_res !== true){
            return $check_res;
        }

        $sell_user_coin = M('user_coin')->where(array('userid' => $sell_userid))->find();
        if($sell_user_coin[$coin_type] < $real_amount){
            return $this->returnErrorMsg(L("OTC-当前卖家userid={$sell_userid}的币数量不足！"));
        }
        if(!$sell_userid){
            return $this->returnErrorMsg(L("OTC-该订单卖家不存在，不能设置为完成!"));
        }

        if(!$buy_userid){
            return $this->returnErrorMsg(L("OTC-该订单买家不存在，不能设置为完成!"));
        }
        $userinfo = M('user')->where(array('id' => $sell_userid))->find();
        if(!$userinfo){
            return $this->returnErrorMsg(L('OTC-卖家用户信息不存在！'));
        }
        //订单号对应的表名
        $orderTableName = D('ExchangeOrder')->getExchangeOrderTableNameWithOrderID($orderid);

        if($orderTableName){
            $mo = M();
            $mo->execute('set autocommit=0');
            $mo->execute("lock tables {$orderTableName} write, tw_user write, tw_user_coin write, tw_exchange_order_history_sum write, tw_payparams_list write, tw_finance write, tw_finance_log write");
            //防止已经处理的订单再次处理，所以先设置状态
            if ($mo->table($orderTableName)->where(array('orderid'=>$orderid))->save(array('status'=>3,'rush_status'=>0))) {

                try{
                    //防止篡改
                    $num        = abs($num);
                    $fee        = abs($fee);
                    $all_scale  = abs($all_scale);
                    //配置金额
                    $config_price = $exchange_config['mytx_uprice'];
                    $fee = $fee / $config_price;
                    //修改买方数据
                    $rs[] = $mo->table('tw_user_coin')->where(array('userid' => $buy_userid))->setInc($coin_type, $real_amount-$fee);
                    //修改卖方数据
                    $rs[] = $mo->table('tw_user_coin')->where(array('userid' => $sell_userid))->setDec($coin_type, $real_amount); // 修改金额
                    
                    if(isAutoC2COrder($orderInfo)){
                        $bEndOrder = true;
                        //计算延迟惩罚
                        $cur_scale = $this->countDelayTimePunishment($orderInfo, $bEndOrder);
                        $del_scale = $all_scale-$cur_scale;
                        //处理上级的分成逻辑
                        $invit_amount = $this->addInvitProfits($all_scale, $userinfo, $coin_type, $exchange_config);
                        if($invit_amount > 0){ //上级分成金额
                            // 修改金额
                            $rs[] = $mo->table('tw_user_coin')->where(array('userid' => $sell_userid))->setDec($coin_type, $del_scale+$invit_amount);
                        }
                        //记录，卖方变动日志
                        $remarks = 'C2C市场'.$orderInfo['type'].'-卖出订单的卖方重置确认';
                        $rs[] = $this->addFinanceLog($orderInfo, $sell_userid, $remarks, 0, 4);
                    }
                    if(!isset($bEndOrder)){
                        $remarks = $orderInfo['remarks'];
                        $rs[] = $mo->table($orderTableName)->where(array('orderid'=>$orderid))->save(array('endtime' => time(),'remarks'=>$remarks));
                    }

                    //记录买方变动日志
                    $remarks = 'C2C市场'.$coin_type.'-卖出订单的买方重置确认';
                    $rs[] = $this->addFinanceLog($orderInfo, $buy_userid, $remarks, 1, 1);

                    if (check_arr($rs)) {
                        $this->mysqlCommit($mo);
                        return true;
                    } else {
                        $this->mysqlRollback($mo);
                        return $this->returnErrorMsg('OTC-卖出订单重置确认失败111！$rs ='.json_encode($rs));
                    }
                }catch(\Think\Exception $e){
                    $this->mysqlRollback($mo);
                    return $this->returnErrorMsg(L('OTC-卖出订单重置确认失败222！'));
                }
            } else {
                $this->mysqlRollback($mo);
                return $this->returnErrorMsg('OTC-卖出订单重置确认失败333！');
            }
        }else{
            return $this->returnErrorMsg('OTC-卖出订单重置确认操作,对应订单表不存在！');
        }
    }

    // C2C卖出订单撤销
    protected function cancelC2CSellOrder($orderInfo)
    {
        if (empty($orderInfo)) {
            return $this->returnErrorMsg('OTC-卖出订单没有要撤销操作的数据!');
        }

        //非卖出订单
        if ($orderInfo['otype'] != 2) {
            return $this->returnErrorMsg('OTC-该订单不是卖出订单!');
        }

        //已经完成的订单
        if($orderInfo['status'] > 2){
            return $this->returnErrorMsg('OTC-该订单已经处于完成状态，不能取消!');
        }

        //已经处理的订单
        if(!isAutoC2COrder($orderInfo) && !isset($orderInfo['is_admin']) && $orderInfo['status'] > 1){
            return $this->returnErrorMsg('OTC-非自动卖出订单交易后不能撤销!');
        }

        $otype       = $orderInfo['otype'];
        $sell_userid = $orderInfo['userid'];
        $buy_userid  = $orderInfo['aid'];
        $id          = $orderInfo['id'];
        $coin_type   = $orderInfo['type']; //提现类型
        $old_status  = $orderInfo['status'];
        $orderid     = $orderInfo['orderid'];
        $channelid   = $orderInfo['pay_channelid'];
        $num         = isset($orderInfo['num'])?$orderInfo['num']:0;
        $mum         = isset($orderInfo['mum'])?$orderInfo['mum']:0;
        $all_scale   = isset($orderInfo['all_scale'])?$orderInfo['all_scale']:0;
        $fee         = isset($orderInfo['fee'])?$orderInfo['fee']:0;

        /** 检查设置条件 **/
        $exchange_config = M('exchange_config')->where(array('id' => 1))->find();
        if($exchange_config['mytx_status'] == 0){ //是否开启了CNC交易
            return $this->returnErrorMsg(L('OTC-CNC交易关闭，请稍后再试！'));
        }

        $paytype_config = M('paytype_config')->where(['channelid'=>$channelid])->find();
        if(empty($paytype_config)){
            return $this->returnErrorMsg(L("OTC-取消卖出订单的账户类型不存在channelid = {$channelid}！"));
        }

        $check_res = $this->checkC2CSellOrderAmount($paytype_config, $exchange_config, $otype, $num, $mum, $all_scale, $fee, $orderInfo);
        if($check_res !== true){
            return $check_res;
        }

        //订单号对应的表名
        $orderTableName = D('ExchangeOrder')->getExchangeOrderTableNameWithOrderID($orderid);
        if($orderTableName){
            $mo = M();
            $mo->execute('set autocommit=0');
            $mo->execute("lock tables {$orderTableName} write, tw_user write, tw_user_coin write, tw_finance write, tw_finance_log write"); 
            //防止已经处理的订单再次处理，所以先设置状态
            if($mo->table($orderTableName)->where(array('orderid' => $orderid))->save(array('status' => 8,'rush_status'=>0))){

                try{
                    $rs = array();
                    $rs[] = $mo->table($orderTableName)->where(array('orderid' => $orderid))->save(array('endtime' => time()));
                    //防止篡改
                    $num        = abs($num);
                    // 修改金额（失败的订单不在返回金额，因为现在只有成功的订单才会扣除金额了）
                    // $rs[] = $mo->table('tw_user_coin')->where(array('userid'=>$sell_userid))->setInc($coin_type, $num);

                    //记录，卖方变动日志
                    $remarks = 'C2C市场'.$coin_type.'-卖出订单的卖方取消';
                    if(!isAutoC2COrder($orderInfo) && $old_status > 0){
                        $rs[] = $this->addFinanceLog($orderInfo, $sell_userid, $remarks, 1, 5);
                    }else{
                        $rs[] = $this->addFinanceLog($orderInfo, $sell_userid, $remarks, 1, 5, false);
                    }
                    if(check_arr($rs)){
                        $this->mysqlCommit($mo);
                        return true;
                    }else{
                        $this->mysqlRollback($mo);
                        return $this->returnErrorMsg('OTC-卖出订单撤销操作失败！$rs ='.json_encode($rs));
                    }
                }catch(\Think\Exception $e){

                    $this->mysqlRollback($mo);
                    return $this->returnErrorMsg('OTC-卖出订单撤销操作失败222！');
                }
            }else{
                $this->mysqlRollback($mo);
                return $this->returnErrorMsg('OTC-卖出订单撤销操作失败333！');
            }
        }else{
            return $this->returnErrorMsg('OTC-卖出订单撤销操作,对应订单表不存在！');
        }
    }

    //C2C买入订单确认成功
    protected function confirmC2CBuyOrder($orderInfo){
        $today_date = date('Y-m-d');
        if (empty($orderInfo)) {
            return $this->returnErrorMsg('OTC-买入订单没有要确认操作的数据!');
        }

        //非买入订单
        if ($orderInfo['otype'] != 1) {
            return $this->returnErrorMsg('OTC-该订单不是买入订单!');
        }

        //已经完成的订单
        if($orderInfo['status'] > 2){
            return $this->returnErrorMsg('OTC-该订单已经处于完成状态，不能再次确认!');
        }

        /** 检查设置条件 **/
        $exchange_config = M('exchange_config')->where(array('id' => 1))->find();
        if($exchange_config['mytx_status'] == 0){ //是否开启了CNC交易
            return $this->returnErrorMsg(L('OTC-CNC交易关闭，请稍后再试！'));
        }

        $buy_userid     = $orderInfo['userid'];
        $sell_userid    = $orderInfo['aid'];
        $id             = $orderInfo['id'];
        $coin_type      = $orderInfo['type']; //提现类型
        $orderid        = $orderInfo['orderid'];
        $channelid      = $orderInfo['pay_channelid'];
        $num            = isset($orderInfo['num'])?$orderInfo['num']:0;
        $all_scale      = isset($orderInfo['all_scale'])?$orderInfo['all_scale']:0;
        $scale_amount   = isset($orderInfo['scale_amount'])?$orderInfo['scale_amount']:0;

        $userinfo = M('user')->where(array('id' => $buy_userid))->find();
        if(!$userinfo){
            return $this->returnErrorMsg(L('OTC-卖家用户信息不存在！'));
        }
        if(isAutoC2COrder($orderInfo) && $userinfo['cancal_c2c_level'] != 1){
            return $this->returnErrorMsg(L('OTC-自动买入订单的买入用户的信任等级不够！'));
        }

        //订单号对应的表名
        $orderTableName = D('ExchangeOrder')->getExchangeOrderTableNameWithOrderID($orderid);
        
        // 对应的后台账号
        $peAdmin = M('PeAdmin')->where(array('userid' => $buy_userid))->find();
        $fPeAdmin = M('PeAdmin')->where(['id' => $peAdmin['fid']])->find();
        
        // 获取用户手续费
        $userFees = $this->getTransFees($peAdmin, $channelid, $num, 1);
        $agentFees = $this->getTransFees($fPeAdmin, $channelid, $num, 1);
        
        // 手续费
        $userPaymentAllFee = $userFees['commission_fee'] + $userFees['fixed_fee'];
        $agentPaymentAllFee = $agentFees['commission_fee'] + $agentFees['fixed_fee'];
        
        // 对应保证金
        $fuser_coin_margin = M('user_coin_margin')->where(array('userid' => $fPeAdmin['userid']))->find();
        $fuser_coin_settle = M('user_coin_settle')->where(array('userid' => $fPeAdmin['userid']))->find();
        
        // 系统服务费模式需要验证保证金
        if ($fPeAdmin['is_system'] == 1) {
            if ($fuser_coin_margin[$coin_type] < ($agentPaymentAllFee + $fuser_coin_settle[$coin_type])) {
                return $this->returnErrorMsg('OTC-卖家用户上级代理'. $coin_type . '保证金余额不足！ user amount is too little');
            }
        }
        
        if($orderTableName){
            $mo = M();
            $mo->execute('set autocommit=0');
            $mo->execute("lock tables {$orderTableName} write, tw_user write, tw_user_coin write, tw_user_coin_settle write, tw_exchange_report write, tw_exchange_order_history_sum write, tw_finance write, tw_finance_log write"); 

            $orderRealData = $mo->table($orderTableName)->where(array('orderid'=>$orderid))->find();
            //防止已经处理的订单再次处理，所以先设置状态
            if ($mo->table($orderTableName)->where(array('orderid'=>$orderid))->save(array('status' => 3,'rush_status'=>0,'punishment_level'=>0))) {

                try{
                    $rs = array();
                    //防止篡改
                    $num            = abs($num);
                    $scale_amount   = abs($scale_amount);
                    $all_scale      = abs($all_scale);
                    //修改买方数据
                    $rs[] = $mo->table('tw_user_coin')->where(array('userid' => $buy_userid))->setInc($coin_type, $num+$scale_amount); 
                    
                    // 操作新后台数据
                    if ($peAdmin) {
                        // // 获取用户手续费
                        // $userFees = $this->getTransFees($peAdmin, $channelid, $num, 1);
                        // $agentFees = $this->getTransFees($fPeAdmin, $channelid, $num, 1);
                        
                        // // 手续费
                        // $userPaymentAllFee = $userFees['commission_fee'] + $userFees['fixed_fee'];
                        // $agentPaymentAllFee = $agentFees['commission_fee'] + $agentFees['fixed_fee'];
                        
                        // 超时罚款
                        $userPunishAmount = 0;
                        $agnetPunishAmount = 0;
                        if ($orderRealData['status'] == 8) {
                            $userPunishAmount = $userFees['punish_fee'];
                            $agnetPunishAmount = $agentFees['punish_fee'];
                        }
                        
                        // 待结算金额 = 到账金额 + 单笔手续费 + 固定手续费 - 超时罚款
                        $userSettleAmount = $num + $userPaymentAllFee - $userPunishAmount;
                        $rs[] = $mo->table('tw_user_coin_settle')->where(array('userid' => $buy_userid))->setDec($coin_type, $userSettleAmount); // 操作结算金额
                        
                        // 判断代理是否是系统模式
                        if ($fPeAdmin['is_system'] == 1) {
                            // 计算系统模式下的手续费
                            $agentSystemFeeAmount = $agentPaymentAllFee + $agnetPunishAmount; // 服务费 + 超时费
                            if ($agentSettleAmount > 0) {
                                $rs[] = $mo->table('tw_user_coin_settle')->where(array('userid' => $fPeAdmin['userid']))->setInc($coin_type, $agentSystemFeeAmount); // 修改上级代理金额
                            }
                            
                        } else {
                            $agentSettleAmount = $num + $agentPaymentAllFee - $agnetPunishAmount;
                            $rs[] = $mo->table('tw_user_coin_settle')->where(array('userid' => $fPeAdmin['userid']))->setDec($coin_type, $agentSettleAmount); // 修改上级代理金额
                        }
                        
                        // 添加记录
                        $userExportData = $mo->table('tw_exchange_report')
                            ->where(array(
                                'userid' => $buy_userid,
                                'currency' => strtoupper($coin_type),
                                'date' => $today_date,
                            ))->find();
                        if ($userExportData) {
                            $rs[] = $mo->table('tw_exchange_report')->where(array('id' => $userExportData['id']))->save([
                                'payment_amount' => $userExportData['payment_amount'] + $num,
                                'payment_fee' => $userExportData['payment_fee'] + $userPaymentAllFee,
                                'punish_amount' => $userExportData['punish_amount'] + $userPunishAmount,
                            ]); // 修改业务员报表数据
                        } else {
                            $rs[] = $mo->table('tw_exchange_report')->add([
                                'userid' => $buy_userid,
                                'currency' => strtoupper($coin_type),
                                'date' => $today_date,
                                'receive_amount' => 0,
                                'receive_fee' => 0,
                                'payment_amount' => $num,
                                'payment_fee' => $userPaymentAllFee,
                                'punish_amount' => $userPunishAmount,
                                'addtime' => time(),
                            ]);
                        }
                        
                        $agentExportData = $mo->table('tw_exchange_report')
                            ->where(array(
                                'userid' => $fPeAdmin['userid'],
                                'currency' => strtoupper($coin_type),
                                'date' => $today_date,
                            ))->find();
                        if ($agentExportData) {
                            $rs[] = $mo->table('tw_exchange_report')->where(array('id' => $agentExportData['id']))->save([
                                'payment_amount' => $agentExportData['payment_amount'] + $num,
                                'payment_fee' => $agentExportData['payment_fee'] + $agentPaymentAllFee,
                                'punish_amount' => $agentExportData['punish_amount'] + $agnetPunishAmount,
                            ]); // 修改代理报表数据
                        } else {
                            $rs[] = $mo->table('tw_exchange_report')->add([
                                'userid' => $fPeAdmin['userid'],
                                'currency' => strtoupper($coin_type),
                                'date' => $today_date,
                                'receive_amount' => 0,
                                'receive_fee' => 0,
                                'payment_amount' => $num,
                                'payment_fee' => $agentPaymentAllFee,
                                'punish_amount' => $agnetPunishAmount,
                                'addtime' => time(),
                            ]);
                        }
                    }
                    
                    //是否记录日志
                    $bFinanceLog = true;
                    if(isAutoC2COrder($orderInfo)){
                        $bFinanceLog = false;
                        //处理上级的分成逻辑
                        $invit_amount = $this->addInvitProfits($all_scale, $userinfo, $coin_type, $exchange_config);
                    }
                    $rs[] = $mo->table($orderTableName)->where(array('orderid' => $orderid))->save(array('endtime' => time()));
                    //记录买方变动日志
                    $remarks = 'C2C市场'.$coin_type.'-买入订单的买方确认';
                    $rs[] = $this->addFinanceLog($orderInfo, $buy_userid, $remarks, 1, 1, $bFinanceLog);

                    if (check_arr($rs)) {
                        $this->mysqlCommit($mo);
                        return true;
                    } else {
                        $this->mysqlRollback($mo);
                        return $this->returnErrorMsg('OTC-买入订单确认操作失败111！$rs ='.json_encode($rs));
                    }
                }catch(\Think\Exception $e){
                    $this->mysqlRollback($mo);
                    return $this->returnErrorMsg('OTC-买入订单确认操作失败222！');
                }
            } else {
                $this->mysqlRollback($mo);
                return $this->returnErrorMsg('OTC-买入订单确认操作失败333！');
            }
        }else{
            return $this->returnErrorMsg('OTC-买入订单确认操作,对应订单表不存在！');
        }
    }

    // C2C买入订单撤销
    protected function cancelC2CBuyOrder($orderInfo)
    {
        if (empty($orderInfo)) {
            return $this->returnErrorMsg('OTC-买入订单没有要撤销操作的数据!');
        }

        //非买入订单
        if ($orderInfo['otype'] != 1) {
            return $this->returnErrorMsg('OTC-该订单不是买入订单!');
        }

        //已经完成的订单
        if($orderInfo['status'] > 2){
            return $this->returnErrorMsg('OTC-该订单已经处于完成状态，不能取消!');
        }

        //已经处理的订单
        if(!isAutoC2COrder($orderInfo) && $orderInfo['status'] > 1){
            return $this->returnErrorMsg('OTC-该订单为非自动订单，处于接单状态不能取消!');
        }

        /** 检查设置条件 **/
        $exchange_config = M('exchange_config')->where(array('id' => 1))->find();
        if($exchange_config['mytx_status'] == 0){ //是否开启了CNC交易
            return $this->returnErrorMsg(L('OTC-exchange_config 网络繁忙，请稍后再试！'));
        }
        //单价
        $price = $exchange_config['mycz_uprice'];

        $buy_userid  = $orderInfo['userid'];
        $sell_userid = $orderInfo['aid'];
        $id         = $orderInfo['id'];
        $old_status = $orderInfo['status'];
        $coin_type  = $orderInfo['type']; //提现类型
        $orderid    = $orderInfo['orderid'];
        $num        = isset($orderInfo['num'])?$orderInfo['num']:0;
        $all_scale  = isset($orderInfo['all_scale'])?$orderInfo['all_scale']:0;
        $fee        = isset($orderInfo['fee'])?$orderInfo['fee']:0;
        //总体手续费
        $total_fee  = $all_scale + $fee;
        //手续费数量
        $total_fee_num    = $total_fee / $price;

        //订单号对应的表名
        $orderTableName = D('ExchangeOrder')->getExchangeOrderTableNameWithOrderID($orderid);

        if($orderTableName){
            $mo = M();
            $mo->execute('set autocommit=0');
            $mo->execute("lock tables {$orderTableName} write, tw_user write, tw_user_coin write, tw_finance write, tw_finance_log write"); 

            //防止已经处理的订单再次处理，所以先设置状态
            if($mo->table($orderTableName)->where(array('orderid'=>$orderid))->save(array('status' => 8,'rush_status'=>0,'punishment_level'=>0))){

                try{
                    $rs = array();
                    $rs[] = $mo->table($orderTableName)->where(array('orderid'=>$orderid))->save(array('endtime' => time()));

                    if($old_status > 0){
                        //防止篡改
                        $num            = abs($num);
                        $total_fee_num  = abs($total_fee_num);
                        // 修改金额
                        $rs[] = $mo->table('tw_user_coin')->where(array('userid'=>$sell_userid))->setInc($coin_type, $num+$total_fee_num);
                         //记录买方变动日志
                        $remarks = 'C2C市场'.$coin_type.'-买入订单的卖方取消';
                        if(!isAutoC2COrder($orderInfo)){
                            $rs[] = $this->addFinanceLog($orderInfo, $sell_userid, $remarks, 1, 2);
                        }else{
                            $rs[] = $this->addFinanceLog($orderInfo, $sell_userid, $remarks, 1, 2, false);
                        }
                    }

                    if(check_arr($rs)){
                        $this->mysqlCommit($mo);
                        return true;
                    }else{
                        $this->mysqlRollback($mo);
                        return $this->returnErrorMsg('OTC-买入订单撤销操作失败！');
                    }
                }catch(\Think\Exception $e){
                    $this->mysqlRollback($mo);
                    return $this->returnErrorMsg('OTC-买入订单撤销操作失败222！');
                }
            }else{
                $this->mysqlRollback($mo);
                return $this->returnErrorMsg('OTC-买入订单撤销操作失败333！');
            }
        }else{
            return $this->returnErrorMsg('OTC-买入订单撤销操作,对应订单表不存在！');
        }
    }

    /**
     * 处理延时惩罚
     * @param orderInfo  订单信息
     * @param bEndOrder  是否设置订单为结束状态
     * @return  cur_scale 当前优惠
     */
    protected function countDelayTimePunishment($orderInfo, $bEndOrder=false){

        if(!empty($orderInfo)){

            $all_scale      = isset($orderInfo['all_scale'])?$orderInfo['all_scale']:0;
            $scale_amount   = isset($orderInfo['scale_amount'])?$orderInfo['scale_amount']:0;
            $fee            = isset($orderInfo['fee'])?$orderInfo['fee']:0;
            $end_time       = $orderInfo['endtime']>0?$orderInfo['endtime']:time();
            $orderid        = $orderInfo['orderid'];
            //防止篡改
            $all_scale    = abs($all_scale);
            $scale_amount = abs($scale_amount);
            $fee          = abs($fee);

            $data = array();
            if($bEndOrder){ //设置为结束
                $data['remarks'] = $orderInfo['remarks'];
                $data['rush_status']= 0;
                $data['endtime']    = time();
            }
            if(isAutoC2COrder($orderInfo) && $orderInfo['rush_status'] == 1){ // 只有自动的卖出订单且被催单的情况下才会惩罚
                //获取延迟等级
                $punishment_level = getDelayTimePunishmentLevel($orderInfo['addtime'], $end_time);
                switch ($punishment_level) {
                    case 3:
                        $cur_scale = $all_scale * 0;
                        $cur_scale_amount = $scale_amount * 0;
                        break;
                    case 2:
                        $cur_scale = $all_scale * 0.1;
                        $cur_scale_amount = $scale_amount * 0.1;
                        break;
                    case 1:
                        $cur_scale = $all_scale * 0.5;
                        $cur_scale_amount = $scale_amount * 0.5;
                        break;
                    default:
                        $cur_scale = $all_scale;
                        $cur_scale_amount = $scale_amount;
                        break;
                }
                $sell_userid = $orderInfo['userid'];
                if($all_scale > $cur_scale){
                    //更改数据
                    $fee = $fee + $all_scale - $cur_scale;
                    $data['all_scale'] = $cur_scale;
                    $data['scale_amount'] = $cur_scale_amount;
                    $data['fee'] = $fee;
                    $data['punishment_level'] = $punishment_level;
                }
                if(!empty($data)){
                    //卖出订单
                    if($orderInfo['otype'] == 2){
                        //设置惩罚时间
                        R('Pay/PayUpdate/setPunishmentWeight', array($sell_userid, $punishment_level));
                    }
                    
                    $res = D('ExchangeOrder')->where(array('orderid'=>$orderid))->save($data);
                    if($res){
                        return $cur_scale;
                    }
                }
                return $cur_scale;
            }else{
                if(!empty($data)){
                    $res = D('ExchangeOrder')->where(array('orderid'=>$orderid))->save($data);
                }
            }
            return $all_scale;
        }
        return 0;
    }

    //添加推广分成
    protected function addInvitProfits($all_scale, $userinfo, $coin_type, $exchange_config, $bsave = true){
        if(isset($all_scale) && !empty($userinfo) && $all_scale > 0 && $coin_type && $exchange_config){

            //开启状态
            $sell_rate_type = $exchange_config['sell_rate_type'];
            if(!$sell_rate_type){ //没有开启
                return 0;
            }
            
            $userid = $userinfo['id'];
            $invit_amount = 0; //上级分成金额
            //一代
            $invit_1 = $userinfo['invit_1'];
            $sell_rate_1 = $exchange_config['sell_rate_1'];
            //二代
            $invit_2 = $userinfo['invit_2'];
            $sell_rate_2 = $exchange_config['sell_rate_2'];
            //三代
            $invit_3 = $userinfo['invit_3'];
            $sell_rate_3 = $exchange_config['sell_rate_3'];
            if($invit_1 > 0 && $sell_rate_1 > 0){
                $invit_amount_1 = round($all_scale * $sell_rate_1 / 100, 3);
                $invit_amount += $invit_amount_1;
                if($bsave && $invit_amount <= $all_scale){
                    M('user_coin')->where(array('id' => $invit_1))->setInc($coin_type, $invit_amount_1);
                    $this->addExchangeOrderHistorySum($invit_1, 0, 0, 0, 0, $invit_amount_1);
                    $this->addInvitLog($userid, $invit_1, $all_scale, $invit_amount_1);
                }
            }
            if($invit_2 > 0 && $sell_rate_2 > 0){
                $invit_amount_2 = round($all_scale * $sell_rate_2 / 100, 3);
                $invit_amount += $invit_amount_2;
                if($bsave && $invit_amount <= $all_scale){
                    M('user_coin')->where(array('id' => $invit_2))->setInc($coin_type, $invit_amount_2);
                    $this->addExchangeOrderHistorySum($invit_2, 0, 0, 0, 0, $invit_amount_2);
                    $this->addInvitLog($userid, $invit_2, $all_scale, $invit_amount_2);
                }
            }
            if($invit_3 > 0 && $sell_rate_3 > 0){
                $invit_amount_3 = round($all_scale * $sell_rate_3 / 100, 3);
                $invit_amount += $invit_amount_3;
                if($bsave && $invit_amount <= $all_scale){
                    M('user_coin')->where(array('id' => $invit_3))->setInc($coin_type, $invit_amount_3);
                    $this->addExchangeOrderHistorySum($invit_3, 0, 0, 0, 0, $invit_amount_3);
                    $this->addInvitLog($userid, $invit_3, $all_scale, $invit_amount_3);
                }
            }
            return $all_scale > $invit_amount ? $invit_amount : $all_scale;
        }
        return 0;
    }

    /**
    * 添加用户的历史金额变动总额
    * @param userid         商户ID
    * @param num            数量
    * @param mum            总价
    * @param fee            费率
    * @param scale_amount   优惠金额
    * @param invit_amount   推广分成
    */
    protected function addExchangeOrderHistorySum($userid, $num = 0, $mum = 0, $fee = 0, $scale_amount = 0, $invit_amount = 0){

        if($num != 0 || $mum != 0 || $fee != 0 || $scale_amount != 0 || $invit_amount != 0){

            $exchange_order_history_sum = M('exchange_order_history_sum')->where(array('userid' => $userid))->find();
            if($exchange_order_history_sum){
                $data = array();
                if($num != 0){
                    $data['num_sum']        = $exchange_order_history_sum['num_sum'] + $num;
                }
                if($mum != 0){
                    $data['mum_sum']        = $exchange_order_history_sum['mum_sum'] + $mum;
                }
                if($fee != 0){
                    $data['fee_sum']        = $exchange_order_history_sum['fee_sum'] + $fee;
                }
                if($scale_amount != 0){
                    $data['scale_amount_sum'] = $exchange_order_history_sum['scale_amount_sum'] + $scale_amount;
                }
                if($invit_amount != 0){
                    $data['invit_amount_sum'] = $exchange_order_history_sum['invit_amount_sum'] + $invit_amount;
                }
                return M('exchange_order_history_sum')->where(array('userid' => $userid))->save($data);
            }else{
                $data = array();
                $data['userid']         = $userid;
                $data['num_sum']        = $num?$num:0;
                $data['mum_sum']        = $mum?$mum:0;
                $data['fee_sum']        = $fee?$fee:0;
                $data['scale_amount_sum'] = $scale_amount?$scale_amount:0;
                $data['invit_amount_sum'] = $invit_amount?$invit_amount:0;
                return M('exchange_order_history_sum')->add($data);
            }
        }
        return true;
    }

     //添加分成的记录
    protected function addInvitLog($userid, $invit, $mum, $invit_amount, $name='C2C交易分成奖励', $type=6, $coin_type=Anchor_CNY){
        if($userid && $invit && $mum && $invit_amount){

            $data = array();
            $data['userid'] = $userid;
            $data['invit']  = $invit;
            $data['name']   = $name;
            $data['type']   = $type;
            $data['num']    = $mum;
            $data['mum']    = $mum;
            $data['fee']    = $invit_amount;
            $data['addtime']= time();
            $data['status'] = 1;
            $data['coin']   = $coin_type;

            return M('invit')->add($data);
        }
        return false;
    }

    //添加资金变动日志
    protected function addFinanceLog($orderInfo, $userid, $remark, $plusminus = 0, $optype = 0, $blog=true){

        //添加订单日志
        $mo = M();
        $coin_type      = $orderInfo['type']; //提现类型
        $coin_type_d    = $orderInfo['type'].'d'; //提现类型，冻结
        // 存在真实金额，使用真实金额，不存在使用订单金额
        $num            = (isset($orderInfo['real_amount']) && $orderInfo['real_amount'] > 0)?$orderInfo['real_amount']:$orderInfo['num'];
        $finance_nameid = $orderInfo['orderid'];

        $rs = array();
        $finance = $mo->table('tw_finance')->where(array('userid' => $userid))->order('id desc')->find();
        // 数据处理完的查询（新数据）
        $finance_mum_user_coin = $mo->table('tw_user_coin')->where(array('userid' => $userid))->find();
        $new_coin_num = $finance_mum_user_coin[$coin_type];
        $new_coin_num_d = $finance_mum_user_coin[$coin_type_d]; //冻结金额

        // 数据未处理时的查询（原数据）
        if($plusminus == 1){ //（0减少，1增加） plusminus
            $old_coin_num = $new_coin_num - $num;
            $old_coin_num_d = $new_coin_num_d; //冻结金额
        }else{
            $old_coin_num = $new_coin_num + $num;
            $old_coin_num_d = $new_coin_num_d; //冻结金额
        }

        //变动hash值
        $finance_hash = md5($userid . $old_coin_num . $old_coin_num_d . $num . $new_coin_num . $new_coin_num_d . MSCODE . 'tp3.net.cn');
        
        $finance_num = $old_coin_num + $old_coin_num_d;
        if ($finance['mum'] < $finance_num) {
            $finance_status = (1 < ($finance_num - $finance['mum']) ? 0 : 1);
        } else {
            $finance_status = (1 < ($finance['mum'] - $finance_num) ? 0 : 1);
        }
        
        // 处理资金变更日志-----------------S
        
        $rs[] = $mo->table('tw_finance')->add(array('userid' => $userid, 'coinname' => $coin_type, 'num_a' => $old_coin_num, 'num_b' => $old_coin_num_d , 'num' => $old_coin_num + $old_coin_num_d, 'fee' => $num, 'type' => $plusminus, 'name' => 'mytx_c2c', 'nameid' => $finance_nameid, 'remark' => $remark, 'mum_a' => $new_coin_num, 'mum_b' => $new_coin_num_d, 'mum' => $new_coin_num + $new_coin_num_d, 'move' => $finance_hash, 'addtime' => time(), 'status' => $finance_status));

        //是否记录日志
        if($blog){
            /*
             * 操作位置（0后台，1前台） position
             * 动作类型（参考function.php） optype
             * 资金类型（1人民币） cointype
             * 类型（0减少，1增加） plusminus
             * 操作数据 amount
             */
            $username = $mo->table('tw_user')->where(array('id' => $userid))->getField("username");

            $rs[] = $mo->table('tw_finance_log')->add(array('username' => $username, 'adminname' => $username, 'addtime' => time(), 'plusminus' => $plusminus, 'amount' => $num, 'optype' => $optype, 'position' => 1, 'cointype' => 1, 'old_amount' =>  $old_coin_num, 'new_amount' => $new_coin_num, 'userid' => $userid, 'adminid' => $userid,'addip'=>get_client_ip()));
        }
         // 处理资金变更日志-----------------E

        if(check_arr($rs)){
            return true;
        }else{
            return false;
        }
    }

    //添加外部来的消息的记录
    protected function addOutsideMsg($msg_info){
        //检查是否已经收到过该消息
        if(isset($msg_info) && is_array($msg_info)){
            $userid     = $msg_info['userid'];
            $amount     = $msg_info['amount'];
            $time       = $msg_info['time'];
            $acount_id  = $msg_info['bankcard'];
            $msg_where = array('userid'=>$userid, 'amount'=>$amount, 'time'=>$time, 'acount_id'=>$acount_id);
            $msg = M('outside_msg')->where($msg_where)->find();
            if($msg){
                return $this->returnErrorMsg('OTC-该消息已经存在 msg is exist !!!');
            }else{
                //删除不需要的记录
                $map['addtime'] = array('lt', (time() - 30*24*60*60));
                $deldd = M('outside_msg')->where($map)->delete();
                //添加新的记录
                $msg_where['addtime'] = time();
                M('outside_msg')->add($msg_where);
            }
        }
        return true;
    }

    //向商户发送通知消息
    protected function requestNotifyUrl($urlparam, $data){
        if($urlparam && $data){
            $url = $urlparam;
            $herder[] = 'Content-Type: application/x-www-form-urlencoded; charset=utf-8';
            $resultResponse = httpRequestData($url,http_build_query($data),$herder,'post', 5);

            //Log::record("requestStarPayData  data= ".json_encode($data).' url = '. $url . ' Response = '.$resultResponse, Log::INFO);
            return $resultResponse;
        }
        return false;
    }

    /**
    * callback通知
    * @param order_info   订单信息
    * @param errorType 错误类型  (00表示成功, 其他表示失败)
    */
    public function responseCallbackUrl($order_info, $errorType){

        if($order_info && isset($order_info["callbackurl"]) && $order_info["callbackurl"] && $order_info["callbackurl"] != ''){

            $userid = $order_info["aid"];
            //同步前端跳转callback通知
            $callback_array = [ // 返回字段
                "userid"         => $userid,                        // 商户ID
                "orderid"        => $order_info['out_order_id'],    // 订单号
                "returncode"     => $errorType,                     // 交易状态(00表示成功)
            ];

            $md5key = M('User')->where(['id' => $userid])->getField('apikey');
            $sign                   = $this->createSign($md5key, $callback_array);
            $callback_array["sign"] = $sign;

            $this->setHtml($order_info["callbackurl"], $callback_array);
        }
    }

    //向账户平台申请订单信息，并实现自动回调
    protected function requestOrderInfo($paytype_config, $payParams, $postInfo){

        if($paytype_config && isset($paytype_config['is_auto_notify']) && $paytype_config['is_auto_notify']){

            if (!is_file(APP_PATH . '/' . MODULE_NAME . '/Controller/' . $paytype_config['code'] . 'Controller.class.php')) {
                $this->showmessage('OTC-账户类型不存在'.$paytype_config['code']);
            }
            $return = R($paytype_config['code'] . '/requestOrder', [$payParams, $postInfo]);
            if($return === false) {

                return $this->returnErrorMsg('OTC-请求生成远程订单信息失败,请稍后再试...');
            }else{

                return $return;
            }
        }else{
            
            return $this->returnErrorMsg('OTC-账户类型不正确,请检查账号类型! ');
        }
    }

    //获取支付通知地址
    protected function getPayNotifyUrl($payParams, $code){
        $notifyurl = ''; 
        $pay_return_site = isset($payParams['pay_return_site']) ? $payParams['pay_return_site'] : false;
        //域名为ip地址时, 是否设置了支付回调地址
        if($pay_return_site && is_string($pay_return_site) && substr_count($pay_return_site, '.') == 3){
            //通知地址
            $notifyurl = 'http://'.$pay_return_site.'/Pay/'.$code.'/notifyurl.html';
        }
        $pay_return_url = C('PAY_RETURN_URL');
        if(isset($pay_return_url)){ // 网站默认的支付回调地址
            $notifyurl = $pay_return_url.'/Pay/'.$code.'/notifyurl.html';
        }
        if(!isset($notifyurl)){ //默认地址
            $notifyurl = C('STAR_EXCHANGE_WEB_URL').'/Pay/'.$code.'/notifyurl.html';
        }
        return $notifyurl;
    }

    //请求货币的汇率
    private function requestCurrencyExchangeRate($fromCurrency, $toCurrency){
        $access_key = '389a14f75c038f5c3f5a42b098450fde';
        $request_url = "http://api.currencylayer.com/live?access_key={$access_key}&currencies={$fromCurrency},{$toCurrency}&format=1";
        $cur_url_data = file_get_contents_new($request_url, 5);
        if($cur_url_data){
            $cur_url_array = json_decode($cur_url_data, true);
            if($cur_url_array['success']){
                $source = $cur_url_array['source'];
                $quotes = $cur_url_array['quotes'];
                $fromCurrencyRate   = $quotes[$source.$fromCurrency];
                $toCurrencyRate     = $quotes[$source.$toCurrency];
                $rate = $toCurrencyRate / $fromCurrencyRate;
                return $rate;
            }
        }
        return false;
    }

    //更新货币的当前费率
    protected function updateCurrencyExchangeRate($fromCurrency, $toCurrency){

        if($fromCurrency && $toCurrency){
            $where = [
                'from_currency' => $fromCurrency,
                'to_currency'   => $toCurrency,
            ];

            $cur_rate_date = M('currency_exchange_rate')->where($where)->find();
            if($cur_rate_date){

                if($cur_rate_date['updatetime'] < time() - 12*60*60) //每半天更新一次
                {
                    $cur_url_rate = $this->requestCurrencyExchangeRate($fromCurrency, $toCurrency);
                    if($cur_url_rate){

                        return M('currency_exchange_rate')->where($where)->save(['rate'=>$cur_url_rate, 'updatetime'=>time()]);
                    }
                }else{
                    return true;
                }
            }else{
                $cur_url_rate = $this->requestCurrencyExchangeRate($fromCurrency, $toCurrency);
                if($cur_url_rate){

                    $data = [
                        'from_currency' => $fromCurrency,
                        'to_currency'   => $toCurrency,
                        'rate'          => $cur_url_rate,
                        'updatetime'    => time(),  
                    ];
                    return M('currency_exchange_rate')->add($data);
                }
            }
        }
        return true;
    }

    //获取货币的当前费率
    protected function getCurrencyExchangeRate($fromCurrency, $toCurrency){
        if($fromCurrency && $toCurrency){
            $where = [
                'from_currency' => $fromCurrency,
                'to_currency'   => $toCurrency,
            ];
            $cur_rate_date = M('currency_exchange_rate')->where($where)->find();
            if($cur_rate_date){
                return $cur_rate_date['rate'];
            }else{
                $cur_url_rate = $this->requestCurrencyExchangeRate($fromCurrency, $toCurrency);
                if($cur_url_rate){
                        
                    $data = [
                        'from_currency' => $fromCurrency,
                        'to_currency'   => $toCurrency,
                        'rate'          => $cur_url_rate,
                        'updatetime'    => time(),  
                    ];
                    M('currency_exchange_rate')->add($data);
                    return $data['rate'];
                }
            }
        }
        return false;
    }

    //C2C卖出订单金额校验
    private function checkC2CSellOrderAmount($paytype_config, $exchange_config, $otype, $order_num, $order_mum, $order_all_scale, $order_fee, $orderInfo=null){

        if(is_array($paytype_config)) {
            
            //净费率
            $auto_buy_rate = $paytype_config['auto_buy_rate'];
            //优惠
            $sale_sell_rate = $paytype_config['sale_sell_rate'];
            if($auto_buy_rate < $sale_sell_rate){
                return $this->returnErrorMsg(L('OTC-校验费率填写问题！'));
            }

            //配置金额
            $config_price = $exchange_config['mytx_uprice'];
            if(empty($orderInfo) || (!empty($orderInfo) && isAutoC2COrder($orderInfo))) {
                //单价
                $price = $config_price * ($sale_sell_rate + 1);
                $price = round($price, 3);
                //优惠折扣金额
                $all_scale = $order_mum - ($config_price * $order_num);
                $all_scale = $all_scale > 0?$all_scale:0;
                $all_scale = round($all_scale, 3);
                //计算实际的净费率
                $fee = ($order_mum * $auto_buy_rate) - $all_scale;
                $fee = $fee > 0?$fee:0;
                $fee = round($fee, 3);
            }elseif(!empty($orderInfo) && $orderInfo['type'] == Anchor_CNY){ //基础货币验证单价
                $price = $exchange_config['mytx_uprice'];
                $all_scale = $orderInfo['all_scale'];
                $fee = $orderInfo['fee'];
            }else{ //不验证
                $price = $orderInfo['uprice'];
                $all_scale = $orderInfo['all_scale'];
                $fee = $orderInfo['fee'];
            }

            //数据异常
            if(abs($all_scale - $order_all_scale) > 0.001 || abs($fee - $order_fee) > 0.001){

                if(!empty($orderInfo)){
                    $this->recordC2COrderError($orderInfo['orderid'], $orderInfo['remarks'].'-订单数据金额异常');
                }
                return $this->returnErrorMsg(L("OTC-订单数据金额异常！"));
                //return $this->returnErrorMsg(L("OTC-订单数据金额异常！all_scale = {$all_scale},order_all_scale={$order_all_scale},fee={$fee},order_fee={$order_fee}"));
            }
            if(!empty($orderInfo) && $price != $orderInfo['uprice']){
                $this->recordC2COrderError($orderInfo['orderid'], $orderInfo['remarks'].'-订单的卖出单价异常');
                return $this->returnErrorMsg(L('OTC-订单的卖出单价异常！'));
            }
            return true;
        }else{
            return $this->returnErrorMsg(L('OTC-paytype_config 不存在！'));
        }
    }

    //记录订单异常的日志
    private function recordC2COrderError($orderid, $remarks){
        if(isset($orderid) && isset($remarks)){
            return D('ExchangeOrder')->where(array('orderid'=>$orderid))->save(array('updatetime' => time(),'remarks'=>$remarks));
        }
        return false;
    }
    
    //添加随机金额
    protected function addOrderRandomAmount($return){

      $orderid = $return['orderid'];
      //订单生成时间
      $pay_applydate = $return['addtime'];
      // 通道配置
      $paytype_config = $return['paytype_config'];
      //间隔时间
      $interval = $paytype_config['random_time_range'];
      //30分钟内
      $curMin   = intval(date('i',$pay_applydate));
      $beginMin = intval($curMin / $interval)*$interval;
      $endMin   = sprintf('%02d', $beginMin + $interval-1);
      $beginMin = sprintf('%02d', $beginMin);
      //30分钟开始的时间
      $tenMinsBegin = strtotime(date("Y-m-d H:{$beginMin}:00", $pay_applydate));
      $tenMinsEnd = strtotime(date("Y-m-d H:{$endMin}:59", $pay_applydate));

      $where = [
              'addtime'   => ['between', [$tenMinsBegin, $tenMinsEnd]], //30分钟内收到短信的订单
              'bankcard'          => $return['bankcard'],
              'mum'          => $return['mum'],
              //'account'         => 'www.payCenter.com',
            //   'pay_productname' => trim($return['subject']),
          ];

        // 上一次的随机金额
        $beforOrder = D('ExchangeOrder')->where($where)->order('addtime DESC')->limit(1)->find();
        if ($beforOrder) {
            $randomNum = 0;
            if ($beforOrder['real_amount'] > 0) {
                $randomNum = $beforOrder['real_amount'] - $beforOrder['mum'];
            }
        } else {
            $randomNum = 0;
        }
        $randomNum = $this->calculateAmount($randomNum, floatval($paytype_config['min_random_money']), floatval($paytype_config['max_random_money']), $paytype_config['is_add_random_money'], floatval($paytype_config['min_unit']), floatval($paytype_config['exclude_random_money']));
        if ($randomNum < $paytype_config['min_random_money'] || $randomNum > $paytype_config['max_random_money']) {
            return false;
        }
        $random_amount = sprintf("%.2f", floatval($return['mum']) + $randomNum);
        
        return $random_amount;
    }
    
    // 计算金额
    protected function calculateAmount($amount, $minAmount, $maxAmount, $way, $unit, $exclude){
        
        $tempMin = $minAmount;
        if ($way != 1) {
            $minAmount = $maxAmount;
            $maxAmount = $tempMin;
        }
        
        if ($way == 1) {
            $randomAmount = $amount + $unit;
        } else {
            $randomAmount = $amount - $unit;
        }
        $fmod = fmod(intval(abs($randomAmount) * 100), intval(abs($exclude) * 100));
        if ($fmod == 0) {
            return $this->calculateAmount($randomAmount, $minAmount, $maxAmount, $way, $unit, $exclude);
        }
        
        return $randomAmount;
    }
    
    // 计算交易手续费$otype
    protected function getTransFees($peAdmin, $channelid, $amount, $otype = 2){
        $return = [
            'commission_fee' => 0,
            'fixed_fee' => 0,
            'punish_fee' => 0,
        ];
        
        if ($peAdmin) {
            // 获取用户手续费
            $channelFees = unserialize($peAdmin['channel_fees']);
            
            if ($otype == 1) { // 代付
                // 百分比手续费
                $userCommission = $channelFees[$channelid]['payment_commission'] ? ($channelFees[$channelid]['payment_commission'] / 100) : 0;
                // 单笔手续费
                $userFixedFee = $channelFees[$channelid]['payment_fee'] ? $channelFees[$channelid]['payment_fee'] : 0;
            } else { // 代收
                // 百分比手续费
                $userCommission = $channelFees[$channelid]['receive_commission'] ? ($channelFees[$channelid]['receive_commission'] / 100) : 0;
                // 单笔手续费
                $userFixedFee = $channelFees[$channelid]['receive_fee'] ? $channelFees[$channelid]['receive_fee'] : 0;
            }
            
            // 超时处罚
            $userPunishCommission = $channelFees[$channelid]['punish_commission'] ? ($channelFees[$channelid]['punish_commission'] / 100) : 0;
            // 计算手续费
            $userReceiveFee = $amount * $userCommission; // 百分比手续费
            $userReceiveAllFee = $userReceiveFee + $userFixedFee; // 总手续费
            $userPunishAmount = $userReceiveAllFee * $userPunishCommission; // 超时手续费
            
            $return['commission_fee'] = $userReceiveFee;
            $return['fixed_fee'] = $userFixedFee;
            $return['punish_fee'] = $userPunishAmount;
        }
        
        return $return;
    }
    
    // 发送消息
    protected function sendMsg($walletid, $msgid, $params = [])
    {
        $send = new \Common\Service\SyncSendMsg();
        // return $send->sendTgMsg($walletid, $msgid, $params);
        $curtime = time();
        $sessionKey = "tg_send_msg_{$walletid}_{$msgid}";
        $sendLog = M('tg_send_session')->where(array('key' => $sessionKey))->find();
        
        if ($sendLog && $curtime <= $sendLog['exptime']) {
            return false;
        }
        
        if ($sendLog) {
            M('tg_send_session')->where(array('key' => $sessionKey))->save([
                'exptime' => $curtime + (60 * 60 * 6),
                'addtime' => $curtime,
            ]);
        } else {
            M('tg_send_session')->add([
                'key' => $sessionKey,
                'value' => md5($curtime . $sessionKey),
                'exptime' => $curtime + (60 * 60 * 6),
                'addtime' => $curtime,
            ]);
        }
        
        return $send->sendTgMsg($walletid, $msgid, $params);
        
    }
}