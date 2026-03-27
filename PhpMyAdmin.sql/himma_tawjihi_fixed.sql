-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 12, 2025
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `himma_tawjihi`
--
CREATE DATABASE IF NOT EXISTS `himma_tawjihi` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `himma_tawjihi`;

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

DROP TABLE IF EXISTS `activity_log`;
CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_log`
--

INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 4, 'register', 'إنشاء حساب جديد', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-30 11:08:04'),
(2, 4, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-30 11:08:26'),
(3, 5, 'register', 'إنشاء حساب جديد', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-30 11:37:56'),
(4, 5, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-30 11:38:13'),
(5, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-30 11:43:55');

-- --------------------------------------------------------

--
-- Table structure for table `admin_permissions`
--

DROP TABLE IF EXISTS `admin_permissions`;
CREATE TABLE `admin_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`permissions`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_admin_id` (`admin_id`),
  KEY `idx_branch_id` (`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

DROP TABLE IF EXISTS `branches`;
CREATE TABLE `branches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `manager_id` int(11) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_manager_id` (`manager_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `branches`
--

INSERT INTO `branches` (`id`, `name`, `description`, `manager_id`, `address`, `phone`, `email`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'الفرع الرئيسي - غزة', 'الفرع الرئيسي في مدينة غزة', 18, 'غزة - شارع الجامعة', '08-2123456', 'gaza@himma.edu', 1, '2025-12-05 20:40:48', '2025-12-05 20:40:48'),
(2, 'فرع الشمال', 'فرع شمال غزة وجباليا', 19, 'جباليا - شارع فلسطين', '08-2123457', 'north@himma.edu', 1, '2025-12-05 20:40:48', '2025-12-05 20:40:48'),
(3, 'فرع الوسطى', 'فرع المحافظة الوسطى', 20, 'دير البلح - شارع الشهداء', '08-2123458', 'middle@himma.edu', 1, '2025-12-05 20:40:48', '2025-12-05 20:40:48'),
(4, 'فرع خان يونس', 'فرع محافظة خان يونس', NULL, 'خان يونس - شارع جمال عبد الناصر', '08-2123459', 'khanyounis@himma.edu', 1, '2025-12-05 20:40:48', '2025-12-05 20:40:48'),
(5, 'فرع رفح', 'فرع محافظة رفح', NULL, 'رفح - شارع الشهداء', '08-2123460', 'rafah@himma.edu', 1, '2025-12-05 20:40:48', '2025-12-05 20:40:48');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('admin','teacher','student') NOT NULL DEFAULT 'student',
  `branch_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_role` (`role`),
  KEY `idx_branch_id` (`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `full_name`, `phone`, `role`, `branch_id`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@himma.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'المدير العام', '0599999999', 'admin', 1, 1, '2025-10-29 14:32:39', '2025-10-29 14:32:39'),
