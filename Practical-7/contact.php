
<?php
$name = "";
$email = "";
$mobile = "";
$messageText = "";

$errors = [];
$successMessage = "";
$storageError = "";

/* Escape output before displaying it in HTML */
function escapeHtml(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8"
    );
}

/* Reduce CSV formula-injection risk */
function safeCsvValue(string $value): string
{
    if (preg_match('/^[\t\r=+\-@]/', $value) === 1) {
        return "'" . $value;
    }

    return $value;
}

/* Process only form submissions */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Read and trim submitted values
    $name = trim((string)($_POST["name"] ?? ""));
    $email = trim((string)($_POST["email"] ?? ""));
    $mobile = trim((string)($_POST["mobile"] ?? ""));
    $messageText = trim((string)($_POST["message"] ?? ""));

    // Validate name
    if ($name === "") {
        $errors["name"] = "Full name is required.";
    } elseif (!preg_match("/^[\p{L}][\p{L} .'-]{1,79}$/u", $name)) {
        $errors["name"] = "Enter a valid name with at least 2 letters.";
    }

    // Validate email
    if ($email === "") {
        $errors["email"] = "Email address is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors["email"] = "Enter a valid email address.";
    }

    // Validate Indian mobile number
    if ($mobile === "") {
        $errors["mobile"] = "Mobile number is required.";
    } elseif (!preg_match("/^[6-9][0-9]{9}$/", $mobile)) {
        $errors["mobile"] =
            "Enter a valid 10-digit Indian mobile number.";
    }

    // Validate message
    if ($messageText === "") {
        $errors["message"] = "Please enter your message.";
    } elseif (strlen($messageText) > 5000) {
        $errors["message"] = "Message must not exceed 5000 bytes.";
    }

    // Store data only when all fields are valid
    if (empty($errors)) {

        $csvFile = __DIR__ . "/contact_submissions.csv";
        $fileHandle = fopen($csvFile, "a");

        if ($fileHandle === false) {
            $storageError =
                "Unable to open the CSV file. Check folder permissions.";
        } elseif (!flock($fileHandle, LOCK_EX)) {

            fclose($fileHandle);

            $storageError =
                "Unable to lock the CSV file. Please try again.";

        } else {

            $fileInfo = fstat($fileHandle);

            // Add column headings when the file is empty
            if ($fileInfo !== false && $fileInfo["size"] === 0) {
                fputcsv($fileHandle, [
                    "Submitted At",
                    "Name",
                    "Email",
                    "Mobile",
                    "Message"
                ]);
            }

            // Write a new record
            $saved = fputcsv($fileHandle, [
                date("Y-m-d H:i:s"),
                safeCsvValue($name),
                safeCsvValue($email),
                safeCsvValue($mobile),
                safeCsvValue($messageText)
            ]);

            fflush($fileHandle);
            flock($fileHandle, LOCK_UN);
            fclose($fileHandle);

            if ($saved === false) {
                $storageError =
                    "The record could not be saved. Please try again.";
            } else {
                $successMessage =
                    "Success! Your contact form has been saved.";

                // Clear submitted values after successful storage
                $name = "";
                $email = "";
                $mobile = "";
                $messageText = "";
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

    <title>Contact Form | StudentHub</title>

    <link rel="stylesheet" href="style.css">

    <style>
        .server-message {
            padding: 14px;
            margin: 16px 0;
            border-radius: 8px;
            line-height: 1.6;
            overflow-wrap: anywhere;
        }

        .server-success {
            color: #166534;
            background: #dcfce7;
            border: 1px solid #16a34a;
        }

        .server-error {
            color: #991b1b;
            background: #fee2e2;
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

        .contact-form .form-group {
            margin-bottom: 16px;
        }

        .contact-form label {
            display: block;
            margin-bottom: 6px;
            font-weight: bold;
        }

        .contact-form input,
        .contact-form textarea {
            width: 100%;
            padding: 10px;
            border-radius: 5px;
            font: inherit;
        }

        .contact-form textarea {
            resize: vertical;
        }

        .contact-form [aria-invalid="true"] {
            border: 2px solid #f87171;
        }

        body.light-theme .field-error {
            color: #b91c1c;
        }
    </style>
</head>

<body>

    <header>
        <h1>STUDENT PORTAL</h1>
    </header>

    <hr>

    <nav aria-label="Main navigation">
        <a href="index.html">Home</a>
        <a href="registration.html">Registration</a>
        <a href="contact.php" aria-current="page">Contact</a>
    </nav>

    <hr>

    <main class="container">

        <h2>Contact StudentHub</h2>

        <p>
            Complete the form below. The server will validate your
            details and store valid submissions in a CSV file.
        </p>

        <?php if ($successMessage !== ""): ?>
            <div class="server-message server-success" role="status">
                <?= escapeHtml($successMessage) ?>
            </div>
        <?php endif; ?>

        <?php if ($storageError !== ""): ?>
            <div class="server-message server-error" role="alert">
                <?= escapeHtml($storageError) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="server-message server-error" role="alert">
                Please correct the errors shown below and submit again.
            </div>
        <?php endif; ?>

        <form class="contact-form" method="POST" action="contact.php"
              novalidate>

            <div class="form-group">
                <label for="name">Full Name</label>

                <input
                    type="text"
                    id="name"
                    name="name"
                    value="<?= escapeHtml($name) ?>"
                    autocomplete="name"
                    required
                    aria-invalid="<?= isset($errors['name']) ? 'true' : 'false' ?>"
                    aria-describedby="name-error">

                <small id="name-error" class="field-error" aria-live="polite">
                    <?= escapeHtml($errors["name"] ?? "") ?>
                </small>
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= escapeHtml($email) ?>"
                    autocomplete="email"
                    required
                    aria-invalid="<?= isset($errors['email']) ? 'true' : 'false' ?>"
                    aria-describedby="email-error">

                <small id="email-error" class="field-error" aria-live="polite">
                    <?= escapeHtml($errors["email"] ?? "") ?>
                </small>
            </div>

            <div class="form-group">
                <label for="mobile">Mobile Number</label>

                <input
                    type="tel"
                    id="mobile"
                    name="mobile"
                    value="<?= escapeHtml($mobile) ?>"
                    inputmode="numeric"
                    autocomplete="tel"
                    required
                    aria-invalid="<?= isset($errors['mobile']) ? 'true' : 'false' ?>"
                    aria-describedby="mobile-error">

                <small id="mobile-error" class="field-error" aria-live="polite">
                    <?= escapeHtml($errors["mobile"] ?? "") ?>
                </small>
            </div>

            <div class="form-group">
                <label for="message">Your Message</label>

                <textarea
                    id="message"
                    name="message"
                    rows="5"
                    maxlength="5000"
                    required
                    aria-invalid="<?= isset($errors['message']) ? 'true' : 'false' ?>"
                    aria-describedby="message-error"><?= escapeHtml($messageText) ?></textarea>

                <small id="message-error" class="field-error" aria-live="polite">
                    <?= escapeHtml($errors["message"] ?? "") ?>
                </small>
            </div>

            <button type="submit">Submit Contact Form</button>
            <button type="reset">Clear Form</button>

        </form>

    </main>

</body>
</html>
