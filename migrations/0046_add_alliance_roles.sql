-- Migration 0046: Extend alliance_members with numeric role column and joined_at
-- NOTE: alliance_members already has a role ENUM and joined_at column from migration 0021.
-- This migration adds a numeric role2 column (for game-logic comparisons) alongside the
-- existing ENUM, and ensures leader promotion is correct.
-- role numeric mapping: 1=Member, 2=Veteran, 3=Officer, 4=Vice-Leader, 5=Leader

ALTER TABLE alliance_members
    ADD COLUMN IF NOT EXISTS role_level TINYINT UNSIGNED NOT NULL DEFAULT 1
        COMMENT '1=Member,2=Veteran,3=Officer,4=Vice-Leader,5=Leader' AFTER role;

-- Backfill role_level from the existing ENUM role column
UPDATE alliance_members SET role_level = 5 WHERE role = 'leader';
UPDATE alliance_members SET role_level = 4 WHERE role = 'vice_leader';
UPDATE alliance_members SET role_level = 3 WHERE role = 'officer';
UPDATE alliance_members SET role_level = 2 WHERE role = 'veteran';
UPDATE alliance_members SET role_level = 1 WHERE role = 'member';

-- Ensure the alliance leader has role = 'leader' and role_level = 5
UPDATE alliance_members am
JOIN alliances a ON a.id = am.alliance_id
SET am.role       = 'leader',
    am.role_level = 5
WHERE am.player_id = a.leader_id;
