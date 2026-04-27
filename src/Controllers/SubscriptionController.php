<?php

namespace App\Controllers;

use App\Repositories\SubscriptionRepository;
use App\Support\Request;
use App\Support\Response;
use RuntimeException;

class SubscriptionController
{
    public function __construct(private SubscriptionRepository $subscriptions)
    {
    }

    public function current(int $userId): void
    {
        try {
            $currentPlan = $this->subscriptions->getCurrentPlanByUserId($userId);
        } catch (RuntimeException $exception) {
            Response::json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        Response::json([
            'success' => true,
            'data' => ['plan' => $currentPlan],
        ]);
    }

    public function checkout(Request $request, int $userId): void
    {
        $payload = $request->json();
        $planId = (int) ($payload['plan_id'] ?? 0);
        $paymentMethod = (string) ($payload['payment_method'] ?? '');

        if ($planId <= 0) {
            Response::json([
                'success' => false,
                'message' => 'plan_id จำเป็นต้องส่งมา',
            ], 422);
        }

        if ($paymentMethod === '') {
            $paymentMethod = 'credit_card';
        }

        try {
            $checkout = $this->subscriptions->createCheckout($userId, $planId, $paymentMethod);
        } catch (RuntimeException $exception) {
            Response::json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        Response::json([
            'success' => true,
            'message' => $checkout['amount_thb'] > 0
                ? 'อัปเกรดแพ็กเกจสำเร็จและเปิดใช้งานทันที'
                : 'เปิดใช้งานแพ็กเกจ Free เรียบร้อย',
            'data' => ['checkout' => $checkout],
        ], 201);
    }
}
