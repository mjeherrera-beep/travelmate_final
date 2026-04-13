<?php
session_start();
include 'config.php';

// Check login
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

// Check if viewing another user's profile
$viewing_user_id = isset($_GET['id']) ? (int)$_GET['id'] : $_SESSION['user_id'];
$is_own_profile = ($viewing_user_id == $_SESSION['user_id']);

// Get user data
$user_query = "SELECT * FROM users WHERE id = $viewing_user_id";
$user_result = mysqli_query($conn, $user_query);
$user = mysqli_fetch_assoc($user_result);

if (!$user) {
    header("Location: homepage.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];

// Check if users are friends
$friendship_query = "SELECT * FROM friends_followers 
                     WHERE ((follower_id = $current_user_id AND following_id = $viewing_user_id)
                        OR (follower_id = $viewing_user_id AND following_id = $current_user_id))
                     AND status = 'accepted'";
$friendship_result = mysqli_query($conn, $friendship_query);
$are_friends = mysqli_num_rows($friendship_result) > 0;

// Check if friend request is pending
$pending_request_query = "SELECT * FROM friends_followers 
                          WHERE follower_id = $current_user_id AND following_id = $viewing_user_id AND status = 'pending'";
$pending_result = mysqli_query($conn, $pending_request_query);
$has_pending_request = mysqli_num_rows($pending_result) > 0;

// Check if user has received a request
$received_request_query = "SELECT * FROM friends_followers 
                           WHERE follower_id = $viewing_user_id AND following_id = $current_user_id AND status = 'pending'";
$received_result = mysqli_query($conn, $received_request_query);
$has_received_request = mysqli_num_rows($received_result) > 0;

// Handle profile update (only for own profile)
if ($is_own_profile && $_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_bio'])) {
        $bio = mysqli_real_escape_string($conn, $_POST['bio']);
        mysqli_query($conn, "UPDATE users SET bio = '$bio' WHERE id = $current_user_id");
        header("Location: profile.php");
        exit();
    }
    
    if (isset($_POST['update_info'])) {
        $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
        $location = mysqli_real_escape_string($conn, $_POST['location']);
        $birthdate = mysqli_real_escape_string($conn, $_POST['birthdate']);
        $education = mysqli_real_escape_string($conn, $_POST['education']);
        
        mysqli_query($conn, "UPDATE users SET full_name = '$full_name', 
                             location = '$location', birthdate = '$birthdate', 
                             education = '$education' WHERE id = $current_user_id");
        
        $_SESSION['full_name'] = $full_name;
        
        header("Location: profile.php");
        exit();
    }
    
    // Remove profile picture
    if (isset($_POST['remove_profile_pic'])) {
        if ($user['profile_pic'] != 'default.jpg' && file_exists('uploads/profiles/' . $user['profile_pic'])) {
            unlink('uploads/profiles/' . $user['profile_pic']);
        }
        mysqli_query($conn, "UPDATE users SET profile_pic = 'default.jpg' WHERE id = $current_user_id");
        $_SESSION['profile_pic'] = 'default.jpg';
        header("Location: profile.php");
        exit();
    }
    
    // Remove cover photo
    if (isset($_POST['remove_cover_photo'])) {
        if ($user['cover_photo'] != 'default_cover.jpg' && file_exists('uploads/covers/' . $user['cover_photo'])) {
            unlink('uploads/covers/' . $user['cover_photo']);
        }
        mysqli_query($conn, "UPDATE users SET cover_photo = 'default_cover.jpg' WHERE id = $current_user_id");
        header("Location: profile.php");
        exit();
    }
    
    // Handle profile picture upload
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            if (!is_dir('uploads/profiles')) {
                mkdir('uploads/profiles', 0777, true);
            }
            
            if ($user['profile_pic'] != 'default.jpg' && file_exists('uploads/profiles/' . $user['profile_pic'])) {
                unlink('uploads/profiles/' . $user['profile_pic']);
            }
            
            $new_filename = "user_" . $current_user_id . "_" . time() . "." . $ext;
            $upload_path = "uploads/profiles/" . $new_filename;
            
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $upload_path)) {
                mysqli_query($conn, "UPDATE users SET profile_pic = '$new_filename' WHERE id = $current_user_id");
                $_SESSION['profile_pic'] = $new_filename;
                header("Location: profile.php");
                exit();
            } else {
                $error = "Failed to upload profile picture.";
            }
        } else {
            $error = "Invalid file type. Please upload JPG, PNG, or GIF.";
        }
    }
    
    // Handle cover photo upload
    if (isset($_FILES['cover_photo']) && $_FILES['cover_photo']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower(pathinfo($_FILES['cover_photo']['name'], PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            if (!is_dir('uploads/covers')) {
                mkdir('uploads/covers', 0777, true);
            }
            
            if ($user['cover_photo'] != 'default_cover.jpg' && file_exists('uploads/covers/' . $user['cover_photo'])) {
                unlink('uploads/covers/' . $user['cover_photo']);
            }
            
            $new_filename = "cover_" . $current_user_id . "_" . time() . "." . $ext;
            $upload_path = "uploads/covers/" . $new_filename;
            
            if (move_uploaded_file($_FILES['cover_photo']['tmp_name'], $upload_path)) {
                mysqli_query($conn, "UPDATE users SET cover_photo = '$new_filename' WHERE id = $current_user_id");
                header("Location: profile.php");
                exit();
            }
        }
    }
}

