-- [S 功能規格 E14/G14/E24/G24；D 資料表建議 C17:D18；E-32]
-- 擴充階段三引用保護表，不重建既有資料；slug 由穩定 UUID 回填。
-- @statement
ALTER TABLE routes
    ADD slug VARCHAR(160) CHARACTER SET ascii NULL,
    ADD theme VARCHAR(255) NOT NULL DEFAULT '',
    ADD summary TEXT NOT NULL DEFAULT '',
    ADD duration_minutes INT UNSIGNED NULL,
    ADD sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    ADD published_at DATETIME NULL,
    ADD created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ADD updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ADD deleted_at DATETIME NULL;
-- @statement
UPDATE routes SET slug=id,published_at=CASE WHEN status='published' THEN UTC_TIMESTAMP() ELSE NULL END;
-- @statement
ALTER TABLE routes MODIFY slug VARCHAR(160) CHARACTER SET ascii NOT NULL,
    ADD UNIQUE KEY route_slug (slug),
    ADD INDEX route_public (deleted_at,status,sort_order,id);
-- @statement
ALTER TABLE route_attractions
    ADD suggested_time TIME NULL,
    ADD note VARCHAR(2000) NOT NULL DEFAULT '',
    ADD UNIQUE KEY route_stop_order (route_id,sort_order);
