<?php
require_once __DIR__ . "/db.php";

$errors = [];
$successMessage = "";
$storageError = "";

$formData = [
    "full_name" => "",
    "username" => "",
    "email" => "",
    "mobile" => ""
];

function escapeHtml(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8"
    );
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Read submitted values
    $formData["full_name"] = trim($_POST["full_name"] ?? "");
    $formData["username"] = trim($_POST["username"] ?? "");
    $formData["email"] = trim($_POST["email"] ?? "");
    $formData["mobile"] = trim($_POST["mobile"] ?? "");

    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    // Validate full name
    if ($formData["full_name"] === "") {
        $errors["full_name"] = "Full name is required.";
    } elseif (
        !preg_match(
            "/^[\p{L}][\p{L} .'-]{1,99}$/u",
            $formData["full_name"]
        )
    ) {
        $errors["full_name"] =
            "Enter a valid name with at least 2 characters.";
    }

    // Validate username
    if ($formData["username"] === "") {
        $errors["username"] = "Username is required.";
    } elseif (
        !preg_match(
            "/^[A-Za-z0-9_.]{3,50}$/",
            $formData["username"]
        )
    ) {
        $errors["username"] =
            "Username must contain 3-50 letters, numbers, dots or underscores.";
    }

    // Validate email
    if ($formData["email"] === "") {
        $errors["email"] = "Email address is required.";
    } elseif (
        !filter_var($formData["email"], FILTER_VALIDATE_EMAIL)
    ) {
        $errors["email"] = "Enter a valid email address.";
    } elseif (strlen($formData["email"]) > 150) {
        $errors["email"] = "Email must not exceed 150 characters.";
    }

    // Validate Indian mobile number
    if ($formData["mobile"] === "") {
        $errors["mobile"] = "Mobile number is required.";
    } elseif (
        !preg_match("/^[6-9][0-9]{9}$/", $formData["mobile"])
    ) {
        $errors["mobile"] =
            "Enter a valid 10-digit Indian mobile number.";
    }

    // Validate password
    if ($password === "") {
        $errors["password"] = "Password is required.";
    } elseif (strlen($password) < 8) {
        $errors["password"] =
            "Password must contain at least 8 characters.";
    }

    // Validate confirm password
    if ($confirmPassword === "") {
        $errors["confirm_password"] =
            "Please confirm your password.";
    } elseif ($password !== $confirmPassword) {
        $errors["confirm_password"] =
            "Passwords do not match.";
    }

    // Insert data only when all validations pass
    if (empty($errors)) {

        try {
            // Check for duplicate username or email
            $check = $conn->prepare(
                "SELECT username, email
                 FROM users
                 WHERE username = ? OR email = ?
                 LIMIT 1"
            );

            $check->bind_param(
                "ss",
                $formData["username"],
                $formData["email"]
            );

            $check->execute();
            $result = $check->get_result();
            $existingUser = $result->fetch_assoc();
            $check->close();

            if ($existingUser !== null) {

                if (
                    strcasecmp(
                        $existingUser["username"],
                        $formData["username"]
                    ) === 0
                ) {
                    $errors["username"] =
                        "This username is already registered.";
                }

                if (
                    strcasecmp(
                        $existingUser["email"],
                        $formData["email"]
                    ) === 0
                ) {
                    $errors["email"] =
                        "This email address is already registered.";
                }

            } else {

                // Hash the password before storing it
                $passwordHash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                // Insert the new user using a prepared statement
                $insert = $conn->prepare(
                    "INSERT INTO users
                    (full_name, username, email, mobile, password_hash)
                    VALUES (?, ?, ?, ?, ?)"
                );

                $insert->bind_param(
                    "sssss",
                    $formData["full_name"],
                    $formData["username"],
                    $formData["email"],
                    $formData["mobile"],
                    $passwordHash
                );

                $insert->execute();
                $insert->close();

                $successMessage =
                    "Registration successful! Your account has been created.";

                // Clear form after successful registration
                $formData = [
                    "full_name" => "",
                    "username" => "",
                    "email" => "",
                    "mobile" => ""
                ];
            }

        } catch (mysqli_sql_exception $e) {

            // Log technical details without exposing them to users
            error_log($e->getMessage());

            if ((int) $e->getCode() === 1062) {
                $storageError =
                    "This username or email is already registered.";
            } else {
                $storageError =
                    "Registration failed. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Secure Registration | StudentHub</title>

    <link rel="stylesheet" href="style.css">

    <style>
        .registration-message {
            margin: 18px 0;
            padding: 14px;
            border-radius: 8px;
            line-height: 1.6;
            overflow-wrap: anywhere;
        }

        .registration-success {
            background-color: #dcfce7;
            color: #166534;
            border: 1px solid #16a34a;
        }

        .registration-error {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #dc2626;
        }

        .field-error {
            display: block;
            margin-top: 5px;
            color: #fecaca;
            font-size: 14px;
            line-height: 1.5;
        }

        .field-error:empty {
            display: none;
        }

        #registration-form [aria-invalid="true"] {
            border: 2px solid #f87171 !important;
        }

        body.light-theme .field-error {
            color: #b91c1c;
        }

        .form-note {
            font-size: 14px;
            text-align: center;
        }
    </style>
</head>

<body>

    <header>
        <h1>STUDENT PORTAL</h1>
    </header>

    <hr>

    <nav aria-label="Main navigation">
        <a href="register.php" aria-current="page">Registration</a>
        <a href="test_db.php">Database Test</a>
    </nav>

    <hr>

    <main class="container">

        <h2>Secure User Registration</h2>

        <p>
            Create your StudentHub account by completing
            the form below.
        </p>

        <?php if ($successMessage !== ""): ?>
            <div class="registration-message registration-success"
                 role="status">
                <?= escapeHtml($successMessage) ?>
            </div>
        <?php endif; ?>

        <?php if ($storageError !== ""): ?>
            <div class="registration-message registration-error"
                 role="alert">
                <?= escapeHtml($storageError) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="registration-message registration-error"
                 role="alert">
                Please correct the errors shown below and submit again.
            </div>
        <?php endif; ?>

        <form id="registration-form"
              method="POST"
              action="register.php">

            <div class="registration-grid">

                <div class="form-group">
                    <label for="full_name">Full Name</label>

                    <input
                        type="text"
                        id="full_name"
                        name="full_name"
                        value="<?= escapeHtml($formData["full_name"]) ?>"
                        minlength="2"
                        maxlength="100"
                        autocomplete="name"
                        required
                        aria-invalid="<?= isset($errors["full_name"]) ? "true" : "false" ?>"
                        aria-describedby="full-name-error">

                    <small id="full-name-error" class="field-error" aria-live="polite"><?= escapeHtml($errors["full_name"] ?? "") ?></small>
                </div>

                <div class="form-group">
                    <label for="username">Username</label>

                    <input
                        type="text"
                        id="username"
                        name="username"
                        value="<?= escapeHtml($formData["username"]) ?>"
                        minlength="3"
                        maxlength="50"
                        pattern="[A-Za-z0-9_.]{3,50}"
                        title="Use 3-50 letters, numbers, dots or underscores."
                        autocomplete="username"
                        required
                        aria-invalid="<?= isset($errors["username"]) ? "true" : "false" ?>"
                        aria-describedby="username-error">

                    <small id="username-error" class="field-error" aria-live="polite"><?= escapeHtml($errors["username"] ?? "") ?></small>
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?= escapeHtml($formData["email"]) ?>"
                        maxlength="150"
                        autocomplete="email"
                        required
                        aria-invalid="<?= isset($errors["email"]) ? "true" : "false" ?>"
                        aria-describedby="email-error">

                    <small id="email-error" class="field-error" aria-live="polite"><?= escapeHtml($errors["email"] ?? "") ?></small>
                </div>

                <div class="form-group">
                    <label for="mobile">Mobile Number</label>

                    <input
                        type="tel"
                        id="mobile"
                        name="mobile"
                        value="<?= escapeHtml($formData["mobile"]) ?>"
                        inputmode="numeric"
                        pattern="[6-9][0-9]{9}"
                        maxlength="10"
                        minlength="10"
                        autocomplete="tel"
                        title="Enter a valid 10-digit Indian mobile number."
                        required
                        aria-invalid="<?= isset($errors["mobile"]) ? "true" : "false" ?>"
                        aria-describedby="mobile-error">

                    <small id="mobile-error" class="field-error" aria-live="polite"><?= escapeHtml($errors["mobile"] ?? "") ?></small>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        minlength="8"
                        autocomplete="new-password"
                        required
                        aria-invalid="<?= isset($errors["password"]) ? "true" : "false" ?>"
                        aria-describedby="password-error">

                    <small id="password-error" class="field-error" aria-live="polite"><?= escapeHtml($errors["password"] ?? "") ?></small>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>

                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        minlength="8"
                        autocomplete="new-password"
                        required
                        aria-invalid="<?= isset($errors["confirm_password"]) ? "true" : "false" ?>"
                        aria-describedby="confirm-password-error">

                    <small id="confirm-password-error" class="field-error" aria-live="polite"><?= escapeHtml($errors["confirm_password"] ?? "") ?></small>
                </div>

                <div class="form-actions full-width">
                    <button type="submit">Create Account</button>
                    <button type="reset">Clear Form</button>
                </div>

            </div>

            <p class="form-note">
                Your password will be securely hashed before storage.
            </p>

        </form>

    </main>

    <script>
        const form = document.getElementById("registration-form");
        const password = document.getElementById("password");
        const confirmPassword =
            document.getElementById("confirm_password");

        function validatePasswordMatch() {
            if (
                confirmPassword.value !== "" &&
                password.value !== confirmPassword.value
            ) {
                confirmPassword.setCustomValidity(
                    "Passwords do not match."
                );
            } else {
                confirmPassword.setCustomValidity("");
            }
        }

        password.addEventListener("input", validatePasswordMatch);
        confirmPassword.addEventListener("input", validatePasswordMatch);

        form.addEventListener("reset", function () {
            setTimeout(function () {
                confirmPassword.setCustomValidity("");
            }, 0);
        });
    </script>

</body>
</html>