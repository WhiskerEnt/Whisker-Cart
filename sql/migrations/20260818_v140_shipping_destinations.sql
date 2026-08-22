-- Which countries the store posts to.
-- Domestic by default: a new store ships within its own country until the
-- owner says otherwise.

INSERT INTO wk_settings (setting_group, setting_key, setting_value) VALUES
('shipping', 'ship_mode', 'domestic'),
('shipping', 'ship_countries', '')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