(2, 'teacher1', 'teacher1@himma.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'أحمد محمد', '0598888888', 'teacher', 1, 1, '2025-10-29 14:32:39', '2025-10-29 14:32:39'),
(4, 'student1', 'student1@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'محمد علي', '0597777777', 'student', 1, 1, '2025-10-30 11:08:04', '2025-10-30 11:08:04'),
(5, 'teacher2', 'teacher2@himma.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'فاطمة أحمد', '0596666666', 'teacher', 2, 1, '2025-10-30 11:37:56', '2025-10-30 11:37:56');

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

DROP TABLE IF EXISTS `subjects`;
CREATE TABLE `subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `teacher_id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `level` varchar(50) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT 0.00,
  `thumbnail` varchar(500) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_teacher_id` (`teacher_id`),
  KEY `idx_branch_id` (`branch_id`),
  KEY `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`id`, `title`, `description`, `teacher_id`, `branch_id`, `category`, `level`, `price`, `thumbnail`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'الرياضيات المتقدمة', 'مادة الرياضيات للتوجيهي', 2, 1, 'علمي', 'توجيهي', 150.00, NULL, 1, '2025-10-29 14:31:40', '2025-10-29 14:31:40'),
(2, 'الفيزياء العامة', 'مادة الفيزياء للتوجيهي', 2, 1, 'علمي', 'توجيهي', 120.00, NULL, 1, '2025-10-29 14:31:40', '2025-10-29 14:31:40'),
(3, 'اللغة العربية', 'مادة اللغة العربية', 2, 1, 'أدبي', 'توجيهي', 100.00, NULL, 1, '2025-10-29 14:31:40', '2025-10-29 14:31:40'),
(5, 'الكيمياء', 'مادة الكيمياء للتوجيهي', 5, 1, 'علمي', 'توجيهي', 130.00, NULL, 1, '2025-10-30 11:37:56', '2025-10-30 11:37:56'),
(6, 'اللغة العربية', 'مادة اللغة العربية', 8, 2, 'أدبي', 'توجيهي', 20.00, NULL, 1, '2025-11-01 13:35:13', '2025-11-01 13:35:13'),
(8, 'التربية الاسلامية', 'مادة التربية الاسلامية', 5, 1, 'ديني', 'توجيهي', 50.00, NULL, 1, '2025-11-07 16:48:15', '2025-11-07 16:48:15');

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

DROP TABLE IF EXISTS `contact_messages`;
CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `subject` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `status` enum('new','read','replied') DEFAULT 'new',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contact_messages`
--

INSERT INTO `contact_messages` (`id`, `name`, `email`, `subject`, `message`, `status`, `created_at`, `updated_at`, `phone`) VALUES
(1, 'محمد أحمد', 'ghadasryle@gmail.com', 'عدم ظهور مساقاتي', 'ارجو منكم التواصل معي باسرع ما يمكن', 'new', '2025-11-03 21:05:05', '2025-11-03 21:05:05', '0123456791'),
(2, 'هالة محمد عوض', 'ghadayousef2020.prog@gmail.com', 'خطأ في الدخول', 'الرجاء التواصل معي', 'new', '2025-11-08 10:02:46', '2025-11-08 10:02:46', '0595796544');

-- --------------------------------------------------------

--
-- Table structure for table `enrollments`
--

DROP TABLE IF EXISTS `enrollments`;
CREATE TABLE `enrollments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `enrollment_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `progress_percentage` decimal(5,2) DEFAULT 0.00,
  `status` enum('active','completed','cancelled') DEFAULT 'active',
  `payment_status` enum('pending','paid','refunded') DEFAULT 'pending',
  `payment_amount` decimal(10,2) DEFAULT NULL,
  `payment_date` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_subject_id` (`subject_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `enrollments`
--

INSERT INTO `enrollments` (`id`, `user_id`, `subject_id`, `enrollment_date`, `progress_percentage`, `status`, `payment_status`, `payment_amount`, `payment_date`) VALUES
(2, 4, 1, '2025-10-30 11:55:36', 0.00, 'active', 'pending', NULL, NULL),
(3, 4, 2, '2025-10-30 11:55:39', 0.00, 'active', 'pending', NULL, NULL),
(5, 4, 5, '2025-11-02 06:06:09', 0.00, 'active', 'pending', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `lessons`
--

DROP TABLE IF EXISTS `lessons`;
CREATE TABLE `lessons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subject_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `content` text DEFAULT NULL,
  `video_url` varchar(500) DEFAULT NULL,
  `video_type` enum('none','youtube','upload') DEFAULT 'none',
  `file_path` varchar(500) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `order_num` int(11) DEFAULT 1,
  `duration_minutes` int(11) DEFAULT 0,
  `is_free` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_subject_id` (`subject_id`),
  KEY `idx_order_num` (`order_num`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lessons`
--

INSERT INTO `lessons` (`id`, `subject_id`, `title`, `description`, `content`, `video_url`, `video_type`, `file_path`, `file_size`, `order_num`, `duration_minutes`, `is_free`, `created_at`, `updated_at`) VALUES
(2, 6, 'شرح البحور', 'بحر الكامل', 'تقطيع البيت الشعري', 'https://youtu.be/uZDhHRQCSCo?si=cDbEyFCk7v4Aaa1R', 'youtube', NULL, NULL, 1, 50, 1, '2025-11-01 13:36:46', '2025-11-01 13:36:46'),
(3, 6, 'بحور اللغة العربية', 'بحر الكامل', 'تقطيع البيت الشعري', 'uploads/videos/video_69060d56660e6_1762004310.mp4', 'upload', NULL, NULL, 1, 50, 1, '2025-11-01 13:38:30', '2025-11-01 13:41:10');

-- --------------------------------------------------------

--
-- Table structure for table `lesson_progress`
--

DROP TABLE IF EXISTS `lesson_progress`;
CREATE TABLE `lesson_progress` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `lesson_id` int(11) NOT NULL,
  `completed` tinyint(1) DEFAULT 0,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_lesson` (`user_id`, `lesson_id`),
  KEY `idx_lesson_id` (`lesson_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `type` enum('system','chat','assignment','grade','announcement','info','success','warning','error') DEFAULT 'system',
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_is_read` (`is_read`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notification_settings`
--

DROP TABLE IF EXISTS `notification_settings`;
CREATE TABLE `notification_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `chat_notifications` tinyint(1) DEFAULT 1,
  `assignment_notifications` tinyint(1) DEFAULT 1,
  `grade_notifications` tinyint(1) DEFAULT 1,
  `announcement_notifications` tinyint(1) DEFAULT 1,
  `email_notifications` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_settings` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quizzes`
--

DROP TABLE IF EXISTS `quizzes`;
CREATE TABLE `quizzes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subject_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `quiz_type` enum('midterm','final','quiz','assignment') NOT NULL DEFAULT 'quiz',
  `total_marks` int(11) NOT NULL DEFAULT 100,
  `pass_marks` int(11) NOT NULL DEFAULT 60,
  `duration` int(11) NOT NULL DEFAULT 60,
  `attempts_allowed` int(11) DEFAULT 3,
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_subject_id` (`subject_id`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `quizzes`
--

INSERT INTO `quizzes` (`id`, `subject_id`, `title`, `description`, `quiz_type`, `total_marks`, `pass_marks`, `duration`, `attempts_allowed`, `start_date`, `end_date`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'اختبار الرياضيات النصفي', 'اختبار نصفي شامل في الرياضيات', 'midterm', 100, 60, 90, 3, NULL, NULL, 1, '2025-10-29 14:31:40', '2025-10-29 14:31:40');

-- --------------------------------------------------------

--
-- Table structure for table `quiz_questions`
--

DROP TABLE IF EXISTS `quiz_questions`;
CREATE TABLE `quiz_questions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quiz_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `question_type` enum('multiple_choice','true_false','short_answer') NOT NULL DEFAULT 'multiple_choice',
  `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`options`)),
  `correct_answer` text NOT NULL,
  `explanation` text DEFAULT NULL,
  `marks` int(11) NOT NULL DEFAULT 1,
  `order_num` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_quiz_id` (`quiz_id`),
  KEY `idx_order_num` (`order_num`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `quiz_questions`
--

INSERT INTO `quiz_questions` (`id`, `quiz_id`, `question_text`, `question_type`, `options`, `correct_answer`, `explanation`, `marks`, `order_num`, `created_at`) VALUES
(1, 1, 'ما هو ناتج 2 + 2؟', 'multiple_choice', '["2", "3", "4", "5"]', '4', NULL, 5, 1, '2025-10-29 14:31:40'),
(2, 1, 'الرقم 7 هو عدد أولي', 'true_false', '["صح", "خطأ"]', 'صح', NULL, 3, 1, '2025-10-29 14:31:40');

-- --------------------------------------------------------

--
-- Table structure for table `quiz_results`
--

DROP TABLE IF EXISTS `quiz_results`;
CREATE TABLE `quiz_results` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quiz_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `score` decimal(5,2) NOT NULL,
  `total_questions` int(11) NOT NULL,
  `correct_answers` int(11) NOT NULL,
  `time_taken` int(11) NOT NULL,
  `answers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`answers`)),
  `is_passed` tinyint(1) DEFAULT 0,
  `attempt_number` int(11) DEFAULT 1,
  `completed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_quiz_id` (`quiz_id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Add Foreign Key Constraints
--

ALTER TABLE `activity_log`
  ADD CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `admin_permissions`
  ADD CONSTRAINT `fk_admin_permissions_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_admin_permissions_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;

ALTER TABLE `branches`
  ADD CONSTRAINT `fk_branch_manager` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `subjects`
  ADD CONSTRAINT `fk_subject_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_subject_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;

ALTER TABLE `enrollments`
  ADD CONSTRAINT `fk_enrollment_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_enrollment_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE;

ALTER TABLE `lessons`
  ADD CONSTRAINT `fk_lesson_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE;

ALTER TABLE `lesson_progress`
  ADD CONSTRAINT `fk_progress_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_progress_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE;

ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notification_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `notification_settings`
  ADD CONSTRAINT `fk_settings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `quizzes`
  ADD CONSTRAINT `fk_quiz_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE;

ALTER TABLE `quiz_questions`
  ADD CONSTRAINT `fk_question_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE;

ALTER TABLE `quiz_results`
  ADD CONSTRAINT `fk_result_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_result_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;