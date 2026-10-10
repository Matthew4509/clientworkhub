ALTER TABLE work_access
  ADD COLUMN auth_required TINYINT(1) NOT NULL DEFAULT 1 AFTER screen_view,
  ADD COLUMN public_heading VARCHAR(120) NOT NULL DEFAULT '' AFTER auth_required,
  ADD COLUMN show_first_name TINYINT(1) NOT NULL DEFAULT 0 AFTER public_heading,
  ADD COLUMN show_job_name TINYINT(1) NOT NULL DEFAULT 0 AFTER show_first_name,
  ADD COLUMN show_task_name TINYINT(1) NOT NULL DEFAULT 1 AFTER show_job_name,
  ADD COLUMN show_estimate TINYINT(1) NOT NULL DEFAULT 1 AFTER show_task_name,
  ADD COLUMN show_status TINYINT(1) NOT NULL DEFAULT 1 AFTER show_estimate;
