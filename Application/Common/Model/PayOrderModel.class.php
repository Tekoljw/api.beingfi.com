<?php
// +----------------------------------------------------------------------
// | 支付模型
// +----------------------------------------------------------------------
namespace Common\Model;

use Think\Model;
use Think\Log;
use Common\Model\DbTableModel;

//C2C订单的通用model
class PayOrderModel{

    private $normalTableName = 'pay_order_';   //通用表名
    private $orderTableCount = 12;                  //12张表，代表12个月份
    private $m_curTableNameList = array();          //当前表的名字的列表
    private $m_limitOffset = 0;                     //limit限制的起始点
    private $m_limitLength = 0;                     //limit限制获取的数量
    private $m_tableAlias = '';                     //字符串的别名
    private $m_where = array();                     //本次搜索的where参数
    private $m_curDay = 0;                          //当天
    private $m_curMonth = 0;                        //当月
    private $m_curYear = 0;                         //当年

    //是否已经调用where
    protected $m_bIsCallWhere = false;              //是否已经调用where
    //是否是查询集合数据
    protected $m_bIsSumData = false;
        
    /**
     * 架构函数
     * 取得DB类的实例对象 字段检查
     */
    public function __construct() {
        $this->m_bIsCallWhere = false;
        $this->m_bIsSumData = false;
        $this->m_where = array();
        //订单开始计算时间
        $order_start_time = strtotime(C('ORDER_START_TIME'));
        $this->order_start_Year = intval(date("Y",$order_start_time));
        $this->order_start_Month = intval(date("m",$order_start_time));
        $this->order_start_Day = intval(date("d",$order_start_time));
        $this->afterStartTables = array();

        //当前的时间
        $this->m_curDay = intval(date('d'));
        $this->m_curMonth = intval(date('m'));
        $this->m_curYear = intval(date('Y'));
    }

    //获取当前时间的c2c订单完整表名
    public function getCurExhcangeOrderTableName(){

        $curTableName = $this->getTableNameWithStrtotime(time());
        if(!$this->bIsCreateNewCurTable($curTableName)){
            return C('DB_PREFIX') . $curTableName;
        }else{
            return C('DB_PREFIX') . $curTableName;
        } 
    }

    //通过orderid来获取表名
    public function getExchangeOrderTableNameWithOrderID($orderid){
        $curTableName = $this->getTableNameWithOrderID($orderid);
        if($curTableName){
            if(!$this->bIsCreateNewCurTable($curTableName)){
                return C('DB_PREFIX') . $curTableName;
            }else{
                return C('DB_PREFIX') . $curTableName;
            }
        }
        return false;
    }

    /**
     * 新增数据(必须有订单号才能添加)
     * @access public
     * @param mixed $data 数据
     * @param array $options 表达式
     * @param boolean $replace 是否replace
     * @return mixed
     */
    public function add($data='',$options=array(),$replace=false) {
        $result = false;
        if(array_key_exists('orderid', $data))
        {
            $TableName = $this->getTableNameWithOrderID($data['orderid']);
            if($TableName && !$this->bIsCreateNewCurTable($TableName)){
                $result = M($TableName)->add($data, $options, $replace);
            }
        }else{
            Log::record('ExchangeOrderModel add函数 添加的数据中必须有pay_orderid', Log::INFO);
        }
        $this->resetParams(); //处理完毕恢复初始状态
        return $result;
    }

    /**
     * 保存数据(必须有订单号才能保存,或者确定某个表)
     * @access public
     * @param mixed $data 数据
     * @param array $options 表达式
     * @return boolean
     */
    public function save($data='',$options=array()) {
        $result = false;
        //Log::record('Order：where save month data = '.json_encode($data), Log::INFO);
        if(array_key_exists('orderid', $data)){

            $TableName = $this->getTableNameWithOrderID($data['orderid']);
            //Log::record('Order：where save month TableName = '.$TableName, Log::INFO);
            if($TableName && !$this->bIsCreateNewCurTable($TableName)){
                $result = M($TableName)->save($data, $options);
                //Log::record('Order：where save month result = '.$result, Log::INFO);
            }
        }elseif($this->m_bIsCallWhere){
            //最多更新2个表
            if(is_array($this->m_curTableNameList)){
                $tableCount = count($this->m_curTableNameList);
                if($tableCount == 1){
                    foreach ($this->m_curTableNameList as $key => $value) {
                        if(!$this->bIsCreateNewCurTable($value)){
                            $result = M($value)->save($data, $options);
                        }
                    }
                }elseif($tableCount > 1 && is_array($this->m_where) && !array_key_exists('id', $this->m_where)){ //带id的查询不能跨表，只能单表
                    foreach ($this->m_curTableNameList as $key => $value) {
                        if(!$this->bIsCreateNewCurTable($value)){
                            $result = M($value)->save($data, $options);
                        }
                    }
                }
            }else{
                $curTableName = $this->getTableNameWithStrtotime(time());
                if(!$this->bIsCreateNewCurTable($curTableName)){
                    $result = M($curTableName)->save($data, $options);
                }
            }
        }else{
             Log::record('ExchangeOrderModel save函数 保存的数据中必须有pay_orderid', Log::INFO);
        }
        $this->resetParams(); //处理完毕恢复初始状态
        return $result;
    }

