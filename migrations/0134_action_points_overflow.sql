-- Refills and refunds may exceed the passive regeneration cap, including whole stacks.
ALTER TABLE players MODIFY COLUMN action_points BIGINT UNSIGNED NOT NULL DEFAULT 200;
