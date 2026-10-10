
ALTER TABLE client
    ADD COLUMN country VARCHAR(60) NOT NULL DEFAULT '' AFTER postcode;

ALTER TABLE client_contact
    ADD COLUMN country VARCHAR(60) NOT NULL DEFAULT '' AFTER postcode;
