<?php
/**
 * 支付宝支付控制器
 * 
 * 处理支付宝支付相关业务逻辑，包括支付请求生成和异步通知处理
 * 命名空间：Pay\Controller
 * 文件名：AlipayPayController.class.php
 */

namespace Pay\PayController;

use Think\Controller;
use Think\Exception;

/**
 * Class AlipayPayController
 * @package Pay\Controller
 */
class WxPayController extends Controller
{
    public function pay ($config)
    {
        return [
            'status' => 1,
            'msg' => '支付请求生成成功',
            'data' => $config,
        ];
    }
    
    public function payment ($config){
        return [
            'status' => 1,
            'msg' => '代付请求生成成功',
            'data' => $config,
        ];
    }
}