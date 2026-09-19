CREATE TABLE IF NOT EXISTS reward_world_overrides (
    world_id INT UNSIGNED NOT NULL,
    source_type VARCHAR(24) NOT NULL,
    source_key VARCHAR(80) NOT NULL,
    config_json JSON NULL,
    revision INT UNSIGNED NOT NULL DEFAULT 1,
    updated_by INT UNSIGNED NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (world_id, source_type, source_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reward_rule_revisions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    scope_world_id INT UNSIGNED NOT NULL DEFAULT 0,
    source_type VARCHAR(24) NOT NULL,
    source_key VARCHAR(80) NOT NULL,
    revision INT UNSIGNED NOT NULL,
    config_json JSON NULL,
    updated_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_reward_revision (scope_world_id, source_type, source_key, revision)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO reward_rule_revisions(scope_world_id,source_type,source_key,revision,config_json,updated_by,created_at)
SELECT 0,source_type,source_key,revision,config_json,updated_by,updated_at FROM reward_overrides;
