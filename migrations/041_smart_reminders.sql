
ALTER TABLE reminder
    ADD COLUMN original_due DATE NULL AFTER due_date;
