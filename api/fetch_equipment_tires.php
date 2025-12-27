<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

$equipment_id = isset($_GET['equipment_id']) ? (int)$_GET['equipment_id'] : 0;
if (!$equipment_id) {
    echo json_encode([]);
    exit;
}

$stmt = $conn->prepare('SELECT * FROM tire_info WHERE equipment_id = ? ORDER BY tire_id ASC');
$stmt->bind_param('i', $equipment_id);
$stmt->execute();
$res = $stmt->get_result();
$tires = [];
while ($row = $res->fetch_assoc()) {
    $tires[] = $row;
}
$stmt->close();
echo json_encode($tires);
