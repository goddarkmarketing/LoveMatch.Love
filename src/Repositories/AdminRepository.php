<?php

namespace App\Repositories;

use PDO;
use RuntimeException;

class AdminRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function dashboardSummary(): array
    {
        return [
            'stats' => [
                'users_total' => $this->count('users'),
                'users_active' => $this->count('users', 'status = "active"'),
                'reports_open' => $this->count('reports', 'status IN ("open", "reviewing")'),
                'payments_paid' => $this->count('payments', 'status = "paid"'),
                'subscriptions_active' => $this->count('subscriptions', 'status = "active"'),
                'gifts_sent' => $this->count('gift_transactions'),
            ],
            'recent_users' => $this->recentUsers(),
            'recent_payments' => $this->recentPayments(),
            'open_reports' => $this->openReports(),
            'recent_gifts' => $this->recentGifts(),
        ];
    }

    public function updateUserStatus(int $userId, string $status): array
    {
        $allowedStatuses = ['active', 'suspended', 'banned'];
        if (!in_array($status, $allowedStatuses, true)) {
            throw new RuntimeException('สถานะผู้ใช้ไม่ถูกต้อง');
        }

        $statement = $this->db->prepare(
            'UPDATE users
             SET status = :status, updated_at = NOW()
             WHERE id = :id'
        );
        $statement->execute([
            'status' => $status,
            'id' => $userId,
        ]);

        if ($statement->rowCount() === 0) {
            throw new RuntimeException('ไม่พบผู้ใช้ที่ต้องการอัปเดต');
        }

        $user = $this->db->prepare(
            'SELECT u.id, u.display_name, u.email, u.status, r.code AS role_code
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.id = :id
             LIMIT 1'
        );
        $user->execute(['id' => $userId]);
        $row = $user->fetch();

        if (!$row) {
            throw new RuntimeException('ไม่พบผู้ใช้ที่ต้องการอัปเดต');
        }

        return [
            'id' => (int) $row['id'],
            'display_name' => $row['display_name'],
            'email' => $row['email'],
            'status' => $row['status'],
            'role_code' => $row['role_code'],
        ];
    }

    public function updateReportStatus(int $reportId, string $status, int $reviewedBy): array
    {
        $allowedStatuses = ['reviewing', 'resolved', 'dismissed'];
        if (!in_array($status, $allowedStatuses, true)) {
            throw new RuntimeException('สถานะรายงานไม่ถูกต้อง');
        }

        $statement = $this->db->prepare(
            'UPDATE reports
             SET status = :status, reviewed_by = :reviewed_by, reviewed_at = NOW(), updated_at = NOW()
             WHERE id = :id'
        );
        $statement->execute([
            'status' => $status,
            'reviewed_by' => $reviewedBy,
            'id' => $reportId,
        ]);

        if ($statement->rowCount() === 0) {
            throw new RuntimeException('ไม่พบรายงานที่ต้องการอัปเดต');
        }

        $report = $this->db->prepare(
            'SELECT
                r.id,
                r.reason_code,
                r.status,
                reporter.display_name AS reporter_name,
                reported.display_name AS reported_name,
                r.created_at
             FROM reports r
             INNER JOIN users reporter ON reporter.id = r.reporter_user_id
             INNER JOIN users reported ON reported.id = r.reported_user_id
             WHERE r.id = :id
             LIMIT 1'
        );
        $report->execute(['id' => $reportId]);
        $row = $report->fetch();

        if (!$row) {
            throw new RuntimeException('ไม่พบรายงานที่ต้องการอัปเดต');
        }

        return [
            'id' => (int) $row['id'],
            'reason_code' => $row['reason_code'],
            'status' => $row['status'],
            'reporter_name' => $row['reporter_name'],
            'reported_name' => $row['reported_name'],
            'created_at' => $row['created_at'],
        ];
    }

    private function count(string $table, ?string $where = null): int
    {
        $sql = 'SELECT COUNT(*) FROM ' . $table;
        if ($where) {
            $sql .= ' WHERE ' . $where;
        }

        return (int) $this->db->query($sql)->fetchColumn();
    }

    private function recentUsers(): array
    {
        $statement = $this->db->query(
            'SELECT
                u.id,
                u.display_name,
                u.email,
                u.status,
                u.created_at,
                r.code AS role_code
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             ORDER BY u.id DESC
             LIMIT 8'
        );

        return array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'display_name' => $row['display_name'],
                'email' => $row['email'],
                'status' => $row['status'],
                'role_code' => $row['role_code'],
                'created_at' => $row['created_at'],
            ];
        }, $statement->fetchAll());
    }

    private function recentPayments(): array
    {
        $statement = $this->db->query(
            'SELECT
                p.id,
                p.amount_thb,
                p.status,
                p.payment_method,
                p.created_at,
                u.display_name,
                sp.name_th AS plan_name
             FROM payments p
             INNER JOIN users u ON u.id = p.user_id
             LEFT JOIN subscriptions s ON s.id = p.subscription_id
             LEFT JOIN subscription_plans sp ON sp.id = s.plan_id
             ORDER BY p.id DESC
             LIMIT 8'
        );

        return array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'display_name' => $row['display_name'],
                'plan_name' => $row['plan_name'] ?: 'แพ็กเกจ',
                'amount_thb' => (float) $row['amount_thb'],
                'status' => $row['status'],
                'payment_method' => $row['payment_method'],
                'created_at' => $row['created_at'],
            ];
        }, $statement->fetchAll());
    }

    private function openReports(): array
    {
        $statement = $this->db->query(
            'SELECT
                r.id,
                r.reason_code,
                r.status,
                r.created_at,
                reporter.display_name AS reporter_name,
                reported.display_name AS reported_name
             FROM reports r
             INNER JOIN users reporter ON reporter.id = r.reporter_user_id
             INNER JOIN users reported ON reported.id = r.reported_user_id
             WHERE r.status IN ("open", "reviewing")
             ORDER BY r.id DESC
             LIMIT 8'
        );

        return array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'reason_code' => $row['reason_code'],
                'status' => $row['status'],
                'created_at' => $row['created_at'],
                'reporter_name' => $row['reporter_name'],
                'reported_name' => $row['reported_name'],
            ];
        }, $statement->fetchAll());
    }

    private function recentGifts(): array
    {
        $statement = $this->db->query(
            'SELECT
                gt.id,
                gt.created_at,
                sender.display_name AS sender_name,
                receiver.display_name AS receiver_name,
                gc.name_th AS gift_name
             FROM gift_transactions gt
             INNER JOIN users sender ON sender.id = gt.sender_user_id
             INNER JOIN users receiver ON receiver.id = gt.receiver_user_id
             INNER JOIN gift_catalog gc ON gc.id = gt.gift_id
             ORDER BY gt.id DESC
             LIMIT 8'
        );

        return array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'sender_name' => $row['sender_name'],
                'receiver_name' => $row['receiver_name'],
                'gift_name' => $row['gift_name'],
                'created_at' => $row['created_at'],
            ];
        }, $statement->fetchAll());
    }
}
