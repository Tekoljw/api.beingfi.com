<?php

if (!function_exists('array_column')) {
	function array_column(array $input, $columnKey, $indexKey = NULL)
	{
		$result = array();

		if (NULL === $indexKey) {
			if (NULL === $columnKey) {
				$result = array_values($input);
			} else {
				foreach ($input as $row) {
					$result[] = $row[$columnKey];
				}
			}
		} else if (NULL === $columnKey) {
			foreach ($input as $row) {
				$result[$row[$indexKey]] = $row;
			}
		} else {
			foreach ($input as $row) {
				$result[$row[$indexKey]] = $row[$columnKey];
			}
		}

		return $result;
	}
}


function getbgqrcode($imageDefault,$textDefault,$background,$filename="",$config=array())
{
	//如果要看报什么错，可以先注释调这个header
	if(empty($filename)) header("content-type: image/png");
	//背景方法
	$backgroundInfo = getimagesize($background);
	$ext = image_type_to_extension($backgroundInfo[2], false);
	$backgroundFun = 'imagecreatefrom'.$ext;
	$background = $backgroundFun($background);
	$backgroundWidth = imagesx($background);  //背景宽度
	$backgroundHeight = imagesy($background);  //背景高度
	$imageRes = imageCreatetruecolor($backgroundWidth,$backgroundHeight);
	$color = imagecolorallocate($imageRes, 0, 0, 0);
	imagefill($imageRes, 0, 0, $color);
	imagecopyresampled($imageRes,$background,0,0,0,0,imagesx($background),imagesy($background),imagesx($background),imagesy($background));
    //处理了图片
    if(!empty($config['image'])){
        foreach ($config['image'] as $key => $val) {
            $val = array_merge($imageDefault,$val);
            $info = getimagesize($val['url']);
            $function = 'imagecreatefrom'.image_type_to_extension($info[2], false);
            if($val['stream']){   
                //如果传的是字符串图像流
                $info = getimagesizefromstring($val['url']);
                $function = 'imagecreatefromstring';
            }
            $res = $function($val['url']);
            $resWidth = $info[0];
            $resHeight = $info[1];
            //建立画板 ，缩放图片至指定尺寸
            $canvas=imagecreatetruecolor($val['width'], $val['height']);
            imagefill($canvas, 0, 0, $color);
            //关键函数，参数（目标资源，源，目标资源的开始坐标x,y, 源资源的开始坐标x,y,目标资源的宽高w,h,源资源的宽高w,h）
            imagecopyresampled($canvas, $res, 0, 0, 0, 0, $val['width'], $val['height'],$resWidth,$resHeight);
            $val['left'] = $val['left']<0?$backgroundWidth- abs($val['left']) - $val['width']:$val['left'];
            $val['top'] = $val['top']<0?$backgroundHeight- abs($val['top']) - $val['height']:$val['top'];
            //放置图像
            imagecopymerge($imageRes,$canvas, $val['left'],$val['top'],$val['right'],$val['bottom'],$val['width'],$val['height'],$val['opacity']);//左，上，右，下，宽度，高度，透明度
        }
    }
    //处理文字
    if(!empty($config['text'])){
        foreach ($config['text'] as $key => $val) {
            $val = array_merge($textDefault,$val);
            list($R,$G,$B) = explode(',', $val['fontColor']);
            $fontColor = imagecolorallocate($imageRes, $R, $G, $B);
            $val['left'] = $val['left']<0?$backgroundWidth- abs($val['left']):$val['left'];
            $val['top'] = $val['top']<0?$backgroundHeight- abs($val['top']):$val['top'];
            imagettftext($imageRes,$val['fontSize'],$val['angle'],$val['left'],$val['top'],$fontColor,$val['fontPath'],$val['text']);
        }
    }
    //生成图片
    if(!empty($filename)){
        $res = imagejpeg ($imageRes,$filename,90); 
        //保存到本地
        imagedestroy($imageRes);
    }else{
        imagejpeg ($imageRes);     
        //在浏览器上显示
        imagedestroy($imageRes);
    }
}


/*
 * 获取当前页面位置
 *
 * @access  public
 * @param   $cid     int     当前页面栏目id
 * @param   $id      int     当前页面文章id
 * @param   $sign    string  栏目之间分隔符
 * @return           string
 */
function GetPosStr($cid=0,$id=0,$sign='&nbsp;&gt;&nbsp;')
{
	//设置首页链接
	$pos_str = "<li><a href=".U('/Support').">".L('帮助中心')."</a></li>";

	//如果cid为空，获取串，否则视为首页
	if(!empty($cid))
	{
		//获取当前栏目信息
		$r = M('article_type')->where(array('id' => $cid))->find();
		if (empty($r['id'])) {
			return $pos_str."<li><span>&gt;</span>".L('栏目不存在')."</li>";
		} else {
			//构成上级栏目字符
			if ($r['pid'] != '0') {
				$cvv = M('article_type')->where(array('id' => $cid))->find();
				if ($cvv['pid'] > 0) {
					$r = M('article_type')->where(array('id' => $cvv['pid']))->find();
					$pos_str .= "<li><span>&gt;</span><a href=".U('Support/index/categories/cid/'.$cvv['pid']).">".$r['title']."</a></li>";
				}
			}

			//构成本级栏目字符
			$r = M('article_type')->where(array('id' => $cid))->find();
			if (isset($r) && is_array($r)) {
				if (!empty($id)) {
					return $pos_str."<li><span>&gt;</span><a href=".U('Support/index/sections/cid/'.$r['pid'].'/id/'.$r['id']).">".$r['title']."</a></li><li><span>&gt;</span>正文</li>";
				} else {
					return $pos_str."<li><span>&gt;</span>".$r['title']."</li>";
				}
			} else {
				return $pos_str."<li><span>&gt;</span>".L('栏目不存在')."</li>";
			}
		}
	} else {
		return $pos_str;
	}
}

/**
 * 权重
 * @param $array
 * @return array
 */
function getWeight($proArr) {
    $result = array();
    foreach ($proArr as $key => $val) {
        if($val['weight'] > 0){
            $arr[$key] = $val['weight'];
        }
    }
    // 概率数组的总概率
    $proSum = array_sum($arr);
    asort($arr);
    $randNum = mt_rand(1, $proSum);
    $curNum = 0;
    // 概率数组循环
    foreach ($arr as $k => $v) {
        $curNum += $v;
        if ($randNum <= $curNum) {
            $result = $proArr[$k];
            break;
        }
    }
    return $result;
}

//获取一个随机字符串
function get_random_str($len = 32)
{
    $chars = array(
        "a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k",
        "l", "m", "n", "o", "p", "q", "r", "s", "t", "u", "v",
        "w", "x", "y", "z", "A", "B", "C", "D", "E", "F", "G",
        "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R",
        "S", "T", "U", "V", "W", "X", "Y", "Z", "0", "1", "2",
        "3", "4", "5", "6", "7", "8", "9"
    );
    $charsLen = count($chars) - 1;
    // 将数组打乱
    shuffle($chars);
    $output = "";
    for ($i = 0; $i < $len; $i++) {
        $output .= $chars[mt_rand(0, $charsLen)];
    }
    return $output;
}

function ReStrLen($str, $len=10, $etc='...')
{
	$restr = '';
	$i = 0;
	$n = 0.0;

	//字符串的字节数
	$strlen = strlen($str);
	while(($n < $len) and ($i < $strlen))
	{
	   $temp_str = substr($str, $i, 1);

	   //得到字符串中第$i位字符的ASCII码
	   $ascnum = ord($temp_str);

	   //如果ASCII位高与252
	   if($ascnum >= 252)
	   {
			//根据UTF-8编码规范，将6个连续的字符计为单个字符
			$restr = $restr.substr($str, $i, 6);
			//实际Byte计为6
			$i = $i + 6;
			//字串长度计1
			$n++;
	   } else if($ascnum >= 248) {
			$restr = $restr.substr($str, $i, 5);
			$i = $i + 5;
			$n++;
	   } else if($ascnum >= 240) {
			$restr = $restr.substr($str, $i, 4);
			$i = $i + 4;
			$n++;
	   } else if($ascnum >= 224) {
			$restr = $restr.substr($str, $i, 3);
			$i = $i + 3 ;
			$n++;
	   } else if ($ascnum >= 192) {
			$restr = $restr.substr($str, $i, 2);
			$i = $i + 2;
			$n++;
	   } else if($ascnum>=65 and $ascnum<=90 and $ascnum!=73) { //如果是大写字母 I除外
			$restr = $restr.substr($str, $i, 1);
			//实际的Byte数仍计1个
			$i = $i + 1;
			//但考虑整体美观，大写字母计成一个高位字符
			$n++;
	   } else if(!(array_search($ascnum, array(37, 38, 64, 109 ,119)) === FALSE)) { //%,&,@,m,w 字符按1个字符宽
			$restr = $restr.substr($str, $i, 1);
			//实际的Byte数仍计1个
			$i = $i + 1;
			//但考虑整体美观，这些字条计成一个高位字符
			$n++;
	   } else { //其他情况下，包括小写字母和半角标点符号
			$restr = $restr.substr($str, $i, 1);
			//实际的Byte数计1个
			$i = $i + 1;
			//其余的小写字母和半角标点等与半个高位字符宽
			$n = $n + 0.5;
	   }
	}

	//超过长度时在尾处加上省略号
	if($i < $strlen)
	{
	   $restr = $restr.$etc;
	}

	return $restr;
}

function DeleteHtml($str) 
{ 
    $str = trim($str); //清除字符串两边的空格
    $str = preg_replace("/\t/","",$str); //使用正则表达式替换内容，如：空格，换行，并将替换为空。
    $str = preg_replace("/\r\n/","",$str); 
    $str = preg_replace("/\r/","",$str); 
    $str = preg_replace("/\n/","",$str); 
    $str = preg_replace("/ /","",$str);
    $str = preg_replace("/  /","",$str);  //匹配html中的空格
    return trim($str); //返回字符串
}


function getCoreConfig()
{
	$file_path = DATABASE_PATH . '/core.json';

	if (file_exists($file_path)) {
		$CoreConfig = preg_replace("/\/\*[\s\S]+?\*\//", "", file_get_contents($file_path));
		$CoreConfig = json_decode($CoreConfig, true);
/*		$SystemQQ = "000000";
		if($CoreConfig['authQQ']==$SystemQQ){
			return $CoreConfig;
		}else{
			return false;
		}*/
		return $CoreConfig;
	} else {
		return false;
	}
}

function Ethcommon($host, $port)
{
	return new \Common\Ext\EthCommon($host, $port);
}

/*
* 资金变更日志（前后台都含有）
* 币种类型 opstype($number,1)
* 动作类型 opstype($number,2)
* 动作类型数组 opstype($number,88)
*/
function opstype($number,$type)
{
	$coin_array = array(
		1 => '人民币',
		2 => '汇云品种',
		3 => '其他',
	);

	$ops_array = array(
		0 => '其他',
		1 => 'c2c买入申请(可用)',
		2 => 'c2c买入撤销(可用)',
		3 => '变更',
		4 => 'c2c卖出申请(可用)',
		5 => 'c2c卖出撤销(可用)',
		6 => '转出申请',
		7 => '转入',
		8 => '会员互转(转出)',
		9 => '会员互转(转入)',
		10 => '买入(可用)',
		11 => '卖出(可用)',
		12 => '买入差价(可用)',
		13 => '买入(冻结)',
		14 => '卖出(冻结)',
		15 => '清理(冻结)',
		16 => '撤销买入(可用)',
		17 => '撤销卖出(可用)',
		18 => '委托买入(可用)',
		19 => '委托卖出(可用)',
		20 => '委托买入(冻结)',
		21 => '委托卖出(冻结)',
		22 => '买入差价(冻结)',
		23 => '清理(可用)',
		24 => '未知',
		25 => '撤销买入(冻结)',
		26 => '撤销卖出(冻结)',
		27 => '注册赠送',
		28 => '充值赠送',
		29 => '佣金释放',
		30 => '投票',
		31 => '认购',
		32 => 'C2C惩罚',
		33 => 'C2C取消并惩罚',
	);

	if ($type == 1) {
		return $coin_array[$number];
	} else if ($type == 2) {
		return $ops_array[$number];
	} else if ($type == 88) {
		return $ops_array;
	}
}

