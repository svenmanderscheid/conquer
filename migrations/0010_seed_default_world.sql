-- Seed the default development world.
-- World 1 is used for local dev and Sprint 1 testing.
-- Production worlds are created via the admin panel.
INSERT INTO worlds (id, name, slug, status, speed_factor, gather_factor, haul_factor, map_size)
VALUES (1, 'World 1', 'w1', 'open', 1.0, 1.0, 1.0, 1024)
ON DUPLICATE KEY UPDATE id = id;  -- no-op if already present
