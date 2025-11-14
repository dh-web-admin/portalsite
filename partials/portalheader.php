<?php
// Shared Portal header
if (session_status() === PHP_SESSION_NONE) { require_once __DIR__ . '/../config/session_init.php'; }
require_once __DIR__ . '/url.php';

 $name = isset($_SESSION['name']) ? $_SESSION['name'] : 'User';
 $title = 'Employee Dashboard';
 $role = 'laborer'; // default role if we can't resolve it from DB

// Determine role to set the title
if (!empty($_SESSION['email'])) {
    // Try to use DB to get the latest role
    // Load DB config from the correct path
    $configPath = __DIR__ . '/../config/config.php';
    if (file_exists($configPath)) {
        require_once $configPath;
        if (isset($conn) && $conn instanceof mysqli) {
            if ($stmt = $conn->prepare('SELECT role FROM users WHERE email = ? LIMIT 1')) {
                $stmt->bind_param('s', $_SESSION['email']);
                if ($stmt->execute()) {
                    $res = $stmt->get_result();
                    if ($res && $res->num_rows > 0) {
                        $row = $res->fetch_assoc();
                        if (isset($row['role'])) {
                            $actualRole = $row['role'];
                            
                            // Check if developer is previewing as another role
                            if ($actualRole === 'developer' && isset($_GET['preview_role'])) {
                                $role = $_GET['preview_role'];
                            } else {
                                $role = $actualRole;
                            }
                            
                            if ($role === 'admin') {
                                $title = 'Admin Dashboard';
                            } elseif ($actualRole === 'developer' && !isset($_GET['preview_role'])) {
                                $title = 'Developer Preview Dashboard';
                            }
                        }
                    }
                }
                $stmt->close();
            }
        }
    }
}

// Preserve preview mode in URLs
$previewParam = '';
if (isset($_GET['preview_role'])) {
    $previewParam = '?preview_role=' . urlencode($_GET['preview_role']);
}
?>

<div class="welcome-section">
    <div class="welcome-left">
        <h1>Welcome, <?php echo htmlspecialchars($name); ?></h1>
        <h2><?php echo htmlspecialchars($title); ?></h2>
    </div>
    <img src="<?php echo htmlspecialchars(base_url('/assets/images/eportal.svg')); ?>" alt="Portal logo" class="welcome-logo" />
    <div class="header-actions" aria-hidden="false">
        <a href="<?php echo htmlspecialchars(base_url('/pages/dashboard/index.php') . $previewParam); ?>" class="header-action-btn">Home</a>
        <a href="<?php echo htmlspecialchars(base_url('/pages/account_settings/index.php') . $previewParam); ?>" class="header-action-btn" title="Account Settings">
            <img src="<?php echo htmlspecialchars(base_url('/assets/images/user-icon.svg')); ?>" alt="Account Settings" style="width: 16px; height: 16px;">
        </a>
        <a href="<?php echo htmlspecialchars(base_url('/auth/logout.php')); ?>" class="header-action-btn logout-btn">Logout</a>
    </div>
</div>

<!-- Global unsaved changes guard script (handles any elements marked with data-track-unsaved) -->
<script src="<?php echo htmlspecialchars(base_url('/assets/js/unsaved-guard.js')); ?>" defer></script>
<!-- Global logout confirmation (ensures consistent prompt on all pages) -->
<script src="<?php echo htmlspecialchars(base_url('/assets/js/logout-confirm.js')); ?>" defer></script>

<!-- Responsive helper stylesheet (loaded late; conservative rules only) -->
<link rel="stylesheet" href="<?php echo htmlspecialchars(base_url('/assets/css/responsive.css')); ?>" />

<?php
// Always attempt to include developer notch from header so it's present across pages
// It will self-check actual developer role and render conditionally.
include __DIR__ . '/dev_notch.php';
?>
