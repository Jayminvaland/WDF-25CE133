
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

// Only admin can manage events
if (($_SESSION["role"] ?? "") !== "admin") {
    http_response_code(403);
    exit("Access denied. Admin permission required.");
}

$_SESSION["last_activity"] = time();

// Generate CSRF token for future delete operations
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Escape output safely
function escapeHtml($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ""),
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8"
    );
}

// Fetch all events
try {
    $stmt = $conn->prepare(
        "SELECT event_id, title, description, event_date,
                venue, poster_path, created_at
         FROM events
         ORDER BY event_date DESC, event_id DESC"
    );

    $stmt->execute();
    $events = $stmt->get_result();

} catch (Throwable $e) {
    error_log("Event list error: " . $e->getMessage());
    http_response_code(500);
    exit("Unable to load events. Please try again.");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Event Management | StudentHub</title>

    <link rel="stylesheet" href="style.css">

    <style>
        .event-poster {
            width: 100px;
            max-height: 120px;
            object-fit: contain;
            border-radius: 6px;
        }

        .event-description {
            min-width: 160px;
            max-width: 280px;
            white-space: normal;
            overflow-wrap: anywhere;
        }

        .event-actions form {
            margin: 6px auto;
            width: auto;
        }

        .event-actions a {
            display: inline-block;
            margin: 5px;
        }

        .status-message {
            padding: 12px;
            margin: 15px 0;
            border-radius: 6px;
        }

        .status-success {
            background: #dcfce7;
            color: #166534;
        }

        .status-error {
            background: #fee2e2;
            color: #991b1b;
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
    <a href="students.php">Manage Students</a>
    <a href="events.php" aria-current="page">Manage Events</a>
    <a href="logout.php">Logout</a>
</nav>

<hr>

<main class="container">

    <h2>Event Management</h2>

    <?php if (isset($_GET["added"]) && $_GET["added"] === "1"): ?>
        <div class="status-message status-success" role="status">
            Event added successfully!
        </div>
    <?php endif; ?>

    <?php if (isset($_GET["updated"]) && $_GET["updated"] === "1"): ?>
        <div class="status-message status-success" role="status">
            Event updated successfully!
        </div>
    <?php endif; ?>

    <?php if (isset($_GET["deleted"]) && $_GET["deleted"] === "1"): ?>
        <div class="status-message status-success" role="status">
            Event deleted successfully!
        </div>
    <?php endif; ?>

    <?php if (isset($_GET["error"])): ?>
        <div class="status-message status-error" role="alert">
            The requested operation could not be completed.
        </div>
    <?php endif; ?>

    <div class="card">

        <h3>Manage Events</h3>

        <p>
            Total events:
            <strong><?= $events->num_rows ?></strong>
        </p>

        <p>
            <a href="event_add.php">+ Add New Event</a>
        </p>

    </div>

    <div class="table-container">

        <table>

            <thead>
                <tr>
                    <th>ID</th>
                    <th>Poster</th>
                    <th>Event Title</th>
                    <th>Description</th>
                    <th>Event Date</th>
                    <th>Venue</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>

                <?php if ($events->num_rows > 0): ?>

                    <?php while ($event = $events->fetch_assoc()): ?>

                        <tr>

                            <td>
                                <?= (int) $event["event_id"] ?>
                            </td>

                            <td>
                                <?php
                                $poster = $event["poster_path"] ?? "";
                                ?>

                                <?php if ($poster !== ""): ?>
                                    <img
                                        class="event-poster"
                                        src="<?= escapeHtml($poster) ?>"
                                        alt="Poster for <?= escapeHtml($event["title"]) ?>"
                                        loading="lazy"
                                    >
                                <?php else: ?>
                                    No poster
                                <?php endif; ?>
                            </td>

                            <td>
                                <?= escapeHtml($event["title"]) ?>
                            </td>

                            <td class="event-description">
                                <?= nl2br(escapeHtml($event["description"] ?: "—")) ?>
                            </td>

                            <td>
                                <?= escapeHtml($event["event_date"]) ?>
                            </td>

                            <td>
                                <?= escapeHtml($event["venue"]) ?>
                            </td>

                            <td>
                                <?= escapeHtml($event["created_at"]) ?>
                            </td>

                            <td class="event-actions">

                                <a href="event_edit.php?id=<?= (int) $event["event_id"] ?>">
                                    Edit
                                </a>

                                <form
                                    action="event_delete.php"
                                    method="POST"
                                    onsubmit="return confirm('Are you sure you want to delete this event?');"
                                >

                                    <input
                                        type="hidden"
                                        name="event_id"
                                        value="<?= (int) $event["event_id"] ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= escapeHtml($_SESSION["csrf_token"]) ?>"
                                    >

                                    <button type="submit">Delete</button>

                                </form>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="8">No events found.</td>
                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</main>

</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
