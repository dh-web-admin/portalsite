<?php
// Engineering section page — Hydraulic Systems
//
// Single unified diagram — no tab sub-navigation. _hc_diagram.php renders
// every layer in images/hydraulic components.svg at once.
//
// Lives in its own subfolder (pages/engineering/hydraulic_system/) along
// with its sibling pages (all-components list, inventory), the diagram
// partial, its SVG source images, and its own style.css. This page needs
// two extra action buttons ("View all components" / "View Inventory") in
// its topbar, so — unlike the other engineering sub-pages — it builds its
// own chrome instead of using the shared _section_top.php/_section_bottom.php
// partials (which assume a page living directly in pages/engineering/).
require_once __DIR__ . '/../../../session_init.php';

if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    header('Location: /auth/login.php');
    exit();
}

require_once __DIR__ . '/../../../config/config.php';

$email = $_SESSION['email'];
$stmt = $conn->prepare('SELECT role FROM users WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$res  = $stmt->get_result();
$user = $res ? $res->fetch_assoc() : null;
$role = $user ? $user['role'] : 'laborer';
$stmt->close();

require_once __DIR__ . '/../../../partials/permissions.php';
$hasEditPermission = can_edit_page('engineering');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes" />
  <meta name="theme-color" content="#667eea" />
  <title>Hydraulic Systems — Engineering</title>
  <link rel="stylesheet" href="../../../assets/css/base.css" />
  <link rel="stylesheet" href="../../../assets/css/admin-layout.css" />
  <link rel="stylesheet" href="../../../assets/css/dashboard.css" />
  <link rel="stylesheet" href="../style.css?v=<?php echo @filemtime(__DIR__ . '/../style.css'); ?>" />
  <link rel="stylesheet" href="style.css?v=<?php echo @filemtime(__DIR__ . '/style.css'); ?>" />
</head>
<body class="admin-page">
  <div class="admin-container">
    <?php include __DIR__ . '/../../../partials/portalheader.php'; ?>
    <div class="admin-layout">
      <?php include __DIR__ . '/../../../partials/sidebar.php'; ?>
      <main class="content-area">
        <div class="main-content">
          <div class="eng-topbar">
            <a href="../index.php" class="eng-back-btn">&larr; Back</a>
            <nav class="eng-breadcrumb">
              <a href="../index.php">Engineering</a>
              <span aria-hidden="true">/</span>
              <span class="current">Hydraulic Systems</span>
            </nav>
            <div class="eng-topbar-actions">
              <a href="hydraulic_components_list.php" class="eng-action-btn eng-action-btn--components">View all components</a>
              <a href="hydraulic_inventory.php" class="eng-action-btn eng-action-btn--inventory">View Inventory</a>
            </div>
          </div>

          <?php include __DIR__ . '/_hc_diagram.php'; ?>
        </div>
      </main>
    </div>
  </div>
  <script>
    (function () {
      var pairs = [['usersToggle', 'usersGroup'], ['devToggle', 'devGroup'], ['maintenanceToggle', 'maintenanceGroup']];
      pairs.forEach(function (p) {
        var btn = document.getElementById(p[0]);
        var grp = document.getElementById(p[1]);
        if (btn && grp) {
          btn.addEventListener('click', function () { grp.classList.toggle('open'); });
        }
      });
    })();
  </script>
  <script src="../../../assets/js/mobile-menu.js"></script>
  <script src="../../../assets/js/logout-confirm.js"></script>
</body>
</html>
