<?php

namespace Pay\Controller;

use Think\Controller;
use Think\Log;

/**
 * @author guotian
 * @date   2018-06-06
 */
//获取更新控制器
class PayUpdateController extends BaseController
{
    /**
     * 初始化控制器
     * BaseController constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    //更新货币汇率
    public function autoUpdateCurrencyExchangeRate(){

        $res = $this->updateCurrencyExchangeRate('MYR', 'CNY');

        return $res;
    }

    //更新账户的权重值
    public function autoUpdatePayParamsWeight(){

        $payTypeWhere = array(
            'status'        => 1,
        );

        $payType_list = M('paytype_config')->where($payTypeWhere)->field('channelid, default_weight')->select();
        //用渠道id作为key的账户类型列表
        $channelidPayType_list = array();
        foreach ($payType_list as $key => $value) {
            $channelidPayType_list[$value['channelid']] = $value;
        }

        //用户的CNC权重
        $userCNCWeight_list = $this->getPayParamsCNCWeightList();
        //用户的操作时间的权重
        $opreateTimeWeight_list = $this->getOperateTimeWeightList();

        //1天内有交易
        $limit_time = time() - 24*60*60;
        $payParamsWhere = array(
            'status'            => 1,
            'check_status'      => 1,
            'last_paying_time'  => ['gt', $limit_time], //1天内有订单的账户
        );
        //筛选支持该支付类型的用户的参数
        $payParams_list = M('payparams_list')->where($payParamsWhere)->select();
        //已经更新的账户的用户ID
        $updated_userid_list = array();
        foreach ($payParams_list as $key => $payParams) {
            $userid = $payParams['userid'];
            $payParams_id = $payParams['id'];
            $payParamsWeight = 0;
            $channelid = $payParams['channelid'];
            //获取默认权重
            $default_weight = $channelidPayType_list[$channelid]['default_weight'];
            $default_weight = $default_weight > 0 ? $default_weight : 1;
            $payParamsWeight = $default_weight;
            //计算成功率列表
            $successRateList = $this->getSuccessRateList($payParams_id);
            if(!empty($successRateList) && is_array($successRateList)){
                //成功率的权重
                $successWeight = $this->getSuccessRateWeight($successRateList[0][2]);
                $payParamsWeight = $payParamsWeight + $successWeight;
            }
            //CNC的权重
            $CNCWeight = isset($userCNCWeight_list[$userid]) ? $userCNCWeight_list[$userid] : 0;
            $payParamsWeight = $payParamsWeight + $CNCWeight;
            //操作时间的权重
            $opreateTiemWeight = isset($opreateTimeWeight_list[$userid]) ? $opreateTimeWeight_list[$userid] : 0;
            $payParamsWeight = $payParamsWeight + $opreateTiemWeight;
            //更新数据
            $date = array(
                'weight'            =>$payParamsWeight,
                'before_weight'     =>$payParamsWeight,
                'success_rate_list' =>json_encode($successRateList),
            );
            M('payparams_list')->where(['id'=>$payParams_id])->save($date);
        }
        return true;
    }

    //设置惩罚权重
    public function setPunishmentWeight($userid, $punishment_level){
        if($userid && $punishment_level){
            $payParamsWhere = array(
                'userid'        => $userid,
                'status'        => 1,
                'check_status'  => 1
            );

            $weight_time = 0;
            switch ($punishment_level) {
                case 1:
                    $weight_time = time() + 60*60;
                    break;
                case 2:
                    $weight_time = time() + 2*60*60;
                    break;
                case 3:
                    $weight_time = time() + 8*60*60;
                    break;
            }

            if($weight_time > 0){
                return M('payparams_list')->where($payParamsWhere)->save(['weight_time'=>$weight_time, 'weight'=>1]);
            }
        }
        return false;
    }

    //更新惩罚权重的时间和状态
    public function updatePunishmentTimeAndStatus($payParams){
        if(isset($payParams) && !empty($payParams) && $payParams['weight_time'] > 0 && time() > $payParams['weight_time']){

            return M('payparams_list')->where(['userid'=>$payParams['userid']])->save(['weight'=>$payParams['before_weight'], 'weight_time'=>0]);
        }
        return true;
    }

    //获取成功率的权重
    private function getSuccessRateWeight($allSuccessRate=0){
        if(isset($allSuccessRate)){
            $addWeight = 0;
            switch ($allSuccessRate) {
                case 20:
                    $addWeight = 1;
                    break;
                case 30:
                    $addWeight = 2;
                    break;
                case 40:
                    $addWeight = 3;
                    break;
                case 50:
                    $addWeight = 4;
                    break;
                case 60:
                    $addWeight = 5;
                    break;
                case 70:
                    $addWeight = 7;
                    break;
                case 80:
                    $addWeight = 10;
                    break;
                case 90:
                    $addWeight = 15;
                    break;
                default:
                    $addWeight = 0;
                    break;
            }
            return $addWeight;
        }
        return 0;
    }

    //获取CNC数量的权重
    private function getPayParamsCNCWeightList(){
        $userCNCWeight_list = array();
        $coin_type  = Anchor_CNY; //货币类型
        $base_count = 20000; //每2万增加1
        $max_weight = 10;
        if($base_count > 0){
            $user_coin_list = M('user_coin')->where(array($coin_type=>['egt', $base_count]))->select();
            foreach ($user_coin_list as $key => $user_coin) {
                $userid = $user_coin['userid'];
                $cur_weight = intval($user_coin[$coin_type] / $base_count);
                $cur_weight = $cur_weight > $max_weight ? $max_weight : $cur_weight;
                $userCNCWeight_list[$userid] = $cur_weight;
            }
        }
        return $userCNCWeight_list;
    }

    //获取操作时间的权重
    private function getOperateTimeWeightList(){
        
        $cur_time = time();
        $start_time = $cur_time - 60*60;

        $opreateTimeWeight_list = array();
        //1天内有交易
        $limit_time = time() - 24*60*60;
        $where = array(
            'status'            => 1, //正常账户
            'idstate'           => 2, //认证通过
            'last_exchange_time'=>['gt', $limit_time],
        );
        $user_list = M('user')->where($where)->select();
        foreach ($user_list as $key => $user) {
            $userid = $user['id'];
            $weight = 0;
            if(isset($userid)){
                $where = array(
                    'userid'    => $userid,
                    'otype'     => 2,
                    'addtime'   => ['between', [$start_time, $cur_time]],
                    'status'    => 3
                );
                $timeInfo = D('ExchangeOrder')->where($where)->field('(sum(endtime) - sum(addtime)) as timesum')->find();
                if(!empty($timeInfo) && isset($timeInfo['timesum'])){
                    $timeSum = $timeInfo['timesum'];
                    $orderCount = D('ExchangeOrder')->where($where)->count();
                    if($orderCount > 0){
                        $averageTime = intval($timeSum / $orderCount);
                        if($averageTime < 2*60){
                            $weight = 5;
                        }elseif($averageTime < 3*60){
                            $weight = 3;
                        }elseif($averageTime < 5*60){
                            $weight = 2;
                        }
                    }
                }
            }
            $opreateTimeWeight_list[$userid] = $weight;
        }
        return $opreateTimeWeight_list;
    }

    //计算不同的成功率分布
    public function getSuccessRateList($payParams_id){
        $successRateList = array();
        if(isset($payParams_id)){

            $cur_time = time();
            $start_time = $cur_time - 60*60;
            $where = [
                'payparams_id'  => $payParams_id,
                'addtime'       => ['between', [$start_time, $cur_time]],
            ];

            //总体成功率
            $start_amount = 0;
            $end_amount = 0;
            $orderCount = D('ExchangeOrder')->where($where)->count();
            if($orderCount > 0){ 
                $where['status'] = 3;
                $orderSuccessCount = D('ExchangeOrder')->where($where)->count();
                //成功率
                $successRate = $orderSuccessCount/$orderCount;
                $successRate = intval($successRate/0.01);
                array_push($successRateList, [$start_amount, $end_amount, $successRate]);
            }else{
                array_push($successRateList, [$start_amount, $end_amount, -1]);
            }

            $start_amount = 0;
            $end_amount = 500;
            unset($where['status']);
            $where['mum'] = ['between', [$start_amount, $end_amount]];
            $orderCount = D('ExchangeOrder')->where($where)->count();
            if($orderCount > 0){ 
                $where['status'] = 3;
                $orderSuccessCount = D('ExchangeOrder')->where($where)->count();
                //成功率
                $successRate = $orderSuccessCount/$orderCount;
                $successRate = intval($successRate/0.01);
                array_push($successRateList, [$start_amount, $end_amount, $successRate]);
            }else{
                array_push($successRateList, [$start_amount, $end_amount, -1]);
            }

            $start_amount = 500;
            $end_amount = 1500;
            unset($where['status']);
            $where['mum'] = ['between', [$start_amount, $end_amount]];
            $orderCount = D('ExchangeOrder')->where($where)->count();
            if($orderCount > 0){ 
                $where['status'] = 3;
                $orderSuccessCount = D('ExchangeOrder')->where($where)->count();
                //成功率
                $successRate = $orderSuccessCount/$orderCount;
                $successRate = intval($successRate/0.01);
                array_push($successRateList, [$start_amount, $end_amount, $successRate]);
            }else{
                array_push($successRateList, [$start_amount, $end_amount, -1]);
            }

            $start_amount = 1500;
            $end_amount = 5000;
            unset($where['status']);
            $where['mum'] = ['between', [$start_amount, $end_amount]];
            $orderCount = D('ExchangeOrder')->where($where)->count();
            if($orderCount > 0){ 
                $where['status'] = 3;
                $orderSuccessCount = D('ExchangeOrder')->where($where)->count();
                //成功率
                $successRate = $orderSuccessCount/$orderCount;
                $successRate = intval($successRate/0.01);
                array_push($successRateList, [$start_amount, $end_amount, $successRate]);
            }else{
                array_push($successRateList, [$start_amount, $end_amount, -1]);
            }

            $start_amount = 5000;
            $end_amount = 10000;
            unset($where['status']);
            $where['mum'] = ['between', [$start_amount, $end_amount]];
            $orderCount = D('ExchangeOrder')->where($where)->count();
            if($orderCount > 0){ 
                $where['status'] = 3;
                $orderSuccessCount = D('ExchangeOrder')->where($where)->count();
                //成功率
                $successRate = $orderSuccessCount/$orderCount;
                $successRate = intval($successRate/0.01);
                array_push($successRateList, [$start_amount, $end_amount, $successRate]);
            }else{
                array_push($successRateList, [$start_amount, $end_amount, -1]);
            }

            $start_amount = 10000;
            $end_amount = 1000000;
            unset($where['status']);
            $where['mum'] = ['egt', $start_amount];
            $orderCount = D('ExchangeOrder')->where($where)->count();
            if($orderCount > 0){ 
                $where['status'] = 3;
                $orderSuccessCount = D('ExchangeOrder')->where($where)->count();
                //成功率
                $successRate = $orderSuccessCount/$orderCount;
                $successRate = intval($successRate/0.01);
                array_push($successRateList, [$start_amount, $end_amount, $successRate]);
            }else{
                array_push($successRateList, [$start_amount, $end_amount, -1]);
            }
        }
        return $successRateList;
    }
}