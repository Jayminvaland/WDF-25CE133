
<?php
session_start();

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/audit.php";

header("Cache-Control: no-store");

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

// Admin-only dashboard
if (($_SESSION["role"] ?? "") !== "admin") {
    http_response_code(403);
    exit("Access denied. Admin permission required.");
}

$_SESSION["last_activity"] = time();

// Escape output safely
function escapeHtml($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ""),
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8"
    );
}

$error = "";
$totalUsers = 0;
$totalStudents = 0;
$totalEvents = 0;
$totalRegistrations = 0;
$latestRegistrations = [];
$latestAuditLogs = [];

try {
    // Dashboard statistics
    $totalUsers = (int) $conn
        ->query("SELECT COUNT(*) AS total FROM users")
        ->fetch_assoc()["total"];

    $totalStudents = (int) $conn
        ->query("SELECT COUNT(*) AS total FROM students")
        ->fetch_assoc()["total"];

    $totalEvents = (int) $conn
        ->query("SELECT COUNT(*) AS total FROM events")
        ->fetch_assoc()["total"];

    $totalRegistrations = (int) $conn
        ->query("SELECT COUNT(*) AS total FROM registrations")
        ->fetch_assoc()["total"];

    // Record one dashboard visit per session
    if (empty($_SESSION["dashboard_visit_logged"])) {
        logAudit(
            $conn,
            "view_dashboard",
            "dashboard",
            null,
            "Admin opened the dashboard."
        );

        $_SESSION["dashboard_visit_logged"] = true;
    }

    // Latest registrations with student and event details
    $registrationResult = $conn->query(
        "SELECT
            r.registration_id,
            r.registered_at,
            r.status,
            s.full_name,
            e.title AS event_title
         FROM registrations r
         LEFT JOIN students s
            ON r.student_id = s.student_id
         LEFT JOIN events e
            ON r.event_id = e.event_id
         ORDER BY r.registered_at DESC,
                  r.registration_id DESC
         LIMIT 5"
    );

    $latestRegistrations =
        $registrationResult->fetch_all(MYSQLI_ASSOC);

    // Latest audit entries
    $auditResult = $conn->query(
        "SELECT
            a.audit_id,
            a.action,
            a.entity_type,
            a.entity_id,
            a.details,
            a.created_at,
            u.full_name AS admin_name
         FROM audit_logs a
         LEFT JOIN users u
            ON a.user_id = u.user_id
         ORDER BY a.created_at DESC, a.audit_id DESC
         LIMIT 5"
    );

    $latestAuditLogs = $auditResult->fetch_all(MYSQLI_ASSOC);

} catch (Throwable $e) {
    error_log("Admin dashboard error: " . $e->getMessage());
    $error = "Some dashboard information could not be loaded.";
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Dashboard | StudentHub</title>
    <link rel="stylesheet" href="style.css">

    <style>
        .dashboard-welcome {
            margin-bottom: 22px;
        }

        .stat-card {
            text-align: center;
        }

        .stat-number {
            font-size: 30px;
            font-weight: bold;
            margin: 12px 0;
            color: #90caf9;
        }

        .dashboard-section {
            margin-top: 28px;
        }

        .dashboard-section h3 {
            margin-bottom: 14px;
        }

        .dashboard-note {
            padding: 12px;
            border-radius: 6px;
            margin: 14px 0;
            background: #fee2e2;
            color: #991b1b;
        }

        .dashboard-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 16px;
        }

        .dashboard-actions a {
            display: inline-block;
            padding: 10px 14px;
            border-radius: 5px;
            background: #0d6efd;
            color: white;
            text-decoration: none;
            font-weight: bold;
        }

        .dashboard-actions a:hover {
            background: #084298;
        }

        .dashboard-table td {
            overflow-wrap: anywhere;
        }

        body.light-theme .stat-number {
            color: #075985;
        }
    </style>
</head>

<body>

<header>
    <h1>STUDENT PORTAL</h1>
</header>

<hr>

<?php require_once __DIR__ . "/menu.php"; ?>

<hr>

