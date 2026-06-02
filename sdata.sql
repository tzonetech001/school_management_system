CREATE TABLE IF NOT EXISTS `schools` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_code` varchar(20) NOT NULL UNIQUE,
  `school_name` varchar(200) NOT NULL,
  `school_motto` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `status` enum('Active','Suspended','Expired','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `school_code` (`school_code`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert default school
INSERT IGNORE INTO `schools` (`id`, `school_code`, `school_name`, `school_motto`, `address`, `status`) 
VALUES (1, 'MVZ001', 'Muyovozi High School', 'Education For Life', 'Kigoma, Tanzania', 'Active');
-- ============================================
-- ADDING school_id COLUMN TO ALL TABLES
-- ============================================

-- Core Tables
ALTER TABLE `students` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);
ALTER TABLE `admins` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);
ALTER TABLE `non_staff` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);

-- Dormitory Module
ALTER TABLE `dormitories` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);
ALTER TABLE `dormitory_rooms` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);
ALTER TABLE `student_dormitory` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);
ALTER TABLE `room_status_logs` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);

-- Academic & Exams Module
ALTER TABLE `exam_types` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);
ALTER TABLE `form_five_results` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);
ALTER TABLE `form_six_results` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);
ALTER TABLE `results_auto_save` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);
ALTER TABLE `results_entry_sessions` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);

-- Teacher & Subject Assignments
ALTER TABLE `subject_teacher_assignments` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);
ALTER TABLE `subject_result_entry_log` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);

-- Payments & Store
ALTER TABLE `student_payments` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);
ALTER TABLE `store_tools` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);
ALTER TABLE `store_tools_transactions` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);
ALTER TABLE `student_equipment` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);

-- Maintenance Module
ALTER TABLE `maintenance_items` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);
ALTER TABLE `maintenance_assignments` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);
ALTER TABLE `maintenance_staff_assignments` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);
ALTER TABLE `maintenance_logs` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);

-- Library Module
ALTER TABLE `library_assignments` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);

-- Sports Module
ALTER TABLE `tournaments` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);
ALTER TABLE `teams` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);
ALTER TABLE `team_participants` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);
ALTER TABLE `matches` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);
ALTER TABLE `matches_schedule` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);
ALTER TABLE `match_officials` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);
ALTER TABLE `match_statistics` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);
ALTER TABLE `sports_equipment` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);
ALTER TABLE `sports_history` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);

-- Notifications & Communication
ALTER TABLE `notifications` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);
ALTER TABLE `notification_views` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);
ALTER TABLE `shule_salama_posts` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);
ALTER TABLE `shule_salama_comments` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);
ALTER TABLE `shule_salama_views` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);
ALTER TABLE `contact_messages` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);

-- Discipline Module
ALTER TABLE `discipline_records` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);

-- Food & Production
ALTER TABLE `food_stock` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);
ALTER TABLE `food_stock_history` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);
ALTER TABLE `productions` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);
ALTER TABLE `production_categories` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);
ALTER TABLE `production_logs` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);
ALTER TABLE `production_uses` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);

-- Support & PS Documents
ALTER TABLE `support_messages` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);
ALTER TABLE `support_replies` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);
ALTER TABLE `ps_documents` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);
ALTER TABLE `ps_document_feedback` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);
ALTER TABLE `ps_document_logs` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);
ALTER TABLE `ps_notifications` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);

-- User Preferences & Theme
ALTER TABLE `user_preferences` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);
ALTER TABLE `theme_settings` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);

-- Logs & History
ALTER TABLE `admin_logs` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);
ALTER TABLE `admin_login_attempts` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);
ALTER TABLE `student_login_attempts` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);
ALTER TABLE `student_login_logs` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);
ALTER TABLE `student_graduation_history` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);
ALTER TABLE `student_leavers` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);
ALTER TABLE `leaver_equipment_history` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);
ALTER TABLE `password_resets` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);
ALTER TABLE `sms_logs` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);
ALTER TABLE `applications` ADD COLUMN IF NOT EXISTS `school_id` int(11) NOT NULL DEFAULT 1, ADD INDEX IF NOT EXISTS `idx_school_id` (`school_id`);

-- ============================================
-- ADDING FOREIGN KEYS
-- ============================================

-- Core Tables Foreign Keys
ALTER TABLE `students` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `admins` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `non_staff` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;

-- Dormitory Module Foreign Keys
ALTER TABLE `dormitories` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `dormitory_rooms` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `student_dormitory` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `room_status_logs` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;

-- Academic & Exams Module Foreign Keys
ALTER TABLE `exam_types` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `form_five_results` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `form_six_results` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `results_auto_save` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `results_entry_sessions` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;

-- Teacher & Subject Assignments Foreign Keys
ALTER TABLE `subject_teacher_assignments` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `subject_result_entry_log` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;

-- Payments & Store Foreign Keys
ALTER TABLE `student_payments` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `store_tools` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `store_tools_transactions` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `student_equipment` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;

-- Maintenance Module Foreign Keys
ALTER TABLE `maintenance_items` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `maintenance_assignments` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `maintenance_staff_assignments` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `maintenance_logs` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;

-- Library Module Foreign Keys
ALTER TABLE `library_assignments` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;

-- Sports Module Foreign Keys
ALTER TABLE `tournaments` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `teams` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `team_participants` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `matches` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `matches_schedule` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `match_officials` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `match_statistics` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `sports_equipment` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `sports_history` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;

-- Notifications & Communication Foreign Keys
ALTER TABLE `notifications` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `notification_views` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `shule_salama_posts` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `shule_salama_comments` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `shule_salama_views` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `contact_messages` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;

-- Discipline Module Foreign Keys
ALTER TABLE `discipline_records` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;

-- Food & Production Foreign Keys
ALTER TABLE `food_stock` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `food_stock_history` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `productions` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `production_categories` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `production_logs` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `production_uses` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;

-- Support & PS Documents Foreign Keys
ALTER TABLE `support_messages` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `support_replies` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `ps_documents` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `ps_document_feedback` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `ps_document_logs` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `ps_notifications` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;

-- User Preferences & Theme Foreign Keys
ALTER TABLE `user_preferences` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `theme_settings` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;

-- Logs & History Foreign Keys
ALTER TABLE `admin_logs` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `admin_login_attempts` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `student_login_attempts` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `student_login_logs` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `student_graduation_history` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `student_leavers` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `leaver_equipment_history` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `password_resets` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `sms_logs` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;
ALTER TABLE `applications` ADD FOREIGN KEY IF NOT EXISTS (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE;

-- ============================================
-- VERIFICATION QUERY (Run to check results)
-- ============================================

-- Turn foreign key checks back on
SET FOREIGN_KEY_CHECKS = 1;

-- Check how many tables have school_id column
SELECT COUNT(*) AS tables_with_school_id 
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = 'muyovozi' 
AND COLUMN_NAME = 'school_id';

-- Show all tables that have school_id
SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE 
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = 'muyovozi' 
AND COLUMN_NAME = 'school_id'
ORDER BY TABLE_NAME;

-- Verify school exists
SELECT * FROM schools WHERE id = 1;

-- ============================================
-- CREATE SUPER ADMIN TABLE WITH YOUR DATA
-- ============================================

-- Step 1: Create table
CREATE TABLE IF NOT EXISTS `super_admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('Super Admin','Account Manager','Support','Developer') NOT NULL DEFAULT 'Super Admin',
  `password` varchar(255) NOT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `phone` (`phone`),
  KEY `idx_status` (`status`),
  KEY `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Step 2: Insert your account (REPLACE THE HASH FIRST!)
-- IMPORTANT: Run PHP script first to get actual hash for 'Admin@123'
-- Then replace '$2y$10$REPLACE_WITH_YOUR_ACTUAL_HASH' below

INSERT INTO `super_admins` (
  `first_name`, 
  `last_name`, 
  `email`, 
  `phone`, 
  `role`, 
  `password`, 
  `status`
) VALUES (
  'Tzone', 
  'IT', 
  'tzone@gmail.com', 
  '255714343162', 
  'Super Admin', 
  '$2y$10$REPLACE_WITH_YOUR_ACTUAL_HASH', 
  1
);

-- Step 3: Verify insertion
SELECT id, first_name, last_name, email, role, status FROM super_admins WHERE email = 'tzone@gmail.com';


-- Add school_motto column to schools table
ALTER TABLE `schools` 
ADD COLUMN `school_motto` varchar(255) DEFAULT NULL AFTER `school_name`;

-- Update existing school with motto
UPDATE `schools` 
SET `school_motto` = 'Education For Life' 
WHERE `id` = 1


-- Add system-wide theme defaults
ALTER TABLE `schools` 
ADD COLUMN `system_theme` TEXT DEFAULT NULL COMMENT 'JSON of default theme colors',
ADD COLUMN `system_preferences` TEXT DEFAULT NULL COMMENT 'JSON of default user preferences',
ADD COLUMN `allowed_customization` JSON DEFAULT NULL COMMENT 'Which settings users can customize';