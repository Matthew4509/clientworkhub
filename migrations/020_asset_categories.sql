
CREATE TABLE IF NOT EXISTS asset_category (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name       VARCHAR(80)  NOT NULL,
    sort       INT          NOT NULL DEFAULT 100,
    created_at DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY ux_asset_category_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE client_asset
    ADD COLUMN category_id INT UNSIGNED NULL DEFAULT NULL AFTER type,
    ADD KEY ix_asset_category (category_id),
    ADD CONSTRAINT fk_asset_category FOREIGN KEY (category_id)
        REFERENCES asset_category (id) ON DELETE SET NULL;