    /**
     * 设置记录的某个字段值
     * 支持使用数据库字段和方法
     * @access public
     * @param string|array $field  字段名
     * @param string $value  字段值
     * @return boolean
     */
    public function setField($field,$value='') {
        if(is_array($field)) {
            $data           =   $field;
        }else{
            $data[$field]   =   $value;
        }
        return $this->save($data);
    }

     /**
     * 删除数据
     * @access public
     * @param mixed $options 表达式
     * @return mixed
     */
    public function delete($options=array()) {
        $result = 0;
        if($this->m_bIsCallWhere){ //必须先调用where，确定查询范围
            if(is_array($this->m_curTableNameList) && count($this->m_curTableNameList) > 0){
                foreach ($this->m_curTableNameList as $key => $value) {
                    if(!$this->bIsCreateNewCurTable($value)){
                        $tempResult = M($value)->delete($options);
                        if($tempResult){
                            $result += $tempResult;
                        }
                    }
                }
            }
        }else{
            Log::record('ExchangeOrderModel delete only Frist call where function, 必须优先调用where函数，可以传空', Log::INFO);
        }
        $this->resetParams(); //处理完毕恢复初始状态
        return $result==0?false:$result;
    }

    /**
     * 查询数据集
     * @access public
     * @param array $options 表达式参数
     * @return mixed
     */
    public function select($options=array()) {
        $tablesDataList = array();
        $offset = $this->m_limitOffset;
        $length = $this->m_limitLength;
        $AllCount = $length;
        //Log::record("OrderModel offset = $offset, length = $length", Log::INFO);
        //Log::save();
        if($this->m_bIsCallWhere){ //必须先调用where，确定查询范围
            //Log::record('Order select  m_curTableNameList ='. json_encode($this->m_curTableNameList), Log::INFO);
            if(is_array($this->m_curTableNameList) && count($this->m_curTableNameList) > 0){
                foreach ($this->m_curTableNameList as $key => $value) {
                    if(!$this->bIsCreateNewCurTable($value)){
                        if($length > 0){
                            $baseoOptions = M($value)->getOptions();
                            $tableData = M($value)->limit($offset . ',' . $length)->select($options);
                            if(empty($tableData)){
                                $dataCount = 0;
                            }else{
                                $dataCount = count($tableData);
                            }
                            if($dataCount <= 0)
                            {
                                $count = M($value)->setOptions($baseoOptions)->count();
                                //Log::record("OrderModel 2222 offset = $offset, count = $count", Log::INFO);
                                $offset -= $count;
                                if($offset < 0){$offset = 0;}
                                continue;
                            }
                        }else{
                            $tableData = M($value)->select($options);
                            if(empty($tableData)){
                                $dataCount = 0;
                            }else{
                                $dataCount = count($tableData);
                            }
                        }
                        if($tableData && $dataCount > 0){
                            //查到第一个数据以后，后面的表都从第一个数据开始取
                            $offset = 0;
                            $length = $length - $dataCount;
                            $tablesDataList = $this->getTablesData($tablesDataList, $tableData);
                            if($AllCount > 0 && count($tablesDataList) >= $AllCount){
                                break;
                            }
                        }
                    }
                }
            }
        }else{
            Log::record('ExchangeOrderModel select only Frist call where function, 必须优先调用where函数，可以传空', Log::INFO);
        }
        
        $this->resetParams(); //处理完毕恢复初始状态
        return $this->copyArray($tablesDataList, $offset, $AllCount);
    }

    /**
     * 查询数据(只查询1条数据)
     * @access public
     * @param mixed $options 表达式参数
     * @return mixed
     */
    public function find($options=array()) {
        
        $tablesDataList = array();
        if($this->m_bIsCallWhere){ //必须先调用where，确定查询范围
            if(is_array($this->m_curTableNameList) && count($this->m_curTableNameList) > 0){
                foreach ($this->m_curTableNameList as $key => $value) {
                    if(!$this->bIsCreateNewCurTable($value)){
                        $tableData = M($value)->find($options);
                        if($this->m_bIsSumData){ //是否是集合数据
                            $tablesDataList = $this->getTablesData($tablesDataList, $tableData);
                        }else{
                            $tablesDataList = $tableData;
                            if($tablesDataList) break;
                        }
                    }
                }
            }
        }else{
            Log::record('ExchangeOrderModel find only Frist call where function, 必须优先调用where函数，可以传空', Log::INFO);
        }
        $this->resetParams(); //处理完毕恢复初始状态
        return $tablesDataList;
    }

     /**
     * 获取一条记录的某个字段值
     * @access public
     * @param string $field  字段名
     * @param string $spea  字段数据间隔符号 NULL返回数组
     * @return mixed
     */
    public function getField($field,$sepa=null) {
        $tablesDataList = array();
        if($this->m_bIsCallWhere){ //必须先调用where，确定查询范围
            if(is_array($this->m_curTableNameList) && count($this->m_curTableNameList) > 0){
                foreach ($this->m_curTableNameList as $key => $value) {
                    if(!$this->bIsCreateNewCurTable($value)){
                        $tablesDataList = M($value)->getField($field,$sepa);
                        if($tablesDataList){
                            break;
                        }
                    }
                }
            }
        }else{
            Log::record('ExchangeOrderModel getField only Frist call where function, 必须优先调用where函数，可以传空', Log::INFO);
        }
        $this->resetParams(); //处理完毕恢复初始状态
        return $tablesDataList;
    }