//获取支付类型的名字
function getPayTypeName($paytype){
	if(is_numeric($paytype) && $paytype > 0){
		$payType = $paytype - 1;
		if(C('PAYTYPES')[$payType]){
			return C('PAYTYPES')[$payType]['name'];
		}else{
			return '未知类型';
		}
	}
	return '未知类型';
}

//获取支付渠道的名字
function getPayChannleTitle($channelid){
	$channel_title = M('paytype_config')->where(['channelid'=>$channelid])->getField('channel_title');
	return $channel_title?$channel_title:'未知类型';
}

//获取支付账户的账户类型名称
function getPayTypeTitle($channelid){
	$paytype = M('paytype_config')->where(['channelid'=>$channelid])->getField('paytype');
	return getPayTypeName($paytype);
}

//获取模糊用户名
function getFuzzyUserName($userid){
	$username = M('User')->where(array('id' => $userid))->getField('username');
	if(strlen($username) > 8){
		$str1 = substr($username, 0, 3);
		$str2 = substr($username, -3);
		$username = $str1 . '****' . $str2;
	}elseif(strlen($username) > 4){
		$str1 = substr($username, 0, 2);
		$str2 = substr($username, -3);
		$username = $str1 . '****' . $str2;
	}else{
		$username = '错误的用户名';
	}
	return $username?$username:'未知用户';
}

//判断是否是自动C2C订单
function isAutoC2COrder($orderInfo){
	if($orderInfo && isset($orderInfo['notifyurl']) &&!is_null($orderInfo['notifyurl']) && $orderInfo['notifyurl'] != ''){
		return true;
	}
	return false;
}

//是否是买方操作C2C订单
function isBuyUserOpreate($orderInfo, $op_userid){
	if($orderInfo && isset($orderInfo['otype'])){
		if($orderInfo['otype'] == 1 && $orderInfo['userid'] == $op_userid){
	        return true;   
	    }elseif($orderInfo['otype'] == 2 && $orderInfo['aid'] == $op_userid){
	        return true;
	    }
	}
    return false;
}

//获取优惠扣除等级
function getDelayTimePunishmentLevel($start_time, $end_time){

	if($start_time && $start_time > 0 && $end_time && $end_time > 0){
		if($start_time < $end_time - 30*60){  // 超过30分钟获得50%
            return 3;
        }elseif($start_time < $end_time - 10*60){ // 超过10分钟获得0%
            return 2;
        }elseif($start_time < $end_time - 5*60){ // 超过5分钟获得0%
            return 1;
        }else{
            return 0;
        }
	}
	return 0;
}

function clear_html($str)
{
	$str = preg_replace("/<style .*?<\/style>/is", "", $str);
	$str = preg_replace("/<script .*?<\/script>/is", "", $str);
	$str = preg_replace("/<br \s*\/?\/>/i", "\n", $str);
	$str = preg_replace("/<\/?p>/i", "\n\n", $str);
	$str = preg_replace("/<\/?td>/i", "\n", $str);
	$str = preg_replace("/<\/?div>/i", "\n", $str);
	$str = preg_replace("/<\/?blockquote>/i", "\n", $str);
	$str = preg_replace("/<\/?li>/i", "\n", $str);
	$str = preg_replace("/\&nbsp\;/i", " ", $str);
	$str = preg_replace("/\&nbsp/i", " ", $str);
	$str = preg_replace("/\&amp\;/i", "&", $str);
	$str = preg_replace("/\&amp/i", "&", $str);
	$str = preg_replace("/\&lt\;/i", "<", $str);
	$str = preg_replace("/\&lt/i", "<", $str);
	$str = preg_replace("/\&ldquo\;/i", '"', $str);
	$str = preg_replace("/\&ldquo/i", '"', $str);
	$str = preg_replace("/\&lsquo\;/i", "'", $str);
	$str = preg_replace("/\&lsquo/i", "'", $str);
	$str = preg_replace("/\&rsquo\;/i", "'", $str);
	$str = preg_replace("/\&rsquo/i", "'", $str);
	$str = preg_replace("/\&gt\;/i", ">", $str);
	$str = preg_replace("/\&gt/i", ">", $str);
	$str = preg_replace("/\&rdquo\;/i", '"', $str);
	$str = preg_replace("/\&rdquo/i", '"', $str);
	$str = strip_tags($str);
	$str = html_entity_decode($str, ENT_QUOTES);
	$str = preg_replace("/\&\#.*?\;/i", "", $str);
	return $str;
}

function authgame($name)
{
	if (!check($name, 'w')) {
		return 0;
		exit();
	}

	if (M('VersionGame')->where(array('name' => $name, 'status' => 1))->find()) {
		return 1;
	} else {
		return 0;
		exit();
	}
}

function getUrl($url)
{
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HEADER, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_TIMEOUT, 3);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, '');
	$data = curl_exec($ch);
	return $data;
}

function huafei($mobile = NULL, $num = NULL, $orderid = NULL)
{
	if (empty($mobile)) {
		return NULL;
	}
	if (empty($num)) {
		return NULL;
	}
	if (empty($orderid)) {
		return NULL;
	}

	header('Content-type:text/html;charset=utf-8');
	$appkey = C('huafei_appkey');
	$openid = C('huafei_openid');
	$recharge = new \Common\Ext\Recharge($appkey, $openid);
	$telRechargeRes = $recharge->telcz($mobile, $num, $orderid);

	if ($telRechargeRes['error_code'] == '0') {
		return 1;
	} else {
		return NULL;
	}
}

function mlog($text)
{
	$text = addtime(time()) . ' ' . $text . "\n";
	file_put_contents('./sitetrade.log', $text, FILE_APPEND);
	// var_dump($text);
}

function logeth($text)
{
	$text = addtime(time()) . ' ' . $text . "\n";
	file_put_contents('./eth97f44b134e.log', $text, FILE_APPEND);
	// var_dump($text);
}

function authUrl($url)
{
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HEADER, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_TIMEOUT, 3);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, '');
	$data = curl_exec($ch);
	return $data;
}

function userid($username = NULL, $type = 'username')
{
	if ($username && $type) {
		$userid = (APP_DEBUG ? NULL : S('userid' . $username . $type));

		if (!$userid) {
			$userid = M('User')->where(array($type => $username))->getField('id');
			S('userid' . $username . $type, $userid);
		}
	} else {
		$userid = session('userId');
	}

	return $userid ? $userid : NULL;
}

function username($id = NULL, $type = 'id')
{
	if ($id && $type) {
		$username = (APP_DEBUG ? NULL : S('username' . $id . $type));

		if (!$username) {
			$username = M('User')->where(array($type => $id))->getField('username');
			S('username' . $id . $type, $username);
		}
	} else {
		$username = session('userName');
	}

	return $username ? $username : NULL;
}


function op_t($text, $addslanshes = false)
{
	$text = nl2br($text);
	$text = real_strip_tags($text);

	if ($addslanshes) {
		$text = addslashes($text);
	}

	$text = trim($text);
	return $text;
}

function text($text, $addslanshes = false)
{
	return op_t($text, $addslanshes);
}

function html($text)
{
	return op_h($text);
}

function op_h($text, $type = 'html')
{
	$text_tags = '';
	$link_tags = '<a>';
	$image_tags = '<img>';
	$font_tags = '<i><b><u><s><em><strong><font><big><small><sup><sub><bdo><h1><h2><h3><h4><h5><h6>';
	$base_tags = $font_tags . '<p><br><hr><a><img><map><area><pre><code><q><blockquote><acronym><cite><ins><del><center><strike>';
	$form_tags = $base_tags . '<form><input><textarea><button><select><optgroup><option><label><fieldset><legend>';
	$html_tags = $base_tags . '<ul><ol><li><dl><dd><dt><table><caption><td><th><tr><thead><tbody><tfoot><col><colgroup><div><span><object><embed><param>';
	$all_tags = $form_tags . $html_tags . '<!DOCTYPE><meta><html><head><title><body><base><basefont><script><noscript><applet><object><param><style><frame><frameset><noframes><iframe>';
	$text = real_strip_tags($text, $$type . '_tags');

	if ($type != 'all') {
		while (preg_match('/(<[^><]+)(ondblclick|onclick|onload|onerror|unload|onmouseover|onmouseup|onmouseout|onmousedown|onkeydown|onkeypress|onkeyup|onblur|onchange|onfocus|action|background[^-]|codebase|dynsrc|lowsrc)([^><]*)/i', $text, $mat)) {
			$text = str_ireplace($mat[0], $mat[1] . $mat[3], $text);
		}

		while (preg_match('/(<[^><]+)(window\\.|javascript:|js:|about:|file:|document\\.|vbs:|cookie)([^><]*)/i', $text, $mat)) {
			$text = str_ireplace($mat[0], $mat[1] . $mat[3], $text);
		}
	}

	return $text;
}

function real_strip_tags($str, $allowable_tags = '')
{
	return strip_tags($str, $allowable_tags);
}

function clean_cache($dirname = './Runtime/')
{
	$dirs = array($dirname);
	foreach ($dirs as $value) {
		rmdirr($value);
	}

	@(mkdir($dirname, 511, true));
}

function getSubByKey($pArray, $pKey = '', $pCondition = '')
{
	$result = array();

	if (is_array($pArray)) {
		foreach ($pArray as $temp_array) {
			if (is_object($temp_array)) {
				$temp_array = (array) $temp_array;
			}
			if ((('' != $pCondition) && ($temp_array[$pCondition[0]] == $pCondition[1])) || ('' == $pCondition)) {
				$result[] = ('' == $pKey ? $temp_array : isset($temp_array[$pKey]) ? $temp_array[$pKey] : '');
			}
		}

		return $result;
	} else {
		return false;
	}
}

function debug($value, $type = 'DEBUG', $verbose = false, $encoding = 'UTF-8')
{
	// if (false) {
	// 	if (!IS_CLI) {
	// 		Common\Ext\FirePHP::getInstance(true)->log($value, $type);
	// 	}
	// }
}

function CoinClient($username, $password, $ip, $port, $timeout = 3, $headers = array(), $suppress_errors = false)
{
	return new \Common\Ext\CoinClient($username, $password, $ip, $port, $timeout, $headers, $suppress_errors);
}

function EthClient($username, $password, $ip, $port, $timeout = 3, $headers = array(), $suppress_errors = false)
{
	return new \Common\Ext\EthCommon($username, $password, $ip, $port, $timeout, $headers, $suppress_errors);
}

function coinname($type){
	if( !S('COINNAME') ){
		$coin_list = D('Coin')->get_all_name_list();
		S('COINNAME',$coin_list,3600);
	}
	$coinname = S('COINNAME');
	return $coinname[$type];
}

