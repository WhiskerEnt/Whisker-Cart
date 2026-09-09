-- WHISKER v1.2.0 — Complete Database Schema (26 tables)

CREATE TABLE IF NOT EXISTS wk_password_resets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_type ENUM('admin','customer') NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    token_hash VARCHAR(64) NOT NULL COMMENT 'SHA256 of the token',
    ip_address VARCHAR(45) DEFAULT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token_hash),
    INDEX idx_user (user_type, user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wk_tax_rates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    country VARCHAR(2) DEFAULT NULL COMMENT 'ISO country code, NULL = all countries',
    state VARCHAR(10) DEFAULT NULL COMMENT 'State/province code, NULL = all states',
    tax_class VARCHAR(50) DEFAULT 'standard',
    rate DECIMAL(6,3) NOT NULL DEFAULT 0,
    label VARCHAR(100) NOT NULL DEFAULT 'Tax',
    priority INT UNSIGNED DEFAULT 0 COMMENT 'Higher priority overrides lower',
    is_compound TINYINT(1) DEFAULT 0 COMMENT 'Apply on top of other taxes',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_country_state (country, state),
    INDEX idx_class (tax_class)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wk_admins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('superadmin','admin','manager') DEFAULT 'admin',
    avatar VARCHAR(255) DEFAULT NULL,
    last_login DATETIME DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wk_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_group VARCHAR(50) NOT NULL,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT,
    UNIQUE KEY unique_setting (setting_group, setting_key)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wk_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id INT UNSIGNED DEFAULT NULL,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    description TEXT,
    image VARCHAR(255) DEFAULT NULL,
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    meta_title VARCHAR(255) DEFAULT NULL,
    meta_description VARCHAR(500) DEFAULT NULL,
    meta_keywords VARCHAR(500) DEFAULT NULL,
    og_image VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES wk_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wk_products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED DEFAULT NULL,
    sku VARCHAR(64) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(280) NOT NULL UNIQUE,
    description TEXT,
    faq TEXT DEFAULT NULL,
    short_description VARCHAR(500),
    price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    sale_price DECIMAL(12,2) DEFAULT NULL,
    cost_price DECIMAL(12,2) DEFAULT NULL,
    currency VARCHAR(3) DEFAULT 'INR',
    stock_quantity INT DEFAULT 0,
    low_stock_threshold INT DEFAULT 5,
    weight DECIMAL(8,3) DEFAULT NULL,
    is_digital TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    is_featured TINYINT(1) DEFAULT 0,
    tax_class VARCHAR(50) DEFAULT 'standard',
    meta_title VARCHAR(255),
    meta_description VARCHAR(500),
    meta_keywords VARCHAR(500) DEFAULT NULL,
    og_image VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES wk_categories(id) ON DELETE SET NULL,
    INDEX idx_slug (slug),
    INDEX idx_active_featured (is_active, is_featured)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wk_product_images (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    image_path VARCHAR(500) NOT NULL,
    width SMALLINT UNSIGNED NULL,
    height SMALLINT UNSIGNED NULL,
    alt_text VARCHAR(255),
    sort_order INT DEFAULT 0,
    is_primary TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES wk_products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wk_product_variants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    variant_name VARCHAR(100) NOT NULL,
    variant_value VARCHAR(100) NOT NULL,
    price_modifier DECIMAL(12,2) DEFAULT 0.00,
    stock_quantity INT DEFAULT 0,
    sku_suffix VARCHAR(20),
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (product_id) REFERENCES wk_products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wk_variant_groups (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES wk_products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wk_variant_options (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_id INT UNSIGNED NOT NULL,
    value VARCHAR(100) NOT NULL,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (group_id) REFERENCES wk_variant_groups(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wk_variant_combos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    option_ids VARCHAR(255) NOT NULL,
    label VARCHAR(255) NOT NULL,
    sku VARCHAR(64) DEFAULT NULL,
    price_override DECIMAL(12,2) DEFAULT NULL,
    stock_quantity INT DEFAULT 0,
    image_id INT UNSIGNED DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (product_id) REFERENCES wk_products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wk_customers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    is_active TINYINT(1) DEFAULT 1,
    email_verified TINYINT(1) DEFAULT 0,
    total_orders INT DEFAULT 0,
    total_spent DECIMAL(12,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wk_customer_addresses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL,
    label VARCHAR(50) DEFAULT 'Home',
    address_line1 VARCHAR(255) NOT NULL,
    address_line2 VARCHAR(255),
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100) NOT NULL,
    postal_code VARCHAR(20) NOT NULL,
    country VARCHAR(2) NOT NULL DEFAULT 'IN',
    is_default TINYINT(1) DEFAULT 0,
    FOREIGN KEY (customer_id) REFERENCES wk_customers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wk_carts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED DEFAULT NULL,
    session_id VARCHAR(128) NOT NULL,
    email VARCHAR(255) DEFAULT NULL,
    status ENUM('active','merged','abandoned','converted') DEFAULT 'active',
    reminder_sent_at DATETIME DEFAULT NULL,
    reminder_count INT UNSIGNED DEFAULT 0,
    recovery_token CHAR(40) DEFAULT NULL,
    phone VARCHAR(40) DEFAULT NULL,
    abandoned_at DATETIME DEFAULT NULL,
    recovered_at DATETIME DEFAULT NULL,
    recovered_order_id INT UNSIGNED DEFAULT NULL,
    expires_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES wk_customers(id) ON DELETE SET NULL,
    UNIQUE KEY uniq_recovery_token (recovery_token),
    INDEX idx_session (session_id),
    INDEX idx_status_abandoned (status, abandoned_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wk_cart_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cart_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    variant_id INT UNSIGNED DEFAULT NULL,
    variant_combo_id INT UNSIGNED DEFAULT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    unit_price DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cart_id) REFERENCES wk_carts(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES wk_products(id) ON DELETE CASCADE,
    FOREIGN KEY (variant_id) REFERENCES wk_product_variants(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wk_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(30) NOT NULL UNIQUE,
    idempotency_key CHAR(64) NULL,
    customer_id INT UNSIGNED DEFAULT NULL,
    status ENUM('pending','processing','paid','shipped','delivered','cancelled','refunded','payment_failed') DEFAULT 'pending',
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    tax_details JSON DEFAULT NULL COMMENT 'Tax breakdown [{label,rate,amount}]',
    shipping_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    currency VARCHAR(3) DEFAULT 'INR',
    payment_gateway VARCHAR(30),
    payment_id VARCHAR(255),
    payment_status ENUM('pending','authorized','captured','failed','refunded') DEFAULT 'pending',
    billing_address JSON,
    shipping_address JSON,
    delivery_method VARCHAR(20) DEFAULT 'shipping' COMMENT 'shipping = home delivery, pickup = locker/collection point',
    pickup_location_id INT UNSIGNED DEFAULT NULL COMMENT 'wk_pickup_locations.id snapshot reference (address is snapshotted into shipping_address)',
    customer_email VARCHAR(255),
    customer_phone VARCHAR(20),
    notes TEXT,
    customer_note TEXT NULL,
    terms_accepted_at DATETIME NULL,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES wk_customers(id) ON DELETE SET NULL,
    UNIQUE KEY uniq_idempotency (idempotency_key),
    INDEX idx_order_number (order_number),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- Pickup points / parcel lockers (EU-style collection point delivery).
-- Admin-managed list; customer picks one at checkout instead of entering
-- a home shipping address. No FK from wk_orders — orders snapshot the
-- pickup address into shipping_address JSON so history survives deletes.
CREATE TABLE IF NOT EXISTS wk_pickup_locations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    carrier VARCHAR(100) DEFAULT '' COMMENT 'e.g. InPost, Mondial Relay, DHL Packstation',
    address_line1 VARCHAR(255) NOT NULL,
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100) DEFAULT '',
    zip VARCHAR(20) DEFAULT '',
    country CHAR(2) NOT NULL DEFAULT 'IN',
    opening_hours VARCHAR(255) DEFAULT '',
    fee DECIMAL(12,2) DEFAULT NULL COMMENT 'NULL = use shipping.pickup_fee setting',
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active (is_active)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wk_order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED DEFAULT NULL,
    product_name VARCHAR(255) NOT NULL,
    product_sku VARCHAR(64),
    variant_info VARCHAR(255),
    variant_combo_id INT UNSIGNED DEFAULT NULL,
    variant_label VARCHAR(255) DEFAULT NULL,
    quantity INT UNSIGNED NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    total_price DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES wk_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES wk_products(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wk_invoices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    invoice_number VARCHAR(30) NOT NULL UNIQUE,
    issued_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES wk_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wk_payment_gateways (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gateway_code VARCHAR(30) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    description VARCHAR(500),
    icon VARCHAR(255),
    is_active TINYINT(1) DEFAULT 0,
    is_test_mode TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    config JSON NOT NULL,
    supported_currencies JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wk_payment_transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    gateway_code VARCHAR(30) NOT NULL,
    transaction_id VARCHAR(255),
    gateway_order_id VARCHAR(255),
    amount DECIMAL(12,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'INR',
    status ENUM('initiated','pending','success','failed','refunded') DEFAULT 'initiated',
    gateway_response JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES wk_orders(id) ON DELETE CASCADE,
    INDEX idx_transaction (transaction_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wk_coupons (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    type ENUM('percentage','fixed') NOT NULL,
    value DECIMAL(12,2) NOT NULL,
    min_order_amount DECIMAL(12,2) DEFAULT 0.00,
    max_discount DECIMAL(12,2) DEFAULT NULL,
    usage_limit INT DEFAULT NULL,
    used_count INT DEFAULT 0,
    starts_at DATETIME,
    expires_at DATETIME,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wk_shipping_carriers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(50),
    tracking_url_template VARCHAR(500),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wk_tickets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_number VARCHAR(20) NOT NULL UNIQUE,
    customer_id INT UNSIGNED DEFAULT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    subject VARCHAR(255) NOT NULL,
    status ENUM('open','in_progress','waiting','resolved','closed') DEFAULT 'open',
    priority ENUM('low','medium','high','urgent') DEFAULT 'medium',
    order_id INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    closed_at DATETIME DEFAULT NULL,
    FOREIGN KEY (customer_id) REFERENCES wk_customers(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_email (email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wk_ticket_replies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT UNSIGNED NOT NULL,
    sender_type ENUM('customer','admin') NOT NULL,
    sender_name VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES wk_tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wk_pages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(60) NOT NULL UNIQUE,
    title VARCHAR(200) NOT NULL,
    content LONGTEXT NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wk_email_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wk_contact_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    email VARCHAR(255),
    subject VARCHAR(255),
    message TEXT,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ═══ SEED DATA ══════════════════════════════════

INSERT INTO wk_admins (username, email, password_hash, role) VALUES
('admin', 'admin@whisker.local', 'INSTALLER_WILL_SET_THIS', 'superadmin');

INSERT INTO wk_payment_gateways (gateway_code, display_name, description, is_active, is_test_mode, sort_order, config, supported_currencies) VALUES
('razorpay', 'Razorpay', 'UPI, Cards, Netbanking & Wallets', 0, 1, 1,
 '{"key_id":"","key_secret":"","webhook_secret":"","test_key_id":"","test_key_secret":""}',
 '["INR","USD"]'),
('ccavenue', 'CCAvenue', 'India''s largest payment gateway', 0, 1, 2,
 '{"merchant_id":"","access_code":"","working_key":"","test_merchant_id":"","test_access_code":"","test_working_key":""}',
 '["INR"]'),
('stripe', 'Stripe', 'International cards — 195+ countries', 0, 1, 3,
 '{"publishable_key":"","secret_key":"","webhook_secret":"","test_publishable_key":"","test_secret_key":""}',
 '["USD","EUR","GBP","INR","AUD","CAD","JPY"]'),
('nowpayments', 'NOWPayments', 'Bitcoin, Ethereum & 300+ cryptocurrencies', 0, 1, 4,
 '{"api_key":"","ipn_secret":"","test_api_key":""}',
 '["BTC","ETH","USDT","LTC","XRP"]');

INSERT INTO wk_settings (setting_group, setting_key, setting_value) VALUES
('leads', 'lead_capture_title', 'Before you go'),
('leads', 'lead_capture_coupon', ''),
('cart_recovery', 'recovery_enabled', '0'),
('cart_recovery', 'abandon_after_minutes', '60'),
('cart_recovery', 'recovery_schedule', '60,1440,4320'),
('cart_recovery', 'recovery_coupon', ''),
('leads', 'lead_capture_enabled', '0'),
('leads', 'lead_capture_fields', 'email'),
('leads', 'lead_capture_when', 'cart'),
('leads', 'lead_capture_trigger', 'both'),
('leads', 'lead_capture_delay', '90'),
('checkout', 'auto_refund_on_cancel', '0'),
('checkout', 'cancel_window_minutes', '0'),
('checkout', 'show_cancel_deadline', '1'),
('social', 'social_enabled', '0'),
('social', 'social_position', 'left'),
('social', 'social_display', 'always'),
('questions', 'questions_enabled', '1'),
('questions', 'notify_on_answer', '1'),
('reviews', 'reviews_enabled', '1'),
('reviews', 'review_policy', 'purchased'),
('reviews', 'auto_approve', '0'),
('reviews', 'show_on_cards', '1'),
('shipping', 'ship_mode', 'domestic'),
('shipping', 'ship_countries', ''),
('privacy', 'cookie_consent', '0'),
('privacy', 'cookie_title', 'We use cookies'),
('privacy', 'cookie_text', 'We use cookies to keep your cart working and to understand how the store is used. You can accept or reject the optional ones.'),
('privacy', 'cookie_policy_url', ''),
('privacy', 'cookie_analytics', '1'),
('privacy', 'cookie_marketing', '0'),
('privacy', 'cookie_version', '1'),
('general', 'site_name', 'My Whisker Store'),
('general', 'site_tagline', 'Shop the things you love'),
('general', 'currency', 'INR'),
('general', 'currency_symbol', '₹'),
('general', 'multi_currency', '0'),
('general', 'timezone', 'Asia/Kolkata'),
('general', 'license_type', 'free'),
('general', 'whisker_version', '1.0.0'),
('checkout', 'guest_checkout', '1'),
('checkout', 'tax_rate', '18'),
('checkout', 'shipping_flat_rate', '50.00'),
('shipping', 'pickup_enabled', '0'),
('shipping', 'pickup_fee', ''),
('email', 'from_email', 'shop@yourdomain.com'),
('email', 'from_name', 'My Whisker Store'),
('seo', 'site_meta_title', NULL),
('seo', 'site_meta_description', NULL),
('seo', 'site_meta_keywords', NULL),
('seo', 'og_image', NULL),
('seo', 'title_separator', ' — '),
('seo', 'title_format', '{page} {sep} {site}'),
('seo', 'twitter_handle', NULL),
('seo', 'google_verification', NULL),
('seo', 'bing_verification', NULL),
('seo', 'robots_index', '1'),
('seo', 'robots_follow', '1'),
('seo', 'auto_generate_meta', '1'),
('seo', 'sitemap_enabled', '1'),
('seo', 'canonical_url', NULL),
('seo', 'schema_org_enabled', '1');

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

CREATE TABLE IF NOT EXISTS wk_email_suppressions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    reason VARCHAR(60) NOT NULL DEFAULT 'unsubscribed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_suppressed_email (email)
) ENGINE=InnoDB;

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

CREATE TABLE IF NOT EXISTS wk_refunds (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    refund_ref VARCHAR(32) NOT NULL,
    gateway_code VARCHAR(30) DEFAULT NULL,
    gateway_refund_id VARCHAR(255) DEFAULT NULL,
    amount DECIMAL(12,2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'INR',
    reason VARCHAR(255) DEFAULT NULL,
    -- unknown: the gateway call did not complete, so the money may or may not
    -- have moved. Blocks further refunds on the order until a human resolves it.
    status ENUM('completed','pending','failed','unknown') NOT NULL DEFAULT 'pending',
    is_manual TINYINT(1) NOT NULL DEFAULT 0,
    message TEXT DEFAULT NULL,
    gateway_response TEXT DEFAULT NULL,
    admin_id INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_refund_ref (refund_ref),
    INDEX idx_order (order_id),
    INDEX idx_status (status),
    FOREIGN KEY (order_id) REFERENCES wk_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;
