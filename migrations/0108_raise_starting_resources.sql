-- New cities start with enough resources for a smoother early-game progression.
-- Existing city balances are deliberately left unchanged.
ALTER TABLE cities
    ALTER COLUMN food SET DEFAULT 100000,
    ALTER COLUMN lumber SET DEFAULT 100000,
    ALTER COLUMN stone SET DEFAULT 100000,
    ALTER COLUMN gold SET DEFAULT 100000;
