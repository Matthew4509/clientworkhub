
ALTER TABLE job
    ADD COLUMN template_id INT UNSIGNED NULL AFTER client_id,
    ADD KEY ix_job_template (template_id),
    ADD CONSTRAINT fk_job_template FOREIGN KEY (template_id)
        REFERENCES job_template (id) ON DELETE SET NULL;
