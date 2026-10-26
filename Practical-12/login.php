<?php
session_start();

require_once __DIR__ . "/db.php";

/*
|--------------------------------------------------------------------------
| Remember Me cookie helpers
|--------------------------------------------------------------------------
*/

function rememberCookieOptions(int $expires): array
{
    return [
        "expires" => $expires,
        "path" => "/",
        "secure" => !empty($_SERVER["HTTPS"])
                    && $_SERVER["HTTPS"] !== "off",
        "httponly" => true,
        "samesite" => "Lax"
    ];
}

function clearRememberCookie(): void
{
    setcookie(
        "studenthub_remember",
        "",
        rememberCookieOptions(time() - 3600)
    );
}

function revokeRememberToken(mysqli $conn): void
{
    if (!isset($_COOKIE["studenthub_remember"])) {
        clearRememberCookie();
        return;
    }

    $parts = explode(
        ":",
        $_COOKIE["studenthub_remember"],
        2
    );

    $selector = $parts[0] ?? "";

    if (preg_match('/^[a-f0-9]{24}$/', $selector)) {
        try {
            $stmt = $conn->prepare(
                "DELETE FROM remember_tokens WHERE selector = ?"
            );

            $stmt->bind_param("s", $selector);
            $stmt->execute();
            $stmt->close();

        } catch (Throwable $e) {
            error_log($e->getMessage());
        }
    }

    clearRememberCookie();
}

function redirectByRole(string $role): void
{
    if ($role === "admin") {
        header("Location: admin_dashboard.php");
    } else {
        header("Location: dashboard.php");
    }

    exit;
}

/*
|--------------------------------------------------------------------------
| Restore login using Remember Me cookie
|--------------------------------------------------------------------------
*/

$sessionTimedOut = isset($_GET["timeout"])
                   && $_GET["timeout"] === "1";

if (
    !isset($_SESSION["user_id"]) &&
    !$sessionTimedOut &&
    isset($_COOKIE["studenthub_remember"])
) {
    $parts = explode(
        ":",
        $_COOKIE["studenthub_remember"],
        2
    );

    $selector = $parts[0] ?? "";
    $validator = $parts[1] ?? "";

    if (
        preg_match('/^[a-f0-9]{24}$/', $selector) &&
        preg_match('/^[a-f0-9]{64}$/', $validator)
    ) {
        try {
            $stmt = $conn->prepare(
                "SELECT
                    rt.token_id,
                    rt.user_id,
                    rt.token_hash,
                    rt.expires_at,
                    u.full_name,
                    u.email,
                    u.role
                 FROM remember_tokens rt
                 INNER JOIN users u
                    ON u.user_id = rt.user_id
                 WHERE rt.selector = ?
                 LIMIT 1"
            );

            $stmt->bind_param("s", $selector);
            $stmt->execute();

            $result = $stmt->get_result();
            $rememberUser = $result->fetch_assoc();

            $stmt->close();

            $tokenIsValid = (
                $rememberUser !== null &&
                strtotime($rememberUser["expires_at"]) > time() &&
                hash_equals(
                    $rememberUser["token_hash"],
                    hash("sha256", $validator)
                )
            );

            if ($tokenIsValid) {
                // Rotate the token after successful use.
                $newSelector = bin2hex(random_bytes(12));
                $newValidator = bin2hex(random_bytes(32));
                $newHash = hash("sha256", $newValidator);

                // Keep the original expiry time.
                $expiresAt = $rememberUser["expires_at"];

                $update = $conn->prepare(
                    "UPDATE remember_tokens
                     SET selector = ?,
                         token_hash = ?
                     WHERE token_id = ?"
                );

                $update->bind_param(
                    "ssi",
                    $newSelector,
                    $newHash,
                    $rememberUser["token_id"]
                );

                $update->execute();
                $update->close();

                setcookie(
                    "studenthub_remember",
                    $newSelector . ":" . $newValidator,
                    rememberCookieOptions(
                        strtotime($expiresAt)
                    )
                );

                session_regenerate_id(true);

                $_SESSION["user_id"] = $rememberUser["user_id"];
                $_SESSION["full_name"] = $rememberUser["full_name"];
                $_SESSION["email"] = $rememberUser["email"];
                $_SESSION["role"] = $rememberUser["role"];
                $_SESSION["last_activity"] = time();

                redirectByRole($rememberUser["role"]);

            } elseif ($rememberUser !== null) {
                // Remove expired or invalid token.
                $delete = $conn->prepare(
                    "DELETE FROM remember_tokens WHERE token_id = ?"
                );

                $delete->bind_param(
                    "i",
                    $rememberUser["token_id"]
                );

                $delete->execute();
                $delete->close();

                clearRememberCookie();

            } else {
                clearRememberCookie();
            }

        } catch (Throwable $e) {
            error_log($e->getMessage());
            // Continue to the login form if restoration fails.
        }

    } else {
        clearRememberCookie();
    }
}

/*
|--------------------------------------------------------------------------
| Redirect users who already have an active session
|--------------------------------------------------------------------------
*/

if (isset($_SESSION["user_id"])) {
    redirectByRole($_SESSION["role"]);
}

