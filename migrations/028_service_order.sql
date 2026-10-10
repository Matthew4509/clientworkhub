
ALTER TABLE job_template
    ADD COLUMN sort INT NOT NULL DEFAULT 100 AFTER category_id;

CREATE INDEX ix_job_template_sort ON job_template (category_id, sort);
