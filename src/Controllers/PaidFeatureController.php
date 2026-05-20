<?php

namespace App\Controllers;

use App\Repositories\PaidFeatureRepository;
use App\Support\Request;
use App\Support\Response;
use RuntimeException;

class PaidFeatureController
{
    public function __construct(private PaidFeatureRepository $features)
    {
    }

    public function products(): void
    {
        Response::json([
            'success' => true,
            'data' => [
                'products' => $this->features->listProducts(),
            ],
        ]);
    }

    public function unlockContact(Request $request, int $userId): void
    {
        $payload = $request->json();
        $targetUserId = (int) ($payload['target_user_id'] ?? 0);
        $productCode = (string) ($payload['product_code'] ?? '');

        try {
            $result = $this->features->unlockContact($userId, $targetUserId, $productCode);
        } catch (RuntimeException $exception) {
            Response::json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        Response::json([
            'success' => true,
            'message' => $result['already_unlocked'] ? 'ช่องทางนี้เคยปลดล็อกแล้ว' : 'ปลดล็อกช่องทางติดต่อสำเร็จ',
            'data' => $result,
        ], 201);
    }

    public function sendCrush(Request $request, int $userId): void
    {
        $payload = $request->json();
        $targetUserId = (int) ($payload['target_user_id'] ?? 0);
        $message = (string) ($payload['message_text'] ?? '');

        try {
            $result = $this->features->sendCrush($userId, $targetUserId, $message);
        } catch (RuntimeException $exception) {
            Response::json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        Response::json([
            'success' => true,
            'message' => 'ส่ง Crush สำเร็จ',
            'data' => $result,
        ], 201);
    }

    public function unlockChat(Request $request, int $userId): void
    {
        $payload = $request->json();
        $targetUserId = (int) ($payload['target_user_id'] ?? 0);

        try {
            $result = $this->features->unlockChat($userId, $targetUserId);
        } catch (RuntimeException $exception) {
            Response::json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        Response::json([
            'success' => true,
            'message' => $result['already_unlocked'] ? 'เคยปลดล็อกแชทนี้แล้ว' : 'ปลดล็อกแชทก่อนแมทช์สำเร็จ',
            'data' => $result,
        ], 201);
    }

    public function boostProfile(Request $request, int $userId): void
    {
        $payload = $request->json();
        $productCode = (string) ($payload['product_code'] ?? 'profile_boost_1h');

        try {
            $result = $this->features->boostProfile($userId, $productCode);
        } catch (RuntimeException $exception) {
            Response::json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        Response::json([
            'success' => true,
            'message' => 'บูสต์โปรไฟล์สำเร็จ',
            'data' => $result,
        ], 201);
    }
}
