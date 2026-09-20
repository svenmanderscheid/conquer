ALTER TABLE bug_reports
 ADD COLUMN report_type ENUM('bug','idea') NOT NULL DEFAULT 'bug' AFTER world_id,
 ADD COLUMN screenshot MEDIUMBLOB NULL AFTER client_context,
 ADD COLUMN screenshot_mime VARCHAR(32) NOT NULL DEFAULT '' AFTER screenshot;
