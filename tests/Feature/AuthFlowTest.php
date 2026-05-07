<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Support\ApiClient;
use Tests\Support\DatabaseTestCase;

final class AuthFlowTest extends DatabaseTestCase
{
    private static function baseUrl(): string
    {
        return rtrim((string) getenv('LOVEMATCH_TEST_BASE_URL'), '/');
    }

    public function testLoginLogoutMeCycle(): void
    {
        $email = (string) getenv('LOVEMATCH_TEST_MEMBER_EMAIL');
        $password = (string) getenv('LOVEMATCH_TEST_MEMBER_PASSWORD');
        $c = new ApiClient(self::baseUrl());

        $me1 = $c->get('/auth/me');
        $this->assertSame(401, $me1['status']);

        $login = $c->post('/auth/login', [
            'email' => $email,
            'password' => $password,
        ]);
        $this->assertSame(200, $login['status'], $login['body']);
        $this->assertTrue($login['json']['success'] ?? false);

        $me2 = $c->get('/auth/me');
        $this->assertSame(200, $me2['status'], $me2['body']);
        $this->assertSame($email, $me2['json']['data']['user']['email'] ?? null);

        $out = $c->post('/auth/logout', []);
        $this->assertSame(200, $out['status'], $out['body']);

        $me3 = $c->get('/auth/me');
        $this->assertSame(401, $me3['status']);
    }

    public function testRegisterFreePlanCreatesSession(): void
    {
        $c = new ApiClient(self::baseUrl());
        $plans = $c->get('/subscription/plans');
        $this->assertSame(200, $plans['status']);
        $free = null;
        foreach ($plans['json']['data']['plans'] ?? [] as $p) {
            if (($p['tier'] ?? '') === 'free' && (float) ($p['price_thb'] ?? 1) <= 0) {
                $free = $p;
                break;
            }
        }
        $this->assertNotNull($free, 'Need a free tier plan in DB');

        $suffix = bin2hex(random_bytes(4));
        $reg = $c->post('/auth/register', [
            'first_name' => 'Test',
            'last_name' => 'User' . $suffix,
            'email' => 'phpunit_free_' . $suffix . '@example.com',
            'password' => 'testpass1',
            'gender' => 'male',
            'interested_in' => 'female',
            'plan_id' => (int) $free['id'],
        ]);
        $this->assertSame(201, $reg['status'], $reg['body']);
        $this->assertTrue($reg['json']['success'] ?? false);

        $me = $c->get('/auth/me');
        $this->assertSame(200, $me['status'], $me['body']);
    }
}
