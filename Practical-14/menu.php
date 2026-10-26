
<?php

// Start a session only if one is not already active
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$role = $_SESSION["role"] ?? "";
$currentPage = basename($_SERVER["PHP_SELF"] ?? "");
?>

<nav aria-label="Main navigation">

    <?php if ($role === "admin"): ?>

        <a
            href="admin_dashboard.php"
            <?= $currentPage === "admin_dashboard.php"
                ? 'aria-current="page"'
                : "" ?>
        >
            Admin Dashboard
        </a>

        <a href="students_ajax.php">
            Student Management
        </a>

        <a href="events.php">
            Event Management
        </a>

    <?php elseif ($role === "student"): ?>

        <a
            href="dashboard.php"
            <?= $currentPage === "dashboard.php"
                ? 'aria-current="page"'
                : "" ?>
        >
            Student Dashboard
        </a>

    <?php endif; ?>

    <a href="logout.php">Logout</a>

</nav>
