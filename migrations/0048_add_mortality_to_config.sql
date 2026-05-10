-- Migration 0048: Add wounded_json to marches for mortality/hospital system
-- wounded_json stores { "troop_code": count } of troops that survived as wounded
-- and are returned to the hospital after a battle (mortality rate default: 0.3 = 30% die,
-- 70% wounded and recoverable).

ALTER TABLE marches
    ADD COLUMN IF NOT EXISTS wounded_json TEXT NULL
        COMMENT 'Wounded troops returned to hospital: {"troop_code": count}'
        AFTER haul_json;
