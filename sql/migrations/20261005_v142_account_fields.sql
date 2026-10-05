-- What a shop asks for when somebody signs up, and what it accepts as a
-- sign-in name.
--
-- A username is optional and belongs to the customer row, so it can carry a
-- unique index. Everything a shop invents for itself lives in its own table
-- instead, so adding "VAT number" to the signup form is a row rather than a
-- schema change on a table other shops share.
ALTER TABLE wk_customers ADD COLUMN username VARCHAR(50) DEFAULT NULL AFTER email;
ALTER TABLE wk_customers ADD UNIQUE KEY uniq_customer_username (username);

CREATE TABLE IF NOT EXISTS wk_customer_fields (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    field_key VARCHAR(40) NOT NULL,
    label VARCHAR(80) NOT NULL,
    type ENUM('text','textarea','number','select','checkbox','date') NOT NULL DEFAULT 'text',
    -- One choice per line, for select only.
    options TEXT DEFAULT NULL,
    placeholder VARCHAR(120) DEFAULT NULL,
    help_text VARCHAR(255) DEFAULT NULL,
    is_required TINYINT(1) NOT NULL DEFAULT 0,
    show_on_register TINYINT(1) NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_customer_field_key (field_key),
    INDEX idx_active (is_active, sort_order)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wk_customer_field_values (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL,
    field_id INT UNSIGNED NOT NULL,
    value TEXT DEFAULT NULL,
    UNIQUE KEY uniq_customer_field (customer_id, field_id),
    FOREIGN KEY (customer_id) REFERENCES wk_customers(id) ON DELETE CASCADE,
    FOREIGN KEY (field_id) REFERENCES wk_customer_fields(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- A shop that upgrades keeps the form it had: name required, phone optional,
-- no username.
INSERT INTO wk_settings (setting_group, setting_key, setting_value) VALUES
('accounts', 'field_first_name', 'required'),
('accounts', 'field_last_name',  'required'),
('accounts', 'field_phone',      'optional'),
('accounts', 'field_username',   'off'),
('accounts', 'login_with_username', '0')
ON DUPLICATE KEY UPDATE setting_value = setting_value;
