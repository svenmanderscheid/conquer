-- Seed 8 shrines for World 1, one per sector.
--
-- Map: 256×256 tiles, divided into 4 columns × 2 rows = 8 sectors.
-- Each sector is 64 tiles wide × 128 tiles tall.
-- Shrine is placed near the sector centre with a small deterministic offset
-- to avoid a perfectly grid-aligned look.
--
-- Sector layout:
--   Col:  0(x 0-63)   1(x 64-127)  2(x 128-191)  3(x 192-255)
--   Row 0 (y  0-127): SH_A         SH_B           SH_C          SH_D
--   Row 1 (y128-255): SH_E         SH_F           SH_G          SH_H
--
-- Tier distribution: 2×S, 2×A, 2×B, 2×C  (S = most strategic)
-- S shrines placed in opposing quadrants (top-right, bottom-left).

INSERT IGNORE INTO shrines
    (world_id, shrine_code, tier, coord_x, coord_y)
VALUES
    -- Row 0 (north half)
    (1, 'SH_A', 'B',  28,  58),   -- sector 0: top-left,     offset from (32,64)
    (1, 'SH_B', 'S',  99,  61),   -- sector 1: top-mid-left  offset from (96,64)
    (1, 'SH_C', 'A', 163,  67),   -- sector 2: top-mid-right offset from (160,64)
    (1, 'SH_D', 'C', 220,  59),   -- sector 3: top-right     offset from (224,64)

    -- Row 1 (south half)
    (1, 'SH_E', 'S',  35, 195),   -- sector 4: bottom-left   offset from (32,192)
    (1, 'SH_F', 'C',  94, 188),   -- sector 5: bottom-mid-l  offset from (96,192)
    (1, 'SH_G', 'A', 157, 194),   -- sector 6: bottom-mid-r  offset from (160,192)
    (1, 'SH_H', 'B', 221, 191);   -- sector 7: bottom-right  offset from (224,192)
