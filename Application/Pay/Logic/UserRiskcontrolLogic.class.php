<?php

namespace Pay\Logic;

class UserRiskcontrolLogic extends RiskcontrolLogic
{
    protected $m_User;
    protected $channelid;

    public function __construct($pay_amount = '0.00', $channelid = NULL)
    {
        parent::__construct();

        /********************查询用户信息***********************/
        $this->pay_amount   = $pay_amount;       //交易金额
        $this->channelid    = $channelid;
        $this->m_User       = M('user');
    }

    //设置config_info配置属性
    public function setConfigInfo($config_info = [])
    {
        parent::setConfigInfo($config_info);

        if(!empty($this->config_info) && isset($this->config_info['last_exchange_time'])){
            $this->config_info['last_paying_time'] = $this->config_info['last_exchange_time'];
        }
    }

    //监测数据
    public function monitoringData()
    {
        if (!empty($this->config_info)) {
            //---------------------基本风控规则-----------------
            // $base_judge = parent::monitoringData();
            // if ($base_judge !== true) {
            //     return $base_judge;
            // }

            //--------------------判断交易总量-----------------
            $the_total_volume_judge = $this->theTotalVolume(function () {

                if($this->config_info['paying_money'] != 0){
                    //如果是新一天，交易量清零,防止定时任务不执行
                    $where = ['id' => $this->config_info['id']];
                    $data  = ['paying_money' => 0.00];
                    $res   = $this->m_User->where($where)->save($data);
                }
            });
            if ($the_total_volume_judge !== true) {
                return $the_total_volume_judge;
            }

            //--------------------判断通道是否开通-------------
            $the_channel_open_judge = $this->checkChannelOpen();
            if ($the_channel_open_judge !== true) {
                return $the_channel_open_judge;
            }
        }
        return true;
    }

    //是否开通这个通道
    protected function checkChannelOpen(){
        if(!empty($this->config_info) && $this->channelid && isset($this->config_info['select_channelid'])) {

            $select_channelid = $this->config_info['select_channelid'];
            $channelid_list = array();
            if(!empty($select_channelid)){
                $channelid_list = explode(',', $select_channelid);
            }
            if(!in_array($this->channelid, $channelid_list)){
                return 'OTC-用户该通道没有开通,not open channel,channelid='.$this->channelid.",userid=".$this->config_info['id'];
            }
        }
        return true;
    }
}
