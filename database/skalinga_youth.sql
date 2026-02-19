-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 19, 2026 at 04:50 PM
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
-- Database: `skalinga_youth`
--

-- --------------------------------------------------------

--
-- Stand-in structure for view `active_users_by_barangay`
-- (See below for the actual view)
--
CREATE TABLE `active_users_by_barangay` (
`barangay` varchar(100)
,`active_count` bigint(21)
,`gender_diversity` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL COMMENT 'Unique admin ID',
  `username` varchar(50) NOT NULL COMMENT 'Admin username for login',
  `email` varchar(255) NOT NULL COMMENT 'Admin email address',
  `password_hash` varchar(255) NOT NULL COMMENT 'Bcrypt hashed password',
  `role` enum('superadmin','staff') DEFAULT 'staff' COMMENT 'Admin role (superadmin has full access)',
  `status` enum('active','inactive') DEFAULT 'active' COMMENT 'Account status',
  `last_login` timestamp NULL DEFAULT NULL COMMENT 'Last login timestamp',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Account creation timestamp',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Last update timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Admin accounts and access control';

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `email`, `password_hash`, `role`, `status`, `last_login`, `created_at`, `updated_at`) VALUES
(4, 'admin', 'admin@skalinga.local', '$2y$10$LK9f0DKfW25j2lfWGANRGuyaZkjznOAefR3W1WBYRdLp1z8ixsplm', 'superadmin', 'active', '2026-02-18 14:46:52', '2026-02-17 08:02:47', '2026-02-18 14:46:52');

-- --------------------------------------------------------

--
-- Table structure for table `borrow_records`
--

CREATE TABLE `borrow_records` (
  `borrow_id` int(11) NOT NULL COMMENT 'Unique borrow transaction ID',
  `item_id` varchar(50) NOT NULL COMMENT 'Foreign key to resources table',
  `member_id` varchar(50) NOT NULL COMMENT 'Youth member ID (SK-YYYY-####)',
  `borrower_name` varchar(255) NOT NULL COMMENT 'Full name of borrower',
  `quantity` int(11) DEFAULT 1,
  `borrow_date` datetime DEFAULT current_timestamp() COMMENT 'Date and time item was borrowed',
  `due_date` datetime NOT NULL COMMENT 'Expected return date',
  `return_date` datetime DEFAULT NULL COMMENT 'Actual return date (NULL if still borrowed)',
  `status` enum('Borrowed','Returned','Overdue') DEFAULT 'Borrowed' COMMENT 'Current borrowing status',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Record creation timestamp',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Last update timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Records of borrowed resources and returns';

--
-- Dumping data for table `borrow_records`
--

INSERT INTO `borrow_records` (`borrow_id`, `item_id`, `member_id`, `borrower_name`, `quantity`, `borrow_date`, `due_date`, `return_date`, `status`, `created_at`, `updated_at`) VALUES
(2, 'ITEM-20260217-717', 'SK-2026-7402', 'Arby Barnuevo', 1, '2026-02-17 18:10:44', '2026-02-18 00:00:00', NULL, 'Borrowed', '2026-02-17 10:10:44', '2026-02-17 10:10:44'),
(3, 'ITEM-20260217-412', 'SK-2026-5315', 'Abdul Malik Disomimba', 49, '2026-02-17 18:25:43', '2026-02-18 00:00:00', NULL, 'Borrowed', '2026-02-17 10:25:43', '2026-02-17 10:25:43');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL COMMENT 'Auto-incremented ID',
  `event_id` varchar(20) NOT NULL COMMENT 'Event ID (EVT-YYYY-NNNN)',
  `title` varchar(255) NOT NULL COMMENT 'Event title',
  `description` longtext DEFAULT NULL COMMENT 'Event description',
  `event_type` varchar(100) NOT NULL COMMENT 'Event type (Training, Sports, Cultural, Community, Other)',
  `date` date NOT NULL COMMENT 'Event date',
  `start_time` time NOT NULL COMMENT 'Event start time',
  `end_time` time DEFAULT NULL COMMENT 'Event end time',
  `location` varchar(255) NOT NULL COMMENT 'Event location',
  `capacity` int(11) DEFAULT 0 COMMENT 'Maximum number of attendees',
  `registered_count` int(11) DEFAULT 0 COMMENT 'Current number of registrations',
  `registration_link` varchar(500) DEFAULT NULL COMMENT 'Registration/Google Form link',
  `image_path` varchar(500) DEFAULT NULL COMMENT 'Path to event image file',
  `status` enum('Upcoming','Ongoing','Past') DEFAULT 'Upcoming' COMMENT 'Event status',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Event creation timestamp',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Last update timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SK Events management table with image support';

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `event_id`, `title`, `description`, `event_type`, `date`, `start_time`, `end_time`, `location`, `capacity`, `registered_count`, `registration_link`, `image_path`, `status`, `created_at`, `updated_at`) VALUES
(2, 'EVT-2026-0002', 'SK League 2026', 'register now', 'Sports', '2026-03-10', '06:00:00', '17:00:00', 'San Antonio Covered Court', 100, 0, '', 'assets/images/events/event_1770315730_basketball.jpg', 'Upcoming', '2026-02-05 18:22:10', '2026-02-17 07:43:38');

