-- Refund automatically when an order is cancelled.
-- Off by default: it moves money without anyone looking, so a shop opts in.

INSERT INTO wk_settings (setting_group, setting_key, setting_value) VALUES
('checkout', 'auto_refund_on_cancel', '0')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
