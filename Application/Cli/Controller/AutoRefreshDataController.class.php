<?php

namespace Cli\Controller;

use Think\Controller;
use Think\Log;

/**
 * @author guotian
 * @date   2018-06-06
 */
//自动处理刷新数据
class AutoRefreshDataController extends BaseController
{
    //自动及时更新数据
    public function index()
    {
        echo "[" . date('Y-m-d H:i:s'). "] 自动更新任务触发\n";
        Log::record("自动更新任务触发", Log::INFO);
        //处理逻辑

        //更新行情
        //$this->doChart();
        //更新市场
        //$this->doUpdate();
        //同步钱包的数据
        //$this->doWallet();
        //更新汇率
        //$this->doUpdateCurrencyExchangeRate();
        //自动补发通知
        $this->doRepostMemberOrderStatus();

        Log::record("自动更新任务结束", Log::INFO);
        echo "[" . date('Y-m-d H:i:s'). "] 自动更新任务结束\n";
        exit;
    }

    //更新行情
    private function doChart(){

        $res = R('Home/Queue/chart');//计算行情
        if ($res === true) {
            Log::record("处理计算行情成功", Log::INFO);
            echo "处理计算行情成功\n";
        } else {
            Log::record("处理计算行情失败", Log::INFO);
            echo "处理计算行情失败\n";
        }
    }

    //更新市场
    private function doUpdate()
    {
        $queues = array(          
            'Home/Queue/move',          //处理交易状态:正常
            'Home/Queue/yichang',       //处理交易状态:异常
        );
        $success = $fail = 0;
        $listscount = count($queues);
        Log::record("更新市场任务处理".$listscount.'个更新', Log::INFO);
        echo "更新市场任务处理".$listscount."个更新\n";
        foreach ($queues as $k => $v) {
            $res = R($v);
            if ($res === true) {
                $success++;
            } else {
                $fail++;
            }
        }
        Log::record("[" . date('Y-m-d H:i:s'). "] 成功：".$success."，失败：".$fail, Log::INFO);
        echo "成功：".$success."，失败：".$fail."\n";
    }

     //同步钱包的数据
    private function doWallet(){

        $queues = array(
            'Home/Queue/syncWalletInfo',                              //同步钱包转入记录
            // 'Home/Queue/tokensonlinea88b77c11d0a9d/coin/suf',      //同步钱包转入记录
            // 'Home/Queue/tokensonlinea88b77c11d0a9d/coin/cw',       //同步钱包转入记录
            // 'Home/Queue/tokensonlinea88b77c11d0a9d/coin/fff',      //同步钱包转入记录
        );
        $success = $fail = 0;
        $listscount = count($queues);
        Log::record("本次计划更新钱包".$listscount.'个更新', Log::INFO);
        echo "本次计划更新钱包".$listscount."个更新\n";
        foreach ($queues as $k => $v) {
            $res = R($v);
            if ($res === true) {
                $success++;
            } else {
                $fail++;
            }
        }
        Log::record("[" . date('Y-m-d H:i:s'). "] 成功：".$success."，失败：".$fail, Log::INFO);
        echo "成功：".$success."，失败：".$fail."\n";
    }

    //同步当前汇率
    private function doUpdateCurrencyExchangeRate(){

        //开始更新汇率
        Log::record("开始更新汇率", Log::INFO);
        echo "开始更新汇率\n";
        $res = R('Pay/PayUpdate/autoUpdateCurrencyExchangeRate');
        if($res){
            Log::record("更新汇率成功", Log::INFO);
            echo "更新汇率成功\n";
        }
        Log::record("结束更新汇率", Log::INFO);
        echo "更新汇率成功\n";
    }

