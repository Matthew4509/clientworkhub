
CREATE TABLE IF NOT EXISTS onboarding_form_category (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name       VARCHAR(80)  NOT NULL,
    sort       INT          NOT NULL DEFAULT 100,
    created_at DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY ux_onboarding_form_category_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS onboarding_form (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(160) NOT NULL,
    description TEXT         NOT NULL,
    category_id INT UNSIGNED NULL,
    sort        INT          NOT NULL DEFAULT 100,
    is_general  TINYINT(1)   NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL,
    updated_at  DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY ix_onboarding_form_sort (category_id, sort),
    CONSTRAINT fk_onboarding_form_category FOREIGN KEY (category_id)
        REFERENCES onboarding_form_category (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE onboarding_field
    ADD COLUMN form_id INT UNSIGNED NULL AFTER template_id,
    ADD KEY ix_field_formid (form_id, sort),
    ADD CONSTRAINT fk_field_form FOREIGN KEY (form_id)
        REFERENCES onboarding_form (id) ON DELETE CASCADE;

INSERT INTO onboarding_form (name, description, category_id, sort, is_general, created_at, updated_at)
SELECT 'General questions',
       'Asked of every client, whatever they have booked. Each service can add its own questions on top.',
       NULL, 10, 1, NOW(), NOW()
  FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM onboarding_form WHERE is_general = 1);

UPDATE onboarding_field
   SET form_id = (SELECT id FROM onboarding_form WHERE is_general = 1 LIMIT 1)
 WHERE template_id IS NULL AND form_id IS NULL;
