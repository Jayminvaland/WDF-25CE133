
<?php
session_start();

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/audit.php";

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

// Only admin can edit events
if (($_SESSION["role"] ?? "") !== "admin") {
    http_response_code(403);
    exit("Access denied. Admin permission required.");
}

$_SESSION["last_activity"] = time();

// Validate event ID
$eventId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$eventId || $eventId < 1) {
    http_response_code(400);
    exit("Invalid event ID.");
}

// Generate CSRF token
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

function escapeHtml($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ""),
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8"
    );
}

// Safely delete a poster stored in uploads/posters
function removePoster(?string $relativePath): void
{
    if (
        !$relativePath ||
        strpos($relativePath, "uploads/posters/") !== 0
    ) {
        return;
    }

    $filename = basename($relativePath);

    if ($filename === "" || $filename === "." || $filename === "..") {
        return;
    }

    $fullPath = __DIR__
        . DIRECTORY_SEPARATOR . "uploads"
        . DIRECTORY_SEPARATOR . "posters"
        . DIRECTORY_SEPARATOR . $filename;

    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}

// Load existing event
$find = $conn->prepare(
    "SELECT event_id, title, description, event_date,
            venue, poster_path
     FROM events
     WHERE event_id = ?
     LIMIT 1"
);

$find->bind_param("i", $eventId);
$find->execute();

$event = $find->get_result()->fetch_assoc();
$find->close();

if (!$event) {
    http_response_code(404);
    exit("Event not found.");
}

// Preserve original poster unless a new one is uploaded
$oldPosterPath = $event["poster_path"] ?? null;
$posterPath = $oldPosterPath;

