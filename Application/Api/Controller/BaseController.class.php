<?php

namespace Api\Controller;

use \think\Controller;
use Think\Log;

/**
 * @author guotian
 * @date   2018-06-06
 */
class BaseController extends Controller
{
    protected $inputData = [];
    protected $user = [];
    
    protected $langConfig = "/www/wwwroot/test-otc.beingfi.com/Public/comfile/json/lang.json";
    protected $adminid = 1;
    
    protected $appid = '647f374d41b6e0bf81055cc6';
    protected $verifyWalltidUrl = "https://test-api.funihash.com/fingernft/mobile/wallet/validate";
    protected $verifyWalltPinUrl = "https://test-api.funihash.com/fingernft/mobile/wallet/verifyPin";
    protected $verifyWalltCodeUrl = "https://test-api.funihash.com/fingernft/mobile/wallet/verifyCode";
    protected $getWalletInfoUrl = "https://test-api.funihash.com/fingernft/mobile/wallet/info";
    protected $walletTransferUrl = "https://test-api.funihash.com/fingernft/mobile/wallet/internalTransfer";
    
    
    protected $bepayKey = "y7UoTFJpCEEwB9T8smtpi50W1sZLq9UC";
    protected $syncProvidersUrl = "https://test-api.bepay.one/api/otc/providers";
    protected $syncEditProviderDataUrl = "https://test-api.bepay.one/api/otc/providers/updateInterface";
    
    /**
     * 初始化控制器
     * BaseController constructor.
     */
    public function __construct()
    {
        if ($_POST || $_GET) {
            $inputData = array_merge($_POST, $_GET);
        } else {
            $inputData = [];
        }
        
        $inputString = file_get_contents('php://input');
        if (json_decode($inputString, true)) {
            $inputStringData = json_decode($inputString, true);
        } else {
            $inputStringData = parse_str($inputString);
        }
        
        if (!$inputStringData) {
            $inputStringData = [];
        }
        
        $this->inputData = array_merge($inputData, $inputStringData);
        $currentAction = CONTROLLER_NAME. '/' . ACTION_NAME;
        
        if ($currentAction != 'Login/doLogin' && 
            $currentAction != 'Login/loginOut' && 
            $currentAction != 'Login/doWalletLogin' && 
            $currentAction != 'Login/doMerchartLogin' && 
            $currentAction != 'Login/walletAutoLogin' && 
            $currentAction != 'Login/verifyOtcWalletid' && 
            $currentAction != 'Login/doMonitorWalletLogin' && 
            $currentAction != 'Index/saveLangConfig' && 
            $currentAction != 'Index/getLangConfig' && 
            $currentAction != 'Index/testAdbInfo'
        ) {
            if ($this->inputData['token']) {
                $this->user = M('PeAdmin')
    				->where(['token' => $this->inputData['token']])
    				->find();
				if (!$this->user) {
				    $this->errorJson('未知用户');
				}
            } else {
                $this->errorJson('未知用户');
            }
        }
        
        parent::__construct();
        
    }
    
    public function successJson ($data=[], $msg='success') {
        $this->ajaxReturn([
            'code' => 0,
            'msg' => $msg,
            'data' => $data
        ]);
    }
    
    public function errorJson ($msg='error', $data=[]) {
        $this->ajaxReturn([
            'code' => -1,
            'msg' => $msg,
            'data' => $data
        ]);
    }
    
    /**
     * 创建签名
     * @param $Md5key
     * @param $list
     * @return string
     */
    protected function createSign($Md5key, $list)
    {
        ksort($list);
        reset($list); //内部指针指向数组中的第一个元素
        $md5str = "";
        foreach ($list as $key => $val) {
            if (($val !== '' && $val !== []) && $key != 'sign') {
                if(is_array($val)){
                    $md5str = $md5str . $key . "=" . json_encode($val) . "&";
                }else{
                    $md5str = $md5str . $key . "=" . $val . "&";
                }
            }
        }
        $md5str = $md5str . "key=" . $Md5key;
        // var_dump($md5str);exit;
        $sign = strtoupper(md5($md5str));
        return $sign;
    }
    
    /**
     * 递归查询所有下级
     * @param int $f_id 上级ID
     * @param array &$result 结果数组
     * @return array
     */
    protected function getAllSubordinates($f_id, &$result = []) {
        // 查询直接下级
        $directSubs = M('PeAdmin')
            ->where(['fid' => $f_id, 'is_merchart' => 1])
            ->field('id,userid,fid')
            ->select();
            
        if ($directSubs) {
            foreach ($directSubs as $sub) {
                $result[] = $sub;
                // 递归查询下级的下级
                $this->getAllSubordinates($sub['id'], $result);
            }
        }
        
        return $result;
    }
}