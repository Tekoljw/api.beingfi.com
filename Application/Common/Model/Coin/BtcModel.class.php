<?php
namespace Common\Model\Coin;

use Think\Model;
use Think\Log;
use Denpa\Bitcoin\Client as BitcoinClient;

//BTC的model管理类
class BtcModel extends BaseModel
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
        $this->modelClient->getwalletinfo();
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
            //return $this->returnErrorMsg(L('btc getaddressesbylabel ='.json_encode($qianbao_addr)));
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

    //根据字符获取对应的钱包地址
    public function getAddressByLabel($addresslabel)
    {
        if($this->modelClient){ 
            $walletInfo = $this->modelClient->getinfo();
            if(!isset($walletInfo['version']) || !$walletInfo['version']){
                return $this->returnErrorMsg(L('钱包链接失败！'));
            }
            $qianbao_addr = $this->modelClient->getaddressesbylabel($addresslabel);
            if (!is_array($qianbao_addr)) {
                $address = $this->getNewAddress($addresslabel);
                if (!$address) {
                    return $this->returnErrorMsg(L('生成钱包地址出错getnewaddress！'));
                }else{
                    return $this->returnSuccessMsg($address);
                }       
            }else{
                $address = $qianbao_addr['address'];
            }

            if (!$address || preg_match("/[\'.,:;*?~`!@#$%^&+=)(<>{}]|\]|\[|\/|\\\|\"|\|/",$address)) {

                return $this->returnErrorMsg(L('生成钱包地址出错 地址不匹配 address ='.$qianbao_addr));
            }
            return $this->returnSuccessMsg($address);
        }
        return false;
    }

    /**
    * 获取余额
    * @param $addresslabel  账户
    * @return 余额
    */
    public function getBalance($addresslabel){
        if($this->modelClient){
            return $this->modelClient->getbalance($addresslabel, 3);
        }
        return false;
    }

    //转出币
    public function sendToCoin($from_address, $to_address, $num, $official_address, $fee){

        if($to_address && $num > 0){
            //首先检查钱包是否连接成功
            $json = $this->modelClient->getinfo();
            if(!isset($json['version']) || !$json['version']){
                return $this->returnErrorMsg(L('钱包链接失败！'));
            }
            
            if ($json['balance'] <  ($num + $fee)) {
                return $this->returnErrorMsg(L('钱包余额不足'));
            } else {
                $sendrs = $this->modelClient->sendtoaddress($to_address ,(double) $num);
            }
            return $this->returnSuccessMsg($sendrs);
        }elseif(!$to_address){
            return $this->returnErrorMsg(L('转出地址不能为空'));
        }else{
            return $this->returnErrorMsg(L('转出数量不能为0!'));
        }
        return false;
    }

    //获取交易记录
    public function getlistTranSactions($label, $count, $skip){
        if($label && $count && $skip){
            return $this->modelClient->listtransactions($label, $count, $skip);
        }
        return false;
    }
}
?>
