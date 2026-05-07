-- Relocate all cities whose coordinates fall outside the new 256×256 map bounds.
-- Places them near the center (128, 128) with a small spread per city id.
-- This is a one-time dev fix; production worlds start fresh.
UPDATE cities
SET
    coord_x = 100 + (id * 13) % 56,
    coord_y = 100 + (id * 17) % 56
WHERE world_id = 1
  AND (coord_x > 255 OR coord_y > 255 OR coord_x < 0 OR coord_y < 0);