function createQRcode($save_path, $qr_data = 'PHP QR Code :)', $qr_level = 'L', $qr_size = 4, $save_prefix = 'qrcode')
{
	if (!isset($save_path)) {
		return '';
	}

	$PNG_TEMP_DIR = &$save_path;
	vendor('PHPQRcode.class#phpqrcode');

	if (!file_exists($PNG_TEMP_DIR)) {
		mkdir($PNG_TEMP_DIR);
	}

	$filename = $PNG_TEMP_DIR . 'test.png';
	$errorCorrectionLevel = 'L';

	if (isset($qr_level) && in_array($qr_level, array('L', 'M', 'Q', 'H'))) {
		$errorCorrectionLevel = &$qr_level;
	}

	$matrixPointSize = 4;

	if (isset($qr_size)) {
		$matrixPointSize = &min(max((int) $qr_size, 1), 10);
	}

	if (isset($qr_data)) {
		if (trim($qr_data) == '') {
			exit('data cannot be empty!');
		}

		$filename = $PNG_TEMP_DIR . $save_prefix . md5($qr_data . '|' . $errorCorrectionLevel . '|' . $matrixPointSize) . '.png';
		QRcode::png($qr_data, $filename, $errorCorrectionLevel, $matrixPointSize, 2, true);
	} else {
		QRcode::png('PHP QR Code :)', $filename, $errorCorrectionLevel, $matrixPointSize, 2, true);
	}

	if (file_exists($PNG_TEMP_DIR . basename($filename))) {
		return basename($filename);
	} else {
		return false;
	}
}

/**
 * @param $num 科学计数法字符串  如 2.1E-5
 * @param int $double 小数点保留位数 默认5位
 * @return string
 */
function sctonum($num, $double = 6){
    if(false !== stripos($num, "e")){
        $a = explode("e",strtolower($num));
        return bcmul($a[0], bcpow(10, $a[1], $double), $double);
	}
}

//出现科学计数法，还原成字符串
function NumToStr($num){
/*    if (stripos($num,'e')===false) return $num;
    $num = trim(preg_replace('/[=\'"]/','',$num,1),'"');
    $result = "";
    while ($num > 0){
        $v = $num - floor($num / 10)*10;
        $num = floor($num / 10);
        $result   =   $v . $result;
    }
	return $result;*/
	
	$parts = explode('E', $num);
	if(count($parts) != 2){ return $num; }
	$exp = abs(end($parts)) + 3;
	$decimal = number_format($num, $exp);
	$decimal = rtrim($decimal, '0');
	
	return rtrim($decimal, '.');
}

function NumToStrold($num)
{
	if (!$num) { return $num; }
	if ($num == 0) { return 0; }

	$num = round($num, 8);
	$min = 0.0001;

	if ($num <= $min) {
		$times = 0;

		while ($num <= $min) {
			$num *= 10;
			$times++;

			if (10 < $times) {
				break;
			}
		}

		$arr = explode('.', $num);
		$arr[1] = str_repeat('0', $times) . $arr[1];
		return $arr[0] . '.' . $arr[1] . '';
	}

	return ($num * 1) . '';
}

function Num($num)
{
	if (!$num) { return $num; }
	if ($num == 0) { return 0; }

	$num = round($num, 8);
	$min = 0.0001;

	if ($num <= $min) {
		$times = 0;

		while ($num <= $min) {
			$num *= 10;
			$times++;

			if (10 < $times) {
				break;
			}
		}

		$arr = explode('.', $num);
		$arr[1] = str_repeat('0', $times) . $arr[1];
		return $arr[0] . '.' . $arr[1] . '';
	}

	return ($num * 1) . '';
}

function check_verify($code, $id = ".cn")
{
	$verify = new \Think\Verify();
	return $verify->check($code, $id);
}

function get_city_ip($ip = NULL)
{
	if (empty($ip)) {
		$ip = get_client_ip();
	}

	$Ip = new Org\Net\IpLocation();
	$area = $Ip->getlocation($ip);
	$str = $area['country'] . $area['area'];
	$str = mb_convert_encoding($str, 'UTF-8', 'GBK');

	if (($ip == '127.0.0.1') || ($str == false) || ($str == 'IANA保留地址用于本地回送')) {
		$str = '未分配或者内网IP';
	}

	return $str;
}

function send_post($url, $post_data)
{
	$postdata = http_build_query($post_data);
	$options = array(
		'http' => array('method' => 'POST', 'header' => 'Content-type:application/x-www-form-urlencoded', 'content' => $postdata, 'timeout' => 15 * 60)
	);
	$context = stream_context_create($options);
	$result = file_get_contents($url, false, $context);
	return $result;
}

function request_by_curl($remote_server, $post_string)
{
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $remote_server);
	curl_setopt($ch, CURLOPT_POSTFIELDS, 'mypost=' . $post_string);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_USERAGENT, 'qianyunlai.com\'s CURL Example beta');
	$data = curl_exec($ch);
	curl_close($ch);
	return $data;
}

function tradeno()
{
	return substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNPQRSTUVWXYZ'), 0, 2) . substr(str_shuffle(str_repeat('123456789', 4)), 0, 9);
}

function tradenoa()
{
	return substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNPQRSTUVWXYZ'), 0, 9);
}

function tradenob()
{
	return substr(str_shuffle(str_repeat('123456789', 4)), 0, 2);
}

function get_user($id, $type = NULL, $field = 'id')
{
	$key = md5('get_user' . $id . $type . $field);
	$data = S($key);

	if (!$data) {
		$data = M('User')->where(array($field => $id))->find();
		S($key, $data);
	}

	if ($type) {
		$rs = $data[$type];
	} else {
		$rs = $data;
	}

	return $rs;
}

