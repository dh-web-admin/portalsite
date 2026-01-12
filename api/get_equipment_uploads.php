<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

$equipment_id = isset($_GET['equipment_id']) ? (int)$_GET['equipment_id'] : 0;
if (!$equipment_id) {
    echo json_encode(['success' => false, 'message' => 'Missing equipment_id']);
    exit;
}

$stmt = $conn->prepare("SELECT id, field, file_url, filename, created_at FROM uploads WHERE equipment_id = ?");
$stmt->bind_param('i', $equipment_id);
$stmt->execute();
$res = $stmt->get_result();
$uploads = [
    'air_filters' => [],
    'warranty' => [],
    'tires' => [],
    'dimension' => []
];

// Resolve mount and web prefix from env with sensible defaults
$isProduction = getenv('RAILWAY_ENVIRONMENT') !== false;
$uploads_mount = getenv('UPLOADS_MOUNT_PATH') ?: '/portalsite/uploads';
$uploads_web_prefix = getenv('UPLOADS_WEB_PREFIX') ?: '/PortalSite/uploads/equipment';

while ($row = $res->fetch_assoc()) {
    $f = $row['field'];
    $filename = $row['filename'] ?? basename($row['file_url'] ?? '');
    if (!$filename) continue;

    if ($isProduction) {
        $filePath = rtrim($uploads_mount, '/') . '/equipment/' . $filename;
        $url = rtrim($uploads_web_prefix, '/') . '/' . $filename;
    } else {
        $filePath = __DIR__ . '/../uploads/equipment/' . $filename;
        $url = rtrim($uploads_web_prefix, '/') . '/' . $filename;
    }

    if (!file_exists($filePath)) continue;
    $row['file_url'] = preg_replace('#/+#', '/', $url);

    if (isset($uploads[$f])) {
        $uploads[$f][] = $row;
    }
}
$stmt->close();

echo json_encode(['success' => true, 'uploads' => $uploads]);
