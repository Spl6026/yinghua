-- [U-05／E-18；R3-01/02/04] 不改已套用的 002；只增加景點自己的圖片說明與文案標籤。
-- @statement
ALTER TABLE attractions
    ADD COLUMN IF NOT EXISTS image_alt VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS image_source VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS image_license VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS recommendation_label VARCHAR(80) NOT NULL DEFAULT '';
-- 現有資料若違反新規則，本 ALTER 失敗，不會默默刪除／補造座標。
-- @statement
ALTER TABLE attractions
    DROP CONSTRAINT IF EXISTS chk_attraction_coordinates,
    ADD CONSTRAINT chk_attraction_coordinates CHECK (
        (latitude IS NULL AND longitude IS NULL) OR
        (latitude IS NOT NULL AND longitude IS NOT NULL AND
         latitude BETWEEN -90 AND 90 AND longitude BETWEEN -180 AND 180)
    );
