<?php
namespace Common\Model\Coin;

use Think\Model;

class BaseModel extends Model
{
	protected $modelClient = null;
    /**
     * 架构函数
     * 取得DB类的实例对象 字段检查
     */
    public function __construct($coinInfo){

        //币的链接属性配置
        $dj_username = $coinInfo['dj_yh'];
        $dj_password = $coinInfo['dj_mm'];
        $dj_address = $coinInfo['dj_zj'];
        $dj_port = $coinInfo['dj_dk'];

        switch ($coinInfo['name']) {
            case 'eth':
                $this->modelClient = EthCommon($dj_address, $dj_port);
                break;
            default:
                $this->modelClient = CoinClient($dj_username, $dj_password, $dj_address, $dj_port, 5, array(), 1);
                break;
        }
    }

    //返回错误信息
    public function returnErrorMsg($msg){
        return ['status'=>0, 'msg'=>$msg];
    }

    //返回正常信息
    public function returnSuccessMsg($msg){
        return ['status'=>1, 'msg'=>$msg];
    }
}
?>
