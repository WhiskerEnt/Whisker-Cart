-- Abandoned cart recovery, and capturing a way to reach the shopper.
--
-- Everything here is inert until the shopkeeper turns it on: recovery emails
-- are off, and so is the lead capture prompt.

ALTER TABLE wk_carts
    ADD COLUMN recovery_token CHAR(40) DEFAULT NULL,
    ADD COLUMN phone VARCHAR(40) DEFAULT NULL,
    ADD COLUMN abandoned_at DATETIME DEFAULT NULL,
    ADD COLUMN recovered_at DATETIME DEFAULT NULL,
    ADD COLUMN recovered_order_id INT UNSIGNED DEFAULT NULL;

-- The token is the whole authorisation for restoring a cart, so it must be
-- unique. NULLs repeat freely in a MySQL unique index, which is what we want
-- for the carts that never earn one.
ALTER TABLE wk_carts ADD UNIQUE KEY uniq_recovery_token (recovery_token);
ALTER TABLE wk_carts ADD INDEX idx_status_abandoned (status, abandoned_at);

-- People who asked not to be emailed again. Kept apart from wk_carts so the
-- wish outlives any one cart, and survives the cart being pruned.
CREATE TABLE IF NOT EXISTS wk_email_suppressions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    reason VARCHAR(60) NOT NULL DEFAULT 'unsubscribed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_suppressed_email (email)
) ENGINE=InnoDB;

-- Contact details a visitor gave us without placing an order.
CREATE TABLE IF NOT EXISTS wk_leads (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) DEFAULT NULL,
    phone VARCHAR(40) DEFAULT NULL,
    source VARCHAR(30) NOT NULL DEFAULT 'exit_intent',
    cart_id INT UNSIGNED DEFAULT NULL,
    coupon_code VARCHAR(50) DEFAULT NULL,
    converted_order_id INT UNSIGNED DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_lead_email (email),
    INDEX idx_lead_phone (phone),
    INDEX idx_lead_cart (cart_id)
) ENGINE=InnoDB;

INSERT INTO wk_settings (setting_group, setting_key, setting_value) VALUES
-- Recovery
('cart_recovery', 'recovery_enabled', '0'),
('cart_recovery', 'abandon_after_minutes', '60'),
-- Minutes after abandonment for each reminder in the sequence.
('cart_recovery', 'recovery_schedule', '60,1440,4320'),
('cart_recovery', 'recovery_coupon', ''),
-- Lead capture
('leads', 'lead_capture_enabled', '0'),
('leads', 'lead_capture_fields', 'email'),
('leads', 'lead_capture_when', 'cart'),
('leads', 'lead_capture_title', 'Before you go'),
('leads', 'lead_capture_text', 'Leave us your email and we will save your basket, so you can pick it up whenever you like.'),
('leads', 'lead_capture_coupon', '')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

INSERT INTO wk_email_templates (slug, name, subject, body, is_active) VALUES
('order-status-update', 'Order Status Update', 'Your order {{order_number}} is now {{order_status}}',
'<div style="text-align:center;margin-bottom:28px"><div style="font-size:52px;margin-bottom:8px">{{status_emoji}}</div><h1 style="font-size:27px;font-weight:900;margin:0 0 6px;color:#1e1b2e">Your order is now {{order_status}}</h1><p style="color:#6b7280;margin:0;font-size:15px">Order {{order_number}}</p></div><div style="background:#faf8f6;border-radius:10px;padding:20px;margin-bottom:24px"><table style="width:100%;font-size:14px"><tr><td style="color:#6b7280;padding:6px 0">Order</td><td style="text-align:right;font-weight:800;font-family:monospace;color:#8b5cf6">{{order_number}}</td></tr><tr><td style="color:#6b7280;padding:6px 0">Placed</td><td style="text-align:right;font-weight:700">{{order_date}}</td></tr><tr><td style="color:#6b7280;padding:6px 0">Total</td><td style="text-align:right;font-weight:900;font-family:monospace">{{order_total}}</td></tr></table></div><p style="font-size:14px;line-height:1.7;color:#6b7280;margin:0 0 22px">Hello {{customer_name}}, we wanted to let you know where your order stands. Any questions, just reply to this email.</p><div style="text-align:center"><a href="{{store_url}}" style="display:inline-block;background:linear-gradient(135deg,#8b5cf6,#ec4899);color:#fff;padding:14px 32px;border-radius:10px;text-decoration:none;font-weight:800;font-size:15px">Visit {{store_name}}</a></div>', 1),
('abandoned-cart', 'Abandoned Cart Reminder', 'You left something behind at {{store_name}}',
'<div style="text-align:center;margin-bottom:28px"><div style="font-size:52px;margin-bottom:8px">&#128717;&#65039;</div><h1 style="font-size:27px;font-weight:900;margin:0 0 6px;color:#1e1b2e">Still thinking it over?</h1><p style="color:#6b7280;margin:0;font-size:15px">Hello {{customer_name}}, your basket is still here.</p></div>{{cart_items_html}}<div style="display:flex;justify-content:space-between;padding:14px 0 0;margin-top:10px;border-top:2px solid #1e1b2e;font-size:20px"><span style="font-weight:900">Total</span><span style="font-weight:900;font-family:monospace">{{cart_total}}</span></div>{{coupon_block}}<div style="text-align:center;margin-top:28px"><a href="{{cart_url}}" style="display:inline-block;background:linear-gradient(135deg,#8b5cf6,#ec4899);color:#fff;padding:15px 38px;border-radius:10px;text-decoration:none;font-weight:800;font-size:15px">Pick up where you left off</a></div><p style="text-align:center;color:#6b7280;font-size:12px;margin-top:26px;line-height:1.7">Not interested? <a href="{{unsubscribe_url}}" style="color:#6b7280">Tell us to stop emailing you</a>.</p>', 1)
ON DUPLICATE KEY UPDATE slug = slug;

-- The dwell trigger, added alongside exit intent.
INSERT INTO wk_settings (setting_group, setting_key, setting_value) VALUES
('leads', 'lead_capture_trigger', 'both'),
('leads', 'lead_capture_delay', '90'),
('leads', 'lead_capture_title_time', ''),
('leads', 'lead_capture_text_time', '')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
