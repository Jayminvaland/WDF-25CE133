<?php
require_once "db.php";

// Prepare a query to find student records
$sql = "SELECT student_id, full_name, email, course, study_year
        FROM students
        WHERE email LIKE :emailPattern
        ORDER BY student_id";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    "emailPattern" => "%@example.com"
]);

$students = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PDO Connection Test | StudentHub</title>
</head>
<body>

    <h1>StudentHub - PDO Connection Test</h1>

    <p>Database connection successful!</p>

    <h2>Student Records</h2>

    <?php if (count($students) > 0): ?>
        <table border="1" cellpadding="10">
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Course</th>
                <th>Year</th>
            </tr>

            <?php foreach ($students as $student): ?>
                <tr>
                    <td><?= htmlspecialchars((string)$student["student_id"]) ?></td>
                    <td><?= htmlspecialchars($student["full_name"]) ?></td>
                    <td><?= htmlspecialchars($student["email"]) ?></td>
                    <td><?= htmlspecialchars($student["course"]) ?></td>
                    <td><?= htmlspecialchars((string)$student["study_year"]) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p>No student records found.</p>
    <?php endif; ?>

</body>
</html>