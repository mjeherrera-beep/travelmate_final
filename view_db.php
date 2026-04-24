<?php
$host = getenv('DB_HOST');
$port = (int)getenv('DB_PORT');
$user = getenv('DB_USER');
$pass = getenv('DB_PASSWORD');
$name = getenv('DB_NAME');

$conn = mysqli_init();
mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL);
mysqli_real_connect($conn, $host, $user, $pass, $name, $port, NULL, MYSQLI_CLIENT_SSL);

$tables = $conn->query("SHOW TABLES");
while($table = $tables->fetch_array()) {
    $tableName = $table[0];
    echo "<h2>$tableName</h2>";
    $result = $conn->query("SELECT * FROM `$tableName`");
    echo "<table border='1'><tr>";
    $fields = $result->fetch_fields();
    foreach($fields as $field) {
        echo "<th>{$field->name}</th>";
    }
    echo "</tr>";
    while($row = $result->fetch_assoc()) {
        echo "<tr>";
        foreach($row as $val) {
            echo "<td>$val</td>";
        }
        echo "</tr>";
    }
    echo "</table><br>";
}
?>
