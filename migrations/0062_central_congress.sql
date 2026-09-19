-- Central Congress: preserve an existing central shrine ID, ownership and garrisons.
-- This landmark alone intentionally occupies the great lake. No terrain rule is changed.
SET @congress_id = (SELECT id FROM shrines WHERE world_id=1 AND (shrine_code='CONGRESS' OR (coord_x=128 AND coord_y=128)) ORDER BY (shrine_code='CONGRESS') DESC,id LIMIT 1);
UPDATE shrines SET shrine_code='CONGRESS',coord_x=128,coord_y=128 WHERE id=@congress_id;
INSERT INTO shrines(world_id,shrine_code,tier,coord_x,coord_y)
SELECT 1,'CONGRESS','S',128,128 WHERE NOT EXISTS(SELECT 1 FROM shrines WHERE world_id=1 AND shrine_code='CONGRESS');

-- Import legacy ownership only when the newer capture record does not exist.
INSERT IGNORE INTO shrine_captures(shrine_id,alliance_id,captured_at,contested_until,secured_at,garrison_troops_json)
SELECT id,COALESCE(contesting_alliance_id,owner_alliance_id),COALESCE(contest_started_at,captured_at,UTC_TIMESTAMP()),
       IF(contesting_alliance_id IS NOT NULL,DATE_ADD(COALESCE(contest_started_at,UTC_TIMESTAMP()),INTERVAL 1 HOUR),NULL),
       IF(contesting_alliance_id IS NULL,COALESCE(secured_at,captured_at,UTC_TIMESTAMP()),NULL),'{}'
FROM shrines WHERE shrine_code='CONGRESS' AND (owner_alliance_id IS NOT NULL OR contesting_alliance_id IS NOT NULL);

CREATE TABLE IF NOT EXISTS shrine_march_orders (
    march_id INT NOT NULL PRIMARY KEY,
    alliance_id BIGINT UNSIGNED NOT NULL,
    buffs_json TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE shrine_garrisons ADD COLUMN IF NOT EXISTS alliance_id BIGINT UNSIGNED NULL;
ALTER TABLE shrine_garrisons ADD COLUMN IF NOT EXISTS buffs_json TEXT NOT NULL DEFAULT '{}';
ALTER TABLE shrine_garrisons ADD COLUMN IF NOT EXISTS travel_seconds INT UNSIGNED NOT NULL DEFAULT 5;
UPDATE shrine_garrisons g JOIN shrine_captures c ON c.shrine_id=g.shrine_id SET g.alliance_id=c.alliance_id WHERE g.alliance_id IS NULL;
