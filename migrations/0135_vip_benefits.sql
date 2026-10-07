-- VIP 1 starts at zero points; preserve all previously earned balances.
ALTER TABLE player_world_vip ALTER COLUMN vip_points SET DEFAULT 0;
ALTER TABLE players ALTER COLUMN vip_points SET DEFAULT 0;

-- Refresh only the cached level. Runtime status derives it from the same thresholds.
UPDATE player_world_vip SET vip_level = CASE
    WHEN vip_points >= 12000000 THEN 20 WHEN vip_points >= 8000000 THEN 19
    WHEN vip_points >= 4000000 THEN 18 WHEN vip_points >= 3000000 THEN 17
    WHEN vip_points >= 2000000 THEN 16 WHEN vip_points >= 1500000 THEN 15
    WHEN vip_points >= 1000000 THEN 14 WHEN vip_points >= 500000 THEN 13
    WHEN vip_points >= 250000 THEN 12 WHEN vip_points >= 200000 THEN 11
    WHEN vip_points >= 150000 THEN 10 WHEN vip_points >= 100000 THEN 9
    WHEN vip_points >= 50000 THEN 8 WHEN vip_points >= 20000 THEN 7
    WHEN vip_points >= 10000 THEN 6 WHEN vip_points >= 5000 THEN 5
    WHEN vip_points >= 1000 THEN 4 WHEN vip_points >= 500 THEN 3
    WHEN vip_points >= 200 THEN 2 ELSE 1 END;
