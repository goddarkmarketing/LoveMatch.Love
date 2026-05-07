<?php

declare(strict_types=1);

namespace Tests\Smoke;

use PHPUnit\Framework\TestCase;
use Tests\Support\ApiClient;

/**
 * /health ไม่ต้องใช้ MySQL
 */
final class HealthEndpointTest extends TestCase
{
    public function testHealthReturnsOkWithoutDatabase(): void
    {
        $base = rtrim((string) getenv('LOVEMATCH_TEST_BASE_URL'), '/');
        $c = new ApiClient($base);
        $r = $c->get('/health');
        $this->assertSame(200, $r['status'], $r['body']);
        $this->assertTrue($r['json']['success'] ?? false);
    }
}
