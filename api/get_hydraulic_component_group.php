<?php
// Returns every hydraulic_component_specs row belonging to one diagram
// component id — the whole number itself plus its lettered callouts and
// decimal sub-parts (e.g. id "8" -> "8", "8a", "8b", "8c"), matched the same
// way the parts list sorts them: by the leading integer run of `number`.
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_SESSION['email'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$id = isset($_GET['id']) ? trim((string) $_GET['id']) : '';
if ($id === '' || !ctype_digit($id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid component id']);
    exit;
}

$tableCheck = $conn->query("SHOW TABLES LIKE 'hydraulic_component_specs'");
if (!$tableCheck || $tableCheck->num_rows === 0) {
    echo json_encode(['success' => true, 'components' => []]);
    exit;
}

$stmt = $conn->prepare(
    "SELECT number, part_no, description, manufacturer_description, material,
            qty, length, id_inches, od_inches, wall_thickness,
            pressure_rating_psi, burst_pressure_psi, manufacturer
     FROM hydraulic_component_specs
     WHERE REGEXP_SUBSTR(number, '^[0-9]+') = ?
     ORDER BY
         CASE
             WHEN number REGEXP '^[0-9]+$'   THEN 0
             WHEN number REGEXP '^[0-9]+[.]' THEN 2
             ELSE 1
         END ASC,
         CASE
             WHEN number REGEXP '^[0-9]+[.]'
                 THEN CAST(REGEXP_SUBSTR(number, '[0-9]+$') AS UNSIGNED)
             ELSE 0
         END ASC,
         number ASC"
);
$stmt->bind_param('s', $id);
$stmt->execute();
$result = $stmt->get_result();

$components = [];
while ($row = $result->fetch_assoc()) {
    $components[] = $row;
}
$stmt->close();

echo json_encode(['success' => true, 'components' => $components]);
