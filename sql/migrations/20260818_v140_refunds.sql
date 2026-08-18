-- Refunds issued from the admin panel.
--
-- One row per attempt, including the ones that failed, so the history of what
-- was tried against the gateway survives. refund_ref is ours and is generated
-- before the gateway is called, so it can be used as the idempotency key.

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

-- Orders can now be partly refunded, which is neither 'paid' nor 'refunded'.
ALTER TABLE wk_orders
    MODIFY COLUMN payment_status ENUM('pending','authorized','captured','failed','refunded','partially_refunded')
    DEFAULT 'pending';
