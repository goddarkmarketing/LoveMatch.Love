<?php

namespace App\Support;

class Response
{
    public static function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function download(string $filepath, string $downloadName, string $contentType = 'application/octet-stream'): void
    {
        if (!is_file($filepath) || !is_readable($filepath)) {
            self::json([
                'success' => false,
                'message' => 'ไม่พบไฟล์ที่ต้องการดาวน์โหลด',
            ], 404);
        }

        http_response_code(200);
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $downloadName) . '"');
        header('Content-Length: ' . (string) filesize($filepath));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        readfile($filepath);
        exit;
    }
}
