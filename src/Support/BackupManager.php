<?php

namespace App\Support;

use PDO;
use RuntimeException;
use ZipArchive;

class BackupManager
{
    private const FILENAME_PATTERN = '/^lovematch-backup-\d{8}-\d{6}\.zip$/';

    public function __construct(
        private PDO $db,
        private array $databaseConfig,
        private string $projectRoot
    ) {
    }

    public function storageDir(): string
    {
        $dir = $this->projectRoot . '/storage/backups';
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new RuntimeException('ไม่สามารถสร้างโฟลเดอร์เก็บแบ็คอัพได้');
        }

        return $dir;
    }

    /**
     * @return list<array{id: string, filename: string, created_at: string, size_bytes: int, created_by: ?string}>
     */
    public function list(): array
    {
        $dir = $this->storageDir();
        $files = glob($dir . '/lovematch-backup-*.zip') ?: [];
        $items = [];

        foreach ($files as $path) {
            $filename = basename($path);
            if (!preg_match(self::FILENAME_PATTERN, $filename)) {
                continue;
            }

            $meta = $this->readManifest($path);
            $items[] = [
                'id' => $filename,
                'filename' => $filename,
                'created_at' => (string) ($meta['created_at'] ?? date('Y-m-d H:i:s', (int) filemtime($path))),
                'size_bytes' => (int) filesize($path),
                'created_by' => isset($meta['created_by']) ? (string) $meta['created_by'] : null,
            ];
        }

        usort($items, static function (array $a, array $b): int {
            return strcmp($b['created_at'], $a['created_at']);
        });

        return $items;
    }

    /**
     * @return array{id: string, filename: string, created_at: string, size_bytes: int, created_by: ?string}
     */
    public function create(int $adminUserId, string $adminDisplayName): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('เซิร์ฟเวอร์ยังไม่ได้เปิด PHP extension ZipArchive');
        }

        @set_time_limit(300);

        $createdAt = date('Y-m-d H:i:s');
        $filename = 'lovematch-backup-' . date('Ymd-His') . '.zip';
        $destination = $this->storageDir() . '/' . $filename;
        $tempSql = $this->storageDir() . '/.tmp-' . bin2hex(random_bytes(8)) . '.sql';

        try {
            $this->dumpDatabaseToFile($tempSql);

            $zip = new ZipArchive();
            if ($zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('ไม่สามารถสร้างไฟล์แบ็คอัพได้');
            }

            $manifest = [
                'app' => 'LoveMatch.Love',
                'created_at' => $createdAt,
                'created_by_user_id' => $adminUserId,
                'created_by' => $adminDisplayName,
                'database' => (string) ($this->databaseConfig['database'] ?? ''),
                'includes' => [
                    'database.sql',
                    'files/uploads',
                    'files/config',
                    'files/db',
                ],
            ];
            $zip->addFromString('manifest.json', json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $zip->addFile($tempSql, 'database.sql');

            $this->addDirectoryToZip($zip, $this->projectRoot . '/uploads', 'files/uploads');
            $this->addConfigFilesToZip($zip);
            $this->addDirectoryToZip($zip, $this->projectRoot . '/db', 'files/db');

            $zip->close();
        } finally {
            if (is_file($tempSql)) {
                @unlink($tempSql);
            }
        }

        if (!is_file($destination)) {
            throw new RuntimeException('สร้างแบ็คอัพไม่สำเร็จ');
        }

        return [
            'id' => $filename,
            'filename' => $filename,
            'created_at' => $createdAt,
            'size_bytes' => (int) filesize($destination),
            'created_by' => $adminDisplayName,
        ];
    }

    public function delete(string $filename): void
    {
        $path = $this->resolveBackupPath($filename);
        if (!unlink($path)) {
            throw new RuntimeException('ลบแบ็คอัพไม่สำเร็จ');
        }
    }

    public function resolveBackupPath(string $filename): string
    {
        $safeName = basename($filename);
        if (!preg_match(self::FILENAME_PATTERN, $safeName)) {
            throw new RuntimeException('ชื่อไฟล์แบ็คอัพไม่ถูกต้อง');
        }

        $path = $this->storageDir() . '/' . $safeName;
        if (!is_file($path)) {
            throw new RuntimeException('ไม่พบไฟล์แบ็คอัพ');
        }

        return $path;
    }

    private function dumpDatabaseToFile(string $destination): void
    {
        if ($this->tryShellDump($destination)) {
            return;
        }

        $this->dumpDatabaseWithPdo($destination);
    }

    private function tryShellDump(string $destination): bool
    {
        $database = (string) ($this->databaseConfig['database'] ?? '');
        if ($database === '') {
            return false;
        }

        $host = (string) ($this->databaseConfig['host'] ?? '127.0.0.1');
        $port = (int) ($this->databaseConfig['port'] ?? 3306);
        $username = (string) ($this->databaseConfig['username'] ?? 'root');
        $password = (string) ($this->databaseConfig['password'] ?? '');

        $candidates = ['mysqldump'];
        if (DIRECTORY_SEPARATOR === '\\') {
            $candidates[] = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
        }

        foreach ($candidates as $binary) {
            $command = escapeshellarg($binary)
                . ' --host=' . escapeshellarg($host)
                . ' --port=' . escapeshellarg((string) $port)
                . ' --user=' . escapeshellarg($username)
                . ' --default-character-set=utf8mb4'
                . ' --single-transaction'
                . ' --routines'
                . ' --triggers'
                . ' ' . escapeshellarg($database);

            if ($password !== '') {
                $command .= ' --password=' . escapeshellarg($password);
            }

            $command .= ' > ' . escapeshellarg($destination) . ' 2>&1';

            $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
            if (in_array('exec', $disabled, true) || !function_exists('exec')) {
                return false;
            }

            $output = [];
            $exitCode = 1;
            @exec($command, $output, $exitCode);

            if ($exitCode === 0 && is_file($destination) && filesize($destination) > 32) {
                return true;
            }

            if (is_file($destination)) {
                @unlink($destination);
            }
        }

        return false;
    }

    private function dumpDatabaseWithPdo(string $destination): void
    {
        $handle = fopen($destination, 'wb');
        if ($handle === false) {
            throw new RuntimeException('ไม่สามารถเขียนไฟล์ฐานข้อมูลชั่วคราวได้');
        }

        try {
            fwrite($handle, "-- LoveMatch.Love database backup\n");
            fwrite($handle, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

            $tables = $this->db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tables as $table) {
                $tableName = (string) $table;
                $createStatement = $this->db->query('SHOW CREATE TABLE `' . str_replace('`', '``', $tableName) . '`')->fetch(PDO::FETCH_ASSOC);
                if (!$createStatement) {
                    continue;
                }

                fwrite($handle, 'DROP TABLE IF EXISTS `' . str_replace('`', '``', $tableName) . "`;\n");
                fwrite($handle, (string) ($createStatement['Create Table'] ?? $createStatement['Create View'] ?? '') . ";\n\n");

                $rows = $this->db->query('SELECT * FROM `' . str_replace('`', '``', $tableName) . '`');
                while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
                    $columns = array_map(static fn (string $column): string => '`' . str_replace('`', '``', $column) . '`', array_keys($row));
                    $values = array_map(function (mixed $value): string {
                        if ($value === null) {
                            return 'NULL';
                        }
                        if (is_int($value) || is_float($value)) {
                            return (string) $value;
                        }

                        return $this->db->quote((string) $value);
                    }, array_values($row));

                    fwrite(
                        $handle,
                        'INSERT INTO `' . str_replace('`', '``', $tableName) . '` (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ");\n"
                    );
                }

                fwrite($handle, "\n");
            }

            fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        } finally {
            fclose($handle);
        }
    }

    private function addConfigFilesToZip(ZipArchive $zip): void
    {
        $configDir = $this->projectRoot . '/config';
        $files = [
            'app.php',
            'payments.php',
            'database.php',
            'database.local.php',
            'payments.local.php',
            'migrate.local.php',
        ];

        foreach ($files as $file) {
            $path = $configDir . '/' . $file;
            if (is_file($path)) {
                $zip->addFile($path, 'files/config/' . $file);
            }
        }
    }

    private function addDirectoryToZip(ZipArchive $zip, string $sourcePath, string $zipPrefix): void
    {
        if (!is_dir($sourcePath)) {
            return;
        }

        $root = realpath($sourcePath);
        if ($root === false) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $fileInfo) {
            /** @var \SplFileInfo $fileInfo */
            $filePath = $fileInfo->getRealPath();
            if ($filePath === false) {
                continue;
            }

            $relative = $zipPrefix . '/' . ltrim(str_replace('\\', '/', substr($filePath, strlen($root))), '/');
            if ($fileInfo->isDir()) {
                $zip->addEmptyDir($relative);
                continue;
            }

            $zip->addFile($filePath, $relative);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function readManifest(string $zipPath): array
    {
        if (!class_exists(ZipArchive::class)) {
            return [];
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return [];
        }

        $raw = $zip->getFromName('manifest.json');
        $zip->close();

        if ($raw === false) {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
