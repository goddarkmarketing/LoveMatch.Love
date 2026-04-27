<?php

namespace App\Controllers;

use App\Repositories\ChatRepository;
use App\Repositories\MatchRepository;
use App\Support\Request;
use App\Support\Response;
use RuntimeException;

class ChatController
{
    public function __construct(
        private ChatRepository $chat,
        private MatchRepository $matches
    )
    {
    }

    public function messages(int $roomId): void
    {
        $room = $this->chat->findRoomById($roomId);
        if (!$room) {
            Response::json([
                'success' => false,
                'message' => 'ไม่พบห้องแชต',
            ], 404);
        }

        Response::json([
            'success' => true,
            'data' => [
                'room' => [
                    'id' => (int) $room['id'],
                    'name' => $room['name_th'],
                    'code' => $room['code'],
                ],
                'messages' => $this->chat->listMessages($roomId),
            ],
        ]);
    }

    public function members(int $roomId): void
    {
        $room = $this->chat->findRoomById($roomId);
        if (!$room) {
            Response::json([
                'success' => false,
                'message' => 'à¹„à¸¡à¹ˆà¸žà¸šà¸«à¹‰à¸­à¸‡à¹à¸Šà¸—',
            ], 404);
        }

        $members = array_map(function (array $member): array {
            $lastSeenAt = $member['last_seen_at'] ?: null;
            $isOnline = false;
            if ($lastSeenAt) {
                $isOnline = (time() - strtotime((string) $lastSeenAt)) <= 300;
            }

            return [
                'id' => (int) $member['id'],
                'display_name' => $member['display_name'],
                'avatar_url' => $member['avatar_url'] ?: null,
                'city' => $member['city'] ?: null,
                'province' => $member['province'] ?: null,
                'last_seen_at' => $lastSeenAt,
                'is_online' => $isOnline,
                'joined_at' => $member['joined_at'] ?: null,
            ];
        }, $this->chat->roomMembers($roomId));

        Response::json([
            'success' => true,
            'data' => [
                'room' => [
                    'id' => (int) $room['id'],
                    'name' => $room['name_th'],
                    'code' => $room['code'],
                    'room_type' => $room['room_type'],
                ],
                'members' => $members,
            ],
        ]);
    }

    public function sidebar(int $userId): void
    {
        $publicRooms = array_map(function (array $room): array {
            return [
                'id' => (int) $room['id'],
                'code' => $room['code'],
                'name' => $room['name_th'],
                'room_type' => $room['room_type'],
                'member_count' => (int) ($room['member_count'] ?? 0),
                'last_message' => $room['last_message_body'] ?: null,
                'last_message_sent_at' => $room['last_message_sent_at'] ?: null,
                'last_message_sender_name' => $room['last_message_sender_name'] ?: null,
                'unread_count' => (int) ($room['unread_count'] ?? 0),
            ];
        }, $this->chat->publicRoomSummaries($userId));

        $privateRooms = array_map(function (array $room): array {
            $lastSeenAt = $room['last_seen_at'] ?: null;
            $isOnline = false;
            if ($lastSeenAt) {
                $isOnline = (time() - strtotime((string) $lastSeenAt)) <= 300;
            }

            return [
                'id' => (int) $room['id'],
                'code' => $room['code'],
                'room_type' => $room['room_type'],
                'other_user_id' => (int) $room['other_user_id'],
                'display_name' => $room['display_name'],
                'avatar_url' => $room['avatar_url'] ?: null,
                'last_seen_at' => $lastSeenAt,
                'is_online' => $isOnline,
                'last_message' => $room['last_message_body'] ?: null,
                'last_message_sent_at' => $room['last_message_sent_at'] ?: null,
                'last_message_sender_name' => $room['last_message_sender_name'] ?: null,
                'unread_count' => (int) ($room['unread_count'] ?? 0),
            ];
        }, $this->chat->privateRoomSummaries($userId));

        Response::json([
            'success' => true,
            'data' => [
                'public_rooms' => $publicRooms,
                'private_rooms' => $privateRooms,
            ],
        ]);
    }

