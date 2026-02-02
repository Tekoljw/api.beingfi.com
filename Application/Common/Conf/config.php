<?php
//安全过滤文件
require dirname(__FILE__).'/secure.php';
return array(
	'COOKIE_EXPIRE' 			=> 3600,
    'COOKIE_SECURE' 			=> false,
    'COOKIE_HTTPONLY' 			=> true,
    'LOG_RECORD'            	=> false,  // 进行日志记录
    'LOG_EXCEPTION_RECORD'  	=> false,    // 是否记录异常信息日志
    'LOG_LEVEL'             	=> '',  // 允许记录的日志级别
	'LOAD_EXT_CONFIG' 	   		=> 'db,paytype,tradeconfig', //预先加载的配置文件
	'ACTION_SUFFIX'        		=> '',
	'MULTI_MODULE'         		=> true,
	'MODULE_DENY_LIST'     		=> array('Common', 'Runtime', 'Admin', 'Home', 'Mobile'),
	'MODULE_ALLOW_LIST'    		=> array('Support','Cli','Pay','Api'),
	'DEFAULT_MODULE'       		=> WHERECOME,
	'URL_CASE_INSENSITIVE' 		=> false,
	'URL_MODEL'            		=> 2,
	'URL_HTML_SUFFIX'      		=> 'html',
	//'URL_PATHINFO_DEPR' 		=> '_', //PATHINFO URL分割符
	'LANG_SWITCH_ON'       		=> true, //开启多语言支持开关
	
	'LANG_AUTO_DETECT'     		=> true, // 自动侦测语言
	'DEFAULT_LANG'         		=> 'zh-cn', // 默认语言
	'LANG_LIST'     	   		=> 'zh-cn,zh-tw,en-us',
	'VAR_LANGUAGE'         		=> 'LANG', //默认语言切换变量
    'PTP_MARKET'           		=> array('USDT','BTC'),
    // 'NATION'    				=> array('en_US'=>'美国','ja_JP'=>'日本','ko_KR'=>'韩国','ru_RU'=>'俄罗斯','zh_CN'=>'中国','zh_HK'=>'中国香港')
    'NATION'     				=> array('zh_CN'=>'中国','en_US'=>'美国',),
    'COINTR'     				=> array('CNY'=>'人民币'),
    'BBAPIKEY'   				=> BBAPIKEY,
    'USER_ADMINISTRATOR'		=> 1, 		//超级管理员的ID
    'ORDER_TABLE_INTERVAL_TIME' => 7, 	//c2c订单表的时间间隔
    'ORDER_START_TIME'			=> 1579447171, //订单计算的开始时间
	
	'TMPL_ACTION_ERROR' 		=> './Public/error.html', //默认错误跳转对应的模板文件
	'TMPL_ACTION_SUCCESS' 		=> './Public/success.html', //默认成功跳转对应的模板文件
	'STAR_EXCHANGE_WEB_URL' 	=> 'https://test-otc-api.beingfi.com', //交易所平台的网址
	'PAY_RETURN_URL'			=> 'https://test-otc-api.beingfi.com', //支付回调地址
    'SESSION_AUTO_START'        => true,// 确保没有禁用 session
	);
?>