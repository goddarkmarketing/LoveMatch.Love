<?php

namespace App\Repositories;

use PDO;

class PaymentRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function createRegistrationPayment(
        int $userId,
        string $method,
        float $amountThb,
        ?string $providerReference,
        string $status
    ): int {
        $paidAt = $status === 'paid' ? date('Y-m-d H:i:s') : null;

        $statement = $this->db->prepare(
            'INSERT INTO payments (
                user_id, subscription_id, payment_target, payment_method,
                amount_thb, currency, provider_reference, status, paid_at, created_at, updated_at
             ) VALUES (
                :user_id, NULL, :target, :pmethod,
                :amount, :currency, :pref, :status, :paid_at, NOW(), NOW()
             )'
        );

        $statement->execute([
            'user_id' => $userId,
            'target' => 'registration',
            'pmethod' => $method === 'credit_card' ? 'credit_card' : 'bank_transfer',
            'amount' => $amountThb,
            'currency' => 'THB',
            'pref' => $providerReference,
            'status' => $status,
            'paid_at' => $paidAt,
        ]);

        return (int) $this->db->lastInsertId();
    }
}
