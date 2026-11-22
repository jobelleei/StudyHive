-- =====================================================
-- StudyHive database schema (structure only, no data)
-- Works on MariaDB 10.4+ (XAMPP) and Amazon RDS MariaDB.
--
-- Safe to run more than once: every table uses
-- CREATE TABLE IF NOT EXISTS, so the EC2 launch script
-- can apply it on every boot without touching existing data.
--
-- Local (XAMPP):  create database `studyhive_db` in phpMyAdmin,
--                 then Import this file into it.
-- =====================================================

SET NAMES utf8mb4;
SET time_zone = "+00:00";

-- -----------------------------------------------------
-- users
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `user_id`         int(11)      NOT NULL AUTO_INCREMENT,
  `first_name`      varchar(100) NOT NULL,
  `last_name`       varchar(100) NOT NULL,
  `email`           varchar(150) NOT NULL,
  `password`        varchar(255) NOT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `created_at`      timestamp    NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------
-- user_settings
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_settings` (
  `setting_id`            int(11)     NOT NULL AUTO_INCREMENT,
  `user_id`               int(11)     NOT NULL,
  `theme`                 varchar(20) DEFAULT 'light',
  `notifications_enabled` tinyint(1)  DEFAULT 1,
  PRIMARY KEY (`setting_id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `user_settings_ibfk_1` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------
-- folders
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `folders` (
  `folder_id`    int(11)      NOT NULL AUTO_INCREMENT,
  `user_id`      int(11)      NOT NULL,
  `folder_name`  varchar(100) NOT NULL,
  `folder_color` varchar(20)  DEFAULT '#5298E8',
  `created_at`   timestamp    NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`folder_id`),
  KEY `fk_folders_user` (`user_id`),
  CONSTRAINT `fk_folders_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------
-- notes
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `notes` (
  `note_id`    int(11)      NOT NULL AUTO_INCREMENT,
  `user_id`    int(11)      NOT NULL,
  `folder_id`  int(11)      DEFAULT NULL,
  `title`      varchar(255) NOT NULL,
  `content`    text         DEFAULT NULL,
  `is_shared`  tinyint(1)   DEFAULT 0,
  `created_at` timestamp    NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp    NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `note_color` varchar(7)   NOT NULL DEFAULT '#FFF2CC',
  PRIMARY KEY (`note_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_notes_folder_id` (`folder_id`),
  CONSTRAINT `fk_notes_folder` FOREIGN KEY (`folder_id`)
    REFERENCES `folders` (`folder_id`) ON DELETE CASCADE,
  CONSTRAINT `notes_ibfk_1` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------
-- events (calendar events and tasks)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `events` (
  `event_id`     int(11)               NOT NULL AUTO_INCREMENT,
  `user_id`      int(11)               NOT NULL,
  `title`        varchar(255)          NOT NULL,
  `description`  text                  DEFAULT NULL,
  `event_date`   date                  NOT NULL,
  `start_time`   time                  DEFAULT NULL,
  `end_time`     time                  DEFAULT NULL,
  `created_at`   timestamp             NOT NULL DEFAULT current_timestamp(),
  `event_type`   enum('event','task')  NOT NULL DEFAULT 'event',
  `is_completed` tinyint(1)            NOT NULL DEFAULT 0,
  PRIMARY KEY (`event_id`),
  KEY `idx_events_user_date` (`user_id`, `event_date`),
  CONSTRAINT `events_ibfk_1` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------
-- achievements
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `achievements` (
  `achievement_id` int(11)      NOT NULL AUTO_INCREMENT,
  `user_id`        int(11)      NOT NULL,
  `activity_name`  varchar(255) NOT NULL,
  `description`    text         DEFAULT NULL,
  `completed_at`   timestamp    NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`achievement_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `achievements_ibfk_1` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------
-- feed_posts
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `feed_posts` (
  `post_id`    int(11)   NOT NULL AUTO_INCREMENT,
  `user_id`    int(11)   NOT NULL,
  `note_id`    int(11)   DEFAULT NULL,
  `caption`    text      DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`post_id`),
  KEY `user_id` (`user_id`),
  KEY `note_id` (`note_id`),
  CONSTRAINT `feed_posts_ibfk_1` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `feed_posts_ibfk_2` FOREIGN KEY (`note_id`)
    REFERENCES `notes` (`note_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------
-- pomodoro_sessions
-- (foreign keys added: the original dump had none, so deleting
--  a folder or note left sessions pointing at missing rows)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `pomodoro_sessions` (
  `session_id`    int(11)   NOT NULL AUTO_INCREMENT,
  `user_id`       int(11)   NOT NULL,
  `folder_id`     int(11)   DEFAULT NULL,
  `note_id`       int(11)   DEFAULT NULL,
  `focus_minutes` int(11)   NOT NULL,
  `break_minutes` int(11)   NOT NULL,
  `completed_at`  timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`session_id`),
  KEY `idx_user_completed` (`user_id`, `completed_at`),
  KEY `idx_pomodoro_folder` (`folder_id`),
  KEY `idx_pomodoro_note` (`note_id`),
  CONSTRAINT `fk_ps_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ps_folder` FOREIGN KEY (`folder_id`)
    REFERENCES `folders` (`folder_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ps_note` FOREIGN KEY (`note_id`)
    REFERENCES `notes` (`note_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