    /**
     * SQL查询
     * @access public
     * @param string $sql  SQL指令
     * @param mixed $parse  是否需要解析SQL
     * @return mixed
     */
    public function query($sql,$parse=false) {
        $tablesDataList = array();
        if($this->m_bIsCallWhere){ //必须先调用where，确定查询范围

            if(is_array($this->m_curTableNameList) && count($this->m_curTableNameList) > 0){
                foreach ($this->m_curTableNameList as $key => $value) {
                    if(!$this->bIsCreateNewCurTable($value)){
                        $tableData = M($value)->query($sql, $parse);
                        $tablesDataList = $this->getTablesData($tablesDataList, $tableData);
                    }
                }
            }
        }else{
            Log::record('ExchangeOrderModel query only Frist call where function, 必须优先调用where函数，可以传空', Log::INFO);
        }
        $this->resetParams(); //处理完毕恢复初始状态
        return $tablesDataList;
    }

    /**
     * 数据的数量
     * @access public
     * @return mixed
     */
    public function count(){
        $count = 0;
        if($this->m_bIsCallWhere){ //必须先调用where，确定查询范围
            if(is_array($this->m_curTableNameList) && count($this->m_curTableNameList) > 0){
                foreach ($this->m_curTableNameList as $key => $value) {
                    if(!$this->bIsCreateNewCurTable($value)){$count += M($value)->count();}
                }
            }
        }else{
            Log::record('ExchangeOrderModel count only Frist call where function, 必须优先调用where函数，可以传空', Log::INFO);
        }
        $this->resetParams(); //处理完毕恢复初始状态
        return $count;
    }

    /**
     * 获取数据的求和
     * @access public
     * @return mixed
     */
    public function sum($param){
        $sum = 0;
        if($this->m_bIsCallWhere){ //必须先调用where，确定查询范围
            if(is_array($this->m_curTableNameList) && count($this->m_curTableNameList) > 0){
                foreach ($this->m_curTableNameList as $key => $value) {
                    if(!$this->bIsCreateNewCurTable($value)){
                        $temp_sum = M($value)->sum($param);
                        if(isset($temp_sum))$sum += $temp_sum;
                    }
                }
            }
        }else{
            Log::record('ExchangeOrderModel sum only Frist call where function, 必须优先调用where函数，可以传空', Log::INFO);
        }
        $this->resetParams(); //处理完毕恢复初始状态
        return $sum;
    }

    /**
     * 获取数据的最小数
     * @access public
     * @return mixed
     */
    public function min($param){
        $min = 0;
        if($this->m_bIsCallWhere){ //必须先调用where，确定查询范围
            if(is_array($this->m_curTableNameList) && count($this->m_curTableNameList) > 0){
                foreach ($this->m_curTableNameList as $key => $value) {
                    if(!$this->bIsCreateNewCurTable($value)){
                        $temp_min = M($value)->min($param);
                        if(isset($temp_min) && $temp_min < $min){
                            $min = $temp_min;
                        }
                    }
                }
            }
        }else{
            Log::record('ExchangeOrderModel min only Frist call where function, 必须优先调用where函数，可以传空', Log::INFO);
        }
        $this->resetParams(); //处理完毕恢复初始状态
        return $min;
    }

    /**
     * 获取数据的最大数
     * @access public
     * @return mixed
     */
    public function max($param){
        $max = 0;
        if($this->m_bIsCallWhere){ //必须先调用where，确定查询范围
            if(is_array($this->m_curTableNameList) && count($this->m_curTableNameList) > 0){
                foreach ($this->m_curTableNameList as $key => $value) {
                    if(!$this->bIsCreateNewCurTable($value)){
                        $temp_max = M($value)->max($param);
                        if(isset($temp_max) && $temp_max > $max){
                            $max = $temp_max;
                        }
                    }
                }
            }
        }else{
            Log::record('ExchangeOrderModel max only Frist call where function, 必须优先调用where函数，可以传空', Log::INFO);
        }
        $this->resetParams(); //处理完毕恢复初始状态
        return $max;
    }


    /////////////////////////////////////////////////////////////////////////////////////////////
    //设置查询参数函数

    public function order($method){
        if($this->m_bIsCallWhere){ //必须先调用where，确定查询范围
            if(is_array($this->m_curTableNameList) && count($this->m_curTableNameList) > 0){
                foreach ($this->m_curTableNameList as $key => $value) {
                    M($value)->order($method);
                }
            }
            return $this;
        }else{
            Log::record('ExchangeOrderModel order only Frist call where function, 必须优先调用where函数，可以传空', Log::INFO);
        }
        return false;
    }

    public function alias($method){
        
        if($this->m_bIsCallWhere){ //必须先调用where，确定查询范围
            if(is_array($this->m_curTableNameList) && count($this->m_curTableNameList) > 0){
                foreach ($this->m_curTableNameList as $key => $value) {
                    M($value)->alias($method);
                }
            }
            //解析当前的别名
            $pos = strpos($method, 'as');
            $tempStr = substr($method, $pos+2);
            $this->tableAlias = str_replace(' ','',$tempStr);
            return $this;
        }else{
            Log::record('ExchangeOrderModel alias only Frist call where function, 必须优先调用where函数，可以传空', Log::INFO);
        }
        return false;
    }

