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
        $fee = (float) ($this->paymentConfig['registration_fee_thb'] ?? 0);
        $pk = (string) ($this->paymentConfig['omise_public_key'] ?? '');

        Response::json([
            'success' => true,
            'data' => [
                'registration_fee_thb' => $fee,
                'omise_public_key' => $pk,
                'card_enabled' => $fee > 0 && $pk !== '',
                'bank_transfer_enabled' => $fee > 0,
                'bank' => [
                    'bank_name' => (string) ($this->paymentConfig['bank_name'] ?? ''),
                    'bank_account_name' => (string) ($this->paymentConfig['bank_account_name'] ?? ''),
                    'bank_account_number' => (string) ($this->paymentConfig['bank_account_number'] ?? ''),
                    'transfer_reference_note' => (string) ($this->paymentConfig['transfer_reference_note'] ?? ''),
                ],
            ],
        ]);
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
