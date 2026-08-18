-- Customer questions about a product.
--
-- A question is only ever public once it has been answered and published, so
-- a product page never shows an unanswered question sitting there.

CREATE TABLE IF NOT EXISTS wk_questions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED DEFAULT NULL,
    author_name VARCHAR(80) NOT NULL,
    author_email VARCHAR(190) NOT NULL,
    question TEXT NOT NULL,
    answer TEXT DEFAULT NULL,
    -- published requires an answer; see QuestionService::publish()
    status ENUM('pending','published','rejected') NOT NULL DEFAULT 'pending',
    answered_by INT UNSIGNED DEFAULT NULL,
    answered_at TIMESTAMP NULL DEFAULT NULL,
    notified_at TIMESTAMP NULL DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_product_status (product_id, status),
    INDEX idx_status (status),
    FOREIGN KEY (product_id) REFERENCES wk_products(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES wk_customers(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO wk_settings (setting_group, setting_key, setting_value) VALUES
('questions', 'questions_enabled', '1'),
('questions', 'notify_on_answer', '1')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

INSERT INTO wk_email_templates (slug, name, subject, body, is_active) VALUES
('question-answered', 'Question Answered', 'Your question about {{product_name}} has been answered',
'<div style="text-align:center;margin-bottom:28px"><div style="font-size:52px;margin-bottom:8px">&#128172;</div><h1 style="font-size:27px;font-weight:900;margin:0 0 6px;color:#1e1b2e">We have answered your question</h1><p style="color:#6b7280;margin:0;font-size:15px">About {{product_name}}</p></div><div style="background:#faf8f6;border-radius:10px;padding:20px;margin-bottom:18px"><div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:#6b7280;margin-bottom:6px">You asked</div><div style="font-size:14px;line-height:1.7">{{question}}</div></div><div style="background:#ede9fe;border-left:3px solid #8b5cf6;border-radius:0 10px 10px 0;padding:20px;margin-bottom:24px"><div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:#8b5cf6;margin-bottom:6px">Our answer</div><div style="font-size:14px;line-height:1.7">{{answer}}</div></div><div style="text-align:center;margin-top:28px"><a href="{{product_url}}" style="display:inline-block;background:linear-gradient(135deg,#8b5cf6,#ec4899);color:#fff;padding:15px 34px;border-radius:10px;text-decoration:none;font-weight:800;font-size:15px">View the product</a></div>', 1)
ON DUPLICATE KEY UPDATE slug = slug;
