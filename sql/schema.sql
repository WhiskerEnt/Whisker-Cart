-- WHISKER v1.2.0 — Complete Database Schema (26 tables)

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
    expires_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES wk_customers(id) ON DELETE SET NULL,
    INDEX idx_session (session_id)
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
    customer_id INT UNSIGNED DEFAULT NULL,
    status ENUM('pending','processing','paid','shipped','delivered','cancelled','refunded') DEFAULT 'pending',
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
    customer_email VARCHAR(255),
    customer_phone VARCHAR(20),
    notes TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES wk_customers(id) ON DELETE SET NULL,
    INDEX idx_order_number (order_number),
    INDEX idx_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wk_order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED DEFAULT NULL,
    product_name VARCHAR(255) NOT NULL,
    product_sku VARCHAR(64),
    variant_info VARCHAR(255),
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
('general', 'site_name', 'My Whisker Store'),
('general', 'site_tagline', 'Shop the things you love'),
('general', 'currency', 'INR'),
('general', 'currency_symbol', '₹'),
('general', 'timezone', 'Asia/Kolkata'),
('general', 'license_type', 'free'),
('general', 'whisker_version', '1.0.0'),
('checkout', 'guest_checkout', '1'),
('checkout', 'tax_rate', '18'),
('checkout', 'shipping_flat_rate', '50.00'),
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