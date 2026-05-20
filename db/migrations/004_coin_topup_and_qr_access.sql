SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS coin_topup_packages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(80) NOT NULL UNIQUE,
  name_th VARCHAR(150) NOT NULL,
  coin_amount INT UNSIGNED NOT NULL,
  price_thb DECIMAL(10,2) NOT NULL,
  bonus_coin INT UNSIGNED NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_coin_topup_packages_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS coin_topup_requests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  package_id BIGINT UNSIGNED NOT NULL,
  payment_id BIGINT UNSIGNED NULL,
  coin_amount INT UNSIGNED NOT NULL,
  bonus_coin INT UNSIGNED NOT NULL DEFAULT 0,
  total_coin_amount INT UNSIGNED NOT NULL,
  amount_thb DECIMAL(10,2) NOT NULL,
  payment_method ENUM('bank_transfer','promptpay_qr') NOT NULL DEFAULT 'bank_transfer',
  slip_url VARCHAR(255) NULL,
  transfer_reference VARCHAR(190) NULL,
  status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  approved_by BIGINT UNSIGNED NULL,
  approved_at DATETIME NULL,
  rejected_by BIGINT UNSIGNED NULL,
  rejected_at DATETIME NULL,
  reject_reason VARCHAR(255) NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_coin_topup_requests_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_coin_topup_requests_package FOREIGN KEY (package_id) REFERENCES coin_topup_packages(id),
  CONSTRAINT fk_coin_topup_requests_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL,
  CONSTRAINT fk_coin_topup_requests_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_coin_topup_requests_rejected_by FOREIGN KEY (rejected_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_coin_topup_requests_status (status, requested_at),
  INDEX idx_coin_topup_requests_user (user_id, requested_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO coin_topup_packages (code, name_th, coin_amount, price_thb, bonus_coin, is_active, sort_order) VALUES
  ('points_100', 'เติม 100 Point', 100, 99.00, 0, 1, 10),
  ('points_300', 'เติม 300 Point', 300, 249.00, 30, 1, 20),
  ('points_700', 'เติม 700 Point', 700, 499.00, 100, 1, 30),
  ('points_1500', 'เติม 1,500 Point', 1500, 999.00, 300, 1, 40)
ON DUPLICATE KEY UPDATE
  name_th = VALUES(name_th),
  coin_amount = VALUES(coin_amount),
  price_thb = VALUES(price_thb),
  bonus_coin = VALUES(bonus_coin),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order),
  updated_at = NOW();

CREATE TABLE IF NOT EXISTS qr_login_sessions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  token_hash CHAR(64) NOT NULL UNIQUE,
  status ENUM('pending','approved','consumed','expired','cancelled') NOT NULL DEFAULT 'pending',
  approved_user_id BIGINT UNSIGNED NULL,
  requester_ip VARCHAR(45) NULL,
  requester_user_agent VARCHAR(255) NULL,
  approved_ip VARCHAR(45) NULL,
  approved_user_agent VARCHAR(255) NULL,
  expires_at DATETIME NOT NULL,
  approved_at DATETIME NULL,
  consumed_at DATETIME NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_qr_login_sessions_user FOREIGN KEY (approved_user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_qr_login_sessions_status (status, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
