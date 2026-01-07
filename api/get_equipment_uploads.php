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
    'warranty'   => [],
    'tires'      => [],
    'dimension'  => []
];

// Detect environment (same as config.php)
$isProduction = getenv('RAILWAY_ENVIRONMENT') !== false;
// Base URL that the browser should use for images
$publicBase = $isProduction ? '/uploads/equipment/' : '/PortalSite/uploads/equipment/';

while ($row = $res->fetch_assoc()) {
    $f = $row['field'];
    if (!isset($uploads[$f])) {
        // Ignore unknown fields
        continue;
    }

    $raw = isset($row['file_url']) ? trim($row['file_url']) : '';
    if ($raw === '') {
        continue;
    }

    // Normalise legacy values and extract just the filename.
    $raw = str_replace('\\', '/', $raw);
    $raw = ltrim($raw, '/');
    $filename = basename($raw);
    if ($filename === '' || $filename === '.' || $filename === '..') {
        continue;
    }

    // Build the environment-correct public URL for the frontend.
    $row['file_url'] = preg_replace('#/+#', '/', $publicBase . $filename);
    $uploads[$f][] = $row;
}
$stmt->close();

echo json_encode(['success' => true, 'uploads' => $uploads]);
