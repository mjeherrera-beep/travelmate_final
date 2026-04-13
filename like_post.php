<?php
session_start();
include 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];
$post_id = mysqli_real_escape_string($conn, $_GET['post_id']);

$check = "SELECT id FROM likes WHERE post_id = $post_id AND user_id = $user_id";
$result = mysqli_query($conn, $check);

if (mysqli_num_rows($result) > 0) {
    mysqli_query($conn, "DELETE FROM likes WHERE post_id = $post_id AND user_id = $user_id");
    $liked = false;
} else {
    mysqli_query($conn, "INSERT INTO likes (post_id, user_id) VALUES ($post_id, $user_id)");
    $liked = true;
}

$count_result = mysqli_query($conn, "SELECT COUNT(*) as count FROM likes WHERE post_id = $post_id");
$count = mysqli_fetch_assoc($count_result);

echo json_encode(['likes' => $count['count'], 'liked' => $liked]);
?>