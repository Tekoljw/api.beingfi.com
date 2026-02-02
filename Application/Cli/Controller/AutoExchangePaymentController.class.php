<?php

namespace Cli\Controller;

use Think\Controller;
use Think\Log;

/**
 * @author guotian
 * @date   2018-06-06
 */
//自动处理交易
class AutoExchangePaymentController extends BaseController
{
    public function index()
    {
        echo "[" . date('Y-m-d H:i:s'). "] 自动确认收款任务触发\n";
        Log::record("自动确认收款任务触发", Log::INFO);
        //处理逻辑
        $this->doExchange();
        Log::record("自动确认收款任务触发", Log::INFO);
        echo "[" . date('Y-m-d H:i:s'). "] 自动确认收款任务触发\n";
        exit;
    }

    //提交
    private function doExchange()
    {
        // 自动回掉已确认的订单
        R('Pay/PayExchange/autoExchangePaymentNotify');
    }
}