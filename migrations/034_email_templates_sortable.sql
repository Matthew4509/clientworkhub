
ALTER TABLE email_template
    ADD COLUMN sort INT UNSIGNED NOT NULL DEFAULT 0 AFTER locked;

UPDATE email_template SET sort = 10 WHERE ekey = 'job_summary';
UPDATE email_template SET sort = 20 WHERE ekey = 'client_followup';
UPDATE email_template SET sort = 10 WHERE ekey = 'password_reset';
UPDATE email_template SET sort = 20 WHERE ekey = 'signin_code';
