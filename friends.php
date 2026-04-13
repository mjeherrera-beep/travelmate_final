<?php
// friends.php
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

$current_user_id = $_SESSION['user_id'];

// Get current user data
$user_query = "SELECT * FROM users WHERE id = $current_user_id";
$user_result = mysqli_query($conn, $user_query);
$current_user = mysqli_fetch_assoc($user_result);

// Handle friend request actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && isset($_POST['friend_id'])) {
        $friend_id = (int)$_POST['friend_id'];
        $action = $_POST['action'];
        
        if ($action == 'send_request') {
            $check = "SELECT id FROM friends_followers 
                      WHERE (follower_id = $current_user_id AND following_id = $friend_id) 
                         OR (follower_id = $friend_id AND following_id = $current_user_id)";
            $check_result = mysqli_query($conn, $check);
            
            if (mysqli_num_rows($check_result) == 0) {
                $insert = "INSERT INTO friends_followers (follower_id, following_id, status) 
                           VALUES ($current_user_id, $friend_id, 'pending')";
                mysqli_query($conn, $insert);
            }
        } 
        elseif ($action == 'accept_request') {
            $update = "UPDATE friends_followers 
                       SET status = 'accepted' 
                       WHERE follower_id = $friend_id AND following_id = $current_user_id AND status = 'pending'";
            mysqli_query($conn, $update);
        }
        elseif ($action == 'decline_request') {
            $delete = "DELETE FROM friends_followers 
                       WHERE follower_id = $friend_id AND following_id = $current_user_id AND status = 'pending'";
            mysqli_query($conn, $delete);
        }
        elseif ($action == 'unfriend') {
            $delete = "DELETE FROM friends_followers 
                       WHERE (follower_id = $current_user_id AND following_id = $friend_id) 
                          OR (follower_id = $friend_id AND following_id = $current_user_id)";
            mysqli_query($conn, $delete);
        }
        elseif ($action == 'cancel_request') {
            $delete = "DELETE FROM friends_followers 
                       WHERE follower_id = $current_user_id AND following_id = $friend_id AND status = 'pending'";
            mysqli_query($conn, $delete);
        }
        
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode(['success' => true]);
            exit();
        }
        
        header("Location: friends.php");
        exit();
    }
}

// Get friend requests
$friend_requests_query = "SELECT u.*, f.created_at as request_date,
                         (SELECT COUNT(*) FROM friends_followers 
                          WHERE follower_id = u.id AND status = 'accepted') as mutual_count
                         FROM users u
                         JOIN friends_followers f ON u.id = f.follower_id
                         WHERE f.following_id = $current_user_id AND f.status = 'pending'
                         ORDER BY f.created_at DESC";
$friend_requests = mysqli_query($conn, $friend_requests_query);
$friend_requests_count = mysqli_num_rows($friend_requests);

// Get sent requests
$sent_requests_query = "SELECT u.*, f.created_at as request_date
                        FROM users u
                        JOIN friends_followers f ON u.id = f.following_id
                        WHERE f.follower_id = $current_user_id AND f.status = 'pending'
                        ORDER BY f.created_at DESC";
$sent_requests = mysqli_query($conn, $sent_requests_query);

// Get all friends
$friends_query = "SELECT u.*, 
                 (SELECT COUNT(*) FROM friends_followers 
                  WHERE follower_id = u.id AND status = 'accepted') as mutual_count
                 FROM users u
                 JOIN friends_followers f ON u.id = f.following_id
                 WHERE f.follower_id = $current_user_id AND f.status = 'accepted'
                 UNION
                 SELECT u.*,
                 (SELECT COUNT(*) FROM friends_followers 
                  WHERE follower_id = u.id AND status = 'accepted') as mutual_count
                 FROM users u
                 JOIN friends_followers f ON u.id = f.follower_id
                 WHERE f.following_id = $current_user_id AND f.status = 'accepted'
                 ORDER BY full_name ASC";
$friends = mysqli_query($conn, $friends_query);
$friends_count = mysqli_num_rows($friends);

