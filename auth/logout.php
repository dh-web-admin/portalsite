<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../partials/url.php';
require_once __DIR__ . '/../config/config.php';

// Clear remember token from database if user is logged in
if (isset($_SESSION['email'])) {
    $stmt = $conn->prepare("UPDATE users SET remember_token = NULL, remember_token_expires = NULL WHERE email = ?");
    $stmt->bind_param("s", $_SESSION['email']);
    $stmt->execute();
    $stmt->close();
}

// Unset all session variables
$_SESSION = [];

// If session uses cookies, invalidate the cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], isset($params['httponly']) ? $params['httponly'] : false
    );
}

// Clear remember me cookie
if (isset($_COOKIE['remember_token'])) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    
    setcookie('remember_token', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

// Destroy the session
session_destroy();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logged Out</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(base_url('/assets/css/base.css')); ?>">
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            font-family: Arial, sans-serif;
        }
        .logout-container {
            background: white;
            padding: 3rem;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            text-align: center;
            max-width: 500px;
            width: 90%;
        }
        .logout-container h1 {
            color: #333;
            margin-bottom: 1rem;
            font-size: 2rem;
        }
        .logout-container p {
            color: #666;
            margin-bottom: 2rem;
            font-size: 1.1rem;
        }
        .button-group {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .btn {
            display: inline-block;
            padding: 1rem 2rem;
            text-decoration: none;
            border-radius: 5px;
            font-size: 1rem;
            font-weight: bold;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .btn-secondary {
            background: #48bb78;
            color: white;
        }
        .btn-secondary:hover {
            background: #38a169;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(72, 187, 120, 0.4);
        }
        .checkmark {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            border-radius: 50%;
            background: #48bb78;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .checkmark svg {
            width: 50px;
            height: 50px;
            fill: white;
        }
    </style>
</head>
<body>
    <div class="logout-container">
        <div class="checkmark">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/>
            </svg>
        </div>
        <h1>You Are Now Logged Out</h1>
        <p>You have been successfully logged out.</p>
        <div class="button-group">
            <a href="<?php echo htmlspecialchars(base_url('/auth/login.php')); ?>" class="btn btn-primary">
                Return to Login Page
            </a>
            <a href="https://darkhorsestabilization.com/" class="btn btn-secondary">
                Visit Dark Horse Stabilization
            </a>
        </div>
    </div>
</body>
</html>
