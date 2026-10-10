
CREATE TABLE IF NOT EXISTS reminder (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title        VARCHAR(190) NOT NULL,
    description  TEXT         NULL,
    due_date     DATE         NOT NULL,
    repeat_every VARCHAR(10)  NOT NULL DEFAULT 'none',
    anchor_day   TINYINT UNSIGNED NULL,
    priority     VARCHAR(10)  NOT NULL DEFAULT 'normal',
    status       VARCHAR(10)  NOT NULL DEFAULT 'pending',
    snoozed_until DATE        NULL,
    client_id    INT UNSIGNED NULL,
    job_id       INT UNSIGNED NULL,
    asset_id     INT UNSIGNED NULL,
    completed_at DATETIME     NULL,
    created_at   DATETIME     NOT NULL,
    updated_at   DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY ix_reminder_due (status, due_date),
    KEY ix_reminder_client (client_id),
    CONSTRAINT fk_reminder_client FOREIGN KEY (client_id) REFERENCES client (id) ON DELETE CASCADE,
    CONSTRAINT fk_reminder_job FOREIGN KEY (job_id) REFERENCES job (id) ON DELETE SET NULL,
    CONSTRAINT fk_reminder_asset FOREIGN KEY (asset_id) REFERENCES client_asset (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS communication (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id   INT UNSIGNED NOT NULL,
    contact     VARCHAR(160) NOT NULL DEFAULT '',
    kind        VARCHAR(10)  NOT NULL DEFAULT 'email',
    subject     VARCHAR(190) NOT NULL,
    notes       TEXT         NULL,
    happened_at DATETIME     NOT NULL,
    follow_up   DATE         NULL,
    created_at  DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY ix_comm_client (client_id, happened_at),
    CONSTRAINT fk_comm_client FOREIGN KEY (client_id) REFERENCES client (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