    public function group($method){
        if($this->m_bIsCallWhere){  //必须先调用where，确定查询范围
            if(is_array($this->m_curTableNameList) && count($this->m_curTableNameList) > 0){
                foreach ($this->m_curTableNameList as $key => $value) {
                    M($value)->group($method);
                }
            }
            return $this;
        }else{
            Log::record('ExchangeOrderModel group only Frist call where function, 必须优先调用where函数，可以传空', Log::INFO);
        }
        return false;
    }

    public function strict($method){
        if($this->m_bIsCallWhere){ //必须先调用where，确定查询范围
            if(is_array($this->m_curTableNameList) && count($this->m_curTableNameList) > 0){
                foreach ($this->m_curTableNameList as $key => $value) {
                    M($value)->strict($method);
                }
            }
            return $this;
        }else{
            Log::record('ExchangeOrderModel strict only Frist call where function, 必须优先调用where函数，可以传空', Log::INFO);
        }
        return false;
    }

    public function having($method){
        if($this->m_bIsCallWhere){ //必须先调用where，确定查询范围
            if(is_array($this->m_curTableNameList) && count($this->m_curTableNameList) > 0){
                foreach ($this->m_curTableNameList as $key => $value) {
                    M($value)->lock($method);
                }
            }
            return $this;
        }else{
            Log::record('ExchangeOrderModel having only Frist call where function, 必须优先调用where函数，可以传空', Log::INFO);
        }
        return false;
    }

    public function lock($method){
        if($this->m_bIsCallWhere){ //必须先调用where，确定查询范围
            if(is_array($this->m_curTableNameList) && count($this->m_curTableNameList) > 0){
                foreach ($this->m_curTableNameList as $key => $value) {
                    M($value)->lock($method);
                }
            }
            return $this;
        }else{
            Log::record('ExchangeOrderModel lock only Frist call where function, 必须优先调用where函数，可以传空', Log::INFO);
        }
        return false;
    }

    /**
     * 指定查询字段 支持字段排除
     * @access public
     * @param mixed $field
     * @param boolean $except 是否排除
     * @return Model
     */
    public function field($field,$except=false){
        if($this->m_bIsCallWhere){ //必须先调用where，确定查询范围
            if(is_string($field)){

                $sumpos = strpos($field, 'SUM');
                $sumpos1 = strpos($field, 'sum');
                $countpos = strpos($field, 'COUNT');
                $countpos1 = strpos($field, 'count');
                if($sumpos !== false || $sumpos1 !== false || $countpos !== false || $countpos1 !== false){
                    $this->m_bIsSumData = true;
                }
            }
            if(is_array($this->m_curTableNameList) && count($this->m_curTableNameList) > 0){
                foreach ($this->m_curTableNameList as $key => $value) {
                    M($value)->field($field, $except);
                }
            }
            return $this;
        }else{
            Log::record('ExchangeOrderModel field only Frist call where function, 必须优先调用where函数，可以传空', Log::INFO);
        }
        return false;
    }

     /**
     * 查询SQL组装 join
     * @access public
     * @param mixed $join
     * @param string $type JOIN类型
     * @return Model
     */
    public function join($join,$type='INNER') {
        if($this->m_bIsCallWhere){ //必须先调用where，确定查询范围
            if(is_array($this->m_curTableNameList) && count($this->m_curTableNameList) > 0){
                foreach ($this->m_curTableNameList as $key => $value) {
                    $_join = str_replace('__ORDER__',C('DB_PREFIX').$value,$join);
                    M($value)->join($_join, $type);
                }
            }
            return $this;
        }else{
            Log::record('ExchangeOrderModel join only Frist call where function, 必须优先调用where函数，可以传空', Log::INFO);
        }
        return false;
    }

    /**
     * 指定查询数量
     * @access public
     * @param mixed $offset 起始位置
     * @param mixed $length 查询数量
     * @return Model
     */
    public function limit($offset,$length=null){
        if(is_null($length) && strpos($offset,',')){
            list($offset,$length)   =   explode(',',$offset);
        }
        $this->m_limitOffset = $offset;
        $this->m_limitLength = $length;
        return $this;
    }

