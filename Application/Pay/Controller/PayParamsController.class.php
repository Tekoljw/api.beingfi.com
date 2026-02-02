<?php

namespace Pay\Controller;

use Think\Controller;
use Think\Log;

/**
 * @author guotian
 * @date   2018-06-06
 */
//获取支付参数控制器
class PayParamsController extends BaseController
{
    private $userid = 1;
    /**
     * 初始化控制器
     * BaseController constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    //请求自动买入生成c2c订单
    public function index()
    {

        $this->checkUserVerify();

        $amount     = I("post.amount", 0);
        $cointype   = I("post.cointype", 0);
        $channelid  = I("post.channelid", 0);
        $orderid    = I("post.orderid", 0);
        $notifyurl  = I("post.notifyurl");
        $peadminid  = I("post.userid");
        $userid = $this->userid;
        $_POST['agentid'] = $_POST['agentid'] ? $_POST['agentid'] : $_POST['userid'];
        $_POST['userid'] = $userid;
        

        if(!$amount){
            $this->showmessage("OTC-金额信息不能为空! amount is null");
        }

        if($amount <= 0){
            $this->showmessage("OTC-金额必须大于0! amount is 0");
        }

        if(!$cointype){
            $this->showmessage("OTC-货币类型不能为空! cointype is null");
        }

        if(!$orderid){
            $this->showmessage("OTC-订单号不能为空! orderid is null");
        }

        if(strlen($orderid) > 26){
            $this->showmessage("OTC-订单号不能超过26位! orderid strlen greater 26");
        }

        if(!$channelid){
            $this->showmessage("OTC-获取的支付渠道ID不能为空! channelid is null");
        }

        if(!$peadminid){
            $this->showmessage("OTC-获取的商户ID不能为空! userid is null");
        }

        if(!$notifyurl){
            $this->showmessage("OTC-notifyurl不能为空! notifyurl is null");
        }

        //判断外部订单号是否已经存在
        $payParams = $this->getExistExchangeOrderPayParams($userid, $orderid);

        if(!$payParams){
            //获取支付参数
            $payParams = $this->getCanUsePayParams($_POST);
            if(empty($payParams)){
                $this->showmessage("OTC-获取符合要求的用户的支付信息失败-failed！");
            }elseif(is_array($payParams) && $payParams['status'] === 0){
                $this->showmessage($payParams['msg']);
            }
        }elseif(is_array($payParams) && $payParams['status'] === 0){
            $this->showmessage($payParams['msg']);
        }

        if(!is_array($payParams)){ //获取的参数不是array，表示没有获取成功
            $this->showmessage("OTC-获取符合要求账户信息失败-payparams-failed！");
        }

        $paytype_config = M('paytype_config')->where(['channelid'=> $payParams['channelid']])->find();

        //判断是否自动通知的类型
        if($paytype_config['is_auto_notify']){

            $return = $this->requestOrderInfo($paytype_config, $payParams, $_POST);
            if(empty($return)){
                $this->showmessage("OTC-解析账户参数信息失败-failed！");
            }elseif(is_array($return) && isset($return['status']) && $return['status'] === 0){
                $this->showmessage($return['msg']);
            }
        }else{
            //回调参数
            $return = [
                "mch_id"       => $payParams["mch_id"],      //商户号
                "signkey"      => $payParams["signkey"],     //签名密钥
                "appid"        => $payParams["appid"],       //APPID
                "appsecret"    => $payParams["appsecret"],   //APPSECRET
                "domain_record"=> $payParams["domain_record"], //备案域名
                "subject"      => urlencode($payParams['subject']), //商品标题
                "truename"     => $payParams["truename"],    //账户名
                "qrcode"     => $payParams["qrcode"] ? C('STAR_EXCHANGE_WEB_URL') . $payParams["qrcode"] : '',    //收款二维码
            ];
        }
        
        $return['out_order_id'] = $payParams["orderid"]; //otc平台的对应订单号
        $return['real_amount'] = $payParams["real_amount"] ? $payParams["real_amount"] : $amount; //真实支付金额
        $return['noticestr']    = get_random_str();      //随机字符串
        // $md5key = M('User')->where(['id' => $userid])->getField('apikey');
        $md5key = M('PeAdmin')->where(['id' => $peadminid])->getField('apikey');
        $sign   = $this->createSign($md5key, $return);
        $return['sign'] = $sign;

        $this->successmMessage($return);
    }

    //进行自动卖出的交易
    public function autoSellC2COrder(){
        $this->checkUserVerify();

        $amount     = I("post.amount", 0);
        $fee        = I("post.fee", 0);
        $cointype   = I("post.cointype", 0);
        $orderid    = I("post.orderid", 0);
        $bank       = I("post.bank", '');
        $bankcard   = I("post.bankcard", '');
        $truename   = I("post.truename", '');
        $bankaddr   = I("post.bankaddr", '');   //附加信息
        $bankprov   = I("post.bankprov", '');   //省
        $bankcity   = I("post.bankcity", '');   //城市
        $notifyurl  = I("post.notifyurl");
        $peadminid  = I("post.userid");     // 商户ID
        
        $userid = $this->userid;
        $_POST['agentid'] = $_POST['agentid'] ? $_POST['agentid'] : $_POST['userid'];
        $_POST['userid'] = $userid;

        if(!$amount){
            $this->showmessage("OTC-金额信息不能为空!amount is null");
        }

        if($amount <= 0){
            $this->showmessage("OTC-金额必须大于0! amount is 0");
        }

        if(!$fee){
            $this->showmessage("OTC-手续费不能为空! fee is null");
        }

        if(!$cointype){
            $this->showmessage("OTC-货币类型不能为空! cointype is null");
        }

        if($fee < 0){
            $this->showmessage("OTC-手续费必须大于等于0! fee is 0");
        }

        if(!$orderid){
            $this->showmessage("OTC-订单号不能为空! orderid is null");
        }

        if(strlen($orderid) > 26){
            $this->showmessage("OTC-订单号不能超过26位! orderid strlen greater 26");
        }

        if(!$bank){
            $this->showmessage("OTC-银行名称不能为空! bank is null");
        }

        if(!$bankcard){
            $this->showmessage("OTC-银行卡号不能为空! bankcard is null");
        }

        if(!$truename){
            $this->showmessage("OTC-银行号账户名不能为空! truename is null");
        }

        if(!$peadminid){
            $this->showmessage("OTC-获取的商户ID不能为空! userid is null");
        }

        if(!$notifyurl){
            $this->showmessage("OTC-notifyurl不能为空! notifyurl is  null");
        }
        
        //判断外部订单号是否已经存在
        $order_status = $this->getExistExchangeOrderPayParams($userid, $orderid);
        if($order_status){
            if(is_array($order_status) && isset($order_status['status']) && $order_status['status'] === 0){
                $this->showmessage($order_status['msg']);
            }else{
                $orderid = $order_status['orderid'];
            }
        }else{
            $result = $this->autoCreateC2CBuyOrderWithApi($_POST);
            if(is_array($result) && $result['status'] === 0){
                $this->showmessage($result['msg']);
            }else{
                $orderid = $result;
            }
        }

        $return = array();
        $return['out_order_id'] = $orderid;              //otc平台的对应订单号
        $return['noticestr']    = get_random_str();      //随机字符串
        // $md5key = M('User')->where(['id' => $userid])->getField('apikey');
        $md5key = M('PeAdmin')->where(['id' => $peadminid])->getField('apikey');
        $sign   = $this->createSign($md5key, $return);
        $return['sign'] = $sign;
        $this->successmMessage($return);
    }

    //获取OTC是否可以进行交易
    public function getOTCPayStatus(){

        $this->checkUserVerify();

        $amount     = I("post.amount", 0);
        $peadminid     = I("post.userid");
        $channelid  = I("post.channelid", 0);
        $userid = $this->userid;

        if(!$amount){
            $this->showmessage("OTC-金额信息不能为空!");
        }

        if(!$channelid){
            $this->showmessage("OTC-获取的支付渠道ID不能为空!");
        }

        if(!$peadminid){
            $this->showmessage("OTC-获取的商户ID不能为空!");
        }
        
        //Log::record("getOTCPayStatus： amount= ".$amount.' channelid = '.$channelid, Log::INFO);

        $user_pay_info = $this->selectC2CUserPayParams($amount, $userid, $channelid);

        if(is_array($user_pay_info) && isset($user_pay_info['status']) && $user_pay_info['status'] === 0){
            $this->showmessage($user_pay_info['msg']);
        }
        elseif(empty($user_pay_info)){
            $this->showmessage('OTC-没有符合要求的商家信息！');
        }else{
            //Log::record("getOTCPayStatus： return = OK", Log::INFO);
            $this->successmMessage();
        }
    }

    //设置交易所对应CNC订单状态
    public function setOTCOrderStatus(){

        exit('接口暂时关闭!!! interface is close');
        $this->checkUserVerify();

        $orderid    = I("post.orderid", 0);
        $status     = I("post.status", 0);
        $noticestr  = I("post.noticestr");

        Log::record("setOTCOrderStatus orderid= ".$orderid.' status = '.$status.' noticestr = '.$noticestr, Log::INFO);

        if(!$orderid){
            $this->showmessage("OTC-订单号不能为空!");
        }

        if(!$status){
            $this->showmessage("OTC-订单状态不能为空!");
        }

        if(!$noticestr){
            $this->showmessage("OTC-noticestr3随机字符串参数不能为空！");
        }

        $res = false;
        if($status == 1){
            $res = R("Pay/PayExchange/confirmCNCSellOrderWithOutOrderId", array($orderid));
        }else{
            $res = R("Pay/PayExchange/cancelCNCSellOrderWithOutOrderId", array($orderid));
        }

        if($res === true){
            $this->successmMessage();
        }else{
             $this->showmessage("OTC-设置订单状态失败!");
        }
        exit;
    }

    //获取otcC2C订单的状态
    public function getOTCC2COrderStatus(){
        $this->checkUserVerify();

        $orderid  = I("post.orderid", 0);
        $peadminid   = I("post.userid", 0);
        $userid = $this->userid;

        if(!$peadminid){
            $this->showmessage("OTC-获取的商户ID不能为空!");
        }

        if(!$orderid){
            $this->showmessage("OTC-获取的订单号不能为空!");
        }

        $status = R("Pay/PayExchange/getOTCC2COrderStatus", array($userid, $orderid));

        $return = array();
        $return['order_status']    = $status;        //状态(-1订单不存在，0 未处理，1处理中，2待确认，3成功，4失败)
        $return['noticestr'] = get_random_str();     //随机字符串
        // $md5key = M('User')->where(['id' => $userid])->getField('apikey');
        $md5key = M('PeAdmin')->where(['id' => $peadminid])->getField('apikey');
        $sign   = $this->createSign($md5key, $return);
        $return['sign'] = $sign;
        $this->successmMessage($return);
    }


    public function getOTCUserBalance(){
        $this->checkUserVerify();

        $coin_type = I("post.coin_type", 0);
        $peadminid   = I("post.userid", 0);
        $userid = $this->userid;

        if(!$peadminid){
            $this->showmessage("OTC-获取的商户ID不能为空!");
        }

        if(!$coin_type){
            $this->showmessage("OTC-获取的货币类型不能为空!");
        }
        $coin_type = strtolower($coin_type);//转小写
        $balance = R("Pay/PayExchange/getOTCUseridBalance", array($userid, $coin_type));

        $return = array();
        $return['balance']  = round($balance, 3);    //余额
        $return['noticestr']= get_random_str();      //随机字符串
        // $md5key = M('User')->where(['id' => $userid])->getField('apikey');
        $md5key = M('PeAdmin')->where(['id' => $peadminid])->getField('apikey');
        $sign   = $this->createSign($md5key, $return);
        $return['sign'] = $sign;
        $this->successmMessage($return);
    }

    //通过银行短信设置C2C订单状态
    public function setOTCOrderStatusWithBankSms(){
        $this->checkUserVerify();
        
        $peadminid          = I("post.userid", 0);
        $userid             = $this->userid;
        $_POST['agentid']   = $_POST['agentid'] ? $_POST['agentid'] : $_POST['userid'];
        $_POST['userid']    = $userid;
        $amount             = I("post.amount", 0);
        $time               = I("post.time", 0);
        $bankcard           = I("post.bankcard", 0);
        $channelid          = I("post.channelid", 0);
        $noticestr          = I("post.noticestr");

        //Log::record("setOTCOrderStatusWithBankSms OTC-收到消息！ userid= ".$userid.' amount = '.$amount.' bankcard = '.$bankcard.' channelid = '.$channelid, Log::INFO);

        if(!$amount){
            Log::record("setOTCOrderStatusWithBankSms OTC-金额amount不能为空 userid=".$userid.',channelid='.$channelid, Log::INFO);
            $this->showmessage("OTC-金额amount不能为空!");
        }

        if($amount <= 0){
            Log::record("setOTCOrderStatusWithBankSms OTC-金额amount必须大于0 userid=".$userid.',channelid='.$channelid, Log::INFO);
            $this->showmessage("OTC-金额amount必须大于0!");
        }

        if(!$time){
            Log::record("setOTCOrderStatusWithBankSms OTC-订单时间time不能为空 userid=".$userid.',channelid='.$channelid, Log::INFO);
            $this->showmessage("OTC-订单时间time不能为空!");
        }

        if(!is_numeric($time)){
            Log::record("setOTCOrderStatusWithBankSms OTC-订单时间格式错误，需为时间戳格式 userid=".$userid.',channelid='.$channelid, Log::INFO);
            $this->showmessage("OTC-订单时间格式错误，需为时间戳格式!");
        }

        if($time-30 > time()){
            Log::record("setOTCOrderStatusWithBankSms OTC-订单时间错误，比平台当前时间大 userid=".$userid.',channelid='.$channelid, Log::INFO);
            $this->showmessage("OTC-订单时间错误，比平台当前时间大! curtime = ".time().' out_time = '.$time);
        }

        if(!$bankcard || $bankcard == ''){
            Log::record("setOTCOrderStatusWithBankSms OTC-银行账号信息bankcard不能为空 userid=".$userid.',channelid='.$channelid, Log::INFO);
            $this->showmessage("OTC-银行账号信息bankcard不能为空!");
        }

        if(!$channelid){
            Log::record("setOTCOrderStatusWithBankSms OTC-账户类型channelid不能为空 userid=".$userid.',time='.$time, Log::INFO);
            $this->showmessage("OTC-账户类型channelid不能为空!");
        }

        if(!$noticestr){
            Log::record("setOTCOrderStatusWithBankSms OTC-noticestr随机字符串参数不能为空！ userid=".$userid.',time='.$time, Log::INFO);
            $this->showmessage("OTC-noticestr随机字符串参数不能为空！");
        }

        //检查是否已经收到过该消息
        $res = $this->addOutsideMsg($_POST);
        if($res !== true){
            Log::record("setOTCOrderStatusWithBankSms OTC-重复的通知消息！ userid=".$userid.',time='.$time.',channelid='.$channelid, Log::INFO);
            $this->showmessage($res['msg']);
        }

        $rcv_time = time();
        $where = array();
        $where['otype']     = 2;
        $where['userid']    = $userid;
        $where['mum']       = $amount;
        $where['pay_channelid'] = $channelid;
        $where['notifyurl'] = ['exp','is not null'];
        $NormalOrderHandle = true;
        switch ($channelid) {
            case '251':
                $where['bankcard']  = ['like', "%" . $bankcard . "%"];
                break;
            case '302':
                $NormalOrderHandle = false;
                unset($where['userid']);
                $where['aid'] = $userid;
                $return_order_id = substr(trim($bankcard), -5);
                $where['return_order_id']  = $return_order_id;
                break;
            default:
                $where['bank']  = ['like', "%" . $bankcard . "%"];
                break;
        }

        if($NormalOrderHandle){
            //时间误差判断
            if(abs($rcv_time - $time) < 90){
                //尝试确认可能的订单
                $this->checkC2CMybeOrder($where, $rcv_time);
            }else{
                $start_time = $rcv_time - 10*60;
                $this->setNotConfirmOrder($where, $start_time, $rcv_time);
            }
        }else{
            $this->confirmOrderWithReturnOrderId($where);
        }
    }

     //设置otc交易所订单的返回交易号后5位
    public function setOTCOrderReturnOrderId(){

        $this->checkUserVerify();

        $orderid            = I("post.orderid", 0);
        $return_order_id    = I("post.return_order_id", 0);
        $noticestr          = I("post.noticestr");
        $pay_proof          = I("post.pay_proof", '');
        $return_bank_name   = I("post.return_bank_name", '');
        $return_account_name= I("post.return_account_name", '');

        Log::record("setOTCOrderStatus orderid= ".$orderid.' return_order_id = '.$return_order_id.' noticestr = '.$noticestr.' pay_proof = '.$pay_proof.' return_bank_name = '.$return_bank_name.' return_account_name = '.$return_account_name, Log::INFO);

        if(!$orderid){
            $this->showmessage("OTC-订单号不能为空!");
        }

        if(!$return_order_id){
            $this->showmessage("OTC-返回的尾数订单号不能为空!");
        }

        if(!$noticestr){
            $this->showmessage("OTC-noticestr3随机字符串参数不能为空！");
        }

        // $res = D('ExchangeOrder')->where(array('orderid'=>$orderid))->save(['return_order_id'=>$return_order_id, 'updatetime'=>time()]);;
        $res = D('ExchangeOrder')->where(array('orderid'=>$orderid))->save([
            'return_order_id'=>$return_order_id,
            'pay_proof'=>$pay_proof,
            'return_bank_name'=>$return_bank_name,
            'return_account_name'=>$return_account_name,
            'updatetime'=>time()
        ]);;
        if($res){
            $this->successmMessage();
        }else{
            $this->showmessage("OTC-设置订单尾号失败!");
        }
    }
    
    //获取代付付款截图
    public function getOTCOrderPayProof(){

        $this->checkUserVerify();

        $orderid            = I("post.orderid", 0);
        $noticestr          = I("post.noticestr");

        Log::record("setOTCOrderStatus orderid= ".$orderid.' noticestr = '.$noticestr, Log::INFO);

        if(!$orderid){
            $this->showmessage("OTC-订单号不能为空!");
        }

        if(!$noticestr){
            $this->showmessage("OTC-noticestr3随机字符串参数不能为空！");
        }

        // $res = D('ExchangeOrder')->where(array('orderid'=>$orderid))->save(['return_order_id'=>$return_order_id, 'updatetime'=>time()]);;
        $order = D('ExchangeOrder')->where(array('orderid'=>$orderid))->find();
        if($order){
            if ($order['status'] != 3) {
                $this->showmessage("OTC-订单未完成付款!");
                
            }
            
            $this->successmMessage(['pay_proof' => $order['pay_proof']]);
        }else{
            $this->showmessage("OTC-订单不存在!");
        }
    }

    //尝试确认可能的订单
    private function checkC2CMybeOrder($where, $rcv_time){
        if(is_array($where) && $rcv_time > 0){
            //最近10分钟是否有唯一订单
            $start_time = $rcv_time - 10*60;
            $end_time = $rcv_time+30;
            if(!$this->confirmOrderWithTime($where, $start_time, $end_time, $rcv_time)){

                $this->setNotConfirmOrder($where, $start_time, $end_time);
            }
        }
    }

    //确认时间范围内的订单
    private function confirmOrderWithTime($where, $start_time, $end_time, $rcv_time){
        if(is_array($where) && $start_time > 0 &&  $end_time > 0){
            unset($where['status']);
            $where['addtime'] = ['between', [$start_time, $end_time]];
            $order_count = D('ExchangeOrder')->where($where)->count();
            if($order_count == 1){ //单个为唯一订单
                $where['status'] = 2;
                $order_info = D('ExchangeOrder')->where($where)->find();
                if(!empty($order_info)){
                    $this->notifyPayTypeOrder($order_info['pay_channelid'], $order_info, $end_time);
                }else{
                    $start_time = $rcv_time - 10*60;
                    $this->setNotConfirmOrder($where, $start_time, $end_time);
                }
            }elseif($order_count > 1){
                $this->setNotConfirmOrder($where, $start_time, $end_time);
            }else{
                return false;
            }
            return true;
        }
        return false;
    }

    //确认返回订单尾号的订单
    private function confirmOrderWithReturnOrderId($where){
        $order_info = D('ExchangeOrder')->where($where)->find();
        if(!empty($order_info)){
            $channelid = $order_info['pay_channelid'];
            $paytype_config = M('paytype_config')->where(['channelid'=> $channelid])->find();
            if(!empty($paytype_config)){
                $order_info['is_admin'] = true;
                $this->autoConfirmExchangeOrder($order_info);
            }else{
                Log::record("OTC-confirmOrderWithReturnOrderId channelid={$channelid}的账户类型不存在！", Log::INFO);
                $this->showmessage("OTC-confirmOrderWithReturnOrderId channelid={$channelid}的账户类型不存在！");
            }
        }else{
            Log::record("OTC-confirmOrderWithReturnOrderId OTC-C2C订单 订单号不存在！orderid = ".$orderid, Log::INFO);
            $this->showmessage("OTC-confirmOrderWithReturnOrderId OTC-C2C订单 订单号不存在！orderid = ".$orderid);
        }
    }

    //处理不能判断的订单
    private function setNotConfirmOrder($where, $start_time, $end_time){
        if(is_array($where) && $start_time > 0 &&  $end_time > 0){
            //最近15分钟是否有唯一订单
            $where['status'] = 2;
            $where['addtime'] = ['between', [$start_time, $end_time]];
            $where['rush_status'] = ['lt', 1];
            $res = D('ExchangeOrder')->where($where)->save(['rush_status'=>2, 'updatetime'=>time()]);
            if($res){
                Log::record("setNotConfirmOrder OTC-没有找到确认订单，已经标记所有可能的订单start_time={$start_time},end_time={$end_time}！", Log::INFO);
                $this->showmessage("OTC-setNotConfirmOrder 没有找到确认订单，已经标记所有可能的订单！");
            }else{
                Log::record("setNotConfirmOrder OTC-C2C订单信息不存在2222！", Log::INFO);
                $this->showmessage("OTC-setNotConfirmOrder C2C订单信息不存在！");
            }
        }else{
            Log::record("setNotConfirmOrder OTC-C2C订单获取条件where不存在！", Log::INFO);
            $this->showmessage("OTC-setNotConfirmOrder C2C订单获取条件where不存在！");
        }
    }

    //通知对应的支付类型处理订单
    private function notifyPayTypeOrder($channelid, $order_info, $rcv_time){
        $paytype_config = M('paytype_config')->where(['channelid'=> $channelid])->find();
        if($paytype_config){
            if(isset($paytype_config['is_auto_notify']) && $paytype_config['is_auto_notify']){

                autoConfirmPayTypeOrder($channelid, $order_info, $paytype_config, $rcv_time);
            }else{
                Log::record("OTC-notifyPayTypeOrder 账户不是自动通知类型，请检查", Log::INFO);
                $this->showmessage('OTC-账户不是自动通知类型，请检查! is not auto type ');
            }
        }else{
            Log::record("OTC-notifyPayTypeOrder channelid={$channelid}的账户类型不存在！", Log::INFO);
            $this->showmessage("OTC-notifyPayTypeOrder channelid={$channelid}的账户类型不存在！");
        }
    }

    //自动确认支付订单
    private function autoConfirmPayTypeOrder($channelid, $order_info, $paytype_config, $rcv_time){
        if (!is_file(APP_PATH . '/' . MODULE_NAME . '/Controller/' . $paytype_config['code'] . 'Controller.class.php')) {
            Log::record('OTC-autoConfirmPayTypeOrder 账户类型不存在'.$paytype_config['code'], Log::INFO);
            $this->showmessage('OTC-账户类型不存在'.$paytype_config['code']);
        }
        $return = R($paytype_config['code'] . '/notifyurl', array($order_info));
        if($return !== true) {
            Log::record('OTC-autoConfirmPayTypeOrder '.$paytype_config['code'].'的处理回调notifyurl错误', Log::INFO);
            $this->showmessage('OTC-'.$paytype_config['code'].'的处理回调notifyurl错误');
        }else{
            Log::record("OTC-autoConfirmPayTypeOrder 处理成功！ userid=".$order_info['userid'].',amount='.$order_info['mum'].',rcv_time='.$rcv_time.',channelid='.$channelid,',bankcard='.isset($order_info['bankcard'])?$order_info['bankcard']:$order_info['bank'], Log::INFO);
            $this->successmMessage();
        }
    }

    //直接确认交易订单
    private function autoConfirmExchangeOrder($order_info){
        if(!empty($order_info)){
            $res = R("Pay/PayExchange/confirmC2COrderWithOrderInfo", array($order_info));
            if ($res === true) {
                $this->successmMessage();
            } else {
                $this->showmessage('OTC-确认交易订单失败 orderid = '.$order_info['orderid'].', res = '.$res);
            }
        }
    }
}