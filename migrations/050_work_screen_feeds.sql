ALTER TABLE work_access
  ADD COLUMN show_business_name TINYINT(1) NOT NULL DEFAULT 0 AFTER public_heading,
  ADD COLUMN show_client_name TINYINT(1) NOT NULL DEFAULT 0 AFTER show_business_name,
  ADD COLUMN highlight_vip TINYINT(1) NOT NULL DEFAULT 0 AFTER show_status,
  ADD COLUMN max_items SMALLINT UNSIGNED NOT NULL DEFAULT 10 AFTER highlight_vip;

