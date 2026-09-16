-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 16, 2026 at 05:32 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `event_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `parent_group` varchar(100) NOT NULL,
  `has_criteria` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `parent_group`, `has_criteria`) VALUES
(1, 'Programming Contest', 'IT GAMES', 0),
(2, 'Speedtyping with Blindfold', 'IT GAMES', 0),
(3, 'Crimping', 'IT GAMES', 0),
(4, 'IP Subnetting', 'IT GAMES', 0),
(5, 'Network Configuration', 'IT GAMES', 0),
(6, 'Battle of the Witz (IT Edition)', 'IT GAMES', 0),
(7, 'Basketball Mens', 'Ball Games', 0),
(8, 'Basketball Womens', 'Ball Games', 0),
(9, 'Volleyball Mens', 'Ball Games', 0),
(10, 'Volleyball Womens', 'Ball Games', 0),
(11, 'Mobile Legends Boys', 'Esports', 0),
(12, 'Mobile Legends Girls', 'Esports', 0),
(13, 'Call of Duty (Mix)', 'Esports', 0),
(14, 'Chess', 'Board Game', 0),
(15, 'Sack Race', 'Other Games', 0),
(16, 'Tug of War', 'Other Games', 0),
(17, 'Chinese Garter', 'Other Games', 0),
(18, 'Dance Competition', 'Games with Criteria', 1),
(19, 'Battle of the Emblems (Ms. Doubtful Pageant)', 'Games with Criteria', 1),
(20, 'Video Competition', 'Games with Criteria', 1);

-- --------------------------------------------------------

--
-- Table structure for table `category_staff`
--

CREATE TABLE `category_staff` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `category_staff`
--

INSERT INTO `category_staff` (`id`, `category_id`, `staff_id`, `created_at`) VALUES
(1, 1, 7, '2026-09-15 22:46:43'),
(2, 16, 8, '2026-09-15 22:56:44'),
(3, 9, 5, '2026-09-15 23:56:46');

-- --------------------------------------------------------

--
-- Table structure for table `criteria`
--

CREATE TABLE `criteria` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `percentage` decimal(5,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `criteria`
--

INSERT INTO `criteria` (`id`, `event_id`, `name`, `percentage`) VALUES
(13, 16, 'Music', 20.00),
(14, 16, 'Execution', 30.00),
(15, 16, 'Costume', 30.00),
(16, 16, 'Audience Impact', 20.00);

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `category_id` int(11) NOT NULL,
  `players_representative` text DEFAULT NULL,
  `start_datetime` datetime DEFAULT NULL,
  `end_datetime` datetime DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `name`, `category_id`, `players_representative`, `start_datetime`, `end_datetime`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'IT DAYS Basketball Mens', 7, '', '2026-09-18 08:00:00', '2026-09-18 11:00:00', 'approved', 2, '2026-09-15 21:54:47', '2026-09-16 01:18:58'),
(2, 'IT DAYS Programming Contest', 1, '', '2026-09-17 13:00:00', '2026-09-17 16:00:00', 'approved', 2, '2026-09-15 22:12:47', '2026-09-16 01:07:37'),
(13, 'IT DAYS Volleyball', 9, '', '2026-09-16 09:00:00', '2026-09-16 12:00:00', 'approved', 2, '2026-09-15 22:18:25', '2026-09-16 01:22:10'),
(14, 'IT DAYS Larong Pinoy (Tug of War)', 16, '', '2026-09-20 10:57:00', '2026-09-20 14:00:00', 'approved', 2, '2026-09-15 22:57:38', '2026-09-16 01:13:06'),
(15, 'IT DAYS CODM', 13, '', '2026-09-21 11:16:00', '2026-09-21 13:19:00', 'approved', 2, '2026-09-15 23:12:28', '2026-09-15 23:12:34'),
(16, 'IT DAYS Video Competition', 20, '', '2026-09-18 00:35:00', '2026-09-18 16:36:00', 'approved', 2, '2026-09-15 23:31:11', '2026-09-16 01:11:24');

-- --------------------------------------------------------

--
-- Table structure for table `event_attendance`
--

CREATE TABLE `event_attendance` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `checked_in_at` timestamp NULL DEFAULT NULL,
  `checked_out_at` timestamp NULL DEFAULT NULL,
  `status` enum('present','late','absent','excused') NOT NULL DEFAULT 'present',
  `scanned_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_attendance`
--

INSERT INTO `event_attendance` (`id`, `event_id`, `student_id`, `checked_in_at`, `checked_out_at`, `status`, `scanned_by`, `created_at`) VALUES
(1, 2, 10, '2026-09-16 02:41:22', '2026-09-16 02:52:40', 'present', 2, '2026-09-16 02:20:06'),
(2, 2, 6, '2026-09-16 02:40:02', '2026-09-16 02:49:42', 'present', 2, '2026-09-16 02:40:02');

-- --------------------------------------------------------

--
-- Table structure for table `event_attendance_settings`
--

CREATE TABLE `event_attendance_settings` (
  `event_id` int(11) NOT NULL,
  `mode` enum('closed','check_in','check_out') NOT NULL DEFAULT 'closed',
  `updated_by` int(11) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_attendance_settings`
