
ALTER TABLE client_link
    ADD COLUMN token_hash CHAR(64) NOT NULL DEFAULT '' AFTER token,
    ADD COLUMN token_enc  TEXT     NULL              AFTER token_hash;

UPDATE client_link SET token_hash = SHA2(token, 256) WHERE token_hash = '';

ALTER TABLE onboarding_response
    ADD COLUMN token_hash CHAR(64) NOT NULL DEFAULT '' AFTER token,
    ADD COLUMN token_enc  TEXT     NULL              AFTER token_hash;

UPDATE onboarding_response SET token_hash = SHA2(token, 256) WHERE token_hash = '';

ALTER TABLE client_link         DROP COLUMN token;
ALTER TABLE onboarding_response DROP COLUMN token;

ALTER TABLE client_link         ADD KEY ix_link_hash (token_hash);
ALTER TABLE onboarding_response ADD KEY ix_onboard_hash (token_hash);
