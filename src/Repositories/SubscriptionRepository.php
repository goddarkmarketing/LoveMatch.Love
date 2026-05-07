<?php

namespace App\Repositories;

use PDO;
use RuntimeException;

class SubscriptionRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function findPlanById(int $planId): ?array
    {
        $statement = $this->db->prepare(
            'SELECT * FROM subscription_plans WHERE id = :id AND is_active = 1 LIMIT 1'
        );
        $statement->execute(['id' => $planId]);
        $plan = $statement->fetch();

        return $plan ?: null;
    }

    public function getCurrentPlanByUserId(int $userId): array
    {
        $statement = $this->db->prepare(
            'SELECT
                sp.id,
                sp.code,
                sp.name_th,
                sp.tier,
                sp.billing_cycle,
                sp.price_thb,
                sp.coin_bonus,
                s.id AS subscription_id,
                s.status AS subscription_status,
                s.started_at,
                s.expires_at
             FROM subscriptions s
             INNER JOIN subscription_plans sp ON sp.id = s.plan_id
             WHERE s.user_id = :user_id
               AND s.status = "active"
               AND sp.is_active = 1
             ORDER BY s.id DESC
             LIMIT 1'
        );
        $statement->execute(['user_id' => $userId]);
        $plan = $statement->fetch();

        if ($plan) {
            return [
                'id' => (int) $plan['id'],
                'code' => $plan['code'],
                'name' => $plan['name_th'],
                'tier' => $plan['tier'],
                'billing_cycle' => $plan['billing_cycle'],
                'price_thb' => (float) $plan['price_thb'],
                'coin_bonus' => (int) $plan['coin_bonus'],
                'subscription' => [
                    'id' => $plan['subscription_id'] ? (int) $plan['subscription_id'] : null,
                    'status' => $plan['subscription_status'],
                    'started_at' => $plan['started_at'],
                    'expires_at' => $plan['expires_at'],
                ],
            ];
        }

        $fallback = $this->db->query(
            'SELECT id, code, name_th, tier, billing_cycle, price_thb, coin_bonus
             FROM subscription_plans
             WHERE is_active = 1
               AND tier = "free"
             LIMIT 1'
        )->fetch();

        if (!$fallback) {
            throw new RuntimeException('ไม่พบแพ็กเกจที่ใช้งานได้');
        }

        return [
            'id' => (int) $fallback['id'],
            'code' => $fallback['code'],
            'name' => $fallback['name_th'],
            'tier' => $fallback['tier'],
            'billing_cycle' => $fallback['billing_cycle'],
            'price_thb' => (float) $fallback['price_thb'],
            'coin_bonus' => (int) $fallback['coin_bonus'],
            'subscription' => [
                'id' => null,
                'status' => 'active',
                'started_at' => null,
                'expires_at' => null,
            ],
        ];
    }

    /**
     * สลับเป็นแพ็กเกจฟรีทันที (ยกเลิกแพ็กเกจที่เปิดอยู่)
     *
     * @return array{subscription_id: int, payment_id: null, plan_name: string, plan_tier: string, amount_thb: float, status: string, current_plan: array}
     */
    public function activateFreePlanForUser(int $userId, int $planId): array
    {
        $plan = $this->findPlanById($planId);
        if (!$plan) {
            throw new RuntimeException('ไม่พบแพ็กเกจที่เลือก');
        }

        if ((float) $plan['price_thb'] > 0) {
            throw new RuntimeException('แพ็กเกจนี้ไม่ใช่แพ็กเกจฟรี');
        }

        $startedAt = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 month'));

        $this->cancelOpenSubscriptions($userId);

        $subscription = $this->db->prepare(
            'INSERT INTO subscriptions (
                user_id, plan_id, started_at, expires_at, status, auto_renew, created_at, updated_at
             ) VALUES (
                :user_id, :plan_id, :started_at, :expires_at, "active", 0, NOW(), NOW()
             )'
        );
        $subscription->execute([
            'user_id' => $userId,
            'plan_id' => $planId,
            'started_at' => $startedAt,
            'expires_at' => $expiresAt,
        ]);

        $subscriptionId = (int) $this->db->lastInsertId();

        return [
            'subscription_id' => $subscriptionId,
            'payment_id' => null,
            'plan_name' => $plan['name_th'],
            'plan_tier' => $plan['tier'],
            'amount_thb' => (float) $plan['price_thb'],
            'status' => 'active',
            'current_plan' => $this->getCurrentPlanByUserId($userId),
        ];
    }

    /**
     * อัปเกรดแพ็กเกจแบบชำระเงิน: สร้าง subscription สถานะ pending (รอแอดมินอนุมัติหลังชำระ/ตรวจสอบ)
     *
     * @return array{subscription_id: int, plan_name: string, plan_tier: string, amount_thb: float, plan_code: string}
     */
    public function insertPendingSubscriptionForUpgrade(int $userId, int $planId): array
    {
        $plan = $this->findPlanById($planId);
        if (!$plan) {
            throw new RuntimeException('ไม่พบแพ็กเกจที่เลือก');
        }

        if ((float) $plan['price_thb'] <= 0) {
            throw new RuntimeException('แพ็กเกจนี้ไม่ต้องชำระเงิน');
        }

        $this->cancelPendingSubscriptionsOnly($userId);

        $startedAt = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 month'));

        $subscription = $this->db->prepare(
            'INSERT INTO subscriptions (
                user_id, plan_id, started_at, expires_at, status, auto_renew, created_at, updated_at
             ) VALUES (
                :user_id, :plan_id, :started_at, :expires_at, "pending", 0, NOW(), NOW()
             )'
        );
        $subscription->execute([
            'user_id' => $userId,
            'plan_id' => $planId,
            'started_at' => $startedAt,
            'expires_at' => $expiresAt,
        ]);

        $subscriptionId = (int) $this->db->lastInsertId();

        return [
            'subscription_id' => $subscriptionId,
            'plan_name' => (string) $plan['name_th'],
            'plan_tier' => (string) $plan['tier'],
            'amount_thb' => (float) $plan['price_thb'],
            'plan_code' => (string) $plan['code'],
        ];
    }

    public function getPendingUpgradeForUser(int $userId): ?array
    {
        $statement = $this->db->prepare(
            'SELECT
                s.id AS subscription_id,
                s.status,
                s.created_at,
                sp.id AS plan_id,
                sp.code AS plan_code,
                sp.name_th AS plan_name,
                sp.tier AS plan_tier,
                sp.price_thb,
                p.id AS payment_id,
                p.status AS payment_status,
                p.payment_method
             FROM subscriptions s
             INNER JOIN subscription_plans sp ON sp.id = s.plan_id
             LEFT JOIN payments p ON p.subscription_id = s.id AND p.payment_target = "subscription"
             WHERE s.user_id = :user_id
               AND s.status = "pending"
               AND sp.is_active = 1
             ORDER BY s.id DESC
             LIMIT 1'
        );
        $statement->execute(['user_id' => $userId]);
        $row = $statement->fetch();

        if (!$row) {
            return null;
        }

        return [
            'subscription_id' => (int) $row['subscription_id'],
            'status' => (string) $row['status'],
            'created_at' => $row['created_at'],
            'plan' => [
                'id' => (int) $row['plan_id'],
                'code' => (string) $row['plan_code'],
                'name' => (string) $row['plan_name'],
                'tier' => (string) $row['plan_tier'],
                'price_thb' => (float) $row['price_thb'],
            ],
            'payment' => $row['payment_id'] ? [
                'id' => (int) $row['payment_id'],
                'status' => (string) $row['payment_status'],
                'payment_method' => (string) $row['payment_method'],
            ] : null,
        ];
    }

    /**
     * แอดมินอนุมัติการอัปเกรด: เปิดใช้ subscription นี้ และยกเลิกแพ็กเกจ active อื่นของผู้ใช้
     *
     * @return array{user_id: int, subscription_id: int, current_plan: array}
     */
    public function approvePendingSubscription(int $subscriptionId): array
    {
        $this->db->beginTransaction();

        try {
            $statement = $this->db->prepare(
                'SELECT id, user_id, status FROM subscriptions WHERE id = :id FOR UPDATE'
            );
            $statement->execute(['id' => $subscriptionId]);
            $row = $statement->fetch();

            if (!$row || (string) $row['status'] !== 'pending') {
                throw new RuntimeException('ไม่พบการอัปเกรดที่รออนุมัติ');
            }

            $userId = (int) $row['user_id'];

            $cancelActive = $this->db->prepare(
                'UPDATE subscriptions
                 SET status = "cancelled", updated_at = NOW()
                 WHERE user_id = :user_id
                   AND id != :sid
                   AND status = "active"'
            );
            $cancelActive->execute(['user_id' => $userId, 'sid' => $subscriptionId]);

            $cancelOtherPending = $this->db->prepare(
                'UPDATE subscriptions
                 SET status = "cancelled", updated_at = NOW()
                 WHERE user_id = :user_id
                   AND id != :sid
                   AND status = "pending"'
            );
            $cancelOtherPending->execute(['user_id' => $userId, 'sid' => $subscriptionId]);

            $startedAt = date('Y-m-d H:i:s');
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 month'));

            $activate = $this->db->prepare(
                'UPDATE subscriptions
                 SET status = "active", started_at = :started_at, expires_at = :expires_at, updated_at = NOW()
                 WHERE id = :id'
            );
            $activate->execute([
                'started_at' => $startedAt,
                'expires_at' => $expiresAt,
                'id' => $subscriptionId,
            ]);

            $markPaid = $this->db->prepare(
                'UPDATE payments
                 SET status = "paid", paid_at = NOW(), updated_at = NOW()
                 WHERE subscription_id = :sid
                   AND payment_target = "subscription"
                   AND status = "pending"'
            );
            $markPaid->execute(['sid' => $subscriptionId]);

            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }

        return [
            'user_id' => $userId,
            'subscription_id' => $subscriptionId,
            'current_plan' => $this->getCurrentPlanByUserId($userId),
        ];
    }

    private function cancelPendingSubscriptionsOnly(int $userId): void
    {
        $statement = $this->db->prepare(
            'UPDATE subscriptions
             SET status = "cancelled", updated_at = NOW()
             WHERE user_id = :user_id
               AND status = "pending"'
        );
        $statement->execute(['user_id' => $userId]);
    }

    private function cancelOpenSubscriptions(int $userId): void
    {
        $statement = $this->db->prepare(
            'UPDATE subscriptions
             SET status = "cancelled", updated_at = NOW()
             WHERE user_id = :user_id
               AND status IN ("pending", "active")'
        );
        $statement->execute(['user_id' => $userId]);
    }

    /**
     * New signup: one subscription row (no cancel — user is new).
     */
    public function insertSubscriptionForNewUser(int $userId, int $planId, string $status): int
    {
        $plan = $this->findPlanById($planId);
        if (!$plan) {
            throw new RuntimeException('ไม่พบแพ็กเกจที่เลือก');
        }

        $startedAt = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 month'));

        $statement = $this->db->prepare(
            'INSERT INTO subscriptions (
                user_id, plan_id, started_at, expires_at, status, auto_renew, created_at, updated_at
             ) VALUES (
                :user_id, :plan_id, :started_at, :expires_at, :status, 0, NOW(), NOW()
             )'
        );
        $statement->execute([
            'user_id' => $userId,
            'plan_id' => $planId,
            'started_at' => $startedAt,
            'expires_at' => $expiresAt,
            'status' => $status,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updateSubscriptionStatus(int $subscriptionId, string $status, ?string $expiresAt = null): void
    {
        if ($expiresAt !== null) {
            $statement = $this->db->prepare(
                'UPDATE subscriptions SET status = :status, expires_at = :expires_at, updated_at = NOW() WHERE id = :id'
            );
            $statement->execute([
                'status' => $status,
                'expires_at' => $expiresAt,
                'id' => $subscriptionId,
            ]);

            return;
        }

        $statement = $this->db->prepare(
            'UPDATE subscriptions SET status = :status, updated_at = NOW() WHERE id = :id'
        );
        $statement->execute(['status' => $status, 'id' => $subscriptionId]);
    }
}
