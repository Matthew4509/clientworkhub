
ALTER TABLE job_template
    ADD COLUMN taxable TINYINT(1) NOT NULL DEFAULT 1 AFTER amount_cents;
