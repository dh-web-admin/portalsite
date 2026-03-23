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

$adminLayoutCssMtime = @filemtime(__DIR__ . '/../assets/css/admin-layout.css');
if ($adminLayoutCssMtime === false) {
    $adminLayoutCssMtime = time();
}

?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(base_url('/assets/css/admin-layout.css?v=' . $adminLayoutCssMtime)); ?>" />
<aside class="side-nav" id="adminSideNav" role="navigation" aria-label="Control panel">
    <button type="button" class="side-nav-collapse-btn" id="sideNavCollapseBtn" aria-label="Collapse sidebar" aria-expanded="true" title="Collapse sidebar">
        <span class="chevron" aria-hidden="true">&#10094;</span>
    </button>
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

<script>
    (function(){
        var sideNav = document.getElementById('adminSideNav');
        var collapseBtn = document.getElementById('sideNavCollapseBtn');
        var body = document.body;
        var adminLayout = sideNav.closest('.admin-layout');
        var storageKey = 'admin_sidebar_collapsed';

        if (!sideNav || !collapseBtn || !body) {
            return;
        }

        function isDesktopViewport() {
            return window.matchMedia('(min-width: 769px)').matches;
        }

        function setCollapsed(collapsed, persist) {
            if (!isDesktopViewport()) {
                body.classList.remove('sidebar-collapsed');
                sideNav.classList.remove('is-collapsed');
                if (adminLayout) {
                    adminLayout.classList.remove('sidebar-collapsed');
                }
                collapseBtn.setAttribute('aria-expanded', 'true');
                collapseBtn.setAttribute('aria-label', 'Collapse sidebar');
                collapseBtn.setAttribute('title', 'Collapse sidebar');
                return;
            }

            if (collapsed) {
                body.classList.add('sidebar-collapsed');
                sideNav.classList.add('is-collapsed');
                if (adminLayout) {
                    adminLayout.classList.add('sidebar-collapsed');
                }
                collapseBtn.setAttribute('aria-expanded', 'false');
                collapseBtn.setAttribute('aria-label', 'Expand sidebar');
                collapseBtn.setAttribute('title', 'Expand sidebar');
            } else {
                body.classList.remove('sidebar-collapsed');
                sideNav.classList.remove('is-collapsed');
                if (adminLayout) {
                    adminLayout.classList.remove('sidebar-collapsed');
                }
                collapseBtn.setAttribute('aria-expanded', 'true');
                collapseBtn.setAttribute('aria-label', 'Collapse sidebar');
                collapseBtn.setAttribute('title', 'Collapse sidebar');
            }

            if (persist) {
                try {
                    window.localStorage.setItem(storageKey, collapsed ? '1' : '0');
                } catch (e) {
                    // Ignore storage failures silently.
                }
            }
        }

        var savedCollapsed = false;
        try {
            savedCollapsed = window.localStorage.getItem(storageKey) === '1';
        } catch (e) {
            savedCollapsed = false;
        }

        setCollapsed(savedCollapsed, false);

        window.__toggleAdminSidebar = function() {
            var willCollapse = !sideNav.classList.contains('is-collapsed');
            setCollapsed(willCollapse, true);
        };

        collapseBtn.addEventListener('click', function(e){
            e.preventDefault();
            window.__toggleAdminSidebar();
        });

        window.addEventListener('resize', function(){
            var collapsed = false;
            try {
                collapsed = window.localStorage.getItem(storageKey) === '1';
            } catch (e) {
                collapsed = body.classList.contains('sidebar-collapsed');
            }
            setCollapsed(collapsed, false);
        });
    })();
</script>

