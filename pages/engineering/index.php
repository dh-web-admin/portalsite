<?php
require_once __DIR__ . '/../../session_init.php';

// Check if user is logged in
if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    header('Location: /auth/login.php');
    exit();
}

// Include database configuration
require_once __DIR__ . '/../../config/config.php';

// Get user role for sidebar
$email = $_SESSION['email'];
$stmt = $conn->prepare('SELECT role FROM users WHERE email=? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
$user = $res ? $res->fetch_assoc() : null;
$role = $user ? $user['role'] : 'laborer';
$stmt->close();

// Load permissions
require_once __DIR__ . '/../../partials/permissions.php';
$hasEditPermission = can_edit_page('engineering');

// Section list for the hub grid
require_once __DIR__ . '/_sections.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes" />
  <meta name="theme-color" content="#667eea" />
  <title>Engineering</title>
  <link rel="stylesheet" href="../../assets/css/base.css" />
  <link rel="stylesheet" href="../../assets/css/admin-layout.css" />
  <link rel="stylesheet" href="../../assets/css/dashboard.css" />
  <link rel="stylesheet" href="style.css?v=<?php echo @filemtime(__DIR__ . '/style.css'); ?>" />
</head>
<body class="admin-page">
  <div class="admin-container">
    <?php include __DIR__ . '/../../partials/portalheader.php'; ?>
    <div class="admin-layout">
      <?php include __DIR__ . '/../../partials/sidebar.php'; ?>
      <main class="content-area">
        <div class="main-content">
          <div class="tiles eng-tiles">
            <?php foreach ($ENGINEERING_SECTIONS as $slug => $label): ?>
              <?php
                // hydraulic_systems lives in its own subfolder alongside its
                // sibling pages, diagram partial, images, and style.css —
                // everything else here is a flat <slug>.php in this folder.
                $href = $slug === 'hydraulic_systems'
                    ? 'hydraulic_system/hydraulic_systems.php'
                    : htmlspecialchars($slug) . '.php';
              ?>
              <a href="<?php echo $href; ?>" class="tile">
                <span class="tile-label"><?php echo htmlspecialchars($label); ?></span>
              </a>
            <?php endforeach; ?>
          </div>
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

      // Toggle dev options sub-nav
      var devToggle = document.getElementById('devToggle');
      var devGroup = document.getElementById('devGroup');
      if (devToggle && devGroup) {
        devToggle.addEventListener('click', function(){
          devGroup.classList.toggle('open');
        });
      }
      // Toggle maintenance sub-nav
      var maintenanceToggle = document.getElementById('maintenanceToggle');
      var maintenanceGroup = document.getElementById('maintenanceGroup');
      if (maintenanceToggle && maintenanceGroup) {
        maintenanceToggle.addEventListener('click', function(){
          maintenanceGroup.classList.toggle('open');
        });
      }
    })();
  </script>
  <script src="../../assets/js/mobile-menu.js"></script>
  <script src="../../assets/js/logout-confirm.js"></script>
</body>
</html>
