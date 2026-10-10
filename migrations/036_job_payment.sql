
CREATE TABLE IF NOT EXISTS job_payment (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  job_id        INT UNSIGNED NOT NULL,
  client_id     INT UNSIGNED NOT NULL,
  amount_cents  INT NOT NULL DEFAULT 0,
  method        VARCHAR(30) NOT NULL DEFAULT 'platform',
  platform      VARCHAR(80) NOT NULL DEFAULT '',
  paid_on       DATE NOT NULL,
  reference     VARCHAR(120) NOT NULL DEFAULT '',
  note          VARCHAR(255) NOT NULL DEFAULT '',
  recorded_by   VARCHAR(190) NOT NULL DEFAULT '',
  created_at    DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY job_payment_job (job_id),
  KEY job_payment_client (client_id),
  KEY job_payment_paid_on (paid_on),
  CONSTRAINT fk_job_payment_job    FOREIGN KEY (job_id)    REFERENCES job (id)    ON DELETE CASCADE,
  CONSTRAINT fk_job_payment_client FOREIGN KEY (client_id) REFERENCES client (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
