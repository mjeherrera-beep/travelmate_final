<?php
session_start();
include 'config.php';

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    mysqli_query($conn, "DELETE FROM user_sessions WHERE user_id = $user_id");
}

setcookie('remember_token', '', time() - 3600, "/");
session_destroy();
header("Location: index.php");
exit();
?>