-- [S：功能規格 G6；E-24] 電話選填；既有內容保留空字串，不猜測或回填電話。
-- @statement
ALTER TABLE attractions ADD COLUMN IF NOT EXISTS phone VARCHAR(80) NOT NULL DEFAULT '' AFTER address;
