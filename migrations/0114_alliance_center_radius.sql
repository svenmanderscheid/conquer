-- Give established alliances enough contiguous territory for their members.
-- JSON_SET updates configured worlds; worlds without a row use the PHP default.
UPDATE world_spawn_settings
SET settings_json = JSON_SET(settings_json, '$.alliance_center_radius', 24),
    updated_at = CURRENT_TIMESTAMP;
