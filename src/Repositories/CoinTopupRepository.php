<?php

namespace App\Repositories;

use PDO;
use RuntimeException;

class CoinTopupRepository
{
    public function __construct(
        private PDO $db,
        private WalletRepository $wallets
    ) {
    }

    public function packages(): array
    {
        $statement = $this->db->query(
            'SELECT id, code, name_th, coin_amount, price_thb, bonus_coin
             FROM coin_topup_packages
             WHERE is_active = 1
             ORDER BY sort_order ASC, price_thb ASC'
        );

        return array_map([$this, 'formatPackage'], $statement->fetchAll() ?: []);
    }

    public function createRequest(int $userId, string $packageCode, ?string $slipUrl = null, ?string $transferReference = null): array
    {
        $package = $this->findPackage($packageCode);
        $coinAmount = (int) $package['coin_amount'];
        $bonusCoin = (int) $package['bonus_coin'];
        $totalCoins = $coinAmount + $bonusCoin;
        $amountThb = (float) $package['price_thb'];

        $this->db->beginTransaction();
        try {
            $payment = $this->db->prepare(
                'INSERT INTO payments (
                    user_id, subscription_id, payment_target, payment_method,
                    amount_thb, currency, provider_reference, slip_url, status, paid_at,
                    created_at, updated_at
                 ) VALUES (
                    :user_id, NULL, "coin_topup", "bank_transfer",
                    :amount_thb, "THB", :provider_reference, :slip_url, "pending", NULL,
                    NOW(), NOW()
                 )'
            );
            $payment->execute([
                'user_id' => $userId,
                'amount_thb' => $amountThb,
                'provider_reference' => $transferReference,
                'slip_url' => $slipUrl,
            ]);
            $paymentId = (int) $this->db->lastInsertId();

            $request = $this->db->prepare(
                'INSERT INTO coin_topup_requests (
                    user_id, package_id, payment_id, coin_amount, bonus_coin, total_coin_amount,
                    amount_thb, payment_method, slip_url, transfer_reference, status,
                    requested_at, created_at, updated_at
                 ) VALUES (
                    :user_id, :package_id, :payment_id, :coin_amount, :bonus_coin, :total_coin_amount,
                    :amount_thb, "bank_transfer", :slip_url, :transfer_reference, "pending",
                    NOW(), NOW(), NOW()
                 )'
            );
            $request->execute([
                'user_id' => $userId,
                'package_id' => $package['id'],
                'payment_id' => $paymentId,
                'coin_amount' => $coinAmount,
                'bonus_coin' => $bonusCoin,
                'total_coin_amount' => $totalCoins,
                'amount_thb' => $amountThb,
                'slip_url' => $slipUrl,
                'transfer_reference' => $transferReference,
            ]);
            $requestId = (int) $this->db->lastInsertId();

            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }

        return $this->findRequestById($requestId) ?: [];
    }

    public function pendingRequests(int $limit = 30): array
    {
        $limit = max(1, min(100, $limit));
        $statement = $this->db->query(
            "SELECT ctr.*, ctp.code AS package_code, ctp.name_th AS package_name,
                    u.display_name, u.email
             FROM coin_topup_requests ctr
             INNER JOIN coin_topup_packages ctp ON ctp.id = ctr.package_id
             INNER JOIN users u ON u.id = ctr.user_id
             WHERE ctr.status = 'pending'
             ORDER BY ctr.id DESC
             LIMIT {$limit}"
        );

        return array_map([$this, 'formatRequest'], $statement->fetchAll() ?: []);
    }

    public function recentRequests(int $limit = 12): array
    {
        $limit = max(1, min(50, $limit));
        $statement = $this->db->query(
            "SELECT ctr.*, ctp.code AS package_code, ctp.name_th AS package_name,
                    u.display_name, u.email
             FROM coin_topup_requests ctr
             INNER JOIN coin_topup_packages ctp ON ctp.id = ctr.package_id
             INNER JOIN users u ON u.id = ctr.user_id
             ORDER BY ctr.id DESC
             LIMIT {$limit}"
        );

        return array_map([$this, 'formatRequest'], $statement->fetchAll() ?: []);
    }

    public function approve(int $requestId, int $adminUserId): array
    {
        $request = $this->findRequestById($requestId);
        if (!$request || $request['status'] !== 'pending') {
            throw new RuntimeException('ไม่พบคำขอเติม Point ที่รออนุมัติ');
        }

        $this->db->beginTransaction();
        try {
            $walletTransaction = $this->wallets->creditForCoinTopup(
                (int) $request['user_id'],
                (int) $request['total_coin_amount'],
                $requestId,
                'Approved coin topup #' . $requestId
            );

            $update = $this->db->prepare(
                'UPDATE coin_topup_requests
                 SET status = "approved", approved_by = :approved_by, approved_at = NOW(), updated_at = NOW()
                 WHERE id = :id AND status = "pending"'
            );
            $update->execute([
                'approved_by' => $adminUserId,
                'id' => $requestId,
            ]);

            if (!empty($request['payment_id'])) {
                $payment = $this->db->prepare(
                    'UPDATE payments
                     SET status = "paid", paid_at = NOW(), updated_at = NOW()
                     WHERE id = :id'
                );
                $payment->execute(['id' => (int) $request['payment_id']]);
            }

            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }

        $approved = $this->findRequestById($requestId) ?: [];
        $approved['wallet'] = [
            'coin_balance' => $walletTransaction['balance_after'],
        ];

        return $approved;
    }

    public function reject(int $requestId, int $adminUserId, string $reason = ''): array
    {
        $request = $this->findRequestById($requestId);
        if (!$request || $request['status'] !== 'pending') {
            throw new RuntimeException('ไม่พบคำขอเติม Point ที่รออนุมัติ');
        }

        $this->db->beginTransaction();
        try {
            $update = $this->db->prepare(
                'UPDATE coin_topup_requests
                 SET status = "rejected", rejected_by = :rejected_by, rejected_at = NOW(),
                     reject_reason = :reject_reason, updated_at = NOW()
                 WHERE id = :id AND status = "pending"'
            );
            $update->execute([
                'rejected_by' => $adminUserId,
                'reject_reason' => $reason !== '' ? $reason : null,
                'id' => $requestId,
            ]);

            if (!empty($request['payment_id'])) {
                $payment = $this->db->prepare(
                    'UPDATE payments
                     SET status = "cancelled", updated_at = NOW()
                     WHERE id = :id'
                );
                $payment->execute(['id' => (int) $request['payment_id']]);
            }

            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }

        return $this->findRequestById($requestId) ?: [];
    }

    private function findPackage(string $code): array
    {
        $statement = $this->db->prepare(
            'SELECT *
             FROM coin_topup_packages
             WHERE code = :code AND is_active = 1
             LIMIT 1'
        );
        $statement->execute(['code' => $code]);
        $package = $statement->fetch();

        if (!$package) {
            throw new RuntimeException('ไม่พบแพ็กเกจเติม Point');
        }

        return $package;
    }

    private function findRequestById(int $requestId): ?array
    {
        $statement = $this->db->prepare(
            'SELECT ctr.*, ctp.code AS package_code, ctp.name_th AS package_name,
                    u.display_name, u.email
             FROM coin_topup_requests ctr
             INNER JOIN coin_topup_packages ctp ON ctp.id = ctr.package_id
             INNER JOIN users u ON u.id = ctr.user_id
             WHERE ctr.id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $requestId]);
        $row = $statement->fetch();

        return $row ? $this->formatRequest($row) : null;
    }

    private function formatPackage(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'code' => $row['code'],
            'name' => $row['name_th'],
            'coin_amount' => (int) $row['coin_amount'],
            'bonus_coin' => (int) $row['bonus_coin'],
            'total_coin_amount' => (int) $row['coin_amount'] + (int) $row['bonus_coin'],
            'price_thb' => (float) $row['price_thb'],
        ];
    }

    private function formatRequest(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'user_id' => (int) $row['user_id'],
            'display_name' => $row['display_name'] ?? null,
            'email' => $row['email'] ?? null,
            'package_code' => $row['package_code'] ?? null,
            'package_name' => $row['package_name'] ?? null,
            'payment_id' => $row['payment_id'] !== null ? (int) $row['payment_id'] : null,
            'coin_amount' => (int) $row['coin_amount'],
            'bonus_coin' => (int) $row['bonus_coin'],
            'total_coin_amount' => (int) $row['total_coin_amount'],
            'amount_thb' => (float) $row['amount_thb'],
            'payment_method' => $row['payment_method'],
            'slip_url' => $row['slip_url'] ?: null,
            'transfer_reference' => $row['transfer_reference'] ?: null,
            'status' => $row['status'],
            'requested_at' => $row['requested_at'],
            'approved_at' => $row['approved_at'] ?: null,
            'rejected_at' => $row['rejected_at'] ?: null,
            'reject_reason' => $row['reject_reason'] ?: null,
        ];
    }
}
