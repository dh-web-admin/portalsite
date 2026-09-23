<?php
require_once __DIR__ . '/../session_init.php';
require_once '../config/config.php';
require_once __DIR__ . '/../partials/url.php';

// Bounce back to the login page carrying an error message.
function login_reject(string $message): void {
    $_SESSION['login_error'] = $message;
    $_SESSION['active_form'] = 'login';
    if (function_exists('session_write_close')) {
        @session_write_close();
    }
    header('Location: ' . base_url('/auth/login.php'));
    exit();
}

if(isset($_POST['login'])){
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $MAX_LOGIN_ATTEMPTS = 5;

    // Ensure lockout columns exist for older schemas.
    $col = $conn->query("SHOW COLUMNS FROM users LIKE 'failed_login_attempts'");
    if ($col && $col->num_rows === 0) {
        $conn->query("ALTER TABLE users ADD COLUMN failed_login_attempts INT UNSIGNED NOT NULL DEFAULT 0");
    }
    $col = $conn->query("SHOW COLUMNS FROM users LIKE 'locked_at'");
    if ($col && $col->num_rows === 0) {
        $conn->query("ALTER TABLE users ADD COLUMN locked_at DATETIME NULL DEFAULT NULL");
    }

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if($result && $result->num_rows > 0){
        $user = $result->fetch_assoc();

        // Locked accounts are rejected outright, even with the right password —
        // that's the point of a lockout. Only a password reset clears it.
        if (!empty($user['locked_at'])) {
            login_reject('This account has been locked after too many failed login attempts. Reset your password to regain access.');
        }

        if(password_verify($password, $user['password'])){
            // Successful login clears any accumulated failed attempts.
            if ((int)($user['failed_login_attempts'] ?? 0) !== 0) {
                $resetStmt = $conn->prepare("UPDATE users SET failed_login_attempts = 0 WHERE email = ?");
                $resetStmt->bind_param("s", $email);
                $resetStmt->execute();
                $resetStmt->close();
            }

            // Strengthen session handling to persist reliably on Railway
            // Regenerate session ID to prevent fixation and force cookie set
            if (function_exists('session_regenerate_id')) {
                @session_regenerate_id(true);
            }

            $_SESSION['name'] = $user['name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'] ?? null;
            // Ensure user_id is available in session for API authorization checks
            if (isset($user['id'])) {
                $_SESSION['user_id'] = intval($user['id']);
            }

            // Page this visitor was originally headed for before being sent
            // to the login screen. Read (and cleared) BEFORE session_write_close(),
            // or the one-shot clear wouldn't be persisted.
            $intendedUrl = take_intended_url();

            // Ensure session data is written before redirect
            if (function_exists('session_write_close')) {
                @session_write_close();
            }

            // Back to whatever they were trying to reach; otherwise developers
            // go to the Dev Dashboard and everyone else to the main dashboard,
            // using base_url for environment compatibility
            if ($intendedUrl !== null) {
                header('Location: ' . $intendedUrl);
            } elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'developer') {
                header('Location: ' . base_url('/dev/index.php'));
            } else {
                header('Location: ' . base_url('/pages/dashboard/'));
            }
            exit();
        }

        // Wrong password — count the failed attempt, locking the account once
        // it reaches the limit.
        $newCount = (int)($user['failed_login_attempts'] ?? 0) + 1;
        if ($newCount >= $MAX_LOGIN_ATTEMPTS) {
            $upd = $conn->prepare("UPDATE users SET failed_login_attempts = ?, locked_at = NOW() WHERE email = ?");
            $upd->bind_param("is", $newCount, $email);
            $upd->execute();
            $upd->close();
            login_reject('This account has been locked after too many failed login attempts. Reset your password to regain access.');
        }

        $upd = $conn->prepare("UPDATE users SET failed_login_attempts = ? WHERE email = ?");
        $upd->bind_param("is", $newCount, $email);
        $upd->execute();
        $upd->close();
    }

    // Generic message — do not reveal whether the account exists
    login_reject('Invalid email or password.');
}
?>
 
 
