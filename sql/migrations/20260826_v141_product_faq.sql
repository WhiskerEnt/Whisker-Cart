-- Frequently asked questions the shopkeeper writes for a product.
--
-- Separate from wk_questions, which is what customers ask: this is the
-- shop answering before anyone has to ask. Stored as JSON on the product so
-- a bulk upload can carry it in one column.

ALTER TABLE wk_products ADD COLUMN faq TEXT DEFAULT NULL;
