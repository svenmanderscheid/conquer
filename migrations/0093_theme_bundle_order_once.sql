-- Only one provider order may ever exist for a package. This closes the small
-- gap between two browser operation keys before either notification arrives.
ALTER TABLE theme_bundle_orders
    ADD UNIQUE INDEX IF NOT EXISTS uq_theme_bundle_once (player_id, skin_code, step);
