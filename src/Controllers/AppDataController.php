<?php

namespace App\Controllers;

use App\Repositories\AppDataRepository;
use App\Support\Response;

class AppDataController
{
    public function __construct(
        private AppDataRepository $repository,
        private array $paymentConfig = []
    ) {
    }

    public function registrationPaymentOptions(): void
    {
        $pk = (string) ($this->paymentConfig['omise_public_key'] ?? '');
        $plans = array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'code' => $row['code'],
                'name' => $row['name_th'],
                'tier' => $row['tier'],
                'billing_cycle' => $row['billing_cycle'],
                'price_thb' => (float) $row['price_thb'],
                'coin_bonus' => (int) $row['coin_bonus'],
            ];
        }, $this->repository->subscriptionPlans());

        $hasPaidPlan = false;
        foreach ($plans as $p) {
            if ($p['price_thb'] > 0) {
                $hasPaidPlan = true;
                break;
            }
        }

        $bankAccounts = $this->normalizedBankAccounts();
        $first = $bankAccounts[0] ?? null;

        Response::json([
            'success' => true,
            'data' => [
                'plans' => $plans,
                'omise_public_key' => $pk,
                'card_enabled' => $hasPaidPlan && $pk !== '',
                'bank_transfer_enabled' => $hasPaidPlan,
                'bank_accounts' => $bankAccounts,
                'bank' => [
                    'account_name' => (string) ($this->paymentConfig['bank_account_name'] ?? ''),
                    'bank_account_name' => (string) ($this->paymentConfig['bank_account_name'] ?? ''),
                    'transfer_reference_note' => (string) ($this->paymentConfig['transfer_reference_note'] ?? ''),
                    'bank_name' => $first ? $first['name_th'] : (string) ($this->paymentConfig['bank_name'] ?? ''),
                    'bank_account_number' => $first ? $first['account_number'] : (string) ($this->paymentConfig['bank_account_number'] ?? ''),
                ],
            ],
        ]);
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

    public function gifts(): void
    {
        $items = array_map(function (array $gift): array {
            $description = $gift['unlock_type'] === 'permanent'
                ? 'ปลดล็อกแชทถาวร'
                : 'ปลดล็อกแชท ' . (int) $gift['unlock_days'] . ' วัน';

            return [
                'id' => (int) $gift['id'],
                'code' => $gift['code'],
                'name' => $gift['name_th'],
                'emoji' => $gift['emoji'],
                'coin_cost' => (int) $gift['coin_cost'],
                'description' => $description,
            ];
        }, $this->repository->gifts());

        Response::json([
            'success' => true,
            'data' => ['gifts' => $items],
        ]);
    }

    public function subscriptionPlans(): void
    {
        $items = array_map(function (array $plan): array {
            return [
                'id' => (int) $plan['id'],
                'code' => $plan['code'],
                'name' => $plan['name_th'],
                'tier' => $plan['tier'],
                'billing_cycle' => $plan['billing_cycle'],
                'price_thb' => (float) $plan['price_thb'],
                'coin_bonus' => (int) $plan['coin_bonus'],
                'features' => $plan['feature_json'] ? json_decode((string) $plan['feature_json'], true) : null,
            ];
        }, $this->repository->subscriptionPlans());

        Response::json([
            'success' => true,
            'data' => ['plans' => $items],
        ]);
    }

    public function chatRooms(): void
    {
        $items = array_map(function (array $room): array {
            return [
                'id' => (int) $room['id'],
                'code' => $room['code'],
                'name' => $room['name_th'],
                'room_type' => $room['room_type'],
                'member_count' => (int) $room['member_count'],
            ];
        }, $this->repository->publicChatRooms());

        Response::json([
            'success' => true,
            'data' => ['rooms' => $items],
        ]);
    }
}
