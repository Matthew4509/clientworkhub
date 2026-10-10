
ALTER TABLE appointment
    ADD COLUMN is_work TINYINT(1) NOT NULL DEFAULT 1 AFTER end_time;
