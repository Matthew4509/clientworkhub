


ALTER TABLE job_template
    ADD COLUMN published    TINYINT(1)   NOT NULL DEFAULT 0 AFTER name,
    ADD COLUMN featured     TINYINT(1)   NOT NULL DEFAULT 0 AFTER published,
    ADD COLUMN public_name  VARCHAR(160) NOT NULL DEFAULT '' AFTER featured,
    ADD COLUMN public_blurb TEXT         NULL               AFTER public_name,
    ADD COLUMN public_sort  INT          NOT NULL DEFAULT 100 AFTER public_blurb,
    ADD KEY ix_tpl_public (published, featured, public_sort);



CREATE TABLE IF NOT EXISTS client_link (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id    INT UNSIGNED NOT NULL,
    token        CHAR(64)     NOT NULL,
    uses         INT UNSIGNED NOT NULL DEFAULT 0,
    last_used_at DATETIME     NULL,
    revoked_at   DATETIME     NULL,
    created_at   DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_link_token (token),
    KEY ix_link_client (client_id, revoked_at),
    CONSTRAINT fk_link_client FOREIGN KEY (client_id) REFERENCES client (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE IF NOT EXISTS booking (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    reference     CHAR(12)     NOT NULL,
    template_id   INT UNSIGNED NULL,
    client_id     INT UNSIGNED NULL,
    link_id       INT UNSIGNED NULL,
    name          VARCHAR(160) NOT NULL DEFAULT '',
    email         VARCHAR(190) NOT NULL DEFAULT '',
    phone         VARCHAR(60)  NOT NULL DEFAULT '',
    company       VARCHAR(160) NOT NULL DEFAULT '',
    message       TEXT         NULL,
    quoted_cents  INT          NOT NULL DEFAULT 0,
    quoted_freq   VARCHAR(20)  NOT NULL DEFAULT 'oneoff',
    requested_from DATE        NULL,
    planned_start DATE         NULL,
    planned_end   DATE         NULL,
    plan_note     VARCHAR(255) NOT NULL DEFAULT '',
    status        VARCHAR(15)  NOT NULL DEFAULT 'new',
    job_id        INT UNSIGNED NULL,
    created_at    DATETIME     NOT NULL,
    updated_at    DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_booking_ref (reference),
    KEY ix_booking_status (status, created_at),
    KEY ix_booking_client (client_id),
    CONSTRAINT fk_booking_template FOREIGN KEY (template_id) REFERENCES job_template (id) ON DELETE SET NULL,
    CONSTRAINT fk_booking_client FOREIGN KEY (client_id) REFERENCES client (id) ON DELETE SET NULL,
    CONSTRAINT fk_booking_job FOREIGN KEY (job_id) REFERENCES job (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE IF NOT EXISTS client_feedback (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id  INT UNSIGNED NULL,
    job_id     INT UNSIGNED NULL,
    link_id    INT UNSIGNED NULL,
    sentiment  VARCHAR(12)  NOT NULL DEFAULT 'unsaid',
    wants      TEXT         NULL,
    status     VARCHAR(12)  NOT NULL DEFAULT 'new',
    created_at DATETIME     NOT NULL,
    handled_at DATETIME     NULL,
    PRIMARY KEY (id),
    KEY ix_feedback_status (status, created_at),
    KEY ix_feedback_client (client_id),
    CONSTRAINT fk_feedback_client FOREIGN KEY (client_id) REFERENCES client (id) ON DELETE SET NULL,
    CONSTRAINT fk_feedback_job FOREIGN KEY (job_id) REFERENCES job (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
