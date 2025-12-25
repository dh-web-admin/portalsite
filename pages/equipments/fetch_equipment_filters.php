<?php
require_once __DIR__ . '/../../config/config.php';
header('Content-Type: application/json');
$equipment_id = isset($_GET['equipment_id']) ? (int)$_GET['equipment_id'] : 0;
$out = [];
if ($equipment_id > 0) {
    $sql = "SELECT ef.filter_id, fn.filter_name, ef.filter_date, ef.hours, ef.part_number, ef.make
            FROM equipment_filters ef
            LEFT JOIN filter_names fn ON ef.filter_id = fn.filter_id
            WHERE ef.equipment_id = ?
            ORDER BY ef.filter_date DESC, ef.id DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $equipment_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $out[] = $row;
    }
    $stmt->close();
}
echo json_encode($out);
?>