//判断是否是手机端还是电脑端
function isMobile() {
	
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

function send_mobiles($mobile, $content)
{
	debug(array($content, $mobile), 'send_mobile');
	$url = C('mobile_url') . '/?Uid=' . C('mobile_user') . '&Key=' . C('mobile_pwd') . '&smsMob=' . $mobile . '&smsText=' . $content;

	if (function_exists('file_get_contents')) {
		$file_contents = file_get_contents($url);
	}
	else {
		$ch = curl_init();
		$timeout = 5;
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
		$file_contents = curl_exec($ch);
		curl_close($ch);
	}

	return $file_contents;
}


function addtime($time = NULL, $type = NULL)
{
	if (empty($time)) {
		return '---';
	}

	if (($time < 2545545) && (1893430861 < $time)) {
		return '---';
	}

	if (empty($type)) {
		$type = 'Y-m-d H:i:s';
	}

	return date($type, $time);
}

function check($data, $rule = NULL, $ext = NULL)
{
	$data = trim(str_replace(PHP_EOL, '', $data));

	if (empty($data)) {
		return false;
	}

	$validate['require'] = '/.+/';
	$validate['url'] = '/^http(s?):\\/\\/(?:[A-za-z0-9-]+\\.)+[A-za-z]{2,4}(?:[\\/\\?#][\\/=\\?%\\-&~`@[\\]\':+!\\.#\\w]*)?$/';
	$validate['currency'] = '/^\\d+(\\.\\d+)?$/';
	$validate['number'] = '/^\\d+$/';
	$validate['zip'] = '/^\\d{6}$/';
	$validate['cny'] = '/^(([1-9]{1}\\d*)|([0]{1}))(\\.(\\d){1,2})?$/';
	$validate['integer'] = '/^[\\+]?\\d+$/';
	$validate['double'] = '/^[\\+]?\\d+(\\.\\d+)?$/';
	$validate['english'] = '/^[A-Za-z]+$/';
	$validate['idcard'] = '/^([0-9]{15}|[0-9]{17}[0-9a-zA-Z])$/';
	$validate['truename'] = '/^[\\x{4e00}-\\x{9fa5}]{2,4}$/u';
	$validate['username'] = '/^[a-zA-Z]{1}[0-9a-zA-Z_]{5,15}$/';
	$validate['email'] = '/^\\w+([-+.]\\w+)*@\\w+([-.]\\w+)*\\.\\w+([-.]\\w+)*$/';
	$validate['mobile'] = '/^(([0-9]{8})+\\d{1,3})$/';
	$validate['password'] = '/^[a-zA-Z0-9_\\@\\#\\$\\%\\^\\&\\*\\(\\)\\!\\,\\.\\?\\-\\+\\|\\=]{6,16}$/';
	// $validate['password'] = '/^[a-zA-Z0-9]{6,16}$/';
	$validate['xnb'] = '/^[a-zA-Z]$/';
	$validate['business_license_id'] = '/^([0-9a-zA-Z]{15}|[0-9a-zA-Z]{18})$/'; //营业执照ID判断

	if (isset($validate[strtolower($rule)])) {
		$rule = $validate[strtolower($rule)];
		return preg_match($rule, $data);
	}

	$Ap = '\\x{4e00}-\\x{9fff}' . '0-9a-zA-Z\\@\\#\\$\\%\\^\\&\\*\\(\\)\\!\\,\\.\\?\\-\\+\\|\\=';
	$Cp = '\\x{4e00}-\\x{9fff}';
	$Dp = '0-9';
	$Wp = 'a-zA-Z';
	$Np = 'a-z';
	$Tp = '@#$%^&*()-+=';
	$_p = '_';
	$pattern = '/^[';
	$OArr = str_split(strtolower($rule));
	in_array('a', $OArr) && ($pattern .= $Ap);
	in_array('c', $OArr) && ($pattern .= $Cp);
	in_array('d', $OArr) && ($pattern .= $Dp);
	in_array('w', $OArr) && ($pattern .= $Wp);
	in_array('n', $OArr) && ($pattern .= $Np);
	in_array('t', $OArr) && ($pattern .= $Tp);
	in_array('_', $OArr) && ($pattern .= $_p);
	isset($ext) && ($pattern .= $ext);
	$pattern .= ']+$/u';
	return preg_match($pattern, $data);
}

function check_arr($rs)
{
	foreach ($rs as $v) {
		if (!$v) {
			return false;
		}
	}

	return true;
}

function maxArrayKey($arr, $key)
{
	$a = 0;

	foreach ($arr as $k => $v) {
		$a = max($v[$key], $a);
	}

	return $a;
}

function arr2str($arr, $sep = ',')
{
	return implode($sep, $arr);
}

function str2arr($str, $sep = ',')
{
	return explode($sep, $str);
}

function url($link = '', $param = '', $default = '')
{
	return $default ? $default : U($link, $param);
}

function rmdirr($dirname)
{
	if (!file_exists($dirname)) {
		return false;
	}

	if (is_file($dirname) || is_link($dirname)) {
		return unlink($dirname);
	}

	$dir = dir($dirname);

	if ($dir) {
		while (false !== $entry = $dir->read()) {
			if (($entry == '.') || ($entry == '..')) {
				continue;
			}

			rmdirr($dirname . DIRECTORY_SEPARATOR . $entry);
		}
	}

	$dir->close();
	return rmdir($dirname);
}

function list_to_tree($list, $pk = 'id', $pid = 'pid', $child = '_child', $root = 0)
{
	$tree = array();
	if (is_array($list)) {
		$refer = array();
		foreach ($list as $key => $data) {
			$refer[$data[$pk]] = &$list[$key];
		}
		foreach ($list as $key => $data) {
			$parentId = $data[$pid];
			if ($root == $parentId) {
				$tree[] = &$list[$key];
			} else if (isset($refer[$parentId])) {
				$parent = &$refer[$parentId];
				$parent[$child][] = &$list[$key];
			}
		}
	}

	return $tree;
}

function tree_to_list($tree, $child = '_child', $order = 'id', &$list = array())
{
	if (is_array($tree)) {
		$refer = array();

		foreach ($tree as $key => $value) {
			$reffer = $value;

			if (isset($reffer[$child])) {
				unset($reffer[$child]);
				tree_to_list($value[$child], $child, $order, $list);
			}

			$list[] = $reffer;
		}

		$list = list_sort_by($list, $order, $sortby = 'asc');
	}

	return $list;
}

function list_sort_by($list, $field, $sortby = 'asc')
{
	if (is_array($list)) {
		$refer = $resultSet = array();

		foreach ($list as $i => $data) {
			$refer[$i] = &$data[$field];
		}

		switch ($sortby) {
		case 'asc':
			asort($refer);
			break;

		case 'desc':
			arsort($refer);
			break;

		case 'nat':
			natcasesort($refer);
		}

		foreach ($refer as $key => $val) {
			$resultSet[] = &$list[$key];
		}

		return $resultSet;
	}

	return false;
}

function list_search($list, $condition)
{
	if (is_string($condition)) {
		parse_str($condition, $condition);
	}

	$resultSet = array();
	foreach ($list as $key => $data) {
		$find = false;
		foreach ($condition as $field => $value) {
			if (isset($data[$field])) {
				if (0 === strpos($value, '/')) {
					$find = preg_match($value, $data[$field]);
				} else if ($data[$field] == $value) {
					$find = true;
				}
			}
		}

		if ($find) {
			$resultSet[] = &$list[$key];
		}
	}

	return $resultSet;
}

function d_f($name, $value, $path = DATA_PATH)
{
	if (APP_MODE == 'sae') {
		return false;
	}

	static $_cache = array();
	$filename = $path . $name . '.php';

	if ('' !== $value) {
		if (is_null($value)) {} 
		else {
			$dir = dirname($filename);

			if (!is_dir($dir)) {
				mkdir($dir, 493, true);
			}

			$_cache[$name] = $value;
			$content = strip_whitespace('<?php' . "\t" . 'return ' . var_export($value, true) . ';?>') . PHP_EOL;
			return file_put_contents($filename, $content, FILE_APPEND);
		}
	}

	if (isset($_cache[$name])) {
		return $_cache[$name];
	}

	if (is_file($filename)) {
		$value = include $filename;
		$_cache[$name] = $value;
	} else {
		$value = false;
	}

	return $value;
}

/**
 * 用户操作日志
 * @param userid            用户ID
 * @param userType          用户类型
 * @param opreateContent    操作内容
 * @param time              操纵时间
 * @param ip                操作IP
 * @return bool
 */
function operation_log($userid,$userType,$opreateContent){

    switch ($userType) {
        case 1: //后台管理员用户
            $username = M("admin")->where(['id' => $userid])->getField('username');
            break;
        case 2: //商户用户
            $username = M("user")->where(['id' => $userid])->getField('username');
            break;
        default:
            $username = '未知用户类型'; 
            break;
    }

    $IpLocation = new \Org\Net\IpLocation('UTFWry.dat'); // 实例化类 参数表示IP地址库文件
    $location   = $IpLocation->getlocation(); // 获取某个IP地址所在的位置
    $Ip         = $location['ip'];
    $country    = $location['country'];
    $area       = $location['area'];

    $rows = [
        'userid'    =>$userid,
        'username'  =>$username,
        'user_type' =>$userType,
        'opreate_content'=> $opreateContent,
        'addtime'   => time(),
        'ip'        => $Ip,
        'country'   => $country,
        'area'      => $area,
    ];
    M('operation_log')->add($rows);
    return true;
}

function DownloadFile($fileName)
{
	ob_end_clean();
	header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
	header('Content-Description: File Transfer');
	header('Content-Type: application/octet-stream');
	header('Content-Length: ' . filesize($fileName));
	header('Content-Disposition: attachment; filename=' . basename($fileName));
	readfile($fileName);
}

function download_file($file, $o_name = '')
{
	if (is_file($file)) {
		$length = filesize($file);
		$type = mime_content_type($file);
		$showname = ltrim(strrchr($file, '/'), '/');

		if ($o_name) {
			$showname = $o_name;
		}

		header('Content-Description: File Transfer');
		header('Content-type: ' . $type);
		header('Content-Length:' . $length);

		if (preg_match('/MSIE/', $_SERVER['HTTP_USER_AGENT'])) {
			header('Content-Disposition: attachment; filename="' . rawurlencode($showname) . '"');
		} else {
			header('Content-Disposition: attachment; filename="' . $showname . '"');
		}

		readfile($file);
		exit();
	} else {
		exit('文件不存在');
	}
}

function wb_substr($str, $len = 140, $dots = 1, $ext = '')
{
	$str = htmlspecialchars_decode(strip_tags(htmlspecialchars($str)));
	$strlenth = 0;
	$output = '';
	preg_match_all('/[' . "\x1" . '-]|[' . "\xc2" . '-' . "\xdf" . '][' . "\x80" . '-' . "\xbf" . ']|[' . "\xe0" . '-' . "\xef" . '][' . "\x80" . '-' . "\xbf" . ']{2}|[' . "\xf0" . '-' . "\xff" . '][' . "\x80" . '-' . "\xbf" . ']{3}/', $str, $match);

	foreach ($match[0] as $v) {
		preg_match('/[' . "\xe0" . '-' . "\xef" . '][' . "\x80" . '-' . "\xbf" . ']{2}/', $v, $matchs);

		if (!empty($matchs[0])) {
			$strlenth += 1;
		} else if (is_numeric($v)) {
			$strlenth += 0.54500000000000004;
		} else {
			$strlenth += 0.47499999999999998;
		}

		if ($len < $strlenth) {
			$output .= $ext;
			break;
		}

		$output .= $v;
	}

	if (($len < $strlenth) && $dots) {
		$output .= '...';
	}

	return $output;
}

function msubstr($str, $start = 0, $length, $charset = 'utf-8', $suffix = true)
{
    $str=strip_tags($str);
	if (function_exists('mb_substr')) {
		$slice = mb_substr($str, $start, $length, $charset);
	} else if (function_exists('iconv_substr')) {
		$slice = iconv_substr($str, $start, $length, $charset);

		if (false === $slice) {
			$slice = '';
		}
	} else {
		$re['utf-8'] = '/[' . "\x1" . '-]|[' . "\xc2" . '-' . "\xdf" . '][' . "\x80" . '-' . "\xbf" . ']|[' . "\xe0" . '-' . "\xef" . '][' . "\x80" . '-' . "\xbf" . ']{2}|[' . "\xf0" . '-' . "\xff" . '][' . "\x80" . '-' . "\xbf" . ']{3}/';
		$re['gb2312'] = '/[' . "\x1" . '-]|[' . "\xb0" . '-' . "\xf7" . '][' . "\xa0" . '-' . "\xfe" . ']/';
		$re['gbk'] = '/[' . "\x1" . '-]|[' . "\x81" . '-' . "\xfe" . '][@-' . "\xfe" . ']/';
		$re['big5'] = '/[' . "\x1" . '-]|[' . "\x81" . '-' . "\xfe" . ']([@-~]|' . "\xa1" . '-' . "\xfe" . '])/';
		preg_match_all($re[$charset], $str, $match);
		$slice = join('', array_slice($match[0], $start, $length));
	}

	return $suffix ? $slice . '...' : $slice;
}

function highlight_map($str, $keyword)
{
	return str_replace($keyword, '<em class=\'keywords\'>' . $keyword . '</em>', $str);
}

function del_file($file)
{
	$file = file_iconv($file);
	@(unlink($file));
}

function status_text($model, $key)
{
	if ($model == 'Nav') {
		$text = array('无效', '有效');
	}

	return $text[$key];
}

function user_auth_sign($user)
{
	ksort($user);
	$code = http_build_query($user);
	$sign = sha1($code);
	return $sign;
}

function get_link($link_id = NULL, $field = 'url')
{
	$link = '';

	if (empty($link_id)) {
		return $link;
	}

	$link = D('Url')->getById($link_id);

	if (empty($field)) {
		return $link;
	} else {
		return $link[$field];
	}
}

function get_cover($cover_id, $field = NULL)
{
	if (empty($cover_id)) {
		return false;
	}

	$picture = D('Picture')->where(array('status' => 1))->getById($cover_id);

	if ($field == 'path') {
		if (!empty($picture['url'])) {
			$picture['path'] = $picture['url'];
		} else {
			$picture['path'] = __ROOT__ . $picture['path'];
		}
	}

	return empty($field) ? $picture : $picture[$field];
}

function get_admin_name()
{
	$user = session(C('USER_AUTH_KEY'));
	return $user['admin_name'];
}

function is_login()
{
	$user = session(C('USER_AUTH_KEY'));

	if (empty($user)) {
		return 0;
	} else {
		return session(C('USER_AUTH_SIGN_KEY')) == user_auth_sign($user) ? $user['admin_id'] : 0;
	}
}

function is_administrator($uid = NULL)
{
	$uid = (is_null($uid) ? is_login() : $uid);
	return $uid && (intval($uid) === C('USER_ADMINISTRATOR'));
}

function show_tree($tree, $template)
{
	$view = new View();
	$view->assign('tree', $tree);
	return $view->fetch($template);
}

function int_to_string(&$data, $map = array(
	'status' => array(1 => '正常', -1 => '删除', 0 => '禁用', 2 => '未审核', 3 => '草稿')
	))
{
	if (($data === false) || ($data === NULL)) {
		return $data;
	}

	$data = (array) $data;

	foreach ($data as $key => $row) {
		foreach ($map as $col => $pair) {
			if (isset($row[$col]) && isset($pair[$row[$col]])) {
				$data[$key][$col . '_text'] = $pair[$row[$col]];
			}
		}
	}

	return $data;
}

function hook($hook, $params = array())
{
	return \Think\Hook::listen($hook, $params);
}

function get_addon_class($name)
{
	$type = (strpos($name, '_') !== false ? 'lower' : 'upper');

	if ('upper' == $type) {
		$dir = \Think\Loader::parseName(lcfirst($name));
		$name = ucfirst($name);
	} else {
		$dir = $name;
		$name = \Think\Loader::parseName($name, 1);
	}

	$class = 'addons\\' . $dir . '\\' . $name;
	return $class;
}

function get_addon_config($name)
{
	$class = get_addon_class($name);

	if (class_exists($class)) {
		$addon = new $class();
		return $addon->getConfig();
	} else {
		return array();
	}
}

function addons_url($url, $param = array())
{
	$url = parse_url($url);
	$case = C('URL_CASE_INSENSITIVE');
	$addons = ($case ? parse_name($url['scheme']) : $url['scheme']);
	$controller = ($case ? parse_name($url['host']) : $url['host']);
	$action = trim($case ? strtolower($url['path']) : $url['path'], '/');

	if (isset($url['query'])) {
		parse_str($url['query'], $query);
		$param = array_merge($query, $param);
	}

	$params = array('_addons' => $addons, '_controller' => $controller, '_action' => $action);
	$params = array_merge($params, $param);
	return U('Addons/execute', $params);
}

function get_addonlist_field($data, $grid, $addon)
{
	foreach ($grid['field'] as $field) {
		$array = explode('|', $field);
		$temp = $data[$array[0]];

		if (isset($array[1])) {
			$temp = call_user_func($array[1], $temp);
		}

		$data2[$array[0]] = $temp;
	}

	if (!empty($grid['format'])) {
		$value = preg_replace_callback('/\\[([a-z_]+)\\]/', function($match) use($data2) {
			return $data2[$match[1]];
		}, $grid['format']);
	} else {
		$value = implode(' ', $data2);
	}

	if (!empty($grid['href'])) {
		$links = explode(',', $grid['href']);

		foreach ($links as $link) {
			$array = explode('|', $link);
			$href = $array[0];

			if (preg_match('/^\\[([a-z_]+)\\]$/', $href, $matches)) {
				$val[] = $data2[$matches[1]];
			} else {
				$show = (isset($array[1]) ? $array[1] : $value);
				$href = str_replace(array('[DELETE]', '[EDIT]', '[ADDON]'), array('del?ids=[id]&name=[ADDON]', 'edit?id=[id]&name=[ADDON]', $addon), $href);
				$href = preg_replace_callback('/\\[([a-z_]+)\\]/', function($match) use($data) {
					return $data[$match[1]];
				}, $href);
				$val[] = '<a href="' . U($href) . '">' . $show . '</a>';
			}
		}

		$value = implode(' ', $val);
	}

	return $value;
}

function get_config_type($type = 0)
{
	$list = C('CONFIG_TYPE_LIST');
	return $list[$type];
}

function get_config_group($group = 0)
{
	$list = C('CONFIG_GROUP_LIST');
	return $group ? $list[$group] : '';
}

function parse_config_attr($string)
{
	$array = preg_split('/[,;\\r\\n]+/', trim($string, ',;' . "\r\n"));

	if (strpos($string, ':')) {
		$value = array();

		foreach ($array as $val) {
			list($k, $v) = explode(':', $val);
			$value[$k] = $v;
		}
	} else {
		$value = $array;
	}

	return $value;
}

function parse_field_attr($string)
{
	if (0 === strpos($string, ':')) {
		return eval(substr($string, 1) . ';');
	}

	$array = preg_split('/[,;\\r\\n]+/', trim($string, ',;' . "\r\n"));
	if (strpos($string, ':')) {
		$value = array();

		foreach ($array as $val) {
			list($k, $v) = explode(':', $val);
			$value[$k] = $v;
		}
	} else {
		$value = $array;
	}

	return $value;
}

function api($name, $vars = array())
{
	$array = explode('/', $name);
	$method = array_pop($array);
	$classname = array_pop($array);
	$module = ($array ? array_pop($array) : 'Common');
	$callback = $module . '\\Api\\' . $classname . 'Api::' . $method;

	if (is_string($vars)) {
		parse_str($vars, $vars);
	}

	return call_user_func_array($callback, $vars);
}

function think_encrypt($data, $key = '', $expire = 0)
{
	$key = md5(empty($key) ? C('DATA_AUTH_KEY') : $key);
	$data = base64_encode($data);
	$x = 0;
	$len = strlen($data);
	$l = strlen($key);
	$char = '';
	$i = 0;

	for (; $i < $len; $i++) {
		if ($x == $l) {
			$x = 0;
		}

		$char .= substr($key, $x, 1);
		$x++;
	}

	$str = sprintf('%010d', $expire ? $expire + time() : 0);
	$i = 0;

	for (; $i < $len; $i++) {
		$str .= chr(ord(substr($data, $i, 1)) + (ord(substr($char, $i, 1)) % 256));
	}

	return str_replace(array('+', '/', '='), array('-', '_', ''), base64_encode($str));
}

function think_decrypt($data, $key = '')
{
	$key = md5(empty($key) ? C('DATA_AUTH_KEY') : $key);
	$data = str_replace(array('-', '_'), array('+', '/'), $data);
	$mod4 = strlen($data) % 4;

	if ($mod4) {
		$data .= substr('====', $mod4);
	}

	$data = base64_decode($data);
	$expire = substr($data, 0, 10);
	$data = substr($data, 10);

	if ((0 < $expire) && ($expire < time())) {
		return '';
	}

	$x = 0;
	$len = strlen($data);
	$l = strlen($key);
	$char = $str = '';
	$i = 0;

	for (; $i < $len; $i++) {
		if ($x == $l) {
			$x = 0;
		}

		$char .= substr($key, $x, 1);
		$x++;
	}

	$i = 0;

	for (; $i < $len; $i++) {
		if (ord(substr($data, $i, 1)) < ord(substr($char, $i, 1))) {
			$str .= chr((ord(substr($data, $i, 1)) + 256) - ord(substr($char, $i, 1)));
		} else {
			$str .= chr(ord(substr($data, $i, 1)) - ord(substr($char, $i, 1)));
		}
	}

	return base64_decode($str);
}

function data_auth_sign($data)
{
	if (!is_array($data)) {
		$data = (array) $data;
	}

	ksort($data);
	$code = http_build_query($data);
	$sign = sha1($code);
	return $sign;
}

function format_bytes($size, $delimiter = '')
{
	$units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
	$i = 0;

	for (; $i < 5; $i++) {
		$size /= 1024;
	}

	return round($size, 2) . $delimiter . $units[$i];
}

function set_redirect_url($url)
{
	cookie('redirect_url', $url);
}

function get_redirect_url()
{
	$url = cookie('redirect_url');
	return empty($url) ? __APP__ : $url;
}

function time_format($time = NULL, $format = 'Y-m-d H:i')
{
	$time = ($time === NULL ? NOW_TIME : intval($time));
	return date($format, $time);
}

function create_dir_or_files($files)
{
	foreach ($files as $key => $value) {
		if ((substr($value, -1) == '/') && !is_dir($value)) {
			mkdir($value);
		} else {
			@(file_put_contents($value, ''));
		}
	}
}

function get_table_name($model_id = NULL)
{
	if (empty($model_id)) {
		return false;
	}

	$Model = M('Model');
	$name = '';
	$info = $Model->getById($model_id);

	if ($info['extend'] != 0) {
		$name = $Model->getFieldById($info['extend'], 'name') . '_';
	}

	$name .= $info['name'];
	return $name;
}

function get_model_attribute($model_id, $group = true)
{
	static $list;

	if (empty($model_id) || !is_numeric($model_id)) {
		return '';
	}

	if (empty($list)) {
		$list = S('attribute_list');
	}

	if (!isset($list[$model_id])) {
		$map = array('model_id' => $model_id);
		$extend = M('Model')->getFieldById($model_id, 'extend');

		if ($extend) {
			$map = array(
				'model_id' => array('in',array($model_id, $extend))
			);
		}

		$info = M('Attribute')->where($map)->select();
		$list[$model_id] = $info;
	}

	$attr = array();

	foreach ($list[$model_id] as $value) {
		$attr[$value['id']] = $value;
	}

	if ($group) {
		$sort = M('Model')->getFieldById($model_id, 'field_sort');

		if (empty($sort)) {
			$group = array(1 => array_merge($attr));
		} else {
			$group = json_decode($sort, true);
			$keys = array_keys($group);

			foreach ($group as &$value) {
				foreach ($value as $key => $val) {
					$value[$key] = $attr[$val];
					unset($attr[$val]);
				}
			}

			if (!empty($attr)) {
				$group[$keys[0]] = array_merge($group[$keys[0]], $attr);
			}
		}
		$attr = $group;
	}
	return $attr;
}

function get_table_field($value = NULL, $condition = 'id', $field = NULL, $table = NULL)
{
	if (empty($value) || empty($table)) {
		return false;
	}

	$map[$condition] = $value;
	$info = M(ucfirst($table))->where($map);

	if (empty($field)) {
		$info = $info->field(true)->find();
	} else {
		$info = $info->getField($field);
	}

	return $info;
}

function get_tag($id, $link = true)
{
	$tags = D('Article')->getFieldById($id, 'tags');

	if ($link && $tags) {
		$tags = explode(',', $tags);
		$link = array();

		foreach ($tags as $value) {
			$link[] = '<a href="' . U('/') . '?tag=' . $value . '">' . $value . '</a>';
		}

		return join($link, ',');
	} else {
		return $tags ? $tags : 'none';
	}
}

function addon_model($addon, $model)
{
	$dir = \Think\Loader::parseName(lcfirst($addon));
	$class = 'addons\\' . $dir . '\\model\\' . ucfirst($model);
	$model_path = ONETHINK_ADDON_PATH . $dir . '/model/';
	$model_filename = \Think\Loader::parseName(lcfirst($model));
	$class_file = $model_path . $model_filename . '.php';

	if (!class_exists($class)) {
		if (is_file($class_file)) {
			\Think\Loader::import($model_filename, $model_path);
		} else {
			E('插件' . $addon . '的模型' . $model . '文件找不到');
		}
	}

	return new $class($model);
}

function check_server()
{
	return true;
}

//检查输入的字符是否包含sql的风险语句
function checkstr($strsql)
{
	if($strsql){
		$tempSqlStr = $strsql;
		//检测字符串是否有注入风险
	    $tempSqlStr = str_replace("'","",$tempSqlStr);
		$tempSqlStr = trim($tempSqlStr);
		//全部转换为小写
		$tempSqlStr = strtolower($tempSqlStr);
		//验证参数
		$check=preg_match('/select|SELECT|or|OR|and|AND|char|CHAR|create|CREATR|drop|DROP|database|DATABASE|table|TABLE|insert|INSERT|script|SCRIPT|function|FUNCTION|update|UPDATE|delete|DELETE|exec|EXEC|system|SYSTEM|passthru|PASSTHRU|shell_exec|SHELL_EXEC|<|\`|\%|\"|\'|\/\*|\*|\.\.\/|\.\/|union|UNION|into|INTO|load_file|LOAD_FILE|outfile|OUTFILE/i',$tempSqlStr);

		if($check)
		{
			return 1;
		}
	}
	return 0;
}

class HttpClient {
    // Request vars
    var $host;
    var $port;
    var $path;
    var $method;
    var $postdata = '';
    var $cookies = array();
    var $referer;
    var $accept = 'text/xml,application/xml,application/xhtml+xml,text/html,text/plain,image/png,image/jpeg,image/gif,*/*';
    var $accept_encoding = 'gzip';
    var $accept_language = 'en-us';
    var $user_agent = 'Incutio HttpClient v0.9';
    // Options
    var $timeout = 20;
    var $use_gzip = true;
    var $persist_cookies = true;  // If true, received cookies are placed in the $this->cookies array ready for the next request
                                  // Note: This currently ignores the cookie path (and time) completely. Time is not important,
                                  //       but path could possibly lead to security problems.
    var $persist_referers = true; // For each request, sends path of last request as referer
    var $debug = false;
    var $handle_redirects = true; // Auaomtically redirect if Location or URI header is found
    var $max_redirects = 5;
    var $headers_only = false;    // If true, stops receiving once headers have been read.
    // Basic authorization variables
    var $username;
    var $password;
    // Response vars
    var $status;
    var $headers = array();
    var $content = '';
    var $errormsg;
    // Tracker variables
    var $redirect_count = 0;
    var $cookie_host = '';
    function HttpClient($host, $port=80) {
        $this->host = $host;
        $this->port = $port;
    }
    function get($path, $data = false) {
        $this->path = $path;
        $this->method = 'GET';
        if ($data) {
            $this->path .= '?'.$this->buildQueryString($data);
        }
        return $this->doRequest();
    }
    function post($path, $data) {
        $this->path = $path;
        $this->method = 'POST';
        $this->postdata = $this->buildQueryString($data);
        return $this->doRequest();
    }
    function buildQueryString($data) {
        $querystring = '';
        if (is_array($data)) {
            // Change data in to postable data
            foreach ($data as $key => $val) {
                if (is_array($val)) {
                    foreach ($val as $val2) {
                        $querystring .= urlencode($key).'='.urlencode($val2).'&';
                    }
                } else {
                    $querystring .= urlencode($key).'='.urlencode($val).'&';
                }
            }
            $querystring = substr($querystring, 0, -1); // Eliminate unnecessary &
        } else {
            $querystring = $data;
        }
        return $querystring;
    }
    function doRequest() {
        // Performs the actual HTTP request, returning true or false depending on outcome
        if (!$fp = @fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout)) {
            // Set error message
            switch($errno) {
                case -3:
                    $this->errormsg = 'Socket creation failed (-3)';
                case -4:
                    $this->errormsg = 'DNS lookup failure (-4)';
                case -5:
                    $this->errormsg = 'Connection refused or timed out (-5)';
                default:
                    $this->errormsg = 'Connection failed ('.$errno.')';
                $this->errormsg .= ' '.$errstr;
                $this->debug($this->errormsg);
            }
            return false;
        }
        socket_set_timeout($fp, $this->timeout);
        $request = $this->buildRequest();
        $this->debug('Request', $request);
        fwrite($fp, $request);
        // Reset all the variables that should not persist between requests
        $this->headers = array();
        $this->content = '';
        $this->errormsg = '';
        // Set a couple of flags
        $inHeaders = true;
        $atStart = true;
        // Now start reading back the response
        while (!feof($fp)) {
            $line = fgets($fp, 4096);
            if ($atStart) {
                // Deal with first line of returned data
                $atStart = false;
                if (!preg_match('/HTTP\/(\\d\\.\\d)\\s*(\\d+)\\s*(.*)/', $line, $m)) {
                    $this->errormsg = "Status code line invalid: ".htmlentities($line);
                    $this->debug($this->errormsg);
                    return false;
                }
                $http_version = $m[1]; // not used
                $this->status = $m[2];
                $status_string = $m[3]; // not used
                $this->debug(trim($line));
                continue;
            }
            if ($inHeaders) {
                if (trim($line) == '') {
                    $inHeaders = false;
                    $this->debug('Received Headers', $this->headers);
                    if ($this->headers_only) {
                        break; // Skip the rest of the input
                    }
                    continue;
                }
                if (!preg_match('/([^:]+):\\s*(.*)/', $line, $m)) {
                    // Skip to the next header
                    continue;
                }
                $key = strtolower(trim($m[1]));
                $val = trim($m[2]);
                // Deal with the possibility of multiple headers of same name
                if (isset($this->headers[$key])) {
                    if (is_array($this->headers[$key])) {
                        $this->headers[$key][] = $val;
                    } else {
                        $this->headers[$key] = array($this->headers[$key], $val);
                    }
                } else {
                    $this->headers[$key] = $val;
                }
                continue;
            }
            // We're not in the headers, so append the line to the contents
            $this->content .= $line;
        }
        fclose($fp);
        // If data is compressed, uncompress it
        if (isset($this->headers['content-encoding']) && $this->headers['content-encoding'] == 'gzip') {
            $this->debug('Content is gzip encoded, unzipping it');
            $this->content = substr($this->content, 10); // See http://www.php.net/manual/en/function.gzencode.php
            $this->content = gzinflate($this->content);
        }
        // If $persist_cookies, deal with any cookies
        if ($this->persist_cookies && isset($this->headers['set-cookie']) && $this->host == $this->cookie_host) {
            $cookies = $this->headers['set-cookie'];
            if (!is_array($cookies)) {
                $cookies = array($cookies);
            }
            foreach ($cookies as $cookie) {
                if (preg_match('/([^=]+)=([^;]+);/', $cookie, $m)) {
                    $this->cookies[$m[1]] = $m[2];
                }
            }
            // Record domain of cookies for security reasons
            $this->cookie_host = $this->host;
        }
        // If $persist_referers, set the referer ready for the next request
        if ($this->persist_referers) {
            $this->debug('Persisting referer: '.$this->getRequestURL());
            $this->referer = $this->getRequestURL();
        }
        // Finally, if handle_redirects and a redirect is sent, do that
        if ($this->handle_redirects) {
            if (++$this->redirect_count >= $this->max_redirects) {
                $this->errormsg = 'Number of redirects exceeded maximum ('.$this->max_redirects.')';
                $this->debug($this->errormsg);
                $this->redirect_count = 0;
                return false;
            }
            $location = isset($this->headers['location']) ? $this->headers['location'] : '';
            $uri = isset($this->headers['uri']) ? $this->headers['uri'] : '';
            if ($location || $uri) {
                $url = parse_url($location.$uri);
                // This will FAIL if redirect is to a different site
                return $this->get($url['path']);
            }
        }
        return true;
    }
    function buildRequest() {
        $headers = array();
        $headers[] = "{$this->method} {$this->path} HTTP/1.0"; // Using 1.1 leads to all manner of problems, such as "chunked" encoding
        $headers[] = "Host: {$this->host}";
        $headers[] = "User-Agent: {$this->user_agent}";
        $headers[] = "Accept: {$this->accept}";
        if ($this->use_gzip) {
            $headers[] = "Accept-encoding: {$this->accept_encoding}";
        }
        $headers[] = "Accept-language: {$this->accept_language}";
        if ($this->referer) {
            $headers[] = "Referer: {$this->referer}";
        }
        // Cookies
        if ($this->cookies) {
            $cookie = 'Cookie: ';
            foreach ($this->cookies as $key => $value) {
                $cookie .= "$key=$value; ";
            }
            $headers[] = $cookie;
        }
        // Basic authentication
        if ($this->username && $this->password) {
            $headers[] = 'Authorization: BASIC '.base64_encode($this->username.':'.$this->password);
        }
        // If this is a POST, set the content type and length
        if ($this->postdata) {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            $headers[] = 'Content-Length: '.strlen($this->postdata);
        }
        $request = implode("\r\n", $headers)."\r\n\r\n".$this->postdata;
        return $request;
    }
    function getStatus() {
        return $this->status;
    }
    function getContent() {
        return $this->content;
    }
    function getHeaders() {
        return $this->headers;
    }
    function getHeader($header) {
        $header = strtolower($header);
        if (isset($this->headers[$header])) {
            return $this->headers[$header];
        } else {
            return false;
        }
    }
    function getError() {
        return $this->errormsg;
    }
    function getCookies() {
        return $this->cookies;
    }
    function getRequestURL() {
        $url = 'http://'.$this->host;
        if ($this->port != 80) {
            $url .= ':'.$this->port;
        }
        $url .= $this->path;
        return $url;
    }
    // Setter methods
    function setUserAgent($string) {
        $this->user_agent = $string;
    }
    function setAuthorization($username, $password) {
        $this->username = $username;
        $this->password = $password;
    }
    function setCookies($array) {
        $this->cookies = $array;
    }
    // Option setting methods
    function useGzip($boolean) {
        $this->use_gzip = $boolean;
    }
    function setPersistCookies($boolean) {
        $this->persist_cookies = $boolean;
    }
    function setPersistReferers($boolean) {
        $this->persist_referers = $boolean;
    }
    function setHandleRedirects($boolean) {
        $this->handle_redirects = $boolean;
    }
    function setMaxRedirects($num) {
        $this->max_redirects = $num;
    }
    function setHeadersOnly($boolean) {
        $this->headers_only = $boolean;
    }
    function setDebug($boolean) {
        $this->debug = $boolean;
    }
    // "Quick" static methods
    function quickGet($url) {
        $bits = parse_url($url);
        $host = $bits['host'];
        $port = isset($bits['port']) ? $bits['port'] : 80;
        $path = isset($bits['path']) ? $bits['path'] : '/';
        if (isset($bits['query'])) {
            $path .= '?'.$bits['query'];
        }
        $client = new HttpClient($host, $port);
        if (!$client->get($path)) {
            return false;
        } else {
            return $client->getContent();
        }
    }
    function quickPost($url, $data) {
        $bits = parse_url($url);
        $host = $bits['host'];
        $port = isset($bits['port']) ? $bits['port'] : 80;
        $path = isset($bits['path']) ? $bits['path'] : '/';
        $client = new HttpClient($host, $port);
        if (!$client->post($path, $data)) {
            return false;
        } else {
            return $client->getContent();
        }
    }
    function debug($msg, $object = false) {
        if ($this->debug) {
            print '<div style="border: 1px solid red; padding: 0.5em; margin: 0.5em;"><strong>HttpClient Debug:</strong> '.$msg;
            if ($object) {
                ob_start();
                print_r($object);
                $content = htmlentities(ob_get_contents());
                ob_end_clean();
                print '<pre>'.$content.'</pre>';
            }
            print '</div>';
        }
    }
}

