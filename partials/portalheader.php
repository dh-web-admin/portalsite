<?php
// Shared Portal header
if (session_status() === PHP_SESSION_NONE) { require_once __DIR__ . '/../session_init.php'; }
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
                            $role = $row['role'];
                            if ($row['role'] === 'admin') {
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
?>
<div class="welcome-section">
  <div class="welcome-left">
    <h1>Welcome, <?php echo htmlspecialchars($name); ?></h1>
    <h2><?php echo htmlspecialchars($title); ?></h2>
  </div>
        <img src="<?php echo htmlspecialchars(base_url('/assets/images/eportal.svg')); ?>" alt="Portal logo" class="welcome-logo" />

        <?php if ($role !== 'admin'): ?>
            <div class="header-actions" aria-hidden="false">
                <a href="<?php echo htmlspecialchars(base_url('/pages/dashboard.php')); ?>" class="header-action-btn">Home</a>
                <a href="<?php echo htmlspecialchars(base_url('/auth/logout.php')); ?>" class="header-action-btn logout-btn">Logout</a>
            </div>
        <?php endif; ?>
</div>
