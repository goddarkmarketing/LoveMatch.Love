<?php

namespace App\Controllers;

use App\Repositories\GiftRepository;
use App\Repositories\WalletRepository;
use App\Support\Request;
use App\Support\Response;
use RuntimeException;

class GiftController
{
    public function __construct(
        private GiftRepository $gifts,
        private WalletRepository $wallets
    ) {
    }

    public function send(Request $request, int $userId): void
    {
        $payload = $request->json();
        $giftId = (int) ($payload['gift_id'] ?? 0);
        $receiverUserId = (int) ($payload['receiver_user_id'] ?? 0);
        $roomId = isset($payload['room_id']) ? (int) $payload['room_id'] : null;
        $message = trim((string) ($payload['message_text'] ?? ''));

        if ($giftId <= 0 || $receiverUserId <= 0) {
            Response::json([
                'success' => false,
                'message' => 'gift_id และ receiver_user_id จำเป็นต้องส่งมา',
            ], 422);
        }

        $gift = $this->gifts->findActiveById($giftId);
        if (!$gift) {
            Response::json([
                'success' => false,
                'message' => 'ไม่พบของขวัญที่เลือก',
            ], 404);
        }

        try {
            $walletTransaction = $this->wallets->debitForGift(
                $userId,
                (int) $gift['coin_cost'],
                'Send gift: ' . $gift['name_th']
            );

            $giftTransaction = $this->gifts->sendGift(
                $userId,
                $receiverUserId,
                $giftId,
                $roomId,
                $walletTransaction['transaction_id'],
                $message
            );
        } catch (RuntimeException $exception) {
            Response::json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        Response::json([
            'success' => true,
            'message' => 'ส่งของขวัญสำเร็จ',
            'data' => [
                'gift_transaction' => $giftTransaction,
                'wallet' => [
                    'coin_balance' => $walletTransaction['balance_after'],
                ],
            ],
        ], 201);
    }
}
