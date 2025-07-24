<?php
session_start();

if (!isset($_SESSION['name']) || !isset($_SESSION['email']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin page</title>
</head>
<body>
    <h1>admin</h1>
    <a href="index.php">logout</a>
</body>
</html>
