CREATE TABLE IF NOT EXISTS work_access (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  username VARCHAR(80) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  token_enc TEXT NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  screen_view VARCHAR(20) NOT NULL DEFAULT 'today',
  expires_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id), UNIQUE KEY ux_work_access_token (token_hash),
  KEY ix_work_access_status (status, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS work_access_item (
  access_id INT UNSIGNED NOT NULL,
  item_type VARCHAR(20) NOT NULL,
  item_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (access_id, item_type, item_id),
  KEY ix_work_access_item (item_type, item_id),
  CONSTRAINT fk_work_access_item_access FOREIGN KEY (access_id) REFERENCES work_access(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

