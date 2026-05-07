<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Support\ApiClient;
use Tests\Support\DatabaseTestCase;

final class PublicApiTest extends DatabaseTestCase
{
    private static function baseUrl(): string
    {
        return rtrim((string) getenv('LOVEMATCH_TEST_BASE_URL'), '/');
    }

    public function testSubscriptionPlansStructure(): void
    {
        $c = new ApiClient(self::baseUrl());
        $r = $c->get('/subscription/plans');
        $this->assertSame(200, $r['status'], $r['body']);
        $this->assertIsArray($r['json']);
        $this->assertTrue($r['json']['success'] ?? false);
        $plans = $r['json']['data']['plans'] ?? null;
        $this->assertIsArray($plans);
        $this->assertNotEmpty($plans);
        $first = $plans[0];
        $this->assertArrayHasKey('id', $first);
        $this->assertArrayHasKey('tier', $first);
        $this->assertArrayHasKey('price_thb', $first);
    }

    public function testRegistrationPaymentOptionsStructure(): void
    {
        $c = new ApiClient(self::baseUrl());
        $r = $c->get('/payments/registration-options');
        $this->assertSame(200, $r['status'], $r['body']);
        $this->assertIsArray($r['json']);
        $this->assertTrue($r['json']['success'] ?? false);
        $data = $r['json']['data'] ?? [];
        $this->assertArrayHasKey('bank_accounts', $data);
        $this->assertIsArray($data['bank_accounts']);
        $this->assertArrayHasKey('plans', $data);
    }

    public function testGiftsList(): void
    {
        $c = new ApiClient(self::baseUrl());
        $r = $c->get('/gifts');
        $this->assertSame(200, $r['status'], $r['body']);
        $this->assertTrue($r['json']['success'] ?? false);
        $this->assertIsArray($r['json']['data']['gifts'] ?? null);
    }

    public function testChatRoomsPublic(): void
    {
        $c = new ApiClient(self::baseUrl());
        $r = $c->get('/chat/rooms');
        $this->assertSame(200, $r['status'], $r['body']);
        $this->assertTrue($r['json']['success'] ?? false);
    }

    public function testDiscoverAllowsAnonymous(): void
    {
        $c = new ApiClient(self::baseUrl());
        $r = $c->get('/discover');
        $this->assertContains($r['status'], [200, 422], $r['body']);
    }
}
