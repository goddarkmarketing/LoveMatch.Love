SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS match_pair_stats (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_one_id BIGINT UNSIGNED NOT NULL,
  user_two_id BIGINT UNSIGNED NOT NULL,
  user_one_to_two_views INT UNSIGNED NOT NULL DEFAULT 0,
  user_two_to_one_views INT UNSIGNED NOT NULL DEFAULT 0,
  user_one_to_two_likes INT UNSIGNED NOT NULL DEFAULT 0,
  user_two_to_one_likes INT UNSIGNED NOT NULL DEFAULT 0,
  user_one_to_two_gifts INT UNSIGNED NOT NULL DEFAULT 0,
  user_two_to_one_gifts INT UNSIGNED NOT NULL DEFAULT 0,
  user_one_to_two_messages INT UNSIGNED NOT NULL DEFAULT 0,
  user_two_to_one_messages INT UNSIGNED NOT NULL DEFAULT 0,
  total_messages_count INT UNSIGNED NOT NULL DEFAULT 0,
  gift_coin_total INT UNSIGNED NOT NULL DEFAULT 0,
  match_count INT UNSIGNED NOT NULL DEFAULT 0,
  total_score INT UNSIGNED NOT NULL DEFAULT 0,
  last_interaction_at DATETIME NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_match_pair_stats_user_one FOREIGN KEY (user_one_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_match_pair_stats_user_two FOREIGN KEY (user_two_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_match_pair_stats (user_one_id, user_two_id),
  INDEX idx_match_pair_stats_score (total_score, last_interaction_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS match_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pair_stats_id BIGINT UNSIGNED NOT NULL,
  event_type ENUM('profile_view','like','super_like','mutual_match','gift_sent','private_message') NOT NULL,
  actor_user_id BIGINT UNSIGNED NOT NULL,
  target_user_id BIGINT UNSIGNED NOT NULL,
  reference_id BIGINT UNSIGNED NULL,
  metadata_json JSON NULL,
  is_public TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_match_events_pair FOREIGN KEY (pair_stats_id) REFERENCES match_pair_stats(id) ON DELETE CASCADE,
  CONSTRAINT fk_match_events_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_match_events_target FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_match_events_pair_type (pair_stats_id, event_type, created_at),
  INDEX idx_match_events_public (is_public, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wall_announcements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pair_stats_id BIGINT UNSIGNED NOT NULL,
  event_type ENUM('mutual_profile_view','mutual_match','gift_sent','chat_streak') NOT NULL,
  actor_user_id BIGINT UNSIGNED NOT NULL,
  target_user_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL,
  body VARCHAR(500) NOT NULL,
  metadata_json JSON NULL,
  visibility ENUM('public','hidden') NOT NULL DEFAULT 'public',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_wall_announcements_pair FOREIGN KEY (pair_stats_id) REFERENCES match_pair_stats(id) ON DELETE CASCADE,
  CONSTRAINT fk_wall_announcements_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_wall_announcements_target FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_wall_announcements_visibility (visibility, created_at),
  INDEX idx_wall_announcements_pair_type (pair_stats_id, event_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
