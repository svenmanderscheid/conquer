-- Account-wide preferences. Existing players continue receiving completion reports.
ALTER TABLE kingdom_profiles
 ADD COLUMN IF NOT EXISTS report_build_complete TINYINT(1) NOT NULL DEFAULT 1,
 ADD COLUMN IF NOT EXISTS report_research_complete TINYINT(1) NOT NULL DEFAULT 1,
 ADD COLUMN IF NOT EXISTS report_train_complete TINYINT(1) NOT NULL DEFAULT 1;
