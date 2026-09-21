-- ============================================================
--  OMK School Management System — Demo Database
--  Database name: omk_school
--  All demo accounts use password: "password"
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- --------------------------------------------------------
-- academic_years
-- --------------------------------------------------------
CREATE TABLE `academic_years` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_current` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `academic_years` VALUES (1,'2024-2025','2024-09-01','2025-06-30',1,NOW());

-- --------------------------------------------------------
-- classes
-- --------------------------------------------------------
CREATE TABLE `classes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `grade_level` int(11) NOT NULL,
  `academic_year_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `academic_year_id` (`academic_year_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `classes` (`id`,`name`,`grade_level`,`academic_year_id`) VALUES
(1,'Grade 10',10,1),
(2,'Grade 11',11,1),
(3,'Grade 12',12,1);

-- --------------------------------------------------------
-- sections
-- --------------------------------------------------------
CREATE TABLE `sections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `max_students` int(11) DEFAULT 30,
  `coordinator_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `class_id` (`class_id`),
  KEY `coordinator_id` (`coordinator_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `sections` (`id`,`class_id`,`name`,`max_students`,`coordinator_id`) VALUES
(1,1,'A',30,2),
(2,1,'B',30,3),
(3,2,'A',30,2),
(4,3,'A',30,3);

-- --------------------------------------------------------
-- courses
-- --------------------------------------------------------
CREATE TABLE `courses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `code` varchar(20) NOT NULL,
  `description` text DEFAULT NULL,
  `color` varchar(20) DEFAULT '#4f46e5',
  `credits` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `courses` (`id`,`name`,`code`,`description`,`color`) VALUES
(1,'Mathematics','MAT','Core mathematics including algebra and geometry','#4f46e5'),
(2,'Physics','PHY','Fundamentals of physics and mechanics','#0ea5e9'),
(3,'Chemistry','CHE','Organic and inorganic chemistry basics','#10b981'),
(4,'Biology','BIO','Life sciences and biological systems','#f59e0b'),
(5,'Computer','COM','Introduction to programming and IT','#8b5cf6'),
(6,'Arabic','ARA','Arabic language, grammar and literature','#ef4444'),
(7,'History','HIS','World history and civilization studies','#f97316'),
(8,'Geography','GEO','Physical and human geography','#06b6d4'),
(9,'Sport','SPO','Physical education and sports activities','#84cc16');

-- --------------------------------------------------------
-- users   (all passwords = "password")
-- --------------------------------------------------------
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','coordinator','teacher','student') NOT NULL,
  `user_id_number` varchar(20) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `gender` enum('male','female') DEFAULT 'male',
  `date_of_birth` date DEFAULT NULL,
  `address` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `must_change_password` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `user_id_number` (`user_id_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- password for all accounts is: password
INSERT INTO `users` (`id`,`first_name`,`last_name`,`email`,`password`,`role`,`user_id_number`,`gender`,`date_of_birth`,`is_active`,`must_change_password`) VALUES
(1,'System','Admin','admin@omk.edu','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','admin','ADM001','male',NULL,1,0),
(2,'Sarah','Morgan','sarah.morgan@omk.edu','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','coordinator','CRD20250001','female',NULL,1,0),
(3,'David','Clark','david.clark@omk.edu','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','coordinator','CRD20250002','male',NULL,1,0),
(4,'Michael','Brown','michael.brown@omk.edu','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','teacher','TCH20250001','male',NULL,1,0),
(5,'Emily','Wilson','emily.wilson@omk.edu','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','teacher','TCH20250002','female',NULL,1,0),
(6,'James','Taylor','james.taylor@omk.edu','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','teacher','TCH20250003','male',NULL,1,0),
(7,'Olivia','Martinez','olivia.martinez@omk.edu','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','teacher','TCH20250004','female',NULL,1,0),
(8,'Alex','Johnson','aj20250001@omk.edu','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','student','STU20250001','male','2009-03-15',1,0),
(9,'Mia','Davis','md20250002@omk.edu','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','student','STU20250002','female','2009-07-22',1,0),
(10,'Ethan','Garcia','eg20250003@omk.edu','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','student','STU20250003','male','2009-11-08',1,0),
(11,'Sophia','Lee','sl20250004@omk.edu','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','student','STU20250004','female','2010-01-30',1,0),
(12,'Lucas','White','lw20250005@omk.edu','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','student','STU20250005','male','2009-05-14',1,0),
(13,'Ava','Harris','ah20250006@omk.edu','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','student','STU20250006','female','2010-09-03',1,0),
(14,'Noah','Thompson','nt20250007@omk.edu','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','student','STU20250007','male','2009-12-19',1,0),
(15,'Isabella','Robinson','ir20250008@omk.edu','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','student','STU20250008','female','2010-04-27',1,0);

-- --------------------------------------------------------
-- teacher_courses
-- --------------------------------------------------------
CREATE TABLE `teacher_courses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `academic_year_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `teacher_id` (`teacher_id`),
  KEY `course_id` (`course_id`),
  KEY `section_id` (`section_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `teacher_courses` (`teacher_id`,`course_id`,`section_id`,`academic_year_id`) VALUES
(4,1,1,1),(4,2,1,1),(5,3,1,1),(5,4,1,1),(6,5,1,1),
(6,1,2,1),(7,2,2,1),(7,5,2,1),
(4,1,3,1),(5,3,3,1),(6,7,3,1);

-- --------------------------------------------------------
-- student_sections
-- --------------------------------------------------------
CREATE TABLE `student_sections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `academic_year_id` int(11) DEFAULT NULL,
  `enrolled_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `section_id` (`section_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `student_sections` (`student_id`,`section_id`,`academic_year_id`) VALUES
(8,1,1),(9,1,1),(10,1,1),(11,1,1),
(12,2,1),(13,2,1),(14,2,1),(15,2,1);

-- --------------------------------------------------------
-- notifications
-- --------------------------------------------------------
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text DEFAULT NULL,
  `type` enum('info','success','warning','danger') DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT 0,
  `link` varchar(255) DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `notifications` (`user_id`,`title`,`message`,`type`,`is_read`) VALUES
(1,'Welcome to OMK School','System is set up and ready to use.','success',0),
(2,'Welcome to OMK School','Your coordinator account is active.','success',0),
(4,'Welcome to OMK School','Your teacher account is active.','success',0),
(8,'Welcome to OMK School','Your student account is active.','success',0);

-- --------------------------------------------------------
-- attendance
-- --------------------------------------------------------
CREATE TABLE `attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `course_id` int(11) DEFAULT NULL,
  `date` date NOT NULL,
  `status` enum('present','absent','late','excused') NOT NULL DEFAULT 'present',
  `recorded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `section_id` (`section_id`),
  KEY `date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- agenda
-- --------------------------------------------------------
CREATE TABLE `agenda` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `event_type` enum('assignment','quiz','exam','homework','project','other') DEFAULT 'other',
  `event_date` date NOT NULL,
  `due_time` time DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `teacher_id` (`teacher_id`),
  KEY `course_id` (`course_id`),
  KEY `section_id` (`section_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- posts
-- --------------------------------------------------------
CREATE TABLE `posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text DEFAULT NULL,
  `post_type` enum('text','document','announcement') DEFAULT 'text',
  `file_path` varchar(255) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `is_pinned` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `teacher_id` (`teacher_id`),
  KEY `course_id` (`course_id`),
  KEY `section_id` (`section_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- lesson_preparations
-- --------------------------------------------------------
CREATE TABLE `lesson_preparations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `course_id` int(11) DEFAULT NULL,
  `section_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `content` text DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','signed','rejected') DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `teacher_id` (`teacher_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- grades
-- --------------------------------------------------------
CREATE TABLE `grades` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `assessment_type` enum('quiz','exam','assignment','project','midterm','final','participation') DEFAULT 'exam',
  `assessment_name` varchar(255) DEFAULT NULL,
  `score` decimal(5,2) DEFAULT NULL,
  `max_score` decimal(5,2) DEFAULT 100,
  `grade_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `course_id` (`course_id`),
  KEY `section_id` (`section_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- grade_documents
-- --------------------------------------------------------
CREATE TABLE `grade_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `section_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_size` int(11) DEFAULT 0,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `section_id` (`section_id`),
  KEY `course_id` (`course_id`),
  FOREIGN KEY (`section_id`) REFERENCES `sections`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`course_id`)  REFERENCES `courses`(`id`)  ON DELETE CASCADE,
  FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- programs  (weekly timetable)
-- --------------------------------------------------------
CREATE TABLE `programs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `section_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `room` varchar(100) DEFAULT NULL,
  `academic_year_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `section_id` (`section_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `programs` (`section_id`,`course_id`,`teacher_id`,`day_of_week`,`start_time`,`end_time`,`room`,`academic_year_id`) VALUES
(1,1,4,'Monday','08:00:00','09:00:00','Room 101',1),
(1,2,4,'Monday','09:00:00','10:00:00','Room 101',1),
(1,3,5,'Tuesday','08:00:00','09:00:00','Lab 1',1),
(1,5,6,'Wednesday','10:00:00','11:00:00','Lab 2',1),
(1,1,4,'Thursday','08:00:00','09:00:00','Room 101',1),
(1,4,5,'Friday','09:00:00','10:00:00','Room 102',1);
