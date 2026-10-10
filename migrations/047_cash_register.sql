CREATE TABLE IF NOT EXISTS register_sale (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  receipt_no VARCHAR(30) NULL,
  client_id INT UNSIGNED NULL,
  description VARCHAR(255) NOT NULL DEFAULT '',
  total_cents INT UNSIGNED NOT NULL,
  payment_method VARCHAR(20) NOT NULL,
  amount_received_cents INT UNSIGNED NULL,
  change_cents INT UNSIGNED NOT NULL DEFAULT 0,
  created_by VARCHAR(190) NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id), UNIQUE KEY ux_register_receipt (receipt_no),
  KEY ix_register_created (created_at), KEY ix_register_client (client_id),
  CONSTRAINT fk_register_client FOREIGN KEY (client_id) REFERENCES client(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS register_refund (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  sale_id INT UNSIGNED NOT NULL,
  amount_cents INT UNSIGNED NOT NULL,
  reason VARCHAR(255) NOT NULL,
  refund_method VARCHAR(20) NOT NULL,
  created_by VARCHAR(190) NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id), KEY ix_refund_sale (sale_id),
  CONSTRAINT fk_refund_sale FOREIGN KEY (sale_id) REFERENCES register_sale(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

