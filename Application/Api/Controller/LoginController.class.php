<?php
namespace Api\Controller;

class LoginController extends BaseController
{
    // 账号密码登录
    public function doLogin ($username = NULL, $password = NULL,  $ga_verify='')
    {
        if (IS_POST) {
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
    
    // 验证钱包id
    public function verifyWalletid($walletid, $code)
    {
        $resultInfo = 'id request error';
        $sendHeader = ['Content-Type: multipart/form-data;'];
        $sendVerifyWalletData = ['appid' => $this->appid, 'id' => $walletid];
        $verifyResult = httpRequestData($this->verifyWalltidUrl, $sendVerifyWalletData, $sendHeader, 'POST');
        if ($verifyResult) {
            $verifyResultArr = json_decode($verifyResult, true);
            if ($verifyResultArr['code'] == 1) {
                $resultInfo = true;
            } else {
                $resultInfo = $verifyResultArr['msg'];
            }
        }
        
        return $resultInfo;
    }
    
    // 验证钱包Pin码
    public function verifyWalletPin($walletid, $pin)
    {
        $resultInfo = 'code request error';
        $sendHeader = ['Content-Type: multipart/form-data;'];
        $sendData = ['appid' => $this->appid, 'id' => $walletid, 'pin' => $pin];
        $result = httpRequestData($this->verifyWalltPinUrl, $sendData, $sendHeader, 'POST');
        if ($result) {
            $resultArr = json_decode($result, true);
            if ($resultArr['code'] == 1) {
                $resultInfo = true;
            } else {
                $resultInfo = $resultArr['msg'];
            }
        }
        
        return $resultInfo;
    }
    
    // 验证钱包code
    public function verifyWalletCode($walletid, $code)
    {
        $resultInfo = 'code request error';
        $sendHeader = ['Content-Type: multipart/form-data;'];
        $verifyResult = $this->verifyWalletid($walletid);
        if ($verifyResult === true) {
            $sendData = ['appid' => $this->appid, 'id' => $walletid, 'code' => $code];
            $result = httpRequestData($this->verifyWalltCodeUrl, $sendData, $sendHeader, 'POST');
            if ($result) {
                $resultArr = json_decode($result, true);
                if ($resultArr['code'] == 1) {
                    $resultInfo = true;
                } else {
                    $resultInfo = $resultArr['msg'];
                }
            }
        } else {
            $resultInfo = $verifyResult;
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