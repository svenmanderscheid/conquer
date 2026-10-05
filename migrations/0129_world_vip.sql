-- VIP progress, daily claims and unconsumed VIP items belong to one world.
-- Preserve legacy progress once, in the player's oldest surviving city/world.
-- Later worlds receive their own initial VIP 1 (200 points).
CREATE TABLE IF NOT EXISTS player_world_vip (
    player_id BIGINT UNSIGNED NOT NULL,
    world_id INT UNSIGNED NOT NULL,
    vip_points INT NOT NULL DEFAULT 200,
    vip_level SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    last_vip_login DATE NULL,
    PRIMARY KEY (player_id, world_id),
    KEY idx_world_vip (world_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO player_world_vip (player_id,world_id,vip_points,vip_level,last_vip_login)
SELECT c.player_id,c.world_id,
       IF(c.id=first_city.city_id,GREATEST(0,p.vip_points),200),
       IF(c.id=first_city.city_id,p.vip_level,1),
       IF(c.id=first_city.city_id,p.last_vip_login,NULL)
FROM cities c JOIN players p ON p.id=c.player_id
JOIN (SELECT player_id,MIN(id) AS city_id FROM cities GROUP BY player_id) first_city ON first_city.player_id=c.player_id;

-- Other item categories keep their existing account inventory (scope 0).
ALTER TABLE player_inventory ADD COLUMN IF NOT EXISTS world_id INT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE player_inventory ADD UNIQUE KEY IF NOT EXISTS uq_player_world_item (player_id,world_id,item_code);
ALTER TABLE player_inventory DROP INDEX IF EXISTS uq_player_item;

-- A resumed migration may encounter both legacy and already-scoped stacks.
-- Merge and remove legacy rows atomically, so retries cannot duplicate items.
START TRANSACTION;
INSERT INTO player_inventory(player_id,world_id,item_code,quantity,created_at,updated_at)
SELECT i.player_id,c.world_id,i.item_code,i.quantity,i.created_at,i.updated_at
FROM player_inventory i
JOIN (SELECT player_id,MIN(id) AS city_id FROM cities GROUP BY player_id) first_city ON first_city.player_id=i.player_id
JOIN cities c ON c.id=first_city.city_id
WHERE i.world_id=0 AND i.item_code IN (10106001,10106002,10106003,10206001,10206002,10206003,10206004)
ON DUPLICATE KEY UPDATE quantity=player_inventory.quantity+VALUES(quantity);

DELETE i FROM player_inventory i JOIN cities c ON c.player_id=i.player_id
WHERE i.world_id=0 AND i.item_code IN (10106001,10106002,10106003,10206001,10206002,10206003,10206004);

-- VIP shop weekly limits follow the same world as VIP eligibility.
INSERT INTO trading_shop_purchases(player_id,scope_world_id,shop_mode,rotation,offer_id,quantity)
SELECT purchases.player_id,c.world_id,purchases.shop_mode,purchases.rotation,purchases.offer_id,purchases.quantity
FROM trading_shop_purchases purchases
JOIN (SELECT player_id,MIN(id) AS city_id FROM cities GROUP BY player_id) first_city ON first_city.player_id=purchases.player_id
JOIN cities c ON c.id=first_city.city_id
WHERE purchases.shop_mode='vip' AND purchases.scope_world_id=0
ON DUPLICATE KEY UPDATE quantity=trading_shop_purchases.quantity+VALUES(quantity);

DELETE purchases FROM trading_shop_purchases purchases JOIN cities c ON c.player_id=purchases.player_id
WHERE purchases.shop_mode='vip' AND purchases.scope_world_id=0;
COMMIT;
