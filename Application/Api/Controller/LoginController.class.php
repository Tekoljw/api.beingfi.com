<?php
namespace Api\Controller;

class LoginController extends BaseController
{
    // 账号密码登录
    public function doLogin ($username = NULL, $password = NULL,  $ga_verify='')
    {
        if (IS_POST) {
                        $username  = isset($this->inputData['username'])  ? $this->inputData['username']  : $username;
                        $password  = isset($this->inputData['password'])  ? $this->inputData['password']  : $password;
                        $ga_verify = isset($this->inputData['ga_verify']) ? $this->inputData['ga_verify'] : $ga_verify;
                        $admin = M('PeAdmin')->where(array('username' => $username))->find();
                        if ($admin['password'] != md5($password)) {
                                $this->errorJson('用户名或密码错误!');
                        } else {
                            if ($admin['status'] == 2) {
                                $this->errorJson('账户已禁用!');
                            }
                                $uids = $admin['id'];
                                // 处理谷歌身份验证器-------------------S
                                // 判断是否需要验证
                                if(!empty($admin['google_key'])){
                                        $ga_n = new \Common\Ext\GoogleAuthenticator();
                                        
                                        // 存储的信息为谷歌密钥
                                        $secret = $admin['google_key'];
                                        if(!$ga_verify){
                                                $this->errorJson(L('请输入Google验证码！'));
                                        }
                                        if(!check($ga_verify,'d')){
                                                $this->errorJson(L('Google验证码格式错误！'));
                                        }
                                        // 判断登录有无验证码
                                        $aa = $ga_n->verifyCode($secret, $ga_verify, 1);
                                        if (!$aa){
                                                $this->errorJson(L('Google身份验证码错误！'));
                                        }
                                }
                                // 处理谷歌身份验证器-------------------E

                $time = time();
                $token = md5($uids . $username . $time . rand(100000, 999999));
                                M('PeAdmin')
                                ->where(['username' => $username])
                                ->save([
                                    'last_login_time' => $time,
                                    'last_login_ip' => get_client_ip(1),
                                    'token' => $token
                            ]);
                                $this->successJson([
                                    'id' => $admin['id'],
                                    'username' => $admin['username'],
                                    'nickname' => $admin['nickname'],
                                    'status' => $admin['status'],
                                    'mobile' => $admin['moble'],
                                    'email' => $admin['email'],
                                    'role' => $admin['role'],
                                    'last_login_time' => $time,
                                    'token' => $token,
                            ]);
                        }
                } else {
                        $this->errorJson('请求拒绝！');
                }
    }
    
    // 钱包登录
    public function doWalletLogin ($walletid = NULL, $code = NULL, $pin = NULL)
    {
        if (IS_POST) {
                        $walletid = isset($this->inputData['walletid'])  ? $this->inputData['walletid']  : (isset($this->inputData['wallet_id']) ? $this->inputData['wallet_id'] : $walletid);
                        $code     = isset($this->inputData['code']) ? $this->inputData['code'] : $code;
                        $pin      = isset($this->inputData['pin'])  ? $this->inputData['pin']  : $pin;
                        $admin = M('PeAdmin')->where(array('wallet_id' => $walletid))->find();
                        if (!$admin) {
                                $this->errorJson('无权限登录!');
                        } else {
                            if ($admin['status'] == 2) {
                                $this->errorJson('账户已禁用!');
                            }
                            
                            $loginStatus = $this->verifyWalletCode($walletid, $code);
                            if ($loginStatus !== true) {
                                $this->errorJson('验证码错误!', ['msg' => $loginStatus]);
                            }
                            
                            $pinStatus = $this->verifyWalletPin($walletid, $pin);
                            if ($pinStatus !== true) {
                                $this->errorJson('PIN码错误!', ['msg' => $pinStatus]);
                            }
                                $uids = $admin['id'];
                $time = time();
                $token = md5($uids . $walletid . $time . rand(100000, 999999));
                                M('PeAdmin')
                                ->where(array('wallet_id' => $walletid))
                                ->save([
                                    'last_login_time' => $time,
                                    'last_login_ip' => get_client_ip(1),
                                    'token' => $token
                            ]);
                                $this->successJson([
                                    'id' => $admin['id'],
                                    'username' => $admin['username'],
                                    'nickname' => $admin['nickname'],
                                    'status' => $admin['status'],
                                    'mobile' => $admin['moble'],
                                    'email' => $admin['email'],
                                    'role' => $admin['role'],
                                    'last_login_time' => $time,
                                    'token' => $token,
                            ]);
                        }
                } else {
                        $this->errorJson('请求拒绝！');
                }
    }
    
