<?php

namespace App\Controllers;

use App\Repositories\PaymentRepository;
use App\Repositories\SubscriptionRepository;
use App\Support\OmiseClient;
use App\Support\Request;
use App\Support\Response;
use PDO;
use RuntimeException;

class SubscriptionController
{
    public function __construct(
        private SubscriptionRepository $subscriptions,
        private PaymentRepository $payments,
        private array $paymentConfig,
        private PDO $pdo
    ) {
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

            return;
        }

        $pendingUpgrade = $this->subscriptions->getPendingUpgradeForUser($userId);

        Response::json([
            'success' => true,
            'data' => [
                'plan' => $currentPlan,
                'pending_upgrade' => $pendingUpgrade,
            ],
        ]);
    }

    public function checkout(Request $request, int $userId): void
    {
        $payload = $request->json();
        $planId = (int) ($payload['plan_id'] ?? 0);
        $paymentMethod = trim((string) ($payload['payment_method'] ?? ''));
        if ($paymentMethod === '') {
            $paymentMethod = 'credit_card';
        }
        $omiseToken = trim((string) ($payload['omise_token'] ?? ''));

        if ($planId <= 0) {
            Response::json([
                'success' => false,
                'message' => 'plan_id จำเป็นต้องส่งมา',
            ], 422);

            return;
        }

        try {
            $currentPlan = $this->subscriptions->getCurrentPlanByUserId($userId);
        } catch (RuntimeException $exception) {
            Response::json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);

            return;
        }

        if ((int) $currentPlan['id'] === $planId) {
            Response::json([
                'success' => false,
                'message' => 'คุณใช้แพ็กเกจนี้อยู่แล้ว',
            ], 422);

            return;
        }

        $plan = $this->subscriptions->findPlanById($planId);
        if (!$plan) {
            Response::json([
                'success' => false,
                'message' => 'ไม่พบแพ็กเกจที่เลือก',
            ], 422);

            return;
        }

        $isFreePlan = (float) $plan['price_thb'] <= 0;

        if ($isFreePlan) {
            try {
                $checkout = $this->subscriptions->activateFreePlanForUser($userId, $planId);
            } catch (RuntimeException $exception) {
                Response::json([
                    'success' => false,
                    'message' => $exception->getMessage(),
                ], 422);

                return;
            }

            Response::json([
                'success' => true,
                'message' => 'เปิดใช้งานแพ็กเกจ Free เรียบร้อย',
                'data' => ['checkout' => $checkout],
            ], 201);

            return;
        }

        if ($this->subscriptions->getPendingUpgradeForUser($userId)) {
            Response::json([
                'success' => false,
                'message' => 'คุณมีคำขออัปเกรดที่รออนุมัติอยู่แล้ว กรุณารอแอดมินตรวจสอบ',
            ], 409);

            return;
        }

        if (!in_array($paymentMethod, ['bank_transfer', 'credit_card'], true)) {
            Response::json([
                'success' => false,
                'message' => 'เลือกวิธีชำระเงิน: bank_transfer หรือ credit_card',
            ], 422);

            return;
        }

        if ($paymentMethod === 'credit_card' && trim((string) ($this->paymentConfig['omise_secret_key'] ?? '')) === '') {
            Response::json([
                'success' => false,
                'message' => 'ระบบยังไม่ตั้งค่า Omise (secret key) สำหรับรับบัตร',
            ], 503);

            return;
        }

        if ($paymentMethod === 'credit_card' && $omiseToken === '') {
            Response::json([
                'success' => false,
                'message' => 'กรุณากรอกข้อมูลบัตรให้ครบ หรือเลือกโอนผ่านธนาคาร',
            ], 422);

            return;
        }

        $this->pdo->beginTransaction();

        try {
            $pending = $this->subscriptions->insertPendingSubscriptionForUpgrade($userId, $planId);
            $subscriptionId = $pending['subscription_id'];
            $price = (float) $pending['amount_thb'];

            if ($paymentMethod === 'credit_card') {
                $omise = new OmiseClient();
                $satang = (int) round($price * 100);
                $result = $omise->createCharge(
                    (string) $this->paymentConfig['omise_secret_key'],
                    $satang,
                    $omiseToken,
                    [
                        'purpose' => 'subscription_upgrade',
                        'plan_id' => (string) $planId,
                        'plan_code' => (string) $plan['code'],
                        'user_id' => (string) $userId,
                    ]
                );

                if (!$result['ok']) {
                    $this->pdo->rollBack();
                    Response::json([
                        'success' => false,
                        'message' => $result['message'] ?? 'ชำระเงินด้วยบัตรไม่สำเร็จ',
                    ], 402);

                    return;
                }

                $paymentId = $this->payments->createSubscriptionPayment(
                    $userId,
                    $subscriptionId,
                    'credit_card',
                    $price,
                    $result['charge_id'] ?? null,
                    'paid'
                );
            } else {
                $paymentId = $this->payments->createSubscriptionPayment(
                    $userId,
                    $subscriptionId,
                    'bank_transfer',
                    $price,
                    null,
                    'pending'
                );
            }

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            Response::json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 500);

            return;
        }

        $checkout = [
            'subscription_id' => $subscriptionId,
            'payment_id' => $paymentId,
            'plan_name' => $pending['plan_name'],
            'plan_tier' => $pending['plan_tier'],
            'amount_thb' => $price,
            'status' => 'pending_activation',
            'current_plan' => $this->subscriptions->getCurrentPlanByUserId($userId),
        ];

        $data = ['checkout' => $checkout];

        if ($paymentMethod === 'bank_transfer') {
            $data['upgrade_payment'] = $this->buildUpgradePaymentPayload($plan, $price);
        }

        $message = $paymentMethod === 'bank_transfer'
            ? 'บันทึกคำขออัปเกรดแล้ว กรุณาโอนเงินตามบัญชีด้านล่าง แล้วรอแอดมินอนุมัติ'
            : 'ชำระเงินด้วยบัตรสำเร็จแล้ว รอแอดมินอนุมัติการอัปเกรดแพ็กเกจ';

        Response::json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], 201);
    }

    /**
     * @param array<string, mixed> $plan
     *
     * @return array<string, mixed>
     */
    private function buildUpgradePaymentPayload(array $plan, float $price): array
    {
        $banks = $this->normalizedBankAccounts();
        $first = $banks[0] ?? null;

        return [
            'status' => 'pending',
            'amount_thb' => $price,
            'plan_name' => (string) $plan['name_th'],
            'bank_account_name' => (string) ($this->paymentConfig['bank_account_name'] ?? ''),
            'transfer_reference_note' => (string) ($this->paymentConfig['transfer_reference_note'] ?? ''),
            'bank_accounts' => $banks,
            'bank_name' => $first ? $first['name_th'] : (string) ($this->paymentConfig['bank_name'] ?? ''),
            'bank_account_number' => $first ? $first['account_number'] : (string) ($this->paymentConfig['bank_account_number'] ?? ''),
        ];
    }

    /**
     * @return list<array{code: string, name_th: string, account_number: string, logo: string, type: string}>
     */
    private function normalizedBankAccounts(): array
    {
        $cfg = $this->paymentConfig;
        if (!empty($cfg['bank_accounts']) && is_array($cfg['bank_accounts'])) {
            $out = [];
            foreach ($cfg['bank_accounts'] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $out[] = [
                    'code' => (string) ($row['code'] ?? ''),
                    'name_th' => (string) ($row['name_th'] ?? ''),
                    'account_number' => (string) ($row['account_number'] ?? ''),
                    'logo' => (string) ($row['logo'] ?? ''),
                    'type' => (string) ($row['type'] ?? 'bank'),
                ];
            }

            return $out;
        }

        return [[
            'code' => 'default',
            'name_th' => (string) ($cfg['bank_name'] ?? ''),
            'account_number' => (string) ($cfg['bank_account_number'] ?? ''),
            'logo' => '',
            'type' => 'bank',
        ]];
    }
}
