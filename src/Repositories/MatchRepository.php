<?php

namespace App\Repositories;

use PDO;

class MatchRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function recordSwipe(int $actorUserId, int $targetUserId, string $action): array
    {
        $statement = $this->db->prepare(
            'INSERT INTO swipes (actor_user_id, target_user_id, action, source, created_at)
             VALUES (:actor_user_id, :target_user_id, :action, "discover", NOW())
             ON DUPLICATE KEY UPDATE created_at = NOW()'
        );
        $statement->execute([
            'actor_user_id' => $actorUserId,
            'target_user_id' => $targetUserId,
            'action' => $action,
        ]);

        $matched = false;

        if (in_array($action, ['like', 'super_like'], true) && $this->hasMutualInterest($actorUserId, $targetUserId)) {
            $this->createMatch($actorUserId, $targetUserId);
            $matched = true;
        }

        return [
            'matched' => $matched,
            'action' => $action,
        ];
    }

    public function listMatches(int $userId): array
    {
        $statement = $this->db->prepare(
            'SELECT
                m.id,
                m.matched_at,
                CASE WHEN m.user_one_id = :user_id THEN m.user_two_id ELSE m.user_one_id END AS other_user_id,
                u.display_name,
                u.avatar_url,
                u.city,
                u.province
             FROM matches m
             INNER JOIN users u
                ON u.id = CASE WHEN m.user_one_id = :user_id THEN m.user_two_id ELSE m.user_one_id END
             WHERE (m.user_one_id = :user_id OR m.user_two_id = :user_id)
               AND m.status = "active"
             ORDER BY m.matched_at DESC'
        );
        $statement->execute(['user_id' => $userId]);

        return $statement->fetchAll();
    }

    public function isMatched(int $userId, int $otherUserId): bool
    {
        $userOneId = min($userId, $otherUserId);
        $userTwoId = max($userId, $otherUserId);

        $statement = $this->db->prepare(
            'SELECT COUNT(*)
             FROM matches
             WHERE user_one_id = :user_one_id
               AND user_two_id = :user_two_id
               AND status = "active"'
        );
        $statement->execute([
            'user_one_id' => $userOneId,
            'user_two_id' => $userTwoId,
        ]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function hasMutualInterest(int $actorUserId, int $targetUserId): bool
    {
        $statement = $this->db->prepare(
            'SELECT COUNT(*)
             FROM swipes
             WHERE actor_user_id = :target_user_id
               AND target_user_id = :actor_user_id
               AND action IN ("like", "super_like")'
        );
        $statement->execute([
            'actor_user_id' => $actorUserId,
            'target_user_id' => $targetUserId,
        ]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function createMatch(int $userA, int $userB): void
    {
        $userOneId = min($userA, $userB);
        $userTwoId = max($userA, $userB);

        $statement = $this->db->prepare(
            'INSERT INTO matches (user_one_id, user_two_id, matched_at, status, created_at, updated_at)
             VALUES (:user_one_id, :user_two_id, NOW(), "active", NOW(), NOW())
             ON DUPLICATE KEY UPDATE status = "active", updated_at = NOW()'
        );
        $statement->execute([
            'user_one_id' => $userOneId,
            'user_two_id' => $userTwoId,
        ]);
    }
}
