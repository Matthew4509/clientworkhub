
CREATE TABLE IF NOT EXISTS account (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email          VARCHAR(190) NOT NULL,
    name           VARCHAR(120) NOT NULL DEFAULT '',
    password_hash  VARCHAR(255) NOT NULL DEFAULT '',
    role           VARCHAR(20)  NOT NULL DEFAULT '',
    email_verified DATETIME     NULL,
    tfa_method     VARCHAR(10)  NOT NULL DEFAULT '',
    tfa_secret     TEXT         NULL,
    tfa_enrolled_at DATETIME    NULL,
    tfa_last_step  INT UNSIGNED NOT NULL DEFAULT 0,
    tfa_backup     TEXT         NULL,
    tfa_code_hash  VARCHAR(255) NOT NULL DEFAULT '',
    tfa_code_expires DATETIME   NULL,
    last_login_at  DATETIME     NULL,
    deleted_at     DATETIME     NULL,
    created_at     DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_account_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_token (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id  INT UNSIGNED NOT NULL,
    kind        VARCHAR(20)  NOT NULL,
    token_hash  CHAR(64)     NOT NULL,
    expires_at  DATETIME     NOT NULL,
    used_at     DATETIME     NULL,
    created_at  DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY ix_token_hash (token_hash),
    KEY ix_token_account (account_id, kind),
    CONSTRAINT fk_token_account FOREIGN KEY (account_id) REFERENCES account (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_hit (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    bucket     VARCHAR(40)  NOT NULL,
    subject    VARCHAR(190) NOT NULL,
    created_at DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY ix_rate (bucket, subject, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS app_setting (
    skey       VARCHAR(120) NOT NULL,
    sval       TEXT         NOT NULL,
    is_secret  TINYINT(1)   NOT NULL DEFAULT 0,
    updated_at DATETIME     NOT NULL,
    updated_by VARCHAR(190) NOT NULL DEFAULT '',
    PRIMARY KEY (skey)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_log (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_email VARCHAR(190) NOT NULL DEFAULT '',
    action      VARCHAR(60)  NOT NULL,
    target_type VARCHAR(40)  NOT NULL DEFAULT '',
    target_id   INT UNSIGNED NULL,
    note        TEXT         NULL,
    created_at  DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY ix_audit_when (created_at),
    KEY ix_audit_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS remember_token (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id INT UNSIGNED NOT NULL,
    selector   CHAR(32)     NOT NULL,
    validator  CHAR(64)     NOT NULL,
    expires_at DATETIME     NOT NULL,
    used_at    DATETIME     NULL,
    created_at DATETIME     NOT NULL,
    user_agent VARCHAR(190) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    UNIQUE KEY uq_remember_selector (selector),
    KEY ix_remember_account (account_id),
    KEY ix_remember_expiry (expires_at),
    CONSTRAINT fk_remember_account FOREIGN KEY (account_id) REFERENCES account (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mail_log (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sent_to    VARCHAR(190) NOT NULL,
    subject    VARCHAR(190) NOT NULL,
    kind       VARCHAR(40)  NOT NULL DEFAULT 'other',
    result     VARCHAR(190) NOT NULL DEFAULT '',
    created_at DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY ix_mail_when (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
