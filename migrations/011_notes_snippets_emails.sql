
ALTER TABLE action_occurrence
    ADD COLUMN internal_note TEXT NULL AFTER note;



CREATE TABLE IF NOT EXISTS work_snippet (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    body       VARCHAR(400) NOT NULL,
    uses       INT UNSIGNED NOT NULL DEFAULT 0,
    archived   TINYINT(1)   NOT NULL DEFAULT 0,
    created_at DATETIME     NOT NULL,
    updated_at DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY ix_snippet_live (archived, uses)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE IF NOT EXISTS job_template_snippet (
    template_id INT UNSIGNED NOT NULL,
    snippet_id  INT UNSIGNED NOT NULL,
    PRIMARY KEY (template_id, snippet_id),
    KEY ix_tsnip_snippet (snippet_id),
    CONSTRAINT fk_tsnip_template FOREIGN KEY (template_id) REFERENCES job_template (id) ON DELETE CASCADE,
    CONSTRAINT fk_tsnip_snippet  FOREIGN KEY (snippet_id)  REFERENCES work_snippet (id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE IF NOT EXISTS email_template (
    ekey       VARCHAR(60)  NOT NULL,
    subject    VARCHAR(200) NOT NULL,
    body       TEXT         NOT NULL,
    locked     TINYINT(1)   NOT NULL DEFAULT 0,
    updated_at DATETIME     NOT NULL,
    updated_by VARCHAR(190) NOT NULL DEFAULT '',
    PRIMARY KEY (ekey)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
