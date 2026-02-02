<?php
namespace Common\Service;

class SyncSendMsg {
    private $appid = '647f374d41b6e0bf81055cc6';
    
    private $sendMsgUrl = "https://test-api.funihash.com/fingernft/mobile/wallet/sendMessage";
    
    public function __construct() {
    }
    
	// 发送tg消息
	public function sendTgMsg($walletid, $msgid, $params = [])
	{
        $sendHeader = ['Content-Type: application/x-www-form-urlencoded'];
        $sendVerifyWalletData = http_build_query(['appid' => $this->appid, 'id' => $walletid, 'msgid' => $msgid, 'paramsList' => $params]);
        $verifyResult = httpRequestData($this->sendMsgUrl, $sendVerifyWalletData, $sendHeader, 'POST');
        if ($verifyResult) {
            $verifyResultArr = json_decode($verifyResult, true);
            if ($verifyResultArr['code'] == 1) {
                return true;
            }
        }
        
        return false;
	}
	
}