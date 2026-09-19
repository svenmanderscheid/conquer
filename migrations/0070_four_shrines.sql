-- Four independent event shrines. A radius of27 is the nearest square whose full
-- 3x3 corner footprints are dry under data/world_terrain.json (SE radius24-26 is wet).
-- Reuse stable codes, IDs and ownership. Do not reset existing capture/garrison data.
INSERT INTO shrines(world_id,shrine_code,tier,coord_x,coord_y) VALUES
 (1,'SHRINE_FOREST','C',101,101),
 (1,'SHRINE_ICE','C',155,101),
 (1,'SHRINE_SAND','C',101,155),
 (1,'SHRINE_LAVA','C',155,155)
ON DUPLICATE KEY UPDATE coord_x=VALUES(coord_x),coord_y=VALUES(coord_y);

INSERT IGNORE INTO shrine_captures(shrine_id,alliance_id,captured_at,contested_until,secured_at,garrison_troops_json)
SELECT id,COALESCE(contesting_alliance_id,owner_alliance_id),COALESCE(contest_started_at,captured_at,UTC_TIMESTAMP()),
       IF(contesting_alliance_id IS NOT NULL,DATE_ADD(COALESCE(contest_started_at,UTC_TIMESTAMP()),INTERVAL 1 HOUR),NULL),
       IF(contesting_alliance_id IS NULL,COALESCE(secured_at,captured_at,UTC_TIMESTAMP()),NULL),'{}'
FROM shrines WHERE world_id=1 AND shrine_code IN ('SHRINE_FOREST','SHRINE_ICE','SHRINE_SAND','SHRINE_LAVA')
AND (owner_alliance_id IS NOT NULL OR contesting_alliance_id IS NOT NULL);

ALTER TABLE shrine_march_orders ADD COLUMN IF NOT EXISTS event_instance VARCHAR(96) NULL;
