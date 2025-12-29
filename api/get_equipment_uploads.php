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
    if (isset($row['file_url'])) {
        $url = str_replace('\\', '/', $row['file_url']);
        $url = ltrim($url, '/');
        // Normalize for production
        if ($isProduction) {
            if (strpos($url, 'uploads/equipment/') === 0) {
                $row['file_url'] = '/' . $url;
            } else {
                $row['file_url'] = '/uploads/equipment/' . $url;
            }
        } else {
            // Local: always prefix with /PortalSite/
            if (strpos($url, 'PortalSite/uploads/equipment/') === 0) {
                $row['file_url'] = '/' . $url;
            } else if (strpos($url, 'uploads/equipment/') === 0) {
                $row['file_url'] = '/PortalSite/' . $url;
            } else {
                $row['file_url'] = '/PortalSite/uploads/equipment/' . $url;
            }
        }
    }
    if (isset($uploads[$f])) $uploads[$f][] = $row;
}
$stmt->close();

echo json_encode(['success' => true, 'uploads' => $uploads]);
