-- Waiting wounded and an explicitly paid, running treatment share a troop row.
ALTER TABLE hospital_wounded MODIFY COLUMN healing_ends_at DATETIME NULL DEFAULT NULL;
ALTER TABLE hospital_wounded ADD COLUMN IF NOT EXISTS healing_count INT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE hospital_wounded ADD COLUMN IF NOT EXISTS healing_started_at DATETIME NULL DEFAULT NULL;
ALTER TABLE hospital_wounded ADD COLUMN IF NOT EXISTS healing_batch VARCHAR(64) NULL DEFAULT NULL;
-- Preserve treatments already promised by the previous hospital implementation.
UPDATE hospital_wounded SET healing_count=count, healing_started_at=created_at,
    healing_batch=CONCAT('legacy_',city_id)
WHERE healing_ends_at IS NOT NULL AND healing_count=0 AND count>0;
