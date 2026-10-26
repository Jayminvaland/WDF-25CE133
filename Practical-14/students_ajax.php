
<?php
session_start();

// Protect the frontend page
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

if (
    !isset($_SESSION["last_activity"]) ||
    (time() - $_SESSION["last_activity"]) > 900
) {
    $_SESSION = [];
    session_destroy();

    header("Location: login.php?timeout=1");
    exit;
}

if (($_SESSION["role"] ?? "") !== "admin") {
    http_response_code(403);
    exit("Access denied. Admin permission required.");
}

$_SESSION["last_activity"] = time();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Student CRUD | StudentHub</title>
    <link rel="stylesheet" href="style.css">

    <style>
        .crud-card {
            margin-bottom: 24px;
        }

        .api-message {
            display: none;
            padding: 12px;
            margin: 15px 0;
            border-radius: 6px;
            overflow-wrap: anywhere;
        }

        .api-message.success {
            display: block;
            background: #dcfce7;
            color: #166534;
        }

        .api-message.error {
            display: block;
            background: #fee2e2;
            color: #991b1b;
        }

        .crud-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 6px;
        }

        .crud-actions button {
            margin: 2px;
        }

        .crud-search {
            display: grid;
            grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
            gap: 12px;
            align-items: end;
        }

        #student-form {
            max-width: 750px;
        }

        @media (max-width: 600px) {
            .crud-search {
                grid-template-columns: 1fr;
            }
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
    <a href="students_ajax.php" aria-current="page">AJAX Student CRUD</a>
    <a href="logout.php">Logout</a>
</nav>

<hr>

<main class="container">

    <h2>Student Management — Fetch API</h2>

    <p>
        Add, view, edit and delete student records without reloading
        the page.
    </p>

    <div id="api-message" class="api-message" role="status"
         aria-live="polite"></div>

    <section class="card crud-card">
        <h3>Search and Filter</h3>

        <form id="search-form" class="crud-search">

            <div class="form-group">
                <label for="search">Search Students</label>
                <input
                    type="search"
                    id="search"
                    placeholder="Search name, email, mobile or course"
                >
            </div>

            <div class="form-group">
                <label for="course-filter">Filter by Course</label>
                <select id="course-filter">
                    <option value="">All Courses</option>
                </select>
            </div>

            <div>
                <button type="submit">Search</button>
                <button type="button" id="reset-filters">
                    Reset
                </button>
            </div>

        </form>
    </section>

    <section class="card crud-card">

        <h3 id="form-heading">Add New Student</h3>

        <form id="student-form">

            <div class="form-group">
                <label for="full_name">Full Name *</label>
                <input
                    type="text"
                    id="full_name"
                    maxlength="100"
                    required
                >
            </div>

            <div class="form-group">
                <label for="email">Email Address *</label>
                <input
                    type="email"
                    id="email"
                    maxlength="150"
                    required
                >
            </div>

            <div class="form-group">
                <label for="mobile">Mobile Number</label>
                <input
                    type="tel"
                    id="mobile"
                    inputmode="numeric"
                    pattern="[0-9]{10}"
                    maxlength="10"
                    placeholder="10-digit mobile number"
                >
            </div>

            <div class="form-group">
                <label for="course">Course *</label>
                <input
                    type="text"
                    id="course"
                    maxlength="100"
                    required
                >
            </div>

            <div class="form-group">
                <label for="study_year">Study Year</label>
                <select id="study_year">
                    <option value="">Select Study Year</option>
                    <option value="1">Year 1</option>
                    <option value="2">Year 2</option>
                    <option value="3">Year 3</option>
                    <option value="4">Year 4</option>
                </select>
            </div>

            <button type="submit" id="save-button">
                Add Student
            </button>

            <button type="button" id="cancel-edit" hidden>
                Cancel Edit
            </button>

        </form>

    </section>

    <section class="card crud-card">

        <h3>Student List</h3>

        <p>
            Total matching students:
            <strong id="student-count">0</strong>
        </p>

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

                <tbody id="student-list">
                    <tr>
                        <td colspan="8">Loading students...</td>
                    </tr>
                </tbody>

            </table>

        </div>

    </section>

</main>

<script>
"use strict";

const API_URL = "api/students.php";

const messageBox = document.getElementById("api-message");
const studentForm = document.getElementById("student-form");
const studentList = document.getElementById("student-list");
const studentCount = document.getElementById("student-count");
const formHeading = document.getElementById("form-heading");
const saveButton = document.getElementById("save-button");
const cancelEditButton = document.getElementById("cancel-edit");
const searchInput = document.getElementById("search");
const courseFilter = document.getElementById("course-filter");

let csrfToken = "";
let editingId = null;
let currentStudents = [];

// Show success or error messages
function showMessage(message, type = "success") {
    messageBox.textContent = message;
    messageBox.className = "api-message " + type;
}

// Make API requests and parse JSON responses
async function apiRequest(url, options = {}) {
    const headers = {
        Accept: "application/json",
        ...(options.headers || {})
    };

    const response = await fetch(url, {
        ...options,
        headers,
        credentials: "same-origin"
    });

    const result = await response.json().catch(() => null);

    if (!result) {
        throw new Error("The server returned an invalid JSON response.");
    }

    if (!response.ok || result.success !== true) {
        throw new Error(
            result.message || "The request could not be completed."
        );
    }

    return result;
}

// Build API URL for search and filtering
function getListURL() {
    const params = new URLSearchParams();

    const search = searchInput.value.trim();
    const course = courseFilter.value;

    if (search) {
        params.set("search", search);
    }

    if (course) {
        params.set("course", course);
    }

    const query = params.toString();

    return query ? API_URL + "?" + query : API_URL;
}

// Populate course dropdown from database records
async function loadCourses() {
    const result = await apiRequest(API_URL);

    csrfToken = result.csrf_token;

    const selectedCourse = courseFilter.value;

    const courses = [
        ...new Set(
            result.data
                .map(student => student.course)
                .filter(Boolean)
        )
    ].sort();

    courseFilter.replaceChildren();

    const allOption = document.createElement("option");
    allOption.value = "";
    allOption.textContent = "All Courses";
    courseFilter.appendChild(allOption);

    courses.forEach(course => {
        const option = document.createElement("option");
        option.value = course;
        option.textContent = course;
        courseFilter.appendChild(option);
    });

    if (courses.includes(selectedCourse)) {
        courseFilter.value = selectedCourse;
    }

    return result;
}

// Render student records safely
function renderStudents(students) {
    currentStudents = students;
    studentCount.textContent = students.length;
    studentList.replaceChildren();

    if (students.length === 0) {
        const row = document.createElement("tr");
        const cell = document.createElement("td");

        cell.colSpan = 8;
        cell.textContent = "No students found.";

        row.appendChild(cell);
        studentList.appendChild(row);
        return;
    }

    students.forEach(student => {
        const row = document.createElement("tr");

        const values = [
            student.student_id,
            student.full_name,
            student.email,
            student.mobile || "—",
            student.course,
            student.study_year ?? "—",
            student.created_at
        ];

        values.forEach(value => {
            const cell = document.createElement("td");
            cell.textContent = String(value ?? "");
            row.appendChild(cell);
        });

        const actionCell = document.createElement("td");
        const actions = document.createElement("div");
        actions.className = "crud-actions";

        const editButton = document.createElement("button");
        editButton.type = "button";
        editButton.textContent = "Edit";
        editButton.dataset.action = "edit";
        editButton.dataset.id = student.student_id;

        const deleteButton = document.createElement("button");
        deleteButton.type = "button";
        deleteButton.textContent = "Delete";
        deleteButton.dataset.action = "delete";
        deleteButton.dataset.id = student.student_id;

        actions.append(editButton, deleteButton);
        actionCell.appendChild(actions);
        row.appendChild(actionCell);

        studentList.appendChild(row);
    });
}

// Fetch students without reloading the page
async function loadStudents() {
    studentList.innerHTML =
        '<tr><td colspan="8">Loading students...</td></tr>';

    try {
        const result = await apiRequest(getListURL());
        renderStudents(result.data);
        csrfToken = result.csrf_token;
    } catch (error) {
        studentList.replaceChildren();

        const row = document.createElement("tr");
        const cell = document.createElement("td");

        cell.colSpan = 8;
        cell.textContent = error.message;

        row.appendChild(cell);
        studentList.appendChild(row);

        showMessage(error.message, "error");
    }
}

// Reset form after add or cancel
function resetStudentForm() {
    studentForm.reset();
    editingId = null;

    formHeading.textContent = "Add New Student";
    saveButton.textContent = "Add Student";
    cancelEditButton.hidden = true;
}

// Submit form using POST or PUT
studentForm.addEventListener("submit", async function (event) {
    event.preventDefault();

    const student = {
        full_name: document.getElementById("full_name").value.trim(),
        email: document.getElementById("email").value.trim(),
        mobile: document.getElementById("mobile").value.trim(),
        course: document.getElementById("course").value.trim(),
        study_year: document.getElementById("study_year").value
    };

    const isEditing = editingId !== null;

    if (isEditing) {
        student.student_id = editingId;
    }

    try {
        saveButton.disabled = true;
        saveButton.textContent = "Saving...";

        const result = await apiRequest(API_URL, {
            method: isEditing ? "PUT" : "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": csrfToken
            },
            body: JSON.stringify(student)
        });

        showMessage(result.message);
        resetStudentForm();

        // Refresh course choices and rows without a page reload
        await loadCourses();
        await loadStudents();

    } catch (error) {
        showMessage(error.message, "error");
    } finally {
        saveButton.disabled = false;
        saveButton.textContent =
            editingId !== null ? "Update Student" : "Add Student";
    }
});