    public function sendMessage(Request $request, int $roomId, int $userId): void
    {
        $payload = $request->json();
        $body = trim((string) ($payload['body'] ?? ''));

        if ($body === '') {
            Response::json([
                'success' => false,
                'message' => 'ข้อความต้องไม่ว่าง',
            ], 422);
        }

        try {
            $message = $this->chat->createMessage($roomId, $userId, $body);
        } catch (RuntimeException $exception) {
            Response::json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 404);
        }

        Response::json([
            'success' => true,
            'message' => 'ส่งข้อความสำเร็จ',
            'data' => ['message' => $message],
        ], 201);
    }

    public function startPrivate(Request $request, int $userId): void
    {
        $payload = $request->json();
        $targetUserId = (int) ($payload['target_user_id'] ?? 0);

        if ($targetUserId <= 0 || $targetUserId === $userId) {
            Response::json([
                'success' => false,
                'message' => 'target_user_id ไม่ถูกต้อง',
            ], 422);
        }

        if (!$this->matches->isMatched($userId, $targetUserId)) {
            Response::json([
                'success' => false,
                'message' => 'ต้อง match กันก่อนจึงจะเริ่ม private chat ได้',
            ], 403);
        }

        if ($this->chat->hasBlockBetween($userId, $targetUserId)) {
            Response::json([
                'success' => false,
                'message' => 'ไม่สามารถเปิดแชทส่วนตัวได้ เนื่องจากมีการบล็อกผู้ใช้นี้อยู่',
            ], 403);
        }

        $room = $this->chat->findOrCreatePrivateRoom($userId, $targetUserId);

        Response::json([
            'success' => true,
            'message' => 'เปิดห้องแชทส่วนตัวแล้ว',
            'data' => [
                'room' => [
                    'id' => (int) ($room['id'] ?? 0),
                    'name' => $room['name_th'] ?? 'แชทส่วนตัว',
                    'code' => $room['code'] ?? null,
                    'room_type' => $room['room_type'] ?? 'private',
                ],
            ],
        ]);
    }

    public function markRead(Request $request, int $roomId, int $userId): void
    {
        $payload = $request->json();
        $lastMessageId = isset($payload['last_message_id']) ? (int) $payload['last_message_id'] : null;

        $room = $this->chat->findRoomById($roomId);
        if (!$room) {
            Response::json([
                'success' => false,
                'message' => 'ไม่พบห้องแชท',
            ], 404);
        }

        $this->chat->markRoomRead($roomId, $userId, $lastMessageId);

        Response::json([
            'success' => true,
            'message' => 'อัปเดตสถานะอ่านข้อความแล้ว',
        ]);
    }

    public function report(Request $request, int $userId): void
    {
        $payload = $request->json();
        $reportedUserId = (int) ($payload['reported_user_id'] ?? 0);
        $roomId = isset($payload['room_id']) ? (int) $payload['room_id'] : null;
        $messageId = isset($payload['message_id']) ? (int) $payload['message_id'] : null;
        $reasonCode = trim((string) ($payload['reason_code'] ?? ''));
        $detailText = trim((string) ($payload['detail_text'] ?? ''));

        if ($reportedUserId <= 0 || $reasonCode === '') {
            Response::json([
                'success' => false,
                'message' => 'reported_user_id และ reason_code จำเป็นต้องส่งมา',
            ], 422);
        }

        $reportId = $this->chat->createReport($userId, $reportedUserId, $roomId, $messageId, $reasonCode, $detailText);

        Response::json([
            'success' => true,
            'message' => 'ส่งรายงานเรียบร้อยแล้ว',
            'data' => [
                'report_id' => $reportId,
            ],
        ], 201);
    }

    public function block(Request $request, int $userId): void
    {
        $payload = $request->json();
        $blockedUserId = (int) ($payload['blocked_user_id'] ?? 0);

        if ($blockedUserId <= 0 || $blockedUserId === $userId) {
            Response::json([
                'success' => false,
                'message' => 'blocked_user_id ไม่ถูกต้อง',
            ], 422);
        }

        $this->chat->blockUser($userId, $blockedUserId);

        Response::json([
            'success' => true,
            'message' => 'บล็อกผู้ใช้นี้เรียบร้อยแล้ว',
        ]);
    }
}
