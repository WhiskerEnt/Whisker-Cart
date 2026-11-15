-- Whether a customer has a password is answered by their password_hash:
-- checkout writes the sentinel '*' for a guest, registration writes a real
-- hash. A second copy of that answer was kept here, one row per customer,
-- and registration never wrote it, so every account made through the
-- registration form was told to set a password it already had.
--
-- Nothing reads these rows now.
DELETE FROM wk_settings WHERE setting_group = 'customer_flags';