<main class="container">

    <h2>Admin Dashboard</h2>

    <section class="dashboard-welcome card">
        <h3>
            Welcome, Admin
            <?= escapeHtml($_SESSION["full_name"] ?? "Administrator") ?>!
        </h3>

        <p>
            You are logged in with administrator privileges.
        </p>

        <p>
            <strong>Email:</strong>
            <?= escapeHtml($_SESSION["email"] ?? "") ?>
        </p>
    </section>

    <?php if ($error !== ""): ?>
        <div class="dashboard-note" role="alert">
            <?= escapeHtml($error) ?>
        </div>
    <?php endif; ?>

    <section class="dashboard-section">
        <h3>Dashboard Statistics</h3>

        <div class="grid-container">

            <div class="card stat-card">
                <h3>Total Users</h3>
                <p class="stat-number"><?= $totalUsers ?></p>
            </div>

            <div class="card stat-card">
                <h3>Total Students</h3>
                <p class="stat-number"><?= $totalStudents ?></p>
            </div>

            <div class="card stat-card">
                <h3>Total Events</h3>
                <p class="stat-number"><?= $totalEvents ?></p>
            </div>

            <div class="card stat-card">
                <h3>Total Registrations</h3>
                <p class="stat-number"><?= $totalRegistrations ?></p>
            </div>

        </div>
    </section>

    <section class="dashboard-section">
        <h3>Quick Actions</h3>

        <div class="dashboard-actions">
            <a href="students_ajax.php">Manage Students</a>
            <a href="events.php">Manage Events</a>
            <a href="admin_dashboard.php">Refresh Dashboard</a>
        </div>
    </section>

    <section class="dashboard-section">
        <h3>Latest Registrations</h3>

        <div class="table-container">
            <table class="dashboard-table">

                <thead>
                    <tr>
                        <th>Registration ID</th>
                        <th>Student</th>
                        <th>Event</th>
                        <th>Status</th>
                        <th>Registered At</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (count($latestRegistrations) > 0): ?>

                        <?php foreach ($latestRegistrations as $registration): ?>
                            <tr>
                                <td>
                                    <?= (int) $registration["registration_id"] ?>
                                </td>

                                <td>
                                    <?= escapeHtml(
                                        $registration["full_name"] ?? "Unknown student"
                                    ) ?>
                                </td>

                                <td>
                                    <?= escapeHtml(
                                        $registration["event_title"] ?? "Unknown event"
                                    ) ?>
                                </td>

                                <td>
                                    <?= escapeHtml($registration["status"] ?? "") ?>
                                </td>

                                <td>
                                    <?= escapeHtml($registration["registered_at"] ?? "") ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                    <?php else: ?>
                        <tr>
                            <td colspan="5">No registrations found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>

            </table>
        </div>
    </section>

    <section class="dashboard-section">
        <h3>Recent Audit Logs</h3>

        <div class="table-container">
            <table class="dashboard-table">

                <thead>
                    <tr>
                        <th>Audit ID</th>
                        <th>Admin</th>
                        <th>Action</th>
                        <th>Entity</th>
                        <th>Details</th>
                        <th>Date and Time</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (count($latestAuditLogs) > 0): ?>

                        <?php foreach ($latestAuditLogs as $log): ?>
                            <tr>
                                <td><?= (int) $log["audit_id"] ?></td>

                                <td>
                                    <?= escapeHtml(
                                        $log["admin_name"] ?? "Unknown user"
                                    ) ?>
                                </td>

                                <td><?= escapeHtml($log["action"]) ?></td>

                                <td>
                                    <?= escapeHtml($log["entity_type"] ?? "—") ?>

                                    <?php if ($log["entity_id"] !== null): ?>
                                        #<?= (int) $log["entity_id"] ?>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?= escapeHtml($log["details"] ?? "—") ?>
                                </td>

                                <td>
                                    <?= escapeHtml($log["created_at"]) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                    <?php else: ?>
                        <tr>
                            <td colspan="6">No audit logs found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>

            </table>
        </div>
    </section>

</main>

</body>
</html>

<?php
$conn->close();
?>
