<?php
/**
 * 云片
 */

namespace Org\Util\SmsYunpian;

class SmsYunpian
{
    // 返回状态
    protected $statusStr = array(
        "0"  => "短信发送成功",
        "1" => "请求参数缺失",
        "2" => "请求参数格式错误",
        "3" => "账户余额不足",
        "4" => "关键词屏蔽",
        "5" => "未自动匹配到合适的模板",
        "6" => "添加模板失败",
        "7" => "模板不可用",
        "8" => "同一手机号30秒内重复提交相同的内容",
        "9" => "同一手机号5分钟内重复提交相同的内容超过3次",
        "10" => "手机号防骚扰名单过滤",
        "11" => "接口不支持GET方式调用",
        "12" => "接口不支持POST方式调用",
        "13" => "营销短信暂停发送",
        "14" => "解码失败",
        "15" => "签名不匹配",
        "16" => "签名格式不正确",
        "17" => "24小时内同一手机号发送次数超过限制",
        "18" => "签名校验失败",
        "19" => "请求已失效",
        "20" => "不支持的国家地区",
        "21" => "解密失败",
        "22" => "1小时内同一手机号发送次数超过限制",
        "23" => "发往模板支持的国家列表之外的地区",
        "24" => "添加告警设置失败",
        "25" => "手机号和内容个数不匹配",
        "26" => "流量包错误",
        "27" => "未开通金额计费",
        "28" => "运营商错误",
        "33" => "超过频率",
        "34" => "签名创建失败",
        "40" => "未开启白名单",
        "43" => "一天内同一手机号发送次数超过限制",
        "48" => "参数长度超过限制",
        "52" => "关键词屏蔽",
        "-1" => "非法的apikey",
        "-2" => "API没有权限",
        "-3" => "IP没有权限",
        "-4" => "访问次数超限",
        "-5" => "访问频率超限",
        "-7" => "HTTP请求错误",
        "-8" => "不支持流量业务",
        "-50" => "未知异常",
        "-51" => "系统繁忙",
        "-52" => "充值失败",
        "-53" => "提交短信失败",
        "-54" => "记录已存在",
        "-55" => "记录不存在",
        "-57" => "用户开通过固定签名功能，但签名未设置",
	);

    public $sms_gateway = 'https://sms.yunpian.com/v2/sms/single_send.json'; //国内短信网关
    public $wsms_gateway = 'https://us.yunpian.com/v2/sms/single_send.json'; //国际短信网关
    public $Code        = '';
    public $Message     = '';
    public $apikey      = '';

    /**
     * 初始化
     * @param  string $apikey    短信平台apikey
     */
    public function __construct($apikey)
    {
        //file_put_contents('yunpian_error_log.txt', "\n Smsyun_pian apikey =".$apikey,FILE_APPEND);
        $this->apikey = $apikey;
    }

    //发送post请求
    public function httpPost($url, $params){

        $headers = array(
                'Accept:application/json;charset=utf-8',
                'Content-Type:application/x-www-form-urlencoded;charset=utf-8'
            );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        $contents = curl_exec($ch);
        $httpCode = curl_getinfo($ch,CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $contents;
    }

    /**
     * 发送国内短信
     * @param  string $phone    手机号码
     * @param  string $tplId    发送的模板ID
     * @param  string $content  发送的模板内参数
     * @return string           发送状态
     */
    public function sendSmsWithContent($mobile, $content){

        $param = [
                'apikey' => $this->apikey,
                'mobile' => $mobile,
                'text'   => $content,
                ];

        $sendParams = http_build_query($param);
        $result = $this->httpPost($this->sms_gateway, $sendParams);
        //var_dump($result);
        $resultJson = json_decode($result, true);
        $obj          = new \stdClass();
        $obj->Code    = $resultJson['code'];
        $obj->Message = $resultJson['msg'];
        return $obj;
    }

    /**
     * 发送国内短信
     * @param  string $phone    手机号码
     * @param  string $content  发送的内容
     * @return string           发送状态
     */
    public function sendSmsTplId($mobile, $tplId, $sendData){

        foreach ($sendData as $key => $value) {
            if($value['type'] == 1){
                $tpl_value = ('#'.$key.'#') .'='. urldecode($value['content']) . '&';
            }else{
                $tpl_value = ('#'.$key.'#') .'='. $value['content'] . '&';
            }
        }

        $tpl_value = substr($tpl_value, 0, strlen($tpl_value)-1);
        $param = [
                'apikey' => $this->apikey,
                'mobile' => $mobile,
                'tpl_id' => $tplId,
                'tpl_value' =>('#code#').'=1538',
                ];

        $sendParams = http_build_query($param);
        $result = $this->httpPost($this->sms_gateway, $sendParams);
        $resultJson = json_decode($result, true);
        $obj          = new \stdClass();
        $obj->Code    = $resultJson['code'];
        $obj->Message = $resultJson['msg'];

        
        return $obj;
    }

     /**
     * 发送国际短信
     * @param  string $phone    手机号码
     * @param  string $content  发送的内容
     * @return string           发送状态
     */
    public function sendWSmsWithContent($mobile, $content){

        $param = [
                'apikey' => $this->apikey,
                'mobile' => $mobile,
                'text'   => $content,
                ];

        $sendParams = http_build_query($param);
        $result = $this->httpPost($this->sms_gateway, $sendParams);
        $resultJson = json_decode($result, true);
        $obj          = new \stdClass();
        $obj->Code    = $resultJson['code'];
        $obj->Message = $resultJson['msg'];
        
        return $obj;
    }

    /**
     * 发送国际短信
     * @param  string $phone    手机号码
     * @param  string $tplId    发送的模板ID
     * @param  string $content  发送的模板内参数
     * @return string           发送状态
     */
    public function sendWSmsTplId($mobile, $tplId, $sendData){

        foreach ($sendData as $key => $value) {
            if($value['type'] == 1){
                $tpl_value = ('#'.$key.'#') .'='. urldecode($value['content']) . '&';
            }else{
                $tpl_value = ('#'.$key.'#') .'='. $value['content'] . '&';
            }
        }
        $tpl_value = substr($tpl_value, 0, strlen($tpl_value)-1);

        $param = [
                'apikey' => $this->apikey,
                'mobile' => $mobile,
                'tpl_id' => $tplId,
                'tpl_value' =>$tpl_value,
                ];

        $sendParams = http_build_query($param);
        $result = $this->httpPost($this->sms_gateway, $sendParams);
        $resultJson = json_decode($result, true);
        $obj          = new \stdClass();
        $obj->Code    = $resultJson['code'];
        $obj->Message = $resultJson['msg'];
        
        return $obj;
    }
}
