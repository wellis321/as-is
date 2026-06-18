-- Auth tables for local and Microsoft Entra sign-in
-- Run: mysql -u root -p as_is < sql/migrate_auth.sql

CREATE TABLE IF NOT EXISTS login_attempts (
  id           INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  ip           VARCHAR(45)   NOT NULL,
  attempted_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ip_time (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(150)  NOT NULL,
  password_hash VARCHAR(255),
  auth_provider ENUM('local','microsoft') NOT NULL DEFAULT 'local',
  entra_oid     VARCHAR(36),
  email         VARCHAR(255),
  display_name  VARCHAR(255),
  app_role      ENUM('viewer','editor','admin') NOT NULL DEFAULT 'viewer',
  is_active     TINYINT(1)    NOT NULL DEFAULT 1,
  created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_username (username),
  UNIQUE KEY uq_entra_oid (entra_oid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
