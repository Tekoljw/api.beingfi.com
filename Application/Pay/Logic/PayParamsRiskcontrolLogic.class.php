<?php

namespace Pay\Logic;

//支付参数风控类
class PayParamsRiskcontrolLogic extends RiskcontrolLogic
{

    protected $autoCheckFields = false;

    protected $m_PayParams;

    public function __construct($pay_amount = 0.00)
    {
        parent::__construct();
        $this->m_PayParams = M('payparams_list');
        $this->pay_amount  = $pay_amount;
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

    //设置config_info配置属性
    public function setConfigInfo($config_info = [])
    {
        parent::setConfigInfo($config_info);

        if(isset($this->config_info['params_id']) && !isset($this->config_info['is_defined'])){
            $where = ['id' => $this->config_info['params_id']];
            $temp_config = $this->m_PayParams->where($where)->find();
            $this->config_info['control_status']    = $temp_config['control_status'];
            $this->config_info['offline_status']    = $temp_config['offline_status'];
            $this->config_info['last_paying_time']  = $temp_config['last_paying_time'];
            $this->config_info['paying_money']      = $temp_config['paying_money'];
            $this->config_info['paying_num']        = $temp_config['paying_num'];
        }
    }

    //监测数据
    public function monitoringData(){

        if (!empty($this->config_info)) {

            if ($this->config_info['control_status'] == 1) {
                if($this->config_info['offline_status'] == 0){
                    return 'OTC-PayParamsControl已下线';
                }

                $base_judge = parent::monitoringData();
                if ($base_judge !== true) {
                    return $base_judge;
                }

                //判断交易总量
                $the_total_volume_judge = $this->theTotalVolume(function () {
                    $paying_money   = $this->config_info['paying_money'];
                    $offline_status = $this->config_info['offline_status'];
                    $paying_num = $this->config_info['paying_num'];
                    if($paying_money != 0 || $offline_status != 1 || $paying_num != 0){
                        //如果是新一天，渠道交易量清零,防止定时任务不执行
                        $where = ['id' => $this->config_info['params_id']];
                        $data  = ['paying_money'=>0.00, 'paying_num'=>0, 'offline_status'=>1];
                        $res   = $this->m_PayParams->where($where)->save($data);
                    }
                });
                if ($the_total_volume_judge !== true) {
                    return $the_total_volume_judge;
                }

                //总交易次数
                $the_total_num_judge = $this->theTotalPayNum();
                if ($the_total_num_judge !== true) {
                    return $the_total_num_judge;
                }
            }

            //5分钟内不出现相同金额的判断
            // $the_same_amount_judge = $this->theSameAmountJudge();
            // if ($the_same_amount_judge !== true) {
            //     return $the_same_amount_judge;
            // }
        }

        return true;
    }

     //总的支付次数(需要在总量之后判断，总量控制时间重置)
    protected function theTotalPayNum(){
        if (isset($this->config_info['all_pay_num']) && $this->config_info['all_pay_num'] != 0) {
            $paying_num = $this->config_info['paying_num'] + 1;
            if($this->config_info['all_pay_num'] < $paying_num){
                return 'OTC-当天总交易次数已满!';
            }
        }
        return true;
    }

    //更新最后交易时间
    protected function updateLastPayTime(){
        if(!empty($this->config_info) && isset($this->config_info['params_id'])){

            $where = ['id' => $this->config_info['params_id']];
            $data  = ['last_paying_time'=>time()];
            return $this->m_PayParams->where($where)->save($data);
        }
        return true;
    }

    //每5分钟内不能出现相同的金额
    protected function theSameAmountJudge(){
        if(!empty($this->config_info) && isset($this->config_info['pay_amount_list']) && $this->config_info['pay_amount_list'] != 'null'){
            $minute_index = intval(date('i') / 5);
            $pay_amount_list = json_decode($this->config_info['pay_amount_list'], true);
            if(empty($pay_amount_list)){
                $where = ['id' => $this->config_info['params_id']];
                $data  = ['pay_amount_list'=>'null'];
                $res = $this->m_PayParams->where($where)->save($data);
                return $res?true:'OTC-判断金额重复支付的逻辑出错1';
            }else{
                $index = intval($pay_amount_list[0]);
                if($minute_index != $index){
                    $where = ['id' => $this->config_info['params_id']];
                    $data  = ['pay_amount_list'=>'null'];
                    $res = $this->m_PayParams->where($where)->save($data);
                    return $res?true:'OTC-判断金额重复支付的逻辑出错2';
                }else{
                    $count = count($pay_amount_list);
                    for ($i=1; $i < $count; $i++) { 
                        if($pay_amount_list[$i] == $this->pay_amount){
                            return 'OTC-该金额在5分钟内已经支付过! paid this amount';
                        }
                    }
                }
            }
        }
        return true;
    }
}
