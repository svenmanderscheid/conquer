-- Historical installations have either details/ip or details_json/ip_address.
-- Keep legacy columns and entries; normalize the columns used by AdminAuth.
ALTER TABLE admin_audit_log ADD COLUMN IF NOT EXISTS details JSON NULL;
ALTER TABLE admin_audit_log ADD COLUMN IF NOT EXISTS ip VARCHAR(45) NULL;
SET @admin_audit_ddl=IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='admin_audit_log' AND column_name='details_json')>0,'UPDATE admin_audit_log SET details=IF(JSON_VALID(details_json),details_json,JSON_OBJECT(''legacy'',details_json)) WHERE details IS NULL AND details_json IS NOT NULL','SELECT 1');
PREPARE admin_audit_repair FROM @admin_audit_ddl;
EXECUTE admin_audit_repair;
DEALLOCATE PREPARE admin_audit_repair;
SET @admin_audit_ddl=IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='admin_audit_log' AND column_name='ip_address')>0,'UPDATE admin_audit_log SET ip=ip_address WHERE ip IS NULL','SELECT 1');
PREPARE admin_audit_repair FROM @admin_audit_ddl;
EXECUTE admin_audit_repair;
DEALLOCATE PREPARE admin_audit_repair;
