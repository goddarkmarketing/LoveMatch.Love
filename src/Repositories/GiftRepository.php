<?php

namespace App\Repositories;

use PDO;
use RuntimeException;

class GiftRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function findActiveById(int $giftId): ?array
    {
        $statement = $this->db->prepare(
            'SELECT * FROM gift_catalog WHERE id = :id AND is_active = 1 LIMIT 1'
        );
        $statement->execute(['id' => $giftId]);
        $gift = $statement->fetch();

        return $gift ?: null;
    }

    public function sendGift(
        int $senderUserId,
        int $receiverUserId,
        int $giftId,
        ?int $roomId,
        ?int $walletTransactionId,
        string $messageText
    ): array {
        $gift = $this->findActiveById($giftId);
        if (!$gift) {
            throw new RuntimeException('ไม่พบของขวัญที่เลือก');
        }

        $unlockStartAt = date('Y-m-d H:i:s');
        $unlockEndAt = null;

        if ($gift['unlock_type'] === 'days' && $gift['unlock_days']) {
          $unlockEndAt = date('Y-m-d H:i:s', strtotime('+' . (int) $gift['unlock_days'] . ' days'));
        }

        $transaction = $this->db->prepare(
            'INSERT INTO gift_transactions (
                room_id, sender_user_id, receiver_user_id, gift_id, wallet_transaction_id,
                message_text, unlock_start_at, unlock_end_at, is_active, created_at, updated_at
             ) VALUES (
                :room_id, :sender_user_id, :receiver_user_id, :gift_id, :wallet_transaction_id,
                :message_text, :unlock_start_at, :unlock_end_at, 1, NOW(), NOW()
             )'
        );

        $transaction->execute([
            'room_id' => $roomId,
            'sender_user_id' => $senderUserId,
            'receiver_user_id' => $receiverUserId,
            'gift_id' => $giftId,
            'wallet_transaction_id' => $walletTransactionId,
            'message_text' => $messageText !== '' ? $messageText : null,
            'unlock_start_at' => $unlockStartAt,
            'unlock_end_at' => $unlockEndAt,
        ]);

        $giftTransactionId = (int) $this->db->lastInsertId();

        $unlock = $this->db->prepare(
            'INSERT INTO chat_unlocks (
                room_id, sender_user_id, receiver_user_id, gift_transaction_id,
                unlock_type, unlock_start_at, unlock_end_at, status, created_at, updated_at
             ) VALUES (
                :room_id, :sender_user_id, :receiver_user_id, :gift_transaction_id,
                :unlock_type, :unlock_start_at, :unlock_end_at, "active", NOW(), NOW()
             )'
        );

        $unlock->execute([
            'room_id' => $roomId,
            'sender_user_id' => $senderUserId,
            'receiver_user_id' => $receiverUserId,
            'gift_transaction_id' => $giftTransactionId,
            'unlock_type' => $gift['unlock_type'],
            'unlock_start_at' => $unlockStartAt,
            'unlock_end_at' => $unlockEndAt,
        ]);

        return [
            'gift_transaction_id' => $giftTransactionId,
            'gift_name' => $gift['name_th'],
            'coin_cost' => (int) $gift['coin_cost'],
            'unlock_end_at' => $unlockEndAt,
            'unlock_type' => $gift['unlock_type'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listSentForUser(int $senderUserId, int $limit = 40): array
    {
        $limit = max(1, min(100, $limit));
        $statement = $this->db->prepare(
            "SELECT gt.id, gt.created_at, gt.message_text, gc.name_th AS gift_name, gc.emoji, gc.coin_cost,
                    ru.display_name AS peer_name, ru.id AS peer_user_id
             FROM gift_transactions gt
             INNER JOIN gift_catalog gc ON gc.id = gt.gift_id
             INNER JOIN users ru ON ru.id = gt.receiver_user_id
             WHERE gt.sender_user_id = :sender
             ORDER BY gt.id DESC
             LIMIT {$limit}"
        );
        $statement->execute(['sender' => $senderUserId]);

        return $statement->fetchAll() ?: [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listReceivedForUser(int $receiverUserId, int $limit = 40): array
    {
        $limit = max(1, min(100, $limit));
        $statement = $this->db->prepare(
            "SELECT gt.id, gt.created_at, gt.message_text, gc.name_th AS gift_name, gc.emoji, gc.coin_cost,
                    su.display_name AS peer_name, su.id AS peer_user_id
             FROM gift_transactions gt
             INNER JOIN gift_catalog gc ON gc.id = gt.gift_id
             INNER JOIN users su ON su.id = gt.sender_user_id
             WHERE gt.receiver_user_id = :receiver
             ORDER BY gt.id DESC
             LIMIT {$limit}"
        );
        $statement->execute(['receiver' => $receiverUserId]);

        return $statement->fetchAll() ?: [];
    }
}
