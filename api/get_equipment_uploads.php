<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

$equipment_id = isset($_GET['equipment_id']) ? (int)$_GET['equipment_id'] : 0;
if (!$equipment_id) {
    echo json_encode(['success' => false, 'message' => 'Missing equipment_id']);
    exit;
}

$stmt = $conn->prepare("SELECT id, field, file_url, uploaded_at FROM equipment_uploads WHERE equipment_id = ?");
$stmt->bind_param('i', $equipment_id);
$stmt->execute();
$res = $stmt->get_result();
$uploads = [
    'air_filters' => [],
    'warranty' => [],
    'tires' => [],
    'dimension' => []
];

// Detect environment (same as config.php)
$isProduction = getenv('RAILWAY_ENVIRONMENT') !== false;
while ($row = $res->fetch_assoc()) {
    $f = $row['field'];
    if (!isset($row['file_url'])) {
        continue;
    }

    // Normalize DB URL and resolve to a concrete filename
    $dbUrl = str_replace('\\', '/', $row['file_url']);
    $dbUrl = ltrim($dbUrl, '/');
    $filename = basename($dbUrl);
    if ($filename === '') {
        continue;
    }

    // Resolve physical path in uploads/equipment (same as add_equipment_upload.php)
    $filePath = $isProduction
        ? '/app/PortalSite/uploads/equipment/' . $filename
        : __DIR__ . '/../uploads/equipment/' . $filename;

    if (!file_exists($filePath)) {
        // Skip orphaned rows where the file no longer exists
        continue;
    }

    // Always expose URLs in the same format used by issue pictures
    $url = '/PortalSite/uploads/equipment/' . $filename;
    $row['file_url'] = preg_replace('#/+#', '/', $url);

    if (isset($uploads[$f])) {
        $uploads[$f][] = $row;
    }
}
$stmt->close();

echo json_encode(['success' => true, 'uploads' => $uploads]);
