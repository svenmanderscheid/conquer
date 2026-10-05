-- Existing wounds retain ordinary healing. Only newly recorded monster wounds
-- receive the scoped Hunter healing bonus; active batch end times stay intact.
ALTER TABLE hospital_wounded ADD COLUMN IF NOT EXISTS monster_count INT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE hospital_wounded ADD COLUMN IF NOT EXISTS monster_healing_count INT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE rally_participants ADD COLUMN IF NOT EXISTS ap_cost_paid INT UNSIGNED NOT NULL DEFAULT 0;
