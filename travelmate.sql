-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 14, 2026 at 08:55 AM
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
-- Database: `travelmate`
--

-- --------------------------------------------------------

--
-- Table structure for table `comments`
--

CREATE TABLE `comments` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment_text` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `comments`
--
DELIMITER $$
CREATE TRIGGER `update_comments_timestamp` BEFORE UPDATE ON `comments` FOR EACH ROW BEGIN
    SET NEW.`updated_at` = CURRENT_TIMESTAMP;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `friends_followers`
--

CREATE TABLE `friends_followers` (
  `id` int(11) NOT NULL,
  `follower_id` int(11) NOT NULL,
  `following_id` int(11) NOT NULL,
  `status` enum('pending','accepted') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `friends_followers`
--

INSERT INTO `friends_followers` (`id`, `follower_id`, `following_id`, `status`, `created_at`) VALUES
(1, 4, 5, 'accepted', '2026-04-14 04:30:47');

-- --------------------------------------------------------

--
-- Stand-in structure for view `friends_list`
-- (See below for the actual view)
--
CREATE TABLE `friends_list` (
`user_id` int(11)
,`friend_id` int(11)
,`friend_username` varchar(50)
,`friend_full_name` varchar(100)
,`friend_profile_pic` varchar(255)
,`status` varchar(8)
,`friendship_date` timestamp
);

-- --------------------------------------------------------

--
-- Table structure for table `hidden_posts`
--

CREATE TABLE `hidden_posts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `likes`
--

CREATE TABLE `likes` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `posts`
--

CREATE TABLE `posts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `content` text DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `video_url` varchar(255) DEFAULT NULL,
  `location_name` varchar(255) DEFAULT NULL,
  `location_latitude` decimal(10,8) DEFAULT NULL,
  `location_longitude` decimal(11,8) DEFAULT NULL,
  `privacy` enum('public','friends','private') DEFAULT 'public',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `posts`
--
DELIMITER $$
CREATE TRIGGER `update_posts_timestamp` BEFORE UPDATE ON `posts` FOR EACH ROW BEGIN
    SET NEW.`updated_at` = CURRENT_TIMESTAMP;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Stand-in structure for view `posts_with_likes`
-- (See below for the actual view)
--
CREATE TABLE `posts_with_likes` (
`id` int(11)
,`user_id` int(11)
,`content` text
,`image_url` varchar(255)
,`video_url` varchar(255)
,`location_name` varchar(255)
,`location_latitude` decimal(10,8)
,`location_longitude` decimal(11,8)
,`created_at` timestamp
,`updated_at` timestamp
,`username` varchar(50)
,`profile_pic` varchar(255)
,`likes_count` bigint(21)
,`comments_count` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `reported_posts`
--

CREATE TABLE `reported_posts` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `reporter_id` int(11) NOT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','reviewed','dismissed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `saved_posts`
--

CREATE TABLE `saved_posts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `profile_pic` varchar(255) DEFAULT 'default.jpg',
  `cover_photo` varchar(255) DEFAULT 'default_cover.jpg',
  `location` varchar(255) DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `education` varchar(255) DEFAULT NULL,
  `pinned_post_id` int(11) DEFAULT NULL,
  `show_location_on_map` tinyint(1) DEFAULT 0,
  `last_latitude` decimal(10,8) DEFAULT NULL,
  `last_longitude` decimal(11,8) DEFAULT NULL,
  `email_verified` tinyint(4) DEFAULT 1,
  `verification_token` varchar(255) DEFAULT NULL,
  `token_expires` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `full_name`, `bio`, `profile_pic`, `cover_photo`, `location`, `birthdate`, `education`, `pinned_post_id`, `show_location_on_map`, `last_latitude`, `last_longitude`, `email_verified`, `verification_token`, `token_expires`, `created_at`) VALUES
