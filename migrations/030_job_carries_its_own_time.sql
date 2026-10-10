
ALTER TABLE job
    ADD COLUMN frequency VARCHAR(10) NOT NULL DEFAULT '' AFTER end_date,
    ADD COLUMN duration_days INT UNSIGNED NOT NULL DEFAULT 0 AFTER frequency,
    ADD COLUMN per_day_minutes INT UNSIGNED NOT NULL DEFAULT 0 AFTER est_minutes,
    ADD COLUMN setup_minutes INT UNSIGNED NOT NULL DEFAULT 0 AFTER per_day_minutes,
    ADD COLUMN completion_minutes INT UNSIGNED NOT NULL DEFAULT 0 AFTER setup_minutes,
    ADD COLUMN completion_mode VARCHAR(10) NOT NULL DEFAULT 'last_day' AFTER completion_minutes;

ALTER TABLE action_occurrence DROP FOREIGN KEY fk_occ_action;

ALTER TABLE action_occurrence MODIFY COLUMN action_id INT UNSIGNED NULL;

ALTER TABLE action_occurrence
    ADD CONSTRAINT fk_occ_action FOREIGN KEY (action_id)
        REFERENCES job_action (id) ON DELETE CASCADE;