--

INSERT INTO `event_attendance_settings` (`event_id`, `mode`, `updated_by`, `updated_at`) VALUES
(2, 'check_in', 2, '2026-09-16 02:57:10');

-- --------------------------------------------------------

--
-- Table structure for table `event_criterion_scores`
--

CREATE TABLE `event_criterion_scores` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `criterion_id` int(11) NOT NULL,
  `participant_id` int(11) NOT NULL,
  `judge_id` int(11) NOT NULL,
  `score` decimal(10,2) NOT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_files`
--

CREATE TABLE `event_files` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `storage_name` varchar(255) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `file_size` int(10) UNSIGNED NOT NULL,
  `is_cover` tinyint(1) NOT NULL DEFAULT 0,
  `uploaded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_files`
--

INSERT INTO `event_files` (`id`, `event_id`, `original_name`, `storage_name`, `mime_type`, `file_size`, `is_cover`, `uploaded_by`, `created_at`) VALUES
(5, 2, 'prog.jpg', '85ee80ee906678d66772e55a0b7fb3b1.jpg', 'image/jpeg', 362198, 1, 2, '2026-09-16 01:09:24'),
(6, 2, 'progg.jpg', 'a5af5c30120ca216d2e9db1696b7140d.jpg', 'image/jpeg', 371563, 0, 2, '2026-09-16 01:09:24'),
(7, 1, 'basket.jpg', '8e06993486ec28efd7aaa652c220b383.jpg', 'image/jpeg', 441897, 1, 2, '2026-09-16 01:09:45'),
(8, 15, 'codm.jpg', '71d71f03d607ae565cb40e1e501f9ca9.jpg', 'image/jpeg', 266184, 1, 2, '2026-09-16 01:09:55'),
(9, 16, 'dance.jpg', '6647d989977550e4434f4f4d0f31ee21.jpg', 'image/jpeg', 843930, 1, 2, '2026-09-16 01:11:24'),
(10, 14, '_DSC8058.jpg', 'aa38dc3f52bb0aef1a0cbd7de69aab8c.jpg', 'image/jpeg', 379486, 1, 2, '2026-09-16 01:12:57'),
(12, 13, 'vb.jpg', 'c8104cb140d740e520f84dc3a37fecf6.jpg', 'image/jpeg', 832132, 1, 2, '2026-09-16 01:18:14');

-- --------------------------------------------------------

--
-- Table structure for table `event_judges`
--

CREATE TABLE `event_judges` (
  `event_id` int(11) NOT NULL,
  `judge_id` int(11) NOT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_judges`
--

INSERT INTO `event_judges` (`event_id`, `judge_id`, `assigned_by`, `assigned_at`) VALUES
(16, 9, 2, '2026-09-16 01:11:24');

-- --------------------------------------------------------

--
-- Table structure for table `event_participants`
--

