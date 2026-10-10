
CREATE TABLE IF NOT EXISTS invoice (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    number      VARCHAR(40)  NOT NULL DEFAULT '',
    client_id   INT UNSIGNED NOT NULL,
    job_id      INT UNSIGNED NULL,
    booking_id  INT UNSIGNED NULL,
    status      VARCHAR(15)  NOT NULL DEFAULT 'draft',

    issued_on   DATE NULL,
    due_on      DATE NULL,

    subtotal_cents INT NOT NULL DEFAULT 0,
    tax_cents      INT NOT NULL DEFAULT 0,
    total_cents    INT NOT NULL DEFAULT 0,
    paid_cents     INT NOT NULL DEFAULT 0,

    tax_mode         VARCHAR(12)  NOT NULL DEFAULT 'none',
    tax_rate         DECIMAL(7,4) NOT NULL DEFAULT 0,
    tax_name         VARCHAR(40)  NOT NULL DEFAULT '',
    tax_number_label VARCHAR(40)  NOT NULL DEFAULT '',
    tax_number       VARCHAR(60)  NOT NULL DEFAULT '',
    currency         CHAR(3)      NOT NULL DEFAULT 'AUD',
    biz_name         VARCHAR(200) NOT NULL DEFAULT '',
    biz_address      VARCHAR(400) NOT NULL DEFAULT '',
    biz_contact      VARCHAR(200) NOT NULL DEFAULT '',
    bill_to_name     VARCHAR(200) NOT NULL DEFAULT '',
    bill_to_address  VARCHAR(400) NOT NULL DEFAULT '',
    bill_to_email    VARCHAR(190) NOT NULL DEFAULT '',
    pay_details      VARCHAR(400) NOT NULL DEFAULT '',
    footer           VARCHAR(400) NOT NULL DEFAULT '',

    notes       TEXT     NULL,
    created_at  DATETIME NOT NULL,
    updated_at  DATETIME NOT NULL,
    issued_by   VARCHAR(190) NOT NULL DEFAULT '',

    PRIMARY KEY (id),
    UNIQUE KEY ux_invoice_number (number),
    KEY ix_invoice_client (client_id, status),
    KEY ix_invoice_status (status, issued_on),
    KEY ix_invoice_job (job_id),
    CONSTRAINT fk_invoice_client FOREIGN KEY (client_id) REFERENCES client (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


ALTER TABLE invoice MODIFY COLUMN number VARCHAR(40) NULL DEFAULT NULL;


CREATE TABLE IF NOT EXISTS invoice_item (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    invoice_id  INT UNSIGNED NOT NULL,
    description VARCHAR(400) NOT NULL,
    qty         DECIMAL(10,2) NOT NULL DEFAULT 1.00,
    unit_cents  INT NOT NULL DEFAULT 0,
    line_cents  INT NOT NULL DEFAULT 0,
    taxable     TINYINT(1) NOT NULL DEFAULT 1,
    template_id INT UNSIGNED NULL,
    sort        INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY ix_item_invoice (invoice_id, sort),
    CONSTRAINT fk_item_invoice FOREIGN KEY (invoice_id) REFERENCES invoice (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS invoice_payment (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    invoice_id   INT UNSIGNED NOT NULL,
    method       VARCHAR(20)  NOT NULL DEFAULT 'transfer',
    amount_cents INT          NOT NULL,
    paid_on      DATE         NOT NULL,
    reference    VARCHAR(190) NOT NULL DEFAULT '',
    recorded_by  VARCHAR(190) NOT NULL DEFAULT '',
    created_at   DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY ix_pay_invoice (invoice_id),
    CONSTRAINT fk_pay_invoice FOREIGN KEY (invoice_id) REFERENCES invoice (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
