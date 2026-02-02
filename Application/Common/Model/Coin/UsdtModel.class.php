<?php
namespace Common\Model\Coin;

use Think\Model;
use Think\Log;
use Denpa\Bitcoin\Client as BitcoinClient;

//usdt的model管理类
class UsdtModel extends BaseModel
{
    /**
     * 架构函数
     * 取得DB类的实例对象 字段检查
     */
    public function __construct($coinInfo) {
        parent::__construct($coinInfo);
    }

    //获取钱包信息
    public function getInfo(){
        $this->modelClient->getinfo();
    }

    //获取新的地址
    public function getNewAddress($addresslabel)
    {
        if($addresslabel){

            $walletInfo = $this->modelClient->getinfo();
            if(!isset($walletInfo['version']) || !$walletInfo['version']){
                return $this->returnErrorMsg(L('钱包链接失败！'));
            }
            $qianbao_addr = $this->modelClient->getnewaddress($addresslabel);
            return $this->returnSuccessMsg($qianbao_addr);
        }else{
            return $this->returnErrorMsg(L('没有addresslabel参数！'));
        }
    }

     //根据字符获取对应的钱包地址(old获取方式)
    public function getOldAddressByAccount($addresslabel)
    {
        if($this->modelClient){ 
            $walletInfo = $this->modelClient->getinfo();
            if(!isset($walletInfo['version']) || !$walletInfo['version']){
                return $this->returnErrorMsg(L('钱包链接失败！'));
            }

            $qianbao_addr = $this->modelClient->getaddressesbyaccount($addresslabel);
            if (!is_array($qianbao_addr)) {
                $address = $this->modelClient->getnewaddress($addresslabel);
                if (!$address) {
                    return $this->returnErrorMsg(L('生成钱包地址出错getnewaddress！'));
                } else {
                    return $this->returnSuccessMsg($address);
                }
            } else {
                $address = $qianbao_addr[0];
            }

            if (!$address || preg_match("/[\'.,:;*?~`!@#$%^&+=)(<>{}]|\]|\[|\/|\\\|\"|\|/",$address)) {

                return $this->returnErrorMsg(L('生成钱包地址出错 地址不匹配 address ='.$qianbao_addr));
            }
            return $this->returnSuccessMsg($address);
        }
        return false;
    }

    //获取usdt的钱包地址
    public function getAddressByLabel($addresslabel)
    {
        if($addresslabel){
            $walletInfo = $this->modelClient->getinfo();
            if(!isset($walletInfo['version']) || !$walletInfo['version']){
                return $this->returnErrorMsg(L('钱包链接失败！'));
            }
            $qianbao_addr = $this->modelClient->getaddressesbylabel($addresslabel);
            //return $this->returnErrorMsg(L('usdt getaddressesbylabel ='.json_encode($qianbao_addr)));
            if (!is_array($qianbao_addr)) {
                $address = $this->modelClient->getnewaddress($addresslabel);
                if (!$address) {
                    return $this->returnErrorMsg(L('生成钱包地址出错getnewaddress！'));
                }else{
                    return $this->returnSuccessMsg($address);
                }       
            }else{
                $address = $qianbao_addr['address'];
            }

            if (!$address || preg_match("/[\'.,:;*?~`!@#$%^&+=)(<>{}]|\]|\[|\/|\\\|\"|\|/",$address)) {
                
                return $this->returnErrorMsg(L('生成钱包地址出错 地址不配置 address ='.$qianbao_addr));
            }
            return $this->returnSuccessMsg($address);
        }
        return $this->returnErrorMsg(L('没有链接钱包！'));
    }

    /**
    * 获取余额
    * @param $addresslabel  钱包地址
    * @return 余额
    */
    public function getBalance($addresslabel){
        if($this->modelClient){
            $balance_info = $this->modelClient->omni_getbalance($addresslabel, 31);
            $balance = $balance_info['balance'];
            return $balance;
        }
        return false;
    }

    //转出币
    public function sendToCoin($from_address, $to_address, $num, $official_address, $fee)
    {
        if($to_address && $num > 0){
            //首先检查钱包是否连接成功
            $walletInfo = $this->modelClient->getinfo();
            if(!isset($walletInfo['version']) || !$walletInfo['version']){
                return $this->returnErrorMsg(L('钱包链接失败！'));
            }
            //转出 USDT
            $json = $this->modelClient->omni_getbalance($from_address,31);
            $mycoin_balance = $json['balance'];
            if ($mycoin_balance < ($num + $fee)) {
                
                return $this->returnErrorMsg(L('钱包余额不足 = '.($num+$fee)));
                
            }else{

                $sendrs = $this->modelClient->omni_send($from_address , $to_address,31 ,$num);

                $official_sendrs = $this->modelClient->omni_send($from_address , $official_address, 31, $fee);
                $official_sendrs_arr = json_decode($official_sendrs, true);
                if (isset($official_sendrs_arr['status']) && ($official_sendrs_arr['status'] == 0)) {
                    Log::record("USDT 从用户钱包转入官方钱包：".$official_address.' num = '.$fee .' 失败', Log::INFO);
                }
            } 
            return $this->returnSuccessMsg($sendrs);
        }elseif(!$to_address){

            return $this->returnErrorMsg(L('转出地址不能为空'));
        }else{
            return $this->returnErrorMsg(L('转出数量不能为0'));
        }
    }

    //获取交易记录列表
    public function getlistTranSactions($label, $count, $skip){
        if($label && $count && $skip){
            return $this->modelClient->listtransactions($label, $count, $skip);
        }
         return $this->returnErrorMsg(L('获取交易记录的参数缺失'));
    }
}
?>
