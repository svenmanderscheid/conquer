-- Resize world 1 from 1024×1024 to 256×256.
-- Reason: hobby project will never have enough players to populate a 1024 map.
-- map_size drives spawn range in OAuth.php and MAP_SIZE in the JS renderer.
UPDATE worlds SET map_size = 256 WHERE id = 1;
