-- Account-wide healing report choice. Existing players keep receiving reports.
ALTER TABLE kingdom_profiles
 ADD COLUMN IF NOT EXISTS report_heal_complete TINYINT(1) NOT NULL DEFAULT 1;
