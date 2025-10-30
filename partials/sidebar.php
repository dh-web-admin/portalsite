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
    $role = $userData['role'] ?? 'laborer';
    $stmt->close();
}
?>
<aside class="side-nav" role="navigation" aria-label="Control panel">
    <p class="adminnav">Control Panel</p>
    <a href="<?php echo htmlspecialchars(base_url('/pages/dashboard.php')); ?>" class="nav-btn">Home</a>
    <?php if (isset($role) && $role === 'admin'): ?>
    <div class="nav-group" id="usersGroup">
        <div class="nav-toggle">
            <button class="nav-btn" id="usersToggle" type="button">Users ▾</button>
        </div>
        <div class="sub-nav">
            <a href="<?php echo htmlspecialchars(base_url('/admin/register_new.php')); ?>" class="nav-btn">Add User</a>
            <a href="<?php echo htmlspecialchars(base_url('/admin/remove_user.php')); ?>" class="nav-btn">Remove User</a>
            <a href="<?php echo htmlspecialchars(base_url('/admin/user_list.php')); ?>" class="nav-btn">List Users</a>
        </div>
    </div>
    <!-- Tiny admin-only debug tools button -->
    <a href="<?php echo htmlspecialchars(base_url('/debug/pages_health.php')); ?>" class="nav-btn" style="font-size:12px;padding:6px 8px;opacity:0.9;">Debug tools for admin</a>
    <?php endif; ?>
    <a href="<?php echo htmlspecialchars(base_url('/auth/logout.php')); ?>" class="nav-btn logout-btn">Logout</a>
</aside>
