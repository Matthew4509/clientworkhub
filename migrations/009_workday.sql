
CREATE TABLE IF NOT EXISTS appointment (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title      VARCHAR(160) NOT NULL,
    client_id  INT UNSIGNED NULL,
    on_date    DATE         NOT NULL,
    start_time TIME         NOT NULL,
    end_time   TIME         NOT NULL,
    notes      VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY ix_appt_when (on_date, start_time),
    CONSTRAINT fk_appt_client FOREIGN KEY (client_id) REFERENCES client (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE job_template
    ADD COLUMN duration_days INT UNSIGNED NOT NULL DEFAULT 0 AFTER completion_mode;
