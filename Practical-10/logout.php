<?php
session_start();

require_once __DIR__ . "/db.php";

// Revoke the Remember Me token for this browser
if (isset($_COOKIE["studenthub_remember"])) {

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
}

// Clear all session data
$_SESSION = [];

// Delete PHP session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        "",
        [
            "expires" => time() - 3600,
            "path" => $params["path"],
            "domain" => $params["domain"],
            "secure" => $params["secure"],
            "httponly" => $params["httponly"],
            "samesite" => $params["samesite"] ?? "Lax"
        ]
    );
}

// Delete Remember Me cookie
setcookie(
    "studenthub_remember",
    "",
    [
        "expires" => time() - 3600,
        "path" => "/",
        "secure" => !empty($_SERVER["HTTPS"])
                    && $_SERVER["HTTPS"] !== "off",
        "httponly" => true,
        "samesite" => "Lax"
    ]
);

session_destroy();

header("Location: login.php?logout=1");
exit;