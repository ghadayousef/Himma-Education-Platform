

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subjects' AND COLUMN_NAME = 'name');

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE `subjects` ADD COLUMN `name` VARCHAR(200) GENERATED ALWAYS AS (`title`) STORED AFTER `title`',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- إضافة فهرس على عمود name
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subjects' AND INDEX_NAME = 'idx_name');

SET @sql = IF(@idx_exists = 0, 
    'ALTER TABLE `subjects` ADD INDEX `idx_name` (`name`)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================
-- ============================================================

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'branches' AND COLUMN_NAME = 'manager_id');

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE `branches` ADD COLUMN `manager_id` INT NULL DEFAULT NULL, ADD INDEX `idx_manager` (`manager_id`)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================
-- 3. جدول المناطق (app_d2335_regions)
-- ============================================================

CREATE TABLE IF NOT EXISTS `app_d2335_regions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `code` VARCHAR(10) DEFAULT NULL,
    `manager_id` INT NULL DEFAULT NULL,
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_name` (`name`),
    INDEX `idx_active` (`is_active`),
    INDEX `idx_manager` (`manager_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. جدول المناطق (regions) - النسخة البديلة المستخدمة في بعض الملفات
-- ============================================================

CREATE TABLE IF NOT EXISTS `regions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `code` VARCHAR(10) DEFAULT NULL,
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_name` (`name`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. جدول المديريات (app_d2335_directorates)
-- ============================================================

CREATE TABLE IF NOT EXISTS `app_d2335_directorates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `code` VARCHAR(20) NOT NULL,
    `region_id` INT NOT NULL,
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_region` (`region_id`),
    INDEX `idx_code` (`code`),
    INDEX `idx_active` (`is_active`),
    FOREIGN KEY (`region_id`) REFERENCES `app_d2335_regions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 6. جدول مدراء المناطق (app_d2335_region_managers)
-- ============================================================

CREATE TABLE IF NOT EXISTS `app_d2335_region_managers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `region_id` INT NOT NULL,
    `assigned_by` INT NOT NULL,
    `permissions` JSON DEFAULT NULL,
    `is_active` BOOLEAN DEFAULT TRUE,
    `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `deactivated_at` TIMESTAMP NULL DEFAULT NULL,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_region` (`region_id`),
    INDEX `idx_active` (`is_active`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`region_id`) REFERENCES `app_d2335_regions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`assigned_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 7. جدول مدراء المناطق (region_managers) - النسخة البديلة
-- ============================================================

CREATE TABLE IF NOT EXISTS `region_managers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `region_id` INT DEFAULT NULL,
    `assigned_by` INT NOT NULL,
    `permissions` JSON DEFAULT NULL,
    `is_active` BOOLEAN DEFAULT TRUE,
    `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `deactivated_at` TIMESTAMP NULL DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_region` (`region_id`),
    INDEX `idx_active` (`is_active`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`assigned_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 8. جدول وكلاء المناطق (app_d2335_region_deputies)
-- ============================================================

CREATE TABLE IF NOT EXISTS `app_d2335_region_deputies` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `region_id` INT NOT NULL,
    `directorate` VARCHAR(100) NOT NULL,
    `directorate_id` INT NULL DEFAULT NULL,
    `assigned_by` INT NOT NULL,
    `permissions` JSON DEFAULT NULL,
    `is_active` BOOLEAN DEFAULT TRUE,
    `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `deactivated_at` TIMESTAMP NULL DEFAULT NULL,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_region` (`region_id`),
    INDEX `idx_directorate` (`directorate_id`),
    INDEX `idx_active` (`is_active`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`region_id`) REFERENCES `app_d2335_regions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`directorate_id`) REFERENCES `app_d2335_directorates`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`assigned_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 9. جدول وكلاء المناطق (region_deputies) - النسخة البديلة
-- ============================================================

CREATE TABLE IF NOT EXISTS `region_deputies` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `region_id` INT DEFAULT NULL,
    `directorate` VARCHAR(100) DEFAULT NULL,
    `directorate_id` INT NULL DEFAULT NULL,
    `assigned_by` INT DEFAULT NULL,
    `appointed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `is_active` BOOLEAN DEFAULT TRUE,
    `permissions` JSON DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_region` (`region_id`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 10. جدول طلبات المعلمين (app_d2335_teacher_applications)
-- ============================================================

CREATE TABLE IF NOT EXISTS `app_d2335_teacher_applications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `teacher_name` VARCHAR(100) NOT NULL,
    `full_name` VARCHAR(100) DEFAULT NULL,
    `email` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `region_id` INT DEFAULT NULL,
    `directorate` VARCHAR(100) DEFAULT NULL,
    `directorate_id` INT DEFAULT NULL,
    `subject_specialization` VARCHAR(200) NOT NULL,
    `experience_years` INT DEFAULT 0,
    `education_level` VARCHAR(50) DEFAULT NULL,
    `qualifications` TEXT DEFAULT NULL,
    `cv_file` VARCHAR(500) DEFAULT NULL,
    `certificates_file` VARCHAR(500) DEFAULT NULL,
    `status` ENUM('pending', 'approved', 'rejected', 'under_review') DEFAULT 'pending',
    `teacher_user_id` INT DEFAULT NULL,
    `review_notes` TEXT DEFAULT NULL,
    `admin_notes` TEXT DEFAULT NULL,
    `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `reviewed_by` INT DEFAULT NULL,
    `reviewed_at` TIMESTAMP NULL DEFAULT NULL,
    `approved_at` TIMESTAMP NULL DEFAULT NULL,
    `approved_by` INT DEFAULT NULL,
    INDEX `idx_email` (`email`),
    INDEX `idx_status` (`status`),
    INDEX `idx_region` (`region_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 11. جدول طلبات المعلمين (teacher_applications) - النسخة البديلة
-- ============================================================

CREATE TABLE IF NOT EXISTS `teacher_applications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `teacher_name` VARCHAR(100) DEFAULT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `region_id` INT DEFAULT NULL,
    `subject_specialization` VARCHAR(200) NOT NULL,
    `experience_years` INT DEFAULT 0,
    `education_level` VARCHAR(50) DEFAULT NULL,
    `cv_file` VARCHAR(500) DEFAULT NULL,
    `certificates_file` VARCHAR(500) DEFAULT NULL,
    `status` ENUM('pending', 'approved', 'rejected', 'under_review') DEFAULT 'pending',
    `admin_notes` TEXT DEFAULT NULL,
    `review_notes` TEXT DEFAULT NULL,
    `teacher_user_id` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `approved_at` TIMESTAMP NULL DEFAULT NULL,
    `approved_by` INT DEFAULT NULL,
    `reviewed_by` INT DEFAULT NULL,
    `reviewed_at` TIMESTAMP NULL DEFAULT NULL,
    INDEX `idx_email` (`email`),
    INDEX `idx_status` (`status`),
    INDEX `idx_region` (`region_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 12. جدول موافقات المعلمين (teacher_approvals)
-- ============================================================

CREATE TABLE IF NOT EXISTS `teacher_approvals` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `teacher_id` INT NOT NULL,
    `branch_id` INT NOT NULL,
    `super_admin_id` INT NULL DEFAULT NULL,
    `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    `notes` TEXT DEFAULT NULL,
    `requested_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `approved_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_teacher` (`teacher_id`),
    INDEX `idx_branch` (`branch_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_super_admin` (`super_admin_id`),
    FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`super_admin_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 13. جدول الملفات الشخصية (user_profiles)
-- ============================================================

CREATE TABLE IF NOT EXISTS `user_profiles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `date_of_birth` DATE NULL DEFAULT NULL,
    `gender` ENUM('male', 'female') NULL DEFAULT NULL,
    `nationality` VARCHAR(100) NULL DEFAULT NULL,
    `address` TEXT NULL DEFAULT NULL,
    `city` VARCHAR(100) NULL DEFAULT NULL,
    `emergency_contact_name` VARCHAR(100) NULL DEFAULT NULL,
    `emergency_contact_phone` VARCHAR(20) NULL DEFAULT NULL,
    `education_level` VARCHAR(50) NULL DEFAULT NULL,
    `specialization` VARCHAR(100) NULL DEFAULT NULL,
    `years_of_experience` INT DEFAULT 0,
    `qualifications` TEXT NULL DEFAULT NULL,
    `interests` TEXT NULL DEFAULT NULL,
    `profile_completion_percentage` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_user_profile` (`user_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 14. جدول سجل أنشطة الإدارة (admin_activity_log)
-- ============================================================

CREATE TABLE IF NOT EXISTS `admin_activity_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `admin_id` INT NOT NULL,
    `action_type` VARCHAR(50) NOT NULL,
    `action_description` TEXT NOT NULL,
    `target_user_id` INT DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_admin` (`admin_id`),
    INDEX `idx_action` (`action_type`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 15. جدول خيارات الاختبارات (quiz_options)
-- ============================================================

CREATE TABLE IF NOT EXISTS `quiz_options` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `question_id` INT NOT NULL,
    `option_text` TEXT NOT NULL,
    `is_correct` BOOLEAN DEFAULT FALSE,
    `order_number` INT DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_question` (`question_id`),
    FOREIGN KEY (`question_id`) REFERENCES `quiz_questions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 16. جدول إجابات الاختبارات (quiz_answers)
-- ============================================================

CREATE TABLE IF NOT EXISTS `quiz_answers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `result_id` INT NOT NULL,
    `question_id` INT NOT NULL,
    `selected_option_id` INT DEFAULT NULL,
    `answer_text` TEXT DEFAULT NULL,
    `is_correct` BOOLEAN DEFAULT FALSE,
    `marks_earned` DECIMAL(5,2) DEFAULT 0.00,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_result` (`result_id`),
    INDEX `idx_question` (`question_id`),
    FOREIGN KEY (`result_id`) REFERENCES `quiz_results`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`question_id`) REFERENCES `quiz_questions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 17. جدول بنك الأسئلة (question_bank)
-- ============================================================

CREATE TABLE IF NOT EXISTS `question_bank` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `subject_id` INT NOT NULL,
    `question_text` TEXT NOT NULL,
    `question_type` ENUM('multiple_choice', 'true_false', 'short_answer', 'essay') DEFAULT 'multiple_choice',
    `category` VARCHAR(100) DEFAULT NULL,
    `options` JSON DEFAULT NULL,
    `correct_answer` TEXT DEFAULT NULL,
    `difficulty` ENUM('easy', 'medium', 'hard') DEFAULT 'medium',
    `marks` DECIMAL(5,2) DEFAULT 1.00,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_subject` (`subject_id`),
    INDEX `idx_category` (`category`),
    INDEX `idx_type` (`question_type`),
    FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 18. جدول الشهادات (certificates)
-- ============================================================

CREATE TABLE IF NOT EXISTS `certificates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `certificate_id` VARCHAR(100) NOT NULL UNIQUE,
    `student_id` INT NOT NULL,
    `subject_id` INT NOT NULL,
    `issue_date` DATE NOT NULL,
    `grade` VARCHAR(10) DEFAULT NULL,
    `score` DECIMAL(5,2) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_student` (`student_id`),
    INDEX `idx_subject` (`subject_id`),
    INDEX `idx_certificate_id` (`certificate_id`),
    FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 19. جدول تعليقات الدروس (lesson_comments)
-- ============================================================

CREATE TABLE IF NOT EXISTS `lesson_comments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `lesson_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `parent_id` INT DEFAULT NULL,
    `comment_text` TEXT NOT NULL,
    `is_approved` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_lesson` (`lesson_id`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_parent` (`parent_id`),
    FOREIGN KEY (`lesson_id`) REFERENCES `lessons`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 20. جدول إعجابات التعليقات (comment_likes)
-- ============================================================

CREATE TABLE IF NOT EXISTS `comment_likes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `comment_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_like` (`comment_id`, `user_id`),
    INDEX `idx_comment` (`comment_id`),
    INDEX `idx_user` (`user_id`),
    FOREIGN KEY (`comment_id`) REFERENCES `lesson_comments`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 21. جدول تقييمات الدروس (lesson_ratings)
-- ============================================================

CREATE TABLE IF NOT EXISTS `lesson_ratings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `lesson_id` INT NOT NULL,
    `student_id` INT NOT NULL,
    `rating` TINYINT NOT NULL CHECK (`rating` BETWEEN 1 AND 5),
    `review` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_rating` (`lesson_id`, `student_id`),
    INDEX `idx_lesson` (`lesson_id`),
    INDEX `idx_student` (`student_id`),
    FOREIGN KEY (`lesson_id`) REFERENCES `lessons`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 22. جدول مشاهدات الدروس (lesson_views)
-- ============================================================

CREATE TABLE IF NOT EXISTS `lesson_views` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `lesson_id` INT NOT NULL,
    `student_id` INT NOT NULL,
    `viewed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `progress_percentage` INT DEFAULT 0,
    UNIQUE KEY `unique_view` (`lesson_id`, `student_id`),
    INDEX `idx_lesson` (`lesson_id`),
    INDEX `idx_student` (`student_id`),
    FOREIGN KEY (`lesson_id`) REFERENCES `lessons`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 23. جدول رسائل المحادثات (chat_messages)
-- ============================================================

CREATE TABLE IF NOT EXISTS `chat_messages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `conversation_id` INT NOT NULL,
    `sender_id` INT NOT NULL,
    `message_text` TEXT NOT NULL,
    `message_type` ENUM('text', 'image', 'file') DEFAULT 'text',
    `is_read` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_conversation` (`conversation_id`),
    INDEX `idx_sender` (`sender_id`),
    INDEX `idx_read` (`is_read`),
    FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 24. جدول المشاركين في المحادثات (chat_participants)
-- ============================================================

CREATE TABLE IF NOT EXISTS `chat_participants` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `conversation_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `joined_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `is_active` BOOLEAN DEFAULT TRUE,
    UNIQUE KEY `unique_participant` (`conversation_id`, `user_id`),
    INDEX `idx_conversation` (`conversation_id`),
    INDEX `idx_user` (`user_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 25. جدول الرسائل العامة (messages)
-- ============================================================

CREATE TABLE IF NOT EXISTS `messages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `sender_id` INT NOT NULL,
    `recipient_id` INT NOT NULL,
    `subject` VARCHAR(255) DEFAULT NULL,
    `message_text` TEXT NOT NULL,
    `is_read` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_sender` (`sender_id`),
    INDEX `idx_recipient` (`recipient_id`),
    INDEX `idx_read` (`is_read`),
    FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`recipient_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 26. إدراج بيانات المناطق الأساسية
-- ============================================================

INSERT IGNORE INTO `app_d2335_regions` (`name`, `description`, `code`, `is_active`) VALUES
('شمال غزة', 'محافظة شمال غزة', 'NG', TRUE),
('غزة', 'محافظة غزة', 'GZ', TRUE),
('الوسطى', 'محافظة الوسطى', 'MD', TRUE),
('خان يونس', 'محافظة خان يونس', 'KY', TRUE),
('رفح', 'محافظة رفح', 'RF', TRUE);

-- نسخ نفس البيانات لجدول regions
INSERT IGNORE INTO `regions` (`name`, `description`, `code`, `is_active`) VALUES
('شمال غزة', 'محافظة شمال غزة', 'NG', TRUE),
('غزة', 'محافظة غزة', 'GZ', TRUE),
('الوسطى', 'محافظة الوسطى', 'MD', TRUE),
('خان يونس', 'محافظة خان يونس', 'KY', TRUE),
('رفح', 'محافظة رفح', 'RF', TRUE);

-- ============================================================
-- إعادة تفعيل فحص المفاتيح الأجنبية
-- ============================================================


-- ============================================================
-- 27. إصلاح عمود duration_weeks المفقود في جدول courses
-- ============================================================

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'courses' AND COLUMN_NAME = 'duration_weeks');

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE `courses` ADD COLUMN `duration_weeks` INT DEFAULT 0 AFTER `description`',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- إضافة عمود duration_weeks لجدول subjects أيضاً إذا لم يكن موجوداً
SET @col_exists2 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subjects' AND COLUMN_NAME = 'duration_weeks');

SET @sql2 = IF(@col_exists2 = 0, 
    'ALTER TABLE `subjects` ADD COLUMN `duration_weeks` INT DEFAULT 0',
    'SELECT 1');
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

SET FOREIGN_KEY_CHECKS = 1;

-- عبر phpMyAdmin:
--   اذهب إلى قاعدة البيانات > استيراد > اختر الملف > تنفيذ
-- 
-- عبر سطر الأوامر:
--   mysql -u username -p database_name < fix_database.sql
-- ============================================================
