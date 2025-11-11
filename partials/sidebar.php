<?php
// Shared sidebar
require_once __DIR__ . '/url.php';

// Get user role if not already set
if (!isset($role) && isset($_SESSION['email'])) {
    require_once __DIR__ . '/../config/config.php';
    $email = $_SESSION['email'];
    $stmt = $conn->prepare("SELECT role FROM users WHERE email=? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $userData = $result->fetch_assoc();
    $actualRole = $userData['role'] ?? 'laborer';
    
    // Check if developer is previewing as another role
    if ($actualRole === 'developer' && isset($_GET['preview_role'])) {
        $role = $_GET['preview_role'];
    } else {
        $role = $actualRole;
    }
    
    $stmt->close();
}
// Only render the control panel for admins
if (!isset($role) || $role !== 'admin') {
    // no sidebar for non-admin users
    return;
}

// Preserve preview mode in URLs
$previewParam = '';
if (isset($_GET['preview_role'])) {
    $previewParam = '?preview_role=' . urlencode($_GET['preview_role']);
}
?>
<aside class="side-nav" role="navigation" aria-label="Control panel">
    <p class="adminnav">Control Panel</p>
    <div class="nav-group" id="usersGroup">
        <div class="nav-toggle">
            <button class="nav-btn" id="usersToggle" type="button">Users ▾</button>
        </div>
        <div class="sub-nav">
            <a href="<?php echo htmlspecialchars(base_url('/admin/register_new.php') . $previewParam); ?>" class="nav-btn">Add User</a>
            <a href="<?php echo htmlspecialchars(base_url('/admin/user_list.php') . $previewParam); ?>" class="nav-btn">List Users</a>
        </div>
    </div>
</aside>
