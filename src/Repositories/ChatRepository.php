<?php

namespace App\Repositories;

use PDO;
use RuntimeException;

class ChatRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function findRoomById(int $roomId): ?array
    {
        $statement = $this->db->prepare('SELECT * FROM chat_rooms WHERE id = :id AND is_active = 1 LIMIT 1');
        $statement->execute(['id' => $roomId]);
        $room = $statement->fetch();

        return $room ?: null;
    }

    public function publicRoomSummaries(int $userId): array
    {
        $statement = $this->db->prepare(
            'SELECT
                cr.id,
                cr.code,
                cr.name_th,
                cr.room_type,
                (
                    SELECT COUNT(*)
                    FROM chat_room_members crm_count
                    WHERE crm_count.room_id = cr.id AND crm_count.join_status = "joined"
                ) AS member_count,
                crm.last_read_message_id,
                last_message.id AS last_message_id,
                last_message.body AS last_message_body,
                last_message.sent_at AS last_message_sent_at,
                sender.display_name AS last_message_sender_name,
                (
                    SELECT COUNT(*)
                    FROM messages unread
                    WHERE unread.room_id = cr.id
                      AND unread.id > COALESCE(crm.last_read_message_id, 0)
                      AND unread.sender_user_id <> :user_id
                ) AS unread_count
             FROM chat_rooms cr
             LEFT JOIN chat_room_members crm
                ON crm.room_id = cr.id
               AND crm.user_id = :user_id
             LEFT JOIN messages last_message
                ON last_message.id = (
                    SELECT m2.id
                    FROM messages m2
                    WHERE m2.room_id = cr.id
                    ORDER BY m2.id DESC
                    LIMIT 1
                )
             LEFT JOIN users sender ON sender.id = last_message.sender_user_id
             WHERE cr.is_active = 1
               AND cr.room_type = "public"
             ORDER BY FIELD(cr.code, "general", "thai", "international"), cr.id ASC'
        );
        $statement->execute(['user_id' => $userId]);

        return $statement->fetchAll();
    }

    public function privateRoomSummaries(int $userId): array
    {
        $statement = $this->db->prepare(
            'SELECT
                cr.id,
                cr.code,
                cr.room_type,
                crm.last_read_message_id,
                other_user.id AS other_user_id,
                other_user.display_name,
                other_user.avatar_url,
                other_user.last_seen_at,
                last_message.id AS last_message_id,
                last_message.body AS last_message_body,
                last_message.sent_at AS last_message_sent_at,
                sender.display_name AS last_message_sender_name,
                (
                    SELECT COUNT(*)
                    FROM messages unread
                    WHERE unread.room_id = cr.id
                      AND unread.id > COALESCE(crm.last_read_message_id, 0)
                      AND unread.sender_user_id <> :user_id
                ) AS unread_count
             FROM chat_room_members crm
             INNER JOIN chat_rooms cr
                ON cr.id = crm.room_id
               AND cr.room_type = "private"
               AND cr.is_active = 1
             INNER JOIN chat_room_members other_member
                ON other_member.room_id = cr.id
               AND other_member.user_id <> :user_id
             INNER JOIN users other_user
                ON other_user.id = other_member.user_id
             LEFT JOIN messages last_message
                ON last_message.id = (
                    SELECT m2.id
                    FROM messages m2
                    WHERE m2.room_id = cr.id
                    ORDER BY m2.id DESC
                    LIMIT 1
                )
             LEFT JOIN users sender ON sender.id = last_message.sender_user_id
             WHERE crm.user_id = :user_id
               AND crm.join_status = "joined"
             ORDER BY COALESCE(last_message.id, 0) DESC, cr.id DESC'
        );
        $statement->execute(['user_id' => $userId]);

        return $statement->fetchAll();
    }

    public function roomMembers(int $roomId): array
    {
        $statement = $this->db->prepare(
            'SELECT
                u.id,
                u.display_name,
                u.avatar_url,
                u.city,
                u.province,
                u.last_seen_at,
                crm.joined_at
             FROM chat_room_members crm
             INNER JOIN users u ON u.id = crm.user_id
             WHERE crm.room_id = :room_id
               AND crm.join_status = "joined"
             ORDER BY u.last_seen_at DESC, u.display_name ASC'
        );
        $statement->execute(['room_id' => $roomId]);

        return $statement->fetchAll();
    }

    public function listMessages(int $roomId, int $limit = 50): array
    {
        $statement = $this->db->prepare(
            'SELECT
                m.id,
                m.message_type,
                m.body,
                m.translated_body,
                m.sent_at,
                m.sender_user_id,
                u.display_name,
                u.avatar_url
             FROM messages m
             INNER JOIN users u ON u.id = m.sender_user_id
             WHERE m.room_id = :room_id
             ORDER BY m.id DESC
             LIMIT ' . (int) $limit
        );
        $statement->execute(['room_id' => $roomId]);
        $messages = $statement->fetchAll();

        return array_reverse($messages);
    }

    public function createMessage(int $roomId, int $senderUserId, string $body, string $messageType = 'text', ?int $giftTransactionId = null): array
    {
        $room = $this->findRoomById($roomId);
        if (!$room) {
            throw new RuntimeException('ไม่พบห้องแชต');
        }

        $statement = $this->db->prepare(
            'INSERT INTO messages (
                room_id, sender_user_id, message_type, body, translated_body,
                gift_transaction_id, moderation_status, sent_at, created_at, updated_at
             ) VALUES (
                :room_id, :sender_user_id, :message_type, :body, NULL,
                :gift_transaction_id, "clean", NOW(), NOW(), NOW()
             )'
        );
        $statement->execute([
            'room_id' => $roomId,
            'sender_user_id' => $senderUserId,
            'message_type' => $messageType,
            'body' => $body,
            'gift_transaction_id' => $giftTransactionId,
        ]);

        $messageId = (int) $this->db->lastInsertId();
        $message = $this->db->prepare(
            'SELECT
                m.id,
                m.message_type,
                m.body,
                m.sent_at,
                m.sender_user_id,
                u.display_name
             FROM messages m
             INNER JOIN users u ON u.id = m.sender_user_id
             WHERE m.id = :id
             LIMIT 1'
        );
        $message->execute(['id' => $messageId]);

        return $message->fetch() ?: [];
    }

    public function markRoomRead(int $roomId, int $userId, ?int $lastMessageId = null): void
    {
        if ($lastMessageId === null) {
            $statement = $this->db->prepare(
                'SELECT id
                 FROM messages
                 WHERE room_id = :room_id
                 ORDER BY id DESC
                 LIMIT 1'
            );
            $statement->execute(['room_id' => $roomId]);
            $lastMessageId = (int) ($statement->fetchColumn() ?: 0);
        }

        $upsert = $this->db->prepare(
            'INSERT INTO chat_room_members (
                room_id, user_id, member_role, join_status, joined_at, last_read_message_id, created_at, updated_at
             ) VALUES (
                :room_id, :user_id, "member", "joined", NOW(), :last_read_message_id, NOW(), NOW()
             )
             ON DUPLICATE KEY UPDATE
                join_status = "joined",
                last_read_message_id = :last_read_message_id,
                updated_at = NOW()'
        );
        $upsert->execute([
            'room_id' => $roomId,
            'user_id' => $userId,
            'last_read_message_id' => $lastMessageId > 0 ? $lastMessageId : null,
        ]);
    }

    public function blockUser(int $blockerUserId, int $blockedUserId): void
    {
        $statement = $this->db->prepare(
            'INSERT INTO user_blocks (blocker_user_id, blocked_user_id, created_at)
             VALUES (:blocker_user_id, :blocked_user_id, NOW())
             ON DUPLICATE KEY UPDATE created_at = NOW()'
        );
        $statement->execute([
            'blocker_user_id' => $blockerUserId,
            'blocked_user_id' => $blockedUserId,
        ]);
    }

    public function hasBlockBetween(int $userId, int $otherUserId): bool
    {
        $statement = $this->db->prepare(
            'SELECT COUNT(*)
             FROM user_blocks
             WHERE (blocker_user_id = :user_id AND blocked_user_id = :other_user_id)
                OR (blocker_user_id = :other_user_id AND blocked_user_id = :user_id)'
        );
        $statement->execute([
            'user_id' => $userId,
            'other_user_id' => $otherUserId,
        ]);

        return (int) $statement->fetchColumn() > 0;
    }

    public function createReport(int $reporterUserId, int $reportedUserId, ?int $roomId, ?int $messageId, string $reasonCode, ?string $detailText = null): int
    {
        $statement = $this->db->prepare(
            'INSERT INTO reports (
                reporter_user_id, reported_user_id, room_id, message_id, reason_code, detail_text, status, created_at, updated_at
             ) VALUES (
                :reporter_user_id, :reported_user_id, :room_id, :message_id, :reason_code, :detail_text, "open", NOW(), NOW()
             )'
        );
        $statement->execute([
            'reporter_user_id' => $reporterUserId,
            'reported_user_id' => $reportedUserId,
            'room_id' => $roomId,
            'message_id' => $messageId,
            'reason_code' => $reasonCode,
            'detail_text' => $detailText !== '' ? $detailText : null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function findOrCreatePrivateRoom(int $userA, int $userB): array
    {
        $userOneId = min($userA, $userB);
        $userTwoId = max($userA, $userB);
        $code = 'private-' . $userOneId . '-' . $userTwoId;

        $existing = $this->db->prepare('SELECT * FROM chat_rooms WHERE code = :code LIMIT 1');
        $existing->execute(['code' => $code]);
        $room = $existing->fetch();

        if (!$room) {
            $insertRoom = $this->db->prepare(
                'INSERT INTO chat_rooms (
                    room_type, code, name_th, description, visibility, required_gift_id, is_active, created_by, created_at, updated_at
                 ) VALUES (
                    "private", :code, :name_th, :description, "public", NULL, 1, :created_by, NOW(), NOW()
                 )'
            );
            $insertRoom->execute([
                'code' => $code,
                'name_th' => 'แชทส่วนตัว',
                'description' => 'ห้องสนทนาส่วนตัว',
                'created_by' => $userA,
            ]);

            $roomId = (int) $this->db->lastInsertId();

            $insertMember = $this->db->prepare(
                'INSERT INTO chat_room_members (
                    room_id, user_id, member_role, join_status, joined_at, created_at, updated_at
                 ) VALUES (
                    :room_id, :user_id, "member", "joined", NOW(), NOW(), NOW()
                 )
                 ON DUPLICATE KEY UPDATE join_status = "joined", updated_at = NOW()'
            );

            $insertMember->execute([
                'room_id' => $roomId,
                'user_id' => $userA,
            ]);
            $insertMember->execute([
                'room_id' => $roomId,
                'user_id' => $userB,
            ]);

            $room = $this->findRoomById($roomId);
        }

        return $room ?: [];
    }
}
