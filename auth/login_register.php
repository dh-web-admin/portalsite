<?php
require_once __DIR__ . '/../session_init.php';
require_once '../config/config.php';
require_once __DIR__ . '/../partials/url.php';

if(isset($_POST['login'])){
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $rememberMe = isset($_POST['remember_me']);

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if($result && $result->num_rows > 0){
        $user = $result->fetch_assoc();
       
        // // DEBUGGING - to check if user is found and password verification
        // echo "User found: " . $user['email'] . "<br>";
        // echo "Role: " . $user['role'] . "<br>";
        // echo "Password verify: " . (password_verify($password, $user['password']) ? 'SUCCESS' : 'FAILED') . "<br>";
        // die(); // Stop here to see the output
       
    if(password_verify($password, $user['password'])){
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
            
            // Issue Remember Me token unconditionally to persist login across browser restarts
            // Generate secure random token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 86400); // 24 hours
            
            // Store token in database
            $stmt = $conn->prepare("UPDATE users SET remember_token = ?, remember_token_expires = ? WHERE email = ?");
            $stmt->bind_param("sss", $token, $expires, $email);
            $stmt->execute();
            $stmt->close();
            
            // Set cookie
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
            
            setcookie('remember_token', $token, [
                'expires' => time() + 86400, // 24 hours
                'path' => '/',
                'domain' => '',
                'secure' => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);

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
    }
   
    // Generic message — do not reveal whether the account exists
    $_SESSION['login_error'] = 'Invalid email or password.';

    $_SESSION['active_form'] = 'login';
    // Persist error session data before redirect
    if (function_exists('session_write_close')) {
        @session_write_close();
    }
    header('Location: ' . base_url('/auth/login.php'));
    exit();
}
?>
 
 