    // 商户登录
    public function doMerchartLogin ($walletid = NULL, $code = NULL)
    {
        if (IS_POST) {
                        $walletid = isset($this->inputData['walletid'])   ? $this->inputData['walletid']   : (isset($this->inputData['wallet_id']) ? $this->inputData['wallet_id'] : $walletid);
                        $code     = isset($this->inputData['code']) ? $this->inputData['code'] : $code;
                        $admin = M('PeAdmin')->where(array('wallet_id' => $walletid, 'role' => 5))->find();
                        if (!$admin) {
                                $this->errorJson('无权限登录!');
                        } else {
                            if ($admin['status'] == 2) {
                                $this->errorJson('账户已禁用!');
                            }
                            
                            $loginStatus = $this->verifyWalletCode($walletid, $code);
                            if ($loginStatus !== true) {
                                $this->errorJson('验证码错误!', ['msg' => $loginStatus]);
                            }
                            
                         //   $pinStatus = $this->verifyWalletPin($walletid, $pin);
                         //   if ($pinStatus !== true) {
                         //       $this->errorJson('PIN码错误!', ['msg' => $pinStatus]);
                         //   }
                                $uids = $admin['id'];
                $time = time();
                $token = md5($uids . $walletid . $time . rand(100000, 999999));
                                M('PeAdmin')
                                ->where(array('wallet_id' => $walletid))
                                ->save([
                                    'last_login_time' => $time,
                                    'last_login_ip' => get_client_ip(1),
                                    'token' => $token
                            ]);
                                $this->successJson([
                                    'id' => $admin['id'],
                                    'username' => $admin['username'],
                                    'nickname' => $admin['nickname'],
                                    'status' => $admin['status'],
                                    'mobile' => $admin['moble'],
                                    'email' => $admin['email'],
                                    'role' => $admin['role'],
                                    'last_login_time' => $time,
                                    'token' => $token,
                            ]);
                        }
                } else {
                        $this->errorJson('请求拒绝！');
                }
    }
    
    // 监控程序钱包登录
    public function doMonitorWalletLogin ($walletid = NULL, $code = NULL, $pin = NULL)
    {
        if (IS_POST) {
                        $walletid = isset($this->inputData['walletid'])   ? $this->inputData['walletid']   : (isset($this->inputData['wallet_id']) ? $this->inputData['wallet_id'] : $walletid);
                        $code     = isset($this->inputData['code']) ? $this->inputData['code'] : $code;
                        $pin      = isset($this->inputData['pin'])  ? $this->inputData['pin']  : $pin;
                        $admin = M('PeAdmin')->where(array('wallet_id' => $walletid))->find();
                        if (!$admin) {
                                $this->errorJson('无权限登录!');
                        } else {
                            if ($admin['status'] == 2) {
                                $this->errorJson('账户已禁用!');
                            }
                            
                            $loginStatus = $this->verifyWalletCode($walletid, $code);
                            if ($loginStatus !== true) {
                                $this->errorJson('验证码错误!', ['msg' => $loginStatus]);
                            }
                            
                            $pinStatus = $this->verifyWalletPin($walletid, $pin);
                            if ($pinStatus !== true) {
                                $this->errorJson('PIN码错误!', ['msg' => $pinStatus]);
                            }
                $time = time();
                                M('PeAdmin')
                                ->where(array('wallet_id' => $walletid))
                                ->save([
                                    'last_login_time' => $time,
                                    'last_monitor_login_time' => get_client_ip(1),
                            ]);
                                $this->successJson([
                                    'id' => $admin['id'],
                                    'username' => $admin['username'],
                                    'nickname' => $admin['nickname'],
                                    'wallet_id' => $admin['wallet_id'],
                                    'status' => $admin['status'],
                                    'mobile' => $admin['moble'],
                                    'email' => $admin['email'],
                                    'role' => $admin['role'],
                                    'last_monitor_login_time' => $time
                            ]);
                        }
                } else {
                        $this->errorJson('请求拒绝！');
                }
    }
    
    // 检查是否允许钱包ID登录
    public function verifyOtcWalletid ($walletid = NULL) {
        if (IS_POST) {
                        $walletid = isset($this->inputData['walletid']) ? $this->inputData['walletid'] : (isset($this->inputData['wallet_id']) ? $this->inputData['wallet_id'] : $walletid);
                        $admin = M('PeAdmin')->where(array('wallet_id' => $walletid))->find();
                        if (!$admin) {
                                $this->errorJson('无权限登录!');
                        } else {
                            $this->successJson([], '允许登录!');
                        }
                } else {
                        $this->errorJson('请求拒绝！');
                }
    }
    
