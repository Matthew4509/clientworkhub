
ALTER TABLE client_credential
    ADD COLUMN password_strength TINYINT NULL AFTER password_enc;

ALTER TABLE action_occurrence
    ADD COLUMN est_minutes INT UNSIGNED NOT NULL DEFAULT 0 AFTER minutes_spent;

ALTER TABLE job_template
    ADD COLUMN amount_cents INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN billing_freq VARCHAR(10) NOT NULL DEFAULT 'oneoff',
    ADD COLUMN total_minutes INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN setup_minutes INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN completion_minutes INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN completion_mode VARCHAR(10) NOT NULL DEFAULT 'last_day';
