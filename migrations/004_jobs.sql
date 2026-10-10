
CREATE TABLE IF NOT EXISTS job (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id    INT UNSIGNED NOT NULL,
    name         VARCHAR(160) NOT NULL,
    description  TEXT         NULL,
    status       VARCHAR(20)  NOT NULL DEFAULT 'active',
    priority     VARCHAR(10)  NOT NULL DEFAULT 'normal',
    start_date   DATE         NULL,
    end_date     DATE         NULL,
    amount_cents INT UNSIGNED NOT NULL DEFAULT 0,
    billing_freq VARCHAR(10)  NOT NULL DEFAULT 'oneoff',
    est_minutes  INT UNSIGNED NOT NULL DEFAULT 0,
    tags         VARCHAR(255) NOT NULL DEFAULT '',
    notes        TEXT         NULL,
    completed_at DATETIME     NULL,
    created_at   DATETIME     NOT NULL,
    updated_at   DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY ix_job_client (client_id),
    KEY ix_job_status (status),
    CONSTRAINT fk_job_client FOREIGN KEY (client_id) REFERENCES client (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS job_asset (
    job_id   INT UNSIGNED NOT NULL,
    asset_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (job_id, asset_id),
    KEY ix_jobasset_asset (asset_id),
    CONSTRAINT fk_jobasset_job FOREIGN KEY (job_id) REFERENCES job (id) ON DELETE CASCADE,
    CONSTRAINT fk_jobasset_asset FOREIGN KEY (asset_id) REFERENCES client_asset (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS job_action (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id      INT UNSIGNED NOT NULL,
    name        VARCHAR(160) NOT NULL,
    frequency   VARCHAR(10)  NOT NULL DEFAULT 'oneoff',
    start_date  DATE         NOT NULL,
    end_date    DATE         NULL,
    est_minutes INT UNSIGNED NOT NULL DEFAULT 0,
    required    TINYINT(1)   NOT NULL DEFAULT 1,
    notes       TEXT         NULL,
    created_at  DATETIME     NOT NULL,
    updated_at  DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY ix_action_job (job_id),
    CONSTRAINT fk_action_job FOREIGN KEY (job_id) REFERENCES job (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS action_occurrence (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    action_id      INT UNSIGNED NOT NULL,
    job_id         INT UNSIGNED NOT NULL,
    scheduled_date DATE         NOT NULL,
    status         VARCHAR(15)  NOT NULL DEFAULT 'scheduled',
    started_at     DATETIME     NULL,
    completed_at   DATETIME     NULL,
    minutes_spent  INT UNSIGNED NOT NULL DEFAULT 0,
    note           TEXT         NULL,
    content_url    VARCHAR(255) NOT NULL DEFAULT '',
    moved_to       DATE         NULL,
    completed_by   VARCHAR(190) NOT NULL DEFAULT '',
    created_at     DATETIME     NOT NULL,
    updated_at     DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_occurrence (action_id, scheduled_date),
    KEY ix_occ_job (job_id),
    KEY ix_occ_queue (status, scheduled_date),
    CONSTRAINT fk_occ_action FOREIGN KEY (action_id) REFERENCES job_action (id) ON DELETE CASCADE,
    CONSTRAINT fk_occ_job FOREIGN KEY (job_id) REFERENCES job (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