/**
 * http进行请求
 * @param  [string] $url        [请求地址] 
 * @param  [data]   $data       [请求发送的数据] 
 * @param  [array]  $headers    [请求发送的数据格式] 
 *例如下格式:
 *1. ['Content-Type: multipart/form-data'] 默认的类型，   是用form表单形式提交，但是可以提交文件，
 *2. ['Content-Type: application/json;charset=utf-8']    表示以json格式提交post请求，数据使用json_encode 处理, [charset 为所选的字符集，可以不选]
 *3. ['Content-Type: application/x-www-form-urlencoded'] 表示以form表单的形式提交post请求,不带提交文件 ，数据使用http_build_query 进行处理
 *4. ['Content-type: application/xml']                   表示以xml的文件格式提交post请求 ，application/xml会根据xml头指定的编码格式来编码
 *5. ['Content-type: text/xml']                          表示以xml的文件格式提交post请求 ，text/xml忽略xml头所指定编码格式而默认采用us-ascii编码
 * @param  [string] $method     [请求的模式：POST ，GET]
 * @param  [int]    $timeOut    [请求的超时时间]
 * @param  [string] $agent      [使用的代理]
 */
function httpRequestData($url, $data = '', $headers = array(),$method='POST',$timeOut=10, $agent = '')
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeOut);           //请求超时时间
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeOut);    //链接超时时间
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);        // https请求 不验证证书和hosts
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, $agent);
    if (strtolower($method) == 'post')
    {
        curl_setopt($ch, CURLOPT_POST, 1);
        if ($data != '') curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }
    $file_contents = curl_exec($ch);
    $httpCode = curl_getinfo($ch,CURLINFO_HTTP_CODE);
    curl_close($ch);
    if($httpCode != 200){//200表示成功
    	return 'httpcode = '.$httpCode. ' contents = ' . $file_contents;
    }
    //这里解析
    return $file_contents;
}

