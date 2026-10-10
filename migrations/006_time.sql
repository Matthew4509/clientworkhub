
CREATE TABLE IF NOT EXISTS time_entry (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    occurrence_id INT UNSIGNED NOT NULL,
    job_id        INT UNSIGNED NOT NULL,
    client_id     INT UNSIGNED NOT NULL,
    started_at    DATETIME     NULL,
    minutes       INT          NOT NULL,
    billable      TINYINT(1)   NOT NULL DEFAULT 1,
    source        VARCHAR(10)  NOT NULL DEFAULT 'timer',
    note          VARCHAR(255) NOT NULL DEFAULT '',
    created_by    VARCHAR(190) NOT NULL DEFAULT '',
    created_at    DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY ix_time_occurrence (occurrence_id),
    KEY ix_time_job (job_id),
    KEY ix_time_client (client_id, created_at),
    CONSTRAINT fk_time_occ FOREIGN KEY (occurrence_id) REFERENCES action_occurrence (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
