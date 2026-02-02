<?php
/**
 * 支付宝支付控制器
 * 
 * 处理支付宝支付相关业务逻辑，包括支付请求生成和异步通知处理
 * 命名空间：Pay\Controller
 * 文件名：AlipayPayController.class.php
 */

namespace Pay\Controller;

use Think\Controller;
use Think\Exception;

/**
 * Class AlipayPayController
 * @package Pay\Controller
 */
class AlipayPayController extends Controller
{
    /**
     * @var string 商户ID
     */
    private $merchantId = '';
    
    /**
     * @var string 支付宝应用ID
     */
    private $appId = '';
    
    /**
     * @var string 商户私钥
     */
    private $privateKey = '';
    
    /**
     * @var string 支付宝公钥
     */
    private $alipayPublicKey = '';
    
    /**
     * @var string 支付宝网关地址
     */
    private $gatewayUrl = 'https://openapi.alipay.com/gateway.do';
    
    /**
     * @var string 签名类型
     */
    private $signType = 'RSA2';
    
    /**
     * @var string 字符编码
     */
    private $charset = 'UTF-8';
    
    /**
     * @var string 返回格式
     */
    private $format = 'json';
    
    /**
     * @var string API版本
     */
    private $version = '1.0';
    
    /**
     * 构造函数
     * 初始化配置参数
     */
    public function __construct()
    {
        parent::__construct();
        
        // 从配置文件中读取支付宝配置
        $alipayConfig = C('ALIPAY_CONFIG');
        
        if (empty($alipayConfig)) {
            throw new Exception('支付宝配置未找到');
        }
        
        $this->appId = $alipayConfig['app_id'] ?? '';
        $this->privateKey = $alipayConfig['merchant_private_key'] ?? '';
        $this->alipayPublicKey = $alipayConfig['alipay_public_key'] ?? '';
        $this->merchantId = $alipayConfig['merchant_id'] ?? '';
        
        // 验证必要配置参数
        $this->validateConfig();
    }
    
    /**
     * 验证配置参数
     * 
     * @throws Exception 配置参数缺失时抛出异常
     */
    private function validateConfig()
    {
        $requiredParams = [
            'app_id' => $this->appId,
            'merchant_private_key' => $this->privateKey,
            'alipay_public_key' => $this->alipayPublicKey,
        ];
        
        foreach ($requiredParams as $paramName => $paramValue) {
            if (empty($paramValue)) {
                throw new Exception("支付宝配置参数 {$paramName} 不能为空");
            }
        }
    }
    
    /**
     * 支付接口
     * 
     * @param array $orderData 订单数据
     * @return array 支付结果
     */
    public function pay($orderData = [])
    {
        try {
            // 验证订单数据
            $this->validateOrderData($orderData);
            
            // 构建支付请求参数
            $bizContent = $this->buildBizContent($orderData);
            
            // 生成签名
            $sign = $this->generateSign($bizContent);
            
            // 构建请求参数
            $requestParams = $this->buildRequestParams($bizContent, $sign);
            
            // 生成支付URL
            $payUrl = $this->gatewayUrl . '?' . http_build_query($requestParams);
            
            return [
                'status' => 1,
                'msg' => '支付请求生成成功',
                'payurl' => $payUrl,
            ];
            
        } catch (Exception $e) {
            // 记录错误日志
            \Think\Log::write('支付宝支付请求失败：' . $e->getMessage(), 'ERROR');
            
            return [
                'status' => 0,
                'msg' => '支付请求生成失败：' . $e->getMessage(),
                'payurl' => '',
            ];
        }
    }
    
