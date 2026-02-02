<?php
header("Content-Type: text/html; charset=UTF-8");
ignore_user_abort(true); //即使Client断开(如关掉浏览器)，PHP脚本也可以继续执行.
set_time_limit(0); // 执行时间为无限制，php默认的执行时间是30秒，通过set_time_limit(0)可以让程序无限制的执行下去

include_once 'define.php';
include_once 'common.php';

$fp = fopen("autoMatchLock.txt", "w+");
if(flock($fp,LOCK_EX | LOCK_NB))
{
	echo '自动匹配交易开始'.date('Y-m-d H:i:s')."\n";
	$result = http_gets(WEB_SITE."/Cli/AutoExchange/autoMatch");
	if($result !== false){
		echo '自动匹配交易成功'."\n";
	}else{
		echo '自动匹配交易失败'."\n";
	}
	flock($fp,LOCK_UN);
}else {
   echo "文件被锁定";
}
fclose($fp);
echo "本次自动匹配交易执行完毕time=".date('Y-m-d H:i:s')."\n";
exit();
?>