-- Purchase history and account cosmetics must survive removal of their original world/city.
SET @world_delete_ddl=IF((SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='theme_bundle_orders' AND CONSTRAINT_NAME='fk_theme_bundle_order_world')>0,'ALTER TABLE theme_bundle_orders DROP FOREIGN KEY fk_theme_bundle_order_world','SELECT 1');
PREPARE world_delete_stmt FROM @world_delete_ddl;
EXECUTE world_delete_stmt;
DEALLOCATE PREPARE world_delete_stmt;
SET @world_delete_ddl=IF((SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='theme_bundle_orders' AND CONSTRAINT_NAME='fk_theme_bundle_order_city')>0,'ALTER TABLE theme_bundle_orders DROP FOREIGN KEY fk_theme_bundle_order_city','SELECT 1');
PREPARE world_delete_stmt FROM @world_delete_ddl;
EXECUTE world_delete_stmt;
DEALLOCATE PREPARE world_delete_stmt;
ALTER TABLE theme_bundle_orders MODIFY world_id INT NULL, MODIFY city_id INT NULL;
ALTER TABLE theme_bundle_orders
    ADD CONSTRAINT fk_theme_bundle_order_world FOREIGN KEY (world_id) REFERENCES worlds(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_theme_bundle_order_city FOREIGN KEY (city_id) REFERENCES cities(id) ON DELETE SET NULL;