    /**
     * 验证订单数据
     * 
     * @param array $orderData 订单数据
     * @throws Exception 订单数据验证失败时抛出异常
     */
    private function validateOrderData($orderData)
    {
        $requiredFields = [
            'out_trade_no' => '商户订单号',
            'total_amount' => '订单总金额',
            'subject' => '订单标题',
        ];
        
        foreach ($requiredFields as $field => $fieldName) {
            if (!isset($orderData[$field]) || empty($orderData[$field])) {
                throw new Exception("订单数据 {$fieldName} 不能为空");
            }
        }
        
        // 验证金额格式
        if (!is_numeric($orderData['total_amount']) || $orderData['total_amount'] <= 0) {
            throw new Exception('订单金额必须为大于0的数字');
        }
        
        // 验证订单号格式
        if (!preg_match('/^[A-Za-z0-9_\-]{1,64}$/', $orderData['out_trade_no'])) {
            throw new Exception('商户订单号格式不正确');
        }
    }
    
    /**
     * 构建业务参数
     * 
     * @param array $orderData 订单数据
     * @return array 业务参数
     */
    private function buildBizContent($orderData)
    {
        $bizContent = [
            'out_trade_no' => $orderData['out_trade_no'],
            'total_amount' => number_format($orderData['total_amount'], 2, '.', ''),
            'subject' => $orderData['subject'],
            'product_code' => 'FAST_INSTANT_TRADE_PAY',
            'timeout_express' => '30m',
        ];
        
        // 可选参数
        if (!empty($orderData['body'])) {
            $bizContent['body'] = $orderData['body'];
        }
        
        if (!empty($orderData['time_expire'])) {
            $bizContent['time_expire'] = $orderData['time_expire'];
        }
        
        return $bizContent;
    }
    
    /**
     * 生成签名
     * 
     * @param array $bizContent 业务参数
     * @return string 签名
     * @throws Exception 签名生成失败时抛出异常
     */
    private function generateSign($bizContent)
    {
        $params = [
            'app_id' => $this->appId,
            'method' => 'alipay.trade.page.pay',
            'charset' => $this->charset,
            'sign_type' => $this->signType,
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => $this->version,
            'biz_content' => json_encode($bizContent, JSON_UNESCAPED_UNICODE),
        ];
        
        // 参数排序
        ksort($params);
        
        // 拼接待签名字符串
        $stringToBeSigned = '';
        foreach ($params as $k => $v) {
            if ($v !== '' && !is_null($v) && $k !== 'sign') {
                $stringToBeSigned .= $k . '=' . $v . '&';
            }
        }
        $stringToBeSigned = rtrim($stringToBeSigned, '&');
        
        // 读取私钥文件
        $privateKey = "-----BEGIN RSA PRIVATE KEY-----\n" .
            wordwrap($this->privateKey, 64, "\n", true) .
            "\n-----END RSA PRIVATE KEY-----";
        
        // 生成签名
        if ($this->signType === 'RSA2') {
            openssl_sign($stringToBeSigned, $sign, $privateKey, OPENSSL_ALGO_SHA256);
        } else {
            openssl_sign($stringToBeSigned, $sign, $privateKey, OPENSSL_ALGO_SHA1);
        }
        
        if (!$sign) {
            throw new Exception('签名生成失败');
        }
        
        return base64_encode($sign);
    }
    
    /**
     * 构建请求参数
     * 
     * @param array $bizContent 业务参数
     * @param string $sign 签名
     * @return array 请求参数
     */
    private function buildRequestParams($bizContent, $sign)
    {
        return [
            'app_id' => $this->appId,
            'method' => 'alipay.trade.page.pay',
            'charset' => $this->charset,
            'sign_type' => $this->signType,
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => $this->version,
            'biz_content' => json_encode($bizContent, JSON_UNESCAPED_UNICODE),
            'sign' => $sign,
            'return_url' => U('Pay/Alipay/return', '', true, true),
            'notify_url' => U('Pay/Alipay/notify', '', true, true),
        ];
    }
    
