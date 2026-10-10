
ALTER TABLE onboarding_form
    ADD COLUMN one_off TINYINT(1) NOT NULL DEFAULT 0 AFTER is_general;

ALTER TABLE onboarding_response
    ADD COLUMN heading VARCHAR(190) NULL AFTER message,
    ADD COLUMN is_message TINYINT(1) NOT NULL DEFAULT 0 AFTER heading;
