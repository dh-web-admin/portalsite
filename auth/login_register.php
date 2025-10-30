<?php
require_once __DIR__ . '/../session_init.php';
require_once '../config/config.php';

if(isset($_POST['login'])){
    $email = trim($_POST['email']);  
    $password = $_POST['password'];
    $rememberMe = isset($_POST['remember_me']);
   
    $result = $conn->query("SELECT * FROM users WHERE email='$email'");
   
    if($result->num_rows > 0){
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
            
            // Issue Remember Me token unconditionally to persist login across browser restarts
            // Generate secure random token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 43200); // 12 hours
            
            // Store token in database
            $stmt = $conn->prepare("UPDATE users SET remember_token = ?, remember_token_expires = ? WHERE email = ?");
            $stmt->bind_param("sss", $token, $expires, $email);
            $stmt->execute();
            $stmt->close();
            
            // Set cookie
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
            
            setcookie('remember_token', $token, [
                'expires' => time() + 43200, // 12 hours
                'path' => '/',
                'domain' => '',
                'secure' => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);

            // Ensure session data is written before redirect
            if (function_exists('session_write_close')) {
                @session_write_close();
            }

            // All users go to the same dashboard
            header("Location: ../pages/dashboard.php");
            exit();
        }
    }
   
    // Check if email exists at all
    if($result->num_rows === 0){
        $_SESSION['login_error'] = 'Email address not found.';
    } else {
        $_SESSION['login_error'] = 'Invalid password.';
    }
    
    $_SESSION['active_form'] = 'login';
    // Persist error session data before redirect
    if (function_exists('session_write_close')) {
        @session_write_close();
    }
    header("Location: login.php");
    exit();
}
?>
 
 
