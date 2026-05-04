<?php

namespace App\Support;

use PDO;
use PDOException;
use RuntimeException;

class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $config = require dirname(__DIR__, 2) . '/config/database.php';
        $dsn = sprintf(
            '%s:host=%s;port=%d;dbname=%s;charset=%s',
            $config['driver'],
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );

        try {
            self::$connection = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $exception) {
            $detail = $exception->getMessage();
            $msg = 'Database connection failed: ' . $detail;
            if (strpos($detail, '1698') !== false || strpos($detail, "Access denied for user 'root'") !== false) {
                $msg .= ' — บน Linux มักต้องใช้ user แอปที่มีรหัสผ่าน (ไม่ใช้ root); ดู config/database.local.php.example';
            }
            throw new RuntimeException($msg);
        }

        return self::$connection;
    }
}
