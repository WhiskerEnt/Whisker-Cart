-- The size a picture will occupy, known before it arrives.
--
-- Without it the browser cannot reserve the space, so every image that loads
-- shoves the page down as it appears. Recorded once at upload; existing images
-- are measured and filled in by the backfill.
ALTER TABLE wk_product_images ADD COLUMN width SMALLINT UNSIGNED NULL AFTER image_path;
ALTER TABLE wk_product_images ADD COLUMN height SMALLINT UNSIGNED NULL AFTER width;
