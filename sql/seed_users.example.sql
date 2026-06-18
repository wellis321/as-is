-- Admin user seed template
-- Copy this file to sql/seed_users.sql (gitignored) and add your real users.
-- Generate bcrypt hashes with:  php -r "echo password_hash('yourpassword', PASSWORD_BCRYPT);"
--
-- Run after sql/migrate_auth.sql

INSERT INTO users (username, password_hash, auth_provider, app_role, is_active) VALUES
  ('admin',
   '$2y$12$REPLACE_WITH_HASH_OF_YOUR_ADMIN_PASSWORD',
   'local', 'admin', 1),
  ('editor',
   '$2y$12$REPLACE_WITH_HASH_OF_EDITOR_PASSWORD',
   'local', 'editor', 1)
ON DUPLICATE KEY UPDATE is_active = VALUES(is_active), app_role = VALUES(app_role);