    //补发商户平台通知
    private function doRepostMemberOrderStatus(){
        echo "[" . date('Y-m-d H:i:s'). "] 自动补发通知开始\n";
        Log::record("自动补发通知开始 time：".date('Y-m-d H:i:s'), Log::INFO);
        $cur_time = time();
        $start_time = $cur_time - 24*60*60; //1天内的订单
        //最多通知5次
        $where = [
            'status'    => 3,
            'notifyurl' => ['exp','is not null'],
            'addtime'   => ['between', [$start_time, $cur_time]],
            'repost_num'=> ['between', [1,5]],
        ];
        $list = D('ExchangeOrder')->where($where)->order('id desc')->limit(10)->select();
        if(!empty($list)){
            foreach ($list as $key => $value) {
                if(isset($value['notifyurl']) && is_string($value['notifyurl'])){
                    $res = R("Pay/PayExchange/requestMemberOrderStatus", array($value, 2));
                    if(!$res){
                        echo "补发通知失败 orderID： ".$value['orderid']."\n";
                        Log::record("补发通知失败 orderID： ".$value['orderid'], Log::INFO);
                    }
                }
            }
        }
        echo "[" . date('Y-m-d H:i:s'). "] 自动补发通知完成\n";
        Log::record("自动补发通知完成 time：".date('Y-m-d H:i:s'), Log::INFO);
    }

    //每日更新一次的数据
    public function autoEveryDayOnce(){
        echo "[" . date('Y-m-d H:i:s'). "] 自动每日更新一次任务触发\n";
        Log::record("自动每日更新一次任务触发", Log::INFO);
        //处理逻辑
        $this->doTendency();
        //删除机器人的无效订单
        $this->doDeleteAllRobotOvertimeTrade();
        //清除失效的C2C交易订单
        $this->doCancelAllOvertimeC2COrder();
        //清除超过月的币币交易成功json日志
        $this->doBeforeMonthTradeJsonDelete();
        //备份钱包数据
        $this->doBackupWalletData();
        //更新理财产品
        $this->doUpdateFinancingHandle();
        //刷新每日收款金额
        $this->doRefreshPayingMounyHandle();
        Log::record("自动每日更新一次任务结束", Log::INFO);
        echo "[" . date('Y-m-d H:i:s'). "] 自动每日更新一次任务结束\n";
    }

    private function doTendency(){
        $res = R('Home/Queue/houprice'); //更新收盘市场价格
        if ($res === true) {
            Log::record("更新收盘市场价格成功", Log::INFO);
            echo "更新收盘市场价格成功\n";
        } else {
            Log::record("更新收盘市场价格失败", Log::INFO);
            echo "更新收盘市场价格失败\n";
        }
        $res = R('Home/Queue/tendency'); //计算趋势,每天运行一次即可
        if ($res === true) {
            Log::record("处理市场趋势成功", Log::INFO);
            echo "处理市场趋势成功\n";
        } else {
            Log::record("处理市场趋势失败", Log::INFO);
            echo "处理市场趋势失败\n";
        }
    }

    //备份钱包数据
    private function doBackupWalletData(){
        $res = R('Home/Queue/outsideBackupWalletData'); //备份钱包数据
        if ($res === true) {
            Log::record("备份钱包数据成功", Log::INFO);
            echo "备份钱包数据成功\n";
        } else {
            Log::record("备份钱包数据失败", Log::INFO);
            echo "备份钱包数据失败\n";
        }
    }

    //更新理财产品
    private function doUpdateFinancingHandle(){
        $res = R('Home/Reward/FinancingHandle'); //更新理财产品
        if ($res === true) {
            Log::record("更新理财产品成功", Log::INFO);
            echo "更新理财产品成功\n";
        } else {
            Log::record("更新理财产品失败", Log::INFO);
            echo "更新理财产品失败\n";
        }
    }
    
    //更新理财产品
    private function doRefreshPayingMounyHandle(){
        $res = R('Home/Trade/refreshPayingMoney'); //更新理财产品
        if ($res === true) {
            Log::record("更新当天已经支付的金额成功", Log::INFO);
            echo "更新当天已经支付的金额成功\n";
        } else {
            Log::record("更新当天已经支付的金额失败", Log::INFO);
            echo "更新当天已经支付的金额失败\n";
        }
    }

