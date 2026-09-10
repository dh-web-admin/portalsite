<?php
/**
 * Shared top half of every engineering sub-page.
 *
 * A sub-page does:
 *     <?php $SECTION_SLUG = 'catwalk'; require __DIR__ . '/_section_top.php'; ?>
 *     ...its content...
 *     <?php require __DIR__ . '/_section_bottom.php'; ?>
 *
 * Provides in scope for the content: $SECTION_SLUG, $SECTION_TITLE, $role,
 * $hasEditPermission, $conn.
 */
require_once __DIR__ . '/../../session_init.php';

if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    header('Location: /auth/login.php');
    exit();
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/_sections.php';

$SECTION_SLUG  = $SECTION_SLUG ?? '';
$SECTION_TITLE = $ENGINEERING_SECTIONS[$SECTION_SLUG] ?? null;

// Unknown / missing slug -> send back to the hub
if ($SECTION_TITLE === null) {
    header('Location: index.php');
    exit();
}

// Role for the sidebar
$email = $_SESSION['email'];
$stmt = $conn->prepare('SELECT role FROM users WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$res  = $stmt->get_result();
$user = $res ? $res->fetch_assoc() : null;
$role = $user ? $user['role'] : 'laborer';
$stmt->close();

require_once __DIR__ . '/../../partials/permissions.php';
$hasEditPermission = can_edit_page('engineering');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes" />
  <meta name="theme-color" content="#667eea" />
  <title><?php echo htmlspecialchars($SECTION_TITLE); ?> — Engineering</title>
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
          <div class="eng-topbar">
            <a href="index.php" class="eng-back-btn">&larr; Back</a>
            <nav class="eng-breadcrumb">
              <a href="index.php">Engineering</a>
              <span aria-hidden="true">/</span>
              <span class="current"><?php echo htmlspecialchars($SECTION_TITLE); ?></span>
            </nav>
          </div>
