<?php
namespace Common\Service;

class SyncSendMsg {
    private $bxbApiBase = 'http://127.0.0.1:8080/api/wallet';
    private $bxbSecret  = 'bxb-internal-secret-2024';

    public function __construct() {}

    // 发送tg消息（BXB bot）
    public function sendTgMsg($telegramId, $msgid, $params = [])
    {
        $text = $msgid;
        if (!empty($params)) {
            $text .= "\n" . (is_array($params) ? implode("\n", $params) : $params);
        }
        $sendHeader = [
            'Content-Type: application/json',
            'x-internal-secret: ' . $this->bxbSecret,
        ];
        $sendData = json_encode([
            'telegramId' => $telegramId,
            'text'       => $text,
            'parseMode'  => 'Markdown',
        ]);
        $result = httpRequestData($this->bxbApiBase . '/message/send', $sendData, $sendHeader, 'POST');
        if ($result) {
            $arr = json_decode($result, true);
            return isset($arr['success']) && $arr['success'];
        }
        return false;
    }
}
