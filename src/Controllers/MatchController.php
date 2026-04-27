<?php

namespace App\Controllers;

use App\Repositories\MatchRepository;
use App\Support\Request;
use App\Support\Response;

class MatchController
{
    public function __construct(private MatchRepository $matches)
    {
    }

    public function swipe(Request $request, int $userId): void
    {
        $payload = $request->json();
        $targetUserId = (int) ($payload['target_user_id'] ?? 0);
        $action = (string) ($payload['action'] ?? '');

        if ($targetUserId <= 0 || !in_array($action, ['like', 'super_like', 'pass', 'rewind'], true)) {
            Response::json([
                'success' => false,
                'message' => 'target_user_id และ action ไม่ถูกต้อง',
            ], 422);
        }

        if ($targetUserId === $userId) {
            Response::json([
                'success' => false,
                'message' => 'ไม่สามารถทำรายการกับตัวเองได้',
            ], 422);
        }

        $result = $this->matches->recordSwipe($userId, $targetUserId, $action);

        Response::json([
            'success' => true,
            'message' => $result['matched'] ? 'จับคู่สำเร็จ' : 'บันทึกการปัดแล้ว',
            'data' => $result,
        ], 201);
    }

    public function index(int $userId): void
    {
        Response::json([
            'success' => true,
            'data' => [
                'matches' => $this->matches->listMatches($userId),
            ],
        ]);
    }
}
