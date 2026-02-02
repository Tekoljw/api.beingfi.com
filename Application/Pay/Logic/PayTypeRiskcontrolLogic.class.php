<?php

namespace Pay\Logic;

//渠道风控类
class PayTypeRiskcontrolLogic extends RiskcontrolLogic
{

    protected $autoCheckFields = false;

    protected $m_PayType;

    public function __construct($pay_amount = 0.00)
    {
        parent::__construct();
        $this->m_PayType  = M('paytype_config');
        $this->pay_amount = $pay_amount;
    }

    //总充值额度限制判断
    public function totalVolumeLimitJudge(){

        $the_total_volume_judge = $this->theTotalVolume(function (){});
        if($the_total_volume_judge === true){
            return false;
        }else{
            return true;
        }
    }

    //监测数据
    public function monitoringData()
    {
        if(!empty($this->config_info)){
            if ($this->config_info['control_status'] == 1) {
                if($this->config_info['offline_status'] == 0){
                    return '已下线';
                }

                //基本风控规则
                $base_judge = parent::monitoringData();
                if ($base_judge !== true) {
                    return $base_judge;
                }

                //判断交易总量
                $the_total_volume_judge = $this->theTotalVolume(function () {

                    //如果是新一天，渠道交易量清零,防止定时任务不执行
                    $where = ['id' => $this->config_info['id']];
                    $data  = ['paying_money' => 0.00, 'offline_status' => 1];
                    $res   = $this->m_PayType->where($where)->save($data);

                });
                if ($the_total_volume_judge !== true) {
                    return $the_total_volume_judge;
                }
            }
        }
        return true;
    }

    //更新最后交易时间
    protected function updateLastPayTime(){

        if(!empty($this->config_info) && isset($this->config_info['id'])){
            
            $where = ['id' => $this->config_info['id']];
            $data  = ['last_paying_time'=>time()];
            return $this->m_PayType->where($where)->save($data);
        }
        return true;
    }

}
