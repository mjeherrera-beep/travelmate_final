<?php
// config.php – works on localhost AND Render (Aiven MySQL)

// Use environment variables if set (Render), otherwise fallback to localhost (XAMPP/WAMP)
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3306';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASSWORD') ?: '';  // default blank for localhost
$name = getenv('DB_NAME') ?: 'travelmate'; // your local database name

// Create connection
$conn = new mysqli($host, $user, $pass, $name, $port);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8mb4");

// Now use $conn for mysqli_query() etc.
?>