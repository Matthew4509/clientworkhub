
ALTER TABLE client
    ADD COLUMN vip TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
    ADD KEY ix_client_vip (vip);
