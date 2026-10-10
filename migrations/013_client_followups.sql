
ALTER TABLE reminder
    ADD COLUMN kind         VARCHAR(10)  NOT NULL DEFAULT 'nudge' AFTER title,
    ADD COLUMN template_key VARCHAR(60)  NOT NULL DEFAULT ''      AFTER kind,
    ADD COLUMN sent_at      DATETIME     NULL                     AFTER template_key,
    ADD KEY ix_reminder_due_kind (kind, status, due_date);
