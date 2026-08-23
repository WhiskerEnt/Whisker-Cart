-- Floating contact and social bar.
-- Off until the shopkeeper turns it on and fills in at least one channel.

INSERT INTO wk_settings (setting_group, setting_key, setting_value) VALUES
('social', 'social_enabled', '0'),
('social', 'social_position', 'left'),
('social', 'social_display', 'always'),
('social', 'social_whatsapp', ''),
('social', 'social_whatsapp_text', ''),
('social', 'social_phone', ''),
('social', 'social_email', ''),
('social', 'social_facebook', ''),
('social', 'social_instagram', ''),
('social', 'social_telegram', ''),
('social', 'social_x', ''),
('social', 'social_youtube', ''),
('social', 'social_tiktok', ''),
('social', 'social_linkedin', '')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
