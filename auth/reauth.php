<?php
// Step-up re-authentication challenge. Reached only via partials/reauth.php's
// require_reauth() guard, which sends visitors here before letting them into
// the admin control panel or account settings once their last password
// confirmation is more than REAUTH_WINDOW_SECONDS old.
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../partials/url.php';
require_once __DIR__ . '/../partials/reauth.php';

if (!isset($_SESSION['email'])) {
    header('Location: ' . base_url('/auth/login.php'));
    exit();
}

$next = is_safe_local_redirect($_POST['next'] ?? $_GET['next'] ?? null) ?? base_url('/pages/dashboard/');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $email = $_SESSION['email'];

    $stmt = $conn->prepare('SELECT password FROM users WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['reauth_at'] = time();
        header('Location: ' . $next);
        exit();
    }

    $error = 'Incorrect password. Please try again.';
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Confirm Your Password</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(base_url('/assets/css/base.css')); ?>" />
    <link rel="stylesheet" href="<?php echo htmlspecialchars(base_url('/assets/css/login.css')); ?>" />
  </head>
  <body>
    <div class="login-container">
      <img src="<?php echo htmlspecialchars(base_url('/assets/images/logo.svg')); ?>" alt="Darkhorse Logo" class="logo" />
      <?php if ($error !== ''): ?>
        <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
      <?php endif; ?>
      <h1>Confirm Your Password</h1>
      <p style="text-align:center; color:#666; margin-top:-8px; margin-bottom:18px; font-size:14px;">
        This area requires you to re-enter your password before continuing.
      </p>
      <form action="reauth.php" method="post" class="login-form">
        <input type="hidden" name="next" value="<?php echo htmlspecialchars($next, ENT_QUOTES, 'UTF-8'); ?>" />
        <label for="password">Password:</label>
        <div class="password-field">
          <input type="password" id="password" name="password" required autofocus />
          <button type="button" id="togglePassword" class="pw-toggle" aria-pressed="false" aria-controls="password" aria-label="Show password" title="Show password">Show</button>
        </div>
        <button type="submit">Confirm</button>
      </form>
    </div>
    <script>
      (function(){
        var pwd = document.getElementById('password');
        var btn = document.getElementById('togglePassword');
        if (!pwd || !btn) return;
        btn.addEventListener('click', function(){
          var isHidden = pwd.type === 'password';
          pwd.type = isHidden ? 'text' : 'password';
          btn.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
          btn.textContent = isHidden ? 'Hide' : 'Show';
        });
      })();
    </script>
  </body>
</html>
