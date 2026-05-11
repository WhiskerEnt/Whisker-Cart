-- v1.2.0 — Tax engine
CREATE TABLE IF NOT EXISTS wk_tax_rates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    country VARCHAR(2) DEFAULT NULL COMMENT 'ISO country code, NULL = all countries',
    state VARCHAR(10) DEFAULT NULL COMMENT 'State/province code, NULL = all states',
    tax_class VARCHAR(50) DEFAULT 'standard',
    rate DECIMAL(6,3) NOT NULL DEFAULT 0,
    label VARCHAR(100) NOT NULL DEFAULT 'Tax',
    priority INT UNSIGNED DEFAULT 0 COMMENT 'Higher priority overrides lower',
    is_compound TINYINT(1) DEFAULT 0 COMMENT 'Apply on top of other taxes',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_country_state (country, state),
    INDEX idx_class (tax_class)
) ENGINE=InnoDB;

-- Add tax breakdown column to orders
ALTER TABLE wk_orders ADD COLUMN IF NOT EXISTS tax_details JSON DEFAULT NULL AFTER tax_amount;
