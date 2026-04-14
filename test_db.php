<?php
include 'config.php';

// Check if connection works
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Show current database name
$result = $conn->query("SELECT DATABASE()");
$row = $result->fetch_row();
echo "Connected to database: " . $row[0] . "<br>";

// List all tables
$result = $conn->query("SHOW TABLES");
echo "Tables in this database:<br>";
while ($row = $result->fetch_row()) {
    echo "- " . $row[0] . "<br>";
}

// Check if users table exists
$check = $conn->query("SELECT 1 FROM users LIMIT 1");
if ($check === false) {
    echo "Error: " . $conn->error;
} else {
    echo "users table exists and is accessible.";
}
?>