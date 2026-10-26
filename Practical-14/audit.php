
<?php
function logAudit(
    mysqli $conn,
    string $action,
    ?string $entityType = null,
    ?int $entityId = null,
    ?string $details = null
): void {

    try {
        $userId = isset($_SESSION["user_id"])
            ? (int) $_SESSION["user_id"]
            : null;

        $ipAddress = $_SERVER["REMOTE_ADDR"] ?? null;

        $stmt = $conn->prepare(
            "INSERT INTO audit_logs
                (user_id, action, entity_type, entity_id, details, ip_address)
             VALUES (?, ?, ?, ?, ?, ?)"
        );

        $stmt->bind_param(
            "ississ",
            $userId,
            $action,
            $entityType,
            $entityId,
            $details,
            $ipAddress
        );

        $stmt->execute();
        $stmt->close();

    } catch (Throwable $e) {
        // Audit failure should not expose database details to users.
        error_log("Audit log failed: " . $e->getMessage());
    }
}
?>