// Handle Edit and Delete actions
studentList.addEventListener("click", async function (event) {
    const button = event.target.closest("button[data-action]");

    if (!button) {
        return;
    }

    const studentId = Number(button.dataset.id);

    const student = currentStudents.find(
        item => Number(item.student_id) === studentId
    );

    if (!student) {
        showMessage("Student record not found. Refreshing list.", "error");
        await loadStudents();
        return;
    }

    if (button.dataset.action === "edit") {
        editingId = studentId;

        document.getElementById("full_name").value =
            student.full_name || "";

        document.getElementById("email").value =
            student.email || "";

        document.getElementById("mobile").value =
            student.mobile || "";

        document.getElementById("course").value =
            student.course || "";

        document.getElementById("study_year").value =
            student.study_year ?? "";

        formHeading.textContent = "Edit Student #" + studentId;
        saveButton.textContent = "Update Student";
        cancelEditButton.hidden = false;

        studentForm.scrollIntoView({
            behavior: "smooth",
            block: "start"
        });

        document.getElementById("full_name").focus();
        return;
    }

    if (button.dataset.action === "delete") {
        const confirmed = confirm(
            "Delete student " + student.full_name + "?"
        );

        if (!confirmed) {
            return;
        }

        try {
            button.disabled = true;

            const result = await apiRequest(API_URL, {
                method: "DELETE",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": csrfToken
                },
                body: JSON.stringify({
                    student_id: studentId
                })
            });

            if (editingId === studentId) {
                resetStudentForm();
            }

            showMessage(result.message);

            await loadCourses();
            await loadStudents();

        } catch (error) {
            showMessage(error.message, "error");
            button.disabled = false;
        }
    }
});

// Search without reloading
document.getElementById("search-form").addEventListener(
    "submit",
    async function (event) {
        event.preventDefault();
        await loadStudents();
    }
);

// Apply course filter immediately
courseFilter.addEventListener("change", loadStudents);

// Reset search and course filter
document.getElementById("reset-filters").addEventListener(
    "click",
    async function () {
        searchInput.value = "";
        courseFilter.value = "";

        await loadStudents();
    }
);

// Cancel editing
cancelEditButton.addEventListener("click", function () {
    resetStudentForm();
    showMessage("Edit cancelled.");
});

// Initialize page
async function initializePage() {
    try {
        await loadCourses();
        await loadStudents();
    } catch (error) {
        showMessage(
            "Unable to initialize student management: " + error.message,
            "error"
        );
    }
}

initializePage();
</script>

</body>
</html>
