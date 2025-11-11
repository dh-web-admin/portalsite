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
    
    <?php
    // Show "Add Service" section only on maps page
    $currentPage = basename($_SERVER['PHP_SELF']);
    $isMapPage = (strpos($_SERVER['REQUEST_URI'], '/pages/maps/') !== false || $currentPage === 'index.php' && strpos($_SERVER['REQUEST_URI'], 'maps') !== false);
    if ($isMapPage):
    ?>
        <div class="nav-group" id="servicesGroup">
                <div class="nav-toggle">
                        <button class="nav-btn" id="servicesToggle" type="button">Edit Services ▾</button>
                </div>
                <div class="sub-nav" style="padding: 8px;">
                    <div style="display:grid; gap:8px;">
                        <!-- Add Service -->
                        <div style="display:grid; gap:4px;">
                            <label for="newServiceName" style="display: block; font-size: 11px; color: #fff; font-weight: 600;">Add Service</label>
                            <input type="text" id="newServiceName" placeholder="Service name" style="width:100%; padding: 6px 8px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 11px; box-sizing:border-box;" />
                            <button id="addServiceBtn" class="nav-btn" style="background: #667eea; color: white; border: none; padding: 6px 10px; border-radius: 4px; font-weight: 600; font-size: 11px; cursor: pointer; width:100%; margin:0;" onmouseover="this.style.background='#5a67d8'" onmouseout="this.style.background='#667eea'">Add</button>
                        </div>
                        <!-- Remove Service -->
                        <div style="display:grid; gap:4px; margin-top:4px;">
                            <label for="removeServiceSelect" style="display:block; font-size:11px; color:#fff; font-weight:600;">Remove Service</label>
                            <select id="removeServiceSelect" style="width:100%; padding:6px 8px; border:1px solid #cbd5e1; border-radius:4px; font-size:11px; box-sizing:border-box;">
                                <option value="">Loading...</option>
                            </select>
                            <button id="removeServiceBtn" class="nav-btn" style="background:#ef4444; color:#fff; border:none; padding:6px 10px; border-radius:4px; font-weight:600; font-size:11px; cursor:pointer; width:100%; margin:0;" onmouseover="this.style.background='#dc2626'" onmouseout="this.style.background='#ef4444'">Remove</button>
                        </div>
                        <!-- Info Note -->
                        <small style="display:block; margin-top:4px; font-size:9px; color:#94a3b8; line-height:1.3;">Note: Removing a service does not delete suppliers.</small>
                    </div>
                </div>
        </div>
    <?php endif; ?>
</aside>

