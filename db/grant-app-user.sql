-- Run as MySQL admin (e.g. sudo mysql) if PHP cannot connect as root (error 1698 on Linux).
-- Replace the password before running.

CREATE DATABASE IF NOT EXISTS lovematch_love CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'lovematch'@'localhost' IDENTIFIED BY 'CHANGE_ME_STRONG_PASSWORD';
GRANT ALL PRIVILEGES ON lovematch_love.* TO 'lovematch'@'localhost';
FLUSH PRIVILEGES;

-- Then import schema/seed, e.g.:
--   mysql -u lovematch -p lovematch_love < db/schema.sql
--   mysql -u lovematch -p lovematch_love < db/seed-demo-users.sql
