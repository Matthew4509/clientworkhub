
ALTER TABLE client
    ADD COLUMN address  VARCHAR(255) NOT NULL DEFAULT '' AFTER phone,
    ADD COLUMN suburb   VARCHAR(120) NOT NULL DEFAULT '' AFTER address,
    ADD COLUMN state    VARCHAR(60)  NOT NULL DEFAULT '' AFTER suburb,
    ADD COLUMN postcode VARCHAR(20)  NOT NULL DEFAULT '' AFTER state;

ALTER TABLE client_contact
    ADD COLUMN is_billing  TINYINT(1)   NOT NULL DEFAULT 0 AFTER has_approval,
    ADD COLUMN wants_email TINYINT(1)   NOT NULL DEFAULT 1 AFTER is_billing,
    ADD COLUMN wants_phone TINYINT(1)   NOT NULL DEFAULT 1 AFTER wants_email,
    ADD COLUMN wants_sms   TINYINT(1)   NOT NULL DEFAULT 1 AFTER wants_phone,
    ADD COLUMN phone_kind  VARCHAR(20)  NOT NULL DEFAULT 'mobile' AFTER phone,
    ADD COLUMN address     VARCHAR(255) NOT NULL DEFAULT '' AFTER phone_kind,
    ADD COLUMN suburb      VARCHAR(120) NOT NULL DEFAULT '' AFTER address,
    ADD COLUMN state       VARCHAR(60)  NOT NULL DEFAULT '' AFTER suburb,
    ADD COLUMN postcode    VARCHAR(20)  NOT NULL DEFAULT '' AFTER state;