//判断是否是中国手机号
function bIsChinaPhoneNo($phoneNo){
    if(is_string($phoneNo) && strlen($phoneNo) > 10){

        $pos = strpos($phoneNo, '86');
        if($pos === 0){
            return true;
        }
    }
    return false;
}

// 短信接口
function sendsmsint($mobile,$code,$sign)
{
	//请使用您自己的开发者KEY
	$config = M('Config')->where(array('id' => 1))->find();
	$accesskey = $config['smsname'];
	$accessScrect = $config['smspass'];
	$mobile = trim($mobile);

	$bIsChanPhone = true;
	//手机号判断
    if(bIsChinaPhoneNo($mobile)){ //是否中国手机号
    	if(strpos($mobile, '86') === 0){
    		$mobile = substr($mobile, 2, strlen($mobile)-2);
    	}
        $bIsChanPhone = true;
    }else{
    	$mobile = '+'.$mobile;
        $bIsChanPhone = false;
    }

	//修改为固定模板，减少模板申请备案
	$smsyunpian = new \Org\Util\SmsYunpian\SmsYunpian($accessScrect);
    //file_put_contents('yunpian_error_log.txt', "\n Smsyun_pian new class",FILE_APPEND);
    if($bIsChanPhone){

        $content = '【亚胜科技】您的验证码为：'.$code.'，该验证码5分钟内有效，请勿泄露他人。';
        $response = $smsyunpian->sendSmsWithContent($mobile, $content);
    }else{

        $content = '【YashengTechnology】Your verification code is: '.$code.', the verification code is valid for 5 minutes, please do not reveal others.';
        $response = $smsyunpian->sendWSmsWithContent($mobile, $content);
    }
    //var_dump($response);
	if ($response->Code == 0) {
		return ture;
	} else {
		return false;
	}
	// echo '返回的JSON结果：'; print_r(json_decode($result));
}

function  randStr($len = 6) {
    $chars='ABDEFGHJKLMNPQRSTVWXYabdefghijkmnpqrstvwxy23456789';
    mt_srand((double)microtime() * 1000000 * getmypid());

    $result = '';
    while(strlen($result) < $len)
        $result .= substr($chars, (mt_rand() % strlen($chars)), 1);

    return $result;
}

/**
 * 生成C2C订单号
 * @return  string
 */
function build_exchange_order_no($time=null)
{
	$time = $time ? $time : time();
    /* 选择一个随机的方案 */
    $orderid = date('YmdHis',$time) . substr(implode(NULL, array_map('ord', str_split(substr(uniqid('',true), 7, 17), 1))), 0, 8);
    $order_info = D('ExchangeOrder')->where(['orderid'=>$orderid])->find();
    while (!empty($order_info)) {
    	$orderid = date('YmdHis',$time) . substr(implode(NULL, array_map('ord', str_split(substr(uniqid('',true), 7, 17), 1))), 0, 8);
    	$order_info = D('ExchangeOrder')->where(['orderid'=>$orderid])->find();
    }
    return $orderid;
}