    /**
     * 异步通知处理
     * 
     * @return void
     */
    public function notify()
    {
        try {
            // 获取支付宝POST过来的通知数据
            $notifyData = $_POST;
            
            if (empty($notifyData)) {
                throw new Exception('通知数据为空');
            }
            
            // 验证签名
            $signVerified = $this->verifySign($notifyData);
            
            if (!$signVerified) {
                throw new Exception('签名验证失败');
            }
            
            // 验证商户ID
            if ($notifyData['app_id'] !== $this->appId) {
                throw new Exception('商户ID不匹配');
            }
            
            // 处理业务逻辑
            $this->processNotify($notifyData);
            
            // 返回成功响应
            echo 'success';
            
        } catch (Exception $e) {
            // 记录错误日志
            \Think\Log::write('支付宝异步通知处理失败：' . $e->getMessage(), 'ERROR');
            
            // 返回失败响应
            echo 'fail';
        }
    }
    
    /**
     * 验证签名
     * 
     * @param array $data 通知数据
     * @return bool 验证结果
     */
    private function verifySign($data)
    {
        $sign = $data['sign'];
        unset($data['sign']);
        unset($data['sign_type']);
        
        // 参数排序
        ksort($data);
        
        // 拼接待验签字符串
        $stringToBeSigned = '';
        foreach ($data as $k => $v) {
            if ($v !== '' && !is_null($v)) {
                $stringToBeSigned .= $k . '=' . $v . '&';
            }
        }
        $stringToBeSigned = rtrim($stringToBeSigned, '&');
        
        // 读取支付宝公钥
        $alipayPublicKey = "-----BEGIN PUBLIC KEY-----\n" .
            wordwrap($this->alipayPublicKey, 64, "\n", true) .
            "\n-----END PUBLIC KEY-----";
        
        // 验证签名
        if ($this->signType === 'RSA2') {
            $result = openssl_verify($stringToBeSigned, base64_decode($sign), $alipayPublicKey, OPENSSL_ALGO_SHA256);
        } else {
            $result = openssl_verify($stringToBeSigned, base64_decode($sign), $alipayPublicKey, OPENSSL_ALGO_SHA1);
        }
        
        return $result === 1;
    }
    
    /**
     * 处理异步通知
     * 
     * @param array $notifyData 通知数据
     * @throws Exception 业务处理失败时抛出异常
     */
    private function processNotify($notifyData)
    {
        // 获取商户订单号
        $outTradeNo = $notifyData['out_trade_no'];
        
        // 获取交易状态
        $tradeStatus = $notifyData['trade_status'];
        
        // 根据交易状态处理业务逻辑
        switch ($tradeStatus) {
            case 'TRADE_SUCCESS':
            case 'TRADE_FINISHED':
                // 支付成功，更新订单状态
                $this->updateOrderStatus($outTradeNo, 1, $notifyData);
                break;
                
            case 'TRADE_CLOSED':
                // 交易关闭，更新订单状态
                $this->updateOrderStatus($outTradeNo, 0, $notifyData);
                break;
                
            default:
                throw new Exception('未知的交易状态：' . $tradeStatus);
        }
    }
    
    /**
     * 更新订单状态
     * 
     * @param string $outTradeNo 商户订单号
     * @param int $status 订单状态
     * @param array $notifyData 通知数据
     * @throws Exception 订单更新失败时抛出异常
     */
    private function updateOrderStatus($outTradeNo, $status, $notifyData)
    {
        // 这里需要根据实际业务逻辑实现订单更新
        // 示例代码：
        // $orderModel = M('Order');
        // $orderInfo = $orderModel->where(['order_sn' => $outTradeNo])->find();
        
        // if (!$orderInfo) {
        //     throw new Exception('订单不存在：' . $outTradeNo);
        // }
        
        // $updateData = [
        //     'status' => $status,
        //     'pay_time' => time(),
        //     'transaction_id' => $notifyData['trade_no'],
        //     'update_time' => time(),
        // ];
        
        // $result = $orderModel->where(['id' => $orderInfo['id']])->save($updateData);
        
        // if (!$result) {
        //     throw new Exception('订单状态更新失败');
        // }
        
        // 记录日志
        \Think\Log::write("订单 {$outTradeNo} 状态更新为：{$status}", 'INFO');
    }
}