
<?php
session_start();

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");
header("X-Content-Type-Options: nosniff");

require_once __DIR__ . "/../db.php";
require_once __DIR__ . "/../audit.php";

// Send a JSON response and stop execution
function sendJson(int $status, array $data): void
{
    http_response_code($status);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

// Read a string safely
function readField(array $input, string $key): string
{
    $value = $input[$key] ?? "";

    if (!is_string($value) && !is_numeric($value)) {
        return "";
    }

    return trim((string) $value);
}

// Read JSON request body
function readJsonInput(): array
{
    $raw = file_get_contents("php://input");
    $data = json_decode($raw, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        sendJson(400, [
            "success" => false,
            "message" => "Invalid JSON request."
        ]);
    }

    return $data;
}

// Validate student fields
function validateStudent(array $input): array
{
    $student = [
        "full_name" => readField($input, "full_name"),
        "email" => readField($input, "email"),
        "mobile" => readField($input, "mobile"),
        "course" => readField($input, "course"),
        "study_year" => readField($input, "study_year")
    ];

    if (
        $student["full_name"] === "" ||
        $student["email"] === "" ||
        $student["course"] === ""
    ) {
        return [$student, "Name, email and course are required."];
    }

    if (strlen($student["full_name"]) > 100) {
        return [$student, "Name must not exceed 100 characters."];
    }

    if (!filter_var($student["email"], FILTER_VALIDATE_EMAIL)) {
        return [$student, "Please enter a valid email address."];
    }

    if (strlen($student["email"]) > 150) {
        return [$student, "Email must not exceed 150 characters."];
    }

    if (strlen($student["course"]) > 100) {
        return [$student, "Course must not exceed 100 characters."];
    }

    if (
        $student["mobile"] !== "" &&
        !preg_match('/^[0-9]{10}$/', $student["mobile"])
    ) {
        return [$student, "Mobile number must contain 10 digits."];
    }

    if (
        $student["study_year"] !== "" &&
        (
            !ctype_digit($student["study_year"]) ||
            (int) $student["study_year"] < 1 ||
            (int) $student["study_year"] > 4
        )
    ) {
        return [$student, "Study year must be between 1 and 4."];
    }

    return [$student, null];
}

// Check login
if (!isset($_SESSION["user_id"])) {
    sendJson(401, [
        "success" => false,
        "message" => "Please log in first."
    ]);
}

// Check 15-minute inactivity timeout
if (
    !isset($_SESSION["last_activity"]) ||
    (time() - $_SESSION["last_activity"]) > 900
) {
    $_SESSION = [];
    session_destroy();

    sendJson(401, [
        "success" => false,
        "message" => "Session expired. Please log in again."
    ]);
}

// Only admins may manage students
if (($_SESSION["role"] ?? "") !== "admin") {
    sendJson(403, [
        "success" => false,
        "message" => "Access denied. Admin permission required."
    ]);
}

$_SESSION["last_activity"] = time();

// Generate a CSRF token
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$method = $_SERVER["REQUEST_METHOD"];

try {

    // ==========================================
    // GET: Fetch students as JSON
    // ==========================================
    if ($method === "GET") {

        $search = readField($_GET, "search");
        $courseFilter = readField($_GET, "course");

        $stmt = $conn->prepare(
            "SELECT student_id, full_name, email, mobile,
                    course, study_year, created_at
             FROM students
             WHERE (
                 ? = ''
                 OR full_name LIKE CONCAT('%', ?, '%')
                 OR email LIKE CONCAT('%', ?, '%')
                 OR mobile LIKE CONCAT('%', ?, '%')
                 OR course LIKE CONCAT('%', ?, '%')
             )
             AND (? = '' OR course = ?)
             ORDER BY student_id DESC"
        );

        $stmt->bind_param(
            "sssssss",
            $search,
            $search,
            $search,
            $search,
            $search,
            $courseFilter,
            $courseFilter
        );

        $stmt->execute();

        $result = $stmt->get_result();
        $students = $result->fetch_all(MYSQLI_ASSOC);

        $stmt->close();

        sendJson(200, [
            "success" => true,
            "message" => "Students fetched successfully.",
            "csrf_token" => $_SESSION["csrf_token"],
            "total" => count($students),
            "data" => $students
        ]);
    }

    // Allow only supported HTTP methods
    if (!in_array($method, ["POST", "PUT", "DELETE"], true)) {
        header("Allow: GET, POST, PUT, DELETE");

        sendJson(405, [
            "success" => false,
            "message" => "Method not allowed."
        ]);
    }

    // Verify CSRF token before any write operation
    $csrfToken = $_SERVER["HTTP_X_CSRF_TOKEN"] ?? "";

    if (
        !is_string($csrfToken) ||
        !hash_equals($_SESSION["csrf_token"], $csrfToken)
    ) {
        sendJson(403, [
            "success" => false,
            "message" => "Invalid CSRF token. Refresh the page and retry."
        ]);
    }

    $input = readJsonInput();

    // ==========================================
    // POST: Add a student
    // ==========================================
    if ($method === "POST") {

        [$student, $error] = validateStudent($input);

        if ($error !== null) {
            sendJson(422, [
                "success" => false,
                "message" => $error
            ]);
        }

        // Check duplicate email
        $check = $conn->prepare(
            "SELECT student_id
             FROM students
             WHERE email = ?
             LIMIT 1"
        );

        $check->bind_param("s", $student["email"]);
        $check->execute();

        $duplicate = $check->get_result()->fetch_assoc();
        $check->close();

        if ($duplicate) {
            sendJson(409, [
                "success" => false,
                "message" => "This email is already registered."
            ]);
        }

        $mobile = $student["mobile"] === ""
            ? null
            : $student["mobile"];

        $studyYear = $student["study_year"] === ""
            ? null
            : (int) $student["study_year"];

        $stmt = $conn->prepare(
            "INSERT INTO students
                (full_name, email, mobile, course, study_year)
             VALUES (?, ?, ?, ?, ?)"
        );

        $stmt->bind_param(
            "ssssi",
            $student["full_name"],
            $student["email"],
            $mobile,
            $student["course"],
            $studyYear
        );

        $stmt->execute();

        // Capture the new ID immediately after insertion
        $newId = (int) $stmt->insert_id;

        $stmt->close();

        // Log the successful student creation
        logAudit(
            $conn,
            "create_student",
            "student",
            $newId,
            "Created student: " . $student["full_name"]
        );

        sendJson(201, [
            "success" => true,
            "message" => "Student added successfully.",
            "student_id" => $newId
        ]);
    }

    // ==========================================
    // PUT: Update a student
    // ==========================================
    if ($method === "PUT") {

        $studentId = filter_var(
            $input["student_id"] ?? null,
            FILTER_VALIDATE_INT
        );

        if (
            $studentId === false ||
            $studentId === null ||
            $studentId < 1
        ) {
            sendJson(400, [
                "success" => false,
                "message" => "A valid student_id is required."
            ]);
        }

        [$student, $error] = validateStudent($input);

        if ($error !== null) {
            sendJson(422, [
                "success" => false,
                "message" => $error
            ]);
        }

        // Check that the student exists
        $find = $conn->prepare(
            "SELECT student_id
             FROM students
             WHERE student_id = ?
             LIMIT 1"
        );

        $find->bind_param("i", $studentId);
        $find->execute();

        $existing = $find->get_result()->fetch_assoc();
        $find->close();

        if (!$existing) {
            sendJson(404, [
                "success" => false,
                "message" => "Student not found."
            ]);
        }

        // Do not allow another student to use this email
        $check = $conn->prepare(
            "SELECT student_id
             FROM students
             WHERE email = ? AND student_id <> ?
             LIMIT 1"
        );

        $check->bind_param("si", $student["email"], $studentId);
        $check->execute();

        $duplicate = $check->get_result()->fetch_assoc();
        $check->close();

        if ($duplicate) {
            sendJson(409, [
                "success" => false,
                "message" => "This email is already used by another student."
            ]);
        }

        $mobile = $student["mobile"] === ""
            ? null
            : $student["mobile"];

        $studyYear = $student["study_year"] === ""
            ? null
            : (int) $student["study_year"];

        $stmt = $conn->prepare(
            "UPDATE students
             SET full_name = ?,
                 email = ?,
                 mobile = ?,
                 course = ?,
                 study_year = ?
             WHERE student_id = ?"
        );

        $stmt->bind_param(
            "ssssii",
            $student["full_name"],
            $student["email"],
            $mobile,
            $student["course"],
            $studyYear,
            $studentId
        );

        $stmt->execute();
        $stmt->close();

        // Log the successful student update
        logAudit(
            $conn,
            "update_student",
            "student",
            (int) $studentId,
            "Updated student: " . $student["full_name"]
        );

        sendJson(200, [
            "success" => true,
            "message" => "Student updated successfully.",
            "student_id" => (int) $studentId
        ]);
    }

    // ==========================================
    // DELETE: Remove a student
    // ==========================================
    if ($method === "DELETE") {

        $studentId = filter_var(
            $input["student_id"] ?? null,
            FILTER_VALIDATE_INT
        );

        if (
            $studentId === false ||
            $studentId === null ||
            $studentId < 1
        ) {
            sendJson(400, [
                "success" => false,
                "message" => "A valid student_id is required."
            ]);
        }

        $stmt = $conn->prepare(
            "DELETE FROM students
             WHERE student_id = ?"
        );

        $stmt->bind_param("i", $studentId);
        $stmt->execute();

        $deleted = $stmt->affected_rows;
        $stmt->close();

        if ($deleted !== 1) {
            sendJson(404, [
                "success" => false,
                "message" => "Student not found."
            ]);
        }

        // Log the successful student deletion
        logAudit(
            $conn,
            "delete_student",
            "student",
            (int) $studentId,
            "Deleted student record."
        );

        sendJson(200, [
            "success" => true,
            "message" => "Student deleted successfully.",
            "student_id" => (int) $studentId
        ]);
    }

} catch (Throwable $e) {

    error_log("Student API error: " . $e->getMessage());

    sendJson(500, [
        "success" => false,
        "message" => "Server error. Please try again."
    ]);
}
?>

