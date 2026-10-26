
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

// Only admin can delete events
if (($_SESSION["role"] ?? "") !== "admin") {
    http_response_code(403);
    exit("Access denied. Admin permission required.");
}

$_SESSION["last_activity"] = time();

// Accept POST requests only
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: events.php");
    exit;
}

// Verify CSRF token
$csrfToken = $_POST["csrf_token"] ?? "";

if (
    !is_string($csrfToken) ||
    !isset($_SESSION["csrf_token"]) ||
    !hash_equals($_SESSION["csrf_token"], $csrfToken)
) {
    header("Location: events.php?error=invalid");
    exit;
}

// Validate event ID
$eventId = filter_var(
    $_POST["event_id"] ?? "",
    FILTER_VALIDATE_INT
);

if ($eventId === false || $eventId === null || $eventId < 1) {
    header("Location: events.php?error=invalid");
    exit;
}

try {
    // Fetch the poster path before deleting the event
    $find = $conn->prepare(
        "SELECT poster_path
         FROM events
         WHERE event_id = ?
         LIMIT 1"
    );

    $find->bind_param("i", $eventId);
    $find->execute();

    $event = $find->get_result()->fetch_assoc();
    $find->close();

    if (!$event) {
        header("Location: events.php?error=notfound");
        exit;
    }

    $posterPath = $event["poster_path"] ?? "";

    // Delete the event record using a prepared statement
    $delete = $conn->prepare(
        "DELETE FROM events
         WHERE event_id = ?"
    );

    $delete->bind_param("i", $eventId);
    $delete->execute();

    $deleted = $delete->affected_rows;
    $delete->close();

    if ($deleted !== 1) {
        header("Location: events.php?error=delete");
        exit;
    }

    // Delete only a poster located inside uploads/posters
    if (
        $posterPath !== "" &&
        strpos($posterPath, "uploads/posters/") === 0
    ) {
        $uploadDirectory = realpath(
            __DIR__ . "/uploads/posters"
        );

        $filename = basename($posterPath);

        if ($uploadDirectory !== false && $filename !== "") {
            $posterFile = realpath(
                $uploadDirectory . DIRECTORY_SEPARATOR . $filename
            );

            if (
                $posterFile !== false &&
                dirname($posterFile) === $uploadDirectory &&
                is_file($posterFile)
            ) {
                if (!@unlink($posterFile)) {
                    error_log(
                        "Could not remove event poster: " . $posterFile
                    );
                }
            }
        }
    }

    header("Location: events.php?deleted=1");
    exit;

} catch (Throwable $e) {
    error_log("Delete event error: " . $e->getMessage());

    header("Location: events.php?error=delete");
    exit;
}
?>
