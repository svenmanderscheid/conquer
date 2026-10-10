ALTER TABLE world_entry_settings
    ADD COLUMN IF NOT EXISTS spawn_until DATETIME NULL DEFAULT NULL AFTER spawn_radius;
