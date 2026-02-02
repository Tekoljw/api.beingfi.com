<?php
// +----------------------------------------------------------------------
// | 支付模型
// +----------------------------------------------------------------------
namespace Common\Model;

use Think\Log;

//数据库表的管理类
class DbTableModel{

    static protected $m_CreatedTables=array();         //已经创建的表名列表

    static protected $m_NoExistTables=array();         //不存在的表
    
    /**
     * 创建Exchangeorder表
     * @access public
     * @param string $TabelName 表名
     */
    static function createExchangeOrderTable($tableName){
        if($tableName){

            self::addCreatedTableName($tableName);

            $tableName = C('DB_PREFIX') . $tableName;
            $createSql = "
            DROP TABLE IF EXISTS `{$tableName}`;
            CREATE TABLE `{$tableName}`  (
              `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
              `otype` int(4) UNSIGNED NOT NULL DEFAULT 0 COMMENT '订单类型（0未知、1充值、2提现）',
              `orderid` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '订单号',
              `out_order_id` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '' COMMENT '外部订单号',
              `remarks` varchar(30) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '汇款备注',
              `userid` int(11) UNSIGNED NOT NULL COMMENT '用户id',
              `uprice` decimal(11, 3) NOT NULL COMMENT '单价',
              `num` decimal(11, 3) UNSIGNED NOT NULL DEFAULT 0.000 COMMENT '充提数量',
              `mum` decimal(11, 2) NOT NULL COMMENT '到账金额',
              `real_amount` decimal(11, 2) NOT NULL DEFAULT 0.00 COMMENT '实际到账金额，修改过金额才会有数据',
              `all_scale` decimal(10, 3) UNSIGNED NOT NULL DEFAULT 0.000 COMMENT '总优惠金额',
              `scale_amount` decimal(10, 3) UNSIGNED NOT NULL DEFAULT 0.000 COMMENT '订单发起者的优惠金额',
              `type` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '' COMMENT '充提类型',
              `aid` int(11) UNSIGNED NOT NULL COMMENT '交易匹配的用户ID',
              `fee` decimal(10, 3) UNSIGNED NOT NULL DEFAULT 0.000 COMMENT '手续费',
              `truename` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '真实姓名',
              `bank` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '银行名称',
              `bankprov` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '银行省份',
              `bankcity` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '银行城市',
              `bankaddr` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '银行支行',
              `bankcard` varchar(200) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '银行账号',
              `addtime` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间',
              `endtime` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '确认时间',
              `updatetime` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间',
              `overtime` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '订单过期时间',
              `status` int(4) NOT NULL DEFAULT 0 COMMENT '状态（0:未处理，1:待处理，2处理中，3成功，8取消, 99待删除）',
              `notifyurl` varchar(300) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '通知回调地址notifyurl',
              `callbackurl` varchar(300) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '页面跳转地址callbackurl',
              `payurl` varchar(300) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '支付的地址',
              `pay_channelid` int(11) NOT NULL DEFAULT 0 COMMENT '支付参数的通道id',
              `payparams_id` int(11) NOT NULL DEFAULT 0 COMMENT '使用的支付参数的id',
              `repost_num` tinyint(2) UNSIGNED NOT NULL DEFAULT 0 COMMENT '重复异步通知的次数',
              `rush_status` tinyint(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT '催单状态(0未催单，1已催单)',
              `punishment_level` tinyint(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT '延迟惩罚等级',
              `order_encode` varchar(1500) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' COMMENT '订单的加密信息',
              `return_order_id` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '返回商户订单号的后5位',
              `return_bank_name` varchar(200) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '付款银行名称',
              `return_account_name` varchar(200) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '付款账户名称',
              `pay_proof` varchar(300) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '付款截图',
              PRIMARY KEY (`id`, `addtime`) USING BTREE,
              INDEX `INDEX_EXCHAGEN_USERID`(`userid`) USING BTREE,
              INDEX `INDEX_EXCHAGEN_STATUS`(`status`) USING BTREE,
              INDEX `INDEX_EXCHAGEN_AID`(`aid`) USING BTREE,
              INDEX `INDEX_EXCHAGEN_ORDERID`(`orderid`) USING BTREE,
              INDEX `INDEX_EXCHANGE_OUT_ORDER`(`out_order_id`) USING BTREE
            ) ENGINE = MyISAM AUTO_INCREMENT = 1 CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = 'c2c交易记录表' ROW_FORMAT = Dynamic PARTITION BY HASH (addtime)
            PARTITIONS 50";
            
            if (strpos($tableName, 'pay_order') !== false) { //外部订单表
                $createSql = "
                DROP TABLE IF EXISTS `{$tableName}`;
                CREATE TABLE `{$tableName}`  (
                  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                  `otype` int(4) UNSIGNED NOT NULL DEFAULT 0 COMMENT '订单类型（0未知、1充值、2提现）',
                  `userid` int(11) UNSIGNED NOT NULL COMMENT '代理商id',
                  `orderid` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '订单号',
                  `out_order_id` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '' COMMENT '外部订单号',
                  `payment_order_id` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '' COMMENT '商户订单号',
                  `pay_channelid` varchar(50) NOT NULL DEFAULT 0 COMMENT '通道ID',
                  `mch_no` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '' COMMENT '支付平台商户号',
                  `appid` varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '' COMMENT '支付平台应用ID',
                  `amount` decimal(11, 2) NOT NULL DEFAULT 0.00 COMMENT '订单金额',
                  `amount_actual` decimal(11, 2) NOT NULL DEFAULT 0.00 COMMENT '实际金额',
                  `mch_fee_amount` decimal(11, 2) NOT NULL DEFAULT 0.00 COMMENT '商户手续费金额',
                  `bank_name` varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT '' COMMENT '收款人开户行名称',
                  `account_name` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT '' COMMENT '收款人姓名',
                  `account_no` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT '' COMMENT '收款账号',
                  `currency` varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '货币代码',
                  `status` int(4) NOT NULL DEFAULT 0 COMMENT '状态（0-订单生成；1-支付中；2-支付成功；3-支付失败；4-已撤销；5-已退款；6-订单关闭）',
                  `remarks` varchar(30) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '汇款备注',
                  `clientip` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT '' COMMENT '客户端IP',
                  `created_at` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间',
                  `success_time` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '成功时间',
                  `req_time` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '通知请求时间',
                  `addtime` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间',
                  `updatetime` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间',
                  PRIMARY KEY (`id`, `addtime`) USING BTREE,
                  INDEX `INDEX_EXCHAGEN_USERID`(`userid`) USING BTREE,
                  INDEX `INDEX_EXCHAGEN_STATUS`(`status`) USING BTREE,
                  INDEX `INDEX_EXCHAGEN_ORDERID`(`orderid`) USING BTREE,
                  INDEX `INDEX_EXCHANGE_OUT_ORDER`(`out_order_id`) USING BTREE,
                  INDEX `INDEX_EXCHANGE_MCH_NO`(`mch_no`) USING BTREE
                ) ENGINE = MyISAM AUTO_INCREMENT = 1 CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = '外部订单记录表' ROW_FORMAT = Dynamic PARTITION BY HASH (addtime)
                PARTITIONS 50";
            }

            $createResult = M()->execute($createSql);

            //创建order表
            return $createResult;
        }
        return false;
    }

    //判断数据库中对应的order表是否存在
    static function bIsExistDBTable($tableName){
        $tableName = C('DB_PREFIX') . $tableName;
        $exist = M()->query("show tables like '$tableName'");
        //Log::record('Order：where bIsExistDBTable serverParams = '.getServerIP(), Log::INFO);
        if($exist){
            return true;
        }else{
            return false;
        }   
    }

    //是否已经创建过表
    static function bIsCreatedTables($tableName){
      return in_array($tableName, self::$m_CreatedTables);
    }

    //添加已经创建过的表
    static function addCreatedTableName($tableName){
      if(!in_array($tableName, self::$m_CreatedTables)){
        array_push(self::$m_CreatedTables, $tableName);
      }
    }

    //获取已经创建的表的列表
    static function getCreatedTables(){
      return self::$m_CreatedTables;
    }

    //添加不存在的表
    static function addNoExistTableName($tableName){
        if(!in_array($tableName, self::$m_CreatedTables)){
            array_push(self::$m_NoExistTables, $tableName);
        }
    }

    //是否是不存在的表
    static function bIsNoExistTableName($tableName){
        //Log::record('Order：where bIsNoExistTableName = '.json_encode(self::$m_NoExistTables), Log::INFO);
        return in_array($tableName, self::$m_NoExistTables);
    }
}

?>