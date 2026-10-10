
ALTER TABLE job_template
    ADD COLUMN per_day_minutes INT UNSIGNED NOT NULL DEFAULT 0 AFTER total_minutes;
