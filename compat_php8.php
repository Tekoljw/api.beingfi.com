<?php
/**
 * PHP 8.0 compatibility shims for ThinkPHP 3.x
 * These functions were removed in PHP 8.0 and need to be shimmed.
 */

// get_magic_quotes_gpc() — always returned false in PHP 7.4+, removed in PHP 8.0
if (!function_exists('get_magic_quotes_gpc')) {
    function get_magic_quotes_gpc(): bool { return false; }
}

// get_magic_quotes_runtime() — always returned false in PHP 7.4+, removed in PHP 8.0
if (!function_exists('get_magic_quotes_runtime')) {
    function get_magic_quotes_runtime(): bool { return false; }
}

// set_magic_quotes_runtime() — no-op, removed in PHP 8.0
if (!function_exists('set_magic_quotes_runtime')) {
    function set_magic_quotes_runtime(bool $enable): bool { return true; }
}

// mysql_escape_string() — removed in PHP 7.0, shim with addslashes
if (!function_exists('mysql_escape_string')) {
    function mysql_escape_string(string $string): string {
        return addslashes($string);
    }
}

// mysql_real_escape_string() — removed in PHP 7.0
if (!function_exists('mysql_real_escape_string')) {
    function mysql_real_escape_string(string $string, $link = null): string {
        return addslashes($string);
    }
}
