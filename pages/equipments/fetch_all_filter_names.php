<?php
require_once __DIR__ . '/../../config/config.php';
header('Content-Type: application/json');
$out = [];
$res = $conn->query('SELECT filter_id, filter_name FROM filter_names ORDER BY filter_id ASC');
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $out[] = $row;
    }
}
echo json_encode($out);
?>