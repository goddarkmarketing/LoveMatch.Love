<?php

namespace App\Controllers;

use App\Repositories\MatchSignalRepository;
use App\Support\Request;
use App\Support\Response;

class MatchSignalController
{
    public function __construct(private MatchSignalRepository $signals)
    {
    }

    public function wall(): void
    {
        Response::json([
            'success' => true,
            'data' => [
                'announcements' => $this->signals->listWallAnnouncements(),
                'top_pairs' => $this->signals->listTopPairs(),
            ],
        ]);
    }

    public function profileView(Request $request, int $userId): void
    {
        $payload = $request->json();
        $targetUserId = (int) ($payload['target_user_id'] ?? 0);

        if ($targetUserId <= 0 || $targetUserId === $userId) {
            Response::json([
                'success' => false,
                'message' => 'target_user_id ไม่ถูกต้อง',
            ], 422);
        }

        $announcement = $this->signals->recordProfileView($userId, $targetUserId);

        Response::json([
            'success' => true,
            'message' => 'บันทึกการดูโปรไฟล์แล้ว',
            'data' => [
                'announcement' => $announcement ?: null,
            ],
        ], 201);
    }
}
