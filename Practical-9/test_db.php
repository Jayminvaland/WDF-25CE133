<?php
require_once "db.php";

echo "<h2>Database Connection Successful!</h2>";
echo "<p>MySQLi connection is working correctly.</p>";

// Close database connection
$conn->close();
?>