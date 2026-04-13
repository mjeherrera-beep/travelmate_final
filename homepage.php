<?php
session_start();
include 'config.php';

// Create necessary tables if they don't exist
$create_saved_table = "CREATE TABLE IF NOT EXISTS saved_posts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    post_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_save (user_id, post_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
)";
mysqli_query($conn, $create_saved_table);

$create_hidden_table = "CREATE TABLE IF NOT EXISTS hidden_posts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    post_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_hide (user_id, post_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
)";
mysqli_query($conn, $create_hidden_table);

$create_reported_table = "CREATE TABLE IF NOT EXISTS reported_posts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    post_id INT NOT NULL,
    reporter_id INT NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('pending', 'reviewed', 'dismissed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE
)";
mysqli_query($conn, $create_reported_table);

if (!isset($_SESSION['user_id'])) {
    if (isset($_COOKIE['remember_token'])) {
        $token = $_COOKIE['remember_token'];
        $query = "SELECT u.* FROM users u 
                  JOIN user_sessions s ON u.id = s.user_id 
                  WHERE s.session_token = '$token' AND s.expires_at > NOW()";
        $result = mysqli_query($conn, $query);
        $user = mysqli_fetch_assoc($result);
        
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['profile_pic'] = $user['profile_pic'];
        } else {
            header("Location: index.php");
            exit();
        }
    } else {
        header("Location: index.php");
        exit();
    }
}

$current_user_id = $_SESSION['user_id'];

$user_query = "SELECT * FROM users WHERE id = $current_user_id";
$user_result = mysqli_query($conn, $user_query);
$current_user = mysqli_fetch_assoc($user_result);

// Handle create post
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_post'])) {
    $content = mysqli_real_escape_string($conn, $_POST['content']);
    $location = mysqli_real_escape_string($conn, $_POST['location']);
    $privacy = mysqli_real_escape_string($conn, $_POST['privacy']);
    $latitude = isset($_POST['latitude']) ? mysqli_real_escape_string($conn, $_POST['latitude']) : 'NULL';
    $longitude = isset($_POST['longitude']) ? mysqli_real_escape_string($conn, $_POST['longitude']) : 'NULL';
    
    $image_url = '';
    if (isset($_FILES['post_image']) && $_FILES['post_image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['post_image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            if (!is_dir('uploads/posts')) {
                mkdir('uploads/posts', 0777, true);
            }
            $new_filename = "post_" . time() . "_" . rand(1000, 9999) . "." . $ext;
            $upload_path = "uploads/posts/" . $new_filename;
            
            if (move_uploaded_file($_FILES['post_image']['tmp_name'], $upload_path)) {
                $image_url = $upload_path;
            }
        }
    }
    
    $latitude_sql = ($latitude === 'NULL') ? 'NULL' : "'$latitude'";
    $longitude_sql = ($longitude === 'NULL') ? 'NULL' : "'$longitude'";
    
    $query = "INSERT INTO posts (user_id, content, image_url, location_name, location_latitude, location_longitude, privacy) 
              VALUES ($current_user_id, '$content', '$image_url', '$location', $latitude_sql, $longitude_sql, '$privacy')";
    mysqli_query($conn, $query);
    header("Location: homepage.php");
    exit();
}

// Handle save/hide/report actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Save post
    if (isset($_POST['save_post'])) {
        $post_id = (int)$_POST['post_id'];
        
        $check = "SELECT id FROM saved_posts WHERE user_id = $current_user_id AND post_id = $post_id";
        $check_result = mysqli_query($conn, $check);
        
        if (mysqli_num_rows($check_result) > 0) {
            $query = "DELETE FROM saved_posts WHERE user_id = $current_user_id AND post_id = $post_id";
            $saved = false;
        } else {
            $query = "INSERT INTO saved_posts (user_id, post_id) VALUES ($current_user_id, $post_id)";
            $saved = true;
        }
        mysqli_query($conn, $query);
        echo json_encode(['success' => true, 'saved' => $saved]);
        exit();
    }
    
    // Hide post
    if (isset($_POST['hide_post'])) {
        $post_id = (int)$_POST['post_id'];
        
        $query = "INSERT INTO hidden_posts (user_id, post_id) VALUES ($current_user_id, $post_id)";
        mysqli_query($conn, $query);
        echo json_encode(['success' => true]);
        exit();
    }
    
    // Report post
    if (isset($_POST['report_post'])) {
        $post_id = (int)$_POST['post_id'];
        $reason = mysqli_real_escape_string($conn, $_POST['reason']);
        
        $query = "INSERT INTO reported_posts (post_id, reporter_id, reason) VALUES ($post_id, $current_user_id, '$reason')";
        mysqli_query($conn, $query);
        echo json_encode(['success' => true]);
        exit();
    }
}

// Get feed with hidden posts excluded
$feed_query = "SELECT p.*, u.username, u.full_name, u.profile_pic,
               COUNT(DISTINCT l.id) as likes_count,
               COUNT(DISTINCT c.id) as comments_count,
               EXISTS(SELECT 1 FROM likes WHERE post_id = p.id AND user_id = $current_user_id) as user_liked,
               EXISTS(SELECT 1 FROM saved_posts WHERE user_id = $current_user_id AND post_id = p.id) as user_saved
               FROM posts p
               JOIN users u ON p.user_id = u.id
               LEFT JOIN likes l ON p.id = l.post_id
               LEFT JOIN comments c ON p.id = c.post_id AND c.is_deleted = FALSE
               WHERE (
                   p.user_id = $current_user_id
                   OR 
                   (p.privacy = 'public')
                   OR
                   (p.privacy IN ('friends', 'friends_except') AND p.user_id IN (
                       SELECT following_id FROM friends_followers 
                       WHERE follower_id = $current_user_id AND status = 'accepted'
                       UNION
                       SELECT follower_id FROM friends_followers 
                       WHERE following_id = $current_user_id AND status = 'accepted'
                   ))
                   OR
                   (p.privacy = 'private' AND p.user_id = $current_user_id)
               )
               AND p.id NOT IN (SELECT post_id FROM hidden_posts WHERE user_id = $current_user_id)
               GROUP BY p.id
               ORDER BY p.created_at DESC
               LIMIT 50";
