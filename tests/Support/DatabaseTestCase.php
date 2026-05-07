<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\TestCase;

/**
 * Skips tests when API cannot reach MySQL (subscription/plans fails).
 */
abstract class DatabaseTestCase extends TestCase
{
    private static ?bool $databaseAvailable = null;

    protected function setUp(): void
    {
        parent::setUp();
        if (self::$databaseAvailable === null) {
            $base = rtrim((string) getenv('LOVEMATCH_TEST_BASE_URL'), '/');
            $c = new ApiClient($base);
            $r = $c->get('/subscription/plans');
            self::$databaseAvailable = $r['status'] === 200 && ($r['json']['success'] ?? false);
        }
        if (!self::$databaseAvailable) {
            $this->markTestSkipped(
                'ฐานข้อมูลไม่พร้อม: เปิด MySQL, import db/schema.sql และ seed, หรือตั้ง LOVEMATCH_TEST_BASE_URL ให้ชี้เซิร์ฟเวอร์ที่ต่อ DB ได้'
            );
        }
    }
}
