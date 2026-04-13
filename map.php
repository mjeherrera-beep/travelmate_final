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

$current_user_id = $_SESSION['user_id'];

// Get current user data
$user_query = "SELECT * FROM users WHERE id = $current_user_id";
$user_result = mysqli_query($conn, $user_query);
$current_user = mysqli_fetch_assoc($user_result);

// Get center coordinates from URL (optional)
$center_lat = isset($_GET['lat']) ? floatval($_GET['lat']) : 14.5995;
$center_lng = isset($_GET['lng']) ? floatval($_GET['lng']) : 120.9842;
$zoom = isset($_GET['zoom']) ? intval($_GET['zoom']) : 6;

// Get all posts with locations
$posts_query = "SELECT p.*, u.username, u.full_name, u.profile_pic,
               COUNT(DISTINCT l.id) as likes_count,
               EXISTS(SELECT 1 FROM likes WHERE post_id = p.id AND user_id = $current_user_id) as user_liked
               FROM posts p
               JOIN users u ON p.user_id = u.id
               LEFT JOIN likes l ON p.id = l.post_id
               WHERE (p.location_latitude IS NOT NULL AND p.location_longitude IS NOT NULL)
               AND (
                   p.privacy = 'public'
                   OR (p.privacy IN ('friends', 'friends_except') AND p.user_id IN (
                       SELECT following_id FROM friends_followers 
                       WHERE follower_id = $current_user_id AND status = 'accepted'
                       UNION
                       SELECT follower_id FROM friends_followers 
                       WHERE following_id = $current_user_id AND status = 'accepted'
                   ))
                   OR p.user_id = $current_user_id
               )
               GROUP BY p.id
               ORDER BY p.created_at DESC";
$posts_result = mysqli_query($conn, $posts_query);