$email = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $rememberMe = isset($_POST["remember_me"])
                  && $_POST["remember_me"] === "1";

    if ($email === "" || $password === "") {
        $error = "Please enter both email and password.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";

    } else {
        try {
            // Fetch the registered account.
            $stmt = $conn->prepare(
                "SELECT user_id, full_name, email,
                        password_hash, role
                 FROM users
                 WHERE email = ?
                 LIMIT 1"
            );

            $stmt->bind_param("s", $email);
            $stmt->execute();

            $result = $stmt->get_result();
            $user = $result->fetch_assoc();

            $stmt->close();

            if (
                $user !== null &&
                password_verify(
                    $password,
                    $user["password_hash"]
                )
            ) {
                /*
                 * Create a persistent token only when
                 * Remember Me is selected.
                 */
                if ($rememberMe) {
                    // Revoke the previous browser token, if any.
                    revokeRememberToken($conn);

                    $selector = bin2hex(random_bytes(12));
                    $validator = bin2hex(random_bytes(32));
                    $tokenHash = hash("sha256", $validator);

                    $expiresAt = date(
                        "Y-m-d H:i:s",
                        time() + (30 * 24 * 60 * 60)
                    );

                    $insert = $conn->prepare(
                        "INSERT INTO remember_tokens
                         (user_id, selector, token_hash, expires_at)
                         VALUES (?, ?, ?, ?)"
                    );

                    $insert->bind_param(
                        "isss",
                        $user["user_id"],
                        $selector,
                        $tokenHash,
                        $expiresAt
                    );

                    $insert->execute();
                    $insert->close();

                    // Only the browser receives the raw validator.
                    setcookie(
                        "studenthub_remember",
                        $selector . ":" . $validator,
                        rememberCookieOptions(
                            time() + (30 * 24 * 60 * 60)
                        )
                    );

                } else {
                    // Do not retain an old remember-me token
                    // when the option is unchecked.
                    revokeRememberToken($conn);
                }

                // Prevent session fixation.
                session_regenerate_id(true);

                $_SESSION["user_id"] = $user["user_id"];
                $_SESSION["full_name"] = $user["full_name"];
                $_SESSION["email"] = $user["email"];
                $_SESSION["role"] = $user["role"];

                // Session inactivity timeout: 15 minutes.
                $_SESSION["last_activity"] = time();

                redirectByRole($user["role"]);

            } else {
                $error = "Invalid email or password.";
            }

        } catch (Throwable $e) {
            error_log($e->getMessage());
            $error = "Login could not be completed. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>StudentHub Login</title>

    <link rel="stylesheet" href="style.css">

    <style>
        .login-box {
            max-width: 500px;
            margin: 25px auto;
            padding: 25px;
            background: rgba(0, 0, 0, 0.45);
            border-radius: 12px;
        }

        .login-box label {
            display: block;
            margin-bottom: 6px;
            font-weight: bold;
        }

        .login-box input {
            width: 100%;
            padding: 11px;
            margin-bottom: 16px;
            border-radius: 5px;
            border: 1px solid #ccc;
            font-size: 16px;
        }

        .login-box .remember-row label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: normal;
            margin-bottom: 16px;
        }

        .login-box input[type="checkbox"] {
            width: auto;
            margin: 0;
            padding: 0;
            flex-shrink: 0;
        }

        .login-box button {
            width: 100%;
            margin: 8px 0;
        }

        .message {
            padding: 12px;
            margin: 15px 0;
            border-radius: 6px;
            background: #fee2e2;
            color: #991b1b;
            overflow-wrap: anywhere;
        }

        .notice {
            padding: 12px;
            margin: 15px 0;
            border-radius: 6px;
            background: #dbeafe;
            color: #1e3a8a;
        }

        body.light-theme .login-box {
            background: rgba(255, 255, 255, 0.9);
            color: #1f2937;
        }
    </style>
</head>

<body>

<header>
    <h1>STUDENT PORTAL</h1>
</header>

<hr>

<nav aria-label="Main navigation">
    <a href="login.php" aria-current="page">Login</a>
</nav>

<hr>

<main class="container">

    <h2>User Login</h2>
    <p>Login to access your StudentHub account.</p>

    <section class="login-box">

        <?php if (isset($_GET["timeout"])): ?>
            <div class="notice" role="status">
                Your session expired. Please log in again.
            </div>
        <?php endif; ?>

        <?php if (isset($_GET["logout"])): ?>
            <div class="notice" role="status">
                You have been logged out successfully.
            </div>
        <?php endif; ?>

        <?php if ($error !== ""): ?>
            <div class="message" role="alert">
                <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    "UTF-8"
                ) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">

            <label for="email">Email Address</label>

            <input
                type="email"
                id="email"
                name="email"
                value="<?= htmlspecialchars(
                    $email,
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    "UTF-8"
                ) ?>"
                autocomplete="username"
                required>

            <label for="password">Password</label>

            <input
                type="password"
                id="password"
                name="password"
                autocomplete="current-password"
                required>

            <div class="remember-row">
                <label for="remember_me">
                    <input
                        type="checkbox"
                        id="remember_me"
                        name="remember_me"
                        value="1">
                    Remember Me (30 days)
                </label>
            </div>

            <button type="submit">Login</button>

        </form>

        <p>Use your registered StudentHub account.</p>

    </section>

</main>

</body>
</html>