$title = $event["title"];
$description = $event["description"] ?? "";
$eventDate = $event["event_date"];
$venue = $event["venue"];
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $title = trim($_POST["title"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $eventDate = trim($_POST["event_date"] ?? "");
    $venue = trim($_POST["venue"] ?? "");
    $csrfToken = $_POST["csrf_token"] ?? "";

    if (
        !is_string($csrfToken) ||
        !hash_equals($_SESSION["csrf_token"], $csrfToken)
    ) {
        $error = "Invalid form request. Please refresh the page.";
    } elseif (
        $title === "" ||
        $eventDate === "" ||
        $venue === ""
    ) {
        $error = "Please fill in the event title, date and venue.";
    } elseif (strlen($title) > 150 || strlen($venue) > 150) {
        $error = "Title and venue must not exceed 150 characters.";
    } elseif (strlen($description) > 10000) {
        $error = "Description is too long.";
    } elseif (
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate) ||
        !checkdate(
            (int) substr($eventDate, 5, 2),
            (int) substr($eventDate, 8, 2),
            (int) substr($eventDate, 0, 4)
        )
    ) {
        $error = "Please enter a valid event date.";
    } else {

        $savedPosterFile = null;
        $newPosterUploaded = false;
        $maxFileSize = 2 * 1024 * 1024;

        // A new poster is optional.
        if (
            isset($_FILES["poster"]) &&
            $_FILES["poster"]["error"] !== UPLOAD_ERR_NO_FILE
        ) {

            if ($_FILES["poster"]["error"] !== UPLOAD_ERR_OK) {
                $error = "Poster upload failed. Please try again.";
            } elseif ($_FILES["poster"]["size"] > $maxFileSize) {
                $error = "Poster size must not exceed 2 MB.";
            } elseif (!is_uploaded_file($_FILES["poster"]["tmp_name"])) {
                $error = "Invalid uploaded file.";
            } else {

                $tmpPath = $_FILES["poster"]["tmp_name"];

                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->file($tmpPath);

                $allowedTypes = [
                    "image/jpeg" => "jpg",
                    "image/png"  => "png",
                    "image/webp" => "webp"
                ];

                $imageInfo = @getimagesize($tmpPath);

                if (
                    $imageInfo === false ||
                    !isset($allowedTypes[$mimeType]) ||
                    ($imageInfo["mime"] ?? "") !== $mimeType
                ) {
                    $error = "Only valid JPG, PNG or WEBP images are allowed.";
                } else {

                    $uploadDir = __DIR__
                        . DIRECTORY_SEPARATOR . "uploads"
                        . DIRECTORY_SEPARATOR . "posters"
                        . DIRECTORY_SEPARATOR;

                    if (
                        !is_dir($uploadDir) ||
                        !is_writable($uploadDir)
                    ) {
                        $error = "Poster folder is missing or not writable.";
                    } else {

                        $fileName = bin2hex(random_bytes(16))
                            . "." . $allowedTypes[$mimeType];

                        $destination = $uploadDir . $fileName;

                        if (!move_uploaded_file($tmpPath, $destination)) {
                            $error = "Unable to save the new poster.";
                        } else {
                            $posterPath = "uploads/posters/" . $fileName;
                            $savedPosterFile = $destination;
                            $newPosterUploaded = true;
                        }
                    }
                }
            }
        }

        if ($error === "") {
            try {

                $descriptionValue = $description === ""
                    ? null
                    : $description;

                $update = $conn->prepare(
                    "UPDATE events
                     SET title = ?,
                         description = ?,
                         event_date = ?,
                         venue = ?,
                         poster_path = ?
                     WHERE event_id = ?"
                );

                $update->bind_param(
                    "sssssi",
                    $title,
                    $descriptionValue,
                    $eventDate,
                    $venue,
                    $posterPath,
                    $eventId
                );

                $update->execute();
                $update->close();
                // Record event update in audit log
logAudit(
    $conn,
    "update_event",
    "event",
    (int) $eventId,
    "Updated event: " . $title
);

                // Remove the old poster only after the database update succeeds.
                if (
                    $newPosterUploaded &&
                    $oldPosterPath !== $posterPath
                ) {
                    removePoster($oldPosterPath);
                }

                $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

                header("Location: events.php?updated=1");
                exit;

            } catch (Throwable $e) {
                error_log("Edit event error: " . $e->getMessage());

                // Clean up the new file if saving the update fails.
                if (
                    $savedPosterFile !== null &&
                    is_file($savedPosterFile)
                ) {
                    @unlink($savedPosterFile);
                }

                $error = "Unable to update event. Please try again.";
                $posterPath = $oldPosterPath;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Event | StudentHub</title>

    <link rel="stylesheet" href="style.css">

    <style>
        .event-form {
            max-width: 750px;
        }

        .form-message {
            padding: 12px;
            margin: 15px 0;
            border-radius: 6px;
        }

        .error-message {
            background: #fee2e2;
            color: #991b1b;
        }

        .current-poster {
            display: block;
            width: 180px;
            max-height: 220px;
            object-fit: contain;
            margin: 10px 0;
            background: white;
            border-radius: 6px;
        }

        .event-form input[type="file"] {
            background: white;
            color: #1f2937;
        }
    </style>
</head>

<body>

<header>
    <h1>STUDENT PORTAL</h1>
</header>

<hr>

<nav>
    <a href="admin_dashboard.php">Admin Dashboard</a>
    <a href="events.php">Manage Events</a>
    <a href="logout.php">Logout</a>
</nav>

<hr>

<main class="container">

    <h2>Edit Event</h2>

    <div class="card event-form">

        <p>
            Event ID: <strong><?= (int) $eventId ?></strong>
        </p>

        <?php if ($error !== ""): ?>
            <div class="form-message error-message" role="alert">
                <?= escapeHtml($error) ?>
            </div>
        <?php endif; ?>

        <form
            action="event_edit.php?id=<?= (int) $eventId ?>"
            method="POST"
            enctype="multipart/form-data"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= escapeHtml($_SESSION["csrf_token"]) ?>"
            >

            <div class="form-group">
                <label for="title">Event Title *</label>

                <input
                    type="text"
                    id="title"
                    name="title"
                    maxlength="150"
                    value="<?= escapeHtml($title) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="description">Event Description</label>

                <textarea
                    id="description"
                    name="description"
                    rows="4"
                    maxlength="10000"
                ><?= escapeHtml($description) ?></textarea>
            </div>

            <div class="form-group">
                <label for="event_date">Event Date *</label>

                <input
                    type="date"
                    id="event_date"
                    name="event_date"
                    value="<?= escapeHtml($eventDate) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="venue">Venue *</label>

                <input
                    type="text"
                    id="venue"
                    name="venue"
                    maxlength="150"
                    value="<?= escapeHtml($venue) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label>Current Poster</label>

                <?php if (!empty($oldPosterPath)): ?>
                    <img
                        class="current-poster"
                        src="<?= escapeHtml($oldPosterPath) ?>"
                        alt="Current event poster"
                    >
                <?php else: ?>
                    <p>No poster uploaded.</p>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="poster">Replace Poster (Optional)</label>

                <input
                    type="file"
                    id="poster"
                    name="poster"
                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                >

                <p>
                    Allowed: JPG, PNG, WEBP. Maximum file size: 2 MB.
                    Leave empty to keep the current poster.
                </p>
            </div>

            <button type="submit">Update Event</button>

            <a href="events.php">Cancel</a>

        </form>

    </div>

</main>

</body>
</html>

<?php
$conn->close();
?>
