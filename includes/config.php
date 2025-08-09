<?php
// database connection for login_register.php
$servername = "localhost";
$username = "aenv";
$password = "springthief044";
$dbname = "users_db";

// create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
return $conn;
?>
