
ALTER TABLE client
    ADD COLUMN unsubscribed_at   DATETIME     NULL DEFAULT NULL AFTER vip,
    ADD COLUMN unsubscribed_how  VARCHAR(40)  NOT NULL DEFAULT '' AFTER unsubscribed_at,
    ADD COLUMN resubscribed_at   DATETIME     NULL DEFAULT NULL AFTER unsubscribed_how,
    ADD COLUMN resubscribed_by   VARCHAR(190) NOT NULL DEFAULT '' AFTER resubscribed_at,
    ADD KEY ix_client_unsub (unsubscribed_at);

ALTER TABLE mail_log
    ADD COLUMN client_id INT UNSIGNED NULL DEFAULT NULL AFTER id,
    ADD COLUMN body      MEDIUMTEXT   NULL              AFTER subject,
    ADD KEY ix_mail_client (client_id, created_at);
