<?php
require_once __DIR__ . '/../session_init.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$equipment_id = isset($_POST['equipment_id']) ? (int)$_POST['equipment_id'] : 0;
$dimension_id = isset($_POST['dimension_id']) ? (int)$_POST['dimension_id'] : 0;

// Fields
$dhss_number        = trim($_POST['dhss_number'] ?? '');
$make               = trim($_POST['make'] ?? '');
$total_height       = trim($_POST['total_height'] ?? '');
$ground_clearance   = trim($_POST['ground_clearance'] ?? '');
$total_width        = trim($_POST['total_width'] ?? '');
$axle_width         = trim($_POST['axle_width'] ?? '');
$weight             = trim($_POST['weight'] ?? '');
$length_rear_tire   = trim($_POST['length_rear_tire'] ?? '');
$length_auger       = trim($_POST['length_auger'] ?? '');
$loa                = trim($_POST['loa'] ?? '');

if ($equipment_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid equipment_id']);
    exit;
}

// Safety: ensure equipment exists
$chk = $conn->prepare("SELECT equipment_id FROM equipments WHERE equipment_id = ? LIMIT 1");
$chk->bind_param('i', $equipment_id);
$chk->execute();
$chkRes = $chk->get_result();
$exists = $chkRes && $chkRes->num_rows > 0;
$chk->close();

if (!$exists) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Equipment not found']);
    exit;
}

// Update equipments.dhss_equipment_number + make if provided (optional but matches your modal)
$uEq = $conn->prepare("UPDATE equipments SET dhss_equipment_number = ?, make = ? WHERE equipment_id = ?");
$uEq->bind_param('ssi', $dhss_number, $make, $equipment_id);
$uEq->execute();
$uEq->close();

// Find existing dimension row if dimension_id missing
if ($dimension_id <= 0) {
    $q = $conn->prepare("SELECT dimension_id FROM dimensions WHERE equipment_id = ? LIMIT 1");
    $q->bind_param('i', $equipment_id);
    $q->execute();
    $r = $q->get_result();
    $row = $r ? $r->fetch_assoc() : null;
    $dimension_id = $row ? (int)$row['dimension_id'] : 0;
    $q->close();
}

if ($dimension_id > 0) {
    // UPDATE
    $stmt = $conn->prepare("
        UPDATE dimensions SET
            total_height = ?,
            ground_clearance = ?,
            total_width = ?,
            axle_width = ?,
            weight = ?,
            length_rear_tire = ?,
            length_auger = ?,
            loa = ?
        WHERE dimension_id = ? AND equipment_id = ?
    ");
    $stmt->bind_param(
        'ssssssssii',
        $total_height,
        $ground_clearance,
        $total_width,
        $axle_width,
        $weight,
        $length_rear_tire,
        $length_auger,
        $loa,
        $dimension_id,
        $equipment_id
    );
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'DB update failed']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Updated', 'dimension_id' => $dimension_id]);
    exit;
} else {
    // INSERT
    $stmt = $conn->prepare("
        INSERT INTO dimensions
            (equipment_id, total_height, ground_clearance, total_width, axle_width, weight, length_rear_tire, length_auger, loa)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        'issssssss',
        $equipment_id,
        $total_height,
        $ground_clearance,
        $total_width,
        $axle_width,
        $weight,
        $length_rear_tire,
        $length_auger,
        $loa
    );
    $ok = $stmt->execute();
    $newId = $stmt->insert_id;
    $stmt->close();

    if (!$ok) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'DB insert failed']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Inserted', 'dimension_id' => $newId]);
    exit;
}
