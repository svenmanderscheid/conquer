-- Protection belongs to one city/world while the unconsumed item is account-wide.
ALTER TABLE cities ADD COLUMN IF NOT EXISTS anti_spy_until TIMESTAMP NULL DEFAULT NULL;
