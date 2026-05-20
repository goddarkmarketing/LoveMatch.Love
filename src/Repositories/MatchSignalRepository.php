<?php

namespace App\Repositories;

use PDO;

class MatchSignalRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function recordProfileView(int $viewerUserId, int $targetUserId): array
    {
        if ($viewerUserId <= 0 || $targetUserId <= 0 || $viewerUserId === $targetUserId) {
            return [];
        }

        $pair = $this->ensurePairStats($viewerUserId, $targetUserId);
        $direction = $this->directionColumns($pair, $viewerUserId);

        $this->insertEvent('profile_view', $viewerUserId, $targetUserId, (int) $pair['id'], null, null, false);
        $this->incrementPairStats((int) $pair['id'], [
            $direction['views'] => 1,
            'total_score' => 1,
        ]);

        $pair = $this->findPairStats((int) $pair['id']);
        if (!$pair) {
            return [];
        }

        $reverseViews = $direction['views'] === 'user_one_to_two_views'
            ? (int) $pair['user_two_to_one_views']
            : (int) $pair['user_one_to_two_views'];

        if ($reverseViews > 0) {
            $announcement = $this->createAnnouncementIfAllowed(
                'mutual_profile_view',
                (int) $pair['id'],
                $viewerUserId,
                $targetUserId,
                'คู่นี้ดูกัน',
                'มีการเข้าดูโปรไฟล์กันทั้งสองฝ่าย สัญญาณเริ่มชัดแล้ว',
                12
            );

            return $announcement ?: [];
        }

        return [];
    }

    public function recordSwipe(int $actorUserId, int $targetUserId, string $action, bool $matched): array
    {
        if ($actorUserId <= 0 || $targetUserId <= 0 || $actorUserId === $targetUserId) {
            return [];
        }

        if (!in_array($action, ['like', 'super_like'], true)) {
            return [];
        }

        $pair = $this->ensurePairStats($actorUserId, $targetUserId);
        $direction = $this->directionColumns($pair, $actorUserId);
        $score = $action === 'super_like' ? 8 : 5;

        $this->insertEvent($action, $actorUserId, $targetUserId, (int) $pair['id'], null, null, false);
        $this->incrementPairStats((int) $pair['id'], [
            $direction['likes'] => 1,
            'total_score' => $score,
        ]);

        if (!$matched) {
            return [];
        }

        $this->insertEvent('mutual_match', $actorUserId, $targetUserId, (int) $pair['id'], null, null, true);
        $this->incrementPairStats((int) $pair['id'], [
            'match_count' => 1,
            'total_score' => 30,
        ]);

        $announcement = $this->createAnnouncementIfAllowed(
            'mutual_match',
            (int) $pair['id'],
            $actorUserId,
            $targetUserId,
            'ประกาศแมทใหม่',
            'ทั้งสองฝ่ายถูกใจกัน ระบบบันทึกเป็นคู่แมทแล้ว',
            24
        );

        return $announcement ?: [];
    }

    public function recordGiftSent(
        int $senderUserId,
        int $receiverUserId,
        int $giftTransactionId,
        string $giftName,
        int $coinCost
    ): array {
        if ($senderUserId <= 0 || $receiverUserId <= 0 || $senderUserId === $receiverUserId) {
            return [];
        }

        $pair = $this->ensurePairStats($senderUserId, $receiverUserId);
        $direction = $this->directionColumns($pair, $senderUserId);
        $score = max(15, min(90, 15 + (int) floor($coinCost / 10)));

        $metadata = [
            'gift_name' => $giftName,
            'coin_cost' => $coinCost,
        ];

        $this->insertEvent('gift_sent', $senderUserId, $receiverUserId, (int) $pair['id'], $giftTransactionId, $metadata, true);
        $this->incrementPairStats((int) $pair['id'], [
            $direction['gifts'] => 1,
            'gift_coin_total' => $coinCost,
            'total_score' => $score,
        ]);

        $announcement = $this->createAnnouncementIfAllowed(
            'gift_sent',
            (int) $pair['id'],
            $senderUserId,
            $receiverUserId,
            'มีคนส่งของขวัญให้กัน',
            'ส่ง ' . $giftName . ' ให้กันบน LoveMatch.Love',
            3,
            $metadata
        );

        return $announcement ?: [];
    }

    public function recordPrivateMessage(int $senderUserId, int $receiverUserId, int $messageId): array
    {
        if ($senderUserId <= 0 || $receiverUserId <= 0 || $senderUserId === $receiverUserId) {
            return [];
        }

        $pair = $this->ensurePairStats($senderUserId, $receiverUserId);
        $direction = $this->directionColumns($pair, $senderUserId);

        $this->insertEvent('private_message', $senderUserId, $receiverUserId, (int) $pair['id'], $messageId, null, false);
        $this->incrementPairStats((int) $pair['id'], [
            $direction['messages'] => 1,
            'total_messages_count' => 1,
            'total_score' => 2,
        ]);

        $pair = $this->findPairStats((int) $pair['id']);
        if (!$pair || (int) $pair['total_messages_count'] < 10) {
            return [];
        }

        $announcement = $this->createAnnouncementIfAllowed(
            'chat_streak',
            (int) $pair['id'],
            $senderUserId,
            $receiverUserId,
            'คู่นี้คุยกันบ่อย',
            'มีสถิติแชทส่วนตัวเพิ่มขึ้นต่อเนื่อง ระบบจัดเป็นคู่ที่น่าจับตา',
            24
        );

        return $announcement ?: [];
    }

    public function listWallAnnouncements(int $limit = 20): array
    {
        $limit = max(1, min(50, $limit));
        $statement = $this->db->query(
            "SELECT
                wa.id,
                wa.event_type,
                wa.title,
                wa.body,
                wa.metadata_json,
                wa.created_at,
                actor.id AS actor_user_id,
                actor.display_name AS actor_name,
                actor.avatar_url AS actor_avatar_url,
                target.id AS target_user_id,
                target.display_name AS target_name,
                target.avatar_url AS target_avatar_url,
                mps.total_score,
                mps.total_messages_count,
                mps.gift_coin_total,
                (mps.user_one_to_two_views + mps.user_two_to_one_views) AS profile_views_count,
                (mps.user_one_to_two_gifts + mps.user_two_to_one_gifts) AS gifts_count
             FROM wall_announcements wa
             INNER JOIN users actor ON actor.id = wa.actor_user_id
             INNER JOIN users target ON target.id = wa.target_user_id
             INNER JOIN match_pair_stats mps ON mps.id = wa.pair_stats_id
             WHERE wa.visibility = 'public'
             ORDER BY wa.id DESC
             LIMIT {$limit}"
        );

        return array_map([$this, 'formatAnnouncement'], $statement->fetchAll() ?: []);
    }

    public function listTopPairs(int $limit = 5): array
    {
        $limit = max(1, min(20, $limit));
        $statement = $this->db->query(
            "SELECT
                mps.id,
                mps.total_score,
                mps.total_messages_count,
                mps.gift_coin_total,
                (mps.user_one_to_two_views + mps.user_two_to_one_views) AS profile_views_count,
                (mps.user_one_to_two_gifts + mps.user_two_to_one_gifts) AS gifts_count,
                u1.id AS user_one_id,
                u1.display_name AS user_one_name,
                u1.avatar_url AS user_one_avatar_url,
                u2.id AS user_two_id,
                u2.display_name AS user_two_name,
                u2.avatar_url AS user_two_avatar_url
             FROM match_pair_stats mps
             INNER JOIN users u1 ON u1.id = mps.user_one_id
             INNER JOIN users u2 ON u2.id = mps.user_two_id
             WHERE mps.total_score > 0
             ORDER BY mps.total_score DESC, mps.last_interaction_at DESC
             LIMIT {$limit}"
        );

        return array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'score' => (int) $row['total_score'],
                'profile_views_count' => (int) $row['profile_views_count'],
                'gifts_count' => (int) $row['gifts_count'],
                'messages_count' => (int) $row['total_messages_count'],
                'gift_coin_total' => (int) $row['gift_coin_total'],
                'user_one' => [
                    'id' => (int) $row['user_one_id'],
                    'display_name' => $row['user_one_name'],
                    'avatar_url' => $row['user_one_avatar_url'] ?: null,
                ],
                'user_two' => [
                    'id' => (int) $row['user_two_id'],
                    'display_name' => $row['user_two_name'],
                    'avatar_url' => $row['user_two_avatar_url'] ?: null,
                ],
            ];
        }, $statement->fetchAll() ?: []);
    }

    private function ensurePairStats(int $userA, int $userB): array
    {
        $userOneId = min($userA, $userB);
        $userTwoId = max($userA, $userB);

        $statement = $this->db->prepare(
            'INSERT INTO match_pair_stats (user_one_id, user_two_id, created_at, updated_at, last_interaction_at)
             VALUES (:user_one_id, :user_two_id, NOW(), NOW(), NOW())
             ON DUPLICATE KEY UPDATE last_interaction_at = NOW(), updated_at = NOW()'
        );
        $statement->execute([
            'user_one_id' => $userOneId,
            'user_two_id' => $userTwoId,
        ]);

        $select = $this->db->prepare(
            'SELECT *
             FROM match_pair_stats
             WHERE user_one_id = :user_one_id AND user_two_id = :user_two_id
             LIMIT 1'
        );
        $select->execute([
            'user_one_id' => $userOneId,
            'user_two_id' => $userTwoId,
        ]);

        return $select->fetch() ?: [];
    }

    private function findPairStats(int $pairStatsId): ?array
    {
        $statement = $this->db->prepare('SELECT * FROM match_pair_stats WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $pairStatsId]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    private function directionColumns(array $pair, int $actorUserId): array
    {
        $prefix = (int) $pair['user_one_id'] === $actorUserId ? 'user_one_to_two' : 'user_two_to_one';

        return [
            'views' => $prefix . '_views',
            'likes' => $prefix . '_likes',
            'gifts' => $prefix . '_gifts',
            'messages' => $prefix . '_messages',
        ];
    }

    /**
     * @param array<string, int> $increments
     */
    private function incrementPairStats(int $pairStatsId, array $increments): void
    {
        $allowed = [
            'user_one_to_two_views',
            'user_two_to_one_views',
            'user_one_to_two_likes',
            'user_two_to_one_likes',
            'user_one_to_two_gifts',
            'user_two_to_one_gifts',
            'user_one_to_two_messages',
            'user_two_to_one_messages',
            'total_messages_count',
            'gift_coin_total',
            'match_count',
            'total_score',
        ];

        $sets = [];
        $params = ['id' => $pairStatsId];

        foreach ($increments as $column => $amount) {
            if (!in_array($column, $allowed, true)) {
                continue;
            }
            $param = $column . '_inc';
            $sets[] = "{$column} = {$column} + :{$param}";
            $params[$param] = $amount;
        }

        if (!$sets) {
            return;
        }

        $sets[] = 'last_interaction_at = NOW()';
        $sets[] = 'updated_at = NOW()';

        $statement = $this->db->prepare(
            'UPDATE match_pair_stats SET ' . implode(', ', $sets) . ' WHERE id = :id'
        );
        $statement->execute($params);
    }

    /**
     * @param array<string, mixed>|null $metadata
     */
    private function insertEvent(
        string $eventType,
        int $actorUserId,
        int $targetUserId,
        int $pairStatsId,
        ?int $referenceId,
        ?array $metadata,
        bool $isPublic
    ): void {
        $statement = $this->db->prepare(
            'INSERT INTO match_events (
                pair_stats_id, event_type, actor_user_id, target_user_id, reference_id,
                metadata_json, is_public, created_at
             ) VALUES (
                :pair_stats_id, :event_type, :actor_user_id, :target_user_id, :reference_id,
                :metadata_json, :is_public, NOW()
             )'
        );
        $statement->execute([
            'pair_stats_id' => $pairStatsId,
            'event_type' => $eventType,
            'actor_user_id' => $actorUserId,
            'target_user_id' => $targetUserId,
            'reference_id' => $referenceId,
            'metadata_json' => $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'is_public' => $isPublic ? 1 : 0,
        ]);
    }

    /**
     * @param array<string, mixed>|null $metadata
     */
    private function createAnnouncementIfAllowed(
        string $eventType,
        int $pairStatsId,
        int $actorUserId,
        int $targetUserId,
        string $title,
        string $body,
        int $cooldownHours,
        ?array $metadata = null
    ): ?array {
        $pair = $this->findPairStats($pairStatsId);
        if (!$pair) {
            return null;
        }

        $recent = $this->db->prepare(
            "SELECT COUNT(*)
             FROM wall_announcements
             WHERE pair_stats_id = :pair_stats_id
               AND event_type = :event_type
               AND created_at >= DATE_SUB(NOW(), INTERVAL {$cooldownHours} HOUR)"
        );
        $recent->execute([
            'pair_stats_id' => $pairStatsId,
            'event_type' => $eventType,
        ]);

        if ((int) $recent->fetchColumn() > 0) {
            return null;
        }

        $statement = $this->db->prepare(
            'INSERT INTO wall_announcements (
                pair_stats_id, event_type, actor_user_id, target_user_id, title, body,
                metadata_json, visibility, created_at, updated_at
             ) VALUES (
                :pair_stats_id, :event_type, :actor_user_id, :target_user_id, :title, :body,
                :metadata_json, "public", NOW(), NOW()
             )'
        );
        $statement->execute([
            'pair_stats_id' => $pairStatsId,
            'event_type' => $eventType,
            'actor_user_id' => $actorUserId,
            'target_user_id' => $targetUserId,
            'title' => $title,
            'body' => $body,
            'metadata_json' => $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        ]);

        $id = (int) $this->db->lastInsertId();
        $select = $this->db->prepare(
            'SELECT
                wa.id,
                wa.event_type,
                wa.title,
                wa.body,
                wa.metadata_json,
                wa.created_at,
                actor.id AS actor_user_id,
                actor.display_name AS actor_name,
                actor.avatar_url AS actor_avatar_url,
                target.id AS target_user_id,
                target.display_name AS target_name,
                target.avatar_url AS target_avatar_url,
                mps.total_score,
                mps.total_messages_count,
                mps.gift_coin_total,
                (mps.user_one_to_two_views + mps.user_two_to_one_views) AS profile_views_count,
                (mps.user_one_to_two_gifts + mps.user_two_to_one_gifts) AS gifts_count
             FROM wall_announcements wa
             INNER JOIN users actor ON actor.id = wa.actor_user_id
             INNER JOIN users target ON target.id = wa.target_user_id
             INNER JOIN match_pair_stats mps ON mps.id = wa.pair_stats_id
             WHERE wa.id = :id
             LIMIT 1'
        );
        $select->execute(['id' => $id]);
        $row = $select->fetch();

        return $row ? $this->formatAnnouncement($row) : null;
    }

    private function formatAnnouncement(array $row): array
    {
        $metadata = [];
        if (!empty($row['metadata_json'])) {
            $decoded = json_decode((string) $row['metadata_json'], true);
            $metadata = is_array($decoded) ? $decoded : [];
        }

        return [
            'id' => (int) $row['id'],
            'event_type' => $row['event_type'],
            'title' => $row['title'],
            'body' => $row['body'],
            'created_at' => $row['created_at'],
            'score' => (int) $row['total_score'],
            'profile_views_count' => (int) $row['profile_views_count'],
            'gifts_count' => (int) $row['gifts_count'],
            'messages_count' => (int) $row['total_messages_count'],
            'gift_coin_total' => (int) $row['gift_coin_total'],
            'metadata' => $metadata,
            'actor' => [
                'id' => (int) $row['actor_user_id'],
                'display_name' => $row['actor_name'],
                'avatar_url' => $row['actor_avatar_url'] ?: null,
            ],
            'target' => [
                'id' => (int) $row['target_user_id'],
                'display_name' => $row['target_name'],
                'avatar_url' => $row['target_avatar_url'] ?: null,
            ],
        ];
    }
}
