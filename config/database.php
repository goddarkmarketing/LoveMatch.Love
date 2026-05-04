<?php

/**
 * Database config. Override via:
 *  - Environment: DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD, DB_DRIVER
 *  - File: config/database.local.php (return an array of keys to merge; gitignored)
 *
 * MySQL error 1698 on Linux: user `root` often uses auth_socket — create a dedicated
 * SQL user with a password (see config/database.local.php.example).
 */

$config = [
    'driver' => 'mysql',
    'host' => '127.0.0.1',
    'port' => 3306,
    'database' => 'lovematch_love',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
];

$localPath = __DIR__ . '/database.local.php';
if (is_file($localPath)) {
    $local = require $localPath;
    if (is_array($local)) {
        $config = array_merge($config, $local);
    }
}

$envString = static function (string $key): ?string {
    $v = getenv($key);
    if ($v === false) {
        return null;
    }
    $v = is_string($v) ? trim($v) : (string) $v;
    return $v === '' ? null : $v;
};

if (($v = $envString('DB_DRIVER')) !== null) {
    $config['driver'] = $v;
}
if (($v = $envString('DB_HOST')) !== null) {
    $config['host'] = $v;
}
if (($v = $envString('DB_PORT')) !== null) {
    $config['port'] = (int) $v;
}
if (($v = $envString('DB_DATABASE')) !== null) {
    $config['database'] = $v;
}
if (($v = $envString('DB_USERNAME')) !== null) {
    $config['username'] = $v;
}
if (getenv('DB_PASSWORD') !== false) {
    $config['password'] = (string) getenv('DB_PASSWORD');
}
if (($v = $envString('DB_CHARSET')) !== null) {
    $config['charset'] = $v;
}

return $config;
