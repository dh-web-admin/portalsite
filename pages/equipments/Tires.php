<?php
require_once __DIR__ . '/../../session_init.php';
if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
        header('Location: /auth/login.php');
        exit();
}
require_once __DIR__ . '/../../config/config.php';
$equipment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($equipment_id <= 0) { echo "Invalid equipment ID."; exit; }
$stmt = $conn->prepare("SELECT file_url, uploaded_at FROM equipment_uploads WHERE equipment_id = ? AND field = 'tires' ORDER BY uploaded_at DESC");
$stmt->bind_param('i', $equipment_id);
$stmt->execute();
$res = $stmt->get_result();
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
</head>
<body class="admin-page">
    <div class="admin-container">
        <?php include __DIR__ . '/../../partials/portalheader.php'; ?>
        <div class="admin-layout">
            <main class="content-area">
                <div class="main-content">
                    <div class="card">
                        <h1 class="success" style="text-align:center;">Tires Files</h1>
                        <h2 style="margin-top:0;font-size:1.2rem;font-weight:600;color:#222;">For Equipment #<?php echo $equipment_id; ?></h2>
                        <ul class="file-list">
                        <?php while ($row = $res->fetch_assoc()):
                            $fileUrl = '../../' . $row['file_url'];
                            $ext = strtolower(pathinfo($fileUrl, PATHINFO_EXTENSION));
                            $isImage = in_array($ext, ['jpg','jpeg','png','gif','bmp','webp','svg']);
                        ?>
                            <li>
                                <?php if ($isImage): ?>
                                    <div style="margin-bottom:8px;"><img src="<?php echo htmlspecialchars($fileUrl); ?>" alt="Tires Image" style="max-width:220px;max-height:160px;display:block;border-radius:8px;box-shadow:0 2px 8px #0001;"></div>
                                <?php endif; ?>
                                <a href="<?php echo htmlspecialchars($fileUrl); ?>" target="_blank">View File (<?php echo htmlspecialchars($row['uploaded_at']); ?>)</a>
                            </li>
                        <?php endwhile; ?>
                        </ul>
                        <a class="back-link" href="index.php">&larr; Back to Equipments</a>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script src="../../assets/js/logout-confirm.js"></script>
</body>
