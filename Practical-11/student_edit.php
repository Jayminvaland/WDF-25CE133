
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

// Only admin can edit students
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

// Validate student ID from URL
$studentId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$studentId || $studentId < 1) {
    http_response_code(400);
    exit("Invalid student ID.");
}

// Generate CSRF token
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Find the existing student
$find = $conn->prepare(
    "SELECT student_id, full_name, email, mobile, course, study_year
     FROM students
     WHERE student_id = ?
     LIMIT 1"
);

$find->bind_param("i", $studentId);
$find->execute();

$student = $find->get_result()->fetch_assoc();
$find->close();

if (!$student) {
    http_response_code(404);
    exit("Student not found.");
}

// Fill form with existing details
$fullName = $student["full_name"];
$email = $student["email"];
$mobile = $student["mobile"] ?? "";
$course = $student["course"];
$studyYear = $student["study_year"] === null
    ? ""
    : (string) $student["study_year"];

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $fullName = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $mobile = trim($_POST["mobile"] ?? "");
    $course = trim($_POST["course"] ?? "");
    $studyYear = trim($_POST["study_year"] ?? "");
    $csrfToken = $_POST["csrf_token"] ?? "";

    // Verify CSRF token
    if (
        !is_string($csrfToken) ||
        !hash_equals($_SESSION["csrf_token"], $csrfToken)
    ) {
        $error = "Invalid form request. Please refresh the page.";
    } elseif (
        $fullName === "" ||
        $email === "" ||
        $course === ""
    ) {
        $error = "Please fill in all required fields.";
    } elseif (strlen($fullName) > 100) {
        $error = "Name must not exceed 100 characters.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($email) > 150) {
        $error = "Email must not exceed 150 characters.";
    } elseif (strlen($course) > 100) {
        $error = "Course must not exceed 100 characters.";
    } elseif (
        $mobile !== "" &&
        !preg_match('/^[0-9]{10}$/', $mobile)
    ) {
        $error = "Mobile number must contain exactly 10 digits.";
    } elseif (
        $studyYear !== "" &&
        (
            !ctype_digit($studyYear) ||
            (int) $studyYear < 1 ||
            (int) $studyYear > 4
        )
    ) {
        $error = "Study year must be between 1 and 4.";
    } else {
        try {
            // Check whether another student uses this email
            $check = $conn->prepare(
                "SELECT student_id
                 FROM students
                 WHERE email = ? AND student_id <> ?
                 LIMIT 1"
            );

            $check->bind_param("si", $email, $studentId);
            $check->execute();

            $duplicate = $check->get_result()->fetch_assoc();
            $check->close();

            if ($duplicate) {
                $error = "This email is already used by another student.";
            } else {
                // Empty optional fields are saved as NULL
                $mobileValue = $mobile === "" ? null : $mobile;
                $yearValue = $studyYear === ""
                    ? null
                    : (int) $studyYear;

                // Update using a prepared statement
                $update = $conn->prepare(
                    "UPDATE students
                     SET full_name = ?,
                         email = ?,
                         mobile = ?,
                         course = ?,
                         study_year = ?
                     WHERE student_id = ?"
                );

                $update->bind_param(
                    "ssssii",
                    $fullName,
                    $email,
                    $mobileValue,
                    $course,
                    $yearValue,
                    $studentId
                );

                $update->execute();
                $update->close();

                // Redirect after successful update
                header(
                    "Location: student_edit.php?id=" .
                    $studentId . "&updated=1"
                );
                exit;
            }
        } catch (mysqli_sql_exception $e) {
            error_log($e->getMessage());
            $error = "Unable to update student. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Student | StudentHub</title>

    <link rel="stylesheet" href="style.css">
</head>

<body>

<header>
    <h1>STUDENT PORTAL</h1>
</header>

<hr>

<nav>
    <a href="admin_dashboard.php">Admin Dashboard</a>
    <a href="students.php">Manage Students</a>
    <a href="logout.php">Logout</a>
</nav>

<hr>

<main class="container">

    <h2>Edit Student</h2>

    <div class="card">

        <p>
            Editing Student ID:
            <strong><?= (int) $studentId ?></strong>
        </p>

        <?php if (isset($_GET["updated"])): ?>
            <p role="status" style="color: #86efac;">
                Student updated successfully!
            </p>
        <?php endif; ?>

        <?php if ($error !== ""): ?>
            <p role="alert" style="color: #fecaca;">
                <?= escapeHtml($error) ?>
            </p>
        <?php endif; ?>

        <form
            action="student_edit.php?id=<?= (int) $studentId ?>"
            method="POST"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= escapeHtml($_SESSION["csrf_token"]) ?>"
            >

            <div class="form-group">
                <label for="full_name">Full Name *</label>

                <input
                    type="text"
                    id="full_name"
                    name="full_name"
                    maxlength="100"
                    value="<?= escapeHtml($fullName) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="email">Email Address *</label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    maxlength="150"
                    value="<?= escapeHtml($email) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="mobile">Mobile Number</label>

                <input
                    type="tel"
                    id="mobile"
                    name="mobile"
                    inputmode="numeric"
                    pattern="[0-9]{10}"
                    maxlength="10"
                    value="<?= escapeHtml($mobile) ?>"
                    placeholder="10-digit mobile number"
                >
            </div>

            <div class="form-group">
                <label for="course">Course *</label>

                <input
                    type="text"
                    id="course"
                    name="course"
                    maxlength="100"
                    value="<?= escapeHtml($course) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="study_year">Study Year</label>

                <select id="study_year" name="study_year">
                    <option value="">Select Study Year</option>

                    <?php for ($i = 1; $i <= 4; $i++): ?>
                        <option
                            value="<?= $i ?>"
                            <?= (string) $studyYear === (string) $i
                                ? "selected"
                                : "" ?>
                        >
                            Year <?= $i ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>

            <button type="submit">Update Student</button>

            <a href="students.php">Cancel</a>

        </form>

    </div>

</main>

</body>
</html>

<?php
$conn->close();
?>