    //每小时触发的逻辑
    public function autoEveryHourOnce(){
        echo "[" . date('Y-m-d H:i:s'). "] 自动每小时更新一次任务触发\n";
        Log::record("自动每小时更新一次任务触发", Log::INFO);
        //更新账户权重
        $this->doUpdatePayParamsWeight();
        Log::record("自动每小时更新一次任务结束", Log::INFO);
        echo "[" . date('Y-m-d H:i:s'). "] 自动每小时更新一次任务结束\n";
    }

    //自动计算今天所有账户的权重
    private function doUpdatePayParamsWeight(){
        $res = R('Pay/PayUpdate/autoUpdatePayParamsWeight'); //更新账户权重
        if ($res === true) {
            Log::record("更新账户权重成功", Log::INFO);
            echo "更新账户权重成功\n";
        } else {
            Log::record("更新账户权重失败", Log::INFO);
            echo "更新账户权重失败\n";
        }
    }

    //有限的清除失效订单
    public function autoClearOvertimeOrder(){
        echo "[" . date('Y-m-d H:i:s'). "] 及时清除失效订单\n";
        Log::record("及时清除失效订单", Log::INFO);
        //处理逻辑
        $this->doDeleteOvertimeTradeOrder();
        $this->doCancelOvertimeC2COrder();
        Log::record("及时清除失效订单结束", Log::INFO);
        echo "[" . date('Y-m-d H:i:s'). "] 及时清除失效订单结束\n";

    }

    //清除失效的币币交易机器人订单
    private function doDeleteOvertimeTradeOrder(){
        $res = R('Home/Trade/overtimeDelete');//清除失效订单
        if ($res === true) {
            Log::record("及时清楚失效的币币交易机器人订单成功", Log::INFO);
            echo "及时清除失效的币币交易机器人订单成功\n";
        } else {
            Log::record("及时清除失效的币币交易机器人订单失败", Log::INFO);
            echo "及时清除失效的币币交易机器人订单失败\n";
        }
    }

    //清除失效的C2C交易订单
    private function doCancelOvertimeC2COrder(){
        $res = R('Home/Exchange/overTimeC2CExchangeOrder');//清除失效订单
        if ($res === true) {
            Log::record("及时清除失效C2C交易订单成功", Log::INFO);
            echo "及时清除失效C2C交易订单成功\n";
        } else {
            Log::record("清除清楚失效C2C交易订单失败", Log::INFO);
            echo "及时清除失效C2C交易订单失败\n";
        }
    }

    //删除机器人超时的交易数据
    private function doDeleteAllRobotOvertimeTrade(){
        $res = R('Home/Trade/overtimeDeleteAll');//清除失效订单
        if ($res === true) {
            Log::record("清除【所有失效的币币交易】机器人订单成功", Log::INFO);
            echo "清除【所有失效的币币交易】机器人订单成功\n";
        } else {
            Log::record("清除【所有失效的币币交易】机器人订单失败", Log::INFO);
            echo "清除【所有失效的币币交易】机器人订单失败\n";
        }
    }

    //删除C2C超时的交易数据
    private function doCancelAllOvertimeC2COrder(){
        $res = R('Home/Exchange/overTimeC2CExchangeOrderAll');//清除失效订单
        if ($res === true) {
            Log::record("清除【所有C2C超时交易】订单成功", Log::INFO);
            echo "清除【所有C2C超时交易】订单成功\n";
        } else {
            Log::record("清除【所有C2C超时交易】订单失败", Log::INFO);
            echo "清除【所有C2C超时交易】订单失败\n";
        }
    }

    //清除超过月的币币交易成功json日志
    private function doBeforeMonthTradeJsonDelete(){
        $res = R('Home/Trade/beforeMonthTradeJsonDelete');//清除交易成功json日
        if ($res === true) {
            Log::record("清除币币交易成功【json日志】成功", Log::INFO);
            echo "清除币币交易成功【json日志】成功\n";
        } else {
            Log::record("清除币币交易成功【json日志】失败", Log::INFO);
            echo "清除币币交易成功【json日志】失败\n";
        }
    }
}