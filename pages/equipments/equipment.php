
<?php
require_once __DIR__ . '/../../session_init.php';
require_once __DIR__ . '/../../config/config.php';

// Get equipment ID from query string
$equipmentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($equipmentId <= 0) {
    echo "<h2>Invalid equipment ID.</h2>";
    exit();
}

// Fetch equipment details
$stmt = $conn->prepare('SELECT * FROM equipments WHERE equipment_id = ? LIMIT 1');
$stmt->bind_param('i', $equipmentId);
$stmt->execute();
$res = $stmt->get_result();
$equipment = $res ? $res->fetch_assoc() : null;

if (!$equipment) {
    echo "<h2>Equipment not found.</h2>";
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Equipment Details</title>
    <link rel="stylesheet" href="../../assets/css/base.css" />
    <link rel="stylesheet" href="../../assets/css/admin-layout.css" />
    <link rel="stylesheet" href="../../assets/css/dashboard.css" />
    <link rel="stylesheet" href="../../pages/equipments/style.css" />
</head>
<body class="admin-page">
    <div class="admin-container">
        <?php include __DIR__ . '/../../partials/portalheader.php'; ?>
        <div class="admin-layout">
            <?php include __DIR__ . '/../../partials/sidebar.php'; ?>
            <main class="content-area">
                <div class="main-content">
                    <section class="equipment-page" aria-label="Equipment details">
                        <div class="equipment-topbar" role="region" aria-label="Equipment actions">
                            <a href="index.php" class="equipment-btn equipment-btn--gray" style="margin-right: 18px;">&larr; Back to Equipments</a>
                            <h2 style="margin: 0; font-size: 1.5rem; font-weight: 700; color: #0f172a;">Equipment Details</h2>
                        </div>
                        <div class="equipment-details-table-wrap" style="margin-top: 32px;">
                            <table class="project-table equipment-table" style="max-width: 600px;">
                                <tbody>
                                <?php foreach ($equipment as $key => $value): ?>
                                    <tr>
                                        <th style="text-align:left; padding: 8px 16px; background: #f8fafc; color: #334155; font-weight: 600; width: 180px;"> <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $key))); ?> </th>
                                        <td style="padding: 8px 16px; background: #fff; color: #0f172a;"> <?php echo htmlspecialchars($value); ?> </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
