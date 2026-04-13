<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo 'error';
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_post'])) {
    $user_id = $_SESSION['user_id'];
    $content = mysqli_real_escape_string($conn, $_POST['content']);
    $location_name = mysqli_real_escape_string($conn, $_POST['location']);
    $latitude = mysqli_real_escape_string($conn, $_POST['latitude']);
    $longitude = mysqli_real_escape_string($conn, $_POST['longitude']);
    $privacy = mysqli_real_escape_string($conn, $_POST['privacy']);
    
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
    
    $query = "INSERT INTO posts (user_id, content, image_url, location_name, location_latitude, location_longitude, privacy) 
              VALUES ($user_id, '$content', '$image_url', '$location_name', '$latitude', '$longitude', '$privacy')";
    
    if (mysqli_query($conn, $query)) {
        echo 'success';
    } else {
        echo 'error';
    }
}
?>