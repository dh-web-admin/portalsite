<?php
require_once __DIR__ . '/../../config/session_init.php';

// Check if user is logged in
if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    header("Location: ../../auth/login.php");
    exit();
}

// Include database configuration
require_once '../../config/config.php';

// Get user information
$email = $_SESSION['email'];
$query = "SELECT role FROM users WHERE email='$email'";
$result = $conn->query($query);
$user = $result->fetch_assoc();

// Store role for access control
$actualRole = $user['role'] ?? 'laborer';

// Check if developer is previewing as another role
if ($actualRole === 'developer' && isset($_GET['preview_role'])) {
    $previewRole = $_GET['preview_role'];
    $allowedRoles = ['admin', 'projectmanager', 'estimator', 'accounting', 'superintendent', 'foreman', 'mechanic', 'operator', 'laborer', 'developer', 'data_entry'];
    if (in_array($previewRole, $allowedRoles)) {
        $role = $previewRole;
    } else {
        $role = $actualRole;
    }
} else {
    $role = $actualRole;
}

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
    'coordinate_entry' => 'Coordinate Entry',
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
    case 'guest':
        // Guests only see the Coordinate Entry tile
        $allowedPages = ['coordinate_entry'];
        $hiddenPages = array_diff(array_keys($allPages), $allowedPages);
        break;
    case 'data_entry':
        // Data-entry users only see maps and coordinate entry
        $allowedPages = ['maps', 'coordinate_entry'];
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
    <link rel="stylesheet" href="../../assets/css/base.css">
    <link rel="stylesheet" href="../../assets/css/admin-layout.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
  <link rel="stylesheet" href="style.css" />
</head>
<body class="admin-page">
    <div class="admin-container">
    <?php include __DIR__ . '/../../partials/portalheader.php'; ?>
        
        <div class="admin-layout">
            <?php include __DIR__ . '/../../partials/sidebar.php'; ?>
            <main class="content-area">
                <div class="main-content">
                    <div class="tiles">
                    <!-- Role-based tiles -->
                    <?php 
                    require_once __DIR__ . '/../../partials/url.php';
                    // Preserve preview mode in tile URLs
                    $previewParam = '';
                    if (isset($_GET['preview_role'])) {
                        $previewParam = '?preview_role=' . urlencode($_GET['preview_role']);
                    }
                    ?>
                    <?php foreach ($allPages as $page => $title): ?>
                        <?php
                            // Skip pages hidden by role rules
                            if (in_array($page, $hiddenPages)) {
                                continue;
                            }

                            // Coordinate entry should only be visible to admins, developers and project managers
                            if ($page === 'coordinate_entry') {
                                $allowedForCoords = ['admin', 'developer', 'projectmanager', 'data_entry'];
                                if (!in_array($role, $allowedForCoords)) {
                                    continue;
                                }
                            }
                        ?>
                        <a href="<?php echo htmlspecialchars(base_url('/pages/' . $page . '/') . $previewParam); ?>" class="tile">
                            <h2><?php echo htmlspecialchars($title); ?></h2>
                        </a>
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

        // Toggle dev options sub-nav
        var devToggle = document.getElementById('devToggle');
        var devGroup = document.getElementById('devGroup');
        if (devToggle && devGroup) {
            devToggle.addEventListener('click', function(){
                devGroup.classList.toggle('open');
            });
        }
        
        // Developer Preview handled globally via dev_notch partial
    })();
    </script>
    <script src="../../assets/js/mobile-menu.js"></script>
</body>
</html>
