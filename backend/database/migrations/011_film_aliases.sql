-- [E-25] 保留歷史網址；不讓改 slug 破壞原始獨立 HTML 連結。
-- @statement
CREATE TABLE IF NOT EXISTS film_aliases (
    path VARCHAR(255) CHARACTER SET ascii PRIMARY KEY,
    film_id CHAR(36) CHARACTER SET ascii NOT NULL,
    CONSTRAINT fk_alias_film FOREIGN KEY (film_id) REFERENCES films(id)
) ENGINE=InnoDB;
