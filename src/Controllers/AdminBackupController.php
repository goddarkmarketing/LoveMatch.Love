<?php

namespace App\Controllers;

use App\Support\BackupManager;
use App\Support\Response;
use RuntimeException;

class AdminBackupController
{
    public function __construct(private BackupManager $backups)
    {
    }

    public function index(): void
    {
        Response::json([
            'success' => true,
            'data' => [
                'backups' => $this->backups->list(),
            ],
        ]);
    }

    public function create(int $adminUserId, string $adminDisplayName): void
    {
        try {
            $backup = $this->backups->create($adminUserId, $adminDisplayName);
        } catch (RuntimeException $exception) {
            Response::json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 500);
        }

        Response::json([
            'success' => true,
            'message' => 'สร้างแบ็คอัพเรียบร้อยแล้ว',
            'data' => ['backup' => $backup],
        ], 201);
    }

    public function download(string $filename): void
    {
        try {
            $path = $this->backups->resolveBackupPath($filename);
        } catch (RuntimeException $exception) {
            Response::json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 404);
        }

        Response::download($path, basename($path), 'application/zip');
    }

    public function delete(string $filename): void
    {
        try {
            $this->backups->delete($filename);
        } catch (RuntimeException $exception) {
            Response::json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        Response::json([
            'success' => true,
            'message' => 'ลบแบ็คอัพเรียบร้อยแล้ว',
        ]);
    }
}