      /**
     * 指定查询条件 支持安全过滤(函数必须传入array形式的变量, 也可以传空值，表示查询当前月数据)
     * @access public
     * @param mixed $where 条件表达式(array形式)
     * @param mixed $parse 预处理参数
     * @return Model
     */
    public function where($where,$parse=null){
        if(is_array($where)){
            //Log::record('Order：where 开始 where = '.json_encode($where), Log::INFO);
            //判断是是否存在可以加快查询的字段
            $orderId = $this->bIsExistOrderIdInWhere($where);
            $dateArray = $this->bIsExistApplyDateInWhere($where);
            if($orderId){
                //orderId赋值是通过array数组方式进行
                if(is_array($orderId) && (in_array('eq', $orderId) || in_array('EQ', $orderId))){
                    $orderId = end($orderId);
                }
                $TableName = $this->getTableNameWithOrderID($orderId);
                //添加创建时间，提高分区搜索的速度
                $orderTimeStr = substr($orderId, 0, 14);
                $year = substr($orderTimeStr, 0, 4);
                $month = substr($orderTimeStr, 4, 2);
                $day = substr($orderTimeStr, 6, 2);
                $hour = substr($orderTimeStr, 8, 2);
                $min = substr($orderTimeStr, 10, 2);
                $second = substr($orderTimeStr, 12, 2);
                $orderTime = $year.'-'.$month.'-'.$day.' '.$hour.':'.$min.':'.$second;
                if($this->m_tableAlias && $this->m_tableAlias != ''){
                    $where['addtime'] = strtotime($orderTime);
                }else{
                    $where['addtime'] = strtotime($orderTime);
                }
                M($TableName)->where($where, $parse); //设置where值
                //Log::record('Order：where 进行ID匹配 where = '.json_encode($where).' orderTime = '.$orderTime, Log::INFO);
            }
            elseif($dateArray){

                //Log::record('Order：where 进行时间匹配 dateArray = '.json_encode($dateArray), Log::INFO);
                if(is_array($dateArray)){
                    //Log::record('Order：where 进行时间匹配 dateArray is array', Log::INFO);
                    if(in_array('lt', $dateArray) || in_array('LT', $dateArray) || in_array('elt', $dateArray) 
                        || in_array('ELT', $dateArray)){
                        $dateTime = end($dateArray);
                        $this->getTableNameWithTime($dateTime, 0,1);
                    }elseif(in_array('gt', $dateArray) || in_array('GT', $dateArray) || in_array('egt', $dateArray) 
                        || in_array('EGT', $dateArray)){
                        $dateTime = end($dateArray);
                        $this->getTableNameWithTime($dateTime, 0,2);
                        //Log::record('Order：where 大于某个时间 pay_applydate = '.$dateTime.' indexZoneId='.$indexZoneId, Log::INFO);
                    }elseif(in_array('between', $dateArray) || in_array('BETWEEN', $dateArray)) {
                        $betweenTimeArray = end($dateArray);
                        $startTime = current($betweenTimeArray);
                        $endTime = end($betweenTimeArray);
                        $this->getTableNameWithTime($startTime , $endTime,3);
                        //Log::record('Order：where 某个时间范围内 $startTime = '.$startTime.' $endTime= '. $endTime . '$indexStartZoneId='.$indexStartZoneId.'indexEndZoneId ='.$indexEndZoneId, Log::INFO);
                    }elseif(in_array('eq', $dateArray) || in_array('EQ', $dateArray)){
                        //等于时间
                        $dateTime = end($dateArray);
                        $this->getTableNameWithTime($dateTime,0,4);
                    }else{

                        $this->getTableNameWithTime();
                    }
                    //设置where值
                    foreach ($this->m_curTableNameList as $key => $value) {
                        M($value)->where($where, $parse);
                    }
                }else{

                    $TableName = $this->getTableNameWithTime($dateArray,0,4);
                    if($TableName && !is_array($TableName)){
                        M($TableName)->where($where, $parse); //设置where值
                    }elseif(is_array($TableName)){
                        //设置where值
                        foreach ($this->m_curTableNameList as $key => $value) {
                            M($value)->where($where, $parse);
                        }
                    }
                }
            }else{

                //Log::record('Order：where 没有时间匹配 $where = '.json_encode($where), Log::INFO);
                $this->getDefaultTableName();
                //设置where值
                foreach ($this->m_curTableNameList as $key => $value) {
                    //Log::record('Order：where 没有时间匹配 value ='.$value.' where = '.json_encode($where), Log::INFO);
                    M($value)->where($where, $parse);
                }
            }
        }
        elseif($where){
            //Log::record('Order：where 不是 array = '.json_encode($where), Log::INFO);
            $this->getDefaultTableName();
            //设置where值
            foreach ($this->m_curTableNameList as $key => $value) {
                M($value)->where($where, $parse);
            }
        }else{
            //设置默认表
            $this->getDefaultTableName();
        }
        $this->m_where = $where;
        $this->m_bIsCallWhere = true;
        return $this;
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////
    //私有处理函数

    //获取创建表的名字
    private function getCreateTableName(){
        return $this->getTableNameWithStrtotime(time(),1);
    }

    //是否需要创建新的表
    private function bIsCreateNewCurTable($tableName = null){

        $intervalTime = C('ORDER_TABLE_INTERVAL_TIME') ? C('ORDER_TABLE_INTERVAL_TIME') : 7;
        //是否创建下个周期的表
        $curDay = $this->m_curDay;
        $curHour = intval(date("H"));
        if(($curDay % $intervalTime) == $intervalTime-1 && $curHour >= 3 && $curHour <= 5){ //一周最后一天的凌晨3点
            $tempTableName = $this->getCreateTableName();
            if(!DbTableModel::bIsExistDBTable($tableName)){
                DbTableModel::createExchangeOrderTable($tableName);
            }
        }
        
        $bIsCreateNewTable = false; //是否新创建表
        //由于怕出现运维事故，删除了表，却不知道。故只能自动添加当前时间的表。历史表不能自动创建
        $curTableName = $this->getTableNameWithStrtotime(time());
        if($curTableName == $tableName){
            $bIsCreateNewTable = true;
            //Log::record('Order：where curTableName curTableName = '.$curTableName.' bIsCreateNewTable = '.$bIsCreateNewTable, Log::INFO);
        }

        if($tableName){
            //Log::record('Order：where m_CreatedTables = '.json_encode(DbTableModel::getCreatedTables()), Log::INFO);
            if(DbTableModel::bIsNoExistTableName($tableName)){ //是否是不存在的表
                //Log::record('Order：where bIsNoExistTableName = '.$curTableName, Log::INFO);
                return true;
            }
            elseif(DbTableModel::bIsCreatedTables($tableName)){ //是否已创建的表
                //Log::record('Order：where bIsCreatedTables = '.$curTableName, Log::INFO);
                return false;
            }else{
                if(DbTableModel::bIsExistDBTable($tableName)){    //是否存在表
                    DbTableModel::addCreatedTableName($tableName);
                    //Log::record('Order：where bIsExistDBTable tableName = '.$tableName, Log::INFO);
                    return false;
                }elseif($bIsCreateNewTable){
                    //Log::record('Order：where createExchangeOrderTable tableName = '.$tableName, Log::INFO);
                    $result = DbTableModel::createExchangeOrderTable($tableName);
                    return false;
                }else{
                    //添加到不存在的表
                    DbTableModel::addNoExistTableName($tableName);
                    //Log::record('Order：where addNoExistTableName tableName = '.$tableName, Log::INFO);
                    return true;
                }
            }
        }
        return false;
    }

    //是否存在订单号的索引条件
    private function bIsExistOrderIdInWhere($where){
        foreach ($where as $key => $value) {

            if(strpos(strtolower($key), 'orderid')!==false){
                 return $value;
            }
        }
        return false;
    }

    //是否存在时间的索引条件
    private function bIsExistApplyDateInWhere($where){
        foreach ($where as $key => $value) {
            if(strpos(strtolower($key), 'addtime')!==false){
                 return $value;
            }elseif (strpos(strtolower($key), 'endtime')!==false) {
                return $value;
            }elseif (strpos(strtolower($key), 'updatetime')!==false) {
                return $value;
            }elseif (strpos(strtolower($key), 'overtime')!==false) {
                return $value;
            }
        }
        return false;
    }

    //通过绝对时间获取表名
    private function getTableNameWithStrtotime($time,$addWeek=0){
        if($time){
            $intervalTime = C('ORDER_TABLE_INTERVAL_TIME') ? C('ORDER_TABLE_INTERVAL_TIME') : 7;
            $day = intval(date("d",$time));
            $week = intval($day/$intervalTime)+1+$addWeek;
            $tableName = $this->normalTableName.date("Ym",$time).$week;
            return $tableName;
        }
        return false;
    }

    //通过年月日获取表名
    private function getTableNameWithYMD($year, $month, $day){
        if(is_numeric($day)){
            $intervalTime = C('ORDER_TABLE_INTERVAL_TIME') ? C('ORDER_TABLE_INTERVAL_TIME') : 7;
            $week = intval($day/$intervalTime)+1;
            if($month < 10){
                $tableName = $this->normalTableName.$year.'0'.$month.$week;
            }else{
                $tableName = $this->normalTableName.$year.$month.$week;
            }
            return $tableName;
        }
        return false;
    }

    //通过订单号计算当前需要访问的表名
    private function getTableNameWithOrderID($pay_orderid){
        if($pay_orderid){
            $this->m_curTableNameList = array();
            $orderTimeStr = substr($pay_orderid, 0, 8);
            $orderTime = substr($orderTimeStr, 0, 4).'-'.substr($orderTimeStr, 4, 2).'-'.substr($orderTimeStr, 6, 2);
            $tempTableName = $this->getTableNameWithStrtotime(strtotime($orderTime));
            $this->addCurUseTableName($tempTableName);
            //Log::record('Order：where countTableNameWithOrderID month i = '.$month.' m_curTableNameList='.$this->m_curTableNameList, Log::INFO);
            return $tempTableName;
        }
        return false;
    }

    //通过创建时间计算当前需要访问的表名
    private function getTableNameWithTime($pay_applydate_start=-1, $pay_applydate_end=-1, $timeType=0){
        $this->m_curTableNameList = array();
        if($pay_applydate_start){
            
            switch ($timeType) {
                case 1:     //小于某个时间
                {
                    $curYear = $this->m_curYear;

                    $dayEnd = intval(date('d',$pay_applydate_start));
                    $monthEnd = intval(date('m',$pay_applydate_start));
                    $year = intval(date('Y',$pay_applydate_start));

                    if($curYear >= $year && $year >= $curYear-1){    //只能是最近两年

                        $this->setBetweenTimeTableName($year,1,1, $year,$monthEnd,$dayEnd);
                    }
                    else{
                        //时间选择错误;
                        $this->getDefaultTableName();
                    }
                    break;
                }
                case 2:     //大于某个时间
                {
                    $curDay = $this->m_curDay;
                    $curMonth = $this->m_curMonth;
                    $curYear = $this->m_curYear;

                    $dayStart = intval(date('d',$pay_applydate_start));
                    $monthStart = intval(date('m',$pay_applydate_start));
                    $year = intval(date('Y',$pay_applydate_start));
                    if($curYear >= $year && $year >= $curYear-1){        //只能是最近两年

                        $this->setBetweenTimeTableName($year,$monthStart,$dayStart, $curYear,$curMonth,$curDay);
                    }
                    else{
                        //时间选择错误;
                        $this->getDefaultTableName();
                    }
                    break;
                } 
                case 3:     //某个时间段之间
                {
                    $curYear = $this->m_curYear;

                    $dayStart = intval(date('d',$pay_applydate_start));
                    $monthStart = intval(date('m',$pay_applydate_start));
                    $yearStart = intval(date('Y',$pay_applydate_start));
                    $dayEnd = intval(date('d',$pay_applydate_end));
                    $monthEnd = intval(date('m',$pay_applydate_end));
                    $yearEnd = intval(date('Y',$pay_applydate_end));
                    if($curYear >= $yearStart && $yearStart >= $curYear-1 && $curYear >= $yearEnd && $yearEnd >= $curYear-1){
                        
                        $this->setBetweenTimeTableName($yearStart,$monthStart,$dayStart, $yearEnd,$monthEnd,$dayEnd);
                    }else{
                        //时间选择错误;
                        $this->getDefaultTableName();
                    }
                    break;
                } 
                case 4:     //确定的某个时间
                {
                    $curYear = $this->m_curYear;

                    $monthStart = intval(date('m',$pay_applydate_start));
                    $year = intval(date('Y',$pay_applydate_start));
                    if($year >= $curYear-1){        //只能是最近两年
                        if($this->LimitHalfYearData($year, $monthStart)) $this->addCurUseTableName($this->getTableNameWithStrtotime($pay_applydate_start));
                    }else{
                        //时间选择错误;
                        $this->getDefaultTableName();
                    }
                    break;
                } 
                
                default:
                    $this->getDefaultTableName();
                    break;
            }
        }else{
            $this->getDefaultTableName();
        }
        return $this->m_curTableNameList;
    }

    //设置两个时间点之间的表名
    private function setBetweenTimeTableName($yearStart,$monthStart,$dayStart, $yearEnd, $monthEnd, $dayEnd){
        $curDay = $this->m_curDay;
        $curMonth = $this->m_curMonth;
        $curYear = $this->m_curYear;
        if($yearStart <= $curYear && $yearEnd <= $curYear){                     //结束年和开始年都必须当前时间小
            if($yearStart == $yearEnd){                                         //年份相同
                if($curYear == $yearStart){                                     //如果为当前年
                    $monthStart = $monthStart > $curMonth ? $curMonth : $monthStart;    //不能比当前的时间大
                    $monthEnd = $monthEnd > $curMonth ? $curMonth : $monthEnd;          //不能比当前的时间大
                    if($curMonth == $monthStart && $monthStart == $monthEnd){   //如果为当前月
                        $dayEnd = $dayEnd > $curDay ? $curDay:$dayEnd;          //不能比当前的时间大
                        $dayStart = $dayStart > $curDay ?$curDay:$dayStart;     //不能比当前的时间大
                    }
                }

                if($monthStart == $monthEnd){                                   //相同月份
                    if($this->LimitHalfYearData($yearStart, $monthStart)){
                        for($i=$dayEnd; $i >= $dayStart; $i--){                 //天循环
                            $tempTableName = $this->getTableNameWithYMD($yearStart, $monthStart, $i);
                            $this->addCurUseTableName($tempTableName);
                        }
                    }
                }else{
                    for ($i=$monthEnd; $i >=$monthStart; $i--) { 
                        if($this->LimitHalfYearData($yearStart, $i)){
                            $startIndex = $i == $monthStart ? $dayStart : 1;    //如果为开始月
                            $endIndex = $i == $monthEnd ? $dayEnd : 31;         //如果为结束月
                            for($j=$endIndex; $j >= $startIndex; $j--){         //天循环
                                $tempTableName = $this->getTableNameWithYMD($yearStart, $i, $j);
                                $this->addCurUseTableName($tempTableName);
                            }
                        }
                        //Log::record('Order：where 某个时间之间 countTableNameWithTime i = '.$i.' monthStart='.$monthStart, Log::INFO);
                    }
                }
            }elseif($yearStart < $yearEnd){                                     //年份不同

                if($curYear == $yearEnd){                                       //如果为当前年
                    $monthEnd = $monthEnd > $curMonth ? $curMonth : $monthEnd;  //不能比当前的时间大
                    if($curMonth == $monthEnd){                                 //如果为当前月
                        $dayEnd = $dayEnd > $curDay ? $curDay:$dayEnd;          //不能比当前的时间大
                    }
                }

                for ($i=12; $i >=$monthStart; $i--) { 
                    if($this->LimitHalfYearData($yearStart, $i)){
                        $startIndex = $i == $monthStart ? $dayStart : 1;        //如果为开始月
                        for($j=31; $j >= $startIndex; $j--){                    //天循环
                            $tempTableName = $this->getTableNameWithYMD($yearStart, $i, $j);
                            $this->addCurUseTableName($tempTableName);
                        }
                    }
                    //Log::record('Order：where 某个时间之间 countTableNameWithTime i = '.$i.' monthStart='.$monthStart, Log::INFO);
                }
                for ($i=$monthEnd; $i >=1; $i--) { 
                    if($this->LimitHalfYearData($yearEnd, $i)){
                        $endIndex = $i == $monthEnd ? $dayEnd : 31;             //如果为结束月
                        for($j=$endIndex; $j >= 1; $j--){                       //天循环
                            $tempTableName = $this->getTableNameWithYMD($yearEnd, $i, $j);
                            $this->addCurUseTableName($tempTableName);
                        }
                    }
                    //Log::record('Order：where 某个时间之间 countTableNameWithTime i = '.$i.' monthStart='.$monthStart, Log::INFO);
                }
            }else{
                $this->getDefaultTableName();
            }
        }else{
            $this->getDefaultTableName();
        }
    }

    //获取默认的表列表
    private function getDefaultTableName(){
        $this->m_curTableNameList = array();
        $curYear = intval(date('Y'));
        $curMonth = intval(date('m'));
        $curDay = intval(date('d'));

        //当月的数据表
        for($i=$curDay; $i >= 1; $i--){ //天循环
            $tempTableName = $this->getTableNameWithYMD($curYear, $curMonth, $i);
            $this->addCurUseTableName($tempTableName);
        }
        //小于半月就取上半个月的数据
        if($curDay < 14){
            $beforeMonth = $curMonth - 1;
            if($curMonth == 1){
                $curYear = $curYear -1;
                $beforeMonth = 12;
            }
            for($i=31; $i >= 14; $i--){ //天循环
                $tempTableName = $this->getTableNameWithYMD($curYear, $beforeMonth, $i);
                $this->addCurUseTableName($tempTableName);
            }
        }
        //Log::record('Order：getDefaultTableName m_curTableNameList = '.json_encode($this->m_curTableNameList), Log::INFO);
    }

    //判断是否是订单开始时间以后的表
    private function bIsAfterOrderStart($tableName){

        if(!in_array($tableName, $this->afterStartTables)){ //是否已经在开始时间以后的表内

            if(!DbTableModel::bIsCreatedTables($tableName)){ //是否已创建的表

                $length = strlen($tableName);
                $tableYear = intval(substr($tableName, $length-7, 4));
                $tableMonth = intval(substr($tableName, $length-3, 2));
                $tableWeek = intval(substr($tableName, $length-1));
                $tableDay = $tableWeek * 7;
                //Log::record('bIsAfterOrderStart tableWeek = '.$tableWeek.' tableDay = '.$tableDay.' order_start_Month = '.$this->order_start_Month.' order_start_Day = '. $this->order_start_Day, Log::INFO);
                if($tableYear < $this->order_start_Year){
                    DbTableModel::addNoExistTableName($tableName);
                    return false;
                }else{
                    if($tableYear == $this->order_start_Year){
                        if($tableMonth < $this->order_start_Month){
                            DbTableModel::addNoExistTableName($tableName);
                            return false;
                        }elseif($tableMonth == $this->order_start_Month && $tableDay <= $this->order_start_Day){
                            DbTableModel::addNoExistTableName($tableName);
                            return false;
                        }
                    }
                }
            }
            array_push($this->afterStartTables, $tableName);
        }
        return true;
    }

    //添加需要用到的表
    private function addCurUseTableName($tableName){
        if(!DbTableModel::bIsNoExistTableName($tableName)){
            if($this->bIsAfterOrderStart($tableName)){
                $this->m_curTableNameList[$tableName] = $tableName;
            }
        }
    }

    //限制只能查询半年内的数据
    private function LimitHalfYearData($year,$month){
        $curMonth = $this->m_curMonth;
        $curYear = $this->m_curYear;
        if($curYear == $year){  //当年时间判断
            if($curMonth == $month){
                return true;
            }
            elseif($curMonth >= 6){ //当月时间大于等于6月
                if($curMonth > $month && $month > $curMonth-6){
                    return true;
                }
            }else{
                if($curMonth > $month){
                    return true;
                }
            }
        }elseif($year == $curYear-1){   //前一年的时间判断
            if($curMonth < 6 && $month > $curMonth+6){
                return true;
            }
        }
        return false;
    }

    //获取tables里面的数据
    private function getTablesData($tablesDataList, $tableData){
        if(empty($tablesDataList)){

            return $tableData;
        }elseif(is_array($tableData) && is_array($tablesDataList)){

            foreach ($tableData as $key => $value) {
                if(!is_array($value) && isset($tablesDataList[$key])){
                    $tablesDataList[$key] += $value;
                    unset($tableData[$key]);
                }
            }
            if(!empty($tableData)){
                return array_merge($tablesDataList, $tableData);
            }
        }
        return $tablesDataList;
    }

    //拷贝array中的一部分数据
    private function copyArray($srcArray, $index=null , $count=null){
        $copyArr = array();
        $curIndex = 0;
        $curCount = 0;
        if(is_array($srcArray) && $index && $count && $count > 0){
            foreach ($srcArray as $key => $value) {
                if($index == $curIndex){

                    $copyArr[$key] = $value;
                    $curCount += 1;
                    if($count == $curCount){
                        break;
                    }
                    continue;
                }
                $curIndex += 1;
            }
            return $copyArr;
        }
        $copyArr = $srcArray;
        return $copyArr;
    }

    //重置查询的参数
    private function resetParams(){
        $this->m_limitOffset = 0;
        $this->m_limitLength = 0;
        $this->m_tableAlias = '';
        $this->m_bIsCallWhere = false;
        $this->m_bIsSumData= false;
        $this->m_where = array();

        //重置数据库查询参数
        foreach($this->m_curTableNameList as $key => $value) {
            M($value)->resetOptions();
        }
        $this->m_curTableNameList = array(); //处理完毕恢复初始状态
    }
}

?>