// Handle post actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['edit_post'])) {
        $post_id = (int)$_POST['post_id'];
        $content = mysqli_real_escape_string($conn, $_POST['content']);
        $location = mysqli_real_escape_string($conn, $_POST['location']);
        
        $query = "UPDATE posts SET content = '$content', location_name = '$location', updated_at = NOW() WHERE id = $post_id AND user_id = $current_user_id";
        mysqli_query($conn, $query);
        echo json_encode(['success' => true]);
        exit();
    }
    
    if (isset($_POST['delete_post'])) {
        $post_id = (int)$_POST['post_id'];
        
        $img_query = "SELECT image_url FROM posts WHERE id = $post_id AND user_id = $current_user_id";
        $img_result = mysqli_query($conn, $img_query);
        $img = mysqli_fetch_assoc($img_result);
        if ($img && $img['image_url'] && file_exists($img['image_url'])) {
            unlink($img['image_url']);
        }
        
        $query = "DELETE FROM posts WHERE id = $post_id AND user_id = $current_user_id";
        mysqli_query($conn, $query);
        echo json_encode(['success' => true]);
        exit();
    }
    
    if (isset($_POST['pin_post'])) {
        $post_id = (int)$_POST['post_id'];
        
        $check = "SELECT pinned_post_id FROM users WHERE id = $current_user_id";
        $check_result = mysqli_query($conn, $check);
        $current_pin = mysqli_fetch_assoc($check_result);
        
        if ($current_pin['pinned_post_id'] == $post_id) {
            $query = "UPDATE users SET pinned_post_id = NULL WHERE id = $current_user_id";
            $pinned = false;
        } else {
            $query = "UPDATE users SET pinned_post_id = $post_id WHERE id = $current_user_id";
            $pinned = true;
        }
        mysqli_query($conn, $query);
        echo json_encode(['success' => true, 'pinned' => $pinned]);
        exit();
    }
}

// Get user stats
$posts_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM posts WHERE user_id = $viewing_user_id"))['count'];
$photos_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM posts WHERE user_id = $viewing_user_id AND image_url != ''"))['count'];
$friends_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM friends_followers WHERE ((follower_id = $viewing_user_id OR following_id = $viewing_user_id) AND status = 'accepted')"))['count'];
$followers_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM friends_followers WHERE following_id = $viewing_user_id AND status = 'accepted'"))['count'];
$following_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM friends_followers WHERE follower_id = $viewing_user_id AND status = 'accepted'"))['count'];

// Get pinned post first, then regular posts
$pinned_post_id = $user['pinned_post_id'];
$pinned_post = null;
if ($pinned_post_id) {
    $pinned_query = "SELECT * FROM posts WHERE id = $pinned_post_id AND user_id = $viewing_user_id";
    $pinned_result = mysqli_query($conn, $pinned_query);
    $pinned_post = mysqli_fetch_assoc($pinned_result);
}

// Get regular posts (excluding pinned if it exists)
$posts_query = "SELECT * FROM posts WHERE user_id = $viewing_user_id";
if ($pinned_post_id) {
    $posts_query .= " AND id != $pinned_post_id";
}
$posts_query .= " ORDER BY created_at DESC";
$posts_result = mysqli_query($conn, $posts_query);

// Get user photos
$photos_query = "SELECT * FROM posts WHERE user_id = $viewing_user_id AND image_url != '' ORDER BY created_at DESC";
$photos_result = mysqli_query($conn, $photos_query);

// Get friends list
$friends_list_query = "SELECT DISTINCT u.* FROM users u
                       WHERE u.id IN (
                           SELECT following_id FROM friends_followers 
                           WHERE follower_id = $viewing_user_id AND status = 'accepted'
                           UNION
                           SELECT follower_id FROM friends_followers 
                           WHERE following_id = $viewing_user_id AND status = 'accepted'
                       )
                       LIMIT 9";
