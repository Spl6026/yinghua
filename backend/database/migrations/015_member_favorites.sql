-- [S：功能規格 E13／G15；D：資料表建議 C21–D21；E-27] 會員收藏，不匯入原型 localStorage。
-- @statement
CREATE TABLE IF NOT EXISTS favorites (
    member_id CHAR(36) CHARACTER SET ascii NOT NULL,
    attraction_id CHAR(36) CHARACTER SET ascii NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (member_id,attraction_id),
    CONSTRAINT favorite_member FOREIGN KEY (member_id) REFERENCES members(id),
    CONSTRAINT favorite_attraction FOREIGN KEY (attraction_id) REFERENCES attractions(id),
    INDEX favorite_listing (member_id,created_at,attraction_id)
) ENGINE=InnoDB;