    // 钱包自动登录
    public function walletAutoLogin ($walletid = NULL, $pin = NULL)
    {
        if (IS_POST) {
                        $walletid = isset($this->inputData['walletid']) ? $this->inputData['walletid'] : (isset($this->inputData['wallet_id']) ? $this->inputData['wallet_id'] : $walletid);
                        $pin      = isset($this->inputData['pin'])      ? $this->inputData['pin']      : $pin;
                        $admin = M('PeAdmin')->where(array('wallet_id' => $walletid))->find();
                        if (!$admin) {
                                $this->errorJson('无权限登录!');
                        } else {
                            if ($admin['status'] == 2) {
                                $this->errorJson('账户已禁用!');
                            }
                            
                            $loginStatus = $this->verifyWalletid($walletid);
                            if ($loginStatus !== true) {
                                $this->errorJson('用户不存在!', ['msg' => $loginStatus]);
                            }
                            
                            $pinStatus = $this->verifyWalletPin($walletid, $pin);
                            if ($pinStatus !== true) {
                                $this->errorJson('PIN码错误,请重新输入PIN码!', ['msg' => $pinStatus]);
                            }
                            
                                $uids = $admin['id'];
                $time = time();
                $token = md5($uids . $walletid . $time . rand(100000, 999999));
                                M('PeAdmin')
                                ->where(array('wallet_id' => $walletid))
                                ->save([
                                    'last_login_time' => $time,
                                    'last_login_ip' => get_client_ip(1),
                                    'token' => $token
                            ]);
                                $this->successJson([
                                    'id' => $admin['id'],
                                    'username' => $admin['username'],
                                    'nickname' => $admin['nickname'],
                                    'status' => $admin['status'],
                                    'mobile' => $admin['moble'],
                                    'email' => $admin['email'],
                                    'role' => $admin['role'],
                                    'last_login_time' => $time,
                                    'token' => $token,
                            ]);
                        }
                } else {
                        $this->errorJson('请求拒绝！');
                }
    }
    
    // 验证钱包id（BXB bot）
    public function verifyWalletid($walletid, $code = null)
    {
        $resultInfo = 'id request error';
        $sendHeader = ['Content-Type: application/json', 'x-internal-secret: bxb-internal-secret-2024'];
        $sendData = json_encode(['walletId' => $walletid]);
        $verifyResult = httpRequestData($this->verifyWalltidUrl, $sendData, $sendHeader, 'POST');
        if ($verifyResult) {
            $verifyResultArr = json_decode($verifyResult, true);
            if ($verifyResultArr['valid'] == true) {
                $resultInfo = true;
            } else {
                $resultInfo = 'wallet not found';
            }
        }
        return $resultInfo;
    }
    
    // 验证钱包Pin码（BXB bot）
    public function verifyWalletPin($walletid, $pin)
    {
        $resultInfo = 'pin request error';
        $sendHeader = ['Content-Type: application/json', 'x-internal-secret: bxb-internal-secret-2024'];
        $validateResult = httpRequestData('http://127.0.0.1:8080/api/wallet/user/validate', json_encode(['walletId' => $walletid]), $sendHeader, 'POST');
        if (!$validateResult) return $resultInfo;
        $validateArr = json_decode($validateResult, true);
        if (!$validateArr['valid']) return 'wallet not found';
        $telegramId = $validateArr['telegramId'];

        $sendData = json_encode(['telegramId' => $telegramId, 'pin' => $pin]);
        $result = httpRequestData($this->verifyWalltPinUrl, $sendData, $sendHeader, 'POST');
        if ($result) {
            $resultArr = json_decode($result, true);
            if ($resultArr['success'] == true) {
                $resultInfo = true;
            } else {
                $resultInfo = 'pin error';
            }
        }
        return $resultInfo;
    }
    
    // 验证钱包code（BXB bot）
    public function verifyWalletCode($walletid, $code)
    {
        $resultInfo = 'code request error';
        $sendHeader = ['Content-Type: application/json', 'x-internal-secret: bxb-internal-secret-2024'];
        $validateResult = httpRequestData('http://127.0.0.1:8080/api/wallet/user/validate', json_encode(['walletId' => $walletid]), $sendHeader, 'POST');
        if (!$validateResult) return $resultInfo;
        $validateArr = json_decode($validateResult, true);
        if (!$validateArr['valid']) return 'wallet not found';
        $telegramId = $validateArr['telegramId'];

        $sendData = json_encode(['telegramId' => $telegramId, 'code' => $code, 'scene' => 'login']);
        $result = httpRequestData($this->verifyWalltCodeUrl, $sendData, $sendHeader, 'POST');
        if ($result) {
            $resultArr = json_decode($result, true);
            if ($resultArr['success'] == true) {
                $resultInfo = true;
            } else {
                $resultInfo = 'code error';
            }
        }
        return $resultInfo;
    }
        
        public function loginout ()
        {
           // if (empty($this->user)) {
           //     $this->errorJson('未知用户！');
           // }
            
            M('PeAdmin')->save([
                'id' => $this->user['id'],
                'token' => ''
        ]);
            
            $this->successJson();
        }

}
?>