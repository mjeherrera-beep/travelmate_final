<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo 'error';
    exit();
}

$user_id = $_SESSION['user_id'];
$post_id = mysqli_real_escape_string($conn, $_POST['post_id']);
$comment = mysqli_real_escape_string($conn, $_POST['comment']);

$query = "INSERT INTO comments (post_id, user_id, comment_text) 
          VALUES ($post_id, $user_id, '$comment')";
mysqli_query($conn, $query);
echo 'success';
?>