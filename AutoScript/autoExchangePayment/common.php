<?php

//通过curl_init框架来处理http请求
function http_gets_curl($url){
	$oCurl = curl_init();
	if(stripos($url,"https://")!==FALSE){
		curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, FALSE);
		curl_setopt($oCurl, CURLOPT_SSLVERSION, 1); //CURL_SSLVERSION_TLSv1
	}
	curl_setopt($oCurl, CURLOPT_URL, $url);
	curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($oCurl, CURLOPT_TIMEOUT, 30*60);
	curl_setopt($oCurl, CURLOPT_CONNECTTIMEOUT, 10);
	$sContent = curl_exec($oCurl);
	$aStatus = curl_getinfo($oCurl);
	curl_close($oCurl);
	if(intval($aStatus["http_code"])==200){
		return $sContent;
	}else{
		return false;
	}
}

//http请求处理
function http_gets($url) {

	if (function_exists('curl_init')) {
		$result = http_gets_curl($url);
	} else {
		$result = file_get_contents($url);
	}
	return $result;
}

?>