(1, 'admin', 'admin@travelmate.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin User', 'Welcome to TravelMate!', 'default.jpg', 'default_cover.jpg', NULL, NULL, NULL, NULL, 0, NULL, NULL, 1, NULL, NULL, '2026-04-14 03:51:02'),
(4, 'Raffy', 'mjeherrera@tip.edu.ph', '$2y$10$t0GHsxCmVOATNuTVC4gGke4z2vGuAZyIBrbvOdHDuyOflggrt76mi', 'JOHN EZEKIEL HERRERA', NULL, 'default.jpg', 'default_cover.jpg', NULL, NULL, NULL, NULL, 0, NULL, NULL, 1, NULL, NULL, '2026-04-14 03:55:12'),
(5, 'Kiel', 'mkjgalicia@tip.edu.ph', '$2y$10$.bIeN2YuGfSCEiJokjJt7OueLxiXinFh4yX6iWHL6kjMvCF5mj/ym', 'KIEL JERBI GALICIA', NULL, 'default.jpg', 'default_cover.jpg', NULL, NULL, NULL, NULL, 0, NULL, NULL, 1, NULL, NULL, '2026-04-14 03:55:46');

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_token` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure for view `friends_list`
--
DROP TABLE IF EXISTS `friends_list`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `friends_list`  AS SELECT `f`.`follower_id` AS `user_id`, `u`.`id` AS `friend_id`, `u`.`username` AS `friend_username`, `u`.`full_name` AS `friend_full_name`, `u`.`profile_pic` AS `friend_profile_pic`, `f`.`status` AS `status`, `f`.`created_at` AS `friendship_date` FROM (`friends_followers` `f` join `users` `u` on(`f`.`following_id` = `u`.`id`)) WHERE `f`.`status` = 'accepted'union select `f`.`following_id` AS `user_id`,`u`.`id` AS `friend_id`,`u`.`username` AS `friend_username`,`u`.`full_name` AS `friend_full_name`,`u`.`profile_pic` AS `friend_profile_pic`,`f`.`status` AS `status`,`f`.`created_at` AS `friendship_date` from (`friends_followers` `f` join `users` `u` on(`f`.`follower_id` = `u`.`id`)) where `f`.`status` = 'accepted'  ;

-- --------------------------------------------------------

--
-- Structure for view `posts_with_likes`
--
DROP TABLE IF EXISTS `posts_with_likes`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `posts_with_likes`  AS SELECT `p`.`id` AS `id`, `p`.`user_id` AS `user_id`, `p`.`content` AS `content`, `p`.`image_url` AS `image_url`, `p`.`video_url` AS `video_url`, `p`.`location_name` AS `location_name`, `p`.`location_latitude` AS `location_latitude`, `p`.`location_longitude` AS `location_longitude`, `p`.`created_at` AS `created_at`, `p`.`updated_at` AS `updated_at`, `u`.`username` AS `username`, `u`.`profile_pic` AS `profile_pic`, count(distinct `l`.`id`) AS `likes_count`, count(distinct `c`.`id`) AS `comments_count` FROM (((`posts` `p` join `users` `u` on(`p`.`user_id` = `u`.`id`)) left join `likes` `l` on(`p`.`id` = `l`.`post_id`)) left join `comments` `c` on(`p`.`id` = `c`.`post_id` and `c`.`is_deleted` = 0)) GROUP BY `p`.`id` ORDER BY `p`.`created_at` DESC ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_comments_post_id` (`post_id`),
  ADD KEY `idx_comments_user_id` (`user_id`);

--
-- Indexes for table `friends_followers`
--
ALTER TABLE `friends_followers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_follow` (`follower_id`,`following_id`),
  ADD KEY `idx_friends_follower` (`follower_id`),
  ADD KEY `idx_friends_following` (`following_id`);

--
-- Indexes for table `hidden_posts`
--
ALTER TABLE `hidden_posts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_hide` (`user_id`,`post_id`),
  ADD KEY `fk_hidden_post` (`post_id`);

--
-- Indexes for table `likes`
--
ALTER TABLE `likes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_like` (`post_id`,`user_id`),
  ADD KEY `fk_likes_user` (`user_id`);

--
-- Indexes for table `posts`
--
ALTER TABLE `posts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_posts_user_id` (`user_id`);

--
-- Indexes for table `reported_posts`
--
ALTER TABLE `reported_posts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_reported_post` (`post_id`),
  ADD KEY `fk_reported_reporter` (`reporter_id`);

--
-- Indexes for table `saved_posts`
--
ALTER TABLE `saved_posts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_save` (`user_id`,`post_id`),
  ADD KEY `fk_saved_post` (`post_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_token` (`session_token`),
  ADD KEY `fk_session_user` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `comments`
--
ALTER TABLE `comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `friends_followers`
--
ALTER TABLE `friends_followers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `hidden_posts`
--
ALTER TABLE `hidden_posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `likes`
--
ALTER TABLE `likes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `posts`
--
ALTER TABLE `posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reported_posts`
--
ALTER TABLE `reported_posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `saved_posts`
--
ALTER TABLE `saved_posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `comments`
--
ALTER TABLE `comments`
  ADD CONSTRAINT `fk_comments_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_comments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `friends_followers`
--
ALTER TABLE `friends_followers`
  ADD CONSTRAINT `fk_friends_follower` FOREIGN KEY (`follower_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_friends_following` FOREIGN KEY (`following_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `hidden_posts`
--
ALTER TABLE `hidden_posts`
  ADD CONSTRAINT `fk_hidden_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_hidden_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `likes`
--
ALTER TABLE `likes`
  ADD CONSTRAINT `fk_likes_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_likes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `posts`
--
ALTER TABLE `posts`
  ADD CONSTRAINT `fk_posts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reported_posts`
--
ALTER TABLE `reported_posts`
  ADD CONSTRAINT `fk_reported_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reported_reporter` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `saved_posts`
--
ALTER TABLE `saved_posts`
  ADD CONSTRAINT `fk_saved_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_saved_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `fk_session_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