// Get suggestions
$suggestions_query = "SELECT u.*,
                     (SELECT COUNT(*) FROM friends_followers 
                      WHERE follower_id = u.id AND status = 'accepted') as mutual_count
                     FROM users u
                     WHERE u.id != $current_user_id
                     AND u.id NOT IN (
                         SELECT following_id FROM friends_followers 
                         WHERE follower_id = $current_user_id
                     )
                     AND u.id NOT IN (
                         SELECT follower_id FROM friends_followers 
                         WHERE following_id = $current_user_id
                     )
                     ORDER BY RAND()
                     LIMIT 20";
$suggestions = mysqli_query($conn, $suggestions_query);

// Get birthday friends
$birthdays_query = "SELECT u.* 
                   FROM users u
                   JOIN friends_followers f ON u.id = f.following_id
                   WHERE f.follower_id = $current_user_id AND f.status = 'accepted'
                   AND MONTH(u.birthdate) = MONTH(CURDATE())
                   UNION
                   SELECT u.*
                   FROM users u
                   JOIN friends_followers f ON u.id = f.follower_id
                   WHERE f.following_id = $current_user_id AND f.status = 'accepted'
                   AND MONTH(u.birthdate) = MONTH(CURDATE())
                   ORDER BY DAY(birthdate) ASC";
