<?php
$host = getenv('DB_HOST');
$port = (int)getenv('DB_PORT');
$user = getenv('DB_USER');
$pass = getenv('DB_PASSWORD');
$name = getenv('DB_NAME');

$conn = mysqli_init();
mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL);
mysqli_real_connect($conn, $host, $user, $pass, $name, $port, NULL, MYSQLI_CLIENT_SSL);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$queries = [
"CREATE TABLE IF NOT EXISTS `users` (`id` int(11) NOT NULL AUTO_INCREMENT, `username` varchar(50) NOT NULL, `email` varchar(100) NOT NULL, `password_hash` varchar(255) NOT NULL, `full_name` varchar(100) DEFAULT NULL, `bio` text DEFAULT NULL, `profile_pic` varchar(255) DEFAULT 'default.jpg', `cover_photo` varchar(255) DEFAULT 'default_cover.jpg', `location` varchar(255) DEFAULT NULL, `birthdate` date DEFAULT NULL, `education` varchar(255) DEFAULT NULL, `pinned_post_id` int(11) DEFAULT NULL, `show_location_on_map` tinyint(1) DEFAULT 0, `last_latitude` decimal(10,8) DEFAULT NULL, `last_longitude` decimal(11,8) DEFAULT NULL, `email_verified` tinyint(4) DEFAULT 1, `verification_token` varchar(255) DEFAULT NULL, `token_expires` timestamp NULL DEFAULT NULL, `created_at` timestamp NOT NULL DEFAULT current_timestamp(), PRIMARY KEY (`id`), UNIQUE KEY `username` (`username`), UNIQUE KEY `email` (`email`))",

"CREATE TABLE IF NOT EXISTS `posts` (`id` int(11) NOT NULL AUTO_INCREMENT, `user_id` int(11) NOT NULL, `content` text DEFAULT NULL, `image_url` varchar(255) DEFAULT NULL, `video_url` varchar(255) DEFAULT NULL, `location_name` varchar(255) DEFAULT NULL, `location_latitude` decimal(10,8) DEFAULT NULL, `location_longitude` decimal(11,8) DEFAULT NULL, `privacy` enum('public','friends','private') DEFAULT 'public', `created_at` timestamp NOT NULL DEFAULT current_timestamp(), `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(), PRIMARY KEY (`id`), KEY `idx_posts_user_id` (`user_id`))",

"CREATE TABLE IF NOT EXISTS `comments` (`id` int(11) NOT NULL AUTO_INCREMENT, `post_id` int(11) NOT NULL, `user_id` int(11) NOT NULL, `comment_text` text NOT NULL, `created_at` timestamp NOT NULL DEFAULT current_timestamp(), `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(), `is_deleted` tinyint(1) DEFAULT 0, PRIMARY KEY (`id`), KEY `idx_comments_post_id` (`post_id`), KEY `idx_comments_user_id` (`user_id`))",

"CREATE TABLE IF NOT EXISTS `likes` (`id` int(11) NOT NULL AUTO_INCREMENT, `post_id` int(11) NOT NULL, `user_id` int(11) NOT NULL, `created_at` timestamp NOT NULL DEFAULT current_timestamp(), PRIMARY KEY (`id`), UNIQUE KEY `unique_like` (`post_id`,`user_id`), KEY `fk_likes_user` (`user_id`))",

"CREATE TABLE IF NOT EXISTS `friends_followers` (`id` int(11) NOT NULL AUTO_INCREMENT, `follower_id` int(11) NOT NULL, `following_id` int(11) NOT NULL, `status` enum('pending','accepted') DEFAULT 'pending', `created_at` timestamp NOT NULL DEFAULT current_timestamp(), PRIMARY KEY (`id`), UNIQUE KEY `unique_follow` (`follower_id`,`following_id`))",

"CREATE TABLE IF NOT EXISTS `hidden_posts` (`id` int(11) NOT NULL AUTO_INCREMENT, `user_id` int(11) NOT NULL, `post_id` int(11) NOT NULL, `created_at` timestamp NOT NULL DEFAULT current_timestamp(), PRIMARY KEY (`id`), UNIQUE KEY `unique_hide` (`user_id`,`post_id`))",

"CREATE TABLE IF NOT EXISTS `reported_posts` (`id` int(11) NOT NULL AUTO_INCREMENT, `post_id` int(11) NOT NULL, `reporter_id` int(11) NOT NULL, `reason` text NOT NULL, `status` enum('pending','reviewed','dismissed') DEFAULT 'pending', `created_at` timestamp NOT NULL DEFAULT current_timestamp(), PRIMARY KEY (`id`))",

"CREATE TABLE IF NOT EXISTS `saved_posts` (`id` int(11) NOT NULL AUTO_INCREMENT, `user_id` int(11) NOT NULL, `post_id` int(11) NOT NULL, `created_at` timestamp NOT NULL DEFAULT current_timestamp(), PRIMARY KEY (`id`), UNIQUE KEY `unique_save` (`user_id`,`post_id`))",

"CREATE TABLE IF NOT EXISTS `user_sessions` (`id` int(11) NOT NULL AUTO_INCREMENT, `user_id` int(11) NOT NULL, `session_token` varchar(255) NOT NULL, `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(), `created_at` timestamp NOT NULL DEFAULT current_timestamp(), PRIMARY KEY (`id`), UNIQUE KEY `session_token` (`session_token`))",

"INSERT IGNORE INTO `users` (`id`,`username`,`email`,`password_hash`,`full_name`,`profile_pic`,`cover_photo`,`email_verified`) VALUES (1,'admin','admin@travelmate.com','\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Admin User','default.jpg','default_cover.jpg',1),(4,'Raffy','mjeherrera@tip.edu.ph','\$2y\$10\$t0GHsxCmVOATNuTVC4gGke4z2vGuAZyIBrbvOdHDuyOflggrt76mi','JOHN EZEKIEL HERRERA','default.jpg','default_cover.jpg',1),(5,'Kiel','mkjgalicia@tip.edu.ph','\$2y\$10\$.bIeN2YuGfSCEiJokjJt7OueLxiXinFh4yX6iWHL6kjMvCF5mj/ym','KIEL JERBI GALICIA','default.jpg','default_cover.jpg',1)"
];

$errors = [];
foreach ($queries as $query) {
    if (!$conn->query($query)) {
        $errors[] = $conn->error . " | Query: " . substr($query, 0, 50);
    }
}

if (empty($errors)) {
    echo "All tables created and data inserted successfully!";
} else {
    echo "Errors:<br>" . implode("<br>", $errors);
}
?>
