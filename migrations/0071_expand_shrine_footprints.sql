-- Shrines now occupy anchor[-2,-2,+3,+3] (6x6); Congress remains7x7.
-- Radius27 intersects southeast water. Radius28 is the smallest dry symmetric square.
-- Apply under WorldRules::combatLock() after an active-march audit.
-- Preserve shrine IDs, ownership, garrisons and every non-coordinate column.
-- A failed CHECK stops the migration rather than silently recording a skipped move.
DROP TEMPORARY TABLE IF EXISTS shrine_expansion_guard;
CREATE TEMPORARY TABLE shrine_expansion_guard (
 ready TINYINT NOT NULL,
 CONSTRAINT shrine_expansion_waits_for_active_marches CHECK (ready=1)
);
INSERT INTO shrine_expansion_guard(ready) SELECT NOT EXISTS(
 SELECT 1 FROM marches m JOIN shrines s ON s.world_id=m.world_id
 AND ((m.target_type=4 AND m.target_id=s.id) OR (m.target_x=s.coord_x AND m.target_y=s.coord_y))
 WHERE s.world_id=1 AND s.shrine_code IN ('SHRINE_FOREST','SHRINE_ICE','SHRINE_SAND','SHRINE_LAVA')
 AND m.state IN ('marching','resolving','returning')
 AND (s.coord_x<>CASE WHEN s.shrine_code IN ('SHRINE_FOREST','SHRINE_SAND') THEN 100 ELSE 156 END
      OR s.coord_y<>CASE WHEN s.shrine_code IN ('SHRINE_FOREST','SHRINE_ICE') THEN 100 ELSE 156 END)
);
UPDATE shrines SET
 coord_x=CASE WHEN shrine_code IN ('SHRINE_FOREST','SHRINE_SAND') THEN 100 ELSE 156 END,
 coord_y=CASE WHEN shrine_code IN ('SHRINE_FOREST','SHRINE_ICE') THEN 100 ELSE 156 END
WHERE world_id=1 AND shrine_code IN ('SHRINE_FOREST','SHRINE_ICE','SHRINE_SAND','SHRINE_LAVA');
DROP TEMPORARY TABLE shrine_expansion_guard;
