<?php

namespace Cli\Controller;

use Think\Controller;
use Think\Log;

/**
 * @author guotian
 * @date   2018-06-06
 */
//自动处理交易
class AutoExchangeController extends BaseController
{
    //自动发起交易
    public function index()
    {
        echo "[" . date('Y-m-d H:i:s'). "] 自动交易任务触发\n";
        Log::record("自动交易任务触发", Log::INFO);
        //处理逻辑
        //同步价格
        $this->doSyncBitPrice();
        //处理交易
        $this->doExchange();
        Log::record("自动交易任务结束", Log::INFO);
        echo "[" . date('Y-m-d H:i:s'). "] 自动交易任务结束\n";
        exit;
    }

    //提交
    private function doExchange()
    {
        //处理机器人交易
        $success = $fail = 0;
        $marketlist = R('Home/Queue/getmarketlist');
        if(is_array($marketlist)){
            $listscount = count($marketlist);
            Log::record("本次计划任务处理".$listscount.'个市场', Log::INFO);
            echo "本次计划任务处理".$listscount."个市场\n";
            foreach ($marketlist as $k => $v) {
                $res = R('Home/Queue/autojy2',array($v['name']));
                if ($res === true) {
                    echo "处理".$v['name']."市场成功\n";
                    Log::record("处理".$v['name']."市场成功", Log::INFO);
                    $success++;
                } else {
                    if($res){
                        echo $res;
                        Log::record("处理失败：".$res, Log::INFO);
                    }
                    $fail++;
                }
            }
        }
        Log::record("[" . date('Y-m-d H:i:s'). "] 成功：".$success."，失败：".$fail, Log::INFO);
        echo "成功：".$success."，失败：".$fail."\n";
    }

    //自动匹配交易
    public function autoMatch()
    {
        echo "[" . date('Y-m-d H:i:s'). "] 自动匹配交易任务触发\n";
        Log::record("自动匹配交易任务触发", Log::INFO);
        $this->doMatch();
        Log::record("自动匹配交易任务结束", Log::INFO);
        echo "[" . date('Y-m-d H:i:s'). "] 自动匹配交易任务结束\n";
    }

    //匹配交易
    private function doMatch()
    {
        $success = $fail = 0;
        $count = 2;
        Log::record("本次计划任务匹配".$count.'次', Log::INFO);
        echo "本次计划任务匹配".$count."次\n";
        for ($i=0; $i < 2; $i++) { 
            if(R("Home/Queue/checkDapan") === false){
                $fail++;
            }else{
                $success++;
            }
        }
        Log::record("[" . date('Y-m-d H:i:s'). "] 成功：".$success."，失败：".$fail, Log::INFO);
        echo "成功：".$success."，失败：".$fail."\n";
    }

    //删除不需要的数据
    public function delPath(){

        D('AuthRule')->delCode();
    }

    //同步市场的货币价格
    private function doSyncBitPrice(){
        //同步CNY(人民币)价格
        $res = R("Home/Queue/usdtToRMB");
        if($res === true){
            echo "获取CNY价格成功\n";
            Log::record("获取CNY价格成功", Log::INFO);
        }else{
            echo "获取CNY价格失败:{$res}\n";
            Log::record("获取CNY价格失败:{$res}", Log::INFO);
        }

        //同步其他货币价格
        if(R("Home/Queue/PriceFromfireCoinWeb") === true){
            echo "获取其他货币价格成功\n";
            Log::record("获取其他货币价格成功", Log::INFO);
        }else{
            echo "获取其他货币价格失败\n";
            Log::record("获取其他货币价格失败", Log::INFO);
        }
    }
}