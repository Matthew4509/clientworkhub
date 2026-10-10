
ALTER TABLE booking
    ADD COLUMN identity_checked TINYINT(1) NOT NULL DEFAULT 0 AFTER client_id;
