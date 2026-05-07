<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Support\ApiClient;
use Tests\Support\DatabaseTestCase;

final class AdminApiTest extends DatabaseTestCase
{
    private static function baseUrl(): string
    {
        return rtrim((string) getenv('LOVEMATCH_TEST_BASE_URL'), '/');
    }

    private function loginAdmin(): ApiClient
    {
        $c = new ApiClient(self::baseUrl());
        $r = $c->post('/auth/login', [
            'email' => (string) getenv('LOVEMATCH_TEST_ADMIN_EMAIL'),
            'password' => (string) getenv('LOVEMATCH_TEST_ADMIN_PASSWORD'),
        ]);
        $this->assertSame(200, $r['status'], $r['body']);

        return $c;
    }

    public function testMemberCannotAccessAdminDashboard(): void
    {
        $c = new ApiClient(self::baseUrl());
        $c->post('/auth/login', [
            'email' => (string) getenv('LOVEMATCH_TEST_MEMBER_EMAIL'),
            'password' => (string) getenv('LOVEMATCH_TEST_MEMBER_PASSWORD'),
        ]);
        $r = $c->get('/admin/dashboard');
        $this->assertSame(403, $r['status'], $r['body']);
    }

    public function testAdminDashboardShape(): void
    {
        $c = $this->loginAdmin();
        $r = $c->get('/admin/dashboard');
        $this->assertSame(200, $r['status'], $r['body']);
        $data = $r['json']['data'] ?? [];
        $this->assertArrayHasKey('stats', $data);
        $this->assertArrayHasKey('pending_subscription_upgrades', $data);
        $this->assertIsArray($data['pending_subscription_upgrades']);
    }

    public function testApproveNonexistentSubscriptionReturns422(): void
    {
        $c = $this->loginAdmin();
        $r = $c->post('/admin/subscriptions/999999999/approve', []);
        $this->assertSame(422, $r['status'], $r['body']);
    }
}
