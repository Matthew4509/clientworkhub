
ALTER TABLE appointment
    ADD COLUMN kind          VARCHAR(60)  NOT NULL DEFAULT '' AFTER title,
    ADD COLUMN buffer_before INT UNSIGNED NOT NULL DEFAULT 0  AFTER end_time,
    ADD COLUMN buffer_after  INT UNSIGNED NOT NULL DEFAULT 0  AFTER buffer_before,
    ADD COLUMN series_id     INT UNSIGNED NULL DEFAULT NULL   AFTER buffer_after,
    ADD KEY ix_appt_series (series_id);
