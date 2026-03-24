<?php
require_once __DIR__ . '/../../session_init.php';

// Check if user is logged in
if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    header("Location: /auth/login.php");
    exit();
}

// Include database configuration
require_once '../../config/config.php';
require_once __DIR__ . '/../../partials/permissions.php';

// Ensure permissions helper can see the DB connection
if (isset($conn)) {
    $GLOBALS['conn'] = $conn;
}

// Get user information
$email = $_SESSION['email'];
$query = "SELECT role FROM users WHERE email='$email'";
$result = $conn->query($query);
$user = $result->fetch_assoc();

// Store role for access control
$actualRole = $user['role'] ?? 'laborer';

// Dev preview mode removed: always use actual role
$role = $actualRole;

// Define page access by role
$allPages = [
    'equipments' => 'Equipment',
    'client_profile' => 'Client Profile',
    'Bid_tracking' => 'Bid Tracking',
    'scheduling' => 'Scheduling',
    'engineering' => 'Engineering',
    'employee_information' => 'Employee Information',
    'for_sale' => 'For Sale',
    'project_checklist' => 'Project Checklist',
    'forms' => 'Forms',
    'company_policies' => 'Company Policies',
    'sops' => 'S.O.Ps',
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
        $allowedPages = ['employee_information', 'company_policies', 'sops'];
        $hiddenPages = array_diff(array_keys($allPages), $allowedPages);
        break;
    case 'guest':
        // Guests see no tiles by default
        $allowedPages = [];
        $hiddenPages = array_diff(array_keys($allPages), $allowedPages);
        break;
    case 'data_entry':
        // Data-entry users only see maps
        $allowedPages = ['maps'];
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
    <title>Dark horse Dashboard</title>
    <link rel="stylesheet" href="../../assets/css/base.css">
    <link rel="stylesheet" href="../../assets/css/admin-layout.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
  <link rel="stylesheet" href="style.css" />
    <style>
        /* Dashboard-specific wallpaper applied to the visible admin container so it shows
             through the layout. Also ensure the main layout backgrounds are transparent
             so the wallpaper is visible. */
        .admin-container {
            background-image: url('../../assets/images/bg.svg');
            background-size: cover;
            background-repeat: no-repeat;
            background-position: center top;
        }

        /* Override layout backgrounds on this page only so the wallpaper is not hidden */
        .admin-container,
        .admin-layout,
        .content-area,
        .main-content {
            background-color: transparent !important;
        }
    </style>
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
                    // No preview params
                    ?>
                    <?php foreach ($allPages as $page => $title): ?>
                        <?php
                            // Centralized access logic:
                            // - If per-user override exists, it applies.
                            // - If no override exists, it falls back to role-based access.
                            if (function_exists('can_access')) {
                                if (!can_access((string)$role, (string)$page)) {
                                    continue;
                                }
                            } else {
                                // Fallback to legacy rules if helper isn't available
                                if (in_array($page, $hiddenPages, true)) {
                                    continue;
                                }
                            }

                            // (Coordinate Entry removed from dashboard)
                        ?>
                        <a href="<?php echo htmlspecialchars(base_url('/pages/' . $page . '/')); ?>" class="tile">
                            <h2><?php echo htmlspecialchars($title); ?></h2>
                        </a>
                    <?php endforeach; ?>
                </div>
                <!-- Fun scroll easter egg placed far below the main tiles -->
                <div style="margin-top: 2000px; text-align: center; color: #9ca3af; font-size: 12px;">
                    easter egg
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

        // Require at least 5 scroll attempts before enabling page scroll
        var scrollAttempts = 0;
        var scrollUnlocked = false;
        var requiredScrolls = 50;
        var lastTouchY = null;

        function reallyUnlockScroll() {
            if (scrollUnlocked) return;
            scrollUnlocked = true;
            try {
                document.body.style.overflowY = '';
            } catch (e) {}
            try {
                window.removeEventListener('wheel', onWheelAttempt, wheelOpts);
            } catch (e) {}
            try {
                window.removeEventListener('touchmove', onTouchAttempt, touchOpts);
                window.removeEventListener('touchstart', onTouchStart, touchOpts);
            } catch (e) {}
            try {
                window.removeEventListener('keydown', onKeyAttempt, true);
            } catch (e) {}
        }

        function registerAttempt(e) {
            if (scrollUnlocked) return true;
            scrollAttempts++;
            if (scrollAttempts >= requiredScrolls) {
                reallyUnlockScroll();
                return true;
            }
            if (e && typeof e.preventDefault === 'function') {
                e.preventDefault();
            }
            return false;
        }

        var wheelOpts = { passive: false };
        var touchOpts = { passive: false };

        function onWheelAttempt(e) {
            // Only count downward scroll (positive deltaY)
            if (!e || typeof e.deltaY === 'undefined' || e.deltaY <= 0) return;
            registerAttempt(e);
        }

        function onTouchAttempt(e) {
            if (!e || !e.touches || e.touches.length === 0) return;
            var currentY = e.touches[0].clientY;
            if (lastTouchY === null) {
                lastTouchY = currentY;
                return;
            }
            // Finger moving up (currentY < lastTouchY) typically causes page to scroll down
            var isDownwardScroll = currentY < lastTouchY;
            lastTouchY = currentY;
            if (!isDownwardScroll) return;
            registerAttempt(e);
        }

        function onTouchStart(e) {
            if (!e || !e.touches || e.touches.length === 0) return;
            lastTouchY = e.touches[0].clientY;
        }

        function onKeyAttempt(e) {
            // Only treat navigation keys as scroll attempts
            var code = e.keyCode || e.which;
            // Keys that scroll down: space, PageDown, End, ArrowDown
            var downKeys = [32,34,35,40];
            if (downKeys.indexOf(code) === -1) return;
            registerAttempt(e);
        }

        // On initial load, lock vertical scroll just for this dashboard page
        try {
            // At this point in the document, body exists, so we can lock immediately
            document.body.style.overflowY = 'hidden';
            window.addEventListener('wheel', onWheelAttempt, wheelOpts);
            window.addEventListener('touchmove', onTouchAttempt, touchOpts);
            window.addEventListener('touchstart', onTouchStart, touchOpts);
            window.addEventListener('keydown', onKeyAttempt, true);
        } catch (e) {}

        // Dev preview mode removed
    })();
    </script>
    <script src="../../assets/js/mobile-menu.js"></script>
</body>
</html>
