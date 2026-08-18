-- Product reviews and ratings.
--
-- Reviews wait for approval by default. A shop that turns that off is making
-- a deliberate choice; the safe default is that nothing a stranger writes
-- appears on a product page unseen.

CREATE TABLE IF NOT EXISTS wk_reviews (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED DEFAULT NULL,
    -- The order this review was earned by, when the store requires a purchase.
    order_id INT UNSIGNED DEFAULT NULL,
    author_name VARCHAR(80) NOT NULL,
    author_email VARCHAR(190) NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    title VARCHAR(140) DEFAULT NULL,
    body TEXT DEFAULT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    is_verified_purchase TINYINT(1) NOT NULL DEFAULT 0,
    admin_reply TEXT DEFAULT NULL,
    admin_replied_at TIMESTAMP NULL DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_product_status (product_id, status),
    INDEX idx_status (status),
    INDEX idx_email (author_email),
    FOREIGN KEY (product_id) REFERENCES wk_products(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES wk_customers(id) ON DELETE SET NULL,
    FOREIGN KEY (order_id) REFERENCES wk_orders(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO wk_settings (setting_group, setting_key, setting_value) VALUES
('reviews', 'reviews_enabled', '1'),
-- anyone | purchased | received
('reviews', 'review_policy', 'purchased'),
('reviews', 'auto_approve', '0'),
('reviews', 'show_on_cards', '1')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
