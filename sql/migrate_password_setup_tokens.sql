-- Password setup tokens for first-time login activation flow.
-- A token is created when a user with no password_hash attempts to sign in.
-- They receive a 6-digit code by email; on successful entry they set their password.
-- Run in phpMyAdmin on the AS-IS database before deploying the activation flow.

CREATE TABLE IF NOT EXISTS password_setup_tokens (
    id         INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED    NOT NULL,
    token      CHAR(6)         NOT NULL,
    expires_at DATETIME        NOT NULL,
    used_at    DATETIME        NULL,
    created_at TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pst_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
