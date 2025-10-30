<?php
require_once __DIR__ . '/../session_init.php';
$errors = [
  'login' => $_SESSION['login_error'] ?? ''
]; 
$active_form = $_SESSION['active_form'] ?? 'login';
session_unset();
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
