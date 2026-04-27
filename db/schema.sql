CREATE DATABASE IF NOT EXISTS lovematch_love
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE lovematch_love;

SET NAMES utf8mb4;

CREATE TABLE roles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  name_th VARCHAR(100) NOT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_id BIGINT UNSIGNED NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  first_name VARCHAR(120) NOT NULL,
  last_name VARCHAR(120) NOT NULL,
  display_name VARCHAR(150) NOT NULL,
  phone VARCHAR(30) NULL,
  birth_date DATE NULL,
  gender ENUM('male','female','non_binary','other','prefer_not_to_say') NULL,
  interested_in ENUM('male','female','everyone','other') NULL,
  country_code CHAR(2) NULL,
  province VARCHAR(120) NULL,
  city VARCHAR(120) NULL,
  bio TEXT NULL,
  avatar_url VARCHAR(255) NULL,
  email_verified_at DATETIME NULL,
  status ENUM('pending_verification','active','suspended','banned','deleted') NOT NULL DEFAULT 'pending_verification',
  is_profile_completed TINYINT(1) NOT NULL DEFAULT 0,
  last_seen_at DATETIME NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_photos (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  file_url VARCHAR(255) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  moderation_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_user_photos_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_photos_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_languages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  language_code VARCHAR(10) NOT NULL,
  proficiency ENUM('basic','intermediate','advanced','native') NOT NULL DEFAULT 'basic',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_user_languages_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_user_language (user_id, language_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE interests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(100) NOT NULL UNIQUE,
  name_th VARCHAR(120) NOT NULL,
  name_en VARCHAR(120) NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_interests (
  user_id BIGINT UNSIGNED NOT NULL,
  interest_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, interest_id),
  CONSTRAINT fk_user_interests_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_interests_interest FOREIGN KEY (interest_id) REFERENCES interests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE profile_preferences (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL UNIQUE,
  min_age TINYINT UNSIGNED NULL,
  max_age TINYINT UNSIGNED NULL,
  preferred_country_code CHAR(2) NULL,
  preferred_province VARCHAR(120) NULL,
  max_distance_km INT NULL,
  show_only_verified TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_profile_preferences_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE swipes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_user_id BIGINT UNSIGNED NOT NULL,
  target_user_id BIGINT UNSIGNED NOT NULL,
  action ENUM('like','super_like','pass','rewind') NOT NULL,
  source VARCHAR(50) NOT NULL DEFAULT 'discover',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_swipes_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_swipes_target FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_swipe (actor_user_id, target_user_id, action),
  INDEX idx_swipes_actor (actor_user_id),
  INDEX idx_swipes_target (target_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE matches (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_one_id BIGINT UNSIGNED NOT NULL,
  user_two_id BIGINT UNSIGNED NOT NULL,
  matched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status ENUM('active','unmatched','blocked','archived') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_matches_user_one FOREIGN KEY (user_one_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_matches_user_two FOREIGN KEY (user_two_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_match_pair (user_one_id, user_two_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE chat_rooms (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_type ENUM('public','private','group') NOT NULL,
  code VARCHAR(100) NULL UNIQUE,
  name_th VARCHAR(150) NOT NULL,
  description TEXT NULL,
  visibility ENUM('public','premium_only','vip_only','hidden') NOT NULL DEFAULT 'public',
  required_gift_id BIGINT UNSIGNED NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_chat_rooms_type (room_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE chat_room_members (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  member_role ENUM('member','moderator','owner') NOT NULL DEFAULT 'member',
  join_status ENUM('joined','left','removed') NOT NULL DEFAULT 'joined',
  joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  left_at DATETIME NULL,
  last_read_message_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_chat_members_room FOREIGN KEY (room_id) REFERENCES chat_rooms(id) ON DELETE CASCADE,
  CONSTRAINT fk_chat_members_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_room_member (room_id, user_id),
  INDEX idx_chat_room_members_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_id BIGINT UNSIGNED NOT NULL,
  sender_user_id BIGINT UNSIGNED NOT NULL,
  message_type ENUM('text','image','gift','system') NOT NULL DEFAULT 'text',
  body TEXT NULL,
  translated_body TEXT NULL,
  gift_transaction_id BIGINT UNSIGNED NULL,
  moderation_status ENUM('clean','flagged','blocked') NOT NULL DEFAULT 'clean',
  sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_messages_room FOREIGN KEY (room_id) REFERENCES chat_rooms(id) ON DELETE CASCADE,
  CONSTRAINT fk_messages_sender FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_messages_room_sent (room_id, sent_at),
  INDEX idx_messages_sender (sender_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE gift_catalog (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  name_th VARCHAR(120) NOT NULL,
  emoji VARCHAR(20) NULL,
  icon_url VARCHAR(255) NULL,
  coin_cost INT UNSIGNED NOT NULL,
  unlock_type ENUM('days','permanent') NOT NULL DEFAULT 'days',
  unlock_days SMALLINT UNSIGNED NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wallets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL UNIQUE,
  coin_balance INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_wallets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wallet_transactions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  wallet_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  transaction_type ENUM('credit','debit','refund','bonus') NOT NULL,
  source_type ENUM('gift_purchase','gift_send','subscription','admin_adjustment','signup_bonus','refund') NOT NULL,
  source_id BIGINT UNSIGNED NULL,
  amount INT NOT NULL,
  balance_before INT NOT NULL,
  balance_after INT NOT NULL,
  note VARCHAR(255) NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_wallet_transactions_wallet FOREIGN KEY (wallet_id) REFERENCES wallets(id) ON DELETE CASCADE,
  CONSTRAINT fk_wallet_transactions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_wallet_tx_user (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE gift_transactions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_id BIGINT UNSIGNED NULL,
  sender_user_id BIGINT UNSIGNED NOT NULL,
  receiver_user_id BIGINT UNSIGNED NOT NULL,
  gift_id BIGINT UNSIGNED NOT NULL,
  wallet_transaction_id BIGINT UNSIGNED NULL,
  message_text VARCHAR(500) NULL,
  unlock_start_at DATETIME NULL,
  unlock_end_at DATETIME NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_gift_tx_room FOREIGN KEY (room_id) REFERENCES chat_rooms(id) ON DELETE SET NULL,
  CONSTRAINT fk_gift_tx_sender FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_gift_tx_receiver FOREIGN KEY (receiver_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_gift_tx_gift FOREIGN KEY (gift_id) REFERENCES gift_catalog(id),
  INDEX idx_gift_tx_receiver (receiver_user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE chat_unlocks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_id BIGINT UNSIGNED NULL,
  sender_user_id BIGINT UNSIGNED NOT NULL,
  receiver_user_id BIGINT UNSIGNED NOT NULL,
  gift_transaction_id BIGINT UNSIGNED NULL,
  unlock_type ENUM('days','permanent') NOT NULL,
  unlock_start_at DATETIME NOT NULL,
  unlock_end_at DATETIME NULL,
  status ENUM('active','expired','revoked') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_chat_unlocks_room FOREIGN KEY (room_id) REFERENCES chat_rooms(id) ON DELETE SET NULL,
  CONSTRAINT fk_chat_unlocks_sender FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_chat_unlocks_receiver FOREIGN KEY (receiver_user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_chat_unlock_pair (sender_user_id, receiver_user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subscription_plans (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  name_th VARCHAR(120) NOT NULL,
  tier ENUM('free','premium','vip') NOT NULL,
  billing_cycle ENUM('monthly','yearly') NOT NULL DEFAULT 'monthly',
  price_thb DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  coin_bonus INT NOT NULL DEFAULT 0,
  feature_json JSON NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subscriptions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  plan_id BIGINT UNSIGNED NOT NULL,
  started_at DATETIME NOT NULL,
  expires_at DATETIME NULL,
  status ENUM('pending','active','expired','cancelled','failed') NOT NULL DEFAULT 'pending',
  auto_renew TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_subscriptions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_subscriptions_plan FOREIGN KEY (plan_id) REFERENCES subscription_plans(id),
  INDEX idx_subscriptions_user_status (user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  subscription_id BIGINT UNSIGNED NULL,
  payment_target ENUM('subscription','coin_topup','gift') NOT NULL,
  payment_method ENUM('credit_card','promptpay_qr','bank_transfer') NOT NULL,
  amount_thb DECIMAL(10,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'THB',
  provider_reference VARCHAR(190) NULL,
  slip_url VARCHAR(255) NULL,
  status ENUM('pending','paid','failed','cancelled','refunded') NOT NULL DEFAULT 'pending',
  paid_at DATETIME NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_payments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_payments_subscription FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE SET NULL,
  INDEX idx_payments_user_status (user_id, status),
  INDEX idx_payments_reference (provider_reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reports (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reporter_user_id BIGINT UNSIGNED NOT NULL,
  reported_user_id BIGINT UNSIGNED NOT NULL,
  room_id BIGINT UNSIGNED NULL,
  message_id BIGINT UNSIGNED NULL,
  reason_code VARCHAR(50) NOT NULL,
  reason_detail TEXT NULL,
  status ENUM('open','reviewing','resolved','dismissed') NOT NULL DEFAULT 'open',
  reviewed_by BIGINT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_reports_reporter FOREIGN KEY (reporter_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_reports_reported FOREIGN KEY (reported_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_reports_room FOREIGN KEY (room_id) REFERENCES chat_rooms(id) ON DELETE SET NULL,
  CONSTRAINT fk_reports_message FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE SET NULL,
  INDEX idx_reports_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_blocks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  blocker_user_id BIGINT UNSIGNED NOT NULL,
  blocked_user_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_blocks_blocker FOREIGN KEY (blocker_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_blocks_blocked FOREIGN KEY (blocked_user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_user_block (blocker_user_id, blocked_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE moderation_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  message_id BIGINT UNSIGNED NULL,
  action_type ENUM('warn','hide_message','suspend','ban','review_photo','review_profile') NOT NULL,
  risk_score DECIMAL(5,2) NULL,
  notes TEXT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_moderation_logs_user (user_id, created_at),
  INDEX idx_moderation_logs_message (message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  notification_type ENUM('match','message','gift','subscription','system','report_update') NOT NULL,
  title VARCHAR(190) NOT NULL,
  body VARCHAR(500) NULL,
  reference_type VARCHAR(50) NULL,
  reference_id BIGINT UNSIGNED NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  read_at DATETIME NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_notifications_user_read (user_id, is_read, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE admin_audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_user_id BIGINT UNSIGNED NOT NULL,
  target_table VARCHAR(100) NOT NULL,
  target_id BIGINT UNSIGNED NULL,
  action VARCHAR(100) NOT NULL,
  payload_json JSON NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_admin_audit_logs_admin FOREIGN KEY (admin_user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_admin_audit_logs_admin (admin_user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (code, name_th) VALUES
  ('member', 'สมาชิก'),
  ('premium', 'สมาชิกพรีเมียม'),
  ('vip', 'สมาชิก VIP'),
  ('moderator', 'ผู้ดูแลเนื้อหา'),
  ('admin', 'ผู้ดูแลระบบ')
ON DUPLICATE KEY UPDATE name_th = VALUES(name_th);

INSERT INTO gift_catalog (code, name_th, emoji, coin_cost, unlock_type, unlock_days, is_active) VALUES
  ('rose', 'ดอกกุหลาบ', '🌹', 10, 'days', 1, 1),
  ('bouquet', 'ช่อดอกไม้', '💐', 50, 'days', 7, 1),
  ('chocolate_box', 'กล่องช็อกโกแลต', '🍫', 80, 'days', 10, 1),
  ('teddy_bear', 'ตุ๊กตาหมี', '🧸', 120, 'days', 14, 1),
  ('diamond_ring', 'แหวนเพชร', '💎', 200, 'days', 30, 1),
  ('plane_ticket', 'ตั๋วเครื่องบิน', '✈️', 500, 'permanent', NULL, 1)
ON DUPLICATE KEY UPDATE
  name_th = VALUES(name_th),
  emoji = VALUES(emoji),
  coin_cost = VALUES(coin_cost),
  unlock_type = VALUES(unlock_type),
  unlock_days = VALUES(unlock_days),
  is_active = VALUES(is_active);

INSERT INTO subscription_plans (code, name_th, tier, billing_cycle, price_thb, coin_bonus, feature_json, is_active) VALUES
  ('free_monthly', 'Free', 'free', 'monthly', 0.00, 50, JSON_OBJECT('likes_per_day', 10, 'private_chat', false), 1),
  ('premium_monthly', 'Premium', 'premium', 'monthly', 499.00, 0, JSON_OBJECT('likes_per_day', -1, 'private_chat', true, 'see_who_likes_you', true), 1),
  ('vip_monthly', 'VIP', 'vip', 'monthly', 1999.00, 0, JSON_OBJECT('likes_per_day', -1, 'private_chat', true, 'priority_protection', true, 'boost_profile', true), 1)
ON DUPLICATE KEY UPDATE
  name_th = VALUES(name_th),
  tier = VALUES(tier),
  billing_cycle = VALUES(billing_cycle),
  price_thb = VALUES(price_thb),
  coin_bonus = VALUES(coin_bonus),
  feature_json = VALUES(feature_json),
  is_active = VALUES(is_active);

INSERT INTO chat_rooms (room_type, code, name_th, description, visibility, is_active) VALUES
  ('public', 'general', 'ห้องสนทนาทั่วไป', 'ห้องรวมสำหรับสมาชิกทั้งหมด', 'public', 1),
  ('public', 'thai', 'คนไทย', 'ห้องรวมสำหรับสมาชิกภาษาไทย', 'public', 1),
  ('public', 'international', 'International', 'ห้องรวมสำหรับสมาชิกนานาชาติ', 'public', 1)
ON DUPLICATE KEY UPDATE
  name_th = VALUES(name_th),
  description = VALUES(description),
  visibility = VALUES(visibility),
  is_active = VALUES(is_active);
