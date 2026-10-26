<?php
session_start();

// Check login session
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// Check session timeout: 15 minutes
if (
    !isset($_SESSION["last_activity"]) ||
    (time() - $_SESSION["last_activity"]) > 900
) {
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            "",
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();

    header("Location: login.php?timeout=1");
    exit;
}

// Admin should use the admin dashboard
if ($_SESSION["role"] === "admin") {
    header("Location: admin_dashboard.php");
    exit;
}

if ($_SESSION["role"] !== "student") {
    http_response_code(403);
    exit("Access denied.");
}

// Update session activity time
$_SESSION["last_activity"] = time();

function escapeHtml(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8"
    );
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard | StudentHub</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header>
    <h1>STUDENT PORTAL</h1>
</header>

<hr>

<nav>
    <a href="dashboard.php" aria-current="page">Dashboard</a>
    <a href="logout.php">Logout</a>
</nav>

<hr>

<main class="container">
    <h2>Student Dashboard</h2>

    <div class="card">
        <h3>
            Welcome,
            <?= escapeHtml($_SESSION["full_name"]) ?>!
        </h3>

        <p>You have logged in successfully.</p>

        <p>
            <strong>User ID:</strong>
            <?= (int) $_SESSION["user_id"] ?>
        </p>

        <p>
            <strong>Email:</strong>
            <?= escapeHtml($_SESSION["email"]) ?>
        </p>

        <p>
            <strong>Role:</strong>
            <?= escapeHtml($_SESSION["role"]) ?>
        </p>
    </div>

    <p>Your session expires after 15 minutes of inactivity.</p>

    <a href="logout.php">Logout from your account</a>
</main>

</body>
</html>