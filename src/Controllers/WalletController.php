<?php

namespace App\Controllers;

use App\Repositories\GiftRepository;
use App\Repositories\WalletRepository;
use App\Support\Response;

class WalletController
{
    public function __construct(
        private WalletRepository $wallets,
        private GiftRepository $gifts
    ) {
    }

    public function show(int $userId): void
    {
        $summary = $this->wallets->getSummaryByUserId($userId);

        if (!$summary) {
            Response::json([
                'success' => false,
                'message' => 'ไม่พบกระเป๋าเหรียญ',
            ], 404);
        }

        $transactions = $summary['transactions'] ?? [];
        $creditLog = [];
        $spendLog = [];

        foreach ($transactions as $row) {
            $type = (string) ($row['transaction_type'] ?? '');
            if (in_array($type, ['credit', 'bonus', 'refund'], true)) {
                $creditLog[] = $row;
            } elseif ($type === 'debit') {
                $spendLog[] = $row;
            }
        }

        $summary['credit_log'] = $creditLog;
        $summary['spend_log'] = $spendLog;
        $summary['gifts_sent'] = array_map(static function (array $r): array {
            return [
                'id' => (int) $r['id'],
                'created_at' => $r['created_at'],
                'gift_name' => $r['gift_name'],
                'emoji' => $r['emoji'],
                'coin_cost' => (int) $r['coin_cost'],
                'message_text' => $r['message_text'],
                'receiver_name' => $r['peer_name'],
                'receiver_user_id' => (int) $r['peer_user_id'],
            ];
        }, $this->gifts->listSentForUser($userId, 40));

        $summary['gifts_received'] = array_map(static function (array $r): array {
            return [
                'id' => (int) $r['id'],
                'created_at' => $r['created_at'],
                'gift_name' => $r['gift_name'],
                'emoji' => $r['emoji'],
                'coin_cost' => (int) $r['coin_cost'],
                'message_text' => $r['message_text'],
                'sender_name' => $r['peer_name'],
                'sender_user_id' => (int) $r['peer_user_id'],
            ];
        }, $this->gifts->listReceivedForUser($userId, 40));

        Response::json([
            'success' => true,
            'data' => $summary,
        ]);
    }
}
