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
    'tires' => []
];
// Detect environment (same as config.php)
$isProduction = getenv('RAILWAY_ENVIRONMENT') !== false;
$filePrefix = $isProduction ? '/uploads/equipment/' : '/PortalSite/uploads/equipment/';
while ($row = $res->fetch_assoc()) {
    $f = $row['field'];
    // Ensure file_url is a full web path, but don't double-prefix
    if (isset($row['file_url']) && strpos($row['file_url'], 'uploads/equipment/') === false) {
        $row['file_url'] = $filePrefix . ltrim($row['file_url'], '/');
    } else if (isset($row['file_url']) && strpos($row['file_url'], $filePrefix) !== 0) {
        // If the file_url is already prefixed but not with the right prefix, fix it
        $row['file_url'] = $filePrefix . basename($row['file_url']);
    }
    if (isset($uploads[$f])) $uploads[$f][] = $row;
}
$stmt->close();

echo json_encode(['success' => true, 'uploads' => $uploads]);
