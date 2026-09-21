-- The complete world map is playable immediately. Regional land levels remain,
-- but middle and centre are no longer progression gates.
UPDATE world_land_zones
SET status = 'open',
    opened_at = COALESCE(opened_at, UTC_TIMESTAMP()),
    opened_reason = 'initial'
WHERE status <> 'open';
