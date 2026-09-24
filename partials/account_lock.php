<?php
/**
 * Account lockout helpers.
 *
 * Two different things can lock an account, and they are NOT equivalent:
 *
 *   'failed_attempts' - a run of bad sign-ins (auth/login_register.php).
 *                       The owner can clear it themselves by completing a
 *                       password reset.
 *   'admin'           - an administrator locked it deliberately from the user
 *                       list. A password reset must NOT clear this one, or
 *                       the person being locked out could simply reset their
 *                       way back in. Only an admin can lift it.
 *
 * An admin unlock clears either kind, and never touches the password.
 */

if (!defined('ACCOUNT_LOCK_MAX_ATTEMPTS')) {
    define('ACCOUNT_LOCK_MAX_ATTEMPTS', 5);
}

const ACCOUNT_LOCK_REASON_ATTEMPTS = 'failed_attempts';
const ACCOUNT_LOCK_REASON_ADMIN = 'admin';

/** Add the lockout columns on demand for databases that predate them. */
function account_lock_ensure_columns($conn): void {
    static $done = false;
    if ($done || !$conn) return;

    try {
        $col = $conn->query("SHOW COLUMNS FROM users LIKE 'failed_login_attempts'");
        if ($col && $col->num_rows === 0) {
            $conn->query('ALTER TABLE users ADD COLUMN failed_login_attempts INT UNSIGNED NOT NULL DEFAULT 0');
        }
        $col = $conn->query("SHOW COLUMNS FROM users LIKE 'locked_at'");
        if ($col && $col->num_rows === 0) {
            $conn->query('ALTER TABLE users ADD COLUMN locked_at DATETIME NULL DEFAULT NULL');
        }
        $col = $conn->query("SHOW COLUMNS FROM users LIKE 'lock_reason'");
        if ($col && $col->num_rows === 0) {
            $conn->query("ALTER TABLE users ADD COLUMN lock_reason VARCHAR(32) NULL DEFAULT NULL");
            // Anything already locked before this column existed came from the
            // failed-attempt counter, which is the only lock that existed then.
            $conn->query("UPDATE users SET lock_reason = 'failed_attempts' WHERE locked_at IS NOT NULL AND lock_reason IS NULL");
        }
        $done = true;
    } catch (Throwable $e) {
        error_log('account_lock_ensure_columns: ' . $e->getMessage());
    }
}

/** Lock an account. $reason is one of the ACCOUNT_LOCK_REASON_* constants. */
function account_lock_set_by_id($conn, int $userId, string $reason): bool {
    if (!$conn || $userId <= 0) return false;
    account_lock_ensure_columns($conn);

    $stmt = $conn->prepare('UPDATE users SET locked_at = NOW(), lock_reason = ? WHERE id = ?');
    if (!$stmt) return false;
    $stmt->bind_param('si', $reason, $userId);
    $ok = $stmt->execute();
    $stmt->close();
    return (bool)$ok;
}

/**
 * Clear a lock and the failed-attempt counter. Never touches the password.
 * Admin-initiated: clears an admin lock too.
 */
function account_lock_clear_by_id($conn, int $userId): bool {
    if (!$conn || $userId <= 0) return false;
    account_lock_ensure_columns($conn);

    $stmt = $conn->prepare('UPDATE users SET failed_login_attempts = 0, locked_at = NULL, lock_reason = NULL WHERE id = ?');
    if (!$stmt) return false;
    $stmt->bind_param('i', $userId);
    $ok = $stmt->execute();
    $stmt->close();
    return (bool)$ok;
}

/**
 * Used by the password reset flow. Resets the attempt counter always, but only
 * lifts the lock when it came from failed attempts — a person an admin locked
 * out must not be able to reset their way back in.
 */
function account_lock_clear_after_reset($conn, string $email): bool {
    if (!$conn || $email === '') return false;
    account_lock_ensure_columns($conn);

    $stmt = $conn->prepare(
        "UPDATE users
            SET failed_login_attempts = 0,
                locked_at   = CASE WHEN lock_reason = 'admin' THEN locked_at ELSE NULL END,
                lock_reason = CASE WHEN lock_reason = 'admin' THEN lock_reason ELSE NULL END
          WHERE email = ?"
    );
    if (!$stmt) return false;
    $stmt->bind_param('s', $email);
    $ok = $stmt->execute();
    $stmt->close();
    return (bool)$ok;
}

/** Row lookup used by the sign-in path and the session gate. */
function account_lock_status($conn, string $email): ?array {
    if (!$conn || $email === '') return null;

    $stmt = @$conn->prepare('SELECT locked_at, lock_reason FROM users WHERE email = ? LIMIT 1');
    if (!$stmt) return null;
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if (!$row) return null;

    return [
        'locked' => !empty($row['locked_at']),
        'reason' => (string)($row['lock_reason'] ?? ''),
    ];
}

/** What to tell someone at the sign-in screen about why they are locked out. */
function account_lock_message(string $reason): string {
    if ($reason === ACCOUNT_LOCK_REASON_ADMIN) {
        return 'This account has been locked by an administrator. Please contact your administrator to regain access.';
    }
    return 'This account has been locked after too many failed login attempts. Reset your password to regain access.';
}
