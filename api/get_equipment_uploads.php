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

// Determine mount path for server-side checks
$isProduction = getenv('RAILWAY_ENVIRONMENT') !== false;
$uploads_mount = getenv('UPLOADS_MOUNT_PATH') ?: '/portalsite/uploads';

while ($row = $res->fetch_assoc()) {
    $f = $row['field'];
    $filename = $row['filename'] ?? basename($row['file_url'] ?? '');
    if (!$filename) continue;

    // Canonical public URL
    $publicUrl = '/uploads/equipment/' . $filename;

    // Server-side file path
    $filePath = rtrim($uploads_mount, '/') . '/equipment/' . $filename;

    // If the physical file doesn't exist, skip
    if (!file_exists($filePath)) {
        continue;
    }

    // If the DB contains a legacy path (e.g., /PortalSite/...), log it for diagnostics
    $stored = isset($row['file_url']) ? $row['file_url'] : '';
    if (strpos($stored, '/uploads/') !== 0) {
        // log mismatch
        $logfile = __DIR__ . '/../uploads/equipment/upload_debug.log';
        @file_put_contents($logfile, date('Y-m-d H:i:s') . " DB path mismatch for id={$row['id']} stored='{$stored}' expected='{$publicUrl}'\n", FILE_APPEND);
    }

    // Return canonical public URL regardless of what is stored
    $row['file_url'] = $publicUrl;

    if (isset($uploads[$f])) {
        $uploads[$f][] = $row;
    }
}
$stmt->close();

echo json_encode(['success' => true, 'uploads' => $uploads]);
