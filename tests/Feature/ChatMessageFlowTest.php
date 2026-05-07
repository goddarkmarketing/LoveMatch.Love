<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Support\ApiClient;
use Tests\Support\DatabaseTestCase;

final class ChatMessageFlowTest extends DatabaseTestCase
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

    public function testListRoomsAndSendPublicMessage(): void
    {
        $c = $this->loginMember();
        $rooms = $c->get('/chat/rooms');
        $this->assertSame(200, $rooms['status'], $rooms['body']);
        $list = $rooms['json']['data']['rooms'] ?? [];
        $this->assertNotEmpty($list, 'ต้องมีห้องแชทสาธารณะในฐานข้อมูล');
        $roomId = (int) ($list[0]['id'] ?? 0);
        $this->assertGreaterThan(0, $roomId);

        $msg = $c->post('/chat/rooms/' . $roomId . '/messages', [
            'body' => 'phpunit chat ' . bin2hex(random_bytes(3)),
        ]);
        $this->assertSame(201, $msg['status'], $msg['body']);
        $this->assertTrue($msg['json']['success'] ?? false);

        $hist = $c->get('/chat/rooms/' . $roomId . '/messages');
        $this->assertSame(200, $hist['status'], $hist['body']);
    }
}
