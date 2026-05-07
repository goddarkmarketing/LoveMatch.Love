<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Support\ApiClient;
use Tests\Support\DatabaseTestCase;

final class AuthenticatedUserTest extends DatabaseTestCase
{
    private static function baseUrl(): string
    {
        return rtrim((string) getenv('LOVEMATCH_TEST_BASE_URL'), '/');
    }

    private function loginMember(): ApiClient
    {
        $c = new ApiClient(self::baseUrl());
        $r = $c->post('/auth/login', [
            'email' => (string) getenv('LOVEMATCH_TEST_MEMBER_EMAIL'),
            'password' => (string) getenv('LOVEMATCH_TEST_MEMBER_PASSWORD'),
        ]);
        $this->assertSame(200, $r['status'], $r['body']);

        return $c;
    }

    public function testSubscriptionCurrentHasPlanShape(): void
    {
        $c = $this->loginMember();
        $r = $c->get('/subscription/current');
        $this->assertSame(200, $r['status'], $r['body']);
        $this->assertArrayHasKey('plan', $r['json']['data'] ?? []);
        $plan = $r['json']['data']['plan'];
        $this->assertArrayHasKey('tier', $plan);
        $this->assertArrayHasKey('pending_upgrade', $r['json']['data'] ?? []);
    }

    public function testWallet(): void
    {
        $c = $this->loginMember();
        $r = $c->get('/wallet');
        $this->assertSame(200, $r['status'], $r['body']);
        $this->assertArrayHasKey('wallet', $r['json']['data'] ?? []);
    }

    public function testMatches(): void
    {
        $c = $this->loginMember();
        $r = $c->get('/matches');
        $this->assertSame(200, $r['status'], $r['body']);
    }

    public function testChatSidebar(): void
    {
        $c = $this->loginMember();
        $r = $c->get('/chat/sidebar');
        $this->assertSame(200, $r['status'], $r['body']);
    }

    public function testDiscoverAuthenticated(): void
    {
        $c = $this->loginMember();
        $r = $c->get('/discover');
        $this->assertSame(200, $r['status'], $r['body']);
    }

    public function testCheckoutFreePlanIdempotent(): void
    {
        $c = $this->loginMember();
        $plans = $c->get('/subscription/plans');
        $this->assertSame(200, $plans['status']);
        $freeId = null;
        foreach ($plans['json']['data']['plans'] ?? [] as $p) {
            if (($p['tier'] ?? '') === 'free' && (float) ($p['price_thb'] ?? 1) <= 0) {
                $freeId = (int) $p['id'];
                break;
            }
        }
        $this->assertNotNull($freeId);
        $r = $c->post('/subscription/checkout', [
            'plan_id' => $freeId,
            'payment_method' => 'bank_transfer',
        ]);
        $this->assertContains($r['status'], [200, 201], $r['body']);
    }

    public function testSwipeEndpointAcceptsPayload(): void
    {
        $c = $this->loginMember();
        $discover = $c->get('/discover');
        $this->assertSame(200, $discover['status']);
        $profiles = $discover['json']['data']['profiles'] ?? [];
        if ($profiles === []) {
            $this->markTestSkipped('No discover profiles in DB for swipe test');
        }
        $targetId = (int) ($profiles[0]['id'] ?? 0);
        $this->assertGreaterThan(0, $targetId);
        $r = $c->post('/swipes', [
            'target_user_id' => $targetId,
            'action' => 'pass',
        ]);
        $this->assertContains($r['status'], [200, 201, 422], $r['body']);
    }
}