$birthdays = mysqli_query($conn, $birthdays_query);
$birthdays_count = mysqli_num_rows($birthdays);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Travel Companions - TravelMate</title>
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

        /* Friends Container */
        .friends-container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* Header Section */
        .friends-header {
            background: white;
            border-radius: 24px;
            padding: 32px;
            margin-bottom: 24px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
            border: 1px solid #e8e8e8;
        }

        .friends-header h1 {
            font-size: 28px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 8px;
        }

        .friends-header h1 i {
            color: #f39c12;
            margin-right: 12px;
        }

        .friends-header p {
            color: #8a8a8a;
            font-size: 14px;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: white;
            padding: 24px;
            position: relative;
            transition: all 0.3s;
            cursor: pointer;
            border: 1px solid #e8e8e8;
            border-radius: 20px;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
            border-color: #f39c12;
        }

        .stat-icon {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 16px;
        }

        .stat-icon.companions { background: #fef9f0; color: #f39c12; }
        .stat-icon.requests { background: #fee; color: #e74c3c; }
        .stat-icon.sent { background: #e8f8f5; color: #1abc9c; }
        .stat-icon.birthdays { background: #fef9e8; color: #f1c40f; }

        .stat-info h3 {
            font-size: 28px;
            font-weight: 700;
            color: #1a1a1a;
        }

        .stat-info p {
            color: #8a8a8a;
            font-size: 13px;
            margin-top: 4px;
        }

        /* Tabs */
        .friends-tabs {
            background: white;
            padding: 8px;
            margin-bottom: 24px;
            border: 1px solid #e8e8e8;
            border-radius: 60px;
            display: inline-block;
            width: auto;
        }

        .tabs-container {
            display: flex;
            gap: 4px;
            overflow-x: auto;
            scrollbar-width: none;
        }

        .tabs-container::-webkit-scrollbar {
            display: none;
        }

        .tab-btn {
            padding: 10px 24px;
            background: none;
            border: none;
            font-size: 14px;
            font-weight: 500;
            color: #8a8a8a;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
            border-radius: 40px;
        }

        .tab-btn:hover {
            background: #f8f8f5;
            color: #f39c12;
        }

        .tab-btn.active {
            background: #f39c12;
            color: white;
        }

        .tab-badge {
            background: #e8e8e8;
            color: #8a8a8a;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 11px;
            margin-left: 8px;
        }

        .tab-btn.active .tab-badge {
            background: white;
            color: #f39c12;
        }

        /* Content Panels */
        .tab-panel {
            display: none;
            animation: fadeSlide 0.3s ease;
        }

        .tab-panel.active {
            display: block;
        }

        @keyframes fadeSlide {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Request Cards */
        .request-card {
            background: white;
            padding: 20px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.3s;
            border: 1px solid #e8e8e8;
            border-radius: 20px;
        }

        .request-card:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            border-color: #f39c12;
        }

        .request-info {
            display: flex;
            align-items: center;
            gap: 16px;
            flex: 1;
        }

        .request-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid #f39c12;
        }

        .request-avatar:hover {
            transform: scale(1.05);
        }

        .request-details h3 {
            font-size: 16px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 4px;
            cursor: pointer;
        }

        .request-details h3:hover {
            color: #f39c12;
        }

        .mutual-friends {
            font-size: 12px;
            color: #8a8a8a;
        }

        .mutual-friends i {
            color: #f39c12;
            margin-right: 4px;
        }

        .request-actions {
            display: flex;
            gap: 10px;
        }

        .btn-accept {
            background: #f39c12;
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-accept:hover {
            background: #e67e22;
            transform: translateY(-2px);
        }

        .btn-decline {
            background: #f0f3f2;
            color: #8a8a8a;
            border: none;
            padding: 10px 24px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-decline:hover {
            background: #e74c3c;
            color: white;
            transform: translateY(-2px);
        }

        /* Friends List Container - Horizontal Layout */
        .friends-list-container {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        /* Friend Row - Horizontal layout */
        .friend-row {
            display: flex;
            align-items: center;
            gap: 16px;
            background: white;
            padding: 16px 20px;
            border-radius: 20px;
            border: 1px solid #e8e8e8;
            transition: all 0.3s;
            cursor: pointer;
        }

        .friend-row:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border-color: #f39c12;
        }

        /* Friend Avatar - List style */
        .friend-avatar-list {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #f39c12;
            flex-shrink: 0;
        }

        /* Friend Details */
        .friend-details {
            flex: 1;
        }

        .friend-name-list {
            font-size: 16px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 4px;
        }

        .friend-username-list {
            font-size: 12px;
            color: #8a8a8a;
            margin-bottom: 4px;
        }

        .friend-mutual-list {
            font-size: 11px;
            color: #f39c12;
        }

        .friend-mutual-list i {
            margin-right: 4px;
        }

        /* Friend Actions */
        .friend-actions-list {
            display: flex;
            gap: 10px;
            flex-shrink: 0;
        }

        .friend-action-btn {
            padding: 8px 16px;
            border-radius: 30px;
            font-weight: 500;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
        }

        .friend-action-btn.message {
            background: #fef9f0;
            color: #f39c12;
        }

        .friend-action-btn.message:hover {
            background: #f39c12;
            color: white;
            transform: translateY(-2px);
        }

        .friend-action-btn.unfriend {
            background: #fee;
            color: #e74c3c;
        }

        .friend-action-btn.unfriend:hover {
            background: #e74c3c;
            color: white;
            transform: translateY(-2px);
        }

        /* Search Bar */
        .search-bar {
            background: white;
            padding: 14px 24px;
            margin-bottom: 28px;
            display: flex;
            align-items: center;
            gap: 14px;
            border: 1px solid rgba(0, 0, 0, 0.04);
            border-radius: 60px;
            transition: all 0.3s;
        }

        .search-bar:focus-within {
            box-shadow: 0 4px 16px rgba(243, 156, 18, 0.15);
            border-color: #f39c12;
        }

        .search-bar i {
            color: #f39c12;
            font-size: 18px;
        }

        .search-bar input {
            flex: 1;
            border: none;
            outline: none;
            font-size: 15px;
            background: transparent;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        /* Empty States */
        .empty-state {
            background: white;
            text-align: center;
            padding: 70px 20px;
            border: 2px dashed #e8ecef;
            border-radius: 32px;
        }

        .empty-icon {
            font-size: 72px;
            margin-bottom: 20px;
            display: inline-block;
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .empty-title {
            font-size: 22px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 8px;
        }

        .empty-text {
            color: #7f8c8d;
            margin-bottom: 24px;
        }

        /* Toast */
        .toast {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            background: #2c3e50;
            color: white;
            padding: 14px 28px;
            border-radius: 60px;
            font-size: 14px;
            z-index: 1000;
            display: none;
            white-space: nowrap;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
        }

        @media (max-width: 900px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .request-card {
                flex-direction: column;
                text-align: center;
            }
            
            .request-info {
                flex-direction: column;
                margin-bottom: 20px;
            }
            
            .friends-grid {
                grid-template-columns: 1fr;
            }

            .toast {
                white-space: normal;
                text-align: center;
                max-width: 90%;
            }
        }

        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
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
            <a href="profile.php" class="nav-link">Profile</a>
            <a href="friends.php" class="nav-link active">Travelers</a>
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

    <div class="friends-container">
        <!-- Header -->
        <div class="friends-header">
            <h1><i class="fas fa-globe-asia"></i> Travel Circle</h1>
            <p>Your adventure community — connect, share, explore together</p>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card" onclick="switchTab('all')">
                <div class="stat-icon companions">
                    <i class="fas fa-user-friends"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $friends_count; ?></h3>
                    <p>Travel Buddies</p>
                </div>
            </div>
            <div class="stat-card" onclick="switchTab('requests')">
                <div class="stat-icon requests">
                    <i class="fas fa-handshake"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $friend_requests_count; ?></h3>
                    <p>Pending Invites</p>
                </div>
            </div>
            <div class="stat-card" onclick="switchTab('sent')">
                <div class="stat-icon sent">
                    <i class="fas fa-paper-plane"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo mysqli_num_rows($sent_requests); ?></h3>
                    <p>Sent Invites</p>
                </div>
            </div>
            <div class="stat-card" onclick="switchTab('birthdays')">
                <div class="stat-icon birthdays">
                    <i class="fas fa-birthday-cake"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $birthdays_count; ?></h3>
                    <p>Celebrations</p>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="friends-tabs">
            <div class="tabs-container">
                <button class="tab-btn active" onclick="switchTab('all')">
                    All Buddies <span class="tab-badge"><?php echo $friends_count; ?></span>
                </button>
                <button class="tab-btn" onclick="switchTab('requests')">
                    Requests 
                    <?php if ($friend_requests_count > 0): ?>
                        <span class="tab-badge" style="background:#e74c3c; color:white;"><?php echo $friend_requests_count; ?></span>
                    <?php else: ?>
                        <span class="tab-badge"><?php echo $friend_requests_count; ?></span>
                    <?php endif; ?>
                </button>
                <button class="tab-btn" onclick="switchTab('sent')">
                    Sent
                </button>
                <button class="tab-btn" onclick="switchTab('suggestions')">
                    Discover
                </button>
                <button class="tab-btn" onclick="switchTab('birthdays')">
                    Celebrations
                    <?php if ($birthdays_count > 0): ?>
                        <span class="tab-badge" style="background:#1abc9c; color:white;"><?php echo $birthdays_count; ?></span>
                    <?php endif; ?>
                </button>
            </div>
        </div>

     <!-- All Friends Panel -->
    <div id="allFriendsPanel" class="tab-panel active">
        <div class="search-bar">
            <i class="fas fa-search"></i>
            <input type="text" id="searchFriends" placeholder="Find your travel buddy..." onkeyup="searchFriends()">
        </div>
        <div id="friendsList" class="friends-list-container">
            <?php if ($friends_count == 0): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-compass"></i>
                    </div>
                    <div class="empty-title">No travel buddies yet</div>
                    <div class="empty-text">Your adventure circle is waiting to grow!</div>
                    <button class="btn-accept" onclick="switchTab('suggestions')">Discover Travelers</button>
                </div>
            <?php else: ?>
                <?php while ($friend = mysqli_fetch_assoc($friends)): ?>
                    <div class="friend-row" data-name="<?php echo strtolower($friend['full_name'] . ' ' . $friend['username']); ?>" onclick="viewProfile(<?php echo $friend['id']; ?>)">
                        <?php
                        $friend_pic = $friend['profile_pic'];
                        if (!empty($friend_pic) && $friend_pic != 'default.jpg' && file_exists('uploads/profiles/' . $friend_pic)) {
                            $friend_avatar_url = 'uploads/profiles/' . $friend_pic;
                        } else {
                            $name = urlencode($friend['full_name']);
                            $friend_avatar_url = "https://ui-avatars.com/api/?background=f39c12&color=fff&rounded=true&size=48&bold=true&name=$name";
                        }
                        ?>
                        <img src="<?php echo $friend_avatar_url; ?>" class="friend-avatar-list" alt="Avatar">
                        <div class="friend-details">
                            <div class="friend-name-list"><?php echo htmlspecialchars($friend['full_name']); ?></div>
                            <div class="friend-username-list">@<?php echo htmlspecialchars($friend['username']); ?></div>
                            <?php if ($friend['mutual_count'] > 0): ?>
                                <div class="friend-mutual-list">
                                    <i class="fas fa-user-friends"></i> <?php echo $friend['mutual_count']; ?> mutual friends
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="friend-actions-list">
                            <button class="friend-action-btn message" onclick="event.stopPropagation(); messageFriend(<?php echo $friend['id']; ?>)">
                                <i class="fas fa-comment"></i> Message
                            </button>
                            <button class="friend-action-btn unfriend" onclick="event.stopPropagation(); unfriend(<?php echo $friend['id']; ?>, '<?php echo addslashes($friend['full_name']); ?>')">
                                <i class="fas fa-user"></i> Remove
                            </button>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </div>

        <!-- Friend Requests Panel -->
        <div id="requestsPanel" class="tab-panel">
            <div id="requestsList">
                <?php if ($friend_requests_count == 0): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-bell-slash"></i>
                        </div>
                        <div class="empty-title">No pending invites</div>
                        <div class="empty-text">When someone wants to connect, you'll see it here.</div>
                    </div>
                <?php else: ?>
                    <?php while ($request = mysqli_fetch_assoc($friend_requests)): ?>
                        <div class="request-card" id="request_<?php echo $request['id']; ?>">
                            <div class="request-info">
                                <?php
                                $req_pic = $request['profile_pic'];
                                if (!empty($req_pic) && $req_pic != 'default.jpg' && file_exists('uploads/profiles/' . $req_pic)) {
                                    $req_avatar_url = 'uploads/profiles/' . $req_pic;
                                } else {
                                    $name = urlencode($request['full_name']);
                                    $req_avatar_url = "https://ui-avatars.com/api/?background=f39c12&color=fff&rounded=true&size=72&bold=true&name=$name";
                                }
                                ?>
                                <img src="<?php echo $req_avatar_url; ?>" class="request-avatar" 
                                     onclick="viewProfile(<?php echo $request['id']; ?>)">
                                <div class="request-details">
                                    <h3 onclick="viewProfile(<?php echo $request['id']; ?>)">
                                        <?php echo htmlspecialchars($request['full_name']); ?>
                                    </h3>
                                    <div class="mutual-friends">
                                        <i class="fas fa-user-friends"></i> 
                                        <?php echo $request['mutual_count']; ?> mutual connections
                                    </div>
                                    <div style="font-size: 12px; color: #7f8c8d; margin-top: 6px;">
                                        <i class="far fa-clock"></i> Requested <?php echo date('M j, Y', strtotime($request['request_date'])); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="request-actions">
                                <button class="btn-accept" onclick="handleFriendRequest(<?php echo $request['id']; ?>, 'accept')">
                                    <i class="fas fa-check"></i> Accept
                                </button>
                                <button class="btn-decline" onclick="handleFriendRequest(<?php echo $request['id']; ?>, 'decline')">
                                    <i class="fas fa-times"></i> Decline
                                </button>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sent Requests Panel -->
        <div id="sentPanel" class="tab-panel">
            <div id="sentList">
                <?php if (mysqli_num_rows($sent_requests) == 0): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-paper-plane"></i>
                        </div>
                        <div class="empty-title">No outgoing invites</div>
                        <div class="empty-text">Invites you send will appear here.</div>
                    </div>
                <?php else: ?>
                    <?php while ($sent = mysqli_fetch_assoc($sent_requests)): ?>
                        <div class="request-card" id="sent_<?php echo $sent['id']; ?>">
                            <div class="request-info">
                                <?php
                                $sent_pic = $sent['profile_pic'];
                                if (!empty($sent_pic) && $sent_pic != 'default.jpg' && file_exists('uploads/profiles/' . $sent_pic)) {
                                    $sent_avatar_url = 'uploads/profiles/' . $sent_pic;
                                } else {
                                    $name = urlencode($sent['full_name']);
                                    $sent_avatar_url = "https://ui-avatars.com/api/?background=f39c12&color=fff&rounded=true&size=72&bold=true&name=$name";
                                }
                                ?>
                                <img src="<?php echo $sent_avatar_url; ?>" class="request-avatar" 
                                     onclick="viewProfile(<?php echo $sent['id']; ?>)">
                                <div class="request-details">
                                    <h3 onclick="viewProfile(<?php echo $sent['id']; ?>)">
                                        <?php echo htmlspecialchars($sent['full_name']); ?>
                                    </h3>
                                    <div style="font-size: 12px; color: #7f8c8d; margin-top: 6px;">
                                        <i class="far fa-paper-plane"></i> Sent <?php echo date('M j, Y', strtotime($sent['request_date'])); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="request-actions">
                                <button class="btn-decline" onclick="cancelRequest(<?php echo $sent['id']; ?>, '<?php echo addslashes($sent['full_name']); ?>')">
                                    <i class="fas fa-times"></i> Cancel Invite
                                </button>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Suggestions Panel -->
        <div id="suggestionsPanel" class="tab-panel">
            <div id="suggestionsList" class="friends-grid">
                <?php if (mysqli_num_rows($suggestions) == 0): ?>
                    <div class="empty-state" style="grid-column: 1/-1;">
                        <div class="empty-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="empty-title">No suggestions right now</div>
                        <div class="empty-text">Check back later for more travel buddies!</div>
                    </div>
                <?php else: ?>
                    <?php while ($suggestion = mysqli_fetch_assoc($suggestions)): ?>
                        <div class="friend-card">
                            <div class="friend-cover"></div>
                            <?php
                            $sug_pic = $suggestion['profile_pic'];
                            if (!empty($sug_pic) && $sug_pic != 'default.jpg' && file_exists('uploads/profiles/' . $sug_pic)) {
                                $sug_avatar_url = 'uploads/profiles/' . $sug_pic;
                            } else {
                                $name = urlencode($suggestion['full_name']);
                                $sug_avatar_url = "https://ui-avatars.com/api/?background=f39c12&color=fff&rounded=true&size=96&bold=true&name=$name";
                            }
                            ?>
                            <img src="<?php echo $sug_avatar_url; ?>" class="friend-avatar-large" 
                                 onclick="viewProfile(<?php echo $suggestion['id']; ?>)">
                            <div class="friend-info">
                                <div class="friend-name" onclick="viewProfile(<?php echo $suggestion['id']; ?>)">
                                    <?php echo htmlspecialchars($suggestion['full_name']); ?>
                                </div>
                                <div class="friend-username">@<?php echo htmlspecialchars($suggestion['username']); ?></div>
                                <?php if ($suggestion['mutual_count'] > 0): ?>
                                    <div class="friend-mutual">
                                        <i class="fas fa-user-friends"></i> <?php echo $suggestion['mutual_count']; ?> mutual friends
                                    </div>
                                <?php endif; ?>
                                <div class="friend-actions">
                                    <button class="friend-action-btn message" style="background:#f39c12; color:white;" 
                                            onclick="sendFriendRequest(<?php echo $suggestion['id']; ?>, '<?php echo addslashes($suggestion['full_name']); ?>')">
                                        <i class="fas fa-user-plus"></i> Connect
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Birthdays Panel -->
        <div id="birthdaysPanel" class="tab-panel">
            <div id="birthdaysList">
                <?php if ($birthdays_count == 0): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-birthday-cake"></i>
                        </div>
                        <div class="empty-title">No celebrations this month</div>
                        <div class="empty-text">Check back next month for birthday wishes!</div>
                    </div>
                <?php else: ?>
                    <div class="friends-grid">
                        <?php while ($birthday = mysqli_fetch_assoc($birthdays)): ?>
                            <div class="friend-card">
                                <div class="friend-cover" style="background: linear-gradient(135deg, #f1c40f, #f39c12);"></div>
                                <?php
                                $birth_pic = $birthday['profile_pic'];
                                if (!empty($birth_pic) && $birth_pic != 'default.jpg' && file_exists('uploads/profiles/' . $birth_pic)) {
                                    $birth_avatar_url = 'uploads/profiles/' . $birth_pic;
                                } else {
                                    $name = urlencode($birthday['full_name']);
                                    $birth_avatar_url = "https://ui-avatars.com/api/?background=f39c12&color=fff&rounded=true&size=96&bold=true&name=$name";
                                }
                                ?>
                                <img src="<?php echo $birth_avatar_url; ?>" class="friend-avatar-large" 
                                     onclick="viewProfile(<?php echo $birthday['id']; ?>)">
                                <div class="friend-info">
                                    <div class="friend-name" onclick="viewProfile(<?php echo $birthday['id']; ?>)">
                                        <?php echo htmlspecialchars($birthday['full_name']); ?>
                                    </div>
                                    <div class="friend-username">@<?php echo htmlspecialchars($birthday['username']); ?></div>
                                    <div class="friend-mutual" style="background:#fef9e8; color:#f1c40f;">
                                        <i class="fas fa-birthday-cake"></i> 
                                        <?php echo date('F j', strtotime($birthday['birthdate'])); ?>
                                    </div>
                                    <div class="friend-actions">
                                        <button class="friend-action-btn message" style="background:#f1c40f; color:#2c3e50;" onclick="messageFriend(<?php echo $birthday['id']; ?>)">
                                            <i class="fas fa-gift"></i> Wish Happy Birthday
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="toast"></div>

    <script>
        // Tab Switching
        function switchTab(tabName) {
            let clickedBtn = event.target.closest('.tab-btn');
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            clickedBtn.classList.add('active');
            
            document.querySelectorAll('.tab-panel').forEach(panel => panel.classList.remove('active'));
            
            if (tabName === 'all') {
                document.getElementById('allFriendsPanel').classList.add('active');
            } else if (tabName === 'requests') {
                document.getElementById('requestsPanel').classList.add('active');
            } else if (tabName === 'sent') {
                document.getElementById('sentPanel').classList.add('active');
            } else if (tabName === 'suggestions') {
                document.getElementById('suggestionsPanel').classList.add('active');
            } else if (tabName === 'birthdays') {
                document.getElementById('birthdaysPanel').classList.add('active');
            }
        }
        
        // Handle Friend Request
        function handleFriendRequest(friendId, action) {
            const formData = new FormData();
            formData.append('action', action === 'accept' ? 'accept_request' : 'decline_request');
            formData.append('friend_id', friendId);
            
            fetch('friends.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const requestDiv = document.getElementById(`request_${friendId}`);
                    if (requestDiv) {
                        requestDiv.style.animation = 'fadeOut 0.3s ease';
                        setTimeout(() => {
                            requestDiv.remove();
                            showToast(action === 'accept' ? 'Connection accepted!' : 'Request declined');
                            setTimeout(() => location.reload(), 1000);
                        }, 300);
                    }
                }
            });
        }
        
        // Send Friend Request
        function sendFriendRequest(friendId, friendName) {
            const formData = new FormData();
            formData.append('action', 'send_request');
            formData.append('friend_id', friendId);
            
            fetch('friends.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(`Invite sent to ${friendName}!`);
                    setTimeout(() => location.reload(), 1000);
                }
            });
        }
        
        // Cancel Request
        function cancelRequest(friendId, friendName) {
            if (confirm(`Cancel invite to ${friendName}?`)) {
                const formData = new FormData();
                formData.append('action', 'cancel_request');
                formData.append('friend_id', friendId);
                
                fetch('friends.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const sentDiv = document.getElementById(`sent_${friendId}`);
                        if (sentDiv) {
                            sentDiv.remove();
                            showToast('Invite cancelled');
                            setTimeout(() => location.reload(), 1000);
                        }
                    }
                });
            }
        }
        
        // Unfriend
        function unfriend(friendId, friendName) {
            if (confirm(`Remove ${friendName} from your travel circle?`)) {
                const formData = new FormData();
                formData.append('action', 'unfriend');
                formData.append('friend_id', friendId);
                
                fetch('friends.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast(`${friendName} removed`);
                        setTimeout(() => location.reload(), 1000);
                    }
                });
            }
        }
        
        // View Profile
        function viewProfile(userId) {
            window.location.href = `profile.php?id=${userId}`;
        }
        
        // Message Friend
        function messageFriend(friendId) {
            showToast('Messaging feature coming soon!');
        }
        
        // Search Friends
        function searchFriends() {
            const searchTerm = document.getElementById('searchFriends').value.toLowerCase();
            const friendCards = document.querySelectorAll('#friendsList .friend-card');
            
            friendCards.forEach(card => {
                const name = card.getAttribute('data-name') || '';
                if (name.includes(searchTerm)) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        }
        
        // Show Toast
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