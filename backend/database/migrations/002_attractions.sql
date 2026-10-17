-- [S：功能規格 6/13/19/20；D：資料表建議；E-18] 階段三新增，不改已套用 migration。
-- @statement
CREATE TABLE IF NOT EXISTS media_assets (
    id CHAR(36) CHARACTER SET ascii PRIMARY KEY,
    storage_key VARCHAR(80) CHARACTER SET ascii NOT NULL UNIQUE,
    mime_type VARCHAR(40) CHARACTER SET ascii NOT NULL,
    width INT UNSIGNED NOT NULL,
    height INT UNSIGNED NOT NULL,
    byte_size INT UNSIGNED NOT NULL,
    alt VARCHAR(255) NOT NULL,
    source VARCHAR(255) NOT NULL DEFAULT '',
    license VARCHAR(255) NOT NULL DEFAULT '',
    module VARCHAR(40) CHARACTER SET ascii NOT NULL,
    uploaded_by CHAR(36) CHARACTER SET ascii NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_media_admin FOREIGN KEY (uploaded_by) REFERENCES admin_users(id),
    INDEX media_module_created (module,created_at,id)
) ENGINE=InnoDB;
-- @statement
CREATE TABLE IF NOT EXISTS attractions (
    id CHAR(36) CHARACTER SET ascii PRIMARY KEY,
    slug VARCHAR(120) CHARACTER SET ascii NOT NULL UNIQUE,
    legacy_id SMALLINT UNSIGNED NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    category ENUM('景點','美食','在地店家') NOT NULL,
    district ENUM('北區','東區','香山區') NOT NULL,
    type_label VARCHAR(80) NOT NULL DEFAULT '',
    summary VARCHAR(500) NOT NULL,
    description TEXT NOT NULL,
    hours VARCHAR(500) NOT NULL DEFAULT '',
    address VARCHAR(255) NOT NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    transport VARCHAR(2000) NOT NULL DEFAULT '',
    website_url VARCHAR(2000) NOT NULL DEFAULT '',
    map_url VARCHAR(2000) NOT NULL DEFAULT '',
    google_place_id VARCHAR(255) CHARACTER SET ascii NULL,
    media_id CHAR(36) CHARACTER SET ascii NULL,
    external_image_url VARCHAR(2000) NOT NULL DEFAULT '',
    external_image_alt VARCHAR(255) NOT NULL DEFAULT '',
    featured BOOLEAN NOT NULL DEFAULT FALSE,
    is_museum BOOLEAN NOT NULL DEFAULT FALSE,
    status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    publish_at DATETIME NOT NULL,
    seo_title VARCHAR(160) NOT NULL DEFAULT '',
    seo_description VARCHAR(500) NOT NULL DEFAULT '',
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    CONSTRAINT fk_attraction_media FOREIGN KEY (media_id) REFERENCES media_assets(id),
    CONSTRAINT chk_attraction_coordinates CHECK ((latitude IS NULL AND longitude IS NULL) OR (latitude BETWEEN -90 AND 90 AND longitude BETWEEN -180 AND 180)),
    INDEX attraction_public (deleted_at,status,publish_at,id),
    INDEX attraction_admin (deleted_at,updated_at,id)
) ENGINE=InnoDB;
-- @statement
CREATE TABLE IF NOT EXISTS merchants (
    attraction_id CHAR(36) CHARACTER SET ascii PRIMARY KEY,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_merchant_attraction FOREIGN KEY (attraction_id) REFERENCES attractions(id)
) ENGINE=InnoDB;
-- @statement
CREATE TABLE IF NOT EXISTS attraction_aliases (
    path VARCHAR(255) CHARACTER SET ascii PRIMARY KEY,
    attraction_id CHAR(36) CHARACTER SET ascii NOT NULL,
    CONSTRAINT fk_alias_attraction FOREIGN KEY (attraction_id) REFERENCES attractions(id)
) ENGINE=InnoDB;
-- @statement
CREATE TABLE IF NOT EXISTS content_publications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    generation CHAR(64) CHARACTER SET ascii NOT NULL,
    status ENUM('pending','succeeded','failed') NOT NULL,
    error_code VARCHAR(80) CHARACTER SET ascii NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL
) ENGINE=InnoDB;
-- [E-18] 僅為 P0 景點刪除的引用保護建立必要參照；P1 路線管理尚未實作。
-- @statement
CREATE TABLE IF NOT EXISTS routes (
    id CHAR(36) CHARACTER SET ascii PRIMARY KEY,
    title VARCHAR(160) NOT NULL,
    status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    version INT UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB;
-- @statement
CREATE TABLE IF NOT EXISTS route_attractions (
    route_id CHAR(36) CHARACTER SET ascii NOT NULL,
    attraction_id CHAR(36) CHARACTER SET ascii NOT NULL,
    sort_order INT UNSIGNED NOT NULL,
    PRIMARY KEY (route_id,attraction_id),
    CONSTRAINT fk_route_item_route FOREIGN KEY (route_id) REFERENCES routes(id),
    CONSTRAINT fk_route_item_attraction FOREIGN KEY (attraction_id) REFERENCES attractions(id),
    INDEX route_item_attraction (attraction_id)
) ENGINE=InnoDB;
