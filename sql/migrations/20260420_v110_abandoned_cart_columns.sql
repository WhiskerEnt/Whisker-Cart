-- v1.1.0 — Abandoned cart email columns
--
-- Portable syntax note: `ADD COLUMN IF NOT EXISTS` is MariaDB-only (real
-- MySQL 5.7/8.0 rejects it). Plain ADD COLUMN is used instead; the migration
-- runner treats "Duplicate column" as already-applied, so re-runs are safe.

ALTER TABLE wk_carts ADD COLUMN email VARCHAR(255) DEFAULT NULL AFTER session_id;

ALTER TABLE wk_carts ADD COLUMN reminder_sent_at DATETIME DEFAULT NULL;

ALTER TABLE wk_carts ADD COLUMN reminder_count INT UNSIGNED DEFAULT 0;
