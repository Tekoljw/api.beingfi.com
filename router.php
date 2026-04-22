<?php
if (!function_exists('mysql_escape_string')) {
    function mysql_escape_string($str) {
        return addslashes($str);
    }
}
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if ($uri !== '/' && file_exists($_SERVER['DOCUMENT_ROOT'] . $uri)) {
    return false;
}
$_GET['s'] = ltrim($uri, '/');
include $_SERVER['DOCUMENT_ROOT'] . '/index.php';
