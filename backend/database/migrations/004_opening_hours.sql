-- [U-09／E-20] 舊 hours 原文保留，不推測舊文字代表每週七天。
-- @statement
ALTER TABLE attractions ADD COLUMN IF NOT EXISTS opening_hours JSON NULL;
