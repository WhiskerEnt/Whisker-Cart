-- 20260817_v132_pickup_delivery.sql
--
-- Locker / collection-point delivery (EU-style pickup points).
--
-- Adds:
--   * wk_pickup_locations — admin-managed list of pickup points/lockers
--   * wk_orders.delivery_method + wk_orders.pickup_location_id
--   * shipping.pickup_enabled / shipping.pickup_fee settings
--
-- Orders snapshot the chosen pickup address into shipping_address JSON, so
-- there is deliberately no FK from wk_orders to wk_pickup_locations —
-- deleting a location must never break order history.

CREATE TABLE IF NOT EXISTS wk_pickup_locations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    carrier VARCHAR(100) DEFAULT '',
    address_line1 VARCHAR(255) NOT NULL,
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100) DEFAULT '',
    zip VARCHAR(20) DEFAULT '',
    country CHAR(2) NOT NULL DEFAULT 'IN',
    opening_hours VARCHAR(255) DEFAULT '',
    fee DECIMAL(12,2) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active (is_active)
) ENGINE=InnoDB;

ALTER TABLE wk_orders ADD COLUMN delivery_method VARCHAR(20) DEFAULT 'shipping';

ALTER TABLE wk_orders ADD COLUMN pickup_location_id INT UNSIGNED DEFAULT NULL;

INSERT INTO wk_settings (setting_group, setting_key, setting_value)
VALUES ('shipping', 'pickup_enabled', '0')
ON DUPLICATE KEY UPDATE setting_value = setting_value;

INSERT INTO wk_settings (setting_group, setting_key, setting_value)
VALUES ('shipping', 'pickup_fee', '')
ON DUPLICATE KEY UPDATE setting_value = setting_value;

-- Multi-currency is opt-in; default off so existing stores keep single-currency.
INSERT INTO wk_settings (setting_group, setting_key, setting_value)
VALUES ('general', 'multi_currency', '0')
ON DUPLICATE KEY UPDATE setting_value = setting_value;

-- Heal stores that picked a non-INR currency before v1.3.2: the installer
-- used to save the currency code but never the symbol, leaving the seeded ₹.
-- Only touches rows where the symbol is still the stale default.
UPDATE wk_settings s
JOIN wk_settings c
  ON c.setting_group = 'general' AND c.setting_key = 'currency'
SET s.setting_value = CASE c.setting_value
    WHEN 'USD' THEN '$'    WHEN 'EUR' THEN '€'   WHEN 'GBP' THEN '£'
    WHEN 'AUD' THEN 'A$'   WHEN 'CAD' THEN 'C$'  WHEN 'JPY' THEN '¥'
    WHEN 'SGD' THEN 'S$'   WHEN 'AED' THEN 'د.إ' WHEN 'BRL' THEN 'R$'
    WHEN 'CNY' THEN '¥'    WHEN 'MYR' THEN 'RM'  WHEN 'THB' THEN '฿'
    WHEN 'IDR' THEN 'Rp'   WHEN 'PHP' THEN '₱'   WHEN 'KRW' THEN '₩'
    WHEN 'ZAR' THEN 'R'    WHEN 'SEK' THEN 'kr'  WHEN 'NZD' THEN 'NZ$'
    WHEN 'CHF' THEN 'CHF'  WHEN 'PLN' THEN 'zł'  WHEN 'NOK' THEN 'kr'
    WHEN 'DKK' THEN 'kr'   WHEN 'CZK' THEN 'Kč'  WHEN 'HUF' THEN 'Ft'
    WHEN 'RON' THEN 'lei'  WHEN 'MXN' THEN 'MX$' WHEN 'HKD' THEN 'HK$'
    ELSE s.setting_value END
WHERE s.setting_group = 'general'
  AND s.setting_key = 'currency_symbol'
  AND s.setting_value = '₹'
  AND c.setting_value <> 'INR';
