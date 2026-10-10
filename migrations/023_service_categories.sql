
CREATE TABLE IF NOT EXISTS service_category (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name       VARCHAR(80)  NOT NULL,
    sort       INT          NOT NULL DEFAULT 100,
    created_at DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY ux_service_category_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE job_template
    ADD COLUMN category_id INT UNSIGNED NULL DEFAULT NULL AFTER name,
    ADD KEY ix_template_category (category_id),
    ADD CONSTRAINT fk_template_category FOREIGN KEY (category_id)
        REFERENCES service_category (id) ON DELETE SET NULL;
