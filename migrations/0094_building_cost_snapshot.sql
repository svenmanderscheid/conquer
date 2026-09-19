-- Null identifies pre-import jobs; cancellation uses their former formula costs.
ALTER TABLE building_queue ADD COLUMN IF NOT EXISTS cost_json LONGTEXT NULL;
INSERT IGNORE INTO city_buildings(city_id,building_code,level)
SELECT id,'watch_tower',1 FROM cities;
