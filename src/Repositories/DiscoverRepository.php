<?php

namespace App\Repositories;

use PDO;

class DiscoverRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function discover(?int $viewerUserId, int $limit = 20): array
    {
        $sql = '
            SELECT
                u.id,
                u.display_name,
                u.first_name,
                u.last_name,
                u.birth_date,
                u.city,
                u.province,
                u.bio,
                u.avatar_url,
                u.status
            FROM users u
            WHERE u.status = "active"
        ';

        $params = [];

        if ($viewerUserId) {
            $sql .= '
                AND u.id <> :viewer_user_id
                AND NOT EXISTS (
                    SELECT 1
                    FROM swipes s
                    WHERE s.actor_user_id = :viewer_user_id
                      AND s.target_user_id = u.id
                )
            ';
            $params['viewer_user_id'] = $viewerUserId;
        }

        $sql .= ' ORDER BY u.id DESC LIMIT ' . (int) $limit;

        $statement = $this->db->prepare($sql);
        $statement->execute($params);

        return array_map(function (array $row): array {
            $age = null;
            if (!empty($row['birth_date'])) {
                $age = (int) date_diff(date_create($row['birth_date']), date_create('today'))->y;
            }

            return [
                'id' => (int) $row['id'],
                'display_name' => $row['display_name'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'age' => $age,
                'location' => trim(implode(', ', array_filter([$row['city'], $row['province']]))),
                'bio' => $row['bio'] ?: 'สมาชิกใหม่ของ LoveMatch.Love',
                'avatar_url' => $row['avatar_url'],
            ];
        }, $statement->fetchAll());
    }
}
