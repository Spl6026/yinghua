-- [U-06／E-16] 真正登入者仍為 sessions.admin_id；模擬只改驗收身分。
-- @statement
ALTER TABLE sessions
    ADD COLUMN IF NOT EXISTS preview_admin_id CHAR(36) CHARACTER SET ascii NULL,
    ADD FOREIGN KEY IF NOT EXISTS preview_admin_fk (preview_admin_id) REFERENCES admin_users(id);
-- @statement
ALTER TABLE sessions
    DROP CONSTRAINT IF EXISTS preview_admin_session,
    ADD CONSTRAINT preview_admin_session CHECK (preview_admin_id IS NULL OR (admin_id IS NOT NULL AND preview_admin_id<>admin_id));
