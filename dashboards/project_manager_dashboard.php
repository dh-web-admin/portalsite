<?php
session_start();
require_once 'config.php';

// Auth check
if (!isset($_SESSION['email'])) {
    header('Location: index.php');
    exit();
}

// Verify user role (project manager)
$userEmail = $_SESSION['email'];
$stmt = $conn->prepare("SELECT role FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $userEmail);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) {
    header('Location: index.php');
    exit();
}
$row = $res->fetch_assoc();
if ($row['role'] !== 'projectmanager') {
    header('Location: index.php');
    exit();
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Manager Dashboard</title>
    <link rel="stylesheet" href="stylesheet.css">
</head>
<body class="admin-page">
    <div class="admin-container">
        <?php include __DIR__ . '/partials/portalheader.php'; ?>

        <div class="admin-layout">
            <aside class="side-nav" role="navigation" aria-label="Project Manager control panel">
                <p class="adminnav">Project Manager Control Panel</p>
                <a href="project_manager_dashboard.php" class="nav-btn">Home</a>
                <a href="logout.php" class="nav-btn logout-btn">Logout</a>
            </aside>

            
        </div>
    </div>
</body>
</html>
