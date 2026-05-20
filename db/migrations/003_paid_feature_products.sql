SET NAMES utf8mb4;

ALTER TABLE wallet_transactions
  MODIFY source_type ENUM(
    'gift_purchase',
    'gift_send',
    'subscription',
    'admin_adjustment',
    'signup_bonus',
    'refund',
    'contact_unlock',
    'crush_send',
    'chat_unlock',
    'profile_boost',
    'coin_topup'
  ) NOT NULL;

CREATE TABLE IF NOT EXISTS paid_feature_products (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(80) NOT NULL UNIQUE,
  action_type ENUM('contact_unlock','crush','chat_unlock','profile_boost') NOT NULL,
  channel_type ENUM('line','facebook','phone') NULL,
  name_th VARCHAR(150) NOT NULL,
  description VARCHAR(500) NOT NULL,
  coin_cost INT UNSIGNED NOT NULL,
  price_thb_estimate DECIMAL(10,2) NULL,
  duration_minutes INT UNSIGNED NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_paid_feature_products_action (action_type, is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_contact_channels (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  channel_type ENUM('line','facebook','phone') NOT NULL,
  contact_value VARCHAR(255) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_user_contact_channels_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_user_contact_channel (user_id, channel_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_unlocks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  buyer_user_id BIGINT UNSIGNED NOT NULL,
  target_user_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  wallet_transaction_id BIGINT UNSIGNED NOT NULL,
  channel_type ENUM('line','facebook','phone') NOT NULL,
  contact_value VARCHAR(255) NOT NULL,
  unlocked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_contact_unlocks_buyer FOREIGN KEY (buyer_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_contact_unlocks_target FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_contact_unlocks_product FOREIGN KEY (product_id) REFERENCES paid_feature_products(id),
  CONSTRAINT fk_contact_unlocks_wallet_tx FOREIGN KEY (wallet_transaction_id) REFERENCES wallet_transactions(id),
  UNIQUE KEY uniq_contact_unlock (buyer_user_id, target_user_id, channel_type),
  INDEX idx_contact_unlocks_buyer (buyer_user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crush_messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sender_user_id BIGINT UNSIGNED NOT NULL,
  target_user_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  wallet_transaction_id BIGINT UNSIGNED NOT NULL,
  message_text VARCHAR(500) NOT NULL,
  status ENUM('sent','read','dismissed') NOT NULL DEFAULT 'sent',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_crush_messages_sender FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_crush_messages_target FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_crush_messages_product FOREIGN KEY (product_id) REFERENCES paid_feature_products(id),
  CONSTRAINT fk_crush_messages_wallet_tx FOREIGN KEY (wallet_transaction_id) REFERENCES wallet_transactions(id),
  INDEX idx_crush_messages_target (target_user_id, created_at),
  INDEX idx_crush_messages_sender (sender_user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS paid_chat_unlocks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  buyer_user_id BIGINT UNSIGNED NOT NULL,
  target_user_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  wallet_transaction_id BIGINT UNSIGNED NOT NULL,
  unlocked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NULL,
  status ENUM('active','expired','revoked') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_paid_chat_unlocks_buyer FOREIGN KEY (buyer_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_paid_chat_unlocks_target FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_paid_chat_unlocks_product FOREIGN KEY (product_id) REFERENCES paid_feature_products(id),
  CONSTRAINT fk_paid_chat_unlocks_wallet_tx FOREIGN KEY (wallet_transaction_id) REFERENCES wallet_transactions(id),
  INDEX idx_paid_chat_unlocks_pair (buyer_user_id, target_user_id, status, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS profile_boosts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  wallet_transaction_id BIGINT UNSIGNED NOT NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL,
  status ENUM('active','expired','cancelled') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_profile_boosts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_profile_boosts_product FOREIGN KEY (product_id) REFERENCES paid_feature_products(id),
  CONSTRAINT fk_profile_boosts_wallet_tx FOREIGN KEY (wallet_transaction_id) REFERENCES wallet_transactions(id),
  INDEX idx_profile_boosts_user_active (user_id, status, ends_at),
  INDEX idx_profile_boosts_active (status, ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO paid_feature_products (
  code, action_type, channel_type, name_th, description, coin_cost,
  price_thb_estimate, duration_minutes, is_active, sort_order
) VALUES
  ('unlock_line', 'contact_unlock', 'line', 'ปลดล็อก Line', 'ดู Line ID ของสมาชิกที่สนใจ', 300, 99.00, NULL, 1, 10),
  ('unlock_facebook', 'contact_unlock', 'facebook', 'ปลดล็อก Facebook', 'ดู Facebook/โปรไฟล์โซเชียลของสมาชิก', 300, 99.00, NULL, 1, 20),
  ('unlock_phone', 'contact_unlock', 'phone', 'ปลดล็อกเบอร์โทร', 'ดูเบอร์โทรสำหรับติดต่อโดยตรง', 500, 149.00, NULL, 1, 30),
  ('crush_message', 'crush', NULL, 'ส่ง Crush พร้อมข้อความ', 'ส่งความสนใจพิเศษพร้อมข้อความก่อนแมทช์', 100, 29.00, NULL, 1, 40),
  ('unlock_chat_no_match', 'chat_unlock', NULL, 'ปลดล็อกแชทก่อนแมทช์', 'เปิดแชทส่วนตัวกับสมาชิกที่ยังไม่ได้แมทช์', 250, 79.00, 1440, 1, 50),
  ('profile_boost_1h', 'profile_boost', NULL, 'บูสต์โปรไฟล์ 1 ชั่วโมง', 'ดันโปรไฟล์ขึ้นในหน้า Discover', 200, 59.00, 60, 1, 60)
ON DUPLICATE KEY UPDATE
  action_type = VALUES(action_type),
  channel_type = VALUES(channel_type),
  name_th = VALUES(name_th),
  description = VALUES(description),
  coin_cost = VALUES(coin_cost),
  price_thb_estimate = VALUES(price_thb_estimate),
  duration_minutes = VALUES(duration_minutes),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order),
  updated_at = NOW();

INSERT INTO user_contact_channels (user_id, channel_type, contact_value, is_active)
SELECT id, 'line', CONCAT('@lovematch-', id), 1
FROM users
WHERE email IN ('test1@example.com', 'test2@example.com')
ON DUPLICATE KEY UPDATE contact_value = VALUES(contact_value), is_active = 1, updated_at = NOW();

INSERT INTO user_contact_channels (user_id, channel_type, contact_value, is_active)
SELECT id, 'facebook', CONCAT('https://facebook.com/lovematch.demo.', id), 1
FROM users
WHERE email IN ('test1@example.com', 'test2@example.com')
ON DUPLICATE KEY UPDATE contact_value = VALUES(contact_value), is_active = 1, updated_at = NOW();

INSERT INTO user_contact_channels (user_id, channel_type, contact_value, is_active)
SELECT id, 'phone', CONCAT('08', LPAD(id, 8, '0')), 1
FROM users
WHERE email IN ('test1@example.com', 'test2@example.com')
ON DUPLICATE KEY UPDATE contact_value = VALUES(contact_value), is_active = 1, updated_at = NOW();
