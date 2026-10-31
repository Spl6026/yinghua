-- [S E18/G18；E-35] 可重建公開搜尋投影，不是編輯來源。
-- @statement
CREATE TABLE search_documents (
    generation CHAR(64) CHARACTER SET ascii NOT NULL,
    resource_type VARCHAR(16) CHARACTER SET ascii NOT NULL,
    resource_id CHAR(36) CHARACTER SET ascii NOT NULL,
    type_order TINYINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    summary TEXT NOT NULL,
    search_text MEDIUMTEXT NOT NULL,
    slug VARCHAR(160) CHARACTER SET ascii NOT NULL,
    url VARCHAR(255) CHARACTER SET ascii NOT NULL,
    thumbnail TEXT NOT NULL,
    thumbnail_alt TEXT NOT NULL,
    PRIMARY KEY (generation,resource_type,resource_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
