<?php
require_once __DIR__ . '/../session_init.php';

// Check if user is logged in
if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Include database configuration
require_once '../config/config.php';

// Get user information
$email = $_SESSION['email'];
$query = "SELECT role FROM users WHERE email='$email'";
$result = $conn->query($query);
$user = $result->fetch_assoc();

// Store role for access control
$role = $user['role'] ?? 'laborer';

// Define page access by role
$allPages = [
    'equipments' => 'Equipment',
    'Bid_tracking' => 'Bid Tracking',
    'scheduling' => 'Scheduling',
    'engineering' => 'Engineering',
    'employee_information' => 'Employee Information',
    'for_sale' => 'For Sale',
    'project_checklist' => 'Project Checklist',
    'pictures' => 'Pictures',
    'forms' => 'Forms',
    'manuals' => 'Manuals',
    'videos' => 'Videos',
    'maps' => 'Maps'
];

// Role-based access control
$hiddenPages = [];
switch ($role) {
    case 'superintendent':
        $hiddenPages = ['Bid_tracking'];
        break;
    case 'foreman':
        $hiddenPages = ['Bid_tracking', 'maps', 'engineering'];
        break;
    case 'mechanic':
        $hiddenPages = ['Bid_tracking', 'maps', 'engineering', 'forms', 'project_checklist'];
        break;
    case 'operator':
    case 'laborer':
        // Only show these 3 pages
        $allowedPages = ['employee_information', 'manuals', 'videos'];
        $hiddenPages = array_diff(array_keys($allPages), $allowedPages);
        break;
    case 'admin':
    case 'projectmanager':
    case 'estimator':
    case 'accounting':
    default:
        // All pages visible
        $hiddenPages = [];
        break;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="theme-color" content="#667eea">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/admin-layout.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body class="admin-page">
    <div class="admin-container">
    <?php include __DIR__ . '/../partials/portalheader.php'; ?>
        <div class="admin-layout">
            <?php include __DIR__ . '/../partials/sidebar.php'; ?>
            <main class="content-area">
                <div class="main-content">
                    <div class="tiles">
                    <!-- Role-based tiles -->
                    <?php require_once __DIR__ . '/../partials/url.php'; ?>
                    <?php foreach ($allPages as $page => $title): ?>
                        <?php if (!in_array($page, $hiddenPages)): ?>
                            <a href="<?php echo htmlspecialchars(base_url('/pages/' . $page . '.php')); ?>" class="tile">
                                <h2><?php echo htmlspecialchars($title); ?></h2>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                </div>
            </main>
        </div>
    </div>
    <script>
    (function(){
        // Toggle users sub-nav
        var usersToggle = document.getElementById('usersToggle');
        var usersGroup = document.getElementById('usersGroup');
        if (usersToggle && usersGroup) {
            usersToggle.addEventListener('click', function(){
                usersGroup.classList.toggle('open');
            });
        }
    })();
    </script>
    <script src="../assets/js/mobile-menu.js"></script>
</body>
</html>
