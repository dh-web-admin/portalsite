<?php
/**
 * Developer Preview Notch
 * Include this file after portalheader.php to show developer preview controls
 */

// Check if user is a developer
$devEmail = $_SESSION['email'] ?? '';
if ($devEmail && isset($conn)) {
    $devStmt = $conn->prepare("SELECT role FROM users WHERE email = ? LIMIT 1");
    $devStmt->bind_param('s', $devEmail);
    $devStmt->execute();
    $devRes = $devStmt->get_result();
    $devUser = $devRes->fetch_assoc();
    $devActualRole = $devUser['role'] ?? 'laborer';
    $devStmt->close();
    
    if ($devActualRole === 'developer'):
        // Include URL helper if not already loaded
        if (!function_exists('base_url')) {
            require_once __DIR__ . '/url.php';
        }
?>
        <!-- Developer Preview Notch -->
    <link rel="stylesheet" href="<?php echo htmlspecialchars(base_url('/pages/dashboard/style.css')); ?>">
        <div class="dev-notch">
            <a href="<?php echo htmlspecialchars(base_url('/pages/dashboard/index.php')); ?>" class="notch-btn" style="text-decoration: none;">Back to Dashboard</a>
            <button class="notch-btn" id="previewAsBtn"><?php echo isset($_GET['preview_role']) ? 'Exit Preview' : 'Preview as'; ?></button>
        </div>
        
        <!-- Preview Indicator -->
        <?php if (isset($_GET['preview_role'])): ?>
        <div class="preview-indicator">
            Previewing as <strong><?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($_GET['preview_role']))); ?></strong>
        </div>
        <?php endif; ?>
        
        <!-- Role Selection Dropdown -->
        <div class="role-dropdown" id="roleDropdown">
            <button class="role-option" data-role="admin">Admin</button>
            <button class="role-option" data-role="projectmanager">Project Manager</button>
            <button class="role-option" data-role="estimator">Estimator</button>
            <button class="role-option" data-role="accounting">Accounting</button>
            <button class="role-option" data-role="superintendent">Superintendent</button>
            <button class="role-option" data-role="foreman">Foreman</button>
            <button class="role-option" data-role="mechanic">Mechanic</button>
            <button class="role-option" data-role="operator">Operator</button>
            <button class="role-option" data-role="laborer">Laborer</button>
        </div>
        
        <!-- Toast Notification -->
        <div class="toast" id="previewToast"></div>
        
        <!-- Developer Preview JavaScript -->
        <script>
        (function(){
            var previewAsBtn = document.getElementById('previewAsBtn');
            var roleDropdown = document.getElementById('roleDropdown');
            var previewToast = document.getElementById('previewToast');
            
            function showToast(message) {
                if (previewToast) {
                    previewToast.textContent = message;
                    previewToast.classList.add('show');
                    setTimeout(function() {
                        previewToast.classList.remove('show');
                    }, 5000);
                }
            }
            
            if (previewAsBtn && roleDropdown) {
                var urlParams = new URLSearchParams(window.location.search);
                var currentPreview = urlParams.get('preview_role');
                var isInPreview = currentPreview !== null;
                
                previewAsBtn.addEventListener('click', function(e){
                    e.stopPropagation();
                    
                    if (isInPreview) {
                        var roleName = currentPreview.replace('_', ' ');
                        roleName = roleName.charAt(0).toUpperCase() + roleName.slice(1);
                        showToast('Exited ' + roleName + ' preview');
                        
                        setTimeout(function() {
                            // Exit preview: always land on base dashboard (no preview param)
                            window.location.href = '<?php echo htmlspecialchars(base_url('/pages/dashboard/index.php')); ?>';
                        }, 400);
                    } else {
                        roleDropdown.classList.toggle('active');
                    }
                });
                
                document.addEventListener('click', function(e){
                    if (!roleDropdown.contains(e.target) && e.target !== previewAsBtn) {
                        roleDropdown.classList.remove('active');
                    }
                });
                
                var roleOptions = roleDropdown.querySelectorAll('.role-option');
                roleOptions.forEach(function(option){
                    option.addEventListener('click', function(){
                        var selectedRole = this.getAttribute('data-role');
                        // Start preview: navigate to default landing page for that role
                        // All roles share the dashboard index as landing; the server/header adjusts view.
                        var target = '<?php echo htmlspecialchars(base_url('/pages/dashboard/index.php')); ?>' + '?preview_role=' + encodeURIComponent(selectedRole);
                        window.location.href = target;
                    });
                });
                
                if (currentPreview) {
                    roleOptions.forEach(function(option){
                        if (option.getAttribute('data-role') === currentPreview) {
                            option.classList.add('current');
                        } else {
                            option.classList.remove('current');
                        }
                    });
                }
            }
        })();
        </script>
<?php 
    endif;
}
?>
