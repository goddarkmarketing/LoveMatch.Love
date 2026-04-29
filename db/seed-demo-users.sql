SET NAMES utf8mb4;

SET @member_role_id := (SELECT id FROM roles WHERE code = 'member' LIMIT 1);
SET @admin_role_id := (SELECT id FROM roles WHERE code = 'admin' LIMIT 1);
SET @general_room_id := (SELECT id FROM chat_rooms WHERE code = 'general' LIMIT 1);
SET @thai_room_id := (SELECT id FROM chat_rooms WHERE code = 'thai' LIMIT 1);
SET @international_room_id := (SELECT id FROM chat_rooms WHERE code = 'international' LIMIT 1);

INSERT INTO users (
  role_id, email, password_hash, first_name, last_name, display_name,
  birth_date, gender, interested_in, country_code, province, city,
  bio, avatar_url, email_verified_at, status, is_profile_completed, last_seen_at,
  created_at, updated_at
) VALUES
  (
    @member_role_id,
    'test1@example.com',
    '$2y$10$K6Jfs.V1zd7ThdIsr/uvxuF.GJxv5eafiQACLpuTMkLawF00.u2Ee',
    'Mike',
    'Demo',
    'Mike Demo',
    '1994-06-15',
    'male',
    'female',
    'TH',
    'Chonburi',
    'Pattaya',
    'Chef at Seafood',
    'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=900&q=80',
    NOW(),
    'active',
    1,
    NOW(),
    NOW(),
    NOW()
  ),
  (
    @admin_role_id,
    'admin@lovematch.love',
    '$2y$10$K6Jfs.V1zd7ThdIsr/uvxuF.GJxv5eafiQACLpuTMkLawF00.u2Ee',
    'Admin',
    'LoveMatch',
    'Admin LoveMatch',
    '1990-01-01',
    'prefer_not_to_say',
    'everyone',
    'TH',
    'Bangkok',
    'Bangkok',
    'System administrator',
    'https://images.unsplash.com/photo-1560250097-0b93528c311a?auto=format&fit=crop&w=900&q=80',
    NOW(),
    'active',
    1,
    NOW(),
    NOW(),
    NOW()
  ),
  (
    @member_role_id,
    'test2@example.com',
    '$2y$10$7EZLZk62LIFbUkxCZ/mWE.aXt5wB.LfarVrwQJ8FFIrT2orwECpWO',
    'Emma',
    'Sample',
    'Emma Sample',
    '1997-02-10',
    'female',
    'male',
    'TH',
    'Bangkok',
    'Bangkok',
    'Travel lover and coffee date fan',
    'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=900&q=80',
    NOW(),
    'active',
    1,
    NOW(),
    NOW(),
    NOW()
  )
ON DUPLICATE KEY UPDATE
  role_id = VALUES(role_id),
  first_name = VALUES(first_name),
  last_name = VALUES(last_name),
  display_name = VALUES(display_name),
  birth_date = VALUES(birth_date),
  gender = VALUES(gender),
  interested_in = VALUES(interested_in),
  country_code = VALUES(country_code),
  province = VALUES(province),
  city = VALUES(city),
  bio = VALUES(bio),
  avatar_url = VALUES(avatar_url),
  email_verified_at = VALUES(email_verified_at),
  status = VALUES(status),
  is_profile_completed = VALUES(is_profile_completed),
  last_seen_at = VALUES(last_seen_at),
  updated_at = NOW();

SET @user1_id := (SELECT id FROM users WHERE email = 'test1@example.com' LIMIT 1);
SET @admin_user_id := (SELECT id FROM users WHERE email = 'admin@lovematch.love' LIMIT 1);
SET @user2_id := (SELECT id FROM users WHERE email = 'test2@example.com' LIMIT 1);

