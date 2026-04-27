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
            'SELECT transaction_type, source_type, amount, balance_before, balance_after, note, created_at
             FROM wallet_transactions
             WHERE user_id = :user_id
             ORDER BY id DESC
             LIMIT 10'
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
            'source_type' => 'gift_send',
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
