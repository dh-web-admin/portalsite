<?php
require_once __DIR__ . '/../../session_init.php';

// Check if user is logged in
if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    header('Location: /auth/login.php');
    exit();
}

require_once __DIR__ . '/../../config/config.php';

// Get user role for sidebar
$email = $_SESSION['email'];
$roleStmt = $conn->prepare('SELECT role FROM users WHERE email=? LIMIT 1');
$roleStmt->bind_param('s', $email);
$roleStmt->execute();
$roleRes = $roleStmt->get_result();
$user = $roleRes ? $roleRes->fetch_assoc() : null;
$role = $user ? $user['role'] : 'laborer';

// Check if developer is previewing as another role
if ($role === 'developer' && isset($_GET['preview_role'])) {
    $role = $_GET['preview_role'];
}

$roleStmt->close();

// Preserve preview mode in URLs
$previewParam = '';
if (isset($_GET['preview_role'])) {
    $previewParam = '?preview_role=' . urlencode($_GET['preview_role']);
}

$equipment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($equipment_id <= 0) { 
    echo "Invalid equipment ID."; 
    exit; 
}

$fileStmt = $conn->prepare("SELECT file_url, uploaded_at FROM equipment_uploads WHERE equipment_id = ? AND field = 'tires' ORDER BY uploaded_at DESC");
$fileStmt->bind_param('i', $equipment_id);
$fileStmt->execute();
$res = $fileStmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes" />
    <meta name="theme-color" content="#667eea" />
    <title>Tires Files</title>
    <link rel="stylesheet" href="../../assets/css/base.css" />
    <link rel="stylesheet" href="../../assets/css/admin-layout.css" />
    <link rel="stylesheet" href="../../assets/css/dashboard.css" />
</head>
<body class="admin-page">
    <div class="admin-container">
        <?php include __DIR__ . '/../../partials/portalheader.php'; ?>
        <div class="admin-layout">
            <?php include __DIR__ . '/../../partials/sidebar.php'; ?>
            <main class="content-area">
                <div class="main-content">
                    <div class="card">
                        <h1 class="success" style="text-align:center;">Tires Files</h1>
                        <h2 style="margin-top:0;font-size:1.2rem;font-weight:600;color:#222;">For Equipment #<?php echo $equipment_id; ?></h2>
                        <ul class="file-list">
                        <?php 
                        $isProduction = getenv('RAILWAY_ENVIRONMENT') !== false;
                        $fileCount = 0;
                        while ($row = $res->fetch_assoc()):
                            $fileCount++;
                            $fileUrl = $row['file_url'];
                            if ($isProduction) {
                                if (strpos($fileUrl, '/uploads/equipment/') !== 0) {
                                    $fileUrl = '/uploads/equipment/' . ltrim($fileUrl, '/');
                                }
                            } else {
                                if (strpos($fileUrl, '/PortalSite/uploads/equipment/') !== 0) {
                                    $fileUrl = '/PortalSite/uploads/equipment/' . ltrim($fileUrl, '/');
                                }
                            }
                            $ext = strtolower(pathinfo($fileUrl, PATHINFO_EXTENSION));
                            $isImage = in_array($ext, ['jpg','jpeg','png','gif','bmp','webp','svg']);
                        ?>
                            <li>
                                <?php if ($isImage): ?>
                                    <div style="margin-bottom:8px;"><img src="<?php echo htmlspecialchars($fileUrl); ?>" alt="Tires Image" style="max-width:220px;max-height:160px;display:block;border-radius:8px;box-shadow:0 2px 8px #0001;"></div>
                                <?php endif; ?>
                                <a href="<?php echo htmlspecialchars($fileUrl); ?>" target="_blank">View File (<?php echo htmlspecialchars($row['uploaded_at']); ?>)</a>
                            </li>
                        <?php endwhile; 
                        $fileStmt->close();
                        if ($fileCount === 0): ?>
                            <li style="color:#94a3b8;font-style:italic;">No tire files uploaded yet.</li>
                        <?php endif; ?>
                        </ul>
                        <a class="back-link" href="index.php<?php echo $previewParam; ?>">&larr; Back to Equipments</a>
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
    })();
    </script>
    <script src="../../assets/js/mobile-menu.js"></script>
    <script src="../../assets/js/logout-confirm.js"></script>
</body>
</html>
