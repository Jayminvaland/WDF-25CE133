
<?php
session_start();

require_once __DIR__ . "/db.php";

// Check login
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// Check 15-minute session timeout
if (
    !isset($_SESSION["last_activity"]) ||
    (time() - $_SESSION["last_activity"]) > 900
) {
    $_SESSION = [];
    session_destroy();

    header("Location: login.php?timeout=1");
    exit;
}

// Only admin can manage students
if (($_SESSION["role"] ?? "") !== "admin") {
    http_response_code(403);
    exit("Access denied. Admin permission required.");
}

$_SESSION["last_activity"] = time();
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

// Search and course filter
$search = trim($_GET["search"] ?? "");
$courseFilter = trim($_GET["course"] ?? "");

// Pagination
$page = isset($_GET["page"]) &&
        ctype_digit((string) $_GET["page"])
        ? max(1, (int) $_GET["page"])
        : 1;

$limit = 10;

// Count matching students
$countStmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM students
     WHERE (
         ? = ''
         OR full_name LIKE CONCAT('%', ?, '%')
         OR email LIKE CONCAT('%', ?, '%')
         OR course LIKE CONCAT('%', ?, '%')
     )
     AND (? = '' OR course = ?)"
);

$countStmt->bind_param(
    "ssssss",
    $search,
    $search,
    $search,
    $search,
    $courseFilter,
    $courseFilter
);

$countStmt->execute();
$countResult = $countStmt->get_result();
$totalStudents = (int) $countResult->fetch_assoc()["total"];
$countStmt->close();

$totalPages = max(1, (int) ceil($totalStudents / $limit));
$page = min($page, $totalPages);
$offset = ($page - 1) * $limit;

// Fetch students using prepared statements
$stmt = $conn->prepare(
    "SELECT student_id, full_name, email, mobile,
            course, study_year, created_at
     FROM students
     WHERE (
         ? = ''
         OR full_name LIKE CONCAT('%', ?, '%')
         OR email LIKE CONCAT('%', ?, '%')
         OR course LIKE CONCAT('%', ?, '%')
     )
     AND (? = '' OR course = ?)
     ORDER BY student_id DESC
     LIMIT ? OFFSET ?"
);

$stmt->bind_param(
    "ssssssii",
    $search,
    $search,
    $search,
    $search,
    $courseFilter,
    $courseFilter,
    $limit,
    $offset
);

$stmt->execute();
$students = $stmt->get_result();

// Load available courses for the filter
$courseResult = $conn->query(
    "SELECT DISTINCT course
     FROM students
     ORDER BY course"
);

$queryString = http_build_query([
    "search" => $search,
    "course" => $courseFilter
]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Student Management | StudentHub</title>

    <link rel="stylesheet" href="style.css">
</head>

<body>

<header>
    <h1>STUDENT PORTAL</h1>
</header>

<hr>

<nav>
    <a href="admin_dashboard.php">Admin Dashboard</a>
    <a href="students.php" aria-current="page">
        Manage Students
    </a>
    <a href="logout.php">Logout</a>
</nav>

<hr>

<main class="container">

    <h2>Student Management</h2>

    <div class="card">

        <h3>Search and Filter Students</h3>

        <form action="students.php" method="GET">

            <div class="form-group">
                <label for="search">
                    Search by name, email or course
                </label>

                <input
                    type="search"
                    id="search"
                    name="search"
                    placeholder="Enter name, email or course"
                    value="<?= escapeHtml($search) ?>"
                >
            </div>

            <div class="form-group">
                <label for="course">Filter by course</label>

                <select id="course" name="course">
                    <option value="">All Courses</option>

                    <?php while ($courseRow = $courseResult->fetch_assoc()): ?>
                        <option
                            value="<?= escapeHtml($courseRow["course"]) ?>"
                            <?= $courseFilter === $courseRow["course"]
                                ? "selected"
                                : "" ?>
                        >
                            <?= escapeHtml($courseRow["course"]) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <button type="submit">Search / Filter</button>

            <a href="students.php">Reset</a>

        </form>

        <p>
            Total matching students:
            <strong><?= $totalStudents ?></strong>
        </p>

        <p>
            <a href="student_add.php">+ Add New Student</a>
        </p>

    </div>

    <div class="table-container">

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Mobile</th>
                    <th>Course</th>
                    <th>Study Year</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>

                <?php if ($students->num_rows > 0): ?>

                    <?php while ($row = $students->fetch_assoc()): ?>

                        <tr>
                            <td><?= (int) $row["student_id"] ?></td>

                            <td><?= escapeHtml($row["full_name"]) ?></td>

                            <td><?= escapeHtml($row["email"]) ?></td>

                            <td>
                                <?= escapeHtml($row["mobile"] ?: "—") ?>
                            </td>

                            <td><?= escapeHtml($row["course"]) ?></td>

                            <td>
                                <?= $row["study_year"] === null
                                    ? "—"
                                    : escapeHtml($row["study_year"]) ?>
                            </td>

                            <td><?= escapeHtml($row["created_at"]) ?></td>

                            <td>
                                <a href="student_edit.php?id=<?= (int) $row["student_id"] ?>">
                                    Edit
                                </a>

                                <form
    action="student_delete.php"
    method="POST"
    onsubmit="return confirm('Are you sure you want to delete this student?');"
>
    <input
        type="hidden"
        name="student_id"
        value="<?= (int) $row["student_id"] ?>"
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
                        <td colspan="8">No students found.</td>
                    </tr>

                <?php endif; ?>

            </tbody>
        </table>

    </div>

    <?php if ($totalPages > 1): ?>

        <div>
            <p>
                Page <?= $page ?> of <?= $totalPages ?>
            </p>

            <?php if ($page > 1): ?>
                <a href="students.php?<?= escapeHtml($queryString) ?>&page=<?= $page - 1 ?>">
                    Previous
                </a>
            <?php endif; ?>

            <?php if ($page < $totalPages): ?>
                <a href="students.php?<?= escapeHtml($queryString) ?>&page=<?= $page + 1 ?>">
                    Next
                </a>
            <?php endif; ?>
        </div>

    <?php endif; ?>

</main>

</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
