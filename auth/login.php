<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../partials/url.php';

// If user is already authenticated, skip login page
if (isset($_SESSION['email']) && isset($_SESSION['name'])) {
  // Ensure role is available in session; fetch if missing
  if (!isset($_SESSION['role'])) {
    $email = $_SESSION['email'];
    if (isset($conn)) {
      if ($stmt = $conn->prepare("SELECT role FROM users WHERE email = ? LIMIT 1")) {
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $row = $result->fetch_assoc()) {
          $_SESSION['role'] = $row['role'];
        }
        $stmt->close();
      }
    }
  }

  if (isset($_SESSION['role']) && $_SESSION['role'] === 'developer') {
    header('Location: ' . base_url('/dev/index.php'));
  } else {
    header('Location: ' . base_url('/pages/dashboard/'));
  }
  exit();
}

// Only capture error + active form, do NOT wipe entire session (was causing logout-on-new-window)
$errors = [
  'login' => $_SESSION['login_error'] ?? ''
]; 
$active_form = $_SESSION['active_form'] ?? 'login';
// Clear transient error flags without destroying auth session data
unset($_SESSION['login_error'], $_SESSION['active_form']);
function showError($error){
  return !empty($error) ? "<p class='error-message'>$error</p>" : '';   
}
function isActiveForm($formName, $activeForm){
  return $formName === $activeForm ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Login</title>
    <link rel="stylesheet" href="../assets/css/base.css" />
    <link rel="stylesheet" href="../assets/css/login.css" />
  </head>
  <body>
    
    <div class="login-container <?= isActiveForm('login', $active_form) ?>">  
      <img src="../assets/images/logo.svg" alt="Darkhorse Logo" class="logo" /> 
      <?= showError($errors['login']) ?>
      <h1>Login</h1>
      <form action="login_register.php" method="post" class="login-form" id="loginForm">
        <label for="email">Email:</label>
        <input type="text" id="email" name="email" required />
        <label for="password">Password:</label>
        <input type="password" id="password" name="password" required />
        <div style="display: flex; align-items: center; gap: 8px; margin: 10px 0;">
          <input type="checkbox" id="remember_me" name="remember_me" value="1" style="width: auto; margin: 0;" />
          <label for="remember_me" style="margin: 0; font-size: 14px; cursor: pointer;">Keep me Logged in</label>
        </div>
        <button type="submit" name="login">Login</button>
        <div style="margin-top: 15px; text-align: center; padding: 10px; background-color: #f0f4f8; border-radius: 6px;">
          <p style="margin: 0; color: #555; font-size: 14px;">
            Can't login? <strong>Contact your administrator</strong> for password reset.
          </p>
        </div>
      </form>
    </div>
  </body>
</html>
