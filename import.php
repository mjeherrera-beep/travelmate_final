<?php
$host = getenv('DB_HOST');
$port = (int)getenv('DB_PORT');
$user = getenv('DB_USER');
$pass = getenv('DB_PASSWORD');
$name = getenv('DB_NAME');

$conn = mysqli_init();
mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL);
mysqli_real_connect($conn, $host, $user, $pass, $name, $port, NULL, MYSQLI_CLIENT_SSL);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

echo "Connected!<br>";

$result = $conn->query("SHOW TABLES");
if ($result) {
    $tables = [];
    while($row = $result->fetch_array()) {
        $tables[] = $row[0];
    }
    if (empty($tables)) {
        echo "No tables found!";
    } else {
        echo "Tables: " . implode(", ", $tables);
    }
} else {
    echo "Error: " . $conn->error;
}
?>
