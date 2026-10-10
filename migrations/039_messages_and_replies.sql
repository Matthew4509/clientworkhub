
ALTER TABLE onboarding_response
    ADD COLUMN form_id INT UNSIGNED NULL AFTER template_id,
    ADD COLUMN message TEXT NULL AFTER form_id,
    ADD COLUMN opened_at DATETIME NULL AFTER created_at,
    ADD CONSTRAINT fk_response_form FOREIGN KEY (form_id)
        REFERENCES onboarding_form (id) ON DELETE SET NULL;


CREATE TABLE IF NOT EXISTS mail_reply (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    mail_id    INT UNSIGNED NOT NULL,
    client_id  INT UNSIGNED NULL,
    note       MEDIUMTEXT   NOT NULL,
    created_by VARCHAR(190) NOT NULL DEFAULT '',
    created_at DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY ix_reply_mail (mail_id, created_at),
    KEY ix_reply_client (client_id, created_at),
    CONSTRAINT fk_reply_mail FOREIGN KEY (mail_id)
        REFERENCES mail_log (id) ON DELETE CASCADE,
    CONSTRAINT fk_reply_client FOREIGN KEY (client_id)
        REFERENCES client (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
