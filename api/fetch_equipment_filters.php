<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

$equipment_id = isset($_GET['equipment_id']) ? (int)$_GET['equipment_id'] : 0;
if (!$equipment_id) {
    echo json_encode([]);
    exit;
}

// Fetch all filters for this equipment from filter_info
$stmt = $conn->prepare('SELECT filter_id, equipment_id, filter_name, filter_date, hours, part_number, make FROM filter_info WHERE equipment_id = ? ORDER BY filter_id ASC');
$stmt->bind_param('i', $equipment_id);
$stmt->execute();
$res = $stmt->get_result();
$filters = [];
while ($row = $res->fetch_assoc()) {
    $filters[] = $row;
}
$stmt->close();

echo json_encode($filters);
?>
