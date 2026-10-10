ALTER TABLE client_asset
  ADD COLUMN property_kind VARCHAR(30) NOT NULL DEFAULT 'web' AFTER type,
  ADD COLUMN property_data TEXT NULL AFTER core_details;

