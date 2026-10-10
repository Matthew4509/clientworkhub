
CREATE TABLE IF NOT EXISTS job_template (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name       VARCHAR(160) NOT NULL,
    notes      TEXT         NULL,
    created_at DATETIME     NOT NULL,
    updated_at DATETIME     NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS job_template_action (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    template_id INT UNSIGNED NOT NULL,
    name        VARCHAR(160) NOT NULL,
    frequency   VARCHAR(10)  NOT NULL DEFAULT 'oneoff',
    anchor      VARCHAR(10)  NOT NULL DEFAULT 'start',
    est_minutes INT UNSIGNED NOT NULL DEFAULT 0,
    required    TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY ix_tpl_action (template_id),
    CONSTRAINT fk_tplaction_tpl FOREIGN KEY (template_id) REFERENCES job_template (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