CREATE TABLE `event_participants` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `team_name` varchar(150) DEFAULT NULL,
  `participant_type` varchar(20) NOT NULL DEFAULT 'team',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_participants`
--

INSERT INTO `event_participants` (`id`, `event_id`, `name`, `team_name`, `participant_type`, `created_at`) VALUES
(1, 15, 'TEAM TAMUMAN', 'TEAM TAMUMAN', 'team', '2026-09-15 23:12:28'),
(2, 2, 'TEAM TAMUMAN', 'TEAM TAMUMAN', 'team', '2026-09-15 23:13:17'),
(3, 2, 'TEAM ISDA', 'TEAM ISDA', 'team', '2026-09-15 23:14:00'),
(4, 2, 'TEAM PRUTAS', 'TEAM PRUTAS', 'team', '2026-09-15 23:14:00'),
(5, 16, 'TEAM TAMUMAN', 'TEAM TAMUMAN', 'team', '2026-09-15 23:32:14'),
(6, 16, 'TEAM ISDA', 'TEAM ISDA', 'team', '2026-09-15 23:32:14'),
(7, 16, 'TEAM LION', 'TEAM LION', 'team', '2026-09-15 23:32:14'),
(8, 13, 'TEAM TAMUMAN', 'TEAM TAMUMAN', 'team', '2026-09-15 23:56:06'),
(9, 13, 'TEAM LION', 'TEAM LION', 'team', '2026-09-15 23:56:18'),
(10, 13, 'TEAM ISDA', 'TEAM ISDA', 'team', '2026-09-15 23:56:18');

-- --------------------------------------------------------

--
-- Table structure for table `event_participant_members`
--

CREATE TABLE `event_participant_members` (
  `id` int(11) NOT NULL,
  `participant_id` int(11) NOT NULL,
  `member_name` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_participant_members`
--

INSERT INTO `event_participant_members` (`id`, `participant_id`, `member_name`) VALUES
(42, 1, 'mark'),
(43, 1, 'adrian'),
(44, 1, 'ivon'),
(45, 1, 'james'),
(46, 6, 'test'),
(47, 6, 'test'),
(48, 6, 'test'),
(49, 5, 'test'),
(50, 5, 'test'),
(51, 5, 'test'),
(52, 7, 'test'),
(53, 7, 'test'),
(54, 7, 'test'),
(55, 2, 'test. test. test'),
(56, 4, 'test. test. test'),
(57, 3, 'test. test. test');

-- --------------------------------------------------------

--
-- Table structure for table `event_results`
--

CREATE TABLE `event_results` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `participant_id` int(11) NOT NULL,
  `rank_position` int(11) DEFAULT NULL,
  `score` decimal(10,2) DEFAULT NULL,
  `is_winner` tinyint(1) NOT NULL DEFAULT 0,
  `notes` varchar(500) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_results`
--

INSERT INTO `event_results` (`id`, `event_id`, `participant_id`, `rank_position`, `score`, `is_winner`, `notes`, `updated_by`, `updated_at`) VALUES
(2, 15, 1, 1, 23.00, 0, 'yes', 2, '2026-09-15 23:12:51'),
(7, 2, 2, 1, 23.00, 1, 'yes', 7, '2026-09-15 23:55:12'),
(8, 2, 4, 2, 22.00, 0, 'yes', 7, '2026-09-15 23:55:12'),
(9, 2, 3, 3, 21.00, 0, 'yes', 7, '2026-09-15 23:55:12'),
(10, 16, 6, 1, 90.00, 1, NULL, 9, '2026-09-15 23:41:29'),
(11, 16, 5, 2, 80.00, 0, NULL, 9, '2026-09-15 23:41:29'),
(12, 16, 7, 3, 70.00, 0, NULL, 9, '2026-09-15 23:41:29'),
(22, 13, 9, 1, 14.00, 1, NULL, 5, '2026-09-15 23:57:51'),
(23, 13, 10, 2, 12.00, 0, NULL, 5, '2026-09-15 23:57:51'),
(24, 13, 8, 3, 10.00, 0, NULL, 5, '2026-09-15 23:57:51');

-- --------------------------------------------------------

--
-- Table structure for table `event_staff`
--

CREATE TABLE `event_staff` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_staff`
--

