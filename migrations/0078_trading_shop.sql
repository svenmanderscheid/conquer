-- Caravan limits are world-specific; weekly VIP limits are account-wide (scope 0).
CREATE TABLE IF NOT EXISTS trading_shop_purchases (
    player_id BIGINT UNSIGNED NOT NULL,
    scope_world_id INT NOT NULL,
    shop_mode VARCHAR(12) NOT NULL,
    rotation VARCHAR(40) NOT NULL,
    offer_id VARCHAR(40) NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (player_id,scope_world_id,shop_mode,rotation,offer_id),
    CONSTRAINT trading_shop_mode CHECK (shop_mode IN ('caravan','vip'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
