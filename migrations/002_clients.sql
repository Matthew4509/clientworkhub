
CREATE TABLE IF NOT EXISTS client (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    first_name VARCHAR(80)  NOT NULL DEFAULT '',
    last_name  VARCHAR(80)  NOT NULL DEFAULT '',
    company    VARCHAR(160) NOT NULL DEFAULT '',
    email      VARCHAR(190) NOT NULL DEFAULT '',
    phone      VARCHAR(60)  NOT NULL DEFAULT '',
    status     VARCHAR(20)  NOT NULL DEFAULT 'active',
    tags       VARCHAR(255) NOT NULL DEFAULT '',
    notes      TEXT         NULL,
    created_at DATETIME     NOT NULL,
    updated_at DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY ix_client_status (status),
    KEY ix_client_company (company),
    KEY ix_client_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS client_contact (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id    INT UNSIGNED NOT NULL,
    first_name   VARCHAR(80)  NOT NULL DEFAULT '',
    last_name    VARCHAR(80)  NOT NULL DEFAULT '',
    role         VARCHAR(120) NOT NULL DEFAULT '',
    email        VARCHAR(190) NOT NULL DEFAULT '',
    phone        VARCHAR(60)  NOT NULL DEFAULT '',
    notes        TEXT         NULL,
    is_primary   TINYINT(1)   NOT NULL DEFAULT 0,
    has_approval TINYINT(1)   NOT NULL DEFAULT 0,
    created_at   DATETIME     NOT NULL,
    updated_at   DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY ix_contact_client (client_id),
    CONSTRAINT fk_contact_client FOREIGN KEY (client_id) REFERENCES client (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS client_core_detail (
    client_id  INT UNSIGNED NOT NULL,
    dkey       VARCHAR(60)  NOT NULL,
    dval       TEXT         NOT NULL,
    updated_at DATETIME     NOT NULL,
    PRIMARY KEY (client_id, dkey),
    CONSTRAINT fk_core_client FOREIGN KEY (client_id) REFERENCES client (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
