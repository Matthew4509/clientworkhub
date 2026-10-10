
ALTER TABLE onboarding_response
  ADD COLUMN signature   MEDIUMTEXT   NULL              AFTER completed_at,
  ADD COLUMN signed_name VARCHAR(120) NOT NULL DEFAULT '' AFTER signature,
  ADD COLUMN signed_at   DATETIME     NULL              AFTER signed_name;
