


CREATE TABLE IF NOT EXISTS onboarding_field (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    template_id INT UNSIGNED NULL,
    fkey        VARCHAR(60)  NOT NULL,
    label       VARCHAR(190) NOT NULL,
    ftype       VARCHAR(20)  NOT NULL DEFAULT 'text',
    help        VARCHAR(400) NOT NULL DEFAULT '',
    choices     TEXT         NULL,
    required    TINYINT(1)   NOT NULL DEFAULT 0,
    sort        INT          NOT NULL DEFAULT 100,
    created_at  DATETIME     NOT NULL,
    updated_at  DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY ix_field_form (template_id, sort),
    CONSTRAINT fk_field_template FOREIGN KEY (template_id)
        REFERENCES job_template (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE IF NOT EXISTS onboarding_response (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    token        CHAR(64)     NOT NULL,
    client_id    INT UNSIGNED NULL,
    booking_id   INT UNSIGNED NULL,
    job_id       INT UNSIGNED NULL,
    template_id  INT UNSIGNED NULL,
    status       VARCHAR(12)  NOT NULL DEFAULT 'pending',
    created_at   DATETIME     NOT NULL,
    completed_at DATETIME     NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_response_token (token),
    KEY ix_response_status (status, created_at),
    KEY ix_response_client (client_id),
    CONSTRAINT fk_response_client FOREIGN KEY (client_id) REFERENCES client (id) ON DELETE CASCADE,
    CONSTRAINT fk_response_booking FOREIGN KEY (booking_id) REFERENCES booking (id) ON DELETE SET NULL,
    CONSTRAINT fk_response_job FOREIGN KEY (job_id) REFERENCES job (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE IF NOT EXISTS onboarding_answer (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    response_id       INT UNSIGNED NOT NULL,
    fkey              VARCHAR(60)  NOT NULL,
    label_at_the_time VARCHAR(190) NOT NULL DEFAULT '',
    value             TEXT         NULL,
    created_at        DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_answer (response_id, fkey),
    CONSTRAINT fk_answer_response FOREIGN KEY (response_id)
        REFERENCES onboarding_response (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



ALTER TABLE job_template
    ADD COLUMN onboard_when VARCHAR(12) NOT NULL DEFAULT 'accept' AFTER duration_days;
