<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
if (!defined('IS_API')) define('IS_API', true);
require_once __DIR__ . '/../partials/permissions.php';

header('Content-Type: application/json; charset=utf-8');

if (isset($conn)) $GLOBALS['conn'] = $conn;
require_edit_api('engineering');

function hcs_num($key)
{
    $v = trim((string) ($_POST[$key] ?? ''));
    return $v === '' ? null : $v;
}

$number = trim((string) ($_POST['number'] ?? ''));
if ($number === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Number is required']);
    exit;
}

$partNo                 = trim((string) ($_POST['part_no'] ?? ''));
$description             = trim((string) ($_POST['description'] ?? ''));
$material                = trim((string) ($_POST['material'] ?? ''));
$qty                     = hcs_num('qty');
$length                  = hcs_num('length');
$idInches                = hcs_num('id_inches');
$odInches                = hcs_num('od_inches');
$wallThickness           = hcs_num('wall_thickness');
$pressureRatingPsi       = hcs_num('pressure_rating_psi');
$burstPressurePsi        = hcs_num('burst_pressure_psi');
$manufacturer            = trim((string) ($_POST['manufacturer'] ?? ''));
$manufacturerDescription = trim((string) ($_POST['manufacturer_description'] ?? ''));

$qtyInt = $qty === null ? 1 : (int) $qty;

$stmt = $conn->prepare(
    'INSERT INTO hydraulic_component_specs
        (number, part_no, description, manufacturer_description, material, qty, length,
         id_inches, od_inches, wall_thickness, pressure_rating_psi, burst_pressure_psi, manufacturer)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

$stmt->bind_param(
    'sssssiddddiis',
    $number, $partNo, $description, $manufacturerDescription, $material, $qtyInt,
    $length, $idInches, $odInches, $wallThickness,
    $pressureRatingPsi, $burstPressurePsi, $manufacturer
);

// mysqli's default error mode (PHP 8.1+) throws on a failed execute() —
// including a unique-constraint violation — rather than returning false,
// so a duplicate Number has to be caught, not branched on.
try {
    $ok = $stmt->execute();
} catch (mysqli_sql_exception $e) {
    $ok = false;
}

if ($ok) {
    echo json_encode(['success' => true, 'id' => $stmt->insert_id]);
} else {
    $dup = $stmt->errno === 1062; // uniq_number
    http_response_code($dup ? 409 : 500);
    echo json_encode([
        'success' => false,
        'message' => $dup ? 'A component with that Number already exists' : 'Failed to add component',
    ]);
}
$stmt->close();
