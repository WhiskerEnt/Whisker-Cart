-- Cookie consent banner settings.
-- Off by default: the store owner decides whether their store needs it.

INSERT INTO wk_settings (setting_group, setting_key, setting_value) VALUES
('privacy', 'cookie_consent', '0'),
('privacy', 'cookie_title', 'We use cookies'),
('privacy', 'cookie_text', 'We use cookies to keep your cart working and to understand how the store is used. You can accept or reject the optional ones.'),
('privacy', 'cookie_policy_url', ''),
('privacy', 'cookie_analytics', '1'),
('privacy', 'cookie_marketing', '0'),
('privacy', 'cookie_version', '1')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