//bignumber
class BigNumber
{
    /**
     * Number value, as a string
     *
     * @var string
     */
    protected $numberValue;

    /**
     * The scale for the current number
     *
     * @var int
     */
    protected $numberScale;

    /**
     * Constructs a BigNumber object from a string, integer, float, or any
     * object that may be cast to a string, resulting in a numeric string value
     *
     * @param mixed $number May be of any type that can be cast to a string
     *                      representation of a base 10 number
     * @param int $scale (optional) Specifies the default number of digits after the decimal
     *                   place to be used in operations for this BigNumber
     * @return void
     */
    public function __construct($number, $scale = null)
    {
        if ($scale) {
            $this->setScale($scale);
        }

        $this->setValue($number);
    }

    /**
     * Returns the string value of this BigNumber
     *
     * @return string String representation of the number in base 10
     */
    public function __toString()
    {
        return (string) $this->getValue();
    }

    /**
     * Sets the current number to the absolute value of itself
     *
     * @return BigNumber for fluent interface
     */
    public function abs()
    {
        // Use substr() to find the negative sign at the beginning of the
        // number, rather than using signum() to determine the sign.
        if (substr($this->numberValue, 0, 1) === '-') {
            $this->numberValue = substr($this->numberValue, 1);
        }

        return $this;
    }

    /**
     * Adds the given number to the current number
     *
     * @param mixed $number May be of any type that can be cast to a string
     *                      representation of a base 10 number
     * @return BigNumber for fluent interface
     * @link http://www.php.net/bcadd
     */
    public function add($number)
    {
        $this->numberValue = bcadd(
            $this->numberValue,
            $this->filterNumber($number),
            $this->getScale()
        );

        return $this;
    }

    /**
     * Finds the next highest integer value by rounding up the current number
     * if necessary
     *
     * @return BigNumber for fluent interface
     * @link http://www.php.net/ceil
     */
    public function ceil()
    {
        $number = $this->getValue();

        if ($this->isPositive()) {
            // 14 is the magic precision number
            $number = bcadd($number, '0', 14);
            if (substr($number, -15) != '.00000000000000') {
                $number = bcadd($number, '1', 0);
            }
        }

        $this->numberValue = bcadd($number, '0', 0);

        return $this;
    }

    /**
     * Compares the current number with the given number
     *
     * Returns 0 if the two operands are equal, 1 if the current number is
     * larger than the given number, -1 otherwise.
     *
     * @param mixed $number May be of any type that can be cast to a string
     *                      representation of a base 10 number
     * @return int
     * @link http://www.php.net/bccomp
     */
    public function compareTo($number)
    {
        return bccomp(
            $this->numberValue,
            $this->filterNumber($number),
            $this->getScale()
        );
    }

    /**
     * Returns the current value converted to an arbitrary base
     *
     * @param int $base The base to convert the current number to
     * @return string String representation of the number in the given base
     */
    public function convertToBase($base)
    {
        return self::convertFromBase10($this->getValue(), $base);
    }

    /**
     * Decreases the value of the current number by one
     *
     * @return BigNumber for fluent interface
     */
    public function decrement()
    {
        return $this->subtract(1);
    }

    /**
     * Divides the current number by the given number
     *
     * @param mixed $number May be of any type that can be cast to a string
     *                      representation of a base 10 number
     * @return BigNumber for fluent interface
     * @throws Exception\ArithmeticException if $number is zero
     * @link http://www.php.net/bcdiv
     */
    public function divide($number)
    {
        $number = $this->filterNumber($number);

        if ($number == '0') {
            throw new Exception\ArithmeticException('Division by zero');
        }

        $this->numberValue = bcdiv(
            $this->numberValue,
            $number,
            $this->getScale()
        );

        return $this;
    }

    /**
     * Finds the next lowest integer value by rounding down the current number
     * if necessary
     *
     * @return BigNumber for fluent interface
     * @link http://www.php.net/floor
     */
    public function floor()
    {
        $number = $this->getValue();

        if ($this->isNegative()) {
            // 14 is the magic precision number
            $number = bcadd($number, '0', 14);
            if (substr($number, -15) != '.00000000000000') {
                $number = bcsub($number, '1', 0);
            }
        }

        $this->numberValue = bcadd($number, '0', 0);

        return $this;
    }

    /**
     * Returns the scale used for this BigNumber
     *
     * If no scale was set, this will default to the value of bcmath.scale
     * in php.ini.
     *
     * @return int
     */
    public function getScale()
    {
        if ($this->numberScale === null) {
            return ini_get('bcmath.scale');
        }

        return $this->numberScale;
    }

    /**
     * Returns the current raw value of this BigNumber
     *
     * @return string String representation of the number in base 10
     */
    public function getValue()
    {
        return $this->numberValue;
    }

    /**
     * Increases the value of the current number by one
     *
     * @return BigNumber for fluent interface
     */
    public function increment()
    {
        return $this->add(1);
    }

    /**
     * Returns true if the current number equals the given number
     *
     * @param mixed $number May be of any type that can be cast to a string
     *                      representation of a base 10 number
     * @return bool
     */
    public function isEqualTo($number)
    {
        return ($this->compareTo($number) == 0);
    }

    /**
     * Returns true if the current number is greater than the given number
     *
     * @param mixed $number May be of any type that can be cast to a string
     *                      representation of a base 10 number
     * @return bool
     */
    public function isGreaterThan($number)
    {
        return ($this->compareTo($number) == 1);
    }

    /**
     * Returns true if the current number is greater than or equal to the given number
     *
     * @param mixed $number May be of any type that can be cast to a string
     *                      representation of a base 10 number
     * @return bool
     */
    public function isGreaterThanOrEqualTo($number)
    {
        return ($this->compareTo($number) >= 0);
    }

    /**
     * Returns true if the current number is less than the given number
     *
     * @param mixed $number May be of any type that can be cast to a string
     *                      representation of a base 10 number
     * @return bool
     */
    public function isLessThan($number)
    {
        return ($this->compareTo($number) == -1);
    }

    /**
     * Returns true if the current number is less than or equal to the given number
     *
     * @param mixed $number May be of any type that can be cast to a string
     *                      representation of a base 10 number
     * @return bool
     */
    public function isLessThanOrEqualTo($number)
    {
        return ($this->compareTo($number) <= 0);
    }

    /**
     * Returns true if the current number is a negative number
     *
     * @return bool
     */
    public function isNegative()
    {
        return ($this->signum() == -1);
    }

    /**
     * Returns true if the current number is a positive number
     *
     * @return bool
     */
    public function isPositive()
    {
        return ($this->signum() == 1);
    }

    /**
     * Finds the modulus of the current number divided by the given number
     *
     * @param mixed $number May be of any type that can be cast to a string
     *                      representation of a base 10 number
     * @return BigNumber for fluent interface
     * @throws Exception\ArithmeticException if $number is zero
     * @link http://www.php.net/bcmod
     */
    public function mod($number)
    {
        $number = $this->filterNumber($number);

        if ($number == '0') {
            throw new Exception\ArithmeticException('Division by zero');
        }

        $this->numberValue = bcmod(
            $this->numberValue,
            $number
        );

        return $this;
    }

    /**
     * Multiplies the current number by the given number
     *
     * @param mixed $number May be of any type that can be cast to a string
     *                      representation of a base 10 number
     * @return BigNumber for fluent interface
     * @link http://www.php.net/bcmul
     */
    public function multiply($number)
    {
        $this->numberValue = bcmul(
            $this->numberValue,
            $this->filterNumber($number),
            $this->getScale()
        );

        return $this;
    }

    /**
     * Sets the current number to the negative value of itself
     *
     * @return BigNumber for fluent interface
     */
    public function negate()
    {
        return $this->multiply(-1);
    }

    /**
     * Raises current number to the given number
     *
     * @param mixed $number May be of any type that can be cast to a string
     *                      representation of a base 10 number
     * @return BigNumber for fluent interface
     * @link http://www.php.net/bcpow
     */
    public function pow($number)
    {
        $this->numberValue = bcpow(
            $this->numberValue,
            $this->filterNumber($number),
            $this->getScale()
        );

        return $this;
    }

    /**
     * Raises the current number to the $pow, then divides by the $mod
     * to find the modulus
     *
     * This is functionally equivalent to the following code:
     *
     * <code>
     *     $n = new BigNumber(1234);
     *     $n->mod($n->pow(32), 2);
     * </code>
     *
     * However, it uses bcpowmod(), so it is faster and can accept larger
     * parameters.
     *
     * @param mixed $pow May be of any type that can be cast to a string
     *                   representation of a base 10 number
     * @param mixed $mod May be of any type that can be cast to a string
     *                   representation of a base 10 number
     * @return BigNumber for fluent interface
     * @throws Exception\ArithmeticException if $number is zero
     * @link http://www.php.net/bcpowmod
     */
    public function powMod($pow, $mod)
    {
        $mod = $this->filterNumber($mod);

        if ($mod == '0') {
            throw new Exception\ArithmeticException('Division by zero');
        }

        $this->numberValue = bcpowmod(
            $this->numberValue,
            $this->filterNumber($pow),
            $mod,
            $this->getScale()
        );
        return $this;
    }

    /**
     * Rounds the current number to the nearest integer
     *
     * @return BigNumber for fluent interface
     * @todo Implement precision digits
     */
    public function round()
    {
        $original = $this->getValue();
        $floored = $this->floor()->getValue();
        $diff = bcsub($original, $floored, 20);

        if ($this->isNegative()) {
            $roundedDiff = round($diff, 0, PHP_ROUND_HALF_DOWN);
        } else {
            $roundedDiff = round($diff);
        }

        $this->numberValue = bcadd(
            $floored,
            $roundedDiff,
            0
        );
        return $this;
    }

    /**
     * Sets the scale of this BigNumber
     *
     * @param int $scale Specifies the default number of digits after the decimal
     *                   place to be used in operations for this BigNumber
     * @return BigNumber for fluent interface
     */
    public function setScale($scale)
    {
        $this->numberScale = (int) $scale;
        return $this;
    }

    /**
     * Sets the value of this BigNumber to a new value
     *
     * @param mixed $number May be of any type that can be cast to a string
     *                      representation of a base 10 number
     * @return BigNumber for fluent interface
     */
    public function setValue($number)
    {
        // Set the scale for the number to the scale value passed in
        $number = bcadd(
            $this->filterNumber($number),
            '0',
            $this->getScale()
        );
        $this->numberValue = $number;
        return $this;
    }

    /**
     * Shifts the current number $bits to the left
     *
     * @param int $bits
     * @return BigNumber for fluent interface
     */
    public function shiftLeft($bits)
    {
        $this->numberValue = bcmul(
            $this->numberValue,
            bcpow('2', $bits)
        );
        return $this;
    }

    /**
     * Shifts the current number $bits to the right
     *
     * @param int $bits
     * @return BigNumber for fluent interface
     */
    public function shiftRight($bits)
    {
        $this->numberValue = bcdiv(
            $this->numberValue,
            bcpow('2', $bits)
        );
        return $this;
    }

    /**
     * Returns the sign (signum) of the current number
     *
     * @return int -1, 0 or 1 as the value of this BigNumber is negative, zero or positive
     */
    public function signum()
    {
        if ($this->isGreaterThan(0)) {
            return 1;
        } elseif ($this->isLessThan(0)) {
            return -1;
        }
        return 0;
    }

    /**
     * Finds the square root of the current number
     *
     * @return BigNumber for fluent interface
     * @link http://www.php.net/bcsqrt
     */
    public function sqrt()
    {
        $this->numberValue = bcsqrt(
            $this->numberValue,
            $this->getScale()
        );
        return $this;
    }