INSERT INTO `event_staff` (`id`, `event_id`, `staff_id`) VALUES
(51, 14, 8),
(55, 13, 5),
(56, 2, 7);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role` enum('superadmin','admin','staff','judge','student') NOT NULL DEFAULT 'student',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `email`, `role`, `created_at`) VALUES
(1, 'superadmin', '$2y$10$Wd9GrizYdFLRd6BSE.ema.NX6/Gd9f98ihAmRCBjQA.sOYvboUEOu', 'Super Admin', 'super@test.com', 'superadmin', '2026-09-15 21:30:53'),
(2, 'admin', '$2y$10$q2tFoi.8aPg22.JqSzYwWuT9HnesTrTp5UWKB4kaZ0HFBu8PI1jPm', 'Main Admin', 'admin@test.com', 'admin', '2026-09-15 21:30:53'),
(3, 'staff1', '$2y$10$q2tFoi.8aPg22.JqSzYwWuT9HnesTrTp5UWKB4kaZ0HFBu8PI1jPm', 'Prof. Reyes', 'reyes@test.com', 'staff', '2026-09-15 21:30:53'),
(4, 'staff2', '$2y$10$q2tFoi.8aPg22.JqSzYwWuT9HnesTrTp5UWKB4kaZ0HFBu8PI1jPm', 'Ms. Cruz', 'cruz@test.com', 'staff', '2026-09-15 21:30:53'),
(5, 'staff3', '$2y$10$q2tFoi.8aPg22.JqSzYwWuT9HnesTrTp5UWKB4kaZ0HFBu8PI1jPm', 'Mr. Santos', 'santos@test.com', 'staff', '2026-09-15 21:30:53'),
(6, 'student1', '$2y$10$WV9euB9x63hNd8LLf/l/W.9e5Bo04fqT8.sciws8dV6EuGMLZxDNG', 'Juan Dela Cruz', 'juan@test.com', 'student', '2026-09-15 21:30:53'),
(7, 'mark', '$2y$10$JlUDny8cXMJSk5aiLqxL1OpzmagUuAvA2CL97D3Acl4ifYglLRac2', 'Mark Lumongsod', '', 'staff', '2026-09-15 22:46:43'),
(8, 'test', '$2y$10$/QgJLZKlF4v9UCMswc5s8OKT6DymsU5gQ/H/hiaWPJiBHZWIAGXAa', 'test', '', 'staff', '2026-09-15 22:56:44'),
(9, 'judgemarcos', '$2y$10$QQ7iUpITTlYdSRcfd6M3reVGMILk5DPKNwgTZ.Fuk0/TMG.N/p0ae', 'Judge Marcos', '', 'judge', '2026-09-15 23:27:40'),
(10, 'student', '$2y$10$lIpkXlRv6gdiEU4/M22r/.bXfzMBdQsOJm89eUz/SQUU2uns87sH.', 'student', '', 'student', '2026-09-16 02:09:45');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `category_staff`
--
ALTER TABLE `category_staff`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cat_staff` (`category_id`,`staff_id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `criteria`
--
ALTER TABLE `criteria`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_id` (`event_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_events_start` (`start_datetime`);

--
-- Indexes for table `event_attendance`
--
ALTER TABLE `event_attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_event_student` (`event_id`,`student_id`),
  ADD KEY `idx_event_attendance_event` (`event_id`),
  ADD KEY `idx_event_attendance_student` (`student_id`),
  ADD KEY `fk_event_attendance_scanned_by` (`scanned_by`);

--
-- Indexes for table `event_attendance_settings`
--
ALTER TABLE `event_attendance_settings`
  ADD PRIMARY KEY (`event_id`),
  ADD KEY `idx_updated_at` (`updated_at`),
  ADD KEY `fk_event_attendance_settings_user` (`updated_by`);

--
-- Indexes for table `event_criterion_scores`
--
ALTER TABLE `event_criterion_scores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_criterion_score` (`event_id`,`criterion_id`,`participant_id`,`judge_id`),
  ADD KEY `idx_criterion_scores_event` (`event_id`),
  ADD KEY `idx_criterion_scores_judge` (`judge_id`),
  ADD KEY `fk_criterion_scores_criterion` (`criterion_id`),
  ADD KEY `fk_criterion_scores_participant` (`participant_id`);

--
-- Indexes for table `event_files`
--
ALTER TABLE `event_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_event_files_event` (`event_id`),
  ADD KEY `idx_event_files_cover` (`event_id`,`is_cover`),
  ADD KEY `fk_event_files_user` (`uploaded_by`);

--
-- Indexes for table `event_judges`
--
ALTER TABLE `event_judges`
  ADD PRIMARY KEY (`event_id`,`judge_id`),
  ADD KEY `idx_event_judges_judge` (`judge_id`),
  ADD KEY `fk_event_judges_assigned_by` (`assigned_by`);

--
-- Indexes for table `event_participants`
--
ALTER TABLE `event_participants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_event_participant_name` (`event_id`,`name`),
  ADD KEY `idx_event_participants_event` (`event_id`);

--
-- Indexes for table `event_participant_members`
--
ALTER TABLE `event_participant_members`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_participant_members_participant` (`participant_id`);

--
-- Indexes for table `event_results`
--
ALTER TABLE `event_results`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_event_result_participant` (`event_id`,`participant_id`),
  ADD KEY `idx_event_results_event` (`event_id`),
  ADD KEY `fk_event_results_participant` (`participant_id`),
  ADD KEY `fk_event_results_user` (`updated_by`);

--
-- Indexes for table `event_staff`
--
ALTER TABLE `event_staff`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `category_staff`
--
ALTER TABLE `category_staff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `criteria`
--
ALTER TABLE `criteria`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `event_attendance`
--
ALTER TABLE `event_attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `event_criterion_scores`
--
ALTER TABLE `event_criterion_scores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `event_files`
--
ALTER TABLE `event_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `event_participants`
--
ALTER TABLE `event_participants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `event_participant_members`
--
ALTER TABLE `event_participant_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- AUTO_INCREMENT for table `event_results`
--
ALTER TABLE `event_results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `event_staff`
--
ALTER TABLE `event_staff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `category_staff`
--
ALTER TABLE `category_staff`
  ADD CONSTRAINT `category_staff_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `category_staff_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `criteria`
--
ALTER TABLE `criteria`
  ADD CONSTRAINT `criteria_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `events_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  ADD CONSTRAINT `events_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `event_attendance`
--
ALTER TABLE `event_attendance`
  ADD CONSTRAINT `fk_event_attendance_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_event_attendance_scanned_by` FOREIGN KEY (`scanned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_event_attendance_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `event_attendance_settings`
--
ALTER TABLE `event_attendance_settings`
  ADD CONSTRAINT `fk_event_attendance_settings_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_event_attendance_settings_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `event_criterion_scores`
--
ALTER TABLE `event_criterion_scores`
  ADD CONSTRAINT `fk_criterion_scores_criterion` FOREIGN KEY (`criterion_id`) REFERENCES `criteria` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_criterion_scores_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_criterion_scores_judge` FOREIGN KEY (`judge_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_criterion_scores_participant` FOREIGN KEY (`participant_id`) REFERENCES `event_participants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `event_files`
--
ALTER TABLE `event_files`
  ADD CONSTRAINT `fk_event_files_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_event_files_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `event_judges`
--
ALTER TABLE `event_judges`
  ADD CONSTRAINT `fk_event_judges_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_event_judges_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_event_judges_judge` FOREIGN KEY (`judge_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `event_participants`
--
ALTER TABLE `event_participants`
  ADD CONSTRAINT `fk_event_participants_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `event_participant_members`
--
ALTER TABLE `event_participant_members`
  ADD CONSTRAINT `fk_participant_members_participant` FOREIGN KEY (`participant_id`) REFERENCES `event_participants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `event_results`
--
ALTER TABLE `event_results`
  ADD CONSTRAINT `fk_event_results_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_event_results_participant` FOREIGN KEY (`participant_id`) REFERENCES `event_participants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_event_results_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `event_staff`
--
ALTER TABLE `event_staff`
  ADD CONSTRAINT `event_staff_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_staff_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
