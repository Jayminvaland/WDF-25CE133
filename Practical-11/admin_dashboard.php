<?php
session_start();

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

// Only admins may access this page
if ($_SESSION["role"] !== "admin") {
    http_response_code(403);
    exit("Access denied. Admin permission required.");
}

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
    <title>Admin Dashboard | StudentHub</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header>
    <h1>STUDENT PORTAL</h1>
</header>

<hr>

<nav>
    <a href="admin_dashboard.php" aria-current="page">Admin Dashboard</a>
    <a href="logout.php">Logout</a>
</nav>

<hr>

<main class="container">
    <h2>Admin Dashboard</h2>

    <div class="card">
        <h3>
            Welcome, Admin
            <?= escapeHtml($_SESSION["full_name"]) ?>!
        </h3>

        <p>Admin login successful.</p>

        <p>
            <strong>Email:</strong>
            <?= escapeHtml($_SESSION["email"]) ?>
        </p>

        <p>
            <strong>Role:</strong> Admin
        </p>
    </div>

    <a href="logout.php">Logout from your account</a>
</main>

</body>
</html>