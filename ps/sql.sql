-- ============================================
-- COMPLETE PS TABLES RESET
-- Drop all existing tables
-- ============================================

-- Disable foreign key checks temporarily to avoid issues
SET FOREIGN_KEY_CHECKS = 0;

-- Drop all PS-related tables
DROP TABLE IF EXISTS `ps_document_feedback`;
DROP TABLE IF EXISTS `ps_document_logs`;
DROP TABLE IF EXISTS `ps_notifications`;
DROP TABLE IF EXISTS `ps_documents`;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- Create tables with correct structure
-- ============================================

-- Create ps_documents table
CREATE TABLE `ps_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `short_note` text DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` enum('image','video','audio','document','spreadsheet','presentation','archive','pdf','text','other') DEFAULT 'document',
  `file_extension` varchar(20) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL COMMENT 'Size in bytes',
  `mime_type` varchar(100) DEFAULT NULL,
  `uploaded_by` int(11) NOT NULL COMMENT 'Admin ID who uploaded',
  `uploader_role` varchar(100) DEFAULT NULL,
  `uploader_name` varchar(200) DEFAULT NULL,
  `visibility` enum('public','private','staff_only') DEFAULT 'staff_only',
  `allow_feedback` tinyint(1) DEFAULT 1,
  `needs_ps_review` tinyint(1) NOT NULL DEFAULT 0,
  `ps_status` enum('pending','approved','rejected','changes_requested') DEFAULT 'approved',
  `ps_reviewed_by` int(11) DEFAULT NULL,
  `ps_reviewed_at` timestamp NULL DEFAULT NULL,
  `ps_comment` text DEFAULT NULL,
  `feedback_count` int(11) DEFAULT 0,
  `download_count` int(11) DEFAULT 0,
  `view_count` int(11) DEFAULT 0,
  `status` enum('active','archived','deleted') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_uploaded_by` (`uploaded_by`),
  KEY `idx_status` (`status`),
  KEY `idx_file_type` (`file_type`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_ps_status` (`ps_status`),
  KEY `idx_needs_review` (`needs_ps_review`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create ps_document_feedback table
CREATE TABLE `ps_document_feedback` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL,
  `commenter_id` int(11) NOT NULL COMMENT 'Admin ID',
  `commenter_name` varchar(200) NOT NULL,
  `commenter_role` varchar(100) DEFAULT NULL,
  `comment` text NOT NULL,
  `parent_comment_id` int(11) DEFAULT NULL COMMENT 'For replies to comments',
  `status` enum('active','deleted') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_document_id` (`document_id`),
  KEY `idx_commenter` (`commenter_id`),
  KEY `idx_parent` (`parent_comment_id`),
  CONSTRAINT `fk_feedback_document` FOREIGN KEY (`document_id`) REFERENCES `ps_documents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_feedback_parent` FOREIGN KEY (`parent_comment_id`) REFERENCES `ps_document_feedback` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create ps_document_logs table
CREATE TABLE `ps_document_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_name` varchar(200) NOT NULL,
  `user_role` varchar(100) DEFAULT NULL,
  `action` enum('view','download','print','feedback') NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_document_id` (`document_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_log_document` FOREIGN KEY (`document_id`) REFERENCES `ps_documents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create ps_notifications table
CREATE TABLE `ps_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('ps_review','document_review','feedback','system') DEFAULT 'system',
  `user_id` int(11) DEFAULT NULL COMMENT 'Target user ID',
  `target_role` varchar(100) DEFAULT NULL COMMENT 'Target role (e.g., PS)',
  `document_id` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `status` enum('unread','read','archived') DEFAULT 'unread',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_target_role` (`target_role`),
  KEY `idx_document_id` (`document_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_notification_document` FOREIGN KEY (`document_id`) REFERENCES `ps_documents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- Verify tables were created
-- ============================================
SHOW TABLES LIKE 'ps_%';
SHOW TABLES LIKE 'ps_notifications';