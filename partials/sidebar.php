<?php
// Shared sidebar
require_once __DIR__ . '/url.php';
require_once __DIR__ . '/permissions.php';

// Get user role if not already set
if (!isset($role) && isset($_SESSION['email'])) {
    require_once __DIR__ . '/../config/config.php';
    // Ensure permissions helper can see the DB connection
    if (isset($conn)) {
        $GLOBALS['conn'] = $conn;
    }
    $email = $_SESSION['email'];
    $stmt = $conn->prepare("SELECT role FROM users WHERE email=? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $userData = $result->fetch_assoc();
    $actualRole = $userData['role'] ?? 'laborer';

    // Dev preview mode removed: always use actual role
    $role = $actualRole;
    
    $stmt->close();
}
// Only render the control panel for users who have Admin Panel access.
// This includes admins by role, plus any user explicitly granted admin_panel.
if (!isset($role) || !function_exists('can_access') || !can_access((string)$role, 'admin_panel')) {
    return;
}

?>
<aside class="side-nav" role="navigation" aria-label="Control panel">
    <p class="adminnav">Control Panel</p>
    <div class="nav-group" id="usersGroup">
        <div class="nav-toggle">
            <button class="nav-btn" id="usersToggle" type="button">Users ▾</button>
        </div>
        <div class="sub-nav">
            <a href="<?php echo htmlspecialchars(base_url('/admin/register_new.php')); ?>" class="nav-btn">Add User</a>
            <a href="<?php echo htmlspecialchars(base_url('/admin/user_list.php')); ?>" class="nav-btn">List Users</a>
            <a href="<?php echo htmlspecialchars(base_url('/admin/permissions.php')); ?>" class="nav-btn">Permissions</a>
        </div>
    </div>
    
    <div class="nav-group" id="maintenanceGroup">
        <a href="<?php echo htmlspecialchars(base_url('/admin/backup.php')); ?>" class="nav-btn">Backups</a>
    </div>
    
</aside>

