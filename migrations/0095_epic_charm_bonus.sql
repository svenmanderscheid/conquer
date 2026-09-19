-- Keep every persisted epic map charm and active effect aligned with the
-- canonical rarity values: normal 3 %, epic 6 %, legendary 10 %.

UPDATE map_charms
SET bonus_pct = 6.00
WHERE grade = 'epic';

UPDATE player_charms_active
SET bonus_pct = 6.00
WHERE grade = 'epic';
