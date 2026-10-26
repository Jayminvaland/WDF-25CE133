<?php
// Database configuration
$host = "127.0.0.1";
$port = 3306;
$dbname = "studenthub_db";
$username = "root";
$password = "";

// PDO connection
try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

    $pdo = new PDO($dsn, $username, $password);

    // Enable exceptions for database errors
    $pdo->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

    // Return results as associative arrays
    $pdo->setAttribute(
        PDO::ATTR_DEFAULT_FETCH_MODE,
        PDO::FETCH_ASSOC
    );

    // Use real prepared statements where supported
    $pdo->setAttribute(
        PDO::ATTR_EMULATE_PREPARES,
        false
    );

} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>