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

    public function createCheckout(int $userId, int $planId, string $paymentMethod): array
    {
        $plan = $this->findPlanById($planId);
        if (!$plan) {
            throw new RuntimeException('ไม่พบแพ็กเกจที่เลือก');
        }

        $isFreePlan = (float) $plan['price_thb'] <= 0;
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
        $paymentId = null;

        if (!$isFreePlan) {
            $payment = $this->db->prepare(
                'INSERT INTO payments (
                    user_id, subscription_id, payment_target, payment_method,
                    amount_thb, currency, provider_reference, status, paid_at, created_at, updated_at
                 ) VALUES (
                    :user_id, :subscription_id, "subscription", :payment_method,
                    :amount_thb, "THB", :provider_reference, "paid", NOW(), NOW(), NOW()
                 )'
            );
            $payment->execute([
                'user_id' => $userId,
                'subscription_id' => $subscriptionId,
                'payment_method' => $paymentMethod,
                'amount_thb' => $plan['price_thb'],
                'provider_reference' => 'PMT-' . date('YmdHis') . '-' . $userId,
            ]);
            $paymentId = (int) $this->db->lastInsertId();
        }

        return [
            'subscription_id' => $subscriptionId,
            'payment_id' => $paymentId,
            'plan_name' => $plan['name_th'],
            'plan_tier' => $plan['tier'],
            'amount_thb' => (float) $plan['price_thb'],
            'status' => 'active',
            'current_plan' => $this->getCurrentPlanByUserId($userId),
        ];
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
