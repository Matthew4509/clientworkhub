
CREATE TABLE IF NOT EXISTS service_finder_question (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    prompt     VARCHAR(200)  NOT NULL,
    hint       VARCHAR(255)  NOT NULL DEFAULT '',
    sort       INT           NOT NULL DEFAULT 100,
    created_at DATETIME      NOT NULL,
    updated_at DATETIME      NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_finder_option (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    question_id INT UNSIGNED NOT NULL,
    label       VARCHAR(200) NOT NULL,
    sort        INT          NOT NULL DEFAULT 100,
    PRIMARY KEY (id),
    KEY ix_sfopt_question (question_id),
    CONSTRAINT fk_sfopt_question FOREIGN KEY (question_id)
        REFERENCES service_finder_question (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_finder_points (
    option_id   INT UNSIGNED NOT NULL,
    template_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (option_id, template_id),
    KEY ix_sfpts_template (template_id),
    CONSTRAINT fk_sfpts_option FOREIGN KEY (option_id)
        REFERENCES service_finder_option (id) ON DELETE CASCADE,
    CONSTRAINT fk_sfpts_template FOREIGN KEY (template_id)
        REFERENCES job_template (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
