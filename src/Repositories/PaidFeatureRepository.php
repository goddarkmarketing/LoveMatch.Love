<?php

namespace App\Repositories;

use PDO;
use RuntimeException;

class PaidFeatureRepository
{
    public function __construct(
        private PDO $db,
        private WalletRepository $wallets
    ) {
    }

    public function listProducts(): array
    {
        $statement = $this->db->query(
            'SELECT id, code, action_type, channel_type, name_th, description, coin_cost,
                    price_thb_estimate, duration_minutes
             FROM paid_feature_products
             WHERE is_active = 1
             ORDER BY sort_order ASC, coin_cost ASC'
        );

        return array_map([$this, 'formatProduct'], $statement->fetchAll() ?: []);
    }

    public function unlockContact(int $buyerUserId, int $targetUserId, string $productCode): array
    {
        $this->assertTarget($buyerUserId, $targetUserId);
        $product = $this->findProduct($productCode, 'contact_unlock');
        $channel = (string) ($product['channel_type'] ?? '');

        if (!in_array($channel, ['line', 'facebook', 'phone'], true)) {
            throw new RuntimeException('แพ็กเกจปลดล็อกช่องทางติดต่อไม่ถูกต้อง');
        }

        $existing = $this->findExistingContactUnlock($buyerUserId, $targetUserId, $channel);
        if ($existing) {
            return [
                'already_unlocked' => true,
                'product' => $this->formatProduct($product),
                'contact' => [
                    'channel_type' => $channel,
                    'value' => $existing['contact_value'],
                ],
                'wallet' => $this->walletPayload($buyerUserId),
            ];
        }

        $contactValue = $this->contactValue($targetUserId, $channel);
        if ($contactValue === null) {
            throw new RuntimeException('สมาชิกนี้ยังไม่ได้เพิ่มช่องทางติดต่อที่เลือก');
        }

        $this->db->beginTransaction();
        try {
            $walletTransaction = $this->wallets->debitForPaidFeature(
                $buyerUserId,
                (int) $product['coin_cost'],
                'contact_unlock',
                'Unlock ' . $channel . ': ' . $targetUserId
            );

            $insert = $this->db->prepare(
                'INSERT INTO contact_unlocks (
                    buyer_user_id, target_user_id, product_id, wallet_transaction_id,
                    channel_type, contact_value, unlocked_at, created_at, updated_at
                 ) VALUES (
                    :buyer_user_id, :target_user_id, :product_id, :wallet_transaction_id,
                    :channel_type, :contact_value, NOW(), NOW(), NOW()
                 )'
            );
            $insert->execute([
                'buyer_user_id' => $buyerUserId,
                'target_user_id' => $targetUserId,
                'product_id' => $product['id'],
                'wallet_transaction_id' => $walletTransaction['transaction_id'],
                'channel_type' => $channel,
                'contact_value' => $contactValue,
            ]);

            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }

