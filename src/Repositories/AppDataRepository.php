<?php

namespace App\Repositories;

use PDO;

class AppDataRepository
{
    public function __construct(private PDO $db)
    {
    }

    private function ensureDefaultGifts(): void
    {
        $this->db->exec(
            'INSERT INTO gift_catalog (code, name_th, emoji, coin_cost, unlock_type, unlock_days, is_active) VALUES
                ("rose", "ดอกกุหลาบ", "🌹", 10, "days", 1, 1),
                ("bouquet", "ช่อดอกไม้", "💐", 50, "days", 7, 1),
                ("chocolate_box", "กล่องช็อกโกแลต", "🍫", 80, "days", 10, 1),
                ("teddy_bear", "ตุ๊กตาหมี", "🧸", 120, "days", 14, 1),
                ("diamond_ring", "แหวนเพชร", "💎", 200, "days", 30, 1),
                ("plane_ticket", "ตั๋วเครื่องบิน", "✈️", 500, "permanent", NULL, 1)
             ON DUPLICATE KEY UPDATE
                name_th = VALUES(name_th),
                emoji = VALUES(emoji),
                coin_cost = VALUES(coin_cost),
                unlock_type = VALUES(unlock_type),
                unlock_days = VALUES(unlock_days),
                is_active = VALUES(is_active)'
        );
    }

    public function gifts(): array
    {
        $this->ensureDefaultGifts();

        $statement = $this->db->query(
            'SELECT id, code, name_th, emoji, coin_cost, unlock_type, unlock_days
             FROM gift_catalog
             WHERE is_active = 1
             ORDER BY coin_cost ASC'
        );

        return $statement->fetchAll();
    }

    public function subscriptionPlans(): array
    {
        $statement = $this->db->query(
            'SELECT id, code, name_th, tier, billing_cycle, price_thb, coin_bonus, feature_json
             FROM subscription_plans
             WHERE is_active = 1
             ORDER BY FIELD(tier, "free", "premium", "vip"), price_thb ASC'
        );

        return $statement->fetchAll();
    }

    public function publicChatRooms(): array
    {
        $statement = $this->db->query(
            'SELECT
                cr.id,
                cr.code,
                cr.name_th,
                cr.room_type,
                (
                    SELECT COUNT(*)
                    FROM chat_room_members crm
                    WHERE crm.room_id = cr.id AND crm.join_status = "joined"
                ) AS member_count
             FROM chat_rooms cr
             WHERE cr.is_active = 1
               AND cr.room_type = "public"
             ORDER BY FIELD(cr.code, "general", "thai", "international"), cr.id ASC'
        );

        return $statement->fetchAll();
    }
}
