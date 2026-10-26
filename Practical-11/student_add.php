
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

// Only admin can add students
if (($_SESSION["role"] ?? "") !== "admin") {
    http_response_code(403);
    exit("Access denied. Admin permission required.");
}

$_SESSION["last_activity"] = time();

function escapeHtml($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8"
    );
}

// Generate CSRF token
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$fullName = "";
$email = "";
$mobile = "";
$course = "";
$studyYear = "";
$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $fullName = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $mobile = trim($_POST["mobile"] ?? "");
    $course = trim($_POST["course"] ?? "");
    $studyYear = trim($_POST["study_year"] ?? "");
    $csrfToken = $_POST["csrf_token"] ?? "";

    // Check CSRF token
    if (
        !is_string($csrfToken) ||
        !hash_equals($_SESSION["csrf_token"], $csrfToken)
    ) {
        $error = "Invalid form request. Please refresh the page.";
    }

    // Validate required fields
    elseif ($fullName === "" || $email === "" || $course === "") {
        $error = "Please fill in all required fields.";
    }

    elseif (strlen($fullName) > 100) {
        $error = "Name must not exceed 100 characters.";
    }

    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    }

    elseif (strlen($email) > 150) {
        $error = "Email must not exceed 150 characters.";
    }

    elseif (strlen($course) > 100) {
        $error = "Course must not exceed 100 characters.";
    }

    elseif (
        $mobile !== "" &&
        !preg_match('/^[0-9]{10}$/', $mobile)
    ) {
        $error = "Mobile number must contain exactly 10 digits.";
    }

    elseif (
        $studyYear !== "" &&
        (!ctype_digit($studyYear) ||
         (int) $studyYear < 1 ||
         (int) $studyYear > 4)
    ) {
        $error = "Study year must be between 1 and 4.";
    }

    else {
        try {
            // Check duplicate email
            $check = $conn->prepare(
                "SELECT student_id
                 FROM students
                 WHERE email = ?
                 LIMIT 1"
            );

            $check->bind_param("s", $email);
            $check->execute();

            $existingStudent = $check->get_result()->fetch_assoc();
            $check->close();

            if ($existingStudent !== null) {
                $error = "This email is already registered.";
            } else {
                // Store empty optional fields as NULL
                $mobileValue = $mobile === "" ? null : $mobile;
                $yearValue = $studyYear === ""
                    ? null
                    : (int) $studyYear;

                // Insert student with a prepared statement
                $stmt = $conn->prepare(
                    "INSERT INTO students
                        (full_name, email, mobile, course, study_year)
                     VALUES (?, ?, ?, ?, ?)"
                );

                $stmt->bind_param(
                    "ssssi",
                    $fullName,
                    $email,
                    $mobileValue,
                    $course,
                    $yearValue
                );

                $stmt->execute();
                $stmt->close();

                $success = "Student added successfully!";

                // Clear form after successful insertion
                $fullName = "";
                $email = "";
                $mobile = "";
                $course = "";
                $studyYear = "";

                // Generate a fresh CSRF token
                $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
            }
        } catch (mysqli_sql_exception $e) {
            error_log($e->getMessage());
            $error = "Unable to add student. Please check the details and try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Add Student | StudentHub</title>

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

    <h2>Add New Student</h2>

    <div class="card">

        <?php if ($error !== ""): ?>
            <p role="alert" style="color: #fecaca;">
                <?= escapeHtml($error) ?>
            </p>
        <?php endif; ?>

        <?php if ($success !== ""): ?>
            <p role="status" style="color: #86efac;">
                <?= escapeHtml($success) ?>
            </p>

            <p>
                <a href="students.php">View Student List</a>
            </p>
        <?php endif; ?>

        <form action="student_add.php" method="POST">

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
                    placeholder="Example: Information Technology"
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

            <button type="submit">Add Student</button>

            <a href="students.php">Cancel</a>

        </form>

    </div>

</main>

</body>
</html>

<?php
$conn->close();
?>