// Get all posts as JSON for JavaScript
$posts = [];
while ($post = mysqli_fetch_assoc($posts_result)) {
    $posts[] = [
        'id' => $post['id'],
        'user_id' => $post['user_id'],
        'full_name' => $post['full_name'],
        'username' => $post['username'],
        'profile_pic' => $post['profile_pic'],
        'content' => $post['content'],
        'image_url' => $post['image_url'],
        'location_name' => $post['location_name'],
        'location_latitude' => $post['location_latitude'],
        'location_longitude' => $post['location_longitude'],
        'created_at' => $post['created_at'],
        'likes_count' => $post['likes_count'],
        'user_liked' => $post['user_liked']
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Travel Map - TravelMate</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
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
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
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

         /* Map Full Container */
        .map-full-container {
            flex: 1;
            position: relative;
            width: 100%;
            display: flex;
            flex-direction: column;
        }

        .map-wrapper {
            max-width: 1280px;
            width: 100%;
            margin: 0 auto;
            padding: 20px 24px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        #map {
            width: 100%;
            flex: 1;
            border-radius: 24px;
            z-index: 1;
            min-height: 500px;
        }

        /* Floating Stats Card - Same style as friends.php stats */
        .map-stats {
            position: absolute;
            bottom: 20px;
            left: 20px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 40px;
            padding: 12px 24px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            z-index: 10;
            display: flex;
            gap: 24px;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .map-stat {
            text-align: center;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .map-stat i {
            font-size: 20px;
        }

        .map-stat i.fa-map-pin { color: #f39c12; }
        .map-stat i.fa-users { color: #1abc9c; }

        .map-stat-number {
            font-size: 20px;
            font-weight: 700;
            color: #2c3e50;
        }

        .map-stat-label {
            font-size: 12px;
            color: #7f8c8d;
            margin-left: 4px;
        }

        /* Reset View Button */
        .reset-view-btn {
            position: absolute;
            bottom: 20px;
            right: 20px;
            background: white;
            border: none;
            border-radius: 40px;
            padding: 12px 20px;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 600;
            font-size: 14px;
            color: #2c3e50;
            z-index: 10;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .reset-view-btn i {
            color: #f39c12;
            font-size: 16px;
        }

        .reset-view-btn:hover {
            background: #f39c12;
            color: white;
            transform: translateY(-2px);
        }

        .reset-view-btn:hover i {
            color: white;
        }

        /* Custom Popup Styles */
        .custom-popup .leaflet-popup-content-wrapper {
            border-radius: 24px;
            padding: 0;
            overflow: hidden;
            box-shadow: 0 8px 28px rgba(0, 0, 0, 0.2);
        }

        .custom-popup .leaflet-popup-content {
            margin: 0;
            min-width: 300px;
            max-width: 340px;
        }

        .custom-popup .leaflet-popup-tip {
            background: white;
        }

        .popup-container {
            padding: 18px;
        }

        .popup-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }

        .popup-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            object-fit: cover;
            cursor: pointer;
            border: 2px solid #f39c12;
        }

        .popup-author {
            flex: 1;
        }

        .popup-name {
            font-weight: 700;
            font-size: 15px;
            color: #2c3e50;
            cursor: pointer;
        }

        .popup-name:hover {
            color: #f39c12;
        }

        .popup-time {
            font-size: 10px;
            color: #95a5a6;
            margin-top: 2px;
        }

        .popup-location {
            font-size: 11px;
            color: #f39c12;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
            background: #fef9f0;
            padding: 4px 10px;
            border-radius: 20px;
            display: inline-flex;
        }

        .popup-content-text {
            font-size: 13px;
            color: #5a6e7a;
            line-height: 1.5;
            margin-bottom: 12px;
            max-height: 80px;
            overflow-y: auto;
        }

        .popup-image {
            width: 100%;
            max-height: 160px;
            object-fit: cover;
            border-radius: 16px;
            margin-bottom: 12px;
            cursor: pointer;
        }

        .popup-stats {
            display: flex;
            gap: 16px;
            margin-bottom: 12px;
            font-size: 12px;
            color: #95a5a6;
        }

        .popup-stats i {
            margin-right: 5px;
        }

        .popup-stats i.fa-heart { color: #e74c3c; }

        .popup-button {
            width: 100%;
            padding: 10px;
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            color: white;
            border: none;
            border-radius: 30px;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .popup-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(243, 156, 18, 0.4);
        }

        /* Post Detail Modal - Same style as friends.php modals */
        .post-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(8px);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .post-modal-content {
            background: white;
            border-radius: 32px;
            width: 550px;
            max-width: 90%;
            max-height: 85vh;
            overflow-y: auto;
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from { transform: scale(0.9) translateY(30px); opacity: 0; }
            to { transform: scale(1) translateY(0); opacity: 1; }
        }

        .post-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            border-bottom: 1px solid #e8ecef;
        }

        .post-modal-header h3 {
            font-size: 20px;
            font-weight: 700;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .post-modal-header h3 i {
            color: #f39c12;
            font-size: 22px;
        }

        .close-post-modal {
            cursor: pointer;
            font-size: 28px;
            color: #95a5a6;
            transition: all 0.3s;
        }

        .close-post-modal:hover {
            color: #e74c3c;
        }

        .post-modal-body {
            padding: 24px;
        }

        .post-modal-author {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid #e8ecef;
        }

        .post-modal-avatar {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            object-fit: cover;
            cursor: pointer;
            border: 2px solid #f39c12;
        }

        .post-modal-name {
            font-weight: 700;
            font-size: 18px;
            color: #2c3e50;
        }

        .post-modal-time {
            font-size: 12px;
            color: #95a5a6;
            margin-top: 4px;
        }

        .post-modal-location {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #fef9f0;
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 12px;
            color: #f39c12;
            margin-bottom: 16px;
        }

        .post-modal-content-text {
            font-size: 15px;
            line-height: 1.6;
            color: #34495e;
            margin-bottom: 20px;
        }

        .post-modal-image {
            width: 100%;
            border-radius: 20px;
            margin-bottom: 20px;
            cursor: pointer;
        }

        .post-modal-stats {
            display: flex;
            gap: 24px;
            padding: 16px 0;
            border-top: 1px solid #e8ecef;
            border-bottom: 1px solid #e8ecef;
            margin-bottom: 16px;
        }

        .post-modal-stats span {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .post-modal-stats i.fa-heart {
            color: #e74c3c;
        }

        .post-modal-actions {
            display: flex;
            gap: 12px;
        }

        .post-modal-btn {
            flex: 1;
            padding: 12px;
            border-radius: 40px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .post-modal-btn.like {
            background: #fef9f0;
            color: #e74c3c;
        }

        .post-modal-btn.like:hover {
            background: #e74c3c;
            color: white;
        }

        .post-modal-btn.comment {
            background: #f0f3f2;
            color: #2c3e50;
        }

        .post-modal-btn.comment:hover {
            background: #f39c12;
            color: white;
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

        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            
            .map-stats {
                bottom: 70px;
                left: 10px;
                padding: 8px 16px;
                gap: 16px;
            }
            
            .map-stat i {
                font-size: 18px;
            }
            
            .map-stat-number {
                font-size: 18px;
            }
            
            .map-stat-label {
                font-size: 10px;
            }
            
            .reset-view-btn {
                bottom: 70px;
                right: 10px;
                padding: 8px 16px;
                font-size: 12px;
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
            <a href="map.php" class="nav-link active">Map</a>
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
    <div class="map-full-container">
        <div id="map"></div>

        <!-- Floating Stats -->
        <div class="map-stats">
            <div class="map-stat">
                <i class="fas fa-map-pin"></i>
                <span class="map-stat-number" id="pinCount">0</span>
                <span class="map-stat-label">Memories</span>
            </div>
            <div class="map-stat">
                <i class="fas fa-users"></i>
                <span class="map-stat-number" id="travelerCount">0</span>
                <span class="map-stat-label">Travelers</span>
            </div>
        </div>

        <!-- Reset View Button -->
        <button class="reset-view-btn" onclick="resetView()">
            <i class="fas fa-location-arrow"></i> Reset View
        </button>
    </div>

    <!-- Post Detail Modal -->
    <div id="postModal" class="post-modal">
        <div class="post-modal-content">
            <div class="post-modal-header">
                <h3><i class="fas fa-map-pin"></i> Travel Memory</h3>
                <span class="close-post-modal" onclick="closePostModal()">&times;</span>
            </div>
            <div class="post-modal-body" id="postModalBody">
                <!-- Dynamic content loaded here -->
            </div>
        </div>
    </div>

    <!-- Fullscreen Image Preview Modal -->
    <div id="fullscreenImageModal" class="image-preview-modal" onclick="closeFullscreenImage()">
        <span class="close-preview" onclick="closeFullscreenImage()">&times;</span>
        <img id="fullscreenImage" src="">
    </div>

    <script>
        // Post data from PHP
        const postsData = <?php echo json_encode($posts); ?>;
        
        // Initialize map
        let map;
        let markers = [];
        let currentPopup = null;

        // Default center (Philippines)
        const defaultCenter = [<?php echo $center_lat; ?>, <?php echo $center_lng; ?>];
        const defaultZoom = <?php echo $zoom; ?>;

        // Custom marker icon
        const customIcon = L.divIcon({
            html: `<div style="background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%); width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 3px solid white; box-shadow: 0 4px 12px rgba(0,0,0,0.2); transition: all 0.2s;">
                    <i class="fas fa-map-marker-alt" style="color: white; font-size: 18px;"></i>
                   </div>`,
            className: 'custom-marker',
            iconSize: [44, 44],
            iconAnchor: [22, 44],
            popupAnchor: [0, -44]
        });

        // Hover marker icon
        const hoverIcon = L.divIcon({
            html: `<div style="background: linear-gradient(135deg, #e67e22 0%, #d35400 100%); width: 52px; height: 52px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 3px solid white; box-shadow: 0 6px 16px rgba(0,0,0,0.25); transition: all 0.2s;">
                    <i class="fas fa-map-marker-alt" style="color: white; font-size: 22px;"></i>
                   </div>`,
            className: 'custom-marker-hover',
            iconSize: [52, 52],
            iconAnchor: [26, 52],
            popupAnchor: [0, -52]
        });

        function initMap() {
            // Create map
            map = L.map('map').setView(defaultCenter, defaultZoom);
            
            // Add tile layer
            L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/attributions">CARTO</a>',
                subdomains: 'abcd',
                maxZoom: 19,
                minZoom: 3
            }).addTo(map);
            
            // Add markers for each post
            const uniqueTravelers = new Set();
            
            postsData.forEach(post => {
                if (post.location_latitude && post.location_longitude) {
                    addMarker(post);
                    uniqueTravelers.add(post.user_id);
                }
            });
            
            // Update stats
            document.getElementById('pinCount').innerText = markers.length;
            document.getElementById('travelerCount').innerText = uniqueTravelers.size;
            
            // Fit bounds to show all markers if there are any
            if (markers.length > 0) {
                const group = L.featureGroup(markers);
                map.fitBounds(group.getBounds().pad(0.1));
            }
        }
        
        function addMarker(post) {
            // Handle profile picture for popup
            let postAvatarUrl = 'uploads/profiles/' + post.profile_pic;
            if (!post.profile_pic || post.profile_pic == 'default.jpg' || !fileExists(postAvatarUrl)) {
                postAvatarUrl = "https://ui-avatars.com/api/?background=f39c12&color=fff&rounded=true&size=44&bold=true&name=" + encodeURIComponent(post.full_name);
            }
            
            const marker = L.marker([post.location_latitude, post.location_longitude], { icon: customIcon })
                .addTo(map)
                .bindPopup(createPopupContent(post, postAvatarUrl), { 
                    maxWidth: 340, 
                    minWidth: 300,
                    className: 'custom-popup',
                    autoPan: true,
                    autoPanPadding: [50, 50]
                });
            
            // Hover effect
            marker.on('mouseover', function() {
                this.setIcon(hoverIcon);
            });
            
            marker.on('mouseout', function() {
                this.setIcon(customIcon);
            });
            
            markers.push(marker);
        }
        
        function fileExists(path) {
            // Simple check - in production you'd want a better method
            return true;
        }
        
        function createPopupContent(post, avatarUrl) {
            let imageHtml = '';
            if (post.image_url) {
                imageHtml = `<img src="${post.image_url}" class="popup-image" onclick="event.stopPropagation(); viewFullImage('${post.image_url}')">`;
            }
            
            // Truncate content
            const truncatedContent = post.content.length > 120 ? post.content.substring(0, 120) + '...' : post.content;
            
            return `
                <div class="popup-container">
                    <div class="popup-header">
                        <img src="${avatarUrl}" class="popup-avatar" onclick="event.stopPropagation(); viewProfile(${post.user_id})">
                        <div class="popup-author">
                            <div class="popup-name" onclick="event.stopPropagation(); viewProfile(${post.user_id})">${escapeHtml(post.full_name)}</div>
                            <div class="popup-time"><i class="far fa-calendar-alt"></i> ${formatDate(post.created_at)}</div>
                        </div>
                    </div>
                    <div class="popup-location">
                        <i class="fas fa-map-marker-alt"></i> ${escapeHtml(post.location_name || 'Unknown location')}
                    </div>
                    <div class="popup-content-text">${escapeHtml(truncatedContent)}</div>
                    ${imageHtml}
                    <div class="popup-stats">
                        <span><i class="fas fa-heart"></i> ${post.likes_count} likes</span>
                    </div>
                    <button class="popup-button" onclick="event.stopPropagation(); viewFullPost(${post.id})">
                        <i class="fas fa-book-open"></i> View Full Story
                    </button>
                </div>
            `;
        }
        
        function viewFullPost(postId) {
            const post = postsData.find(p => p.id == postId);
            if (!post) return;
            
            let postAvatarUrl = 'uploads/profiles/' + post.profile_pic;
            if (!post.profile_pic || post.profile_pic == 'default.jpg') {
                postAvatarUrl = "https://ui-avatars.com/api/?background=f39c12&color=fff&rounded=true&size=56&bold=true&name=" + encodeURIComponent(post.full_name);
            }
            
            const modalBody = document.getElementById('postModalBody');
            const imageHtml = post.image_url ? `<img src="${post.image_url}" class="post-modal-image" onclick="viewFullImage('${post.image_url}')">` : '';
            
            modalBody.innerHTML = `
                <div class="post-modal-author">
                    <img src="${postAvatarUrl}" class="post-modal-avatar" onclick="viewProfile(${post.user_id})">
                    <div>
                        <div class="post-modal-name" onclick="viewProfile(${post.user_id})">${escapeHtml(post.full_name)}</div>
                        <div class="post-modal-time"><i class="far fa-calendar-alt"></i> ${formatDate(post.created_at)}</div>
                    </div>
                </div>
                ${post.location_name ? `<div class="post-modal-location"><i class="fas fa-map-marker-alt"></i> ${escapeHtml(post.location_name)}</div>` : ''}
                <div class="post-modal-content-text">${escapeHtml(post.content)}</div>
                ${imageHtml}
                <div class="post-modal-stats">
                    <span><i class="fas fa-heart"></i> ${post.likes_count} likes</span>
                </div>
                <div class="post-modal-actions">
                    <button class="post-modal-btn like" onclick="toggleLikeFromModal(${post.id}, this)">
                        <i class="fas fa-thumbs-up"></i> Like
                    </button>
                    <button class="post-modal-btn comment" onclick="viewComments(${post.id})">
                        <i class="fas fa-comment"></i> Comment
                    </button>
                </div>
            `;
            
            document.getElementById('postModal').style.display = 'flex';
        }
        
        function closePostModal() {
            document.getElementById('postModal').style.display = 'none';
        }
        
        function viewFullImage(imageUrl) {
            const modal = document.getElementById('fullscreenImageModal');
            const img = document.getElementById('fullscreenImage');
            img.src = imageUrl;
            modal.style.display = 'flex';
        }

        function closeFullscreenImage() {
            document.getElementById('fullscreenImageModal').style.display = 'none';
        }
        
        function viewProfile(userId) {
            window.location.href = `profile.php?id=${userId}`;
        }
        
        function viewComments(postId) {
            closePostModal();
            window.location.href = `homepage.php?post=${postId}#comments`;
        }
        
        function toggleLikeFromModal(postId, btn) {
            fetch(`like_post.php?post_id=${postId}`)
                .then(response => response.json())
                .then(data => {
                    const statsDiv = btn.closest('.post-modal-actions').previousElementSibling;
                    if (statsDiv && statsDiv.classList.contains('post-modal-stats')) {
                        statsDiv.innerHTML = `<span><i class="fas fa-heart"></i> ${data.likes} likes</span>`;
                    }
                    
                    if (data.liked) {
                        btn.style.background = '#e74c3c';
                        btn.style.color = 'white';
                    } else {
                        btn.style.background = '#fef9f0';
                        btn.style.color = '#e74c3c';
                    }
                    
                    const post = postsData.find(p => p.id == postId);
                    if (post) {
                        post.likes_count = data.likes;
                        post.user_liked = data.liked;
                    }
                });
        }
        
        function resetView() {
            if (markers.length > 0) {
                const group = L.featureGroup(markers);
                map.fitBounds(group.getBounds().pad(0.1));
            } else {
                map.setView(defaultCenter, 6);
            }
        }
        
        function formatDate(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diff = Math.floor((now - date) / 1000 / 60 / 60 / 24);
            
            if (diff === 0) return 'Today';
            if (diff === 1) return 'Yesterday';
            if (diff < 7) return `${diff} days ago`;
            
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Close modal on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closePostModal();
                closeFullscreenImage();
            }
        });
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('postModal');
            if (event.target === modal) {
                closePostModal();
            }
            const imageModal = document.getElementById('fullscreenImageModal');
            if (event.target === imageModal) {
                closeFullscreenImage();
            }
        }
        
        // Initialize map when page loads
        document.addEventListener('DOMContentLoaded', initMap);
    </script>
</body>
</html>