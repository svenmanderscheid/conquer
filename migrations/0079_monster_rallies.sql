-- Existing city rallies retain their targets and behaviour.
ALTER TABLE rallies MODIFY target_player_id INT NULL;
ALTER TABLE rallies MODIFY target_city_id BIGINT NULL;
ALTER TABLE rallies ADD COLUMN IF NOT EXISTS target_kind VARCHAR(16) NOT NULL DEFAULT 'city';
ALTER TABLE rallies ADD COLUMN IF NOT EXISTS target_monster_id INT NULL;
