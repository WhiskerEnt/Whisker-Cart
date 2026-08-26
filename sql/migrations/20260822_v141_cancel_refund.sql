-- Refund automatically when an order is cancelled.
-- Off by default: it moves money without anyone looking, so a shop opts in.

-- cancel_window_minutes: 0 means no time limit, only the status rules apply.

INSERT INTO wk_settings (setting_group, setting_key, setting_value) VALUES
('checkout', 'auto_refund_on_cancel', '0'),
('checkout', 'cancel_window_minutes', '0'),
('checkout', 'show_cancel_deadline', '1')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
