-- Migration 0032: Player item inventory
-- player_inventory — one row per player+item_code combination
-- item_code conventions: 10103xxx = speedups, 10102xxx = boosts, 1010xxxx = resources

CREATE TABLE IF NOT EXISTS player_inventory (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    player_id  BIGINT UNSIGNED NOT NULL,
    item_code  INT UNSIGNED    NOT NULL,
    quantity   INT UNSIGNED    NOT NULL DEFAULT 0,
    created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_player_item (player_id, item_code),
    INDEX idx_player (player_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
