-- [S：功能規格 G15／L15；D：資料表建議 C19–D20；U-08／E-27] P0 必要保存，沿用至完整旅程。
-- @statement
CREATE TABLE IF NOT EXISTS itineraries (
    id CHAR(36) CHARACTER SET ascii PRIMARY KEY,
    member_id CHAR(36) CHARACTER SET ascii NOT NULL,
    title VARCHAR(200) NOT NULL DEFAULT '我的旅行計畫',
    trip_date DATE NULL,
    status VARCHAR(20) CHARACTER SET ascii NOT NULL DEFAULT 'active',
    version INT UNSIGNED NOT NULL DEFAULT 1,
    current_slot TINYINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    CONSTRAINT itinerary_member FOREIGN KEY (member_id) REFERENCES members(id),
    CONSTRAINT current_itinerary_slot CHECK (current_slot IS NULL OR current_slot=1),
    CONSTRAINT deleted_itinerary_slot CHECK (deleted_at IS NULL OR current_slot IS NULL),
    UNIQUE KEY member_current (member_id,current_slot)
) ENGINE=InnoDB;
-- @statement
CREATE TABLE IF NOT EXISTS itinerary_items (
    id CHAR(36) CHARACTER SET ascii PRIMARY KEY,
    itinerary_id CHAR(36) CHARACTER SET ascii NOT NULL,
    attraction_id CHAR(36) CHARACTER SET ascii NOT NULL,
    sort_order INT UNSIGNED NOT NULL,
    planned_time TIME NULL,
    note TEXT NOT NULL DEFAULT '',
    snapshot_name VARCHAR(120) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT item_itinerary FOREIGN KEY (itinerary_id) REFERENCES itineraries(id),
    CONSTRAINT item_attraction FOREIGN KEY (attraction_id) REFERENCES attractions(id),
    CONSTRAINT positive_item_order CHECK (sort_order>0),
    UNIQUE KEY itinerary_attraction (itinerary_id,attraction_id),
    UNIQUE KEY itinerary_order (itinerary_id,sort_order)
) ENGINE=InnoDB;
