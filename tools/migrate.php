<?php

/**
 * Migration runner — applies every db/migrations/*.sql in numeric order
 * and records applied files in `schema_migrations`.
 *
 * Usage (CLI on hosting via SSH):
 *   php tools/migrate.php
 *
 * Usage (browser fallback when SSH not available):
 *   https://your-host/tools/migrate.php?key=YOUR_SECRET
 *
 * The secret is read from env MIGRATE_KEY, or config/migrate.local.php
 * (returning ['key' => '...']). Without a secret set, browser execution
 * is refused. CLI execution always allowed.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Support/Database.php';

use App\Support\Database;

$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
    $expected = getenv('MIGRATE_KEY') ?: '';
    $localKeyFile = dirname(__DIR__) . '/config/migrate.local.php';
    if ($expected === '' && is_file($localKeyFile)) {
        $local = require $localKeyFile;
        if (is_array($local) && isset($local['key'])) {
            $expected = (string) $local['key'];
        }
    }
    $provided = (string) ($_GET['key'] ?? '');
    if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
        http_response_code(403);
        echo "Forbidden. Set MIGRATE_KEY (env or config/migrate.local.php) and call with ?key=...\n";
        exit;
    }
}

$migrationsDir = dirname(__DIR__) . '/db/migrations';

try {
    $pdo = Database::connection();
} catch (Throwable $exception) {
    fwrite(STDERR, 'DB connect failed: ' . $exception->getMessage() . "\n");
    if (!$isCli) {
        echo 'DB connect failed: ' . $exception->getMessage() . "\n";
    }
    exit(1);
}

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        filename VARCHAR(190) NOT NULL PRIMARY KEY,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$applied = $pdo->query('SELECT filename FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
$applied = is_array($applied) ? array_flip($applied) : [];

$files = glob($migrationsDir . '/*.sql') ?: [];
sort($files, SORT_NATURAL);

$applied_count = 0;
foreach ($files as $file) {
    $name = basename($file);
    if (isset($applied[$name])) {
        echo "[skip] {$name}\n";
        continue;
    }

    $sql = file_get_contents($file);
    if ($sql === false || trim($sql) === '') {
        echo "[empty] {$name}\n";
        continue;
    }

    echo "[apply] {$name} ... ";
    try {
        $pdo->exec($sql);
        $insert = $pdo->prepare('INSERT INTO schema_migrations (filename) VALUES (:filename)');
        $insert->execute(['filename' => $name]);
        $applied_count++;
        echo "OK\n";
    } catch (Throwable $exception) {
        echo "FAIL\n";
        echo '  ' . $exception->getMessage() . "\n";
        exit(1);
    }
}

echo "Done. Applied {$applied_count} migration(s).\n";
