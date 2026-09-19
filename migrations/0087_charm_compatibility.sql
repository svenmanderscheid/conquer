-- Reconcile development databases that applied an early 0085 revision before
-- chronological charm activation and legacy grade snapshots were added.

ALTER TABLE player_charms_active ADD COLUMN IF NOT EXISTS source_march_id BIGINT NULL AFTER source_map_charm_id;
ALTER TABLE marches ADD INDEX IF NOT EXISTS idx_charm_collect_race (world_id, march_type, target_id, state, arrival_time, id);

UPDATE map_charms SET
    bonus_pct=CASE grade WHEN 'legendary' THEN 10.00 WHEN 'epic' THEN 6.00 ELSE 3.00 END,
    effect_duration_seconds=CASE grade WHEN 'legendary' THEN 14400 WHEN 'epic' THEN 7200 ELSE 1800 END
WHERE source_receipt_id IS NULL;
