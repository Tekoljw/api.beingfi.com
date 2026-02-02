<?php
// 定义系统编码
header("Content-Type: text/html;charset=utf-8");
// 定义应用路径
define('APP_PATH', './Application/');
//定义根路径
define('APP_REALPATH',dirname(__FILE__));
// 定义缓存路径
define('RUNTIME_PATH', './Runtime/');
// 定义备份路径
define('DATABASE_PATH', './Database/');
// 定义钱包路径
define('COIN_PATH', './Coin/');
// 定义备份路径
define('UPLOAD_PATH', './Upload/');

// 后台安全入口
//define('ADMIN_KEY', 'starexchange');

// 获取所有传参
if ($_POST || $_GET) {
    $inputData = array_merge($_POST, $_GET);
} else {
    $inputData = [];
}
$inputString = file_get_contents('php://input');
if (json_decode($inputString, true)) {
    $inputStringData = json_decode($inputString, true);
} else {
    $inputStringData = parse_str($inputString);
}
if (!$inputStringData) {
    $inputStringData = [];
}
$inputData = array_merge($inputData, $inputStringData);

//法币交易OTC的key
define('BBAPIKEY', '1254972');

//定义交易码
define('MSCODE', 'www.01otc.com');

// 锚定货币单位（需要配合多处改才能生效，慎重）
$cnyChannel = [400]; // cny channelids
$thrChannel = [500]; // cny channelids
if (isset($inputData['channelid']) && in_array($inputData['channelid'], $cnyChannel)) {
    define('Anchor_CNY', 'cny');
} else if (isset($inputData['channelid']) && in_array($inputData['channelid'], $thrChannel)) {
    define('Anchor_CNY', 'thb');
} else {
    define('Anchor_CNY', 'mmk');
}


// 开启演示模式
define('APP_DEMO',0);
// 开始调试模式
define('APP_DEBUG',true);


//判断走手机还是PC
function wherecome()
{
    $_SERVER['ALL_HTTP'] = isset($_SERVER['ALL_HTTP']) ? $_SERVER['ALL_HTTP'] : '';
    $mobile_browser = '0';
    if(preg_match('/(up.browser|up.link|mmp|symbian|smartphone|midp|wap|phone|iphone|ipad|ipod|android|xoom)/i', strtolower($_SERVER['HTTP_USER_AGENT'])))
        $mobile_browser++;
    if((isset($_SERVER['HTTP_ACCEPT'])) and (strpos(strtolower($_SERVER['HTTP_ACCEPT']),'application/vnd.wap.xhtml+xml') !== false))
        $mobile_browser++;
    if(isset($_SERVER['HTTP_X_WAP_PROFILE']))
        $mobile_browser++;
    if(isset($_SERVER['HTTP_PROFILE']))
        $mobile_browser++;
    $mobile_ua = strtolower(substr($_SERVER['HTTP_USER_AGENT'],0,4));
    $mobile_agents = array(
        'w3c ','acs-','alav','alca','amoi','audi','avan','benq','bird','blac',
        'blaz','brew','cell','cldc','cmd-','dang','doco','eric','hipt','inno',
        'ipaq','java','jigs','kddi','keji','leno','lg-c','lg-d','lg-g','lge-',
        'maui','maxo','midp','mits','mmef','mobi','mot-','moto','mwbp','nec-',
        'newt','noki','oper','palm','pana','pant','phil','play','port','prox',
        'qwap','sage','sams','sany','sch-','sec-','send','seri','sgh-','shar',
        'sie-','siem','smal','smar','sony','nokia','sph-','symb','t-mo','teli','tim-',
        'tosh','tsm-','upg1','upsi','vk-v','voda','wap-','wapa','wapi','wapp',
        'wapr','webc','winw','winw','xda','xda-','samsung','lenovo'
    );
    if(in_array($mobile_ua, $mobile_agents))
        $mobile_browser++;
    if(strpos(strtolower($_SERVER['ALL_HTTP']), 'operamini') !== false)
        $mobile_browser++;
    // Pre-final check to reset everything if the user is on Windows
    if(strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'windows') !== false)
        $mobile_browser=0;
    // But WP7 is also Windows, with a slightly different characteristic
    if(strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'windows phone') !== false)
        $mobile_browser++;
    if($mobile_browser>0)
        return true;
    else
        return false;
}

// 判断访问入口
if(wherecome()) {
    define('WHERECOME','Mobile');
} else {
    define('WHERECOME','Home');
}

//define('WHERECOME','Home');
//引入guzzlehttp框架
require './core/Library/Vendor/composer/autoload.php';
// 引入入口文件
require './core/ThinkPHP.php';
?>