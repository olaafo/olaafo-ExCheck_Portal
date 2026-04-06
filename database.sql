-- EntEx Portal Database Schema
-- Created for Juilliard Academy Entrance Examination Portal
-- Compatible with MySQL / MariaDB (phpMyAdmin)

CREATE DATABASE IF NOT EXISTS entex_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE entex_portal;

-- --------------------------------------------------------
-- Admin table
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(150) NOT NULL DEFAULT 'Administrator',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default admin: username=admin, password=password (CHANGE THIS after first login via Settings)
INSERT INTO `admin` (`username`, `password`, `full_name`) VALUES
('admin', '$2y$10$6iB6aN4xemTEQORsFS/w3OKGBEulbBgnuBY/recPaaDbCbUUz9GVm', 'Super Admin');

-- --------------------------------------------------------
-- Settings table
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('school_name', 'Juilliard Academy'),
('logo', ''),
('welcome_message', 'Welcome To Prospective Student Portal'),
('acceptance_fee_url', 'https://paystack.shop/pay/Acceptance-Fee'),
('index_prefix', 'JA/PS'),
('serial_length', '3'),
('grading_100', '[{"min":75,"max":100,"grade":"A","comment":"Excellent"},{"min":60,"max":74,"grade":"B","comment":"Very Good"},{"min":50,"max":59,"grade":"C","comment":"Good"},{"min":40,"max":49,"grade":"D","comment":"Pass"},{"min":0,"max":39,"grade":"F","comment":"Fail"}]'),
('grading_60', '[{"min":45,"max":60,"grade":"A","comment":"Excellent"},{"min":36,"max":44,"grade":"B","comment":"Very Good"},{"min":30,"max":35,"grade":"C","comment":"Good"},{"min":24,"max":29,"grade":"D","comment":"Pass"},{"min":0,"max":23,"grade":"F","comment":"Fail"}]'),
('grading_50', '[{"min":38,"max":50,"grade":"A","comment":"Excellent"},{"min":30,"max":37,"grade":"B","comment":"Very Good"},{"min":25,"max":29,"grade":"C","comment":"Good"},{"min":20,"max":24,"grade":"D","comment":"Pass"},{"min":0,"max":19,"grade":"F","comment":"Fail"}]'),
('cutoff_100', '50'),
('cutoff_60', '30'),
('cutoff_50', '25');

-- --------------------------------------------------------
-- Exam Batches table
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `exam_batches` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `batch_name` VARCHAR(100) NOT NULL UNIQUE,
  `exam_date` DATE NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Subjects table
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `subjects` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `subject_name` VARCHAR(150) NOT NULL UNIQUE,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Students table
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `students` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name` VARCHAR(200) NOT NULL,
  `grade_applied` VARCHAR(50) NOT NULL,
  `age` TINYINT UNSIGNED NOT NULL,
  `gender` ENUM('Male','Female','Other') NOT NULL,
  `index_id` VARCHAR(50) NOT NULL UNIQUE,
  `photo` VARCHAR(255) DEFAULT NULL,
  `registration_type` ENUM('batch','oneoff') NOT NULL DEFAULT 'batch',
  `batch_id` INT UNSIGNED DEFAULT NULL,
  `forced_in` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_students_batch` (`batch_id`),
  CONSTRAINT `fk_students_batch` FOREIGN KEY (`batch_id`) REFERENCES `exam_batches` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Student Subjects (many-to-many)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `student_subjects` (
  `student_id` INT UNSIGNED NOT NULL,
  `subject_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`student_id`, `subject_id`),
  KEY `fk_ss_subject` (`subject_id`),
  CONSTRAINT `fk_ss_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ss_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Exam Schedules table
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `exam_schedules` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` INT UNSIGNED NOT NULL,
  `batch_id` INT UNSIGNED DEFAULT NULL,
  `exam_date` DATE NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `scheduled_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notified` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `fk_es_student` (`student_id`),
  KEY `fk_es_batch` (`batch_id`),
  CONSTRAINT `fk_es_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_es_batch` FOREIGN KEY (`batch_id`) REFERENCES `exam_batches` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Results table
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `results` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` INT UNSIGNED NOT NULL,
  `subject_id` INT UNSIGNED NOT NULL,
  `score` DECIMAL(5,2) NOT NULL DEFAULT 0,
  `max_score` ENUM('100','60','50') NOT NULL DEFAULT '100',
  `grade` VARCHAR(5) DEFAULT NULL,
  `comment` VARCHAR(100) DEFAULT NULL,
  `meet_cutoff` TINYINT(1) DEFAULT NULL,
  `qualified_interview` TINYINT(1) DEFAULT NULL,
  `interview_date` DATE DEFAULT NULL,
  `interview_time` TIME DEFAULT NULL,
  `resit` TINYINT(1) DEFAULT 0,
  `resit_date` DATE DEFAULT NULL,
  `published` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_result_student_subject` (`student_id`, `subject_id`),
  KEY `fk_results_subject` (`subject_id`),
  CONSTRAINT `fk_results_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_results_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Interview Schedules table
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `interview_schedules` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` INT UNSIGNED NOT NULL,
  `interview_date` DATE NOT NULL,
  `interview_time` TIME NOT NULL,
  `status` ENUM('scheduled','confirmed','rescheduled','cancelled') NOT NULL DEFAULT 'scheduled',
  `student_response` ENUM('pending','confirmed','requested_change') NOT NULL DEFAULT 'pending',
  `alternate_date` DATE DEFAULT NULL,
  `admin_notified` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_is_student` (`student_id`),
  CONSTRAINT `fk_is_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Admission Status table
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admission_status` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` INT UNSIGNED NOT NULL UNIQUE,
  `status` ENUM('pending','offered','rejected') NOT NULL DEFAULT 'pending',
  `notification_message` TEXT DEFAULT NULL,
  `admission_number` VARCHAR(50) DEFAULT NULL,
  `payment_confirmed` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_as_student` (`student_id`),
  CONSTRAINT `fk_as_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Notifications table
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` INT UNSIGNED NOT NULL,
  `message` TEXT NOT NULL,
  `type` ENUM('schedule','result','interview','admission','general') NOT NULL DEFAULT 'general',
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_notif_student` (`student_id`),
  CONSTRAINT `fk_notif_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
