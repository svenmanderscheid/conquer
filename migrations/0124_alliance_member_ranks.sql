-- Keep the existing role enum authoritative for R1-R5.
-- Repair leadership only when the designated leader already belongs to this
-- exact alliance and world. Missing leaders require deliberate operator repair;
-- this migration never inserts memberships or guesses another leader.
UPDATE alliance_members m
JOIN alliances a ON a.id = m.alliance_id AND a.world_id = m.world_id
JOIN alliance_members designated ON designated.alliance_id = a.id
    AND designated.world_id = a.world_id AND designated.player_id = a.leader_id
SET m.role = CASE
    WHEN m.player_id = a.leader_id THEN 'leader'
    WHEN m.role = 'leader' THEN 'member'
    ELSE m.role
END
WHERE m.player_id = a.leader_id OR m.role = 'leader';

-- Idempotent reconciliation of numbers, including stale values left by earlier
-- creation, promotion or transfer paths. Unknown enum values grant no rank.
UPDATE alliance_members
SET role_level = CASE role
    WHEN 'member' THEN 1
    WHEN 'veteran' THEN 2
    WHEN 'officer' THEN 3
    WHEN 'vice_leader' THEN 4
    WHEN 'leader' THEN 5
    ELSE 0
END;
