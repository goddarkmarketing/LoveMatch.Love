<?php

namespace App\Repositories;

use PDO;

class UserRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function findByEmail(string $email): ?array
    {
        $statement = $this->db->prepare(
            'SELECT u.*, r.code AS role_code
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.email = :email
             LIMIT 1'
        );
        $statement->execute(['email' => mb_strtolower(trim($email))]);
        $user = $statement->fetch();

        return $user ?: null;
    }

    public function findById(int $id): ?array
    {
        $statement = $this->db->prepare(
            'SELECT u.id, u.email, u.first_name, u.last_name, u.display_name, u.gender, u.interested_in,
                    u.avatar_url, u.status, u.is_profile_completed, u.last_seen_at, r.code AS role_code
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $user = $statement->fetch();

        return $user ?: null;
    }

    public function create(array $payload): array
    {
        $roleId = $this->getRoleId('member');

        $statement = $this->db->prepare(
            'INSERT INTO users (
                role_id, email, password_hash, first_name, last_name, display_name,
                gender, interested_in, status, is_profile_completed, created_at, updated_at
             ) VALUES (
                :role_id, :email, :password_hash, :first_name, :last_name, :display_name,
                :gender, :interested_in, :status, :is_profile_completed, NOW(), NOW()
             )'
        );

        $statement->execute([
            'role_id' => $roleId,
            'email' => mb_strtolower(trim($payload['email'])),
            'password_hash' => $payload['password_hash'],
            'first_name' => trim($payload['first_name']),
            'last_name' => trim($payload['last_name']),
            'display_name' => trim($payload['display_name']),
            'gender' => $payload['gender'] ?: null,
            'interested_in' => $payload['interested_in'] ?: null,
            'status' => 'active',
            'is_profile_completed' => 1,
        ]);

        $userId = (int) $this->db->lastInsertId();
        $this->createWallet($userId, 50);

        return $this->findById($userId);
    }

    public function touchLastSeen(int $userId): void
    {
        $statement = $this->db->prepare('UPDATE users SET last_seen_at = NOW(), updated_at = NOW() WHERE id = :id');
        $statement->execute(['id' => $userId]);
    }

    private function getRoleId(string $roleCode): int
    {
        $statement = $this->db->prepare('SELECT id FROM roles WHERE code = :code LIMIT 1');
        $statement->execute(['code' => $roleCode]);
        $roleId = $statement->fetchColumn();

        if (!$roleId) {
            throw new \RuntimeException('Role `' . $roleCode . '` not found. Please import db/schema.sql first.');
        }

        return (int) $roleId;
    }

    private function createWallet(int $userId, int $signupBonus): void
    {
        $statement = $this->db->prepare(
            'INSERT INTO wallets (user_id, coin_balance, created_at, updated_at)
             VALUES (:user_id, :coin_balance, NOW(), NOW())'
        );
        $statement->execute([
            'user_id' => $userId,
            'coin_balance' => $signupBonus,
        ]);

        $walletId = (int) $this->db->lastInsertId();
        $transaction = $this->db->prepare(
            'INSERT INTO wallet_transactions (
                wallet_id, user_id, transaction_type, source_type, source_id, amount,
                balance_before, balance_after, note, created_at
             ) VALUES (
                :wallet_id, :user_id, :transaction_type, :source_type, NULL, :amount,
                :balance_before, :balance_after, :note, NOW()
             )'
        );
        $transaction->execute([
            'wallet_id' => $walletId,
            'user_id' => $userId,
            'transaction_type' => 'credit',
            'source_type' => 'signup_bonus',
            'amount' => $signupBonus,
            'balance_before' => 0,
            'balance_after' => $signupBonus,
            'note' => 'Signup bonus',
        ]);
    }
}
