

CREATE DATABASE IF NOT EXISTS facebook_clone CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
USE facebook_clone;

DELIMITER $$

DROP PROCEDURE IF EXISTS fb_add_index_if_missing $$
CREATE PROCEDURE fb_add_index_if_missing(
  IN in_table VARCHAR(64),
  IN in_index VARCHAR(64),
  IN in_ddl TEXT
)
BEGIN
  DECLARE idx_count INT DEFAULT 0;

  SELECT COUNT(*) INTO idx_count
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = in_table
    AND index_name = in_index;

  IF idx_count = 0 THEN
    SET @ddl := in_ddl;
    PREPARE stmt FROM @ddl;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END $$

DROP PROCEDURE IF EXISTS fb_alter_table_if_exists $$
CREATE PROCEDURE fb_alter_table_if_exists(
  IN in_table VARCHAR(64),
  IN in_ddl TEXT
)
BEGIN
  DECLARE tbl_count INT DEFAULT 0;

  SELECT COUNT(*) INTO tbl_count
  FROM information_schema.tables
  WHERE table_schema = DATABASE()
    AND table_name = in_table;

  IF tbl_count = 1 THEN
    SET @ddl := in_ddl;
    PREPARE stmt FROM @ddl;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END $$

-- InnoDB ROW_FORMAT tuning (applies only if tables exist)
CALL fb_alter_table_if_exists('users', 'ALTER TABLE users ROW_FORMAT=DYNAMIC') $$
CALL fb_alter_table_if_exists('posts', 'ALTER TABLE posts ROW_FORMAT=DYNAMIC') $$
CALL fb_alter_table_if_exists('likes', 'ALTER TABLE likes ROW_FORMAT=DYNAMIC') $$
CALL fb_alter_table_if_exists('comments', 'ALTER TABLE comments ROW_FORMAT=DYNAMIC') $$
CALL fb_alter_table_if_exists('comment_likes', 'ALTER TABLE comment_likes ROW_FORMAT=DYNAMIC') $$
CALL fb_alter_table_if_exists('friends', 'ALTER TABLE friends ROW_FORMAT=DYNAMIC') $$
CALL fb_alter_table_if_exists('conversations', 'ALTER TABLE conversations ROW_FORMAT=DYNAMIC') $$
CALL fb_alter_table_if_exists('conversation_users', 'ALTER TABLE conversation_users ROW_FORMAT=DYNAMIC') $$
CALL fb_alter_table_if_exists('notifications', 'ALTER TABLE notifications ROW_FORMAT=DYNAMIC') $$
CALL fb_alter_table_if_exists('pending_registrations', 'ALTER TABLE pending_registrations ROW_FORMAT=DYNAMIC') $$
CALL fb_alter_table_if_exists('remember_tokens', 'ALTER TABLE remember_tokens ROW_FORMAT=DYNAMIC') $$
CALL fb_alter_table_if_exists('password_resets', 'ALTER TABLE password_resets ROW_FORMAT=DYNAMIC') $$
CALL fb_alter_table_if_exists('messages', 'ALTER TABLE messages ROW_FORMAT=DYNAMIC') $$

-- Indexes (only add if missing)
CALL fb_add_index_if_missing('users', 'idx_online_last_active',
  'CREATE INDEX idx_online_last_active ON users (is_online, last_active)') $$

CALL fb_add_index_if_missing('posts', 'idx_created',
  'CREATE INDEX idx_created ON posts (created_at, id)') $$
CALL fb_add_index_if_missing('posts', 'idx_privacy_created',
  'CREATE INDEX idx_privacy_created ON posts (privacy, created_at, id)') $$

CALL fb_add_index_if_missing('likes', 'idx_post_created',
  'CREATE INDEX idx_post_created ON likes (post_id, created_at)') $$

CALL fb_add_index_if_missing('comments', 'idx_post_created',
  'CREATE INDEX idx_post_created ON comments (post_id, created_at, id)') $$
CALL fb_add_index_if_missing('comments', 'idx_post_parent_created',
  'CREATE INDEX idx_post_parent_created ON comments (post_id, parent_id, created_at, id)') $$

CALL fb_add_index_if_missing('comment_likes', 'idx_comment_created',
  'CREATE INDEX idx_comment_created ON comment_likes (comment_id, created_at)') $$

CALL fb_add_index_if_missing('friends', 'idx_user_status_created',
  'CREATE INDEX idx_user_status_created ON friends (user_id, status, created_at)') $$
CALL fb_add_index_if_missing('friends', 'idx_friend_status_created',
  'CREATE INDEX idx_friend_status_created ON friends (friend_id, status, created_at)') $$
CALL fb_add_index_if_missing('friends', 'idx_pair_reverse',
  'CREATE INDEX idx_pair_reverse ON friends (friend_id, user_id)') $$

CALL fb_add_index_if_missing('conversations', 'idx_type_created',
  'CREATE INDEX idx_type_created ON conversations (type, created_at, id)') $$

CALL fb_add_index_if_missing('conversation_users', 'idx_user_conv',
  'CREATE INDEX idx_user_conv ON conversation_users (user_id, conversation_id)') $$

CALL fb_add_index_if_missing('notifications', 'idx_user_created',
  'CREATE INDEX idx_user_created ON notifications (user_id, created_at, id)') $$
CALL fb_add_index_if_missing('notifications', 'idx_user_read_created',
  'CREATE INDEX idx_user_read_created ON notifications (user_id, is_read, created_at, id)') $$

CALL fb_add_index_if_missing('pending_registrations', 'idx_expires_email',
  'CREATE INDEX idx_expires_email ON pending_registrations (expires_at, email)') $$

CALL fb_add_index_if_missing('remember_tokens', 'idx_user_expires',
  'CREATE INDEX idx_user_expires ON remember_tokens (user_id, expires_at)') $$

CALL fb_add_index_if_missing('password_resets', 'idx_expires',
  'CREATE INDEX idx_expires ON password_resets (expires_at)') $$

CALL fb_add_index_if_missing('messages', 'idx_pair_reverse',
  'CREATE INDEX idx_pair_reverse ON messages (to_user, from_user, id)') $$
CALL fb_add_index_if_missing('messages', 'idx_to_from_read',
  'CREATE INDEX idx_to_from_read ON messages (to_user, from_user, is_read, id)') $$
CALL fb_add_index_if_missing('messages', 'idx_from_created',
  'CREATE INDEX idx_from_created ON messages (from_user, created_at, id)') $$

-- Cleanup helpers
DROP PROCEDURE IF EXISTS fb_add_index_if_missing $$
DROP PROCEDURE IF EXISTS fb_alter_table_if_exists $$

DELIMITER ;
