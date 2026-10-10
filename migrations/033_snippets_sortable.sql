
ALTER TABLE work_snippet
    ADD COLUMN sort INT UNSIGNED NOT NULL DEFAULT 0 AFTER archived;

SET @i := 0;
UPDATE work_snippet
   SET sort = (@i := @i + 10)
 ORDER BY archived, uses DESC, body;

ALTER TABLE work_snippet
    ADD KEY ix_snippet_order (archived, sort);