        return [
            'already_unlocked' => false,
            'product' => $this->formatProduct($product),
            'contact' => [
                'channel_type' => $channel,
                'value' => $contactValue,
            ],
            'wallet' => ['coin_balance' => $walletTransaction['balance_after']],
        ];
    }

    public function sendCrush(int $senderUserId, int $targetUserId, string $messageText): array
    {
        $this->assertTarget($senderUserId, $targetUserId);
        $product = $this->findProduct('crush_message', 'crush');
        $messageText = trim($messageText);

        if ($messageText === '') {
            $messageText = 'สนใจอยากทำความรู้จักกับคุณ';
        }
        if (mb_strlen($messageText) > 500) {
            throw new RuntimeException('ข้อความ Crush ต้องไม่เกิน 500 ตัวอักษร');
        }

        $this->db->beginTransaction();
        try {
            $walletTransaction = $this->wallets->debitForPaidFeature(
                $senderUserId,
                (int) $product['coin_cost'],
                'crush_send',
                'Send Crush: ' . $targetUserId
            );

            $insert = $this->db->prepare(
                'INSERT INTO crush_messages (
                    sender_user_id, target_user_id, product_id, wallet_transaction_id,
                    message_text, status, created_at, updated_at
                 ) VALUES (
                    :sender_user_id, :target_user_id, :product_id, :wallet_transaction_id,
                    :message_text, "sent", NOW(), NOW()
                 )'
            );
            $insert->execute([
                'sender_user_id' => $senderUserId,
                'target_user_id' => $targetUserId,
                'product_id' => $product['id'],
                'wallet_transaction_id' => $walletTransaction['transaction_id'],
                'message_text' => $messageText,
            ]);
            $crushId = (int) $this->db->lastInsertId();

            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }

        return [
            'crush_id' => $crushId,
            'product' => $this->formatProduct($product),
            'wallet' => ['coin_balance' => $walletTransaction['balance_after']],
        ];
    }

    public function unlockChat(int $buyerUserId, int $targetUserId): array
    {
        $this->assertTarget($buyerUserId, $targetUserId);

        if ($this->hasActiveChatUnlock($buyerUserId, $targetUserId)) {
            return [
                'already_unlocked' => true,
                'wallet' => $this->walletPayload($buyerUserId),
            ];
        }

        $product = $this->findProduct('unlock_chat_no_match', 'chat_unlock');
        $durationMinutes = (int) ($product['duration_minutes'] ?: 1440);

        $this->db->beginTransaction();
        try {
            $walletTransaction = $this->wallets->debitForPaidFeature(
                $buyerUserId,
                (int) $product['coin_cost'],
                'chat_unlock',
                'Unlock pre-match chat: ' . $targetUserId
            );

            $insert = $this->db->prepare(
                'INSERT INTO paid_chat_unlocks (
                    buyer_user_id, target_user_id, product_id, wallet_transaction_id,
                    unlocked_at, expires_at, status, created_at, updated_at
                 ) VALUES (
                    :buyer_user_id, :target_user_id, :product_id, :wallet_transaction_id,
                    NOW(), DATE_ADD(NOW(), INTERVAL ' . $durationMinutes . ' MINUTE), "active", NOW(), NOW()
                 )'
            );
            $insert->execute([
                'buyer_user_id' => $buyerUserId,
                'target_user_id' => $targetUserId,
                'product_id' => $product['id'],
                'wallet_transaction_id' => $walletTransaction['transaction_id'],
            ]);

            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }

        return [
            'already_unlocked' => false,
            'product' => $this->formatProduct($product),
            'wallet' => ['coin_balance' => $walletTransaction['balance_after']],
        ];
    }

    public function boostProfile(int $userId, string $productCode = 'profile_boost_1h'): array
    {
        $product = $this->findProduct($productCode, 'profile_boost');
        $durationMinutes = (int) ($product['duration_minutes'] ?: 60);

        $this->db->beginTransaction();
        try {
            $walletTransaction = $this->wallets->debitForPaidFeature(
                $userId,
                (int) $product['coin_cost'],
                'profile_boost',
                'Profile boost: ' . $product['code']
            );

            $activeUntil = $this->activeBoostUntil($userId);
            $startsExpression = $activeUntil ? ':starts_at' : 'NOW()';
            $endsBaseExpression = $activeUntil ? ':ends_base_at' : 'NOW()';
            $insert = $this->db->prepare(
                'INSERT INTO profile_boosts (
                    user_id, product_id, wallet_transaction_id, starts_at, ends_at,
                    status, created_at, updated_at
                 ) VALUES (
                    :user_id, :product_id, :wallet_transaction_id, ' . $startsExpression . ',
                    DATE_ADD(' . $endsBaseExpression . ', INTERVAL ' . $durationMinutes . ' MINUTE),
                    "active", NOW(), NOW()
                 )'
            );
            $params = [
                'user_id' => $userId,
                'product_id' => $product['id'],
                'wallet_transaction_id' => $walletTransaction['transaction_id'],
            ];
            if ($activeUntil) {
                $params['starts_at'] = $activeUntil;
                $params['ends_base_at'] = $activeUntil;
            }
            $insert->execute($params);
            $boostId = (int) $this->db->lastInsertId();

            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }

        return [
            'boost_id' => $boostId,
            'product' => $this->formatProduct($product),
            'wallet' => ['coin_balance' => $walletTransaction['balance_after']],
        ];
    }

    public function hasActiveChatUnlock(int $buyerUserId, int $targetUserId): bool
    {
        $statement = $this->db->prepare(
            'SELECT COUNT(*)
             FROM paid_chat_unlocks
             WHERE buyer_user_id = :buyer_user_id
               AND target_user_id = :target_user_id
               AND status = "active"
               AND (expires_at IS NULL OR expires_at > NOW())'
        );
        $statement->execute([
            'buyer_user_id' => $buyerUserId,
            'target_user_id' => $targetUserId,
        ]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function findProduct(string $code, string $actionType): array
    {
        $statement = $this->db->prepare(
            'SELECT *
             FROM paid_feature_products
             WHERE code = :code
               AND action_type = :action_type
               AND is_active = 1
             LIMIT 1'
        );
        $statement->execute([
            'code' => $code,
            'action_type' => $actionType,
        ]);
        $product = $statement->fetch();

        if (!$product) {
            throw new RuntimeException('ไม่พบแพ็กเกจที่เลือก');
        }

        return $product;
    }

    private function assertTarget(int $actorUserId, int $targetUserId): void
    {
        if ($actorUserId <= 0 || $targetUserId <= 0 || $actorUserId === $targetUserId) {
            throw new RuntimeException('target_user_id ไม่ถูกต้อง');
        }
    }

    private function contactValue(int $targetUserId, string $channel): ?string
    {
        $statement = $this->db->prepare(
            'SELECT contact_value
             FROM user_contact_channels
             WHERE user_id = :user_id
               AND channel_type = :channel_type
               AND is_active = 1
             LIMIT 1'
        );
        $statement->execute([
            'user_id' => $targetUserId,
            'channel_type' => $channel,
        ]);
        $value = $statement->fetchColumn();

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function findExistingContactUnlock(int $buyerUserId, int $targetUserId, string $channel): ?array
    {
        $statement = $this->db->prepare(
            'SELECT *
             FROM contact_unlocks
             WHERE buyer_user_id = :buyer_user_id
               AND target_user_id = :target_user_id
               AND channel_type = :channel_type
             LIMIT 1'
        );
        $statement->execute([
            'buyer_user_id' => $buyerUserId,
            'target_user_id' => $targetUserId,
            'channel_type' => $channel,
        ]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    private function activeBoostUntil(int $userId): ?string
    {
        $statement = $this->db->prepare(
            'SELECT MAX(ends_at)
             FROM profile_boosts
             WHERE user_id = :user_id
               AND status = "active"
               AND ends_at > NOW()'
        );
        $statement->execute(['user_id' => $userId]);
        $value = $statement->fetchColumn();

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function walletPayload(int $userId): array
    {
        $wallet = $this->wallets->getByUserId($userId);

        return [
            'coin_balance' => $wallet ? (int) $wallet['coin_balance'] : 0,
        ];
    }

    private function formatProduct(array $product): array
    {
        return [
            'id' => (int) $product['id'],
            'code' => $product['code'],
            'action_type' => $product['action_type'],
            'channel_type' => $product['channel_type'] ?: null,
            'name' => $product['name_th'],
            'description' => $product['description'],
            'coin_cost' => (int) $product['coin_cost'],
            'price_thb_estimate' => $product['price_thb_estimate'] !== null ? (float) $product['price_thb_estimate'] : null,
            'duration_minutes' => $product['duration_minutes'] !== null ? (int) $product['duration_minutes'] : null,
        ];
    }
}
