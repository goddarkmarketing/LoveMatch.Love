SET NAMES utf8mb4;

ALTER TABLE users
  ADD COLUMN photo_usage_consent_at DATETIME NULL
  AFTER is_profile_completed;
