<?php

namespace App\Support;

use PDO;
use Throwable;

/**
 * Lightweight auto-migrator that runs at the start of an API request when
 * new SQL files have been added to db/migrations. Uses a single touch file
 * for fast no-op on hot paths and a file lock for safe concurrency.
 *
 * Disabled when env LM_DISABLE_AUTO_MIGRATE=1 or file
 * config/disable-auto-migrate is present.
 */
class MigrationAutoRunner
{
    public static function runIfNeeded(PDO $pdo): void
    {
        try {
            if (self::isDisabled()) {
                return;
            }

            $migrationsDir = dirname(__DIR__, 2) . '/db/migrations';
            if (!is_dir($migrationsDir)) {
                return;
            }

            $files = glob($migrationsDir . '/*.sql') ?: [];
            if (!$files) {
                return;
            }
            sort($files, SORT_NATURAL);

            $signature = self::computeSignature($files);
            $touchFile = sys_get_temp_dir() . '/lovematch-migrate-' . md5(__DIR__) . '.touch';

            if (is_file($touchFile) && trim((string) @file_get_contents($touchFile)) === $signature) {
                return;
            }

            $lockFile = sys_get_temp_dir() . '/lovematch-migrate-' . md5(__DIR__) . '.lock';
            $lockHandle = @fopen($lockFile, 'c');
            if ($lockHandle === false) {
                return;
            }

            if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
                fclose($lockHandle);
                return;
            }

            try {
                self::ensureSchemaMigrationsTable($pdo);
                $applied = $pdo->query('SELECT filename FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
                $appliedSet = is_array($applied) ? array_flip($applied) : [];

                foreach ($files as $file) {
                    $name = basename($file);
                    if (isset($appliedSet[$name])) {
                        continue;
                    }

                    $sql = file_get_contents($file);
                    if ($sql === false || trim($sql) === '') {
                        continue;
                    }

                    $pdo->exec($sql);
                    $insert = $pdo->prepare('INSERT INTO schema_migrations (filename) VALUES (:filename)');
                    $insert->execute(['filename' => $name]);
                }

                @file_put_contents($touchFile, $signature);
            } finally {
                flock($lockHandle, LOCK_UN);
                fclose($lockHandle);
            }
        } catch (Throwable $exception) {
            error_log('[MigrationAutoRunner] ' . $exception->getMessage());
        }
    }

    private static function isDisabled(): bool
    {
        if (getenv('LM_DISABLE_AUTO_MIGRATE') === '1') {
            return true;
        }
        $marker = dirname(__DIR__, 2) . '/config/disable-auto-migrate';
        return is_file($marker);
    }

    private static function computeSignature(array $files): string
    {
        $hash = hash_init('sha256');
        foreach ($files as $file) {
            hash_update($hash, basename($file));
            $mtime = @filemtime($file);
            hash_update($hash, '|' . ($mtime ?: 0) . '|' . (@filesize($file) ?: 0) . "\n");
        }
        return hash_final($hash);
    }

    private static function ensureSchemaMigrationsTable(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                filename VARCHAR(190) NOT NULL PRIMARY KEY,
                applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
}
