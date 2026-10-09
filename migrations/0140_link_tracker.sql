CREATE TABLE IF NOT EXISTS link_tracker_links (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(64) NOT NULL,
    label VARCHAR(120) NOT NULL,
    destination VARCHAR(2048) NOT NULL DEFAULT '',
    kind ENUM('campaign','website') NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    revision INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tracker_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Aggregates only: no click history, identifiers, IPs, full URLs or user agents.
CREATE TABLE IF NOT EXISTS link_tracker_daily (
    link_id INT UNSIGNED NOT NULL,
    day DATE NOT NULL,
    device ENUM('desktop','mobile','tablet','other') NOT NULL,
    source VARCHAR(16) NOT NULL,
    clicks BIGINT UNSIGNED NOT NULL DEFAULT 0,
    last_click_at DATETIME NOT NULL,
    PRIMARY KEY (link_id,day,device,source),
    INDEX idx_tracker_day (day,link_id),
    CONSTRAINT fk_tracker_link FOREIGN KEY (link_id) REFERENCES link_tracker_links(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO link_tracker_links(slug,label,kind) VALUES
('site-login','login','website'),
('site-register-hero','register_hero','website'),
('site-register-browser','register_browser','website'),
('site-register-access','register_access','website'),
('site-register-guide','register_guide','website'),
('site-waitlist','waitlist','website'),
('site-discord','discord','website'),
('site-contact','contact','website'),
('site-privacy','privacy','website'),
('site-deletion','deletion','website'),
('site-gallery-city','gallery_city','website'),
('site-gallery-world','gallery_world','website'),
('site-gallery-army','gallery_army','website');
