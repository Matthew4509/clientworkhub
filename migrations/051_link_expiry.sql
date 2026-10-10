
ALTER TABLE onboarding_response
  ADD COLUMN expires_at DATETIME NULL DEFAULT NULL AFTER created_at;
