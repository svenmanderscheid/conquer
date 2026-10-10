ALTER TABLE admin_users MODIFY COLUMN role ENUM('superadmin','moderator','support') NOT NULL DEFAULT 'moderator';
ALTER TABLE bug_reports MODIFY COLUMN report_type ENUM('bug','idea','support') NOT NULL DEFAULT 'bug';
ALTER TABLE bug_reports MODIFY COLUMN status ENUM('new','in_progress','waiting','resolved','closed') NOT NULL DEFAULT 'new';
ALTER TABLE community_reports MODIFY COLUMN status ENUM('new','reviewing','waiting','resolved','dismissed') NOT NULL DEFAULT 'new';

-- Source reports remain authoritative and keep their existing identifiers and links.
CREATE TABLE IF NOT EXISTS support_cases (
 source_type ENUM('bug','content') NOT NULL,
 source_id BIGINT UNSIGNED NOT NULL,
 assigned_to BIGINT UNSIGNED NULL,
 priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
 revision INT UNSIGNED NOT NULL DEFAULT 0,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(source_type,source_id),
 INDEX assigned_cases(assigned_to,updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_case_messages (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 source_type ENUM('bug','content') NOT NULL,
 source_id BIGINT UNSIGNED NOT NULL,
 visibility ENUM('public','internal','history') NOT NULL,
 author_type ENUM('admin','player') NOT NULL,
 author_id BIGINT UNSIGNED NOT NULL,
 body TEXT NOT NULL,
 event_json JSON NULL,
 operation_key VARCHAR(64) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY message_receipt(author_type,author_id,operation_key),
 INDEX case_conversation(source_type,source_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