$friends_list = mysqli_query($conn, $friends_list_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($user['full_name']); ?> - TravelMate Profile</title>
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

        

        /* Image Preview Modal */
        .image-preview-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.95);
            backdrop-filter: blur(10px);
            z-index: 10000;
            justify-content: center;
            align-items: center;
            cursor: pointer;
        }

        .image-preview-modal img {
            max-width: 90%;
            max-height: 90%;
            object-fit: contain;
            border-radius: 16px;
        }

        .close-preview {
            position: absolute;
            top: 20px;
            right: 40px;
            color: white;
            font-size: 48px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .close-preview:hover {
            color: #f39c12;
            transform: scale(1.1);
        }

        /* Profile Container */
        .profile-container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* Cover Photo */
        .cover-container {
            position: relative;
            background: white;
            border-radius: 32px 32px 0 0;
            overflow: hidden;
            margin-top: 32px;
        }
        
        .cover-photo {
            height: 350px;
            background-size: cover;
            background-position: center;
            position: relative;
            cursor: pointer;
            transition: all 0.3s;
        }

        .cover-photo:hover {
            opacity: 0.95;
        }
        
        .cover-overlay {
            position: absolute;
            bottom: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
            z-index: 10;
        }
        
        .edit-cover-btn {
            background: rgba(0,0,0,0.6);
            color: white;
            padding: 8px 16px;
            border-radius: 30px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .edit-cover-btn:hover {
            background: rgba(0,0,0,0.8);
            transform: translateY(-2px);
        }

        .remove-cover-btn {
            background: rgba(231, 76, 60, 0.8);
            color: white;
            padding: 8px 16px;
            border-radius: 30px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }

        .remove-cover-btn:hover {
            background: #e74c3c;
            transform: translateY(-2px);
        }

        /* Profile Info Area */
        .profile-info-area {
            background: white;
            border-radius: 0 0 32px 32px;
            padding: 0 32px 32px;
            position: relative;
            margin-bottom: 32px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.04);
            border: 1px solid rgba(0, 0, 0, 0.04);
            border-top: none;
        }
        
        .profile-pic-wrapper {
            position: relative;
            display: inline-block;
            margin-top: -60px;
            margin-left: 20px;
        }
        
        .profile-pic {
            width: 168px;
            height: 168px;
            border-radius: 50%;
            border: 4px solid white;
            object-fit: cover;
            background: white;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
            cursor: pointer;
            transition: all 0.3s;
        }

        .profile-pic:hover {
            transform: scale(1.02);
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.15);
        }
        
        .edit-pic-btn {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: #f39c12;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            color: white;
        }
        
        .edit-pic-btn:hover {
            background: #e67e22;
            transform: scale(1.1);
        }

        .remove-pic-btn {
            position: absolute;
            bottom: 10px;
            right: 55px;
            background: #e74c3c;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            color: white;
        }

        .remove-pic-btn:hover {
            background: #c0392b;
            transform: scale(1.1);
        }
        
        .profile-name-section {
            margin-left: 200px;
            margin-top: -80px;
            padding: 20px 0;
        }
        
        .profile-name {
            font-size: 32px;
            font-weight: 700;
            color: #2c3e50;
        }
        
        .profile-bio {
            color: #7f8c8d;
            margin-top: 8px;
        }
        
        .profile-stats {
            display: flex;
            gap: 30px;
            margin-top: 20px;
        }
        
        .stat-item {
            cursor: pointer;
            transition: all 0.2s;
            padding: 5px 10px;
            border-radius: 12px;
        }
        
        .stat-item:hover {
            background: #f8f9fa;
            transform: translateY(-2px);
        }
        
        .stat-number {
            font-weight: 700;
            font-size: 18px;
            color: #f39c12;
        }
        
        .stat-label {
            color: #7f8c8d;
            font-size: 14px;
        }
        
        .edit-profile-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            background: #f0f3f2;
            padding: 10px 20px;
            border-radius: 30px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            color: #2c3e50;
        }
        
        .edit-profile-btn:hover {
            background: #e67e22;
            color: white;
            transform: translateY(-2px);
        }
        
        /* Friend Action Buttons */
        .friend-action-buttons {
            position: absolute;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
        }
        
        .add-friend-btn {
            background: #f39c12;
            color: white;
            padding: 10px 20px;
            border-radius: 30px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .add-friend-btn:hover {
            background: #e67e22;
            transform: translateY(-2px);
        }
        
        .pending-btn {
            background: #f0f3f2;
            color: #7f8c8d;
            padding: 10px 20px;
            border-radius: 30px;
            border: none;
            cursor: default;
            font-weight: 500;
        }
        
        .message-btn {
            background: #f0f3f2;
            color: #2c3e50;
            padding: 10px 20px;
            border-radius: 30px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .message-btn:hover {
            background: #f39c12;
            color: white;
            transform: translateY(-2px);
        }

        /* Profile Content Grid */
        .profile-content {
            display: grid;
            grid-template-columns: 360px 1fr;
            gap: 24px;
            margin-bottom: 32px;
        }
        
        /* Left Column */
        .about-section {
            background: white;
            border-radius: 24px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.04);
            border: 1px solid rgba(0, 0, 0, 0.04);
        }
        
        .section-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #2c3e50;
        }
        
        .edit-link {
            color: #f39c12;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
        }
        
        .edit-link:hover {
            text-decoration: underline;
        }
        
        .info-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 0;
            border-bottom: 1px solid #e8ecef;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-icon {
            width: 36px;
            color: #f39c12;
            font-size: 18px;
        }
        
        .info-text {
            flex: 1;
        }
        
        .info-label {
            font-size: 12px;
            color: #95a5a6;
        }
        
        .info-value {
            font-weight: 500;
            color: #2c3e50;
        }
        
        /* Friends Section */
        .friends-section {
            background: white;
            border-radius: 24px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.04);
            border: 1px solid rgba(0, 0, 0, 0.04);
        }
        
        .friends-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-top: 16px;
        }
        
        .friend-card {
            text-align: center;
            cursor: pointer;
            padding: 12px;
            border-radius: 20px;
            transition: all 0.3s;
        }
        
        .friend-card:hover {
            background: #fef9f0;
            transform: translateY(-3px);
        }
        
        .friend-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #f39c12;
        }
        
        .friend-name {
            font-size: 13px;
            font-weight: 500;
            margin-top: 8px;
            color: #2c3e50;
        }
        
        /* Right Column - Posts */
        .posts-section {
            background: white;
            border-radius: 24px;
            padding: 24px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.04);
            border: 1px solid rgba(0, 0, 0, 0.04);
        }
        
        .post-tabs {
            display: flex;
            gap: 20px;
            border-bottom: 2px solid #e8ecef;
            margin-bottom: 24px;
        }
        
        .post-tab {
            padding: 12px 0;
            cursor: pointer;
            color: #95a5a6;
            font-weight: 600;
            position: relative;
            transition: all 0.3s;
        }
        
        .post-tab:hover {
            color: #f39c12;
        }
        
        .post-tab.active {
            color: #f39c12;
        }
        
        .post-tab.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 3px;
            background: #f39c12;
            border-radius: 3px;
        }
        
        /* Photo Grid */
        .photos-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
        }
        
        .photo-item {
            position: relative;
            aspect-ratio: 1;
            overflow: hidden;
            cursor: pointer;
            border-radius: 16px;
        }
        
        .photo-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }
        
        .photo-item:hover img {
            transform: scale(1.05);
        }
        
        /* Post Card */
        .post-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
            transition: all 0.3s;
            border: 1px solid rgba(0, 0, 0, 0.04);
        }
        
        .post-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
        }
        
        .pinned-badge {
            display: inline-block;
            background: #fef9f0;
            color: #f39c12;
            font-size: 11px;
            padding: 4px 10px;
            border-radius: 20px;
            margin-left: 10px;
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
        
        .post-content {
            margin-bottom: 16px;
            line-height: 1.6;
            font-size: 15px;
            color: #34495e;
        }
        
        .post-image-container {
            width: 100%;
            overflow: hidden;
            border-radius: 16px;
            margin: 16px 0;
        }
        
        .post-image {
            width: 100%;
            height: auto;
            border-radius: 16px;
            display: block;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .post-image:hover {
            transform: scale(1.02);
        }
        
        .post-stats {
            display: flex;
            gap: 20px;
            padding-top: 12px;
            border-top: 1px solid #e8ecef;
            color: #95a5a6;
            font-size: 13px;
        }
        
        /* Three Dot Menu */
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
            color: #f39c12;
        }

        .dropdown-item:hover {
            background: #f8f9fa;
        }

        .dropdown-item.danger {
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
        
        .divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, #e8ecef, transparent);
            margin: 16px 0;
        }

        /* Modal */
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
        }
        
        .close-modal {
            cursor: pointer;
            font-size: 28px;
            transition: all 0.3s;
            color: #95a5a6;
        }
        
        .close-modal:hover {
            color: #e74c3c;
        }
        
        .modal input, .modal textarea {
            width: 100%;
            padding: 14px;
            margin: 12px 0;
            border: 1px solid #e8ecef;
            border-radius: 24px;
            font-size: 14px;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        
        .modal input:focus, .modal textarea:focus {
            outline: none;
            border-color: #f39c12;
        }
        
        .modal button {
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
        
        .modal button:hover {
            background: #e67e22;
            transform: translateY(-2px);
        }
        
        .error-message {
            background: #fee;
            color: #e74c3c;
            padding: 12px;
            border-radius: 16px;
            margin-bottom: 16px;
            text-align: center;
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
        }
        
        @media (max-width: 900px) {
            .profile-content {
                grid-template-columns: 1fr;
            }
            .profile-name-section {
                margin-left: 0;
                margin-top: 16px;
                text-align: center;
            }
            .profile-pic-wrapper {
                display: block;
                text-align: center;
                margin-left: 0;
            }
            .edit-profile-btn, .friend-action-buttons {
                position: static;
                margin-top: 16px;
                width: 100%;
                justify-content: center;
            }
            .profile-stats {
                justify-content: center;
                flex-wrap: wrap;
            }
            .photos-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .friends-grid {
                grid-template-columns: repeat(2, 1fr);
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
            <a href="homepage.php" class="nav-link">Home</a>
            <a href="map.php" class="nav-link">Map</a>
            <a href="profile.php" class="nav-link active">Profile</a>
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

    <div class="profile-container">
        <!-- Cover Photo -->
        <div class="cover-container">
            <?php
            $cover_photo_path = 'uploads/covers/' . $user['cover_photo'];
            if (!empty($user['cover_photo']) && $user['cover_photo'] != 'default_cover.jpg' && file_exists($cover_photo_path)) {
                $cover_url = $cover_photo_path;
            } else {
                $cover_url = 'https://via.placeholder.com/1200x350/f5f5f0/f39c12?text=Cover+Photo';
            }
            ?>
            <div class="cover-photo" style="background-image: url('<?php echo $cover_url; ?>'); background-size: cover; background-position: center;" 
                 onclick="viewFullImage('<?php echo $cover_url; ?>')">
                <?php if ($is_own_profile): ?>
                <div class="cover-overlay" onclick="event.stopPropagation()">
                    <button class="edit-cover-btn" onclick="openCoverModal()">
                        <i class="fas fa-camera"></i> Edit Cover
                    </button>
                    <?php if (!empty($user['cover_photo']) && $user['cover_photo'] != 'default_cover.jpg' && file_exists('uploads/covers/' . $user['cover_photo'])): ?>
                    <button class="remove-cover-btn" onclick="removeCoverPhoto()">
                        <i class="fas fa-trash-alt"></i> Remove
                    </button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Profile Info -->
        <div class="profile-info-area">
            <div class="profile-pic-wrapper">
                <?php
                $profile_pic_path = 'uploads/profiles/' . $user['profile_pic'];
                if (!empty($user['profile_pic']) && $user['profile_pic'] != 'default.jpg' && file_exists($profile_pic_path)) {
                    $profile_url = $profile_pic_path;
                    $profile_full_url = $profile_pic_path;
                } else {
                    $name = urlencode($user['full_name']);
                    $profile_url = "https://ui-avatars.com/api/?background=f39c12&color=fff&rounded=true&size=168&bold=true&name=$name";
                    $profile_full_url = '';
                }
                ?>
                <img src="<?php echo $profile_url; ?>" class="profile-pic" alt="Profile Picture" id="mainProfilePic" 
                     onclick="viewFullImage('<?php echo $profile_full_url; ?>')">
                <?php if ($is_own_profile): ?>
                <div class="edit-pic-btn" onclick="openProfilePicModal()">
                    <i class="fas fa-camera"></i>
                </div>
                <?php if (!empty($user['profile_pic']) && $user['profile_pic'] != 'default.jpg' && file_exists('uploads/profiles/' . $user['profile_pic'])): ?>
                <div class="remove-pic-btn" onclick="removeProfilePic()">
                    <i class="fas fa-trash-alt"></i>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <div class="profile-name-section">
                <div class="profile-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
                <div class="profile-bio"><?php echo htmlspecialchars($user['bio'] ?: "No bio yet. Share your travel story!"); ?></div>
                
                <div class="profile-stats">
                    <div class="stat-item" onclick="showPostsTab()">
                        <span class="stat-number"><?php echo $posts_count; ?></span>
                        <span class="stat-label">journeys</span>
                    </div>
                    <div class="stat-item" onclick="window.location.href='friends.php'">
                        <span class="stat-number"><?php echo $friends_count; ?></span>
                        <span class="stat-label">companions</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo $followers_count; ?></span>
                        <span class="stat-label">followers</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo $following_count; ?></span>
                        <span class="stat-label">following</span>
                    </div>
                </div>
            </div>
            
            <?php if ($is_own_profile): ?>
                <button class="edit-profile-btn" onclick="openEditProfileModal()">
                    <i class="fas fa-pen"></i> Edit Profile
                </button>
            <?php else: ?>
                <div class="friend-action-buttons">
                    <?php if ($are_friends): ?>
                        <button class="message-btn" onclick="messageUser(<?php echo $viewing_user_id; ?>)">
                            <i class="fas fa-comment"></i> Message
                        </button>
                        <button class="edit-profile-btn" onclick="unfriend(<?php echo $viewing_user_id; ?>, '<?php echo addslashes($user['full_name']); ?>')">
                            <i class="fas fa-user-minus"></i> Unfriend
                        </button>
                    <?php elseif ($has_pending_request): ?>
                        <button class="pending-btn" disabled>
                            <i class="fas fa-clock"></i> Request Sent
                        </button>
                        <button class="edit-profile-btn" onclick="cancelRequest(<?php echo $viewing_user_id; ?>)">
                            Cancel
                        </button>
                    <?php elseif ($has_received_request): ?>
                        <button class="add-friend-btn" onclick="acceptRequest(<?php echo $viewing_user_id; ?>)">
                            <i class="fas fa-check"></i> Confirm
                        </button>
                        <button class="edit-profile-btn" onclick="declineRequest(<?php echo $viewing_user_id; ?>)">
                            Delete
                        </button>
                    <?php else: ?>
                        <button class="add-friend-btn" onclick="sendFriendRequest(<?php echo $viewing_user_id; ?>, '<?php echo addslashes($user['full_name']); ?>')">
                            <i class="fas fa-user-plus"></i> Add Companion
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Profile Content -->
        <div class="profile-content">
            <!-- Left Column -->
            <div class="left-column">
                <!-- About Section -->
                <div class="about-section">
                    <div class="section-title">
                        About
                        <?php if ($is_own_profile): ?>
                        <span class="edit-link" onclick="openEditInfoModal()">Edit</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-icon"><i class="fas fa-map-marker-alt"></i></div>
                        <div class="info-text">
                            <div class="info-label">From</div>
                            <div class="info-value"><?php echo htmlspecialchars($user['location'] ?? 'Not specified'); ?></div>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-icon"><i class="fas fa-birthday-cake"></i></div>
                        <div class="info-text">
                            <div class="info-label">Born</div>
                            <div class="info-value"><?php echo htmlspecialchars($user['birthdate'] ?? 'Not specified'); ?></div>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-icon"><i class="fas fa-graduation-cap"></i></div>
                        <div class="info-text">
                            <div class="info-label">Education</div>
                            <div class="info-value"><?php echo htmlspecialchars($user['education'] ?? 'Not specified'); ?></div>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-icon"><i class="fas fa-envelope"></i></div>
                        <div class="info-text">
                            <div class="info-label">Email</div>
                            <div class="info-value"><?php echo htmlspecialchars($user['email']); ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- Friends Section -->
                <div class="friends-section">
                    <div class="section-title">
                        Travel Companions
                        <a href="friends.php" class="edit-link">See all →</a>
                    </div>
                    <div class="friends-grid">
                        <?php if (mysqli_num_rows($friends_list) == 0): ?>
                            <div style="grid-column: span 3; text-align: center; color: #95a5a6; padding: 20px;">
                                No companions yet
                            </div>
                        <?php else: ?>
                            <?php while ($friend = mysqli_fetch_assoc($friends_list)): ?>
                                <div class="friend-card" onclick="viewProfile(<?php echo $friend['id']; ?>)">
                                    <?php
                                    $friend_pic = $friend['profile_pic'];
                                    if (!empty($friend_pic) && $friend_pic != 'default.jpg' && file_exists('uploads/profiles/' . $friend_pic)) {
                                        $friend_avatar_url = 'uploads/profiles/' . $friend_pic;
                                    } else {
                                        $name = urlencode($friend['full_name']);
                                        $friend_avatar_url = "https://ui-avatars.com/api/?background=f39c12&color=fff&rounded=true&size=80&bold=true&name=$name";
                                    }
                                    ?>
                                    <img src="<?php echo $friend_avatar_url; ?>" class="friend-avatar">
                                    <div class="friend-name"><?php echo htmlspecialchars($friend['full_name']); ?></div>
                                </div>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column - Posts -->
            <div class="posts-section">
                <div class="post-tabs">
                    <div class="post-tab active" onclick="switchTab('posts')">Journeys</div>
                    <div class="post-tab" onclick="switchTab('photos')">Memories</div>
                </div>
                
                <!-- Posts Tab Content -->
                <div id="postsContent" class="tab-content">
                    <?php if ($posts_count == 0 && !$pinned_post): ?>
                        <div style="text-align: center; padding: 60px 20px; color: #95a5a6;">
                            <i class="fas fa-compass" style="font-size: 48px; margin-bottom: 16px; display: block;"></i>
                            No journeys shared yet
                        </div>
                    <?php else: ?>
                        <!-- Pinned Post -->
                        <?php if ($pinned_post): ?>
                            <div class="post-card" style="border: 2px solid #f39c12;">
                                <div class="post-header">
                                    <div class="post-header-left">
                                        <?php
                                        $post_avatar_pic = $user['profile_pic'];
                                        if (!empty($post_avatar_pic) && $post_avatar_pic != 'default.jpg' && file_exists('uploads/profiles/' . $post_avatar_pic)) {
                                            $post_avatar_url = 'uploads/profiles/' . $post_avatar_pic;
                                        } else {
                                            $name = urlencode($user['full_name']);
                                            $post_avatar_url = "https://ui-avatars.com/api/?background=f39c12&color=fff&rounded=true&size=48&bold=true&name=$name";
                                        }
                                        ?>
                                        <img src="<?php echo $post_avatar_url; ?>" class="post-avatar" onclick="viewProfile(<?php echo $user['id']; ?>)">
                                        <div>
                                            <div class="post-author" onclick="viewProfile(<?php echo $user['id']; ?>)">
                                                <?php echo htmlspecialchars($user['full_name']); ?>
                                                <span class="pinned-badge"><i class="fas fa-thumbtack"></i> Pinned</span>
                                            </div>
                                            <div class="post-time"><?php echo date('M j, Y', strtotime($pinned_post['created_at'])); ?></div>
                                        </div>
                                    </div>
                                    <?php if ($is_own_profile): ?>
                                    <div class="post-menu">
                                        <div class="three-dots" onclick="toggleMenu(this)">
                                            <i class="fas fa-ellipsis-h"></i>
                                        </div>
                                        <div class="dropdown-menu">
                                            <div class="dropdown-item" onclick="editProfilePost(<?php echo $pinned_post['id']; ?>, '<?php echo addslashes($pinned_post['content']); ?>', '<?php echo addslashes($pinned_post['location_name']); ?>')">
                                                <i class="fas fa-pen"></i> Edit Post
                                            </div>
                                            <div class="dropdown-item" onclick="pinProfilePost(<?php echo $pinned_post['id']; ?>)">
                                                <i class="fas fa-thumbtack"></i> Unpin from Profile
                                            </div>
                                            <div class="dropdown-divider"></div>
                                            <div class="dropdown-item danger" onclick="deleteProfilePost(<?php echo $pinned_post['id']; ?>)">
                                                <i class="fas fa-trash-alt"></i> Delete Post
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="post-content"><?php echo nl2br(htmlspecialchars($pinned_post['content'])); ?></div>
                                <?php if ($pinned_post['image_url']): ?>
                                    <div class="post-image-container">
                                        <img src="<?php echo $pinned_post['image_url']; ?>" class="post-image" onclick="viewFullImage('<?php echo $pinned_post['image_url']; ?>')">
                                    </div>
                                <?php endif; ?>
                                <?php if ($pinned_post['location_name']): ?>
                                    <div style="display: inline-flex; align-items: center; gap: 6px; background: #fef9f0; padding: 6px 14px; border-radius: 30px; font-size: 12px; color: #f39c12; margin: 12px 0;">
                                        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($pinned_post['location_name']); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="post-stats">
                                    <span><i class="fas fa-heart"></i> Like</span>
                                    <span><i class="fas fa-comment"></i> Comment</span>
                                    <span><i class="fas fa-share"></i> Share</span>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Regular Posts -->
                        <?php while ($post = mysqli_fetch_assoc($posts_result)): ?>
                            <div class="post-card">
                                <div class="post-header">
                                    <div class="post-header-left">
                                        <?php
                                        $post_avatar_pic = $user['profile_pic'];
                                        if (!empty($post_avatar_pic) && $post_avatar_pic != 'default.jpg' && file_exists('uploads/profiles/' . $post_avatar_pic)) {
                                            $post_avatar_url = 'uploads/profiles/' . $post_avatar_pic;
                                        } else {
                                            $name = urlencode($user['full_name']);
                                            $post_avatar_url = "https://ui-avatars.com/api/?background=f39c12&color=fff&rounded=true&size=48&bold=true&name=$name";
                                        }
                                        ?>
                                        <img src="<?php echo $post_avatar_url; ?>" class="post-avatar" onclick="viewProfile(<?php echo $user['id']; ?>)">
                                        <div>
                                            <div class="post-author" onclick="viewProfile(<?php echo $user['id']; ?>)">
                                                <?php echo htmlspecialchars($user['full_name']); ?>
                                            </div>
                                            <div class="post-time"><?php echo date('M j, Y', strtotime($post['created_at'])); ?></div>
                                        </div>
                                    </div>
                                    <?php if ($is_own_profile): ?>
                                    <div class="post-menu">
                                        <div class="three-dots" onclick="toggleMenu(this)">
                                            <i class="fas fa-ellipsis-h"></i>
                                        </div>
                                        <div class="dropdown-menu">
                                            <div class="dropdown-item" onclick="editProfilePost(<?php echo $post['id']; ?>, '<?php echo addslashes($post['content']); ?>', '<?php echo addslashes($post['location_name']); ?>')">
                                                <i class="fas fa-pen"></i> Edit Post
                                            </div>
                                            <div class="dropdown-item" onclick="pinProfilePost(<?php echo $post['id']; ?>)">
                                                <i class="fas fa-thumbtack"></i> Pin to Profile
                                            </div>
                                            <div class="dropdown-divider"></div>
                                            <div class="dropdown-item danger" onclick="deleteProfilePost(<?php echo $post['id']; ?>)">
                                                <i class="fas fa-trash-alt"></i> Delete Post
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="post-content"><?php echo nl2br(htmlspecialchars($post['content'])); ?></div>
                                <?php if ($post['image_url']): ?>
                                    <div class="post-image-container">
                                        <img src="<?php echo $post['image_url']; ?>" class="post-image" onclick="viewFullImage('<?php echo $post['image_url']; ?>')">
                                    </div>
                                <?php endif; ?>
                                <?php if ($post['location_name']): ?>
                                    <div style="display: inline-flex; align-items: center; gap: 6px; background: #fef9f0; padding: 6px 14px; border-radius: 30px; font-size: 12px; color: #f39c12; margin: 12px 0;">
                                        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($post['location_name']); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="post-stats">
                                    <span><i class="fas fa-heart"></i> Like</span>
                                    <span><i class="fas fa-comment"></i> Comment</span>
                                    <span><i class="fas fa-share"></i> Share</span>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Photos Tab Content -->
                <div id="photosContent" class="tab-content" style="display: none;">
                    <?php if (mysqli_num_rows($photos_result) == 0): ?>
                        <div style="text-align: center; padding: 60px 20px; color: #95a5a6;">
                            <i class="fas fa-image" style="font-size: 48px; margin-bottom: 16px; display: block;"></i>
                            No memories shared yet
                        </div>
                    <?php else: ?>
                        <div class="photos-grid">
                            <?php while ($photo = mysqli_fetch_assoc($photos_result)): ?>
                                <div class="photo-item" onclick="viewFullImage('<?php echo $photo['image_url']; ?>')">
                                    <img src="<?php echo $photo['image_url']; ?>" alt="Memory">
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Fullscreen Image Preview Modal -->
    <div id="fullscreenImageModal" class="image-preview-modal" onclick="closeFullscreenImage()">
        <span class="close-preview" onclick="closeFullscreenImage()">&times;</span>
        <img id="fullscreenImage" src="">
    </div>

    <!-- Modals -->
    <div id="editProfileModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <i class="fas fa-pen"></i> Edit Bio
                <span class="close-modal" onclick="closeModal('editProfileModal')">&times;</span>
            </div>
            <form method="POST">
                <textarea name="bio" rows="4" placeholder="Share your travel story..."><?php echo htmlspecialchars($user['bio']); ?></textarea>
                <button type="submit" name="update_bio">Save</button>
            </form>
        </div>
    </div>

    <div id="editInfoModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <i class="fas fa-user-edit"></i> Edit Details
                <span class="close-modal" onclick="closeModal('editInfoModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" placeholder="Full Name">
                <input type="text" name="location" value="<?php echo htmlspecialchars($user['location'] ?? ''); ?>" placeholder="Location">
                <input type="date" name="birthdate" value="<?php echo htmlspecialchars($user['birthdate'] ?? ''); ?>">
                <input type="text" name="education" value="<?php echo htmlspecialchars($user['education'] ?? ''); ?>" placeholder="Education">
                <button type="submit" name="update_info">Save Changes</button>
            </form>
        </div>
    </div>

    <div id="profilePicModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <i class="fas fa-camera"></i> Update Profile Picture
                <span class="close-modal" onclick="closeModal('profilePicModal')">&times;</span>
            </div>
            <?php if (isset($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            <form method="POST" enctype="multipart/form-data">
                <input type="file" name="profile_pic" accept="image/*" required>
                <button type="submit">Upload</button>
            </form>
        </div>
    </div>

    <div id="coverModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <i class="fas fa-camera"></i> Update Cover Photo
                <span class="close-modal" onclick="closeModal('coverModal')">&times;</span>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="file" name="cover_photo" accept="image/*" required>
                <button type="submit">Upload</button>
            </form>
        </div>
    </div>

    <div id="editPostModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <i class="fas fa-pen"></i> Edit Journey
                <span class="close-modal" onclick="closeModal('editPostModal')">&times;</span>
            </div>
            <textarea id="editContent" rows="4" placeholder="What's on your mind?"></textarea>
            <input type="text" id="editLocation" placeholder="Location">
            <input type="hidden" id="editPostId">
            <button onclick="saveProfileEdit()"><i class="fas fa-save"></i> Save Changes</button>
        </div>
    </div>

    <div id="toast" class="toast"></div>

    <script>
        // Fullscreen image view
        function viewFullImage(imageUrl) {
            const modal = document.getElementById('fullscreenImageModal');
            const img = document.getElementById('fullscreenImage');
            img.src = imageUrl;
            modal.style.display = 'flex';
        }

        function closeFullscreenImage() {
            document.getElementById('fullscreenImageModal').style.display = 'none';
        }

        function removeProfilePic() {
            if (confirm('Are you sure you want to remove your profile picture?')) {
                const formData = new FormData();
                formData.append('remove_profile_pic', true);
                
                fetch('profile.php', {
                    method: 'POST',
                    body: formData
                }).then(() => {
                    location.reload();
                });
            }
        }

        function removeCoverPhoto() {
            if (confirm('Are you sure you want to remove your cover photo?')) {
                const formData = new FormData();
                formData.append('remove_cover_photo', true);
                
                fetch('profile.php', {
                    method: 'POST',
                    body: formData
                }).then(() => {
                    location.reload();
                });
            }
        }

        function viewProfile(userId) {
            window.location.href = `profile.php?id=${userId}`;
        }
        
        function openEditProfileModal() { document.getElementById('editProfileModal').style.display = 'flex'; }
        function openEditInfoModal() { document.getElementById('editInfoModal').style.display = 'flex'; }
        function openProfilePicModal() { document.getElementById('profilePicModal').style.display = 'flex'; }
        function openCoverModal() { document.getElementById('coverModal').style.display = 'flex'; }
        function closeModal(modalId) { document.getElementById(modalId).style.display = 'none'; }
        
        function showPostsTab() { switchTab('posts'); }
        
        function switchTab(tab) {
            document.querySelectorAll('.post-tab').forEach(t => t.classList.remove('active'));
            event.target.classList.add('active');
            
            document.querySelectorAll('.tab-content').forEach(content => {
                content.style.display = 'none';
            });
            
            if (tab === 'posts') {
                document.getElementById('postsContent').style.display = 'block';
            } else if (tab === 'photos') {
                document.getElementById('photosContent').style.display = 'block';
            }
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
        
        function editProfilePost(postId, content, location) {
            document.getElementById('editContent').value = content;
            document.getElementById('editLocation').value = location || '';
            document.getElementById('editPostId').value = postId;
            document.getElementById('editPostModal').style.display = 'flex';
        }
        
        function saveProfileEdit() {
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
            
            fetch('profile.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Post updated!');
                    closeModal('editPostModal');
                    location.reload();
                }
            });
        }
        
        function pinProfilePost(postId) {
            const formData = new FormData();
            formData.append('pin_post', true);
            formData.append('post_id', postId);
            
            fetch('profile.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.pinned ? 'Post pinned to profile!' : 'Post unpinned');
                    location.reload();
                }
            });
        }
        
        function deleteProfilePost(postId) {
            if (confirm('Are you sure you want to delete this post?')) {
                const formData = new FormData();
                formData.append('delete_post', true);
                formData.append('post_id', postId);
                
                fetch('profile.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Post deleted');
                        location.reload();
                    }
                });
            }
        }
        
        function sendFriendRequest(userId, userName) {
            const formData = new FormData();
            formData.append('action', 'send_request');
            formData.append('friend_id', userId);
            
            fetch('friends.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(`Request sent to ${userName}!`);
                    setTimeout(() => location.reload(), 1500);
                }
            });
        }
        
        function acceptRequest(userId) {
            const formData = new FormData();
            formData.append('action', 'accept_request');
            formData.append('friend_id', userId);
            
            fetch('friends.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Request accepted!');
                    setTimeout(() => location.reload(), 1500);
                }
            });
        }
        
        function declineRequest(userId) {
            const formData = new FormData();
            formData.append('action', 'decline_request');
            formData.append('friend_id', userId);
            
            fetch('friends.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Request declined');
                    setTimeout(() => location.reload(), 1500);
                }
            });
        }
        
        function unfriend(userId, userName) {
            if (confirm(`Remove ${userName} from your companions?`)) {
                const formData = new FormData();
                formData.append('action', 'unfriend');
                formData.append('friend_id', userId);
                
                fetch('friends.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast(`${userName} removed`);
                        setTimeout(() => location.reload(), 1500);
                    }
                });
            }
        }
        
        function cancelRequest(userId) {
            const formData = new FormData();
            formData.append('action', 'cancel_request');
            formData.append('friend_id', userId);
            
            fetch('friends.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Request cancelled');
                    setTimeout(() => location.reload(), 1500);
                }
            });
        }
        
        function messageUser(userId) {
            showToast('Messaging coming soon!');
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
    </script>
</body>
</html>