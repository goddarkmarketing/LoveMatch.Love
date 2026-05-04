-- Run once on existing databases (phpMyAdmin / mysql client).
ALTER TABLE payments
  MODIFY payment_target ENUM('subscription','coin_topup','gift','registration') NOT NULL;
