<?php
require_once __DIR__ . '/../session_init.php';

// Check if user is logged in
if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    header('Location: ../auth/login.php');
    exit();
}

// Include database configuration
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../partials/permissions.php';

// Get user role for sidebar
$email = $_SESSION['email'];
$stmt = $conn->prepare('SELECT role FROM users WHERE email=? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
$user = $res ? $res->fetch_assoc() : null;
$role = $user ? $user['role'] : 'laborer';
$stmt->close();

// Enforce access control for this page
if (!can_access($role, 'maps')) {
  header('Location: ../pages/dashboard.php');
  exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>{{TITLE}}</title>
  <link rel="stylesheet" href="../assets/css/base.css" />
  <link rel="stylesheet" href="../assets/css/admin-layout.css" />
  <link rel="stylesheet" href="../assets/css/dashboard.css" />
</head>
<body class="admin-page">
  <div class="admin-container">
    <?php include __DIR__ . '/../partials/portalheader.php'; ?>
    <div class="admin-layout">
      <?php include __DIR__ . '/../partials/sidebar.php'; ?>
      <main class="content-area">
        <div class="main-content">
         <img src="<?php echo htmlspecialchars(base_url('/assets/images/maintenance.png')); ?>" alt="Maintenance Image" />
        </div>
      </main>
    </div>
  </div>
  <script>
    (function(){
      var usersToggle = document.getElementById('usersToggle');
      var usersGroup = document.getElementById('usersGroup');
      if (usersToggle && usersGroup) {
        usersToggle.addEventListener('click', function(){
          usersGroup.classList.toggle('open');
        });
      }
    })();
  </script>
</body>
</html>