INSERT INTO user_photos (user_id, file_url, sort_order, is_primary, moderation_status, created_at, updated_at)
VALUES
  (@user1_id, 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=900&q=80', 1, 1, 'approved', NOW(), NOW()),
  (@admin_user_id, 'https://images.unsplash.com/photo-1560250097-0b93528c311a?auto=format&fit=crop&w=900&q=80', 1, 1, 'approved', NOW(), NOW()),
  (@user2_id, 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=900&q=80', 1, 1, 'approved', NOW(), NOW())
ON DUPLICATE KEY UPDATE
  file_url = VALUES(file_url),
  is_primary = VALUES(is_primary),
  moderation_status = VALUES(moderation_status),
  updated_at = NOW();

INSERT INTO wallets (user_id, coin_balance, created_at, updated_at)
VALUES
  (@user1_id, 1500, NOW(), NOW()),
  (@admin_user_id, 9999, NOW(), NOW()),
  (@user2_id, 1500, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  coin_balance = VALUES(coin_balance),
  updated_at = NOW();

SET @wallet1_id := (SELECT id FROM wallets WHERE user_id = @user1_id LIMIT 1);
SET @admin_wallet_id := (SELECT id FROM wallets WHERE user_id = @admin_user_id LIMIT 1);
SET @wallet2_id := (SELECT id FROM wallets WHERE user_id = @user2_id LIMIT 1);

INSERT INTO wallet_transactions (
  wallet_id, user_id, transaction_type, source_type, source_id, amount,
  balance_before, balance_after, note, created_at
) VALUES
  (@wallet1_id, @user1_id, 'credit', 'admin_adjustment', NULL, 1500, 0, 1500, 'Demo balance topup', NOW()),
  (@admin_wallet_id, @admin_user_id, 'credit', 'admin_adjustment', NULL, 9999, 0, 9999, 'Admin demo balance', NOW()),
  (@wallet2_id, @user2_id, 'credit', 'admin_adjustment', NULL, 1500, 0, 1500, 'Demo balance topup', NOW());

INSERT IGNORE INTO chat_room_members (room_id, user_id, member_role, join_status, joined_at, created_at, updated_at)
VALUES
  (@general_room_id, @user1_id, 'member', 'joined', NOW(), NOW(), NOW()),
  (@general_room_id, @user2_id, 'member', 'joined', NOW(), NOW(), NOW()),
  (@thai_room_id, @user1_id, 'member', 'joined', NOW(), NOW(), NOW()),
  (@thai_room_id, @user2_id, 'member', 'joined', NOW(), NOW(), NOW()),
  (@international_room_id, @user1_id, 'member', 'joined', NOW(), NOW(), NOW()),
  (@international_room_id, @user2_id, 'member', 'joined', NOW(), NOW(), NOW());

INSERT INTO swipes (actor_user_id, target_user_id, action, source, created_at)
VALUES
  (@user1_id, @user2_id, 'like', 'discover', NOW()),
  (@user2_id, @user1_id, 'like', 'discover', NOW())
ON DUPLICATE KEY UPDATE
  created_at = NOW();

INSERT INTO matches (user_one_id, user_two_id, matched_at, status, created_at, updated_at)
VALUES
  (LEAST(@user1_id, @user2_id), GREATEST(@user1_id, @user2_id), NOW(), 'active', NOW(), NOW())
ON DUPLICATE KEY UPDATE
  status = 'active',
  updated_at = NOW();

INSERT INTO messages (
  room_id, sender_user_id, message_type, body, translated_body,
  gift_transaction_id, moderation_status, sent_at, created_at, updated_at
) VALUES
  (@general_room_id, @user2_id, 'text', 'สวัสดีค่ะ! มีใครอยู่ไหม', NULL, NULL, 'clean', NOW(), NOW(), NOW()),
  (@general_room_id, @user1_id, 'text', 'สวัสดีครับ! ยินดีที่ได้รู้จัก', NULL, NULL, 'clean', NOW(), NOW(), NOW());

INSERT INTO reports (
  reporter_user_id, reported_user_id, room_id, message_id, reason_code, reason_detail, status, created_at, updated_at
) VALUES
  (@user2_id, @user1_id, @general_room_id, NULL, 'spam', 'ส่งข้อความซ้ำหลายครั้ง', 'open', NOW(), NOW())
ON DUPLICATE KEY UPDATE
  reason_detail = VALUES(reason_detail),
  status = VALUES(status),
  updated_at = NOW();

SET @premium_plan_id := (SELECT id FROM subscription_plans WHERE tier = 'premium' LIMIT 1);

INSERT INTO subscriptions (
  user_id, plan_id, started_at, expires_at, status, auto_renew, created_at, updated_at
) VALUES
  (@user1_id, @premium_plan_id, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), 'active', 0, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  expires_at = VALUES(expires_at),
  status = VALUES(status),
  updated_at = NOW();

SET @user1_subscription_id := (SELECT id FROM subscriptions WHERE user_id = @user1_id ORDER BY id DESC LIMIT 1);

INSERT INTO payments (
  user_id, subscription_id, payment_target, payment_method, amount_thb, currency,
  provider_reference, status, paid_at, created_at, updated_at
) VALUES
  (@user1_id, @user1_subscription_id, 'subscription', 'credit_card', 499.00, 'THB', 'SEED-PAYMENT-001', 'paid', NOW(), NOW(), NOW())
ON DUPLICATE KEY UPDATE
  status = VALUES(status),
  paid_at = VALUES(paid_at),
  updated_at = NOW();
