<?php
$isProduction = (bool) getenv('REPLIT_DEPLOYMENT');
$dbUrl = $isProduction
    ? getenv('DATABASE_URL')
    : getenv('DATABASE_URL_TEST');

if (!$dbUrl) {
    throw new Exception('Database URL not set in environment');
}

$url = parse_url($dbUrl);
return array(
    'DB_TYPE'   => 'mysql',
    'DB_HOST'   => $url['host'],
    'DB_NAME'   => ltrim($url['path'], '/'),
    'DB_USER'   => $url['user'],
    'DB_PWD'    => $url['pass'],
    'DB_PORT'   => $url['port'],
    'DB_PREFIX' => 'tw_',
);
