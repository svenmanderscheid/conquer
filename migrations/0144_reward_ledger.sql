-- Reward receipts commit with inventory, relic or resource mutations. No player/world foreign keys:
-- deleting a world must not destroy the historical receipt.
CREATE TABLE IF NOT EXISTS reward_grant_ledger (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    player_id INT UNSIGNED NOT NULL,
    world_id INT UNSIGNED NOT NULL DEFAULT 0,
    inventory_world_id INT UNSIGNED NOT NULL DEFAULT 0,
    item_code INT UNSIGNED NOT NULL,
    reward_kind VARCHAR(16) NOT NULL DEFAULT 'item',
    resource_code VARCHAR(16) NOT NULL DEFAULT '',
    quantity BIGINT UNSIGNED NOT NULL,
    source_type VARCHAR(40) NOT NULL,
    source_key VARCHAR(120) NOT NULL DEFAULT '',
    source_reference VARCHAR(160) NOT NULL DEFAULT '',
    rule_revision VARCHAR(100) NOT NULL DEFAULT '',
    operation_id VARCHAR(100) NOT NULL DEFAULT '',
    result_json TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY reward_grants_world_time (world_id, created_at, id),
    KEY reward_grants_player_time (player_id, created_at, id),
    KEY reward_grants_item_time (item_code, created_at, id),
    KEY reward_grants_source_time (source_type, created_at, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
