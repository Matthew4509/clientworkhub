
CREATE TABLE IF NOT EXISTS client_asset (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id   INT UNSIGNED NOT NULL,
    name        VARCHAR(120) NOT NULL,
    type        VARCHAR(30)  NOT NULL DEFAULT 'other',
    url         VARCHAR(255) NOT NULL DEFAULT '',
    handle      VARCHAR(190) NOT NULL DEFAULT '',
    notes       TEXT         NULL,
    status      VARCHAR(20)  NOT NULL DEFAULT 'active',
    created_at  DATETIME     NOT NULL,
    updated_at  DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY ix_asset_client (client_id),
    KEY ix_asset_status (status),
    CONSTRAINT fk_asset_client FOREIGN KEY (client_id) REFERENCES client (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS client_credential (
    asset_id     INT UNSIGNED NOT NULL,
    login_url    VARCHAR(255) NOT NULL DEFAULT '',
    username     VARCHAR(190) NOT NULL DEFAULT '',
    password_enc TEXT         NULL,
    notes_enc    TEXT         NULL,
    recovery_enc TEXT         NULL,
    updated_at   DATETIME     NOT NULL,
    updated_by   VARCHAR(190) NOT NULL DEFAULT '',
    PRIMARY KEY (asset_id),
    CONSTRAINT fk_credential_asset FOREIGN KEY (asset_id) REFERENCES client_asset (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
