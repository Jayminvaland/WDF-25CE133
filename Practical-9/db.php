<?php
// Database configuration
$host = "localhost";
$username = "root";
$password = "";
$database = "studenthub_db";
$port = 3306;

// Enable MySQLi error reporting
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Create database connection
    $conn = new mysqli(
        $host,
        $username,
        $password,
        $database,
        $port
    );

    // Set character encoding
    $conn->set_charset("utf8mb4");

} catch (mysqli_sql_exception $e) {
    error_log($e->getMessage());

    http_response_code(500);
    exit("Database connection failed. Please check XAMPP settings.");
}
?>