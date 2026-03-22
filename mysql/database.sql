CREATE DATABASE IF NOT EXISTS facebook_clone CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
USE facebook_clone;

-- 1. users
CREATE TABLE IF NOT EXISTS users (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	name VARCHAR(100) NOT NULL,
	email VARCHAR(100) NOT NULL UNIQUE,
	password VARCHAR(255) NOT NULL,
	avatar VARCHAR(500) DEFAULT 'https://scontent.fsgn2-11.fna.fbcdn.net/v/t1.30497-1/453178253_471506465671661_2781666950760530985_n.png?stp=dst-png_s160x160&_nc_cat=1&ccb=1-7&_nc_sid=207b4a&_nc_ohc=_YrXabF6_a4Q7kNvwHChZwQ&_nc_oc=AdlDRJ--0vlW8IqqcwyTpWmsukzJoOAOi1zlFbzf32vPCkEj_D9-B9RX9Xqzfx45vkJJH-Cqint4SLDPns7iCl9j&_nc_zt=24&_nc_ht=scontent.fsgn2-11.fna&oh=00_AfhAoJODDZ_B96ADMufr5faG67W3BLoUDLafJG1bb7-c0g&oe=695279FA',
	cover VARCHAR(255) DEFAULT 'default-cover.jpg',
	bio TEXT,
	is_online TINYINT(1) UNSIGNED DEFAULT 0,
	last_active DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
	birthday DATE NULL,
	gender ENUM('female','male','custom') NULL,
	pronoun VARCHAR(100) NULL,
	pronoun_text VARCHAR(255) NULL,
	INDEX idx_email (email),
	INDEX idx_name (name),
	INDEX idx_online_last_active (is_online, last_active)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. posts
CREATE TABLE IF NOT EXISTS posts (
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	user_id INT UNSIGNED NOT NULL,
	content TEXT,
	image VARCHAR(255),
	video VARCHAR(255),
	privacy ENUM('public','friends','only_me') DEFAULT 'public',
	created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
	INDEX idx_user_created (user_id, created_at),
	INDEX idx_privacy (privacy),
	INDEX idx_created (created_at, id),
	INDEX idx_privacy_created (privacy, created_at, id),
	CONSTRAINT fk_posts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. likes
CREATE TABLE IF NOT EXISTS likes (
	user_id INT UNSIGNED NOT NULL,
	post_id BIGINT UNSIGNED NOT NULL,
	reaction VARCHAR(16) NOT NULL DEFAULT 'like',
	created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (user_id, post_id),
	INDEX idx_post (post_id),
	INDEX idx_post_created (post_id, created_at),
	CONSTRAINT fk_likes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
	CONSTRAINT fk_likes_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. comments
CREATE TABLE IF NOT EXISTS comments (
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	user_id INT UNSIGNED NOT NULL,
	post_id BIGINT UNSIGNED NOT NULL,
	parent_id BIGINT UNSIGNED NULL,
	content TEXT NOT NULL,
	created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
	INDEX idx_post (post_id),
	INDEX idx_parent (parent_id),
	INDEX idx_post_created (post_id, created_at, id),
	INDEX idx_post_parent_created (post_id, parent_id, created_at, id),
	CONSTRAINT fk_comments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
	CONSTRAINT fk_comments_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
	CONSTRAINT fk_comments_parent FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- comment likes (for liking comments)
CREATE TABLE IF NOT EXISTS comment_likes (
	user_id INT UNSIGNED NOT NULL,
	comment_id BIGINT UNSIGNED NOT NULL,
	created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (user_id, comment_id),
	INDEX idx_comment (comment_id),
	INDEX idx_comment_created (comment_id, created_at),
	CONSTRAINT fk_comment_likes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
	CONSTRAINT fk_comment_likes_comment FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. friends
CREATE TABLE IF NOT EXISTS friends (
	user_id INT UNSIGNED NOT NULL,
	friend_id INT UNSIGNED NOT NULL,
	status ENUM('pending','accepted','declined') DEFAULT 'pending',
	created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (user_id, friend_id),
	INDEX idx_friend (friend_id),
	INDEX idx_status (status),
	INDEX idx_user_status_created (user_id, status, created_at),
	INDEX idx_friend_status_created (friend_id, status, created_at),
	INDEX idx_pair_reverse (friend_id, user_id),
	CONSTRAINT fk_friends_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
	CONSTRAINT fk_friends_friend FOREIGN KEY (friend_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- conversation / messenger
CREATE TABLE IF NOT EXISTS conversations (
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	type ENUM('private','group') DEFAULT 'private',
	created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
	INDEX idx_type_created (type, created_at, id)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS conversation_users (
	conversation_id BIGINT UNSIGNED NOT NULL,
	user_id INT UNSIGNED NOT NULL,
	PRIMARY KEY (conversation_id, user_id),
	INDEX idx_user_conv (user_id, conversation_id),
	CONSTRAINT fk_conv_users_conv FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
	CONSTRAINT fk_conv_users_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. notifications
CREATE TABLE IF NOT EXISTS notifications (
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	user_id INT UNSIGNED NOT NULL,
	sender_id INT UNSIGNED NOT NULL,
	type ENUM('like','comment','friend_request','post','tag') NOT NULL,
	post_id BIGINT UNSIGNED NULL,
	comment_id BIGINT UNSIGNED NULL,
	message TEXT,
	is_read TINYINT(1) UNSIGNED DEFAULT 0,
	created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
	INDEX idx_user_read (user_id, is_read),
	INDEX idx_user_created (user_id, created_at, id),
	INDEX idx_user_read_created (user_id, is_read, created_at, id),
	INDEX idx_sender (sender_id),
	INDEX idx_type (type),
	CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
	CONSTRAINT fk_notifications_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
	CONSTRAINT fk_notifications_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
	CONSTRAINT fk_notifications_comment FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pending_registrations (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	email VARCHAR(100) NOT NULL UNIQUE,
	name VARCHAR(100) NOT NULL,
	password_hash VARCHAR(255) NOT NULL,
	birthday DATE NULL,
	gender ENUM('female','male','custom') DEFAULT NULL,
	pronoun VARCHAR(50) NULL,
	pronoun_text VARCHAR(255) NULL,
	code VARCHAR(6) NOT NULL,
	expires_at INT UNSIGNED NOT NULL,
	created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
	INDEX idx_expires (expires_at),
	INDEX idx_email (email),
	INDEX idx_expires_email (expires_at, email)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS remember_tokens (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  selector CHAR(12) NOT NULL UNIQUE,
  validator_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (user_id),
  INDEX (expires_at),
  INDEX idx_user_expires (user_id, expires_at)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	user_id INT UNSIGNED NOT NULL,
	selector VARCHAR(20) NOT NULL,
	validator_hash VARCHAR(64) NOT NULL,
	expires_at DATETIME NOT NULL,
	created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
	INDEX idx_selector (selector),
	INDEX idx_user (user_id),
	INDEX idx_expires (expires_at),
	CONSTRAINT fk_pwreset_user
		FOREIGN KEY (user_id) REFERENCES users(id)
		ON DELETE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- messages (legacy/direct schema used by current PHP chat endpoints)
CREATE TABLE IF NOT EXISTS messages (
		id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
		from_user INT UNSIGNED NOT NULL,
		to_user INT UNSIGNED NOT NULL,
		content TEXT NOT NULL,
		is_read TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		INDEX idx_pair (from_user, to_user, id),
		INDEX idx_to_read (to_user, is_read, id),
		INDEX idx_pair_reverse (to_user, from_user, id),
		INDEX idx_to_from_read (to_user, from_user, is_read, id),
		INDEX idx_from_created (from_user, created_at, id),
		CONSTRAINT fk_messages_from_user
			FOREIGN KEY (from_user) REFERENCES users(id)
			ON DELETE CASCADE,
		CONSTRAINT fk_messages_to_user
			FOREIGN KEY (to_user) REFERENCES users(id)
			ON DELETE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- conversation_reads: per-user last read message in a 1-1 conversation
-- Used for unread badges + "seen" avatar placement.
CREATE TABLE IF NOT EXISTS conversation_reads (
		user_id INT UNSIGNED NOT NULL,
		peer_id INT UNSIGNED NOT NULL,
		last_read_message_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (user_id, peer_id),
		INDEX idx_peer (peer_id),
		INDEX idx_user_last (user_id, last_read_message_id),
		CONSTRAINT fk_convreads_user
			FOREIGN KEY (user_id) REFERENCES users(id)
			ON DELETE CASCADE,
		CONSTRAINT fk_convreads_peer
			FOREIGN KEY (peer_id) REFERENCES users(id)
			ON DELETE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
