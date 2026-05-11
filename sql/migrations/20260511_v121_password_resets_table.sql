-- v1.2.1 — Dedicated password reset tokens table
CREATE TABLE IF NOT EXISTS wk_password_resets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_type ENUM('admin','customer') NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    token_hash VARCHAR(64) NOT NULL COMMENT 'SHA256 of the token',
    ip_address VARCHAR(45) DEFAULT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token_hash),
    INDEX idx_user (user_type, user_id)
) ENGINE=InnoDB;

-- Cleanup old tokens from wk_settings
DELETE FROM wk_settings WHERE setting_group = 'admin_reset_tokens';
DELETE FROM wk_settings WHERE setting_group = 'reset_tokens';