$feed_result = mysqli_query($conn, $feed_query);

$friends_query = "SELECT DISTINCT u.* FROM users u
                  WHERE u.id IN (
                      SELECT following_id FROM friends_followers 
                      WHERE follower_id = $current_user_id AND status = 'accepted'
                      UNION
                      SELECT follower_id FROM friends_followers 
                      WHERE following_id = $current_user_id AND status = 'accepted'
                  )
                  LIMIT 8";
$friends_result = mysqli_query($conn, $friends_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TravelMate - Home</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #faf9f6;
            background-image: radial-gradient(circle at 10% 20%, rgba(255, 182, 120, 0.08) 0%, rgba(255, 255, 255, 0) 50%),
                              radial-gradient(circle at 90% 80%, rgba(74, 189, 172, 0.06) 0%, rgba(255, 255, 255, 0) 50%);
            color: #2c3e50;
            min-height: 100vh;
        }

        /* Navbar - Consistent across ALL pages */
        .navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            position: sticky;
            top: 0;
            z-index: 100;
            width: 100%;
        }

        .nav-container {
            max-width: 1280px;
            width: 100%;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 24px;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 140px;
        }

        .logo i {
            font-size: 24px;
            color: #f39c12;
        }

        .logo h2 {
            font-size: 20px;
            font-weight: 600;
            color: #1a1a1a;
            letter-spacing: -0.3px;
            white-space: nowrap;
        }

        .nav-links {
            display: flex;
            gap: 8px;
        }

        .nav-link {
            padding: 8px 20px;
            text-decoration: none;
            color: #5a6e7a;
            font-weight: 500;
            border-radius: 30px;
            transition: all 0.3s;
            font-size: 14px;
            white-space: nowrap;
        }

        .nav-link:hover {
            background: #f0f3f2;
            color: #f39c12;
        }

        .nav-link.active {
            background: #f39c12;
            color: white;
            box-shadow: 0 2px 8px rgba(243, 156, 18, 0.3);
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 16px;
            min-width: 140px;
            justify-content: flex-end;
        }

        .profile-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid #f39c12;
        }

        .profile-avatar:hover {
            transform: scale(1.05);
            border-color: #e67e22;
        }

        .logout-btn {
            background: #fff5eb;
            padding: 8px 20px;
            border-radius: 30px;
            text-decoration: none;
            color: #e67e22;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
            border: 1px solid #fdebd0;
            white-space: nowrap;
        }

        .logout-btn:hover {
            background: #e67e22;
            color: white;
            border-color: #e67e22;
        }

        /* Main Container */
        .main-container {
            max-width: 1280px;
            margin: 32px auto;
            display: grid;
            grid-template-columns: 300px 1fr 320px;
            gap: 24px;
            padding: 0 24px;
        }

        /* Left Sidebar */
        .sidebar-card {
            background: white;
            border-radius: 24px;
            padding: 24px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.04);
            border: 1px solid rgba(0, 0, 0, 0.04);
            position: sticky;
            top: 90px;
        }

        .sidebar-avatar {
            width: 96px;
            height: 96px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 16px;
            border: 3px solid #f39c12;
        }

        .sidebar-card h3 {
            font-size: 18px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 4px;
        }

        .sidebar-card p {
            font-size: 13px;
            color: #7f8c8d;
            margin-bottom: 20px;
        }

        .sidebar-stats {
            display: flex;
            justify-content: space-around;
            background: #f8f9fa;
            padding: 12px;
            border-radius: 20px;
            margin: 16px 0;
        }

        .sidebar-stat {
            text-align: center;
        }

        .sidebar-stat-number {
            font-weight: 700;
            font-size: 20px;
            color: #f39c12;
        }

        .sidebar-stat-label {
            font-size: 11px;
            color: #7f8c8d;
            margin-top: 4px;
        }

        .sidebar-menu-item {
            padding: 12px 16px;
            margin: 6px 0;
            border-radius: 16px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
            font-size: 14px;
            color: #5a6e7a;
            text-align: left;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sidebar-menu-item i {
            width: 24px;
            font-size: 16px;
            color: #f39c12;
        }

        .sidebar-menu-item:hover {
            background: #fef9f0;
            transform: translateX(5px);
            color: #e67e22;
        }

        /* Create Post Card */
        .create-post-card {
            background: white;
            border-radius: 24px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.04);
            border: 1px solid rgba(0, 0, 0, 0.04);
        }

        .post-input-area {
            display: flex;
            gap: 12px;
            align-items: flex-start;
        }

        .post-input-area img {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
        }

        .post-input-area textarea {
            flex: 1;
            border: 1px solid #e8ecef;
            background: #fafbfc;
            padding: 14px 18px;
            border-radius: 24px;
            resize: none;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 14px;
            color: #2c3e50;
            transition: all 0.3s;
        }

        .post-input-area textarea:focus {
            outline: none;
            border-color: #f39c12;
            background: white;
            box-shadow: 0 0 0 3px rgba(243, 156, 18, 0.1);
        }

        .privacy-select {
            margin-top: 12px;
            display: flex;
            justify-content: flex-end;
        }

        .privacy-select select {
            padding: 6px 12px;
            border: 1px solid #e8ecef;
            border-radius: 20px;
            background: white;
            font-size: 12px;
            color: #7f8c8d;
            cursor: pointer;
        }

        .post-actions {
            display: flex;
            gap: 12px;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #e8ecef;
        }

        .action-btn {
            flex: 1;
            padding: 10px;
            background: none;
            border: 1px solid #e8ecef;
            border-radius: 30px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            color: #5a6e7a;
            font-size: 13px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .action-btn i {
            color: #f39c12;
        }

        .action-btn:hover {
            background: #fef9f0;
            border-color: #f39c12;
            transform: translateY(-2px);
        }

        .post-submit {
            background: #f39c12;
            color: white;
            border: none;
        }

        .post-submit i {
            color: white;
        }

        .post-submit:hover {
            background: #e67e22;
            transform: translateY(-2px);
        }

        /* Post Cards */
        .post-card {
            background: white;
            border-radius: 24px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.04);
            transition: all 0.3s;
            border: 1px solid rgba(0, 0, 0, 0.04);
            position: relative;
        }

        .post-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.08);
        }

        .post-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }

        .post-header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .post-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
            cursor: pointer;
            border: 2px solid #f39c12;
        }

        .post-author {
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            color: #2c3e50;
        }

        .post-author:hover {
            color: #f39c12;
        }

        .post-time {
            font-size: 11px;
            color: #95a5a6;
            margin-top: 4px;
        }

        .privacy-badge {
            display: inline-block;
            font-size: 10px;
            background: #f0f3f2;
            padding: 2px 8px;
            border-radius: 20px;
            margin-left: 8px;
            color: #7f8c8d;
        }

        .post-menu {
            position: relative;
        }

        .three-dots {
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            transition: all 0.3s;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #8a8a8a;
        }

        .three-dots:hover {
            background: #f0f3f2;
        }

        .dropdown-menu {
            position: absolute;
            top: 40px;
            right: 0;
            background: white;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
            min-width: 200px;
            z-index: 10;
            display: none;
            overflow: hidden;
            border: 1px solid #e8ecef;
        }

        .dropdown-menu.show {
            display: block;
            animation: fadeIn 0.2s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .dropdown-item {
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 14px;
            color: #2c3e50;
        }

        .dropdown-item i {
            width: 18px;
            color: #f39c12;
        }

        .dropdown-item:hover {
            background: #f8f9fa;
        }

        .dropdown-item.danger {
            color: #e74c3c;
        }

        .dropdown-item.danger i {
            color: #e74c3c;
        }

        .dropdown-item.danger:hover {
            background: #fee;
        }

        .dropdown-divider {
            height: 1px;
            background: #e8ecef;
            margin: 4px 0;
        }

        .post-content {
            margin-bottom: 16px;
            line-height: 1.6;
            font-size: 15px;
            color: #34495e;
        }

        .image-wrapper {
            width: 100%;
            overflow: hidden;
            border-radius: 16px;
            margin: 16px 0;
        }

        .image-wrapper img {
            width: 100%;
            height: auto;
            border-radius: 16px;
            display: block;
            cursor: pointer;
        }

        .location-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #fef9f0;
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 12px;
            color: #f39c12;
            margin: 12px 0;
            cursor: pointer;
        }

        .post-stats {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-top: 1px solid #e8ecef;
            border-bottom: 1px solid #e8ecef;
            color: #95a5a6;
            font-size: 13px;
        }

        .post-stats i {
            margin-right: 4px;
        }

        .post-buttons {
            display: flex;
            gap: 12px;
            margin-top: 12px;
        }

        .post-btn {
            flex: 1;
            padding: 10px;
            background: none;
            border: none;
            border-radius: 30px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            color: #5a6e7a;
            font-size: 13px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .post-btn i {
            color: #f39c12;
        }

        .post-btn:hover {
            background: #f8f9fa;
            transform: translateY(-2px);
            color: #f39c12;
        }

        .post-btn.liked {
            color: #e74c3c;
        }

        .post-btn.liked i {
            color: #e74c3c;
        }

        /* Comments Section */
        .comments-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e8ecef;
        }

        .comment {
            display: flex;
            gap: 12px;
            margin-bottom: 16px;
        }

        .comment-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
        }

        .comment-bubble {
            background: #f8f9fa;
            padding: 10px 16px;
            border-radius: 20px;
            flex: 1;
        }

        .comment-author {
            font-weight: 600;
            font-size: 12px;
            margin-bottom: 4px;
            color: #2c3e50;
        }

        .comment-text {
            font-size: 13px;
            color: #5a6e7a;
        }

        .comment-input {
            display: flex;
            gap: 12px;
            margin-top: 16px;
        }

        .comment-input input {
            flex: 1;
            padding: 12px 16px;
            border: 1px solid #e8ecef;
            background: #fafbfc;
            border-radius: 30px;
            font-size: 13px;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .comment-input input:focus {
            outline: none;
            border-color: #f39c12;
        }

        .comment-input button {
            padding: 12px 24px;
            background: #f39c12;
            color: white;
            border: none;
            border-radius: 30px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }

        .comment-input button:hover {
            background: #e67e22;
            transform: translateY(-2px);
        }

        /* Right Sidebar */
        .right-sidebar {
            background: white;
            border-radius: 24px;
            padding: 24px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.04);
            border: 1px solid rgba(0, 0, 0, 0.04);
            position: sticky;
            top: 90px;
        }

        .right-sidebar h4 {
            font-size: 18px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 20px;
        }

        .right-sidebar h4 i {
            color: #f39c12;
            margin-right: 8px;
        }

        .friend-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            margin-bottom: 8px;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .friend-item:hover {
            background: #fef9f0;
            transform: translateX(-5px);
        }

        .friend-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
        }

        .friend-info strong {
            font-size: 14px;
            font-weight: 600;
            color: #2c3e50;
            display: block;
        }

        .friend-info span {
            font-size: 11px;
            color: #95a5a6;
        }

        .discover-btn {
            margin-top: 20px;
            padding: 12px;
            background: #fef9f0;
            text-align: center;
            border-radius: 30px;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.3s;
            color: #f39c12;
            border: 1px solid #fdebd0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .discover-btn i {
            color: #f39c12;
        }

        .discover-btn:hover {
            background: #f39c12;
            color: white;
            transform: translateY(-2px);
        }

        .discover-btn:hover i {
            color: white;
        }

        /* Modals */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: white;
            border-radius: 32px;
            width: 500px;
            max-width: 90%;
            padding: 32px;
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            font-size: 20px;
            font-weight: 700;
            color: #2c3e50;
        }

        .modal-header i {
            color: #f39c12;
            margin-right: 8px;
        }

        .close-modal {
            cursor: pointer;
            font-size: 28px;
            color: #95a5a6;
            transition: all 0.3s;
        }

        .close-modal:hover {
            color: #e74c3c;
        }

        .modal-content textarea, .modal-content input {
            width: 100%;
            padding: 14px;
            margin: 12px 0;
            border: 1px solid #e8ecef;
            border-radius: 24px;
            font-size: 14px;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .modal-content textarea:focus, .modal-content input:focus {
            outline: none;
            border-color: #f39c12;
        }

        .modal-content button {
            width: 100%;
            padding: 14px;
            background: #f39c12;
            color: white;
            border: none;
            border-radius: 30px;
            margin-top: 16px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }

        .modal-content button:hover {
            background: #e67e22;
            transform: translateY(-2px);
        }

        .report-options {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin: 20px 0;
        }
        
        .report-option {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .report-option:hover {
            background: #f8f9fa;
        }
        
        .report-option input {
            width: auto;
            margin: 0;
        }
        
        .report-option label {
            flex: 1;
            cursor: pointer;
            font-size: 14px;
        }

        .location-suggestion {
            padding: 12px;
            cursor: pointer;
            border-bottom: 1px solid #e8ecef;
            transition: all 0.2s;
        }

        .location-suggestion:hover {
            background: #fef9f0;
            transform: translateX(5px);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 24px;
            border: 1px solid #e8ecef;
        }

        .empty-state i {
            font-size: 64px;
            color: #f39c12;
            margin-bottom: 16px;
            display: block;
        }

        .divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, #e8ecef, transparent);
            margin: 16px 0;
        }

        .toast {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            background: #2c3e50;
            color: white;
            padding: 12px 24px;
            border-radius: 40px;
            font-size: 14px;
            z-index: 1001;
            display: none;
            white-space: nowrap;
        }

        @media (max-width: 1100px) {
            .main-container {
                grid-template-columns: 280px 1fr;
            }
            .right-sidebar {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .main-container {
                grid-template-columns: 1fr;
            }
            .sidebar-card {
                display: none;
            }
            .nav-links {
                display: none;
            }
            .toast {
                white-space: normal;
                text-align: center;
                max-width: 90%;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">
                <i class="fas fa-compass logo-icon"></i>
                <h2>Travel<span>Mate</span></h2>
            </div>
            <div class="nav-links">
                <a href="homepage.php" class="nav-link active">Home</a>
                <a href="map.php" class="nav-link">Map</a>
                <a href="profile.php" class="nav-link">Profile</a>
                <a href="friends.php" class="nav-link">Travelers</a>
            </div>
            <div class="nav-right">
                <?php
                $nav_profile_pic = isset($_SESSION['profile_pic']) ? $_SESSION['profile_pic'] : 'default.jpg';
                if (!empty($nav_profile_pic) && $nav_profile_pic != 'default.jpg' && file_exists('uploads/profiles/' . $nav_profile_pic)) {
                    $nav_avatar_url = 'uploads/profiles/' . $nav_profile_pic;
                } else {
                    $name = isset($_SESSION['full_name']) ? urlencode($_SESSION['full_name']) : 'User';
                    $nav_avatar_url = "https://ui-avatars.com/api/?background=f39c12&color=fff&rounded=true&size=40&bold=true&name=$name";
                }
                ?>
                <img src="<?php echo $nav_avatar_url; ?>" class="profile-avatar" alt="Profile" onclick="window.location.href='profile.php'">
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </nav>

    <div class="main-container">
        <!-- Left Sidebar -->
        <div class="sidebar-card">
            <?php
            $sidebar_pic = $current_user['profile_pic'];
            if (!empty($sidebar_pic) && $sidebar_pic != 'default.jpg' && file_exists('uploads/profiles/' . $sidebar_pic)) {
                $sidebar_avatar_url = 'uploads/profiles/' . $sidebar_pic;
            } else {
                $name = urlencode($current_user['full_name']);
                $sidebar_avatar_url = "https://ui-avatars.com/api/?background=f39c12&color=fff&rounded=true&size=96&bold=true&name=$name";
            }
            ?>
            <img src="<?php echo $sidebar_avatar_url; ?>" class="sidebar-avatar">
            <h3><?php echo htmlspecialchars($current_user['full_name']); ?></h3>
            <p>@<?php echo htmlspecialchars($current_user['username']); ?></p>
            <div class="sidebar-stats">
                <div class="sidebar-stat">
                    <div class="sidebar-stat-number"><?php echo mysqli_num_rows($feed_result); ?></div>
                    <div class="sidebar-stat-label">Posts</div>
                </div>
                <div class="sidebar-stat">
                    <div class="sidebar-stat-number"><?php echo mysqli_num_rows($friends_result); ?></div>
                    <div class="sidebar-stat-label">Friends</div>
                </div>
            </div>
            <div class="divider"></div>
            <div class="sidebar-menu-item" onclick="window.location.href='profile.php'">
                <i class="fas fa-chart-line"></i> My Stats
            </div>
            <div class="sidebar-menu-item" onclick="window.location.href='profile.php'">
                <i class="fas fa-pen-alt"></i> My Stories
            </div>
            <div class="sidebar-menu-item">
                <i class="fas fa-bookmark"></i> Saved
            </div>
        </div>

        <!-- Middle Section -->
        <div>
            <!-- Create Post -->
            <div class="create-post-card">
                <div class="post-input-area">
                    <?php
                    $create_post_pic = $current_user['profile_pic'];
                    if (!empty($create_post_pic) && $create_post_pic != 'default.jpg' && file_exists('uploads/profiles/' . $create_post_pic)) {
                        $create_post_avatar_url = 'uploads/profiles/' . $create_post_pic;
                    } else {
                        $name = urlencode($current_user['full_name']);
                        $create_post_avatar_url = "https://ui-avatars.com/api/?background=f39c12&color=fff&rounded=true&size=48&bold=true&name=$name";
                    }
                    ?>
                    <img src="<?php echo $create_post_avatar_url; ?>" alt="Avatar">
                    <textarea id="postContent" rows="2" placeholder="Share your travel story..."></textarea>
                </div>
                <div class="privacy-select">
                    <select id="postPrivacy">
                        <option value="public">Public</option>
                        <option value="friends">Friends</option>
                        <option value="private">Only Me</option>
                    </select>
                </div>
                <div class="post-actions">
                    <button class="action-btn" onclick="openImageModal()">
                        <i class="fas fa-image"></i> Photo
                    </button>
                    <button class="action-btn" onclick="openLocationModal()">
                        <i class="fas fa-map-pin"></i> Location
                    </button>
                    <button class="action-btn post-submit" onclick="submitPost()">
                        <i class="fas fa-paper-plane"></i> Post
                    </button>
                </div>
            </div>

            <!-- Posts Feed -->
            <div id="postsFeed">
                <?php if (mysqli_num_rows($feed_result) == 0): ?>
                    <div class="empty-state">
                        <i class="fas fa-compass"></i>
                        <h3 style="margin-bottom: 8px;">No posts yet</h3>
                        <p style="color: #7f8c8d;">Share your first travel story!</p>
                    </div>
                <?php else: ?>
                    <?php while ($post = mysqli_fetch_assoc($feed_result)): ?>
                        <div class="post-card" data-post-id="<?php echo $post['id']; ?>">
                            <div class="post-header">
                                <div class="post-header-left">
                                    <?php
                                    $post_avatar_pic = $post['profile_pic'];
                                    if (!empty($post_avatar_pic) && $post_avatar_pic != 'default.jpg' && file_exists('uploads/profiles/' . $post_avatar_pic)) {
                                        $post_avatar_url = 'uploads/profiles/' . $post_avatar_pic;
                                    } else {
                                        $name = urlencode($post['full_name']);
                                        $post_avatar_url = "https://ui-avatars.com/api/?background=f39c12&color=fff&rounded=true&size=48&bold=true&name=$name";
                                    }
                                    ?>
                                    <img src="<?php echo $post_avatar_url; ?>" class="post-avatar" 
                                         onclick="viewProfile(<?php echo $post['user_id']; ?>)">
                                    <div>
                                        <div class="post-author" onclick="viewProfile(<?php echo $post['user_id']; ?>)">
                                            <?php echo htmlspecialchars($post['full_name']); ?>
                                        </div>
                                        <div class="post-time">
                                            <i class="far fa-calendar-alt"></i> <?php echo date('M j, Y \a\t g:i A', strtotime($post['created_at'])); ?>
                                            <span class="privacy-badge"><?php echo ucfirst($post['privacy']); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <!-- Three dot menu for ALL posts -->
                                <div class="post-menu">
                                    <div class="three-dots" onclick="toggleMenu(this)">
                                        <i class="fas fa-ellipsis-h"></i>
                                    </div>
                                    <div class="dropdown-menu">
                                        <div class="dropdown-item" onclick="savePost(<?php echo $post['id']; ?>, this)">
                                            <i class="fas fa-bookmark"></i> <span id="saveText_<?php echo $post['id']; ?>"><?php echo $post['user_saved'] ? 'Unsave Post' : 'Save Post'; ?></span>
                                        </div>
                                        <div class="dropdown-item" onclick="hidePost(<?php echo $post['id']; ?>)">
                                            <i class="fas fa-eye-slash"></i> Hide Post
                                        </div>
                                        <div class="dropdown-divider"></div>
                                        <div class="dropdown-item" onclick="openReportModal(<?php echo $post['id']; ?>)">
                                            <i class="fas fa-flag"></i> Report Post
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="post-content"><?php echo nl2br(htmlspecialchars($post['content'])); ?></div>
                            <?php if ($post['image_url']): ?>
                                <div class="image-wrapper">
                                    <img src="<?php echo $post['image_url']; ?>" onclick="viewImage('<?php echo $post['image_url']; ?>')">
                                </div>
                            <?php endif; ?>
                            <?php if ($post['location_name']): ?>
                                <div class="location-tag" onclick="viewOnMap(<?php echo $post['location_latitude'] ?: 'null'; ?>, <?php echo $post['location_longitude'] ?: 'null'; ?>, '<?php echo addslashes($post['location_name']); ?>')">
                                    <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($post['location_name']); ?>
                                </div>
                            <?php endif; ?>
                            <div class="post-stats">
                                <span id="likeCount_<?php echo $post['id']; ?>">
                                    <i class="fas fa-heart"></i> <?php echo $post['likes_count']; ?> likes
                                </span>
                                <span>
                                    <i class="fas fa-comment"></i> <?php echo $post['comments_count']; ?> comments
                                </span>
                            </div>
                            <div class="post-buttons">
                                <button class="post-btn <?php echo $post['user_liked'] ? 'liked' : ''; ?>" onclick="toggleLike(<?php echo $post['id']; ?>, this)">
                                    <i class="fas fa-thumbs-up"></i> Like
                                </button>
                                <button class="post-btn" onclick="toggleComments(<?php echo $post['id']; ?>)">
                                    <i class="fas fa-comment"></i> Comment
                                </button>
                                <button class="post-btn" onclick="sharePost(<?php echo $post['id']; ?>)">
                                    <i class="fas fa-share"></i> Share
                                </button>
                            </div>
                            <div class="comments-section" id="comments_<?php echo $post['id']; ?>" style="display: none;">
                                <div id="commentsList_<?php echo $post['id']; ?>"></div>
                                <div class="comment-input">
                                    <input type="text" id="commentInput_<?php echo $post['id']; ?>" placeholder="Write a comment...">
                                    <button onclick="addComment(<?php echo $post['id']; ?>)">Post</button>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Sidebar -->
        <div class="right-sidebar">
            <h4><i class="fas fa-users"></i> Travelers</h4>
            <div>
                <?php if (mysqli_num_rows($friends_result) == 0): ?>
                    <div style="text-align: center; color: #95a5a6; padding: 20px;">
                        <i class="fas fa-user-friends" style="font-size: 40px; margin-bottom: 10px; display: block;"></i>
                        No friends yet
                    </div>
                <?php else: ?>
                    <?php while ($friend = mysqli_fetch_assoc($friends_result)): ?>
                        <div class="friend-item" onclick="viewProfile(<?php echo $friend['id']; ?>)">
                            <?php
                            $friend_pic = $friend['profile_pic'];
                            if (!empty($friend_pic) && $friend_pic != 'default.jpg' && file_exists('uploads/profiles/' . $friend_pic)) {
                                $friend_avatar_url = 'uploads/profiles/' . $friend_pic;
                            } else {
                                $name = urlencode($friend['full_name']);
                                $friend_avatar_url = "https://ui-avatars.com/api/?background=f39c12&color=fff&rounded=true&size=48&bold=true&name=$name";
                            }
                            ?>
                            <img src="<?php echo $friend_avatar_url; ?>" class="friend-avatar">
                            <div class="friend-info">
                                <strong><?php echo htmlspecialchars($friend['full_name']); ?></strong>
                                <span>@<?php echo htmlspecialchars($friend['username']); ?></span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
            <div class="divider"></div>
            <div class="discover-btn" onclick="window.location.href='friends.php'">
                <i class="fas fa-search"></i> Discover Travelers
            </div>
        </div>
    </div>

    <!-- Modals -->
    <div id="imageModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span><i class="fas fa-image"></i> Upload Photo</span>
                <span class="close-modal" onclick="closeModal('imageModal')">&times;</span>
            </div>
            <input type="file" id="postImage" accept="image/*">
        </div>
    </div>

    <div id="locationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span><i class="fas fa-map-pin"></i> Add Location</span>
                <span class="close-modal" onclick="closeModal('locationModal')">&times;</span>
            </div>
            <input type="text" id="postLocationSearch" placeholder="Search for a location...">
            <div id="locationSuggestions" style="margin-top: 10px; max-height: 200px; overflow-y: auto;"></div>
            <button onclick="useCurrentLocation()" style="margin-top: 10px;">
                <i class="fas fa-location-dot"></i> Use My Location
            </button>
            <input type="hidden" id="selectedLatitude">
            <input type="hidden" id="selectedLongitude">
            <input type="hidden" id="selectedLocationName">
        </div>
    </div>

    <div id="editPostModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span><i class="fas fa-pen"></i> Edit Story</span>
                <span class="close-modal" onclick="closeModal('editPostModal')">&times;</span>
            </div>
            <textarea id="editContent" rows="4" placeholder="What's on your mind?"></textarea>
            <input type="text" id="editLocation" placeholder="Location">
            <input type="hidden" id="editPostId">
            <button onclick="saveEditPost()"><i class="fas fa-save"></i> Save Changes</button>
        </div>
    </div>

    <div id="privacyModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span><i class="fas fa-lock"></i> Edit Audience</span>
                <span class="close-modal" onclick="closeModal('privacyModal')">&times;</span>
            </div>
            <div class="privacy-options">
                <div class="privacy-option" onclick="selectPrivacy('public')">
                    <input type="radio" name="privacy" value="public" id="privacy_public">
                    <label for="privacy_public">
                        <strong>Public</strong><br>
                        <small style="color:#7f8c8d;">Anyone can see this post</small>
                    </label>
                </div>
                <div class="privacy-option" onclick="selectPrivacy('friends')">
                    <input type="radio" name="privacy" value="friends" id="privacy_friends">
                    <label for="privacy_friends">
                        <strong>Friends</strong><br>
                        <small style="color:#7f8c8d;">Only your friends can see this post</small>
                    </label>
                </div>
                <div class="privacy-option" onclick="selectPrivacy('private')">
                    <input type="radio" name="privacy" value="private" id="privacy_private">
                    <label for="privacy_private">
                        <strong>Only Me</strong><br>
                        <small style="color:#7f8c8d;">Only you can see this post</small>
                    </label>
                </div>
            </div>
            <input type="hidden" id="privacyPostId">
            <button onclick="updatePostPrivacy()"><i class="fas fa-check"></i> Save Audience</button>
        </div>
    </div>

    <!-- Report Modal -->
    <div id="reportModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span><i class="fas fa-flag"></i> Report Post</span>
                <span class="close-modal" onclick="closeModal('reportModal')">&times;</span>
            </div>
            <p style="color: #8a8a8a; margin-bottom: 16px;">Why are you reporting this post?</p>
            <div class="report-options">
                <div class="report-option" onclick="selectReportReason('Spam')">
                    <input type="radio" name="report_reason" value="Spam" id="report_spam">
                    <label for="report_spam">Spam</label>
                </div>
                <div class="report-option" onclick="selectReportReason('Harassment')">
                    <input type="radio" name="report_reason" value="Harassment" id="report_harassment">
                    <label for="report_harassment">Harassment or bullying</label>
                </div>
                <div class="report-option" onclick="selectReportReason('Inappropriate')">
                    <input type="radio" name="report_reason" value="Inappropriate" id="report_inappropriate">
                    <label for="report_inappropriate">Inappropriate content</label>
                </div>
                <div class="report-option" onclick="selectReportReason('False Information')">
                    <input type="radio" name="report_reason" value="False Information" id="report_false">
                    <label for="report_false">False information</label>
                </div>
                <div class="report-option" onclick="selectReportReason('Other')">
                    <input type="radio" name="report_reason" value="Other" id="report_other">
                    <label for="report_other">Other</label>
                </div>
            </div>
            <input type="hidden" id="reportPostId">
            <button onclick="submitReport()">Submit Report</button>
        </div>
    </div>

    <div id="imagePreviewModal" class="modal">
        <div class="modal-content" style="max-width: 90%; width: auto; text-align: center; padding: 20px;">
            <div class="modal-header" style="margin-bottom: 10px;">
                <span></span>
                <span class="close-modal" onclick="closeModal('imagePreviewModal')">&times;</span>
            </div>
            <img id="previewImage" src="" style="max-width: 100%; max-height: 70vh; border-radius: 16px;">
        </div>
    </div>

    <div id="toast" class="toast"></div>

    <script>
        let selectedImage = null;
        let selectedLocation = '';
        let selectedLat = '';
        let selectedLng = '';
        let currentReportPostId = null;

        function viewProfile(userId) {
            window.location.href = `profile.php?id=${userId}`;
        }
        
        function viewImage(imageUrl) {
            document.getElementById('previewImage').src = imageUrl;
            document.getElementById('imagePreviewModal').style.display = 'flex';
        }

        function viewOnMap(lat, lng, locationName) {
            if (lat && lng) {
                window.location.href = `map.php?lat=${lat}&lng=${lng}&zoom=15`;
            } else {
                showToast('No coordinates available for this location');
            }
        }

        function openImageModal() {
            document.getElementById('imageModal').style.display = 'flex';
        }

        function openLocationModal() {
            document.getElementById('locationModal').style.display = 'flex';
            document.getElementById('postLocationSearch').focus();
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        document.getElementById('postImage').addEventListener('change', function(e) {
            selectedImage = e.target.files[0];
            closeModal('imageModal');
        });

        let searchTimeout;
        document.getElementById('postLocationSearch').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value;
            if (query.length > 2) {
                searchTimeout = setTimeout(() => {
                    searchLocation(query);
                }, 500);
            }
        });

        function searchLocation(query) {
            fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=5`)
                .then(response => response.json())
                .then(data => {
                    const suggestions = document.getElementById('locationSuggestions');
                    suggestions.innerHTML = '';
                    data.forEach(place => {
                        const div = document.createElement('div');
                        div.className = 'location-suggestion';
                        div.innerHTML = `<i class="fas fa-map-marker-alt" style="color:#f39c12; margin-right:8px;"></i> ${place.display_name}`;
                        div.onclick = () => {
                            document.getElementById('selectedLocationName').value = place.display_name;
                            document.getElementById('selectedLatitude').value = place.lat;
                            document.getElementById('selectedLongitude').value = place.lon;
                            selectedLocation = place.display_name;
                            selectedLat = place.lat;
                            selectedLng = place.lon;
                            suggestions.innerHTML = '';
                            closeModal('locationModal');
                            showToast(`Location set: ${place.display_name.substring(0, 50)}...`);
                        };
                        suggestions.appendChild(div);
                    });
                });
        }

        function useCurrentLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(position => {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    
                    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
                        .then(response => response.json())
                        .then(data => {
                            document.getElementById('selectedLocationName').value = data.display_name;
                            document.getElementById('selectedLatitude').value = lat;
                            document.getElementById('selectedLongitude').value = lng;
                            selectedLocation = data.display_name;
                            selectedLat = lat;
                            selectedLng = lng;
                            closeModal('locationModal');
                            showToast(`Location set to your current position!`);
                        });
                }, () => {
                    showToast('Unable to get your location');
                });
            } else {
                showToast('Geolocation is not supported');
            }
        }

        function submitPost() {
            let content = document.getElementById('postContent').value;
            let privacy = document.getElementById('postPrivacy').value;
            
            if (!content.trim() && !selectedImage) {
                showToast('Share something about your journey!');
                return;
            }

            let formData = new FormData();
            formData.append('create_post', true);
            formData.append('content', content);
            formData.append('location', selectedLocation);
            formData.append('latitude', selectedLat);
            formData.append('longitude', selectedLng);
            formData.append('privacy', privacy);
            if (selectedImage) {
                formData.append('post_image', selectedImage);
            }

            fetch('homepage.php', {
                method: 'POST',
                body: formData
            }).then(() => {
                location.reload();
            });
        }

        function toggleMenu(element) {
            const dropdown = element.nextElementSibling;
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                if (menu !== dropdown) menu.classList.remove('show');
            });
            dropdown.classList.toggle('show');
        }

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.post-menu')) {
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    menu.classList.remove('show');
                });
            }
        });

        function editPost(postId, content, location) {
            document.getElementById('editContent').value = content;
            document.getElementById('editLocation').value = location || '';
            document.getElementById('editPostId').value = postId;
            document.getElementById('editPostModal').style.display = 'flex';
        }

        function saveEditPost() {
            const postId = document.getElementById('editPostId').value;
            const content = document.getElementById('editContent').value;
            const location = document.getElementById('editLocation').value;

            if (!content.trim()) {
                showToast('Post content cannot be empty');
                return;
            }

            const formData = new FormData();
            formData.append('edit_post', true);
            formData.append('post_id', postId);
            formData.append('content', content);
            formData.append('location', location);

            fetch('homepage.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Post updated successfully!');
                    closeModal('editPostModal');
                    location.reload();
                }
            });
        }

        function deletePost(postId) {
            if (confirm('Are you sure you want to delete this post? This action cannot be undone.')) {
                const formData = new FormData();
                formData.append('delete_post', true);
                formData.append('post_id', postId);

                fetch('homepage.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Post deleted successfully');
                        location.reload();
                    }
                });
            }
        }

        function savePost(postId, element) {
            const formData = new FormData();
            formData.append('save_post', true);
            formData.append('post_id', postId);

            fetch('homepage.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const saveText = document.getElementById(`saveText_${postId}`);
                    if (data.saved) {
                        saveText.innerHTML = 'Unsave Post';
                        showToast('Post saved to your collection');
                    } else {
                        saveText.innerHTML = 'Save Post';
                        showToast('Post removed from saved');
                    }
                }
            });
        }

        function pinPost(postId, element) {
            const formData = new FormData();
            formData.append('pin_post', true);
            formData.append('post_id', postId);

            fetch('homepage.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const pinText = document.getElementById(`pinText_${postId}`);
                    if (data.pinned) {
                        pinText.innerHTML = 'Unpin from Profile';
                        showToast('Post pinned to your profile');
                    } else {
                        pinText.innerHTML = 'Pin to Profile';
                        showToast('Post unpinned from profile');
                    }
                }
            });
        }

        // Hide post function
        function hidePost(postId) {
            if (confirm('Hide this post? You can undo this later from settings.')) {
                const formData = new FormData();
                formData.append('hide_post', true);
                formData.append('post_id', postId);

                fetch('homepage.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Post hidden');
                        // Remove the post card from DOM
                        const postCard = document.querySelector(`.post-card[data-post-id="${postId}"]`);
                        if (postCard) {
                            postCard.style.animation = 'fadeOut 0.3s ease';
                            setTimeout(() => {
                                postCard.remove();
                            }, 300);
                        }
                    }
                });
            }
        }

        // Report post functions
        function openReportModal(postId) {
            currentReportPostId = postId;
            document.getElementById('reportPostId').value = postId;
            document.getElementById('reportModal').style.display = 'flex';
        }

        function selectReportReason(reason) {
            document.querySelectorAll('input[name="report_reason"]').forEach(radio => {
                radio.checked = false;
            });
            const radioId = `report_${reason.toLowerCase().replace(/ /g, '_')}`;
            document.getElementById(radioId).checked = true;
        }

        function submitReport() {
            const selectedReason = document.querySelector('input[name="report_reason"]:checked');
            if (!selectedReason) {
                showToast('Please select a reason for reporting');
                return;
            }

            const reason = selectedReason.value;
            const postId = document.getElementById('reportPostId').value;

            const formData = new FormData();
            formData.append('report_post', true);
            formData.append('post_id', postId);
            formData.append('reason', reason);

            fetch('homepage.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Thank you for your report. We will review it.');
                    closeModal('reportModal');
                }
            });
        }

        let currentPrivacyPostId = null;

        function openPrivacyModal(postId, currentPrivacy) {
            currentPrivacyPostId = postId;
            document.getElementById('privacyPostId').value = postId;
            document.getElementById(`privacy_${currentPrivacy}`).checked = true;
            document.getElementById('privacyModal').style.display = 'flex';
        }

        function selectPrivacy(privacy) {
            document.getElementById(`privacy_${privacy}`).checked = true;
        }

        function updatePostPrivacy() {
            const privacy = document.querySelector('input[name="privacy"]:checked').value;
            const postId = document.getElementById('privacyPostId').value;

            const formData = new FormData();
            formData.append('update_privacy', true);
            formData.append('post_id', postId);
            formData.append('privacy', privacy);

            fetch('homepage.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Post audience updated successfully!');
                    closeModal('privacyModal');
                    location.reload();
                }
            });
        }

        function sharePost(postId) {
            const url = `${window.location.origin}/view_post.php?id=${postId}`;
            navigator.clipboard.writeText(url).then(() => {
                showToast('Post link copied to clipboard!');
            });
        }

        function toggleLike(postId, btn) {
            fetch(`like_post.php?post_id=${postId}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById(`likeCount_${postId}`).innerHTML = `<i class="fas fa-heart"></i> ${data.likes} likes`;
                    if (data.liked) {
                        btn.classList.add('liked');
                    } else {
                        btn.classList.remove('liked');
                    }
                });
        }

        function toggleComments(postId) {
            let commentsDiv = document.getElementById(`comments_${postId}`);
            if (commentsDiv.style.display === 'none') {
                commentsDiv.style.display = 'block';
                loadComments(postId);
            } else {
                commentsDiv.style.display = 'none';
            }
        }

        function loadComments(postId) {
            fetch(`get_comments.php?post_id=${postId}`)
                .then(response => response.json())
                .then(data => {
                    let html = '';
                    data.forEach(comment => {
                        html += `
                            <div class="comment">
                                <img src="uploads/profiles/${comment.profile_pic}" class="comment-avatar">
                                <div class="comment-bubble">
                                    <div class="comment-author">${escapeHtml(comment.full_name)}</div>
                                    <div class="comment-text">${escapeHtml(comment.comment_text)}</div>
                                </div>
                            </div>
                        `;
                    });
                    document.getElementById(`commentsList_${postId}`).innerHTML = html;
                });
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function addComment(postId) {
            let input = document.getElementById(`commentInput_${postId}`);
            let comment = input.value;
            if (!comment.trim()) return;

            fetch('add_comment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `post_id=${postId}&comment=${encodeURIComponent(comment)}`
            }).then(() => {
                input.value = '';
                loadComments(postId);
            });
        }

        function showToast(message) {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.style.display = 'block';
            setTimeout(() => {
                toast.style.opacity = '0';
                setTimeout(() => {
                    toast.style.display = 'none';
                    toast.style.opacity = '1';
                }, 300);
            }, 3000);
        }
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }

        // Add fadeOut animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeOut {
                from { opacity: 1; transform: translateX(0); }
                to { opacity: 0; transform: translateX(-20px); }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>