-- --------------------------------------------------------

--
-- Table structure for table `incidents`
--

CREATE TABLE `incidents` (
  `id` int(11) NOT NULL,
  `member_id` varchar(50) NOT NULL,
  `category` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `location` varchar(255) NOT NULL,
  `urgency` varchar(20) NOT NULL DEFAULT 'low',
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `photo_path` varchar(255) DEFAULT NULL,
  `submitted_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_date` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `admin_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `incidents`
--

INSERT INTO `incidents` (`id`, `member_id`, `category`, `description`, `location`, `urgency`, `status`, `photo_path`, `submitted_date`, `updated_date`, `admin_notes`) VALUES
(3, 'SK-2026-7402', 'bullying', 'haha', 'Saint Francis 7', 'emergency', 'closed', 'uploads/incidents/incident_1771351344_9807.png', '2026-02-17 18:02:24', '2026-02-17 18:09:55', ''),
(4, 'SK-2026-5315', 'bullying', 'ga', 'Saint Francis 7', 'medium', 'pending', '[\"uploads\\/incidents\\/incident_1771352468_2936.jpg\"]', '2026-02-17 18:21:08', '2026-02-17 18:21:08', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL COMMENT 'Reset request ID',
  `user_id` int(11) NOT NULL COMMENT 'Foreign key to users table',
  `otp_code` varchar(6) DEFAULT NULL COMMENT 'One-time password (6-digit code)',
  `otp_attempts` int(11) DEFAULT 0 COMMENT 'Number of failed OTP attempts',
  `is_used` tinyint(1) DEFAULT 0 COMMENT 'Whether OTP has been used',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'OTP creation timestamp',
  `expires_at` timestamp NULL DEFAULT NULL COMMENT 'OTP expiration time (15 mins)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Password reset OTP requests';

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `user_id`, `otp_code`, `otp_attempts`, `is_used`, `created_at`, `expires_at`) VALUES
(5, 8, '226051', 0, 0, '2026-02-19 15:47:58', '2026-02-19 09:02:58');

-- --------------------------------------------------------

--
-- Table structure for table `printing_requests`
--

CREATE TABLE `printing_requests` (
  `request_id` int(11) NOT NULL,
  `member_id` varchar(50) NOT NULL,
  `member_name` varchar(255) NOT NULL,
  `document_title` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `print_type` enum('Black & White','Colored') NOT NULL DEFAULT 'Black & White',
  `paper_size` enum('A4','Short','Long') NOT NULL DEFAULT 'A4',
  `copies` int(11) NOT NULL DEFAULT 1,
  `status` enum('Pending','Printing','Completed','Claimed') NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `claimed_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `printing_requests`
--

INSERT INTO `printing_requests` (`request_id`, `member_id`, `member_name`, `document_title`, `file_path`, `file_name`, `file_size`, `print_type`, `paper_size`, `copies`, `status`, `created_at`, `updated_at`, `claimed_at`, `notes`) VALUES
(8, 'SK-2026-7402', 'Arby Barnuevo', 'Research Project', 'uploads/printing/1771349006_8ea622c9.docx', 'Calendar Posting Holidays and Observances.docx', 14388, 'Black & White', 'Short', 1, 'Completed', '2026-02-17 17:23:26', '2026-02-17 17:25:32', NULL, NULL),
(9, 'SK-2026-5315', 'Abdul Malik Disomimba', 'Research Project', 'uploads/printing/1771424772_f21576ae.docx', 'AWS_Cloud_Club_Marketing_Office_Official_Structure (1).docx', 38403, 'Colored', 'A4', 34, 'Pending', '2026-02-18 14:26:12', '2026-02-18 14:26:12', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `resources`
--

CREATE TABLE `resources` (
  `item_id` varchar(50) NOT NULL COMMENT 'Unique item identifier (ITEM-YYYYMMDD-###)',
  `name` varchar(255) NOT NULL COMMENT 'Item name (e.g., Projector, Microphone)',
  `description` text DEFAULT NULL COMMENT 'Detailed description, brand, condition, etc.',
  `quantity` int(11) NOT NULL DEFAULT 0 COMMENT 'Total quantity of this item',
  `available` int(11) NOT NULL DEFAULT 0 COMMENT 'Currently available units for borrowing',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Resource creation timestamp',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Last update timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Borrowable resources and equipment inventory';

--
-- Dumping data for table `resources`
--

INSERT INTO `resources` (`item_id`, `name`, `description`, `quantity`, `available`, `created_at`, `updated_at`) VALUES
('ITEM-20260217-334', 'Basketball', 'NBA Molten', 5, 5, '2026-02-17 09:27:26', '2026-02-17 09:27:26'),
('ITEM-20260217-412', 'Monobloc Chairs', 'Uratex Pink Monobloc Chairs', 50, 1, '2026-02-17 09:25:56', '2026-02-17 10:25:43'),
('ITEM-20260217-717', 'Speaker', 'JBL 26 Inch BT Speaker', 2, 1, '2026-02-17 09:28:53', '2026-02-17 10:10:44'),
('ITEM-20260217-841', 'Projector', 'Epson110', 2, 2, '2026-02-17 09:32:59', '2026-02-17 09:32:59');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL COMMENT 'Unique user ID',
  `email` varchar(255) NOT NULL COMMENT 'Email address (login credential)',
  `password_hash` varchar(255) NOT NULL COMMENT 'Bcrypt hashed password',
  `member_id` varchar(20) NOT NULL COMMENT 'Unique member ID (SK-YYYY-NNNN)',
  `status` enum('active','inactive','suspended') DEFAULT 'active' COMMENT 'Account status',
  `email_verified` tinyint(1) DEFAULT 0 COMMENT 'Email verification status',
  `email_verified_at` timestamp NULL DEFAULT NULL COMMENT 'Email verification timestamp',
  `last_login` timestamp NULL DEFAULT NULL COMMENT 'Last login timestamp',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Account creation timestamp',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Last update timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='User authentication and account management';

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password_hash`, `member_id`, `status`, `email_verified`, `email_verified_at`, `last_login`, `created_at`, `updated_at`) VALUES
(8, 'arbybarnuevo27@gmail.com', '$2y$10$Icrez0svpyp5hFyif3fjDeAeqiOcNAFJfxby0DzjpUcjCFOYQr2Kq', 'SK-2026-7402', 'active', 1, NULL, '2026-02-18 14:36:04', '2026-02-17 05:56:40', '2026-02-18 14:36:04'),
(9, 'abdulmalik@gmail.com', '$2y$10$QNBS21Vx6MZyl5brm.pOAOx6rXX2lBxsTHGfHyUmN26ANWlnCrQLG', 'SK-2026-5315', 'active', 0, NULL, '2026-02-18 14:25:07', '2026-02-17 05:58:42', '2026-02-18 14:25:07');

-- --------------------------------------------------------

--
-- Stand-in structure for view `user_profiles_full`
-- (See below for the actual view)
--
CREATE TABLE `user_profiles_full` (
`id` int(11)
,`email` varchar(255)
,`member_id` varchar(20)
,`status` enum('active','inactive','suspended')
,`user_created_at` timestamp
,`firstname` varchar(100)
,`lastname` varchar(100)
,`birthday` date
,`age` int(11)
,`gender` enum('Male','Female','Other','Prefer not to say')
,`phone` varchar(20)
,`address` longtext
,`barangay` varchar(100)
,`avatar_path` varchar(255)
,`bio` text
,`profile_updated_at` timestamp
);

-- --------------------------------------------------------

--
-- Table structure for table `youth_profiles`
--

CREATE TABLE `youth_profiles` (
  `id` int(11) NOT NULL COMMENT 'Profile record ID',
  `user_id` int(11) NOT NULL COMMENT 'Foreign key to users table',
  `firstname` varchar(100) NOT NULL COMMENT 'First name',
  `lastname` varchar(100) NOT NULL COMMENT 'Last name',
  `birthday` date DEFAULT NULL COMMENT 'Date of birth',
  `age` int(11) DEFAULT NULL COMMENT 'Age (auto-calculated from birthday)',
  `gender` enum('Male','Female','Other','Prefer not to say') DEFAULT NULL COMMENT 'Gender identity',
  `phone` varchar(20) DEFAULT NULL COMMENT 'Contact phone number (11-digit)',
  `address` longtext DEFAULT NULL COMMENT 'Full physical address',
  `barangay` varchar(100) DEFAULT 'San Antonio' COMMENT 'Barangay location',
  `avatar_path` varchar(255) DEFAULT NULL COMMENT 'Path to profile picture',
  `bio` text DEFAULT NULL COMMENT 'User biography',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Profile creation timestamp',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Last profile update timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Youth profile and personal information';

--
-- Dumping data for table `youth_profiles`
--

INSERT INTO `youth_profiles` (`id`, `user_id`, `firstname`, `lastname`, `birthday`, `age`, `gender`, `phone`, `address`, `barangay`, `avatar_path`, `bio`, `created_at`, `updated_at`) VALUES
(8, 8, 'Arby', 'Barnuevo', '2005-10-27', 20, 'Male', '09935072068', '1544 Durian St. Garcia Subd. San Antonio Binan Laguna', 'San Antonio', NULL, NULL, '2026-02-17 05:56:40', '2026-02-17 05:56:40'),
(9, 9, 'Abdul Malik', 'Disomimba', '2005-12-13', 20, '', '09171234567', '123 Simple Subd. Brgy Canlalay Binan Laguna', 'Canlalay Binan Laguna', NULL, NULL, '2026-02-17 05:58:42', '2026-02-17 05:58:42');

-- --------------------------------------------------------

--
-- Structure for view `active_users_by_barangay`
--
DROP TABLE IF EXISTS `active_users_by_barangay`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `active_users_by_barangay`  AS SELECT `p`.`barangay` AS `barangay`, count(`u`.`id`) AS `active_count`, count(distinct `p`.`gender`) AS `gender_diversity` FROM (`users` `u` join `youth_profiles` `p` on(`u`.`id` = `p`.`user_id`)) WHERE `u`.`status` = 'active' GROUP BY `p`.`barangay` ORDER BY `p`.`barangay` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `user_profiles_full`
--
DROP TABLE IF EXISTS `user_profiles_full`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `user_profiles_full`  AS SELECT `u`.`id` AS `id`, `u`.`email` AS `email`, `u`.`member_id` AS `member_id`, `u`.`status` AS `status`, `u`.`created_at` AS `user_created_at`, `p`.`firstname` AS `firstname`, `p`.`lastname` AS `lastname`, `p`.`birthday` AS `birthday`, `p`.`age` AS `age`, `p`.`gender` AS `gender`, `p`.`phone` AS `phone`, `p`.`address` AS `address`, `p`.`barangay` AS `barangay`, `p`.`avatar_path` AS `avatar_path`, `p`.`bio` AS `bio`, `p`.`updated_at` AS `profile_updated_at` FROM (`users` `u` left join `youth_profiles` `p` on(`u`.`id` = `p`.`user_id`)) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `borrow_records`
--
ALTER TABLE `borrow_records`
  ADD PRIMARY KEY (`borrow_id`),
  ADD KEY `idx_member_id` (`member_id`),
  ADD KEY `idx_item_id` (`item_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_borrow_date` (`borrow_date`),
  ADD KEY `idx_due_date` (`due_date`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `event_id` (`event_id`),
  ADD KEY `idx_event_id` (`event_id`),
  ADD KEY `idx_date` (`date`),
  ADD KEY `idx_start_time` (`start_time`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_event_type` (`event_type`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `incidents`
--
ALTER TABLE `incidents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_member_id` (`member_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_urgency` (`urgency`),
  ADD KEY `idx_submitted_date` (`submitted_date`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_otp_code` (`otp_code`),
  ADD KEY `idx_expires_at` (`expires_at`);

--
-- Indexes for table `printing_requests`
--
ALTER TABLE `printing_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `idx_member_id` (`member_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `resources`
--
ALTER TABLE `resources`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `idx_name` (`name`),
  ADD KEY `idx_available` (`available`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `member_id` (`member_id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_member_id` (`member_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `youth_profiles`
--
ALTER TABLE `youth_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_firstname` (`firstname`),
  ADD KEY `idx_lastname` (`lastname`),
  ADD KEY `idx_barangay` (`barangay`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Unique admin ID', AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `borrow_records`
--
ALTER TABLE `borrow_records`
  MODIFY `borrow_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Unique borrow transaction ID', AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Auto-incremented ID', AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `incidents`
--
ALTER TABLE `incidents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Reset request ID', AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `printing_requests`
--
ALTER TABLE `printing_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Unique user ID', AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `youth_profiles`
--
ALTER TABLE `youth_profiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Profile record ID', AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `borrow_records`
--
ALTER TABLE `borrow_records`
  ADD CONSTRAINT `borrow_records_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `resources` (`item_id`);

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `fk_password_resets_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `printing_requests`
--
ALTER TABLE `printing_requests`
  ADD CONSTRAINT `printing_requests_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `users` (`member_id`) ON DELETE CASCADE;

--
-- Constraints for table `youth_profiles`
--
ALTER TABLE `youth_profiles`
  ADD CONSTRAINT `fk_youth_profiles_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
