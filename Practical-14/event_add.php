
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

// Only admin can add events
if (($_SESSION["role"] ?? "") !== "admin") {
    http_response_code(403);
    exit("Access denied. Admin permission required.");
}

$_SESSION["last_activity"] = time();

// Generate CSRF token
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Escape HTML output
function escapeHtml($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ""),
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8"
    );
}

$title = "";
$description = "";
$eventDate = "";
$venue = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $title = trim($_POST["title"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $eventDate = trim($_POST["event_date"] ?? "");
    $venue = trim($_POST["venue"] ?? "");
    $csrfToken = $_POST["csrf_token"] ?? "";

    // Verify CSRF token
    if (
        !is_string($csrfToken) ||
        !hash_equals($_SESSION["csrf_token"], $csrfToken)
    ) {
        $error = "Invalid form request. Please refresh the page.";
    }

    // Validate required fields
    elseif ($title === "" || $eventDate === "" || $venue === "") {
        $error = "Please fill in the event title, date and venue.";
    }

    elseif (strlen($title) > 150) {
        $error = "Event title must not exceed 150 characters.";
    }

    elseif (strlen($venue) > 150) {
        $error = "Venue must not exceed 150 characters.";
    }

    elseif (strlen($description) > 10000) {
        $error = "Description is too long.";
    }

    // Validate date format
    elseif (
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate) ||
        !checkdate(
            (int) substr($eventDate, 5, 2),
            (int) substr($eventDate, 8, 2),
            (int) substr($eventDate, 0, 4)
        )
    ) {
        $error = "Please enter a valid event date.";
    }

    else {

        $posterPath = null;
        $savedPosterFile = null;
        $maxFileSize = 2 * 1024 * 1024;

        // Poster is optional, but any uploaded file is validated.
        if (
            !isset($_FILES["poster"]) ||
            $_FILES["poster"]["error"] === UPLOAD_ERR_NO_FILE
        ) {
            // No poster uploaded.
        } elseif ($_FILES["poster"]["error"] !== UPLOAD_ERR_OK) {
            $error = "Poster upload failed. Please try again.";
        } elseif ($_FILES["poster"]["size"] > $maxFileSize) {
            $error = "Poster size must not exceed 2 MB.";
        } elseif (
            !is_uploaded_file($_FILES["poster"]["tmp_name"])
        ) {
            $error = "Invalid uploaded file.";
        } else {

            $tmpPath = $_FILES["poster"]["tmp_name"];

            // Detect actual file MIME type
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($tmpPath);

            $allowedTypes = [
                "image/jpeg" => "jpg",
                "image/png"  => "png",
                "image/webp" => "webp"
            ];

            // Verify that the file is a valid supported image
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

                    // Generate a random filename
                    $fileName = bin2hex(random_bytes(16))
                        . "." . $allowedTypes[$mimeType];

                    $destination = $uploadDir . $fileName;

                    if (!move_uploaded_file($tmpPath, $destination)) {
                        $error = "Unable to save the poster file.";
                    } else {
                        $posterPath = "uploads/posters/" . $fileName;
                        $savedPosterFile = $destination;
                    }
                }
            }
        }

        // Insert event only if validation succeeds
        if ($error === "") {
            try {
                $descriptionValue = $description === ""
                    ? null
                    : $description;

                $stmt = $conn->prepare(
                    "INSERT INTO events
                        (title, description, event_date, venue, poster_path)
                     VALUES (?, ?, ?, ?, ?)"
                );

                $stmt->bind_param(
                    "sssss",
                    $title,
                    $descriptionValue,
                    $eventDate,
                    $venue,
                    $posterPath
                );

                $stmt->execute();

// Get the newly created event ID
$newEventId = (int) $conn->insert_id;

$stmt->close();

// Record event creation in audit log
logAudit(
    $conn,
    "create_event",
    "event",
    $newEventId,
    "Created event: " . $title
);

// Prevent duplicate form submission
$_SESSION["csrf_token"] = bin2hex(random_bytes(32));

header("Location: events.php?added=1");
exit;

            } catch (Throwable $e) {
                error_log("Add event error: " . $e->getMessage());

                // Remove the uploaded file if DB insertion fails
                if (
                    $savedPosterFile !== null &&
                    is_file($savedPosterFile)
                ) {
                    unlink($savedPosterFile);
                }

                $error = "Unable to add event. Please try again.";
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

    <title>Add Event | StudentHub</title>

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

        .event-form input[type="file"] {
            background: white;
            color: #1f2937;
        }

        .help-text {
            font-size: 14px;
            opacity: 0.9;
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

    <h2>Add New Event</h2>

    <div class="card event-form">

        <?php if ($error !== ""): ?>
            <div class="form-message error-message" role="alert">
                <?= escapeHtml($error) ?>
            </div>
        <?php endif; ?>

        <form
            action="event_add.php"
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
                <label for="poster">Event Poster (Optional)</label>

                <input
                    type="file"
                    id="poster"
                    name="poster"
                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                >

                <p class="help-text">
                    Allowed formats: JPG, PNG, WEBP. Maximum size: 2 MB.
                </p>
            </div>

            <button type="submit">Add Event</button>

            <a href="events.php">Cancel</a>

        </form>

    </div>

</main>

</body>
</html>

<?php
$conn->close();
?>
