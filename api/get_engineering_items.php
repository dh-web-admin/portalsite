<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

$result = $conn->query('SELECT id, name FROM engineering_items ORDER BY id ASC');
$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}
echo json_encode(['success' => true, 'items' => $items]);
