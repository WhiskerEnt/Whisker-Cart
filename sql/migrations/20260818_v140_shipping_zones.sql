-- Shipping zones: different rates for different parts of the world.
--
-- A zone carries the same knobs as the store-wide shipping settings, so the
-- rate maths is identical either way — only where the numbers come from
-- changes. No zone matching the destination means the store-wide settings
-- apply, which is what every existing shop already has. Nothing is migrated
-- and nothing changes until a zone is created.

CREATE TABLE IF NOT EXISTS wk_shipping_zones (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    -- Comma-separated ISO 3166-1 alpha-2 codes.
    countries TEXT NOT NULL,
    method ENUM('flat','free','free_above','per_item','weight') NOT NULL DEFAULT 'flat',
    flat_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    flat_rate_below DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    free_threshold DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    per_item DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    per_item_cap DECIMAL(10,2) DEFAULT NULL,
    weight_base DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    weight_per_kg DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active, sort_order)
) ENGINE=InnoDB;
