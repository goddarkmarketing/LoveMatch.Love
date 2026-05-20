<?php

namespace App\Repositories;

use PDO;
use RuntimeException;

class WalletRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function getByUserId(int $userId): ?array
    {
        $statement = $this->db->prepare('SELECT * FROM wallets WHERE user_id = :user_id LIMIT 1');
        $statement->execute(['user_id' => $userId]);
        $wallet = $statement->fetch();

        return $wallet ?: null;
    }

    public function getSummaryByUserId(int $userId): ?array
    {
        $wallet = $this->getByUserId($userId);

        if (!$wallet) {
            return null;
        }

        $statement = $this->db->prepare(
            'SELECT id, transaction_type, source_type, amount, balance_before, balance_after, note, created_at
             FROM wallet_transactions
             WHERE user_id = :user_id
             ORDER BY id DESC
             LIMIT 80'
        );
        $statement->execute(['user_id' => $userId]);

        return [
            'wallet' => [
                'id' => (int) $wallet['id'],
                'coin_balance' => (int) $wallet['coin_balance'],
            ],
            'transactions' => $statement->fetchAll(),
        ];
    }

    public function debitForGift(int $userId, int $amount, string $note): array
    {
        return $this->debit($userId, $amount, 'gift_send', $note);
    }

    public function debitForPaidFeature(int $userId, int $amount, string $sourceType, string $note): array
    {
        if (!in_array($sourceType, ['contact_unlock', 'crush_send', 'chat_unlock', 'profile_boost'], true)) {
            throw new RuntimeException('ประเภทการใช้เหรียญไม่ถูกต้อง');
        }

        return $this->debit($userId, $amount, $sourceType, $note);
    }

    public function creditForCoinTopup(int $userId, int $amount, int $sourceId, string $note): array
    {
        if ($amount <= 0) {
            throw new RuntimeException('จำนวน Point ไม่ถูกต้อง');
        }

        return $this->credit($userId, $amount, 'coin_topup', $sourceId, $note);
    }

    private function credit(int $userId, int $amount, string $sourceType, ?int $sourceId, string $note): array
    {
        $wallet = $this->getByUserId($userId);

        if (!$wallet) {
            $create = $this->db->prepare(
                'INSERT INTO wallets (user_id, coin_balance, created_at, updated_at)
                 VALUES (:user_id, 0, NOW(), NOW())'
            );
            $create->execute(['user_id' => $userId]);
            $wallet = $this->getByUserId($userId);
        }

        if (!$wallet) {
            throw new RuntimeException('ไม่พบกระเป๋าเหรียญของผู้ใช้');
        }

        $balanceBefore = (int) $wallet['coin_balance'];
        $balanceAfter = $balanceBefore + $amount;

        $update = $this->db->prepare(
            'UPDATE wallets SET coin_balance = :coin_balance, updated_at = NOW() WHERE id = :id'
        );
        $update->execute([
            'coin_balance' => $balanceAfter,
            'id' => $wallet['id'],
        ]);

        $insert = $this->db->prepare(
            'INSERT INTO wallet_transactions (
                wallet_id, user_id, transaction_type, source_type, source_id,
                amount, balance_before, balance_after, note, created_at
             ) VALUES (
                :wallet_id, :user_id, "credit", :source_type, :source_id,
                :amount, :balance_before, :balance_after, :note, NOW()
             )'
        );
        $insert->execute([
            'wallet_id' => $wallet['id'],
            'user_id' => $userId,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'note' => $note,
        ]);

        return [
            'wallet_id' => (int) $wallet['id'],
            'transaction_id' => (int) $this->db->lastInsertId(),
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
        ];
    }

    private function debit(int $userId, int $amount, string $sourceType, string $note): array
    {
        $wallet = $this->getByUserId($userId);

        if (!$wallet) {
            throw new RuntimeException('ไม่พบกระเป๋าเหรียญของผู้ใช้');
        }

        $balanceBefore = (int) $wallet['coin_balance'];
        if ($balanceBefore < $amount) {
            throw new RuntimeException('เหรียญไม่เพียงพอ');
        }

        $balanceAfter = $balanceBefore - $amount;

        $update = $this->db->prepare(
            'UPDATE wallets SET coin_balance = :coin_balance, updated_at = NOW() WHERE id = :id'
        );
        $update->execute([
            'coin_balance' => $balanceAfter,
            'id' => $wallet['id'],
        ]);

        $insert = $this->db->prepare(
            'INSERT INTO wallet_transactions (
                wallet_id, user_id, transaction_type, source_type, source_id,
                amount, balance_before, balance_after, note, created_at
             ) VALUES (
                :wallet_id, :user_id, :transaction_type, :source_type, NULL,
                :amount, :balance_before, :balance_after, :note, NOW()
             )'
        );
        $insert->execute([
            'wallet_id' => $wallet['id'],
            'user_id' => $userId,
            'transaction_type' => 'debit',
            'source_type' => $sourceType,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'note' => $note,
        ]);

        return [
            'wallet_id' => (int) $wallet['id'],
            'transaction_id' => (int) $this->db->lastInsertId(),
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
        ];
    }
}
