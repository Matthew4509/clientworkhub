
CREATE TABLE IF NOT EXISTS workday (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    label      VARCHAR(60)  NOT NULL DEFAULT '',
    weekdays   VARCHAR(20)  NOT NULL DEFAULT '',
    starts     TIME         NOT NULL,
    ends       TIME         NOT NULL,
    sort       INT          NOT NULL DEFAULT 100,
    created_at DATETIME     NOT NULL,
    updated_at DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY ix_workday_sort (sort, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS workday_break (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    workday_id INT UNSIGNED NOT NULL,
    label      VARCHAR(60)  NOT NULL DEFAULT '',
    starts     TIME         NOT NULL,
    ends       TIME         NOT NULL,
    flexible   TINYINT(1)   NOT NULL DEFAULT 0,
    sort       INT          NOT NULL DEFAULT 100,
    PRIMARY KEY (id),
    KEY ix_break_day (workday_id, starts),
    CONSTRAINT fk_break_workday FOREIGN KEY (workday_id)
        REFERENCES workday (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
