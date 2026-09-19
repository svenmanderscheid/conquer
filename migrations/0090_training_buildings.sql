-- Existing cities retain their training-building progress; no troops are removed.
INSERT IGNORE INTO city_buildings(city_id,building_code,level)
SELECT c.id,'archery_range',COALESCE(b.level,1) FROM cities c LEFT JOIN city_buildings b ON b.city_id=c.id AND b.building_code='barrack';
INSERT IGNORE INTO city_buildings(city_id,building_code,level)
SELECT c.id,'stable',COALESCE(b.level,1) FROM cities c LEFT JOIN city_buildings b ON b.city_id=c.id AND b.building_code='barrack';
UPDATE troop_queue SET barrack_slot=FLOOR(troop_code/100000)%10 WHERE troop_code BETWEEN 50100101 AND 50301001;
ALTER TABLE troop_queue ADD COLUMN IF NOT EXISTS cost_json JSON NULL;
