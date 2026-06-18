-- Run this after schema.sql if upgrading an existing database.
-- All statements are safe to re-run (checks before altering).

-- Add action_type to steps (phone, document, email, wait, meeting, data-entry, check, escalation, general)
SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'steps' AND COLUMN_NAME = 'action_type');
SET @sql = IF(@col = 0,
    "ALTER TABLE steps ADD COLUMN action_type VARCHAR(30) NOT NULL DEFAULT 'general' AFTER step_type",
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add document metadata columns
SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'as_is_documents' AND COLUMN_NAME = 'owner');
SET @sql = IF(@col = 0,
    "ALTER TABLE as_is_documents
        ADD COLUMN owner VARCHAR(255) NULL AFTER description,
        ADD COLUMN department VARCHAR(255) NULL AFTER owner,
        ADD COLUMN captured_date DATE NULL AFTER department,
        ADD COLUMN version VARCHAR(30) NULL AFTER captured_date",
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
