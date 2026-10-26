
<?php
session_start();

require_once __DIR__ . "/db.php";

// Check login
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// Check 15-minute inactivity timeout
if (
    !isset($_SESSION["last_activity"]) ||
    (time() - $_SESSION["last_activity"]) > 900
) {
    $_SESSION = [];
    session_destroy();

    header("Location: login.php?timeout=1");
    exit;
}

// Only admin can delete students
if (($_SESSION["role"] ?? "") !== "admin") {
    http_response_code(403);
    exit("Access denied. Admin permission required.");
}

$_SESSION["last_activity"] = time();

// Only POST requests are allowed
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: students.php");
    exit;
}

// Validate CSRF token
$csrfToken = $_POST["csrf_token"] ?? "";

if (
    !is_string($csrfToken) ||
    !isset($_SESSION["csrf_token"]) ||
    !hash_equals($_SESSION["csrf_token"], $csrfToken)
) {
    header("Location: students.php?delete_error=invalid");
    exit;
}

// Validate student ID
$studentId = filter_var(
    $_POST["student_id"] ?? "",
    FILTER_VALIDATE_INT
);

if ($studentId === false || $studentId === null || $studentId < 1) {
    header("Location: students.php?delete_error=invalid");
    exit;
}

try {
    $stmt = $conn->prepare(
        "DELETE FROM students WHERE student_id = ?"
    );

    $stmt->bind_param("i", $studentId);
    $stmt->execute();

    $deleted = $stmt->affected_rows;
    $stmt->close();

    if ($deleted === 1) {
        header("Location: students.php?deleted=1");
    } else {
        header("Location: students.php?delete_error=notfound");
    }

    exit;

} catch (Throwable $e) {
    error_log("Student delete error: " . $e->getMessage());

    header("Location: students.php?delete_error=failed");
    exit;
}
?>
