<?php
session_start();
include 'config.php';

header('Content-Type: application/json');

$post_id = mysqli_real_escape_string($conn, $_GET['post_id']);

$query = "SELECT c.*, u.username, u.full_name, u.profile_pic 
          FROM comments c
          JOIN users u ON c.user_id = u.id
          WHERE c.post_id = $post_id AND c.is_deleted = FALSE
          ORDER BY c.created_at ASC";
$result = mysqli_query($conn, $query);

$comments = [];
while ($row = mysqli_fetch_assoc($result)) {
    $comments[] = $row;
}

echo json_encode($comments);
?>