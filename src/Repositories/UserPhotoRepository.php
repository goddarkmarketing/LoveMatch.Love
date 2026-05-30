<?php

namespace App\Repositories;

use PDO;

class UserPhotoRepository
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * @param list<array{file_url: string, sort_order: int, is_primary: int}> $photos
     */
    public function insertPhotos(int $userId, array $photos): void
    {
        if ($photos === []) {
            return;
        }

        $statement = $this->db->prepare(
            'INSERT INTO user_photos (user_id, file_url, sort_order, is_primary, moderation_status, created_at, updated_at)
             VALUES (:user_id, :file_url, :sort_order, :is_primary, :moderation_status, NOW(), NOW())'
        );

        foreach ($photos as $photo) {
            $statement->execute([
                'user_id' => $userId,
                'file_url' => $photo['file_url'],
                'sort_order' => $photo['sort_order'],
                'is_primary' => $photo['is_primary'],
                'moderation_status' => 'pending',
            ]);
        }
    }
}
