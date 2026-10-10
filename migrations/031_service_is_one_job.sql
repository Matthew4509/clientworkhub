
ALTER TABLE job_template
    ADD COLUMN frequency VARCHAR(10) NOT NULL DEFAULT '' AFTER duration_days;
