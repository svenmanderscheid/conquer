-- Migration 0045: Conquest event system
-- conquest_events        — one active event per world, phases unlock shrines progressively
-- conquest_contributions — per-player point accumulation within an event
-- phase: 1=C-shrines only, 2=C+B, 3=C+B+A, 4=all shrines

CREATE TABLE IF NOT EXISTS conquest_events (
    id         BIGINT UNSIGNED                        NOT NULL AUTO_INCREMENT,
    world_id   BIGINT UNSIGNED                        NOT NULL DEFAULT 1,
    phase      TINYINT UNSIGNED                       NOT NULL DEFAULT 1 COMMENT '1=C only,2=C+B,3=C+B+A,4=all',
    starts_at  DATETIME                               NOT NULL,
    ends_at    DATETIME                               NOT NULL,
    state      ENUM('upcoming','active','ended')      NOT NULL DEFAULT 'upcoming',
    created_at DATETIME                               NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_world_state (world_id, state)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS conquest_contributions (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id    BIGINT UNSIGNED NOT NULL,
    player_id   BIGINT UNSIGNED NOT NULL,
    alliance_id BIGINT UNSIGNED NOT NULL,
    points      INT UNSIGNED    NOT NULL DEFAULT 0,
    updated_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_event_player  (event_id, player_id),
    INDEX idx_event_alliance    (event_id, alliance_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