    /**
     * Subtracts the given number from the current number
     *
     * @param mixed $number May be of any type that can be cast to a string
     *                      representation of a base 10 number
     * @return BigNumber for fluent interface
     * @link http://www.php.net/bcsub
     */
    public function subtract($number)
    {
        $this->numberValue = bcsub(
            $this->numberValue,
            $this->filterNumber($number),
            $this->getScale()
        );
        return $this;
    }

    /**
     * Filters a number, converting it to a string value
     *
     * @param mixed $number
     * @return string
     */
    protected function filterNumber($number)
    {
        return filter_var(
            $number,
            FILTER_SANITIZE_NUMBER_FLOAT,
            FILTER_FLAG_ALLOW_FRACTION
        );
    }

    /**
     * Converts a number between arbitrary bases (from 2 to 36)
     *
     * @param string|int $number The number to convert
     * @param int $fromBase (optional) The base $number is in; defaults to 10
     * @param int $toBase (optional) The base to convert $number to; defaults to 16
     * @return string
     */
    public static function baseConvert($number, $fromBase = 10, $toBase = 16)
    {
        $number = self::convertToBase10($number, $fromBase);
        return self::convertFromBase10($number, $toBase);
    }

    /**
     * Converts a base-10 number to an arbitrary base (from 2 to 36)
     *
     * @param string|int $number The number to convert
     * @param int $toBase The base to convert $number to
     * @return string
     * @throws \InvalidArgumentException if $toBase is outside the range 2 to 36
     */
    public static function convertFromBase10($number, $toBase)
    {
        if ($toBase < 2 || $toBase > 36) {
            throw new \InvalidArgumentException("Invalid `to base' ({$toBase})");
        }

        $bn = new self($number);
        $number = $bn->abs()->getValue();
        $digits = '0123456789abcdefghijklmnopqrstuvwxyz';
        $outNumber = '';
        $returnDigitCount = 0;

        while (bcdiv($number, bcpow($toBase, (string) $returnDigitCount)) > ($toBase - 1)) {
            $returnDigitCount++;
        }
		
        for ($i = $returnDigitCount; $i >= 0; $i--) {
            $pow = bcpow($toBase, (string) $i);
            $c = bcdiv($number, $pow);
            $number = bcsub($number, bcmul($c, $pow));
            $outNumber .= $digits[(int) $c];
        }
		
        return $outNumber;
    }

    /**
     * Converts a number from an arbitrary base (from 2 to 36) to base 10
     *
     * @param string|int $number The number to convert
     * @param int $fromBase The base $number is in
     * @return string
     * @throws \InvalidArgumentException if $fromBase is outside the range 2 to 36
     */
    public static function convertToBase10($number, $fromBase)
    {
        if ($fromBase < 2 || $fromBase > 36) {
            throw new \InvalidArgumentException("Invalid `from base' ({$fromBase})");
        }

        $number = (string) $number;
        $len = strlen($number);
        $base10Num = '0';

        for ($i = $len; $i > 0; $i--) {
            $c = ord($number[$len - $i]);
            if ($c >= ord('0') && $c <= ord('9')) {
                $c -= ord('0');
            } elseif ($c >= ord('A') && $c <= ord('Z')) {
                $c -= ord('A') - 10;
            } elseif ($c >= ord('a') && $c <= ord('z')) {
                $c -= ord('a') - 10;
            } else {
                continue;
            }

            if ($c >= $fromBase) {
                continue;
            }
            $base10Num = bcadd(bcmul($base10Num, $fromBase), (string) $c);
        }
        return $base10Num;
    }

    /**
     * Changes the default scale used by all Binary Calculator functions
     *
     * @param int $scale
     * @return void
     */
    public static function setDefaultScale($scale)
    {
        ini_set('bcmath.scale', $scale);
    }
}

function bnumber($num,$fromBase,$toBase)
{
	$getnum = new BigNumber();
	$result = $getnum->baseConvert($num,$fromBase,$toBase);
	return $result;
}

// 获取文件最后修改时间
function stamp($file)
{
   $version = filemtime('.'.$file);
   return $file."?v=".$version;
}

//创建TOKEN
function creatToken() {
    $code = chr(mt_rand(0xB0, 0xF7)) . chr(mt_rand(0xA1, 0xFE)) . chr(mt_rand(0xB0, 0xF7)) . chr(mt_rand(0xA1, 0xFE)) . chr(mt_rand(0xB0, 0xF7)) . chr(mt_rand(0xA1, 0xFE));
    session('__token__', authcode($code));
}
/* 加密TOKEN */
function authcode($str) {
    $key = "ANDIAMON";
    $str = substr(md5($str), 8, 10);
    return md5($key . $str);
}
//判断TOKEN
function checkToken($token) {
    if ($token == session('__token__')) {
        //session('__token__', NULL);
        return true;
    } else {
		return false;
    }
}

//获取日期的每个单位时间的列表
function getDateList($time = null){
	if(isset($time) && $time > 0){
		$curDate = date('Y-m-d-H-i-s', $time);
		return explode('-',  $curDate);
	}else{
		$curDate = date('Y-m-d-H-i-s');
		return explode('-',  $curDate);
	}
}

//获取二维码的url
function getQRcodeToUrl($qrcode){
	if($qrcode){
		$qrcodepath = C('STAR_EXCHANGE_WEB_URL').'/Upload/qrcode/personal/'.$qrcode;
		$request_url = 'http://zxing.org/w/decode?u='. urlencode($qrcodepath);
		$code_data = file_get_contents($request_url);
		preg_match("/<table id=\"result\">(.*)<\/table>/isU",$code_data,$table_math);
		preg_match("/<tr><td>Raw text<\/td>(.*)<\/tr>/isU",$table_math[1],$tr_math);
		preg_match("/<pre>(.*)<\/pre>/isU",$tr_math[1],$mathdata);
		if($mathdata){
			return $mathdata[1];
		}
		return $tr_math[1];
	}else{
		return false;
	}
}

//通过流的方式远程获取数据(重新实现的)
function file_get_contents_new($url, $timeout=10){
	$array = array('http'=>['method'=>'GET', 'timeout'=>$timeout]);
    $context = stream_context_create($array);
    return file_get_contents($url, false, $context);
}

//更新token值
function updateTokenCache($id, $token, $time=null){

    $time = $time?$time:time(); 
    if($id && $token){

        $tokenCaches = C('TokenCache');
        $tokenCaches[$id] = ['token'=>$token,'time'=>$time];

        $str = "";
        $str = "<?php \n";
        $str .= "\treturn array(\n";
        $str .= "\t\t'TokenCache'=>[\n";
        foreach ($tokenCaches as $key => $value) {
            $str .= "\t\t\t'{$key}' => [ 'token'=>'" . $value['token'] ."', 'time'=>'".$value['time']. "'],\n";
        }

        $str .= "\t\t]\n";
        $str .= "\t);\n";
        $str .= "?>";

        file_put_contents(CONF_PATH . 'tokenCache.php', $str);
    }
}

/**
 * 使用PHPEXCEL导出数据为excel表格
 * @param $data    一个二维数组,结构如同从数据库查出来的数组
 * @param $title   excel的第一行标题,一个数组,如果为空则没有标题
 * @param $numberField 数组字段
 * @param $filename 下载的文件名
 * @examlpe
$stu = M ('User');
 * $arr = $stu -> select();
 * exportexcel($arr,array('id','账户','密码','昵称'),'文件名!');
 */
function exportexcel($data = array(), $title = array(), $numberField = [], $filename = 'report') {

    ini_set("memory_limit", "1024M"); // 设置php可使用内存
    set_time_limit(0);  # 设置执行时间最大值
    define('EOL', '<br />');
    vendor("PHPExcel.PHPExcel");
    $objPHPExcel = new \PHPExcel();
    $cacheMethod = \PHPExcel_CachedObjectStorageFactory::cache_to_discISAM;
    if (!\PHPExcel_Settings::setCacheStorageMethod($cacheMethod)) {
        die($cacheMethod . " 缓存方法不可用" . EOL);
    }
    $cellName = array('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z','AA','AB','AC','AD','AE','AF','AG','AH','AI','AJ','AK','AL','AM','AN','AO','AP','AQ','AR','AS','AT','AU','AV','AW','AX','AY','AZ');
    $objPHPExcel->setActiveSheetIndex(0);
    if (!empty($title)) {
        foreach ($title as $k => $v) {
            $objPHPExcel->getActiveSheet()->getStyle($cellName[$k])->getNumberFormat()
                ->setFormatCode(\PHPExcel_Style_NumberFormat::FORMAT_NUMBER);
            $objPHPExcel->getActiveSheet()->setCellValue($cellName[$k].'1', $v);
        }
    }
    if (!empty($data)) {
        $Cellkey = 2;
        foreach ($data as $key => $val) {
            $i = 0;
            foreach ($val as $ck => $cv) {
                if(strpos($cv, '=') === 0){ $cv= "'".$cv;}
                if(in_array($ck, $numberField)) {
                    $objPHPExcel->getActiveSheet()->setCellValue($cellName[$i]. $Cellkey, $cv);
                } else {
                    $objPHPExcel->getActiveSheet()->setCellValueExplicit($cellName[$i]. $Cellkey, $cv, PHPExcel_Cell_DataType::TYPE_STRING);
                }
                $i++;
            }
            $Cellkey++;
        }
        $objPHPExcel->setActiveSheetIndex(0);
        //$objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $filename = "EXCEL".date("mdHis",time());
        \ob_end_clean();
        header("Content-type:application/octet-stream");
        header("Accept-Ranges:bytes");
        header("Content-type:application/vnd.ms-excel");
        header("Content-Disposition:attachment;filename=" . $filename . ".xlsx");
        header("Pragma: no-cache");
        header("Expires: 0");
        $objWriter->save('php://output');
    } else {
        die(" 暂无数据" . EOL);
    }
}

//导入excel
function importExcel($file, $keyArray){
    header("Content-type: text/html; charset=utf-8");
    vendor("PHPExcel.PHPExcel");
     if (!file_exists($file)) {
        die('no file!');
    }
    //扩展名
    $extension = strtolower( pathinfo($file, PATHINFO_EXTENSION) );

    if ($extension =='xlsx') {
        $objReader = new PHPExcel_Reader_Excel2007();
        $objReader->setReadDataOnly(true);
        $objExcel = $objReader ->load($file, $encode = 'utf-8');
    } else if ($extension =='xls') {
        $objReader = new PHPExcel_Reader_Excel5();
        $objReader->setReadDataOnly(true);
        $objExcel = $objReader ->load($file, $encode = 'utf-8');
    } else if ($extension=='csv') {
        $PHPReader = new PHPExcel_Reader_CSV();
        $objReader->setReadDataOnly(true);
        //默认输入字符集
        $PHPReader->setInputEncoding('GBK');
        //默认的分隔符
        $PHPReader->setDelimiter(',');
        //载入文件
        $objExcel = $PHPReader->load($file, $encode = 'utf-8');
    }
    $sheet         = $objExcel->getSheet(0);
    $highestRow    = $sheet->getHighestRow(); // 取得总行数
    $highestColumn = $sheet->getHighestColumn(); // 取得总列数

    $cellName = array('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z','AA','AB','AC','AD','AE','AF','AG','AH','AI','AJ','AK','AL','AM','AN','AO','AP','AQ','AR','AS','AT','AU','AV','AW','AX','AY','AZ');

    for ($i = 2; $i <= $highestRow; $i++) {

        //根据传入的Key列表，来赋值
        foreach ($keyArray as $key => $value) {
            $data[$i][$keyArray[$key]] = $objExcel->getActiveSheet()->getCell($cellName[$key] . $i)->getValue();
        }
    }
    return $data;
}

?>