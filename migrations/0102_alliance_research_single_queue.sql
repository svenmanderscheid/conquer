ALTER TABLE alliance_research_queue
    ADD COLUMN active_slot TINYINT GENERATED ALWAYS AS (CASE WHEN is_processed = 0 THEN 1 ELSE NULL END) STORED,
    ADD UNIQUE KEY uq_alliance_research_active (alliance_id, active_slot);
