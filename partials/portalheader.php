<?php
// Shared Portal header
if (session_status() === PHP_SESSION_NONE) { require_once __DIR__ . '/../session_init.php'; }
require_once __DIR__ . '/url.php';
require_once __DIR__ . '/permissions.php';

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
            // Make DB connection available to permissions helper
            $GLOBALS['conn'] = $conn;
            if ($stmt = $conn->prepare('SELECT role FROM users WHERE email = ? LIMIT 1')) {
                $stmt->bind_param('s', $_SESSION['email']);
                if ($stmt->execute()) {
                    $res = $stmt->get_result();
                    if ($res && $res->num_rows > 0) {
                        $row = $res->fetch_assoc();
                        if (isset($row['role'])) {
                            $actualRole = $row['role'];

                            // Dev preview mode removed: always use actual role
                            $role = $actualRole;

                            // Make role available to permissions helper
                            $GLOBALS['role'] = $role;

                            if ($role === 'admin') {
                                $title = 'Admin Dashboard';
                            }
                        }
                    }
                }
                $stmt->close();
            }
        }
    }
}

// Determine profile image to show. Prefer session, fall back to DB (user_details.profile_picture then users.profile_image if present).
$profileSrc = null;
if (!empty($_SESSION['profile_image'])) {
    $profileSrc = $_SESSION['profile_image'];
} elseif (!empty($_SESSION['email']) && isset($conn) && $conn instanceof mysqli) {
    // Check whether users.profile_image column exists to avoid SQL errors on older schemas
    $hasProfileCol = false;
    $colRes = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_image'");
    if ($colRes && $colRes->num_rows > 0) { $hasProfileCol = true; }

    if ($hasProfileCol) {
        $sql = 'SELECT COALESCE(ud.profile_picture, u.profile_image) AS pic FROM users u LEFT JOIN user_details ud ON ud.user_id = u.id WHERE u.email = ? LIMIT 1';
    } else {
        // Only select from user_details to avoid referencing a missing column
        $sql = 'SELECT ud.profile_picture AS pic FROM users u LEFT JOIN user_details ud ON ud.user_id = u.id WHERE u.email = ? LIMIT 1';
    }

    $picStmt = $conn->prepare($sql);
    if ($picStmt) {
        $picStmt->bind_param('s', $_SESSION['email']);
        if ($picStmt->execute()) {
            $pres = $picStmt->get_result();
            if ($pres && $pres->num_rows > 0) {
                $prow = $pres->fetch_assoc();
                if (!empty($prow['pic'])) $profileSrc = $prow['pic'];
            }
        }
        $picStmt->close();
    }
}
?>

<div class="welcome-section">
    <div class="welcome-left">
        <h1>Welcome, <?php echo htmlspecialchars($name); ?></h1>
        <h2><?php echo htmlspecialchars($title); ?></h2>
    </div>
    <?php
    // Always show the portal logo in the header masthead to avoid duplicate
    // profile images; the account action button will show the user's avatar.
    $imgSrc = base_url('/assets/images/eportal.svg');
    $imgClass = 'welcome-logo';
    $imgAlt = 'Portal logo';
    ?>
    <img src="<?php echo htmlspecialchars($imgSrc); ?>" alt="<?php echo $imgAlt; ?>" class="<?php echo $imgClass; ?>" />
    <div class="header-actions" aria-hidden="false">
        <a href="<?php echo htmlspecialchars(base_url('/pages/dashboard/index.php')); ?>" class="header-action-btn">Home</a>
        <?php if (isset($role) && $role === 'developer'): ?>
            <a href="<?php echo htmlspecialchars(base_url('/dev/index.php')); ?>" class="header-action-btn">Dev Dashboard</a>
        <?php endif; ?>
        <a href="<?php echo htmlspecialchars(base_url('/pages/account_settings/index.php')); ?>" class="header-action-btn" title="Account Settings">
            <?php if (!empty($profileSrc)): ?>
                <?php
                    $acctImg = $profileSrc;
                    if (strpos($acctImg, 'http') !== 0 && strpos($acctImg, '/') !== 0) {
                        $acctImg = base_url($acctImg);
                    }
                ?>
                <img src="<?php echo htmlspecialchars($acctImg); ?>" alt="Account" class="header-action-avatar sm" />
            <?php else: ?>
                <img src="<?php echo htmlspecialchars(base_url('/assets/images/user-icon.svg')); ?>" alt="Account Settings" style="width: 16px; height: 16px;">
            <?php endif; ?>
        </a>
        <a href="<?php echo htmlspecialchars(base_url('/auth/logout.php')); ?>" class="header-action-btn logout-btn">Logout</a>
    </div>
</div>

<!-- Global unsaved changes guard script (handles any elements marked with data-track-unsaved) -->
<script src="<?php echo htmlspecialchars(base_url('/assets/js/unsaved-guard.js')); ?>" defer></script>
<!-- Global logout confirmation (ensures consistent prompt on all pages) -->
<script src="<?php echo htmlspecialchars(base_url('/assets/js/logout-confirm.js')); ?>" defer></script>


<?php // Dev preview mode removed ?>
